<?php
session_start();
include 'db_config.php';

// Redirect if not admin
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Initialize variables with default values
$total_sales = 0;
$status_breakdown = ['Pending' => 0, 'Completed' => 0, 'Cancelled' => 0];
$top_customers = [];
$completed_change = $pending_change = $cancelled_change = 0;
$selected_month = date('Y-m');
$top_category = 'N/A';
$top_category_percentage = 0;
$average_order_value = 0;
$items_per_order = 0;
$top_category_revenue = 'N/A';
$aov_change = 0;
$ipo_change = 0;

// Handle month selection
if (isset($_POST['month'])) {
    $selected_month = $_POST['month'];
}

// Fetch report data for selected month
try {
    $start_date = date('Y-m-01', strtotime($selected_month));
    $end_date = date('Y-m-t', strtotime($selected_month));
    $prev_start_date = date('Y-m-01', strtotime($selected_month . ' -1 month'));
    $prev_end_date = date('Y-m-t', strtotime($selected_month . ' -1 month'));
    
    // Total Sales
    $stmt = $pdo->prepare("SELECT SUM(total_amount) as total_sales FROM orders WHERE order_status != 'Cancelled' AND order_date BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $total_sales = $stmt->fetchColumn() ?? 0;

    // Order Status Breakdown
    $stmt = $pdo->prepare("SELECT order_status, COUNT(*) as count FROM orders WHERE order_date BETWEEN ? AND ? GROUP BY order_status");
    $stmt->execute([$start_date, $end_date]);
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($status_counts as $status) {
        $status_breakdown[$status['order_status']] = $status['count'];
    }

    // Top Customers
    $stmt = $pdo->prepare("
        SELECT u.full_name, SUM(o.total_amount) as total_spent
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        WHERE o.order_status != 'Cancelled' AND o.order_date BETWEEN ? AND ?
        GROUP BY u.user_id, u.full_name
        ORDER BY total_spent DESC
        LIMIT 5
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate percentage changes (compared to previous month)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE order_status = 'Completed' AND order_date BETWEEN ? AND ?");
    $stmt->execute([$prev_start_date, $prev_end_date]);
    $prev_completed = $stmt->fetchColumn();
    $current_completed = $status_breakdown['Completed'];
    $completed_change = $prev_completed > 0 ? 
        round((($current_completed - $prev_completed) / $prev_completed) * 100, 1) : 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE order_status = 'Pending' AND order_date BETWEEN ? AND ?");
    $stmt->execute([$prev_start_date, $prev_end_date]);
    $prev_pending = $stmt->fetchColumn();
    $pending_change = $prev_pending > 0 ? 
        round((($status_breakdown['Pending'] - $prev_pending) / $prev_pending) * 100, 1) : 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE order_status = 'Cancelled' AND order_date BETWEEN ? AND ?");
    $stmt->execute([$prev_start_date, $prev_end_date]);
    $prev_cancelled = $stmt->fetchColumn();
    $cancelled_change = $prev_cancelled > 0 ? 
        round((($status_breakdown['Cancelled'] - $prev_cancelled) / $prev_cancelled) * 100, 1) : 0;

    // Most Bought Category
    $stmt = $pdo->prepare("
        SELECT c.cat_Name, COUNT(*) as count 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        JOIN products p ON oi.prod_id = p.prod_Id
        JOIN subcategories s ON p.subcat_Id = s.subcat_Id
        JOIN categories c ON s.cat_Id = c.cat_Id
        WHERE o.order_date BETWEEN ? AND ?
        GROUP BY c.cat_Id, c.cat_Name
        ORDER BY count DESC
        LIMIT 1
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_category_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($top_category_data) {
        $top_category = $top_category_data['cat_Name'];
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE o.order_date BETWEEN ? AND ?
        ");
        $stmt->execute([$start_date, $end_date]);
        $total_items = $stmt->fetchColumn();
        $top_category_percentage = $total_items > 0 ? 
            round(($top_category_data['count'] / $total_items) * 100) : 0;
    }

    // Best Selling Products
    $stmt = $pdo->prepare("
        SELECT p.prod_Name, SUM(oi.quantity) as total_quantity
        FROM order_items oi
        JOIN products p ON oi.prod_id = p.prod_Id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_date BETWEEN ? AND ?
        GROUP BY p.prod_Id, p.prod_Name
        ORDER BY total_quantity DESC
        LIMIT 5
    ");
    $stmt->execute([$start_date, $end_date]);
    $best_sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Revenue by Category
    $stmt = $pdo->prepare("
        SELECT c.cat_Name, SUM(oi.price * oi.quantity) as category_revenue
        FROM order_items oi
        JOIN products p ON oi.prod_id = p.prod_Id
        JOIN subcategories s ON p.subcat_Id = s.subcat_Id
        JOIN categories c ON s.cat_Id = c.cat_Id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_date BETWEEN ? AND ? AND o.order_status != 'Cancelled'
        GROUP BY c.cat_Id, c.cat_Name
        ORDER BY category_revenue DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $revenue_by_category = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Number of non-cancelled orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE order_status != 'Cancelled' AND order_date BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $non_cancelled_orders = $stmt->fetchColumn() ?? 0;

    // Total items sold
    $stmt = $pdo->prepare("
        SELECT SUM(oi.quantity) as total_items
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_status != 'Cancelled' AND o.order_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $total_items_sold = $stmt->fetchColumn() ?? 0;

    // Calculate Average Order Value
    $average_order_value = $non_cancelled_orders > 0 ? $total_sales / $non_cancelled_orders : 0;

    // Calculate Items per Order
    $items_per_order = $non_cancelled_orders > 0 ? $total_items_sold / $non_cancelled_orders : 0;

    // Get top category for Revenue per Category display
    $cat_name = !empty($revenue_by_category[0]['cat_Name']) ? htmlspecialchars($revenue_by_category[0]['cat_Name'], ENT_QUOTES, 'UTF-8') : '';
    $top_category_revenue = !empty($revenue_by_category) ? $cat_name . ":<br>RM " . number_format($revenue_by_category[0]['category_revenue'], 2) : 'N/A';

    // Previous month non-cancelled orders and total items
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE order_status != 'Cancelled' AND order_date BETWEEN ? AND ?");
    $stmt->execute([$prev_start_date, $prev_end_date]);
    $prev_non_cancelled_orders = $stmt->fetchColumn() ?? 0;

    $stmt = $pdo->prepare("
        SELECT SUM(oi.quantity) as total_items
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_status != 'Cancelled' AND o.order_date BETWEEN ? AND ?
    ");
    $stmt->execute([$prev_start_date, $prev_end_date]);
    $prev_total_items_sold = $stmt->fetchColumn() ?? 0;

    // Previous month total sales
    $stmt = $pdo->prepare("SELECT SUM(total_amount) as total_sales FROM orders WHERE order_status != 'Cancelled' AND order_date BETWEEN ? AND ?");
    $stmt->execute([$prev_start_date, $prev_end_date]);
    $prev_total_sales = $stmt->fetchColumn() ?? 0;

    // Calculate previous month metrics
    $prev_average_order_value = $prev_non_cancelled_orders > 0 ? $prev_total_sales / $prev_non_cancelled_orders : 0;
    $prev_items_per_order = $prev_non_cancelled_orders > 0 ? $prev_total_items_sold / $prev_non_cancelled_orders : 0;

    // Calculate percentage changes
    $aov_change = $prev_average_order_value > 0 ? 
        round((($average_order_value - $prev_average_order_value) / $prev_average_order_value) * 100, 1) : 0;
    $ipo_change = $prev_items_per_order > 0 ? 
        round((($items_per_order - $prev_items_per_order) / $prev_items_per_order) * 100, 1) : 0;
} 
catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

    <link rel="stylesheet" href="order_confirmation_content.css">
    <link rel="stylesheet" href="admin_dashboard.css">
    <link rel="stylesheet" href="reports.css">
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

    <!-- Main Content -->
    <main class="content-shift ml-0 flex-grow" id="main-content">
        <!-- Header -->
        <header class="bg-white shadow p-7 flex justify-between items-center">
            <button id="toggle-sidebar-mobile" class="text-gray-600 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <h1 class="text-2xl">Reports</h1>
            <div class="flex items-center space-x-4 text-xl">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['user']['full_name']); ?></span>
            </div>
        </header>

        <!-- Reports Content -->
        <div class="container mx-auto p-6">

            <!-- Month Selector Form -->
            <form method="POST" action="reports.php" class="mb-6">
                <label for="month" class="block text-sm font-medium text-gray-700 mb-2">Select Month</label>
                <div class="flex">
                    <input type="month" 
                        id="month" 
                        name="month" 
                        value="<?php echo $selected_month; ?>" 
                        class="rounded-l-md border border-gray-300 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        max="<?php echo date('Y-m'); ?>">
                    <button type="submit" name="apply_month" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-r-md transition duration-200">Apply</button>
                </div>
            </form>

            <!-- Export Form -->
            <form method="POST" action="generate_report.php" class="mb-6">
                <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
                <div class="flex justify-between items-center">
                    <button type="submit" name="Monthly_Report_pdf" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md transition duration-200">
                        Export as PDF
                    </button>
                </div>
            </form>

            <!-- Display selected month -->
            <div class="text-lg font-semibold text-gray-800 mb-6">
                Showing data for <?php echo date('F Y', strtotime($selected_month)); ?>
            </div>

            <!-- Large Metric Cards -->
            <div class="metric-row-lg">
                <!-- Total Orders -->
                <div class="metric-card-lg">
                    <div class="icon orders">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="content">
                        <div class="title">Total Orders</div>
                        <div class="value"><?php echo array_sum($status_breakdown); ?></div>
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="metric-card-lg">
                    <div class="icon revenue">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="content">
                        <div class="title">Total Revenue</div>
                        <div class="value">RM <?php echo number_format($total_sales, 2); ?></div>
                    </div>
                </div>
            </div>

            <!-- Small Metric Cards -->
            <div class="metric-row-sm">
                <!-- Completed Orders -->
                <div class="metric-card-sm">
                    <div class="title">Completed</div>
                    <div class="value"><?php echo $status_breakdown['Completed']; ?></div>
                    <div class="change <?php echo ($completed_change >= 0) ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-arrow-<?php echo ($completed_change >= 0) ? 'up' : 'down'; ?>"></i>
                        <?php echo abs($completed_change); ?>%
                    </div>
                </div>

                <!-- Pending Orders -->
                <div class="metric-card-sm">
                    <div class="title">Pending</div>
                    <div class="value"><?php echo $status_breakdown['Pending']; ?></div>
                    <div class="change <?php echo ($pending_change >= 0) ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-arrow-<?php echo ($pending_change >= 0) ? 'up' : 'down'; ?>"></i>
                        <?php echo abs($pending_change); ?>%
                    </div>
                </div>

                <!-- Cancelled Orders -->
                <div class="metric-card-sm">
                    <div class="title">Cancelled</div>
                    <div class="value"><?php echo $status_breakdown['Cancelled']; ?></div>
                    <div class="change <?php echo ($cancelled_change >= 0) ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-arrow-<?php echo ($cancelled_change >= 0) ? 'up' : 'down'; ?>"></i>
                        <?php echo abs($cancelled_change); ?>%
                    </div>
                </div>
            </div>

            <!-- Donut Charts -->
            <div class="donut-chart-container">
                <!-- Completion Rate -->
                <div class="donut-chart-card">
                    <div class="title">Completion Rate</div>
                    <div class="donut-chart-wrapper">
                        <canvas id="completion-chart"></canvas>
                        <div class="donut-chart-label">
                            <div class="value">
                                <?php 
                                $completion_rate = ($status_breakdown['Completed'] / max(1, array_sum($status_breakdown))) * 100;
                                echo round($completion_rate); 
                                ?>%
                            </div>
                            <div class="label">Completed</div>
                        </div>
                    </div>
                </div>

                <!-- Most Bought Category -->
                <div class="donut-chart-card">
                    <div class="title">Most Bought Category</div>
                    <div class="donut-chart-wrapper">
                        <canvas id="category-chart"></canvas>
                        <div class="donut-chart-label">
                            <div class="value">
                                <?php echo $top_category_percentage; ?>%
                            </div>
                            <div class="label"><?php echo $top_category; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Metrics Row -->
            <div class="metric-row-lg">
                <!-- Average Order Value -->
                <div class="metric-card-lg">
                    <div class="icon average-order">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="content">
                        <div class="title">Average Order Value</div>
                        <div class="value2">RM <?php echo number_format($average_order_value, 2); ?></div>
                        <div class="change <?php echo ($aov_change >= 0) ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo ($aov_change >= 0) ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($aov_change); ?>%
                        </div>
                    </div>
                </div>

                <!-- Items per Order -->
                <div class="metric-card-lg">
                    <div class="icon items-per-order">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="content">
                        <div class="title">Items per Order</div>
                        <div class="value2"><?php echo number_format($items_per_order, 2); ?></div>
                        <div class="change <?php echo ($ipo_change >= 0) ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo ($ipo_change >= 0) ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($ipo_change); ?>%
                        </div>
                    </div>
                </div>

                <!-- Top Category Revenue -->
                <div class="metric-card-lg">
                    <div class="icon top-category">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="content">
                        <div class="title">Top Category Revenue</div>
                        <div class="value2"><?php echo $top_category_revenue; ?></div>
                    </div>
                </div>
            </div>

            <!-- Best Selling Products -->
            <div class="report-section bg-white rounded-lg shadow p-6 mt-6">
                <h3 class="text-xl font-medium mb-4">Top 5 Best Selling Products</h3>
                <div class="space-y-3">
                    <?php if (!empty($best_sellers)): ?>
                        <?php foreach ($best_sellers as $product): ?>
                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span class="text-lg font-normal leading-7"><?php echo htmlspecialchars($product['prod_Name']); ?></span>
                                <span class="bg-blue-100 text-blue-800 py-2 px-6 rounded-full text-m">
                                    <?php echo $product['total_quantity']; ?> sold
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500">No products sold in this period.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Revenue by Category -->
            <div class="report-section bg-white rounded-lg shadow p-6 mt-6">
                <h3 class="text-xl font-medium mb-4">Revenue by Category</h3>
                <div class="space-y-3">
                    <?php if (!empty($revenue_by_category)): ?>
                        <?php foreach ($revenue_by_category as $category): ?>
                            <div class="mb-3 py-3">
                                <div class="flex justify-between mb-1">
                                    <span class="text-lg font-normal leading-7"><?php echo htmlspecialchars($category['cat_Name']); ?></span>
                                    <span>RM <?php echo number_format($category['category_revenue'], 2); ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-blue-600 h-2.5 rounded-full" 
                                        style="width: <?php echo ($total_sales > 0) ? min(($category['category_revenue'] / $total_sales * 100), 100) : 0; ?>%">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500">No revenue data by category.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white shadow mt-8 p-4 text-center text-gray-600">
        <p>© 2025 JellyHome. All Rights Reserved.</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle Sidebar
            $('#toggle-sidebar, #toggle-sidebar-mobile').on('click', function() {
                $('#sidebar').toggleClass('sidebar-hidden');
                if (!$('#sidebar').hasClass('sidebar-hidden')) {
                    $('#main-content').addClass('ml-[20%]').removeClass('ml-0');
                } else {
                    $('#main-content').addClass('ml-0').removeClass('ml-[20%]');
                }
            });

            // Completion Rate Donut Chart
            const completionCtx = document.getElementById('completion-chart').getContext('2d');
            const completionChart = new Chart(completionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Non-Completed'],
                    datasets: [{
                        data: [
                            <?php echo $status_breakdown['Completed']; ?>,
                            <?php echo array_sum($status_breakdown) - $status_breakdown['Completed']; ?>
                        ],
                        backgroundColor: ['#10b981', '#e2e8f0'],
                        borderWidth: 0
                    }]
                },
                options: {
                    cutout: '70%',
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            enabled: true,
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw || 0;
                                    return `${label}: ${value}`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });

            // Category Donut Chart
            const categoryCtx = document.getElementById('category-chart').getContext('2d');
            const categoryChart = new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: ['<?php echo $top_category; ?>', 'Other'],
                    datasets: [{
                        data: [
                            <?php echo $top_category_percentage; ?>,
                            <?php echo 100 - $top_category_percentage; ?>
                        ],
                        backgroundColor: ['#4f46e5', '#e2e8f0'],
                        borderWidth: 0
                    }]
                },
                options: {
                    cutout: '70%',
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            enabled: true,
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw || 0;
                                    return `${label}: ${value}%`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
        });
    </script>
</body>
</html>