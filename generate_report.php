<?php
session_start();
include 'db_config.php';
require 'vendor/autoload.php'; // Include Composer autoloader for Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

// Redirect if not admin
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Initialize variables with default values
$total_sales = 0;
$status_breakdown = ['Pending' => 0, 'Completed' => 0, 'Cancelled' => 0];
$top_customers = [];
$top_category = 'N/A';
$top_category_percentage = 0;
$average_order_value = 0;
$items_per_order = 0;
$top_category_revenue = 'N/A';
$completed_change = $pending_change = $cancelled_change = 0;
$aov_change = $ipo_change = 0;

// Handle month selection
$selected_month = isset($_POST['month']) ? $_POST['month'] : date('Y-m');

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

    // Percentage changes
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

    // Average Order Value and Items per Order
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE order_status != 'Cancelled' AND order_date BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $non_cancelled_orders = $stmt->fetchColumn() ?? 0;

    $stmt = $pdo->prepare("
        SELECT SUM(oi.quantity) as total_items
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_status != 'Cancelled' AND o.order_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $total_items_sold = $stmt->fetchColumn() ?? 0;

    $average_order_value = $non_cancelled_orders > 0 ? $total_sales / $non_cancelled_orders : 0;
    $items_per_order = $non_cancelled_orders > 0 ? $total_items_sold / $non_cancelled_orders : 0;

    $cat_name = !empty($revenue_by_category[0]['cat_Name']) ? htmlspecialchars($revenue_by_category[0]['cat_Name'], ENT_QUOTES, 'UTF-8') : '';
    $top_category_revenue = !empty($revenue_by_category) ? $cat_name . ": RM " . number_format($revenue_by_category[0]['category_revenue'], 2) : 'N/A';

    // Previous month metrics
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

    $stmt = $pdo->prepare("SELECT SUM(total_amount) as total_sales FROM orders WHERE order_status != 'Cancelled' AND order_date BETWEEN ? AND ?");
    $stmt->execute([$prev_start_date, $prev_end_date]);
    $prev_total_sales = $stmt->fetchColumn() ?? 0;

    $prev_average_order_value = $prev_non_cancelled_orders > 0 ? $prev_total_sales / $prev_non_cancelled_orders : 0;
    $prev_items_per_order = $prev_non_cancelled_orders > 0 ? $prev_total_items_sold / $prev_non_cancelled_orders : 0;

    $aov_change = $prev_average_order_value > 0 ? 
        round((($average_order_value - $prev_average_order_value) / $prev_average_order_value) * 100, 1) : 0;
    $ipo_change = $prev_items_per_order > 0 ? 
        round((($items_per_order - $prev_items_per_order) / $prev_items_per_order) * 100, 1) : 0;
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("Error generating report: Database issue");
}

// HTML content for the PDF
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>JellyHome Report - <?php echo date('F Y', strtotime($selected_month)); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h1, h3 { color: #1f2937; }
        .metric-row-lg, .metric-row-sm { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .metric-card-lg, .metric-card-sm { background: #f3f4f6; padding: 15px; border-radius: 8px; width: 30%; }
        .metric-card-lg .title, .metric-card-sm .title { font-weight: bold; font-size: 14px; }
        .metric-card-lg .value, .metric-card-sm .value { font-size: 18px; color: #1f2937; }
        .metric-card-lg .value2 { font-size: 16px; color: #1f2937; }
        .change { font-size: 12px; }
        .positive { color: #10b981; }
        .negative { color: #ef4444; }
        .report-section { background: #f3f4f6; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .report-section h3 { font-size: 16px; }
        .progress-bar { background: #e2e8f0; height: 10px; border-radius: 5px; }
        .progress-fill { background: #4f46e5; height: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>JellyHome Report - <?php echo date('F Y', strtotime($selected_month)); ?></h1>

    <!-- Large Metric Cards -->
    <div class="metric-row-lg">
        <div class="metric-card-lg">
            <div class="title">Total Orders</div>
            <div class="value"><?php echo array_sum($status_breakdown); ?></div>
        </div>
        <div class="metric-card-lg">
            <div class="title">Total Revenue</div>
            <div class="value">RM <?php echo number_format($total_sales, 2); ?></div>
        </div>
    </div>

    <!-- Small Metric Cards -->
    <div class="metric-row-sm">
        <div class="metric-card-sm">
            <div class="title">Completed</div>
            <div class="value"><?php echo $status_breakdown['Completed']; ?></div>
            <div class="change <?php echo ($completed_change >= 0) ? 'positive' : 'negative'; ?>">
                <?php echo ($completed_change >= 0) ? '+' : '-'; ?><?php echo abs($completed_change); ?>%
            </div>
        </div>
        <div class="metric-card-sm">
            <div class="title">Pending</div>
            <div class="value"><?php echo $status_breakdown['Pending']; ?></div>
            <div class="change <?php echo ($pending_change >= 0) ? 'positive' : 'negative'; ?>">
                <?php echo ($pending_change >= 0) ? '+' : '-'; ?><?php echo abs($pending_change); ?>%
            </div>
        </div>
        <div class="metric-card-sm">
            <div class="title">Cancelled</div>
            <div class="value"><?php echo $status_breakdown['Cancelled']; ?></div>
            <div class="change <?php echo ($cancelled_change >= 0) ? 'positive' : 'negative'; ?>">
                <?php echo ($cancelled_change >= 0) ? '+' : '-'; ?><?php echo abs($cancelled_change); ?>%
            </div>
        </div>
    </div>

    <!-- Chart Data as Text -->
    <div class="metric-row-lg">
        <div class="metric-card-lg">
            <div class="title">Completion Rate</div>
            <div class="value"><?php echo round(($status_breakdown['Completed'] / max(1, array_sum($status_breakdown))) * 100); ?>% Completed</div>
        </div>
        <div class="metric-card-lg">
            <div class="title">Most Bought Category</div>
            <div class="value"><?php echo $top_category_percentage; ?>% <?php echo htmlspecialchars($top_category); ?></div>
        </div>
    </div>

    <!-- New Metrics Row -->
    <div class="metric-row-lg">
        <div class="metric-card-lg">
            <div class="title">Average Order Value</div>
            <div class="value2">RM <?php echo number_format($average_order_value, 2); ?></div>
            <div class="change <?php echo ($aov_change >= 0) ? 'positive' : 'negative'; ?>">
                <?php echo ($aov_change >= 0) ? '+' : '-'; ?><?php echo abs($aov_change); ?>%
            </div>
        </div>
        <div class="metric-card-lg">
            <div class="title">Items per Order</div>
            <div class="value2"><?php echo number_format($items_per_order, 2); ?></div>
            <div class="change <?php echo ($ipo_change >= 0) ? 'positive' : 'negative'; ?>">
                <?php echo ($ipo_change >= 0) ? '+' : '-'; ?><?php echo abs($ipo_change); ?>%
            </div>
        </div>
        <div class="metric-card-lg">
            <div class="title">Top Category Revenue</div>
            <div class="value2"><?php echo $top_category_revenue; ?></div>
        </div>
    </div>

    <!-- Best Selling Products -->
    <div class="report-section">
        <h3>Top 5 Best Selling Products</h3>
        <?php if (!empty($best_sellers)): ?>
            <?php foreach ($best_sellers as $product): ?>
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
                    <span><?php echo htmlspecialchars($product['prod_Name']); ?></span>
                    <span><?php echo $product['total_quantity']; ?> sold</span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No products sold in this period.</p>
        <?php endif; ?>
    </div>

    <!-- Revenue by Category -->
    <div class="report-section">
        <h3>Revenue by Category</h3>
        <?php if (!empty($revenue_by_category)): ?>
            <?php foreach ($revenue_by_category as $category): ?>
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span><?php echo htmlspecialchars($category['cat_Name']); ?></span>
                        <span>RM <?php echo number_format($category['category_revenue'], 2); ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($total_sales > 0) ? min(($category['category_revenue'] / $total_sales * 100), 100) : 0; ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No revenue data by category.</p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Initialize Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // Allow remote images if needed
$dompdf = new Dompdf($options);

// Load HTML content
$dompdf->loadHtml($html);

// Set paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render the PDF
$dompdf->render();

// Output the PDF as a download
$dompdf->stream('JellyHome_Reports_' . date('F_Y', strtotime($selected_month)) . '.pdf', ['Attachment' => true]);
?>