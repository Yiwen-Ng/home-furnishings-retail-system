<?php
session_start();
include 'db_config.php';

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user'])) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Fetch order details based on order_id from query parameter
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$order = [];
$order_items = [];
$is_cancelled = false;

try {
    $user = getUserData($pdo, $_SESSION['user']);
    $user_id = $user['user_id'];

    // Debug: Check received order_id and user_id
    // echo "Debug: order_id = $order_id, user_id = $user_id<br>";

    if ($order_id <= 0) {
        throw new Exception("Invalid order ID.");
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found or unauthorized access.");
    }

    // Check if order is marked as cancelled in session
    if (isset($_SESSION['cancelled_orders'][$order_id])) {
        $is_cancelled = true;
    } else {
        $stmt = $pdo->prepare("SELECT order_status FROM orders WHERE order_id = ? AND user_id = ?");
        $stmt->execute([$order_id, $user_id]);
        $status = $stmt->fetchColumn();
        $is_cancelled = (strtolower($status) === 'cancelled');
        if ($is_cancelled) {
            $_SESSION['cancelled_orders'][$order_id] = true;
        }
    }

    $stmt = $pdo->prepare("SELECT oi.*, p.prod_Name, p.prod_Image FROM order_items oi JOIN products p ON oi.prod_id = p.prod_Id WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch order history
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND order_id != ? ORDER BY order_date DESC LIMIT 5");
    $stmt->execute([$user_id, $order_id]);
    $order_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Debug: Log the error for investigation
    // error_log("Error in order_confirmation.php: " . $e->getMessage());
    die("Error: " . $e->getMessage());
}

function getUserData($pdo, $session_user) {
    if (is_array($session_user) && isset($session_user['user_id']) && isset($session_user['email'])) {
        return $session_user; // Return existing session data if valid
    }
    // Fallback: Fetch by user_id if available, or full_name as a last resort
    $condition = isset($session_user['user_id']) ? "user_id = ?" : "email = ?"; // Changed from full_name to email for better matching
    $param = isset($session_user['user_id']) ? $session_user['user_id'] : $session_user['email'];
    $stmt = $pdo->prepare("SELECT user_id, email, full_name FROM users WHERE $condition");
    $stmt->execute([$param]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['user'] = $user; // Update session with fetched data including full_name
        return $user;
    }
    return ['user_id' => 0, 'email' => 'user@email.com', 'full_name' => 'Guest']; // Default fallback with full_name
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation Page</title>

    <link rel="stylesheet" href="order_confirmation.css">
    <link rel="stylesheet" href="profile.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
</head>

<body class="bg-gray-100">
    <!-- Sidebar Menu -->
    <div id="mySidepanel" class="sidepanel">
        <div class="categories">
            <span class="closebtn" onclick="closeNav()">×</span>
            <ul><a href="#" class="category-item" data-target="living-room-sub">Living Room</a></ul>
            <ul><a href="#" class="category-item" data-target="kitchen-sub">Kitchen</a></ul>
            <ul><a href="#" class="category-item" data-target="bedroom-sub">Bedroom</a></ul>
            <ul><a href="#" class="category-item" data-target="dining-room-sub">Dining Room</a></ul>
        </div>
        <div class="subcategories">
            <div id="living-room-sub" class="subcategory-list">
                <ul><a href="subcategory.php?subcategories=Sofas">Sofas</a></ul>
                <ul><a href="subcategory.php?subcategories=Armchairs">Armchairs</a></ul>
                <ul><a href="subcategory.php?subcategories=Coffee and Side Tables">Coffee and Side Tables</a></ul>
            </div>
            <div id="kitchen-sub" class="subcategory-list">
                <ul><a href="subcategory.php?subcategories=Kitchen Islands">Kitchen Islands</a></ul>
                <ul><a href="subcategory.php?subcategories=Pantry Cupboards">Pantry Cupboards</a></ul>
                <ul><a href="subcategory.php?subcategories=Kitchen Sink Cabinets">Kitchen Sink Cabinets</a></ul>
            </div>
            <div id="bedroom-sub" class="subcategory-list">
                <ul><a href="subcategory.php?subcategories=Wardrobes">Wardrobes</a></ul>
                <ul><a href="subcategory.php?subcategories=Nightstands">Nightstands</a></ul>
                <ul><a href="subcategory.php?subcategories=Beds">Beds</a></ul>
            </div>
            <div id="dining-room-sub" class="subcategory-list">
                <ul><a href="subcategory.php?subcategories=Dining Tables">Dining Tables</a></ul>
                <ul><a href="subcategory.php?subcategories=Dining Chairs">Dining Chairs</a></ul>
                <ul><a href="subcategory.php?subcategories=Stools and Benches">Stools and Benches</a></ul>
            </div>
        </div>
    </div>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-left">
            <a href="homepage.php">Home</a>
            <a href="#" onclick="openNav()">Collection</a>
            <a href="#" onclick="openSearch()">Search</a>
        </div>
        <div class="nav-center">
            <img src="assets/home/logo.png" alt="Logo">
        </div>
        <div class="nav-right">
            <?php if (isset($_SESSION['user']) && is_array($_SESSION['user'])): ?>
                <a href="wishlist.php">Wishlist</a>
                <a href="cart.php">Cart</a>
                <a href="#" onclick="openProfilePanel()">Profile</a>
            <?php else: ?>
                <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Wishlist</a>
                <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Cart</a>
                <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Profile Side Panel -->
    <?php if (isset($_SESSION['user']) && is_array($_SESSION['user'])): ?>
        <div id="profilePanel" class="side-panel-profile">
            <a href="javascript:void(0)" class="closebtn-profile" onclick="closeProfilePanel()">×</a>
            <h2>Hey <?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? 'Guest'); ?> !</h2>
            
            <div class="menu-item" data-href="profile.php">
                <div class="menu-left"><span class="icon"><img src="assets/icon/user.png"></span></div>
                <div class="menu-center">
                    <a href="profile.php" class="menu-title">Profile Details</a>
                    <p class="profile-description">View and edit your profile details</p>
                </div>
                <div class="menu-right"><span class="arrow">→</span></div>
            </div>

            <div class="menu-item" data-href="order.php">
                <div class="menu-left"><span class="icon"><img src="assets/icon/box.png"></span></div>
                <div class="menu-center">
                    <a href="order.php" class="menu-title">Online Orders</a>
                    <p class="profile-description">View your order history and details</p>
                </div>
                <div class="menu-right"><span class="arrow">→</span></div>
            </div>

            <div class="menu-item" data-href="enquiry.php">
                <div class="menu-left"><span class="icon"><img src="assets/icon/help.png"></span></div>
                <div class="menu-center">
                    <a href="enquiry.php" class="menu-title">Product Enquiries</a>
                    <p class="profile-description">View your product enquiries and details</p>
                </div>
                <div class="menu-right"><span class="arrow">→</span></div>
            </div>

            <div class="menu-item" data-href="change_password.php">
                <div class="menu-left"><span class="icon"><img src="assets/icon/padlock.png"></span></div>
                <div class="menu-center">
                    <a href="change_password.php" class="menu-title">Change Password</a>
                    <p class="profile-description">Update your account password</p>
                </div>
                <div class="menu-right"><span class="arrow">→</span></div>
            </div>

            <div class="menu-item" data-href="delete_account.php">
                <div class="menu-left"><span class="icon"><img src="assets/icon/delete.png"></span></div>
                <div class="menu-center">
                    <a href="delete_account.php" class="menu-title">Delete Account</a>
                    <p class="profile-description">Permanently delete your account</p>
                </div>
                <div class="menu-right"><span class="arrow">→</span></div>
            </div>

            <div class="menu-item" data-href="logout.php">
                <div class="menu-left"><span class="icon"><img src="assets/icon/logout.png"></span></div>
                <div class="menu-center">
                    <a href="logout.php" class="menu-title">Logout</a>
                    <p class="profile-description">Sign out of your account</p>
                </div>
                <div class="menu-right"><span class="arrow">→</span></div>
            </div>
        </div>
    <?php else: ?>
        <div id="profilePanel" class="side-panel-profile" style="display: none;">
            <a href="javascript:void(0)" class="closebtn-profile" onclick="closeProfilePanel()">×</a>
            <h2>Hey Guest!</h2>
            <p>No user session found. <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Please login</a>.</p>
        </div>
    <?php endif; ?>

    <!-- Search Panel -->
    <div id="searchPanel" class="search-panel">
        <span class="closebtn" onclick="closeSearch()">×</span>
        <div class="search-container">
            <h2>Search Products</h2>
            <form id="searchForm" onsubmit="return false;">
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search products...">
                    <span class="search-icon">🔍</span>
                    <div class="suggestions" id="suggestions"></div>
                </div>
            </form>
        </div>
        <div class="search-results-container">
            <div class="searchcollection-column" id="searchCollectionColumn">
                <h3>COLLECTION</h3>
                <ul id="searchCollectionList"></ul>
            </div>
            <div class="search-results" id="searchResults"></div>
        </div>
    </div>

    <!-- Order Confirmation Section -->
    <div class="container">
        <div class="card">
            <svg class="<?php echo $is_cancelled ? 'cross-icon' : 'check-icon'; ?>" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="<?php echo $is_cancelled ? 'M6 18L18 6M6 6l12 12' : 'M5 13l4 4L19 7'; ?>"></path>
            </svg>
            <h1>Order #<?php echo htmlspecialchars($order['order_id']); ?></h1>
            <p><?php echo $is_cancelled ? 'Your order has been cancelled.' : 'Order placed successfully! You’ll receive an email once it’s processed.'; ?></p>
            <div class="details">
                <h2>Order Details</h2>
                <p><strong>Account:</strong> <?php echo htmlspecialchars($_SESSION['user']['email'] ?? 'user@email.com'); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars(date('F j, Y, g:i a', strtotime($order['order_date']))); ?></p>
                <p><strong>Items:</strong></p>
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <p><?php echo htmlspecialchars($item['prod_Name']); ?> (x<?php echo $item['quantity']; ?>) 
                            <?php if (!empty($item['color']) && $item['color'] !== 'Default'): ?>
                                <span class="color-info">- <?php echo htmlspecialchars($item['color']); ?></span>
                            <?php endif; ?>
                            <span class="price">RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="details">
                <h2>Shipping</h2>
                <p>Standard (10 days) <span class="price">RM 10.00</span></p>
            </div>
            <div class="details-total">
                <h2>Total<span class="price">RM <?php echo number_format($order['total_amount'], 2); ?></span></h2>
            </div>
            <div class="details">
                <h2>Status
                    <div class="status-box-up <?php echo $is_cancelled ? 'cancelled-status' : 'pending-status'; ?>">
                        <?php echo $is_cancelled ? 'Cancelled' : 'Pending'; ?>
                    </div>
                </h2>
            </div>
            <div class="buttons">
                <a href="homepage.php" class="btn btn-continue">Continue Shopping</a>
                <a href="homepage.php" class="btn btn-view">Go to Homepage</a>
                <form id="cancel-form" style="display:inline;">
                    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id']); ?>">
                    <button type="submit" class="btn btn-cancel" <?php echo $is_cancelled ? 'disabled' : ''; ?>>
                        <?php echo $is_cancelled ? 'Cancelled' : 'Cancel Order'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer Section -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-column">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="#">Home</a></li>
                    <li><a href="#">Shop</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">FAQs</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Categories</h3>
                <ul>
                    <li>Living Room</li>
                    <li>Kitchen</li>
                    <li>Bedroom</li>
                    <li>Dining Room</li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Contact Us</h3>
                <p>+6012-3456789</p>
                <p>support@jellyhome.com</p>
                <p>123 Creative Street, Design City, Malaysia</p>
            </div>
            <div class="footer-column about-column">
                <h3>About Us</h3>
                <p>We blend art with design to create unique, high-quality products that inspire creativity and style.</p>
                <a href="homepage.php#our-creative-section" class="navigate-btn">Learn More →</a>
                <div class="footer-logo">
                    <img src="assets/home/logo.png" alt="Brand Logo">
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2025 Your Brand. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Sidebar Navigation
        function openNav() {
            let panel = $("#mySidepanel");
            panel.css("width", "35%");
            $("body").addClass("sidebar-open");
            $(".subcategory-list").hide();
            $(".subcategories").hide();
        }

        function closeNav() {
            let panel = $("#mySidepanel");
            panel.css("width", "0");
            $("body").removeClass("sidebar-open");
        }

        $("a[onclick='openNav()']").on("click", function(e) {
            e.preventDefault();
            openNav();
        });

        $("a[onclick='openSearch()']").on("click", function(e) {
            e.preventDefault();
            openSearch();
        });

        $(".category-item").on("mouseenter", function() {
            let panel = $("#mySidepanel");
            panel.css("width", "800px");
            $(".subcategories").show();
            $(".subcategory-list").hide();
            const targetId = $(this).data("target");
            $(`#${targetId}`).show();
        });

        $(".sidepanel .closebtn").on("click", function() {
            closeNav();
        });

        // Search Panel
        function openSearch() {
            let panel = $("#searchPanel");
            panel.addClass("partial-open");
            $("body").addClass("search-open no-scroll");
            $("#searchInput").focus();
        }

        function closeSearch() {
            let panel = $("#searchPanel");
            panel.removeClass("partial-open open");
            $("body").removeClass("search-open no-scroll");
            $("#searchInput").val("");
            $("#searchResults").html("");
            $("#searchCollectionList").html("");
            $("#suggestions").removeClass("show");
        }

        $(".search-panel .closebtn").on("click", function() {
            closeSearch();
        });

        const searchInput = $("#searchInput");
        const searchIcon = $(".search-icon");
        const suggestionsContainer = $("#suggestions");
        const searchResults = $("#searchResults");
        const searchCollectionList = $("#searchCollectionList");
        const searchPanel = $("#searchPanel");

        searchInput.on("input", function() {
            const query = $(this).val().trim();
            if (query.length > 0) {
                fetchSuggestions(query);
                fetchSearchResults(query);
                suggestionsContainer.addClass("show");
                searchPanel.removeClass("partial-open").addClass("open");
            } else {
                suggestionsContainer.removeClass("show");
                searchResults.html("");
                searchCollectionList.html("");
                searchPanel.removeClass("open").addClass("partial-open");
            }
        });

        searchInput.on("keydown", function(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                const query = $(this).val().trim();
                if (query.length > 0) {
                    fetchSearchResults(query);
                    suggestionsContainer.removeClass("show");
                    searchPanel.removeClass("partial-open").addClass("open");
                }
            }
        });

        searchIcon.on("click", function() {
            const query = searchInput.val().trim();
            if (query.length > 0) {
                fetchSearchResults(query);
                suggestionsContainer.removeClass("show");
                searchPanel.removeClass("partial-open").addClass("open");
            }
        });

        suggestionsContainer.on("click", ".suggestion-item", function() {
            searchInput.val($(this).text());
            suggestionsContainer.removeClass("show");
            fetchSearchResults(searchInput.val());
            searchPanel.removeClass("partial-open").addClass("open");
        });

        $(document).on("click", function(e) {
            if (!searchInput.is(e.target) && !suggestionsContainer.is(e.target) && !searchIcon.is(e.target)) {
                suggestionsContainer.removeClass("show");
            }
        });

        function fetchSuggestions(query) {
            fetch(`search.php?suggest=${encodeURIComponent(query)}`)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    suggestionsContainer.html("");
                    data.forEach(keyword => {
                        $("<div>").addClass("suggestion-item").text(keyword).appendTo(suggestionsContainer);
                    });
                    if (data.length > 0) suggestionsContainer.addClass("show");
                    else suggestionsContainer.removeClass("show");
                })
                .catch(error => console.error("Error fetching suggestions:", error));
        }

        function fetchSearchResults(query) {
            fetch(`search.php?q=${encodeURIComponent(query)}&ajax=1`)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}, URL: search.php?q=${encodeURIComponent(query)}&ajax=1`);
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error(`Invalid JSON: ${text}`);
                        }
                    });
                })
                .then(data => {
                    if (data.html) {
                        searchResults.html(data.html);
                        animateResults();
                    } else {
                        searchResults.html(`<p class='no-results'>No results found for "${query}".</p>`);
                    }
                    searchCollectionList.html("");
                    if (data.categories && data.categories.length > 0) {
                        data.categories.forEach(category => {
                            $("<li>").append($("<span>").text(category)).appendTo(searchCollectionList);
                        });
                    }
                })
                .catch(error => {
                    console.error("Error fetching search results:", error.message);
                    searchResults.html(`<p class='no-results'>Error loading results: ${error.message}. Please try again.</p>`);
                    searchCollectionList.html("<li>Error loading collections</li>");
                });
        }

        function animateResults() {
            const cards = searchResults.find(".product-card-search");
            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const card = entry.target;
                        const index = cards.index(card);
                        $(card).css({
                            animation: "fadeInUp 0.6s ease forwards",
                            animationDelay: `${index * 0.15}s`
                        });
                        observer.unobserve(card);
                    }
                });
            }, { threshold: 0.2 });
            cards.each(function() {
                observer.observe(this);
            });
        }

        // Cancel Order Functionality
        $('#cancel-form').on('submit', function(e) {
            e.preventDefault();
            const orderId = $('input[name="order_id"]').val();
            $.ajax({
                url: 'cancel_order.php',
                type: 'POST',
                data: { order_id: orderId },
                success: function(response) {
                    if (response.success) {
                        if (!$_SESSION['cancelled_orders']) $_SESSION['cancelled_orders'] = {};
                        $_SESSION['cancelled_orders'][orderId] = true;
                        location.reload(); // Reload to reflect updated status
                    } else {
                        alert('Failed to cancel order: ' + response.message);
                    }
                },
                error: function() {
                    alert('An error occurred while cancelling the order.');
                }
            });
        });

        // Profile Panel Functions
        function openProfilePanel() {
            $("#profilePanel").addClass("expanded");
        }

        function closeProfilePanel() {
            $("#profilePanel").removeClass("expanded");
        }

        $("a[onclick='openProfilePanel()']").on("click", function(e) {
            e.preventDefault();
            openProfilePanel();
        });

        $(document).on("click", function(e) {
            if (!$(e.target).closest('.side-panel-profile').length && !$(e.target).closest("a[onclick='openProfilePanel()']").length) {
                closeProfilePanel();
            }
        });
    </script>
</body>
</html>