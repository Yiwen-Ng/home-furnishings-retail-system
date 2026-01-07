<?php
session_start();
include 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to manage your wishlist.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token mismatch. Received: " . ($_POST['csrf_token'] ?? 'Not set') . ", Expected: " . ($_SESSION['csrf_token'] ?? 'Not set'));
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
}

$user_id = getUserId($pdo, $_SESSION['user']);
$action = $_GET['action'] ?? '';

if ($action === 'add') {
    $prod_id = $_POST['prod_id'] ?? 0;
    if ($prod_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
        exit;
    }
    if ($user_id <= 0) {
        error_log("Invalid user_id for session: " . print_r($_SESSION['user'], true));
        echo json_encode(['success' => false, 'message' => 'Invalid user session.']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, prod_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $prod_id]);
        echo json_encode(['success' => true, 'message' => 'Product added to wishlist.']);
    } catch (PDOException $e) {
        error_log("Error adding to wishlist: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error adding to wishlist: ' . $e->getMessage()]);
    }
} elseif ($action === 'remove') {
    $prod_id = $_POST['prod_id'] ?? 0;
    if ($prod_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND prod_id = ?");
        $stmt->execute([$user_id, $prod_id]);
        echo json_encode(['success' => true, 'message' => 'Product removed from wishlist.']);
    } catch (PDOException $e) {
        error_log("Error removing from wishlist: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error removing from wishlist: ' . $e->getMessage()]);
    }
} elseif ($action === 'view') {
    try {
        $stmt = $pdo->prepare("
            SELECT p.prod_Id, p.prod_Name, p.prod_Image, p.prod_Price, p.is_on_sale, p.sale_price
            FROM wishlist w
            JOIN products p ON w.prod_id = p.prod_Id
            WHERE w.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'items' => $items]);
    } catch (PDOException $e) {
        error_log("Error fetching wishlist: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error fetching wishlist: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}

function getUserId($pdo, $session_user) {
    if (is_array($session_user) && isset($session_user['user_id']) && $session_user['user_id'] > 0) {
        return $session_user['user_id'];
    }
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE full_name = ?");
        $stmt->execute([is_array($session_user) ? ($session_user['full_name'] ?? '') : $session_user]);
        $user_id = $stmt->fetchColumn();
        if ($user_id === false || $user_id <= 0) {
            error_log("User not found for session data: " . print_r($session_user, true));
            return 0;
        }
        // Update session with user_id for future requests
        $_SESSION['user'] = is_array($session_user) ? array_merge($session_user, ['user_id' => $user_id]) : ['user_id' => $user_id, 'full_name' => $session_user];
        return $user_id;
    } catch (PDOException $e) {
        error_log("Error fetching user_id: " . $e->getMessage());
        return 0;
    }
}
?>