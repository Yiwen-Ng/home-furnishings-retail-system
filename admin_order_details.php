<?php
session_start();
include 'db_config.php';

// Redirect if not admin
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo '<p>Unauthorized access.</p>';
    exit;
}

// Fetch order details based on order_id from query parameter
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$order = [];
$order_items = [];

try {
    if ($order_id <= 0) {
        echo '<p>Invalid order ID.</p>';
        exit;
    }

    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name, u.email
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        WHERE o.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo '<p>Order not found.</p>';
        exit;
    }

    // Fetch order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.prod_Name, p.prod_Image
        FROM order_items oi
        JOIN products p ON oi.prod_id = p.prod_Id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo '<p>Error fetching order details: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Determine timeline step status based on order_status
$timeline_steps = [
    [
        'title' => 'Order Processed',
        'description' => 'The order is being prepared (products are being packed)',
        'completed' => $order['order_status'] === 'Completed'
    ],
    [
        'title' => 'Payment Confirmed',
        'description' => 'Payment has been successfully processed and verified',
        'completed' => in_array($order['order_status'], ['Completed', 'Pending'])
    ],
    [
        'title' => 'Order Placed',
        'description' => 'Order has been successfully placed by the customer',
        'completed' => true // Always completed
    ]
];
?>

<div class="p-4">
    <h3 class="text-lg font-semibold mb-4">Order #<?php echo htmlspecialchars($order['order_id']); ?></h3>
    <div class="details">
        <h2>Order Details</h2>
        <p><strong>Account:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
        <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['full_name']); ?></p>
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
            <div class="status-box-up <?php echo $order['order_status'] === 'Cancelled' ? 'cancelled-status' : ($order['order_status'] === 'Pending' ? 'pending-status' : 'success-status'); ?>">
                <?php echo htmlspecialchars($order['order_status']); ?>
            </div>
        </h2>
    </div>
    <div class="details">
        <h2>Timeline</h2>
        <?php if ($order['order_status'] === 'Cancelled'): ?>
            <p class="cancelled-status">Order was cancelled</p>
        <?php endif; ?>
        <?php foreach ($timeline_steps as $step): ?>
            <div class="timeline-step">
                <div class="timeline-marker <?php echo $step['completed'] && $order['order_status'] !== 'Cancelled' ? 'completed' : ''; ?>"></div>
                <div class="timeline-content">
                    <p><strong><?php echo htmlspecialchars($step['title']); ?></strong></p>
                    <p><?php echo htmlspecialchars($step['description']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>