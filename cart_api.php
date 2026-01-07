<?php
session_start();
include 'db_config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to manage your cart.']);
    exit;
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'])) {
        error_log("CSRF token not provided");
        echo json_encode(['success' => false, 'message' => 'CSRF token required.']);
        exit;
    }
    
    // Check if token exists in session
    if (!isset($_SESSION['csrf_token'])) {
        error_log("No CSRF token in session");
        echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh the page.']);
        exit;
    }
    
    // Compare tokens
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        error_log("CSRF token mismatch. Received: " . ($_POST['csrf_token'] ?? 'Not set') . ", Expected: " . ($_SESSION['csrf_token'] ?? 'Not set'));
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.']);
        exit;
    }
}

// Get user ID from session
$user_id = getUserId($pdo, $_SESSION['user']);
if (!$user_id) {
    error_log("Invalid user ID for session: " . print_r($_SESSION['user'], true));
    echo json_encode(['success' => false, 'message' => 'Invalid user session.']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'add') {
    $prod_id = $_POST['prod_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;
    $color = filter_var($_POST['color'] ?? 'Default', FILTER_SANITIZE_STRING);
    error_log("Adding to cart: prod_id=$prod_id, quantity=$quantity, color=$color, user_id=$user_id");
    if ($prod_id > 0 && $quantity > 0) {
        try {
            // Check product existence and stock
            $stmt = $pdo->prepare("SELECT prod_Id, prod_Stock FROM products WHERE prod_Id = ?");
            $stmt->execute([$prod_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
                exit;
            }
            $available_stock = $product['prod_Stock'] ?? 0;
            error_log("Available stock for prod_id=$prod_id: $available_stock");

            // Check existing quantity in cart for this product and color
            $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND prod_id = ? AND color = ?");
            $stmt->execute([$user_id, $prod_id, $color]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            $existing_quantity = $existing ? $existing['quantity'] : 0;
            $total_quantity = $existing_quantity + $quantity;

            // Validate against stock
            if ($total_quantity > $available_stock) {
                echo json_encode(['success' => false, 'message' => "Cannot add $quantity item(s). Only $available_stock available in stock."]);
                exit;
            }

            // Update or insert cart item
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND prod_id = ? AND color = ?");
                $stmt->execute([$total_quantity, $user_id, $prod_id, $color]);
                error_log("Updated cart: prod_id=$prod_id, color=$color, new_quantity=$total_quantity");
            } else {
                $stmt = $pdo->prepare("INSERT INTO cart (user_id, prod_id, quantity, color) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $prod_id, $quantity, $color]);
                error_log("Inserted new cart item: prod_id=$prod_id, color=$color, quantity=$quantity");
            }
            echo json_encode(['success' => true, 'message' => 'Product added to cart.']);
        } catch (PDOException $e) {
            error_log("Error adding to cart: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error adding to cart: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID or quantity.']);
    }
}

elseif ($action === 'update') {
    $prod_id = $_POST['prod_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 0;
    $color = filter_var($_POST['color'] ?? 'Default', FILTER_SANITIZE_STRING);
    if ($prod_id > 0 && $quantity >= 0) {
        try {
            // Check product existence and stock
            $stmt = $pdo->prepare("SELECT prod_Id, prod_Stock FROM products WHERE prod_Id = ?");
            $stmt->execute([$prod_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
                exit;
            }
            $available_stock = $product['prod_Stock'] ?? 0;
            error_log("Available stock for prod_id=$prod_id: $available_stock");

            // Validate quantity against stock
            if ($quantity > $available_stock) {
                echo json_encode(['success' => false, 'message' => "Cannot update to $quantity item(s). Only $available_stock available in stock."]);
                exit;
            }

            // Update or delete cart item
            if ($quantity == 0) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND prod_id = ? AND color = ?");
                $stmt->execute([$user_id, $prod_id, $color]);
                error_log("Deleted cart item: prod_id=$prod_id, color=$color");
            } else {
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND prod_id = ? AND color = ?");
                $stmt->execute([$quantity, $user_id, $prod_id, $color]);
                error_log("Updated cart: prod_id=$prod_id, color=$color, new_quantity=$quantity");
            }
            echo json_encode(['success' => true, 'message' => 'Cart updated.']);
        } catch (PDOException $e) {
            error_log("Error updating cart: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error updating cart: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID or quantity.']);
    }
} 

elseif ($action === 'remove') {
    $prod_id = $_POST['prod_id'] ?? 0;
    $color = filter_var($_POST['color'] ?? 'Default', FILTER_SANITIZE_STRING);
    if ($prod_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND prod_id = ? AND color = ?");
            $stmt->execute([$user_id, $prod_id, $color]);
            error_log("Deleted cart item: prod_id=$prod_id, color=$color");
            echo json_encode(['success' => true, 'message' => 'Product removed from cart.']);
        } catch (PDOException $e) {
            error_log("Error removing from cart: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error removing from cart: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
    }
} 

elseif ($action === 'view') {
    try {
        $stmt = $pdo->prepare("
            SELECT p.prod_Id, p.prod_Name, p.prod_Image, p.prod_Price, p.is_on_sale, p.sale_price, c.quantity,
                   p.prod_Dimension, c.color, p.color_images
            FROM cart c
            JOIN products p ON c.prod_id = p.prod_Id
            WHERE c.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) {
            $item['dimension'] = $item['prod_Dimension'] ?: 'N/A';
            $item['color'] = $item['color'] ?: 'Default';
            $colorImages = json_decode($item['color_images'] ?? '{}', true);
            $item['prod_Image'] = $colorImages[$item['color']] ?? $item['prod_Image'];
            unset($item['color_images']);
        }
        error_log("Fetched cart for user_id=$user_id: " . json_encode($items));
        echo json_encode(['success' => true, 'items' => $items]);
    } catch (PDOException $e) {
        error_log("Error fetching cart: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error fetching cart: ' . $e->getMessage()]);
    }
} 

else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}

function getUserId($pdo, $session_user) {
    if (is_array($session_user) && isset($session_user['user_id'])) {
        error_log("Using user_id from session: " . $session_user['user_id']);
        return $session_user['user_id'];
    }
    error_log("Invalid session user data: " . print_r($session_user, true));
    throw new Exception("Invalid user session data.");
}
?>