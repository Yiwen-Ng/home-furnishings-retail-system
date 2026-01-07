<?php
include 'db_config.php';
header('Content-Type: application/json');
session_start();
if (isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    $user_id = getUserId($pdo, $_SESSION['user']);
    $stmt = $pdo->prepare("UPDATE orders SET order_status = 'Cancelled' WHERE order_id = ? AND user_id = ?");
    if ($stmt->execute([$order_id, $user_id])) {
        echo json_encode(['success' => true, 'message' => 'Order cancelled']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel order']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
function getUserId($pdo, $session_user) {
    if (is_array($session_user) && isset($session_user['user_id'])) return $session_user['user_id'];
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE full_name = ?");
    $stmt->execute([$session_user]);
    return $stmt->fetchColumn() ?: 0;
}
?>