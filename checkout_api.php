<?php
ob_start();
session_start();
include 'db_config.php';
require 'vendor/autoload.php';
use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json');

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to proceed with checkout.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['data'])) {
    error_log("Invalid request data: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

$data = json_decode($_POST['data'], true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data.']);
    exit;
}

if (!isset($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token mismatch. Received: " . ($data['csrf_token'] ?? 'Not set') . ", Expected: " . ($_SESSION['csrf_token'] ?? 'Not set'));
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

if (!isset($data['full_name'], $data['email'], $data['address'], $data['city'], $data['postal_code'], $data['payment_method'], $data['cart'])) {
    error_log("Missing required form fields: " . print_r($data, true));
    echo json_encode(['success' => false, 'message' => 'Missing required form fields.']);
    exit;
}

if ($data['payment_method'] === 'bank_transfer' && !isset($data['bank_transfer_type'])) {
    error_log("Missing bank_transfer_type for bank transfer payment.");
    echo json_encode(['success' => false, 'message' => 'Please select a bank transfer type.']);
    exit;
}

try {
    $user_id = getUserId($pdo, $_SESSION['user']);
    if (!$user_id) {
        error_log("Invalid user ID for session: " . print_r($_SESSION['user'], true));
        echo json_encode(['success' => false, 'message' => 'Invalid user session.']);
        exit;
    }
} catch (Exception $e) {
    error_log("Error getting user ID: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error validating user: ' . $e->getMessage()]);
    exit;
}

$full_name = $data['full_name'];
$email = $data['email'];
$address = $data['address'];
$city = $data['city'];
$postal_code = $data['postal_code'];
$payment_method = $data['payment_method'];
$bank_transfer_type = $data['bank_transfer_type'] ?? null;
$paypal_order_id = $data['paypal_order_id'] ?? null;
$total = floatval(str_replace('RM ', '', $data['cart']['total']));
$cart = $data['cart'];

if (!isset($cart['items']) || !is_array($cart['items']) || empty($cart['items'])) {
    error_log("Invalid or empty cart data: " . print_r($cart, true));
    echo json_encode(['success' => false, 'message' => 'Cart is empty or invalid.']);
    exit;
}

error_log("Starting checkout for user_id: $user_id, total: $total, payment_method: $payment_method, bank_transfer_type: " . ($bank_transfer_type ?? 'none'));

try {
    $pdo->beginTransaction();

    $payment_status = 'Pending';
    $payment_transaction_id = null;
    $bank_name = null;

    if ($payment_method === 'credit_card') {
        if ($paypal_order_id) {
            $paypal_client_id = $_ENV['PAYPAL_CLIENT_ID'] ?? '';
            $paypal_secret = $_ENV['PAYPAL_SECRET'] ?? '';
            if (!empty($paypal_client_id) && !empty($paypal_secret)) {
                $ch = curl_init('https://api-m.sandbox.paypal.com/v1/oauth2/token');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
                curl_setopt($ch, CURLOPT_USERPWD, "$paypal_client_id:$paypal_secret");
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Accept-Language: en_US']);
                $response = curl_exec($ch);
                if (curl_errno($ch)) {
                    error_log("PayPal auth error: " . curl_error($ch));
                } else {
                    $auth_data = json_decode($response, true);
                    curl_close($ch);
                    if (isset($auth_data['access_token'])) {
                        $access_token = $auth_data['access_token'];
                        $ch = curl_init("https://api-m.sandbox.paypal.com/v2/checkout/orders/$paypal_order_id/capture");
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            "Authorization: Bearer $access_token",
                            'PayPal-Request-Id: ' . uniqid(),
                        ]);
                        $response = curl_exec($ch);
                        if (curl_errno($ch)) {
                            error_log("PayPal capture error: " . curl_error($ch));
                        } else {
                            $capture_data = json_decode($response, true);
                            curl_close($ch);
                            if ($capture_data['status'] === 'COMPLETED') {
                                $payment_status = 'Completed';
                                $payment_transaction_id = $capture_data['id'];
                                error_log("PayPal payment captured: transaction_id=$payment_transaction_id");
                            }
                        }
                    }
                }
            }
            // For testing, proceed with order even if PayPal payment fails or is not completed
            if ($payment_status !== 'Completed') {
                error_log("PayPal payment not completed, treating as Pending for testing. paypal_order_id=$paypal_order_id");
                $payment_transaction_id = $paypal_order_id; // Store PayPal order ID as transaction ID
            }
        } else {
            error_log("No PayPal order ID provided, treating as Pending for testing.");
        }
    } elseif ($payment_method === 'bank_transfer' && in_array($bank_transfer_type, ['public_bank', 'maybank', 'cimb'])) {
        $payment_status = 'Pending';
        $bank_name = $bank_transfer_type === 'public_bank' ? 'Public Bank' : ($bank_transfer_type === 'maybank' ? 'Maybank' : 'CIMB Bank');
        error_log("Direct bank transfer selected: bank=$bank_name");
    } elseif ($payment_method === 'cash_on_delivery') {
        $payment_status = 'Pending';
        error_log("Cash on Delivery selected, no PayPal processing required.");
    } else {
        throw new Exception("Invalid payment method or bank transfer type.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, total_amount, shipping_address, city, postal_code, payment_method, payment_status, payment_transaction_id, bank_name, order_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $total, $address, $city, $postal_code, $payment_method, $payment_status, $payment_transaction_id, $bank_name]);
    $order_id = $pdo->lastInsertId();
    error_log("Order inserted: $order_id");

    foreach ($cart['items'] as $item) {
        $stmt = $pdo->prepare("SELECT prod_Id FROM products WHERE prod_Id = ?");
        $stmt->execute([$item['prod_Id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid product ID: " . $item['prod_Id']);
        }
        $price = ($item['is_on_sale'] && $item['sale_price'] > 0) ? $item['sale_price'] : $item['prod_Price'];
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, prod_id, quantity, price, color) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$order_id, $item['prod_Id'], $item['quantity'], $price, $item['color'] ?? 'Default']);
    }

    $tracking_number = null;
    if ($payment_status === 'Completed' || $payment_method === 'cash_on_delivery') {
        $tracking_number = createShippingLabel($order_id, $full_name, $address, $city, $postal_code);
        if ($tracking_number) {
            $stmt = $pdo->prepare("UPDATE orders SET tracking_number = ? WHERE order_id = ?");
            $stmt->execute([$tracking_number, $order_id]);
            error_log("Shipping label created: tracking_number=$tracking_number");
        }
    }

    // Clear the cart after successful order creation
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    error_log("Cart cleared for user_id: $user_id");

    $pdo->commit();
    sendNotification($full_name, $email, $order_id, $tracking_number, $cart, $address, $city, $postal_code, $payment_method, $payment_status, $payment_transaction_id, $bank_name);

    // Redirection Logic
    $redirectUrl = null;
    if ($payment_method === 'bank_transfer' && in_array($bank_transfer_type, ['public_bank', 'maybank', 'cimb'])) {
        switch ($bank_transfer_type) {
            case 'public_bank':
                $redirectUrl = "https://www.pbebank.com/Personal-Banking/Services/Payment-Services?amount={$total}&order_id={$order_id}&return_url=" . urlencode("https://yourdomain.com/order_confirmation.php?order_id={$order_id}");
                break;
            case 'maybank':
                $redirectUrl = "https://www.maybank2u.com.my/mbb/m2u/common/M2ULoginPblController.do?amount={$total}&order_id={$order_id}&return_url=" . urlencode("https://yourdomain.com/order_confirmation.php?order_id={$order_id}");
                break;
            case 'cimb':
                $redirectUrl = "https://www.cimbclicks.com.my/clicks/login?amount={$total}&order_id={$order_id}&return_url=" . urlencode("https://yourdomain.com/order_confirmation.php?order_id={$order_id}");
                break;
        }
    }

    echo json_encode(['success' => true, 'message' => 'Order placed successfully.', 'order_id' => $order_id, 'redirectUrl' => $redirectUrl]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Checkout error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error during checkout: ' . $e->getMessage()]);
}

function createShippingLabel($order_id, $full_name, $address, $city, $postal_code) {
    try {
        $ch = curl_init('https://api.easyparcel.com/v1/ship/label');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'api_key' => $_ENV['EASYPARCEL_API_KEY'] ?? '',
            'order_id' => $order_id,
            'recipient_name' => $full_name,
            'address' => $address,
            'city' => $city,
            'postal_code' => $postal_code
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log("cURL error in EasyParcel: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        curl_close($ch);
        $result = json_decode($response, true);
        error_log("EasyParcel response: " . print_r($result, true));
        return $result['tracking_number'] ?? null;
    } catch (Exception $e) {
        error_log("Shipping label creation failed: " . $e->getMessage());
        return null;
    }
}

function sendNotification($full_name, $email, $order_id, $tracking_number, $cart, $address, $city, $postal_code, $payment_method, $payment_status, $payment_transaction_id, $bank_name = null) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.mail.me.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'] ?? 'yiwen1333@icloud.com';
        $mail->Password = $_ENV['SMTP_PASSWORD'] ?? 'bpgt-uhtr-jdkx-lajg';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom($_ENV['SMTP_USERNAME'] ?? 'yiwen1333@icloud.com', 'JellyHome');
        $mail->addAddress($email);
        $mail->Subject = 'Order Confirmation - #' . $order_id;

        $orderItemsHtml = '';
        $totalAmount = 0;
        foreach ($cart['items'] as $item) {
            $price = ($item['is_on_sale'] && $item['sale_price'] > 0) ? $item['sale_price'] : $item['prod_Price'];
            $itemTotal = $price * $item['quantity'];
            $totalAmount += $itemTotal;
            $orderItemsHtml .= "
                <tr>
                    <td>" . htmlspecialchars($item['prod_Name']) . "</td>
                    <td>" . htmlspecialchars($item['quantity']) . "</td>
                    <td>RM " . number_format($itemTotal, 2) . "</td>
                </tr>
            ";
        }

        $paymentDetails = '';
        if ($payment_method === 'cash_on_delivery') {
            $paymentDetails = "<p><strong>Payment Method:</strong> Cash on Delivery</p>";
        } elseif ($payment_method === 'credit_card') {
            $paymentDetails = "<p><strong>Payment Method:</strong> Credit/Debit Card (PayPal)<br><strong>Transaction ID:</strong> " . ($payment_transaction_id ?? 'N/A') . "<br><strong>Payment Status:</strong> $payment_status</p>";
        } elseif ($payment_method === 'bank_transfer') {
            $bank_details = '';
            if ($bank_name === 'Public Bank') {
                $bank_details = "<p>Bank: Public Bank<br>Account Number: 1234-5678-9012-3456<br>Account Name: JellyHome Sdn Bhd</p>";
            } elseif ($bank_name === 'Maybank') {
                $bank_details = "<p>Bank: Maybank<br>Account Number: 9876-5432-1098-7654<br>Account Name: JellyHome Sdn Bhd</p>";
            } elseif ($bank_name === 'CIMB Bank') {
                $bank_details = "<p>Bank: CIMB Bank<br>Account Number: 5678-1234-9012-3456<br>Account Name: JellyHome Sdn Bhd</p>";
            }
            $paymentDetails = "<p><strong>Payment Method:</strong> Direct Bank Transfer ($bank_name)<br><strong>Payment Status:</strong> $payment_status<br><strong><br>Instructions:</strong><br>Please transfer the total amount of RM " . number_format($totalAmount, 2) . " to the following account:<br>$bank_details</p>";
        }

        $mail->isHTML(true);
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: 'Arial', sans-serif; color: #333; line-height: 1.6; }
                    .container { width: 100%; max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                    .header { background: #1e4b51; color: #fff; text-align: center; padding: 10px 0; }
                    .content { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                    .order-summary table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    .order-summary th, .order-summary td { padding: 10px; border-bottom: 1px solid #eee; text-align: center; }
                    .order-summary th { background: #f1f1f1; }
                    .total-row { font-weight: bold; color: #1e4b51; }
                    .tracking { color: #28a745; font-weight: bold; }
                    .footer { text-align: center; padding: 10px; font-size: 12px; color: #777; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>JELLYHOME</h2>
                    </div>
                    <div class='content'>
                        <h3>Order Confirmation - #$order_id</h3>
                        <p>Dear $full_name,</p>
                        <p>Thank you for your purchase! Your order has been successfully placed on " . date('F j, Y, g:i a', time()) . ". Below are the details:</p>
                        <div class='order-summary'>
                            <h4>Order Summary</h4>
                            <table>
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                </tr>
                                $orderItemsHtml
                                <tr class='total-row'>
                                    <td colspan='2'>Total</td>
                                    <td>RM " . number_format($totalAmount, 2) . "</td>
                                </tr>
                            </table>
                        </div>
                        <div class='customer-details'>
                            <h4>Customer Details</h4>
                            <p><strong>Name:</strong> " . htmlspecialchars($full_name) . "</p>
                            <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                            <p><strong>Shipping Address:</strong> " . htmlspecialchars($address) . ", " . htmlspecialchars($city) . ", " . htmlspecialchars($postal_code) . "</p>
                        </div>
                        <div class='payment-details'>
                            <h4>Payment Details</h4>
                            $paymentDetails
                        </div>
                        <div class='status'>
                            <h4>Status</h4>
                            <p>Your order is currently <strong>" . ($tracking_number ? 'Shipped' : 'Pending') . "</strong>.</p>
                            " . ($tracking_number ? "<p class='tracking'>Tracking Number: $tracking_number<br><a href='https://yourstore.com/track?order=$order_id'>Track Your Order</a></p>" : '') . "
                        </div>
                        <p>If you have any questions, feel free to contact us at support@jellyhome.com or +6012-3456789.</p>
                    </div>
                    <div class='footer'>
                        <p>© 2025 JellyHome. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        error_log("Email notification sent to $email for order_id: $order_id");
    } catch (Exception $e) {
        error_log("Notification failed: " . $e->getMessage());
    }
}

function getUserId($pdo, $session_user) {
    if (is_array($session_user) && isset($session_user['user_id'])) {
        error_log("Using user_id from session: " . $session_user['user_id']);
        return $session_user['user_id'];
    }
    error_log("Invalid session user data: " . print_r($session_user, true));
    throw new Exception("Invalid user session data.");
}

ob_end_flush();
?>