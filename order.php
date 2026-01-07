<?php
session_start();
include 'db_config.php';

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user'])) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    header('Content-Type: application/json');

    $user_id = $_SESSION['user']['user_id'];

    // Validate POST data
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $rating   = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comment  = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    if ($order_id <= 0 || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
        exit;
    }

    // Check if this user already submitted a review for this order
    $checkStmt = $pdo->prepare("SELECT rating_id FROM order_ratings WHERE order_id = ? AND user_id = ?");
    $checkStmt->execute([$order_id, $user_id]);
    if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'You already submitted a review for this order.']);
        exit;
    }

    // Handle image upload (optional)
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/reviews/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log("Failed to create upload directory: $uploadDir");
                echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
                exit;
            }
        }

        // Check directory permissions
        if (!is_writable($uploadDir)) {
            error_log("Upload directory ($uploadDir) is not writable by web server (user: daemon).");
            echo json_encode(['success' => false, 'message' => 'Upload directory is not writable.']);
            exit;
        }

        // Validate file type and size (max 2MB, only images)
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $file_type = $_FILES['image']['type'];
        $file_size = $_FILES['image']['size'];

        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Only JPEG, PNG, or GIF images are allowed.']);
            exit;
        }
        if ($file_size > $max_size) {
            echo json_encode(['success' => false, 'message' => 'Image must be less than 2MB.']);
            exit;
        }

        $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $file_name = $user_id . '_' . $order_id . '_' . time() . '.' . $file_ext;
        $destPath = $uploadDir . $file_name;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
            $error_code = $_FILES['image']['error'];
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => "File exceeds PHP's upload_max_filesize.",
                UPLOAD_ERR_FORM_SIZE => "File exceeds form's MAX_FILE_SIZE.",
                UPLOAD_ERR_PARTIAL => "File was only partially uploaded.",
                UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder.",
                UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                UPLOAD_ERR_EXTENSION => "A PHP extension stopped the upload."
            ];
            $error_message = $error_messages[$error_code] ?? "Unknown upload error.";
            error_log("Failed to move uploaded file to $destPath. Error code: $error_code ($error_message)");
            echo json_encode(['success' => false, 'message' => "Failed to upload image: $error_message"]);
            exit;
        }

        $image_path = $destPath;
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error_code = $_FILES['image']['error'];
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => "File exceeds PHP's upload_max_filesize.",
            UPLOAD_ERR_FORM_SIZE => "File exceeds form's MAX_FILE_SIZE.",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the upload."
        ];
        $error_message = $error_messages[$error_code] ?? "Unknown upload error.";
        echo json_encode(['success' => false, 'message' => "File upload error: $error_message"]);
        exit;
    }

    // Insert review
    $stmt = $pdo->prepare("INSERT INTO order_ratings (order_id, user_id, rating, comment, image_path) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$order_id, $user_id, $rating, $comment, $image_path])) {
        echo json_encode(['success' => true, 'message' => 'Review submitted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->errorInfo()[2]]);
    }
    exit;
}

// Handle fetching review for display
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_review') {
    header('Content-Type: application/json');
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $user_id = $_SESSION['user']['user_id'];

    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT rating, comment, image_path FROM order_ratings WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($review) {
        echo json_encode(['success' => true, 'review' => $review]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No review found for this order.']);
    }
    exit;
}

// Fetch user data
try {
    $user = getUserData($pdo, $_SESSION['user']);
    $user_id = $user['user_id'];

    // Fetch all orders for the user
    $stmt = $pdo->prepare("SELECT order_id, order_date, total_amount, order_status FROM orders WHERE user_id = ? ORDER BY order_date DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count summary stats
    $total_orders = count($orders);
    $completed = count(array_filter($orders, fn($o) => strtolower($o['order_status']) === 'completed'));
    $cancelled = count(array_filter($orders, fn($o) => strtolower($o['order_status']) === 'cancelled'));
    $pending   = count(array_filter($orders, fn($o) => strtolower($o['order_status']) === 'pending'));

    $filter = $_GET['filter'] ?? 'all';

    $filtered_orders = match($filter) {
        'completed' => array_filter($orders, fn($o) => strtolower($o['order_status']) === 'completed'),
        'cancelled' => array_filter($orders, fn($o) => strtolower($o['order_status']) === 'cancelled'),
        'pending' => array_filter($orders, fn($o) => strtolower($o['order_status']) === 'pending'),
        default => $orders,
    };
} catch (Exception $e) {
    die("Error fetching orders: " . $e->getMessage());
}

function getUserData($pdo, $session_user) {
    if (is_array($session_user) && isset($session_user['user_id']) && isset($session_user['email'])) {
        if (!isset($session_user['full_name'])) {
            // Fetch full_name if not present in session
            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
            $stmt->execute([$session_user['user_id']]);
            $name = $stmt->fetchColumn();
            if ($name) {
                $session_user['full_name'] = $name;
                $_SESSION['user'] = $session_user; // Update session with full_name
            }
        }
        return $session_user; // Return updated session data
    }
    // Fallback: Fetch by user_id if available, or full_name as a last resort
    $condition = isset($session_user['user_id']) ? "user_id = ?" : "full_name = ?";
    $param = isset($session_user['user_id']) ? $session_user['user_id'] : $session_user;
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
    <title>Order Page</title>
    
    <link rel="stylesheet" href="order.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="chat.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>

<body>
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

    <div class="chatbot-overlay" onclick="toggleChat()"></div>

    <!-- AI ChatBot -->
    <div class="chat-widget">
        <div class="chat-header" onclick="toggleChat()">
            <span>Chat with JellyBot</span>
            <span class="chat-toggle-icon"></span>
        </div>
        <div class="chat-body">
            <div class="chat-messages" id="chat-messages">
                <div class="message bot-message">
                    <p style="white-space: pre-line;">Hello! I'm JellyBot. How can I help you today? You can ask me about products or your order status.</p>
                </div>
            </div>
            <div class="chat-input-container">
                <input type="text" id="chat-input" placeholder="Ask a question...">
                <button id="send-btn">Send</button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Orders Section -->
        <div class="container">
            <h2>My Orders</h2>

            <!-- Summary Description -->
            <p class="summary-text">Organize all of your ordered products</p>

            <?php if (empty($orders)): ?>
                <p class="no-orders">No orders found.</p>
            <?php else: ?>

                <!-- Summary Section -->
                <div class="summary-cards">
                    <div class="card-summary">
                        <div class="card-content">
                            <div class="icon"><img src="assets/icon/box.png"></img></div>
                            <div class="info">
                                <h4>Total Orders</h4>
                                <p><?= $total_orders ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="card-summary">
                        <div class="card-content">
                            <div class="icon"><img src="assets/icon/delivery.png"></img></div>
                            <div class="info">
                                <h4>Completed</h4>
                                <p><?= $completed ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="card-summary">
                        <div class="card-content">
                            <div class="icon"><img src="assets/icon/product-return.png"></img></div>
                            <div class="info">
                                <h4>Cancelled</h4>
                                <p><?= $cancelled ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="card-summary">
                        <div class="card-content">
                            <div class="icon"><img src="assets/icon/time.png"></img></div>
                            <div class="info">
                                <h4>Pending</h4>
                                <p><?= $pending ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <a href="?filter=all" class="tab <?= $filter === 'all' ? 'active' : '' ?>">All <span><?= $total_orders ?></span></a>
                    <a href="?filter=completed" class="tab <?= $filter === 'completed' ? 'active' : '' ?>">Completed <span><?= $completed ?></span></a>
                    <a href="?filter=cancelled" class="tab <?= $filter === 'cancelled' ? 'active' : '' ?>">Cancelled <span><?= $cancelled ?></span></a>
                    <a href="?filter=pending" class="tab <?= $filter === 'pending' ? 'active' : '' ?>">Pending <span><?= $pending ?></span></a>
                </div>
                <hr class="tab-divider">
                
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($filtered_orders)): ?>
                            <tr><td colspan="4" style="text-align:center;">No orders found.</td></tr>
                        <?php else: ?>
                            <?php
                            // Prepare the statement to check for an existing review for efficiency
                            $reviewCheckStmt = $pdo->prepare("SELECT rating_id FROM order_ratings WHERE order_id = ? AND user_id = ?");
                            ?>
                            <?php foreach ($filtered_orders as $order): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                                    <td>RM <?= number_format($order['total_amount'], 2) ?></td>
                                    <?php
                                    $status = strtolower($order['order_status']);
                                    $status_class = match($status) {
                                        'pending' => 'status-pending',
                                        'cancelled' => 'status-cancelled',
                                        'completed' => 'status-completed',
                                        default => ''
                                    };
                                    ?>
                                    <td>
                                        <span class="status-<?php echo strtolower($order['order_status']); ?>">
                                            <?php echo htmlspecialchars($order['order_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-icon btn-view" href="javascript:void(0);" onclick="viewOrder(<?= $order['order_id'] ?>)" title="View Order">
                                            <img src="assets/icon/file.png" alt="View Icon" class="icon-img">
                                        </a>
                                        <?php if (strtolower($order['order_status']) === 'completed'): ?>
                                            <?php
                                            // Check for an existing review for the current order
                                            $reviewCheckStmt->execute([$order['order_id'], $user_id]);
                                            $has_review = $reviewCheckStmt->fetch(PDO::FETCH_ASSOC);
                                            ?>
                                            <a href="#" class="btn btn-icon rate-order" data-order-id="<?php echo htmlspecialchars($order['order_id']); ?>" data-has-review="<?php echo $has_review ? 'true' : 'false'; ?>" title="<?php echo $has_review ? 'View Review' : 'Rate Order'; ?>">
                                                <img src="assets/icon/<?php echo $has_review ? 'view-review' : 'rate'; ?>.png" alt="<?php echo $has_review ? 'View Review Icon' : 'Rate Icon'; ?>" class="icon-img">
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rating Modal -->
    <div id="ratingModal" class="modal">
        <div class="modal-content">
            <a href="javascript:void(0)" class="closebtn-modal" onclick="closeRatingModal()">×</a>
            <h3>Leave a Review</h3>
            <p class="modal-desc">Share your experience with us.</p>
            <form id="ratingForm">
                <div class="rating-section">
                    <p>Your Rating</p>
                    <div class="stars" data-rating="0">
                        <span class="star" data-value="1">☆</span>
                        <span class="star" data-value="2">☆</span>
                        <span class="star" data-value="3">☆</span>
                        <span class="star" data-value="4">☆</span>
                        <span class="star" data-value="5">☆</span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="comment">Comments</label>
                    <textarea id="comment" name="comment" rows="4" placeholder="Share your feedback..."></textarea>
                </div>
                <div class="form-group">
                    <label for="image-upload">Upload an Image</label>
                    <input type="file" id="image-upload" name="image" accept="image/jpeg,image/png,image/gif">
                </div>
                <div class="modal-buttons">
                    <button type="submit" class="btn-submit-rating">Submit Review</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Review Display Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <a href="javascript:void(0)" class="closebtn-modal" onclick="closeReviewModal()">×</a>
            <h3>Your Review</h3>
            <p class="modal-desc">Here is the review you submitted for this order.</p>
            <div class="review-content">
                <div class="rating-section">
                    <p>Your Rating</p>
                    <div class="stars" id="reviewStars"></div>
                </div>
                <div class="form-group">
                    <label>Comments</label>
                    <p id="reviewComment" class="review-text"></p>
                </div>
                <div class="form-group">
                    <label>Uploaded Image</label>
                    <div id="reviewImageContainer" class="reviewImage"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sliding Side Panel -->
    <div id="orderPanel" class="order-panel">
        <div class="order-panel-content">
            <span class="close-btn" onclick="closeOrderPanel()">×</span>
            <div id="orderDetails">Loading...</div>
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
        $(document).ready(function() {
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
        });

        function viewOrder(orderId) {
            const panel = document.getElementById('orderPanel');
            const content = document.getElementById('orderDetails');
            
            // Load content via AJAX
            fetch('order_confirmation_content.php?order_id=' + orderId)
                .then(response => response.text())
                .then(html => {
                    content.innerHTML = html;
                    panel.classList.add('open');
                    
                    // Reinitialize event handlers for the loaded content
                    initOrderConfirmationHandlers();
                })
                .catch(err => {
                    content.innerHTML = '<p>Error loading order details.</p>';
                    console.error(err);
                });
        }

        function initOrderConfirmationHandlers() {
            // Cancel button click handler
            $('#cancel-button').off('click').on('click', function(e) {
                console.log('Cancel button clicked');
                showCancelModal();
            });

            // Modal confirm cancel
            $('#confirm-cancel').off('click').on('click', function() {
                console.log('Confirm cancel clicked');
                confirmCancel();
            });

            // Modal keep order
            $('#keep-order').off('click').on('click', function() {
                console.log('Keep order clicked');
                closeCancelModal();
            });

            // Close modal when clicking outside
            $(document).off('click', '#cancelModal.show').on('click', function(e) {
                if ($(e.target).is('#cancelModal.show')) {
                    console.log('Clicked outside modal');
                    closeCancelModal();
                }
            });

            function showCancelModal() {
                console.log('Showing cancel modal');
                $('#cancelModal').addClass('show');
            }

            function closeCancelModal() {
                console.log('Closing cancel modal');
                $('#cancelModal').removeClass('show');
            }

            function confirmCancel() {
                console.log('Confirming cancellation');
                const orderId = $('input[name="order_id"]').val();
                $.ajax({
                    url: 'cancel_order.php',
                    type: 'POST',
                    data: { order_id: orderId },
                    dataType: 'json',
                    success: function(response) {
                        console.log('AJAX success:', response);
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Failed to cancel order: ' + response.message);
                            closeCancelModal();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        alert('An error occurred while cancelling the order.');
                        closeCancelModal();
                    }
                });
            }
        }

        function closeOrderPanel() {
            document.getElementById('orderPanel').classList.remove('open');
            // Remove body class if it was added
            // document.body.classList.remove('panel-open'); // Comment out or remove
        }

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

        // Rating and Review Modal Functions
        function openRatingModal(orderId) {
            $('#ratingModal').addClass('open');
            $('body').addClass('no-scroll');
            $('#ratingModal').data('order-id', orderId);
        }

        function closeRatingModal() {
            $('#ratingModal').removeClass('open');
            $('body').removeClass('no-scroll');
            $('#ratingForm')[0].reset();
            $('.stars').attr('data-rating', '0');
            $('.star').text('☆');
        }

        function openReviewModal(orderId) {
            $.ajax({
                url: 'order.php',
                type: 'POST',
                data: { action: 'get_review', order_id: orderId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.review) {
                        $('#reviewStars').html('');
                        for (let i = 1; i <= 5; i++) {
                            $('#reviewStars').append(
                                `<span class="star" data-value="${i}">${i <= response.review.rating ? '★' : '☆'}</span>`
                            );
                        }
                        $('#reviewComment').text(response.review.comment || 'No comment provided.');
                        if (response.review.image_path) {
                            $('#reviewImageContainer').html(
                                `<img src="${response.review.image_path}" alt="Review Image" class="review-image">`
                            );
                        } else {
                            $('#reviewImageContainer').html('<p>No image uploaded.</p>');
                        }
                        $('#reviewModal').addClass('open');
                        $('body').addClass('no-scroll');
                    } else {
                        showCustomMessage('Error: ' + (response.message || 'Unable to load review.'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error, xhr.responseText);
                    showCustomMessage('An error occurred while loading the review.');
                }
            });
        }

        function closeReviewModal() {
            $('#reviewModal').removeClass('open');
            $('body').removeClass('no-scroll');
            $('#reviewStars').html('');
            $('#reviewComment').text('');
            $('#reviewImageContainer').html('');
        }

        $(document).on('click', '.rate-order', function(e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');
            const hasReview = $(this).data('has-review') === true;
            if (hasReview) {
                openReviewModal(orderId);
            } else {
                openRatingModal(orderId);
            }
        });

        $(document).on('click', '.star', function() {
            const rating = $(this).data('value');
            const starsContainer = $(this).parent();
            starsContainer.attr('data-rating', rating);
            starsContainer.children('.star').each(function() {
                const starValue = $(this).data('value');
                $(this).text(starValue <= rating ? '★' : '☆');
            });
        });

        // Validate file size before upload
        $('#image-upload').on('change', function() {
            const maxSize = 2 * 1024 * 1024; // 2MB
            if (this.files[0] && this.files[0].size > maxSize) {
                showCustomMessage('Image must be less than 2MB.');
                this.value = '';
            }
        });

        // Handle rating form submit
        $('#ratingForm').on('submit', function(e) {
            e.preventDefault();
            const orderId = $('#ratingModal').data('order-id');
            const rating = $('.stars').attr('data-rating');
            const comment = $('#comment').val().trim();
            const formData = new FormData(this);
            formData.append('action', 'submit_review');
            formData.append('order_id', orderId);
            formData.append('rating', rating);

            if (!orderId || orderId <= 0) {
                showCustomMessage('Invalid order ID.');
                return;
            }
            if (rating < 1 || rating > 5) {
                showCustomMessage('Please select a rating between 1 and 5 stars.');
                return;
            }

            $.ajax({
                url: 'order.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhrFields: { withCredentials: true },
                success: function(response) {
                    try {
                        const res = typeof response === 'string' ? JSON.parse(response) : response;
                        if (res.success) {
                            showCustomMessage('Thank you for your feedback!');
                            closeRatingModal();
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showCustomMessage('Failed to submit review: ' + res.message);
                        }
                    } catch (e) {
                        console.error('Invalid JSON response:', response);
                        showCustomMessage('Unexpected server response. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error, xhr.responseText);
                    showCustomMessage('An error occurred while submitting your review: ' + (xhr.responseText || error));
                }
            });
        });

        function showCustomMessage(message) {
            const modalHtml = `
                <div id="customMessageModal" class="modal">
                    <div class="modal-content small-modal">
                        <h3>Notice</h3>
                        <p>${message}</p>
                        <div class="modal-buttons">
                            <button class="btn-cancel-modal" onclick="closeCustomMessage()">OK</button>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHtml);
            $('#customMessageModal').addClass('open');
            $('body').addClass('no-scroll');
        }

        function closeCustomMessage() {
            $('#customMessageModal').remove();
            $('body').removeClass('no-scroll');
        }

        // AI Chatbot Functions
        const chatBody = document.querySelector('.chat-body');
        const chatWidget = document.querySelector('.chat-widget');
        const chatInput = document.getElementById('chat-input');
        const sendBtn = document.getElementById('send-btn');
        const chatMessages = document.getElementById('chat-messages');
        const chatbotOverlay = document.querySelector('.chatbot-overlay'); // Get the new overlay element
        const body = document.body; // Get the body element

        function toggleChat() {
            chatWidget.classList.toggle('collapsed');

            // Toggle the active state of the overlay and body
            if (chatWidget.classList.contains('collapsed')) {
                chatbotOverlay.style.display = 'none';
                body.classList.remove('chat-active');
            } else {
                chatbotOverlay.style.display = 'block';
                setTimeout(() => {
                    chatbotOverlay.style.opacity = '1';
                }, 10); // A slight delay for the transition
                body.classList.add('chat-active');
            }
        }

        function addMessage(data, sender) {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message', `${sender}-message`);

            if (typeof data === 'string') {
                const p = document.createElement('p');
                p.textContent = data;
                p.style.whiteSpace = 'pre-line'; // Preserve newlines and spaces
                messageDiv.appendChild(p);
            } else if (typeof data === 'object') {
                if (data.reply) {
                    const p = document.createElement('p');
                    p.textContent = data.reply;
                    p.style.whiteSpace = 'pre-line'; // Preserve newlines and spaces
                    messageDiv.appendChild(p);
                }
                if (data.products && Array.isArray(data.products)) {
                    const productsContainer = document.createElement('div');
                    productsContainer.className = 'products-container-chatbot';
                    data.products.forEach(product => {
                        const productCard = document.createElement('div');
                        productCard.className = 'product-card-chatbot';
                        productCard.innerHTML = `
                            <a href="${product.url}" target="_blank">
                                <img src="${product.image}" alt="${product.name}" class="product-image-chatbot">
                                <div class="product-info-chatbot">
                                    <p class="product-name-chatbot">${product.name}</p>
                                    <p class="product-price-chatbot">RM ${product.price}</p>
                                    <p class="product-stock-chatbot">${product.stock}</p>
                                </div>
                            </a>
                        `;
                        productsContainer.appendChild(productCard);
                    });
                    messageDiv.appendChild(productsContainer);
                }
            }
            
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        async function sendMessage() {
            const message = chatInput.value.trim();
            if (message === '') return;

            addMessage(message, 'user');
            chatInput.value = '';

            addMessage("JellyBot is thinking...", 'bot');

            try {
                const response = await fetch('chatbot_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: message })
                });

                if (!response.ok) throw new Error('Network response was not ok');
                
                const data = await response.json();
                
                chatMessages.removeChild(chatMessages.lastChild); // Remove "thinking..."
                addMessage(data, 'bot');

            } catch (error) {
                console.error('Fetch Error:', error);
                chatMessages.removeChild(chatMessages.lastChild);
                addMessage('Sorry, something went wrong. Please try again.', 'bot');
            }
        }

        sendBtn.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        // Set initial state as collapsed
        chatWidget.classList.add('collapsed');
    </script>
</body>
</html>