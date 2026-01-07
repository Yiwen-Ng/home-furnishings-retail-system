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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_POST['product_id'], $_POST['full_name'], $_POST['email'], $_POST['contact_preference'], $_POST['csrf_token'])) {
    error_log("Missing required fields: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token mismatch. Received: " . ($_POST['csrf_token'] ?? 'Not set') . ", Expected: " . ($_SESSION['csrf_token'] ?? 'Not set'));
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$product_id = (int)$_POST['product_id'];
$full_name = trim($_POST['full_name']);
$email = trim($_POST['email']);
$contact_preference = $_POST['contact_preference'];
$custom_dimensions = trim($_POST['custom_dimensions'] ?? '');
$custom_color = trim($_POST['custom_color'] ?? '');
$additional_notes = trim($_POST['additional_notes'] ?? '');
$file_path = null;

// Fetch dynamic customization options
$stmt = $pdo->prepare("SELECT option_type FROM product_customizations WHERE product_id = ?");
$stmt->execute([$product_id]);
$custom_options = $stmt->fetchAll(PDO::FETCH_COLUMN);
$custom_data = [];
foreach ($custom_options as $option_type) {
    if (isset($_POST['custom_' . $option_type])) {
        $custom_data[$option_type] = trim($_POST['custom_' . $option_type]);
    }
}

// Handle file upload
if (isset($_FILES['engraving_image']) && $_FILES['engraving_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/engraving_images/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create upload directory: $upload_dir");
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
            exit;
        }
    }

    // Check directory permissions
    if (!is_writable($upload_dir)) {
        error_log("Upload directory ($upload_dir) is not writable by web server (user: daemon).");
        echo json_encode(['success' => false, 'message' => 'Upload directory is not writable.']);
        exit;
    }

    // Validate file type and size
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = mime_content_type($_FILES['engraving_image']['tmp_name']);
    $file_size = $_FILES['engraving_image']['size'] / 1024 / 1024; // Size in MB
    
    if (!in_array($file_type, $allowed_types)) {
        error_log("Invalid file type: $file_type");
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.']);
        exit;
    }
    
    if ($file_size > 5) {
        error_log("File size exceeds 5MB: $file_size MB");
        echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit.']);
        exit;
    }
    
    $file_name = uniqid('engraving_') . '_' . basename($_FILES['engraving_image']['name']);
    $file_path = $upload_dir . $file_name;
    
    if (!move_uploaded_file($_FILES['engraving_image']['tmp_name'], $file_path)) {
        $error_code = $_FILES['engraving_image']['error'];
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => "File exceeds PHP's upload_max_filesize.",
            UPLOAD_ERR_FORM_SIZE => "File exceeds form's MAX_FILE_SIZE.",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the upload."
        ];
        $error_message = $error_messages[$error_code] ?? "Unknown upload error.";
        error_log("Failed to move uploaded file to $file_path. Error code: $error_code ($error_message)");
        echo json_encode(['success' => false, 'message' => "Error uploading file: $error_message"]);
        exit;
    }
}

if (!in_array($contact_preference, ['call', 'email'])) {
    error_log("Invalid contact preference: " . $contact_preference);
    echo json_encode(['success' => false, 'message' => 'Invalid contact preference.']);
    exit;
}

try {
    $user_id = isset($_SESSION['user']['user_id']) ? (int)$_SESSION['user']['user_id'] : null;

    // Validate product ID
    $stmt = $pdo->prepare("SELECT prod_Id FROM products WHERE prod_Id = ?");
    $stmt->execute([$product_id]);
    if (!$stmt->fetch()) {
        error_log("Invalid product ID: $product_id");
        echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
        exit;
    }

    // Insert enquiry into database
    $stmt = $pdo->prepare("
        INSERT INTO enquiries (user_id, product_id, full_name, email, contact_preference, custom_dimensions, custom_material, custom_color, file_path, additional_notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $product_id,
        $full_name,
        $email,
        $contact_preference,
        $custom_dimensions,
        json_encode($custom_data),
        $custom_color,
        $file_path,
        $additional_notes
    ]);
    $enquiry_id = $pdo->lastInsertId();
    error_log("Enquiry inserted: enquiry_id=$enquiry_id, product_id=$product_id, user_id=" . ($user_id ?? 'guest'));

    // Send email notification to admin
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
        $mail->addAddress('yiwen1333@icloud.com', 'JellyHome Support');
        $mail->Subject = "New Product Enquiry - #$enquiry_id";

        // Construct base URL with scheme (HTTP or HTTPS)
        $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        $base_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/Home/';
        $image_url = $file_path ? $base_url . $file_path : '';
        error_log("Generated image URL for enquiry #$enquiry_id: $image_url");

        $customization_details = '';
        if ($custom_dimensions || $custom_data || $custom_color || $file_path || $additional_notes) {
            $customization_details .= "<h4>Customization Details</h4>";
            if ($custom_dimensions) {
                $customization_details .= "<p><strong>Custom Dimensions:</strong> " . htmlspecialchars($custom_dimensions) . "</p>";
            }
            foreach ($custom_data as $option_type => $value) {
                if ($value) {
                    $customization_details .= "<p><strong>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $option_type))) . ":</strong> " . htmlspecialchars($value) . "</p>";
                }
            }
            if ($custom_color) {
                $customization_details .= "<p><strong>Custom Color:</strong> <span style='background-color: $custom_color; padding: 2px 8px; border-radius: 4px; color: #fff;'>$custom_color</span></p>";
            }
            if ($file_path) {
                $customization_details .= "<p><strong>Uploaded Image:</strong> <a href='" . htmlspecialchars($image_url) . "'>View Image</a></p>";
            }
            if ($additional_notes) {
                $customization_details .= "<p><strong>Additional Notes:</strong> " . htmlspecialchars($additional_notes) . "</p>";
            }
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
                    .footer { text-align: center; padding: 10px; font-size: 12px; color: #777; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>JELLYHOME</h2>
                    </div>
                    <div class='content'>
                        <h4>New Product Enquiry #$enquiry_id</h4>
                        <p>A new enquiry has been submitted for product ID $product_id.</p>
                        <br><h4>Customer Details</h4>
                        <p><strong>Name:</strong> " . htmlspecialchars($full_name) . "</p>
                        <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                        <p><strong>Contact Preference:</strong> " . ($contact_preference === 'call' ? 'Phone Call' : 'Email') . "</p><br>
                        $customization_details
                        <br><p>Please review and respond to the customer as soon as possible.</p>
                    </div>
                    <div class='footer'>
                        <p>© 2025 JellyHome. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        error_log("Enquiry notification sent to yiwen1333@icloud.com for enquiry_id: $enquiry_id");
    } catch (Exception $e) {
        error_log("Enquiry notification failed: " . $e->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'Enquiry submitted successfully. We will contact you soon!']);
} catch (Exception $e) {
    error_log("Enquiry error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error submitting enquiry: ' . $e->getMessage()]);
}

ob_end_flush();
?>