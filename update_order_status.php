<?php
session_start();
include 'db_config.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$order_id = $_POST['order_id'] ?? 0;
$status = $_POST['status'] ?? '';

if ($order_id <= 0 || !in_array($status, ['Pending', 'Completed', 'Cancelled'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID or status.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
    $stmt->execute([$status, $order_id]);
    echo json_encode(['success' => true, 'message' => 'Order status updated.']);
} catch (PDOException $e) {
    error_log("Error updating order status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating order status: ' . $e->getMessage()]);
}
?>