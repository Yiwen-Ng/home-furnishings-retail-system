<?php
session_start();
include 'db_config.php';

// Verify CSRF token and admin role
if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== $_POST['csrf_token'] || 
    !isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$order_id = $_POST['order_id'] ?? null;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

try {
    // Get user info for the order
    $stmt = $pdo->prepare("
        SELECT u.full_name 
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        WHERE o.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Insert notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (order_id, message, is_read, is_archived, created_at) 
        VALUES (?, ?, 0, 0, NOW())
    ");
    $message = "New order #$order_id from " . $user['full_name'];
    $stmt->execute([$order_id, $message]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}