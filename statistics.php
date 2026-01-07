<?php
session_start();
include 'db_config.php';

// Redirect if not admin
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Initialize variables
$selected_month = date('Y-m');
$total_sales = 0;
$total_orders = 0;
$unique_customers = 0;
$status_breakdown = ['Pending' => 0, 'Completed' => 0, 'Cancelled' => 0];
$sales_trend = [];
$category_revenue = [];
$top_products = [];
$customer_retention_rate = 0;

// Handle month selection
if (isset($_POST['month'])) {
    $selected_month = $_POST['month'];
}

try {
    $start_date = date('Y-m-01', strtotime($selected_month));
    $end_date = date('Y-m-t', strtotime($selected_month));

    // Total Sales
    $stmt = $pdo->prepare("SELECT SUM(total_amount) as total_sales FROM orders WHERE order_status != 'Cancelled' AND order_date BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $total_sales = $stmt->fetchColumn() ?? 0;

    // Total Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE order_date BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $total_orders = $stmt->fetchColumn() ?? 0;

    // Unique Customers
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as count FROM orders WHERE order_date BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $unique_customers = $stmt->fetchColumn() ?? 0;

    // Order Status Breakdown
    $stmt = $pdo->prepare("SELECT order_status, COUNT(*) as count FROM orders WHERE order_date BETWEEN ? AND ? GROUP BY order_status");
    $stmt->execute([$start_date, $end_date]);
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($status_counts as $status) {
        $status_breakdown[$status['order_status']] = $status['count'];
    }

    // Sales Trend (last 6 months)
    $sales_trend = [];
    for ($i = 5; $i >= 0; $i--) {
        $month_start = date('Y-m-01', strtotime($selected_month . " -$i months"));
        $month_end = date('Y-m-t', strtotime($selected_month . " -$i months"));
        $stmt = $pdo->prepare("SELECT SUM(total_amount) as total_sales FROM orders WHERE order_status != 'Cancelled' AND order_date BETWEEN ? AND ?");
        $stmt->execute([$month_start, $month_end]);
        $sales_trend[] = [
            'month' => date('M Y', strtotime($month_start)),
            'sales' => $stmt->fetchColumn() ?? 0
        ];
    }

    // Category Revenue
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
    $category_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top Products by Quantity Sold
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
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Customer Retention Rate
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT o.user_id) as repeat_customers
        FROM orders o
        WHERE o.order_date BETWEEN ? AND ?
        AND o.user_id IN (
            SELECT user_id
            FROM orders
            WHERE order_date < ?
            GROUP BY user_id
        )
    ");
    $stmt->execute([$start_date, $end_date, $start_date]);
    $repeat_customers = $stmt->fetchColumn() ?? 0;
    $customer_retention_rate = $unique_customers > 0 ? round(($repeat_customers / $unique_customers) * 100) : 0;
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

    <link rel="stylesheet" href="admin_dashboard.css">
    <link rel="stylesheet" href="statistics.css">
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
            <h1 class="text-2xl">Statistics</h1>
            <div class="flex items-center space-x-4 text-xl">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['user']['full_name']); ?></span>
            </div>
        </header>

        <!-- Statistics Content -->
        <div class="container mx-auto p-6">
            <!-- Month Selector -->
            <form method="POST" class="mb-6">
                <label for="month" class="block text-sm font-medium text-gray-700 mb-2">Select Month</label>
                <div class="flex">
                    <input type="month" 
                           id="month" 
                           name="month" 
                           value="<?php echo $selected_month; ?>" 
                           class="rounded-l-md border border-gray-300 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           max="<?php echo date('Y-m'); ?>">
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-r-md transition duration-200">
                        Apply
                    </button>
                </div>
            </form>

            <!-- Display selected month -->
            <div class="text-lg font-semibold text-gray-800 mb-6">
                Showing statistics for <?php echo date('F Y', strtotime($selected_month)); ?>
            </div>

            <!-- Summary Metrics -->
            <div class="metric-row-lg">
                <div class="metric-card-lg">
                    <div class="icon orders">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="content">
                        <div class="title">Total Orders</div>
                        <div class="value"><?php echo $total_orders; ?></div>
                    </div>
                </div>
                <div class="metric-card-lg">
                    <div class="icon revenue">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="content">
                        <div class="title">Total Revenue</div>
                        <div class="value">RM <?php echo number_format($total_sales, 2); ?></div>
                    </div>
                </div>
                <div class="metric-card-lg">
                    <div class="icon top-category">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="content">
                        <div class="title">Unique Customers</div>
                        <div class="value"><?php echo $unique_customers; ?></div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="donut-chart-container">
                <!-- Sales Trend Line Chart -->
                <div class="donut-chart-card">
                    <div class="title">Sales Trend (Last 6 Months)</div>
                    <div class="donut-chart-wrapper" style="width: 100%; height: 300px;">
                        <canvas id="sales-trend-chart"></canvas>
                    </div>
                </div>
                <!-- Category Revenue Bar Chart -->
                <div class="donut-chart-card">
                    <div class="title">Revenue by Category</div>
                    <div class="donut-chart-wrapper" style="width: 100%; height: 300px;">
                        <canvas id="category-revenue-chart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Additional Charts -->
            <div class="donut-chart-container">
                <!-- Order Status Pie Chart -->
                <div class="donut-chart-card">
                    <div class="title">Order Status Distribution</div>
                    <div class="donut-chart-wrapper">
                        <canvas id="status-chart"></canvas>
                        <div class="donut-chart-label">
                            <div class="value"><?php echo round(($status_breakdown['Completed'] / max(1, $total_orders)) * 100); ?>%</div>
                            <div class="label">Completed</div>
                        </div>
                    </div>
                </div>
                <!-- Top Products Bar Chart -->
                <div class="donut-chart-card">
                    <div class="title">Top 5 Products by Units Sold</div>
                    <div class="donut-chart-wrapper" style="width: 100%; height: 300px;">
                        <canvas id="top-products-chart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Customer Retention -->
            <div class="metric-card-lg">
                <div class="icon top-category">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="content">
                    <div class="title">Customer Retention Rate</div>
                    <div class="value"><?php echo $customer_retention_rate; ?>%</div>
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

            // Sales Trend Line Chart
            const salesTrendCtx = document.getElementById('sales-trend-chart').getContext('2d');
            new Chart(salesTrendCtx, {
                type: 'line',
                data: {
                    labels: [<?php echo "'" . implode("','", array_column($sales_trend, 'month')) . "'"; ?>],
                    datasets: [{
                        label: 'Revenue (RM)',
                        data: [<?php echo implode(',', array_column($sales_trend, 'sales')); ?>],
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Revenue (RM)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Category Revenue Bar Chart
            const categoryRevenueCtx = document.getElementById('category-revenue-chart').getContext('2d');
            new Chart(categoryRevenueCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo "'" . implode("','", array_column($category_revenue, 'cat_Name')) . "'"; ?>],
                    datasets: [{
                        label: 'Revenue (RM)',
                        data: [<?php echo implode(',', array_column($category_revenue, 'category_revenue')); ?>],
                        backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#3b82f6', '#ef4444'],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Revenue (RM)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Category'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Order Status Pie Chart
            const statusCtx = document.getElementById('status-chart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Pending', 'Cancelled'],
                    datasets: [{
                        data: [
                            <?php echo $status_breakdown['Completed']; ?>,
                            <?php echo $status_breakdown['Pending']; ?>,
                            <?php echo $status_breakdown['Cancelled']; ?>
                        ],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    cutout: '70%',
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Top Products Bar Chart
            const topProductsCtx = document.getElementById('top-products-chart').getContext('2d');
            new Chart(topProductsCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo "'" . implode("','", array_column($top_products, 'prod_Name')) . "'"; ?>],
                    datasets: [{
                        label: 'Units Sold',
                        data: [<?php echo implode(',', array_column($top_products, 'total_quantity')); ?>],
                        backgroundColor: '#4f46e5',
                        borderRadius: 8
                    }]
                },
                options: {
                    indexAxis: 'y', // Horizontal bar chart
                    responsive: true,
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Units Sold'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Product'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Export to PDF
            $('#export-pdf').on('click', function() {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                html2canvas(document.querySelector('.container'), {
                    scale: 2,
                    useCORS: true
                }).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    const imgProps = doc.getImageProperties(imgData);
                    const pdfWidth = doc.internal.pageSize.getWidth();
                    const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
                    doc.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
                    doc.save('JellyHome_Statistics_<?php echo date('F_Y', strtotime($selected_month)); ?>.pdf');
                });
            });
        });
    </script>
</body>
</html>