<?php
session_start();
include 'db_config.php';

// Redirect if not admin
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Fetch notifications from orders table, left join with notifications for read/archive status

// Optional: exclude cancelled orders

try {
    $stmt = $pdo->query("
        SELECT 
            o.order_id, 
            COALESCE(n.message, CONCAT('New order #', o.order_id, ' from ', u.full_name)) AS message,
            o.order_date AS created_at, 
            COALESCE(n.is_read, 0) AS is_read, 
            COALESCE(n.is_archived, 0) AS is_archived
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN notifications n ON o.order_id = n.order_id
        ORDER BY o.order_date DESC
        LIMIT 50
    ");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notifications = [];
    error_log("Error fetching notifications: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

    <link rel="stylesheet" href="admin_dashboard.css">
    <link rel="stylesheet" href="notification.css">
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
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 24, 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <h1 class="text-2xl">Notifications</h1>
            <div class="flex items-center space-x-4 text-xl">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['user']['full_name']); ?></span>
            </div>
        </header>

        <!-- Notification Content -->
        <div class="notification-container">
            <div class="notification-header">
                <h1></h1>
                <div class="notification-actions">
                    <button id="mark-all-read">Mark All as Read</button>
                    <button id="sync-notifications">Sync Notifications</button>
                </div>
            </div>

            <div class="tabs">
                <div class="tab active" data-tab="all">All</div>
                <div class="tab" data-tab="unread">Unread</div>
                <div class="tab" data-tab="archived">Archived</div>
            </div>

            <div class="notification-list">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">No new orders yet.</div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item fade-in <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?> <?php echo $notif['is_archived'] == 1 ? 'archived' : ''; ?>" data-order-id="<?php echo $notif['order_id']; ?>">
                            <div class="notification-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notification-time"><?php echo date('F j, Y, g:i a', strtotime($notif['created_at'])); ?></div>
                            </div>
                            <div class="notification-actions-small">
                                <button class="mark-read" title="Mark as Read"><i class="fas fa-check"></i></button>
                                <button class="archive" title="Archive"><i class="fas fa-archive"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
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
                $('#main-content').toggleClass('ml-[20%] ml-0');
            });

            // Tab switching
            $('.tab').on('click', function() {
                $('.tab').removeClass('active');
                $(this).addClass('active');
                const tab = $(this).data('tab');
                filterNotifications(tab);
            });

            function filterNotifications(tab) {
                $('.notification-item').each(function() {
                    const $item = $(this);
                    const isUnread = $item.hasClass('unread');
                    const isArchived = $item.hasClass('archived');
                    if (tab === 'all') {
                        if (!isArchived) $item.removeClass('hidden');
                        else $item.addClass('hidden');
                    } else if (tab === 'unread') {
                        if (isUnread && !isArchived) $item.removeClass('hidden');
                        else $item.addClass('hidden');
                    } else if (tab === 'archived') {
                        if (isArchived) $item.removeClass('hidden');
                        else $item.addClass('hidden');
                    }
                });
            }

            // Initial filter
            filterNotifications('all');

            // Use event delegation for dynamically loaded elements
            $(document).on('click', '.mark-read', function(e) {
                e.stopPropagation();
                const $item = $(this).closest('.notification-item');
                const orderId = $item.data('order-id');
                $.ajax({
                    url: 'mark_notification_read.php',
                    type: 'POST',
                    data: { order_id: orderId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $item.removeClass('unread');
                            filterNotifications($('.tab.active').data('tab'));
                        } else {
                            alert('Error marking as read: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error marking as read.');
                    }
                });
            });

            $(document).on('click', '.archive', function(e) {
                e.stopPropagation();
                const $item = $(this).closest('.notification-item');
                const orderId = $item.data('order-id');
                $.ajax({
                    url: 'archive_notification.php',
                    type: 'POST',
                    data: { order_id: orderId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $item.addClass('archived');
                            filterNotifications($('.tab.active').data('tab'));
                        } else {
                            alert('Error archiving: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error archiving.');
                    }
                });
            });

            // Mark all as read
            $('#mark-all-read').on('click', function() {
                $.ajax({
                    url: 'mark_all_notifications_read.php',
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('.notification-item').removeClass('unread');
                            filterNotifications($('.tab.active').data('tab'));
                        } else {
                            alert('Error marking all as read: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error marking all as read.');
                    }
                });
            });

            $('#sync-notifications').on('click', function() {
                $.ajax({
                    url: 'sync_notifications.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Synced ' + response.count + ' notifications');
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error syncing notifications');
                    }
                });
            });
        });
    </script>
</body>
</html>