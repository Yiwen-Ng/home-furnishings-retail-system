<?php
session_start();
include 'db_config.php';

// Only admin can run this
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

try {
    // Find orders without notifications
    $stmt = $pdo->query("
        SELECT o.order_id, u.full_name
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN notifications n ON o.order_id = n.order_id
        WHERE n.order_id IS NULL
        ORDER BY o.order_date DESC
    ");
    $missingOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($missingOrders as $order) {
        $message = "New order #{$order['order_id']} from {$order['full_name']}";
        $stmt = $pdo->prepare("
            INSERT INTO notifications (order_id, message, is_read, is_archived, created_at)
            VALUES (?, ?, 0, 0, NOW())
        ");
        $stmt->execute([$order['order_id'], $message]);
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($missingOrders),
        'message' => 'Notifications synced successfully'
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error syncing notifications: ' . $e->getMessage()
    ]);
}