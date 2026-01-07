<?php
session_start();
include 'db_config.php';

// Redirect if not admin
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Fetch order statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total_orders FROM orders");
    $total_orders = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Completed'");
    $completed = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Cancelled'");
    $cancelled = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'");
    $pending = $stmt->fetchColumn();

    // Get filter and sort parameters
    $filter = $_GET['filter'] ?? 'all';
    $sort = $_GET['sort'] ?? 'desc'; // Default to descending order
    $sort = in_array($sort, ['asc', 'desc']) ? $sort : 'desc'; // Validate sort parameter

    // Build the query
    $query = "SELECT o.order_id, o.user_id, o.total_amount, o.order_status, o.order_date, u.full_name
              FROM orders o
              JOIN users u ON o.user_id = u.user_id";
    
    // Add WHERE clause for filtering
    $params = [];
    if ($filter !== 'all') {
        $query .= " WHERE o.order_status = ?";
        $params[] = $filter;
    }
    
    // Add ORDER BY clause for sorting
    $query .= " ORDER BY o.order_id " . ($sort === 'asc' ? 'ASC' : 'DESC');
    
    // Execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching dashboard data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

    <link rel="stylesheet" href="order_confirmation_content.css">
    <link rel="stylesheet" href="admin_dashboard.css">
</head>

<body class="bg-gray-100 font-roboto min-h-screen flex flex-col">
    <!-- Sidebar -->
    <div class="sidebar fixed top-0 left-0 h-full w-[20%] bg-gray-800 text-white flex flex-col px-8 py-10 sidebar-hidden" id="sidebar">
        <div class="flex items-center justify-between mb-10">
            <div class="flex items-center space-x-3">
                <i class="fas fa-home text-2xl"></i>
                <h2 class="text-3xl font-bold">JellyHome</h2>
            </div>
            <button id="toggle-sidebar" class="text-white focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <nav class="flex-1">
            <ul class="space-y-4">
                <li>
                    <a href="admin_dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-700 text-xl leading-9 font-bold">
                        <i class="fas fa-tachometer-alt text-xl"></i>
                        <span >Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="notification.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-700 text-xl leading-9 font-bold">
                        <i class="fas fa-bell text-xl"></i>
                        <span>Notifications</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-700 text-xl leading-9 font-bold">
                        <i class="fas fa-file-alt text-xl"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="statistics.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-700 text-xl leading-9 font-bold">
                        <i class="fas fa-chart-bar text-xl"></i>
                        <span>Statistics</span>
                    </a>
                </li>
                <li class="mt-auto">
                    <a href="logout.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-700 text-xl leading-9 font-bold">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Overlay for Dimming -->
    <div id="overlay" class="overlay overlay-hidden fixed inset-0 bg-gray-600 bg-opacity-50 z-10"></div>

    <!-- Order Details Panel -->
    <div class="order-panel fixed top-0 right-0 h-full w-[35%] bg-white text-gray-800 flex flex-col px-8 py-10 panel-hidden shadow-lg z-20" id="orderPanel">
        <div class="flex justify-end mb-4">
            <button id="close-order-panel" class="text-gray-600 hover:text-gray-800 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="orderDetailsContent" class="flex-1 overflow-y-auto">Select an order to view details.</div>
    </div>

    <!-- Main Content -->
    <main class="content-shift ml-0 flex-grow" id="main-content">
        <!-- Header -->
        <header class="bg-white shadow p-7 flex justify-between items-center">
            <button id="toggle-sidebar-mobile" class="text-gray-600 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <h1 class="text-2xl">Dashboard</h1>
            <div class="flex items-center space-x-4 text-xl">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['user']['full_name']); ?></span>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="container mx-auto p-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow fade-in">
                    <h3 class="text-lg font-semibold text-gray-700">Total Orders</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo $total_orders; ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow fade-in">
                    <h3 class="text-lg font-semibold text-gray-700">Completed</h3>
                    <p class="text-3xl font-bold text-green-600"><?php echo $completed; ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow fade-in">
                    <h3 class="text-lg font-semibold text-gray-700">Cancelled</h3>
                    <p class="text-3xl font-bold text-red-600"><?php echo $cancelled; ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow fade-in">
                    <h3 class="text-lg font-semibold text-gray-700">Pending</h3>
                    <p class="text-3xl font-bold text-yellow-600"><?php echo $pending; ?></p>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="mb-6">
                <div class="flex justify-between items-center">
                    <!-- Filter buttons on the left -->
                    <div class="flex space-x-4">
                        <a href="?filter=all" class="px-4 py-2 rounded <?php echo $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">All (<?php echo $total_orders; ?>)</a>
                        <a href="?filter=Completed" class="px-4 py-2 rounded <?php echo $filter === 'Completed' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Completed (<?php echo $completed; ?>)</a>
                        <a href="?filter=Cancelled" class="px-4 py-2 rounded <?php echo $filter === 'Cancelled' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Cancelled (<?php echo $cancelled; ?>)</a>
                        <a href="?filter=Pending" class="px-4 py-2 rounded <?php echo $filter === 'Pending' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">Pending (<?php echo $pending; ?>)</a>
                    </div>

                    <!-- Sort dropdown on the right -->
                    <select id="sortOrder" onchange="changeSortOrder(this.value)" class="w-48 border border-gray-300 rounded-md p-2 text-gray-700 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:bg-gray-100 transition-all duration-200 custom-select">
                        <option value="desc" <?php echo $sort === 'desc' ? 'selected' : ''; ?>>Sort Descending</option>
                        <option value="asc" <?php echo $sort === 'asc' ? 'selected' : ''; ?>>Sort Ascending</option>
                    </select>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">No orders found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">#<?php echo htmlspecialchars($order['order_id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($order['full_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">RM <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-4 inline-flex text-s leading-6 font-semibold rounded-full 
                                            <?php echo $order['order_status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                  ($order['order_status'] === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                            <?php echo htmlspecialchars($order['order_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)" class="text-blue-600 no-underline mr-2 hover:text-blue-800 transition-colors duration-200">View</button>
                                        <select onchange="updateOrderStatus(<?php echo $order['order_id']; ?>, this.value)" class="w-32 ml-2 text-center border border-blue-300 rounded-md p-1 text-gray-700 bg-blue-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:bg-blue-100 transition-all duration-200 custom-select">
                                            <option value="Pending" <?php echo $order['order_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Completed" <?php echo $order['order_status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="Cancelled" <?php echo $order['order_status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Order Details Panel -->
        <div class="order-panel fixed top-0 right-0 h-full w-[20%] bg-white text-gray-800 flex flex-col px-8 py-10 panel-hidden shadow-lg z-20" id="orderPanel">
            <div class="flex justify-end mb-4">
                <button id="close-order-panel" class="text-gray-600 hover:text-gray-800 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="orderDetailsContent" class="flex-1 overflow-y-auto">Select an order to view details.</div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white shadow mt-8 p-4 text-center text-gray-600">
        <p>© 2025 JellyHome. All Rights Reserved.</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle Sidebar
            $('#toggle-sidebar, #toggle-sidebar-mobile').on('click', function() {
                $('#sidebar').toggleClass('sidebar-hidden');
                $('#main-content').toggleClass('ml-64');
            });

            // View Order Details
            window.viewOrderDetails = function(orderId) {
                $.ajax({
                    url: 'admin_order_details.php',
                    type: 'GET',
                    data: { order_id: orderId },
                    success: function(data) {
                        $('#orderDetailsContent').html(data);
                        $('#orderPanel').removeClass('panel-hidden');
                        $('#overlay').removeClass('overlay-hidden');
                    },
                    error: function() {
                        $('#orderDetailsContent').html('<p>Error loading order details.</p>');
                    }
                });
            };

            // Close Order Panel
            $('#close-order-panel').on('click', function() {
                $('#orderPanel').addClass('panel-hidden');
                $('#overlay').addClass('overlay-hidden');
                $('#orderDetailsContent').html('Select an order to view details.');
            });

            // Close Order Panel when clicking overlay
            $('#overlay').on('click', function() {
                $('#orderPanel').addClass('panel-hidden');
                $('#overlay').addClass('overlay-hidden');
                $('#orderDetailsContent').html('Select an order to view details.');
            });

            // Update Order Status
            window.updateOrderStatus = function(orderId, status) {
                $.ajax({
                    url: 'update_order_status.php',
                    type: 'POST',
                    data: { order_id: orderId, status: status },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            location.reload(); // Refresh to update table
                        } else {
                            alert('Error updating status: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error updating status.');
                    }
                });
            };

            // Close Modal
            window.closeOrderModal = function() {
                $('#orderModal').addClass('hidden');
            };

            // Change Sort Order
            window.changeSortOrder = function(sort) {
                const url = new URL(window.location.href);
                url.searchParams.set('sort', sort);
                window.location.href = url.toString();
            };
        });
    </script>
</body>
</html>