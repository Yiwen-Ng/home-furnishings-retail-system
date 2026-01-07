<?php
session_start();
include 'db_config.php';

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user'])) {
    header("Location: login.php?redirect=order_confirmation.php");
    exit;
}

// Fetch order details based on order_id from query parameter
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$order = [];
$order_items = [];

try {
    $user = getUserData($pdo, $_SESSION['user']);
    $user_id = $user['user_id'];
    $user_email = $user['email'];
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("Order not found or unauthorized access.");
    }

    $stmt = $pdo->prepare("SELECT oi.*, p.prod_Name, p.prod_Image FROM order_items oi JOIN products p ON oi.prod_id = p.prod_Id WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch order history
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND order_id != ? ORDER BY order_date DESC LIMIT 5");
    $stmt->execute([$user_id, $order_id]);
    $order_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching order details: " . $e->getMessage());
}

function getUserData($pdo, $session_user) {
    if (is_array($session_user) && isset($session_user['user_id']) && isset($session_user['email'])) {
        return ['user_id' => $session_user['user_id'], 'email' => $session_user['email']];
    }
    $stmt = $pdo->prepare("SELECT user_id, email FROM users WHERE full_name = ?");
    $stmt->execute([$session_user]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['user'] = ['user_id' => $user['user_id'], 'email' => $user['email']];
        return ['user_id' => $user['user_id'], 'email' => $user['email']];
    }
    return ['user_id' => 0, 'email' => 'user@email.com'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation Content Page</title>
    <link rel="stylesheet" href="order_confirmation_content.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container">
        <div class="card">
            <svg class="<?php echo $order['order_status'] === 'Cancelled' ? 'cross-icon' : 'check-icon'; ?>" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="<?php echo $order['order_status'] === 'Cancelled' ? 'M6 18L18 6M6 6l12 12' : 'M5 13l4 4L19 7'; ?>"></path>
            </svg>
            <h1>Order #<?php echo htmlspecialchars($order['order_id']); ?></h1>
            <p><?php 
                switch ($order['order_status']) {
                    case 'Cancelled':
                        echo 'Your order has been cancelled.';
                        break;
                    case 'Completed':
                        echo 'Your order has been completed successfully!';
                        break;
                    default:
                        echo 'Order placed successfully! You’ll receive an email once it’s processed.';
                }
            ?></p>
            <div class="details">
                <h2>Order Details</h2>
                <p><strong>Account:</strong> <?php echo htmlspecialchars($_SESSION['user']['email'] ?? 'user@email.com'); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars(date('F j, Y, g:i a', strtotime($order['order_date']))); ?></p>
                <p><strong>Items:</strong></p>
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <p><?php echo htmlspecialchars($item['prod_Name']); ?> (x<?php echo $item['quantity']; ?>) 
                            <?php if (!empty($item['color']) && $item['color'] !== 'Default'): ?>
                                <span class="color-info">Option - <?php echo htmlspecialchars($item['color']); ?></span>
                            <?php endif; ?>
                            <span class="price">RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="details">
                <h2>Shipping</h2>
                <p>Standard (10 days) <span class="price">RM 10.00</span></p>
            </div>
            <div class="details-total">
                <h2>Total<span class="price">RM <?php echo number_format($order['total_amount'], 2); ?></span></h2>
            </div>
            <div class="details">
                <h2>Status
                    <div class="status-box-up <?php 
                        echo $order['order_status'] === 'Pending' ? 'pending-status' : 
                             ($order['order_status'] === 'Completed' ? 'completed-status' : 'cancelled-status'); ?>">
                        <?php echo htmlspecialchars($order['order_status']); ?>
                    </div>
                </h2>
            </div>
            <div class="buttons">
                <?php if ($order['order_status'] === 'Pending'): ?>
                    <form id="cancel-form" style="display:inline;">
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id']); ?>">
                        <button type="button" class="btn btn-cancel" id="cancel-button">Cancel Order</button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-cancel" disabled>
                        <?php echo $order['order_status'] === 'Cancelled' ? 'Cancelled' : 'Completed'; ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cancellation Policy Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <h2>Cancellation Policy</h2>
            <p>Please review our cancellation policy before proceeding:</p>
            <ul>
                <li>Orders can only be cancelled within 24 hours of placement.</li>
                <li>Cancelled orders may incur a 10% processing fee.</li>
                <li>Refunds will be processed within 5-7 business days.</li>
                <li>Items already shipped cannot be cancelled.</li>
            </ul>
            <p>Are you sure you want to cancel Order #<?php echo htmlspecialchars($order['order_id']); ?>?</p>
            <div class="modal-buttons">
                <button class="btn btn-cancel" id="confirm-cancel">Cancel Order</button>
                <button class="btn btn-keep" id="keep-order">Keep Order</button>
            </div>
        </div>
    </div>

    <!-- jQuery with local fallback -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script>
        // Fallback if jQuery CDN fails
        if (typeof jQuery == 'undefined') {
            console.warn('jQuery not loaded from CDN, attempting local fallback');
            document.write('<script src="js/jquery-3.6.0.min.js"><\/script>');
        }
    </script>
    <script>
        $(document).ready(function() {
            // Debug: Log when script runs
            console.log('Order confirmation script loaded');

            // Cancel button click handler
            $('#cancel-button').on('click', function(e) {
                console.log('Cancel button clicked');
                showCancelModal();
            });

            // Modal confirm cancel
            $('#confirm-cancel').on('click', function() {
                console.log('Confirm cancel clicked');
                confirmCancel();
            });

            // Modal keep order
            $('#keep-order').on('click', function() {
                console.log('Keep order clicked');
                closeCancelModal();
            });

            // Close modal when clicking outside
            $(document).on('click', function(e) {
                if ($(e.target).is('#cancelModal.show')) {
                    console.log('Clicked outside modal');
                    closeCancelModal();
                }
            });

            function showCancelModal() {
                console.log('Showing cancel modal');
                $('#cancelModal').addClass('show');
            }

            function closeCancelModal() {
                console.log('Closing cancel modal');
                $('#cancelModal').removeClass('show');
            }

            function confirmCancel() {
                console.log('Confirming cancellation');
                const orderId = $('input[name="order_id"]').val();
                $.ajax({
                    url: 'cancel_order.php',
                    type: 'POST',
                    data: { order_id: orderId },
                    dataType: 'json',
                    success: function(response) {
                        console.log('AJAX success:', response);
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Failed to cancel order: ' + response.message);
                            closeCancelModal();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        alert('An error occurred while cancelling the order.');
                        closeCancelModal();
                    }
                });
            }
        });

        // Debug: Log if jQuery is loaded
        console.log('jQuery loaded:', typeof jQuery !== 'undefined');
    </script>
</body>
</html>