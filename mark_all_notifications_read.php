<?php
session_start();
include 'db_config.php';

// Verify admin role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Update existing notifications
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
    $stmt->execute();

    // For orders without notifications, create them as read
    $stmt = $pdo->prepare("
        INSERT INTO notifications (order_id, message, is_read, is_archived)
        SELECT o.order_id, CONCAT('New order #', o.order_id, ' from ', u.full_name), 1, 0
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN notifications n ON o.order_id = n.order_id
        WHERE n.order_id IS NULL
    ");
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error marking all notifications read: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>