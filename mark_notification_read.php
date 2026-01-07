<?php
session_start();
include 'db_config.php';

// Verify admin role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    try {
        // Check if notification exists, if not, create it with message
        $stmt = $pdo->prepare("SELECT id, message FROM notifications WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $notification = $stmt->fetch();
        
        if (!$notification) {
            // Get order details to create message
            $stmt = $pdo->prepare("
                SELECT u.full_name 
                FROM orders o
                JOIN users u ON o.user_id = u.user_id
                WHERE o.order_id = ?
            ");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            if ($order) {
                $message = "New order #$order_id from " . $order['full_name'];
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (order_id, message, is_read, is_archived) 
                    VALUES (?, ?, 1, 0)
                ");
                $stmt->execute([$order_id, $message]);
            }
        } else {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE order_id = ?");
            $stmt->execute([$order_id]);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Error marking notification read: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
}
?>