<?php
session_start();
include 'db_config.php';

// Check if user is admin
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

try {
    // Build query with filters
    $query = "
        SELECT o.order_id, o.order_date, o.total_amount, o.order_status, o.shipping_address, o.city, o.postal_code, 
               o.payment_method, o.tracking_number, u.full_name, u.email
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
    ";
    $params = [];

    if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
        $query .= " WHERE o.order_status = ?";
        $params[] = $_GET['status'];
    }

    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $query .= (empty($params) ? " WHERE" : " AND") . " o.order_date BETWEEN ? AND ?";
        $params[] = $_GET['start_date'];
        $params[] = $_GET['end_date'];
    }

    $query .= " ORDER BY o.order_date DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare CSV
    header('Content-Type: text/csv');
    $filename = 'orders_export_' . date('Y-m-d_His') . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Order ID', 'Customer Name', 'Email', 'Order Date', 'Total Amount', 'Status', 
        'Shipping Address', 'City', 'Postal Code', 'Payment Method', 'Tracking Number', 'Items'
    ]);

    foreach ($orders as $order) {
        // Fetch order items
        $stmt = $pdo->prepare("
            SELECT p.prod_Name, oi.quantity, oi.price
            FROM order_items oi
            JOIN products p ON oi.prod_id = p.prod_Id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order['order_id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items_string = '';
        foreach ($items as $item) {
            $items_string .= $item['prod_Name'] . ' (x' . $item['quantity'] . ' @ RM ' . number_format($item['price'], 2) . '), ';
        }
        $items_string = rtrim($items_string, ', ');

        fputcsv($output, [
            $order['order_id'],
            $order['full_name'],
            $order['email'],
            $order['order_date'],
            number_format($order['total_amount'], 2),
            $order['order_status'],
            $order['shipping_address'],
            $order['city'],
            $order['postal_code'],
            $order['payment_method'],
            $order['tracking_number'] ?: 'N/A',
            $items_string
        ]);
    }

    fclose($output);
    exit;
} catch (PDOException $e) {
    error_log("Error exporting orders: " . $e->getMessage());
    die("Error exporting orders: " . $e->getMessage());
}
?>