<?php
session_start();
include 'db_config.php';
require 'vendor/autoload.php'; // For Dotenv
use Dotenv\Dotenv;

// Load environment variables (fix: was missing)
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user'])) {
    header("Location: login.php?redirect=cart.php");
    exit;
}

// Generate CSRF token for security
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart Page</title>
    
    <link rel="stylesheet" href="cart.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="chat.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Include PayPal JavaScript SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo htmlspecialchars($_ENV['PAYPAL_CLIENT_ID'] ?? ''); ?>&currency=MYR"></script>
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

    <!-- Cart Container -->
    <div class="cart-container">
        <h2>My Cart</h2>
        <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="cart-split">
            <div class="cart-items-panel" id="cart-items-panel">
                <div class="products-container" id="cart-items"></div>
                <div class="cart-summary" id="cart-summary">
                    <h3>Order Summary</h3>
                    <p>Subtotal: <span id="subtotal">RM 0.00</span></p>
                    <p>Shipping: <span id="shipping">RM 0.00</span></p>
                    <p><em>Or pick up your order FREE</em></p>
                    <p class="total">Total: <span id="total">RM 0.00</span></p>
                    <p class="payment-method">Payment Method: [ To be selected at checkout ]</p>
                    <div class="cart-actions">
                        <button class="continue" onclick="window.location.href='homepage.php'">Continue Shopping</button>
                        <button class="checkout" id="checkout-btn">Checkout</button>
                    </div>
                </div>
            </div>
            <div class="checkout-panel" id="checkout-panel">
                <h2>Checkout</h2>
                <form id="checkout-form">
                    <input type="hidden" id="csrf_token_checkout" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" id="paypal_order_id" name="paypal_order_id">
                    
                    <div class="form-group">
                        <label for="full-name">Full Name:</label>
                        <input type="text" id="full-name" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo isset($_SESSION['user']['email']) ? htmlspecialchars($_SESSION['user']['email']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address:</label>
                        <textarea id="address" name="address" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="city">City:</label>
                        <input type="text" id="city" name="city" required>
                    </div>
                    <div class="form-group">
                        <label for="postal-code">Postal Code:</label>
                        <input type="text" id="postal-code" name="postal_code" required>
                    </div>
                    <div class="form-group">
                        <label for="payment-method">Payment Method:</label>
                        <select id="payment-method" name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash_on_delivery">Cash on Delivery</option>
                        </select>
                    </div>
                    <div class="form-group" id="bank-transfer-options" style="display: none;">
                        <label for="bank-transfer-type">Bank Transfer Type:</label>
                        <select id="bank-transfer-type" name="bank_transfer_type">
                            <option value="">Select Bank</option>
                            <option value="public_bank">Public Bank</option>
                            <option value="maybank">Maybank</option>
                            <option value="cimb">CIMB Bank</option>
                        </select>
                    </div>         
                    <div id="paypal-button-container" style="display: none;"></div>
                    <button type="submit" class="checkout-confirm" id="confirm-order-btn">Confirm Order</button>
                </form>
                <div id="loading-indicator">
                    <div id="loading-style">
                        <h3>Processing your order...</h3>
                        <p>Please do not close this window.</p>
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
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

            // Global variable to store cart data
            let cartData = null;

            // Check for recent order and redirect to order confirmation
            function checkRecentOrder() {
                fetch('cart_api.php?action=check_recent_order')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.order_id) {
                            window.location.href = `order_confirmation.php?order_id=${data.order_id}`;
                        } else {
                            fetchCart();
                        }
                    })
                    .catch(error => {
                        console.error('Error checking recent order:', error);
                        fetchCart();
                    });
            }

            // Cart Functionality
            function fetchCart() {
                const container = $('#cart-items');
                const summary = $('#cart-summary');
                container.html('');
                fetch('cart_api.php?action=view')
                    .then(response => response.json())
                    .then(data => {
                        console.log('Cart API Response:', data);
                        let subtotal = 0;
                        if (data.success && data.items && data.items.length > 0) {
                            cartData = { items: data.items, total: 0 };
                            data.items.forEach(item => {
                                const itemPrice = item.is_on_sale && item.sale_price > 0 ? item.sale_price : item.prod_Price;
                                const itemTotal = itemPrice * item.quantity;
                                subtotal += itemTotal;
                                const dimensionLines = item.dimension !== 'N/A' ? item.dimension.split('\n').join('<br>') : 'N/A';
                                const div = $('<div>').addClass('product-card-sub');
                                div.html(`
                                    <div class="product-img-container">                                        
                                        <img src="${item.prod_Image}" alt="${item.prod_Name}">
                                    </div>
                                    <div class="product-info">
                                        <p class="product-name">${item.prod_Name}</p>
                                        <p class="product-color">Color: ${item.color || 'Default'}</p>
                                        <p class="product-dimension">${dimensionLines}</p>
                                        <div class="quantity-controls">
                                            <div class="cart-quantity-stepper cart-quantity-stepper--small">
                                                <button class="cart-btn cart-btn--xsmall cart-btn--icon-tertiary cart-quantity-stepper__decrease" type="button">
                                                    <span class="cart-btn__inner">
                                                        <svg viewBox="0 0 35 35" focusable="false" width="35" height="35" aria-hidden="true" class="cart-svg-icon cart-btn__icon">
                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M17 13H7v-2h10v2z"></path>
                                                        </svg>
                                                    </span>
                                                </button>
                                                <input class="cart-quantity-stepper__input" inputmode="numeric" type="text" min="0" max="999" value="${item.quantity}" style="width: 5ch;" data-prod-id="${item.prod_Id}">
                                                <span class="cart-quantity-stepper__sr-label">cart-item-quantity-stepper</span>
                                                <button class="cart-btn cart-btn--xsmall cart-btn--icon-tertiary cart-quantity-stepper__increase" type="button">
                                                    <span class="cart-btn__inner">
                                                        <svg viewBox="0 0 35 35" focusable="false" width="35" height="35" aria-hidden="true" class="cart-svg-icon cart-btn__icon">
                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M10.998 13v4h2v-4h4v-2h-4V7h-2v4h-4v2h4z"></path>
                                                        </svg>
                                                    </span>
                                                </button>
                                                <span class="cart-quantity-stepper__border"></span>
                                                <span class="cart-quantity-stepper__sr-label" aria-live="assertive"></span>
                                            </div>
                                            <button class="remove-btn" data-prod-id="${item.prod_Id}">Remove</button>
                                        </div>
                                    </div>
                                    <div class="product-price">
                                        ${item.is_on_sale && item.sale_price > 0
                                            ? `<span class="original-price">RM ${item.prod_Price}</span> <span class="sale-price">RM ${item.sale_price}</span>`
                                            : `RM ${item.prod_Price}`}
                                    </div>
                                `);
                                container.append(div);
                            });
                            cartData.total = (subtotal + (subtotal > 0 ? 10.00 : 0.00)).toFixed(2);
                            $('.cart-quantity-stepper__increase').on('click', function() {
                                const prodId = $(this).closest('.cart-quantity-stepper').find('.cart-quantity-stepper__input').data('prod-id');
                                const input = $(this).closest('.cart-quantity-stepper').find('.cart-quantity-stepper__input');
                                let quantity = parseInt(input.val()) || 0;
                                quantity = Math.min(999, quantity + 1);
                                input.val(quantity);
                                updateQuantity(prodId, quantity);
                            });
                            $('.cart-quantity-stepper__decrease').on('click', function() {
                                const prodId = $(this).closest('.cart-quantity-stepper').find('.cart-quantity-stepper__input').data('prod-id');
                                const input = $(this).closest('.cart-quantity-stepper').find('.cart-quantity-stepper__input');
                                let quantity = parseInt(input.val()) || 0;
                                quantity = Math.max(0, quantity - 1);
                                input.val(quantity);
                                updateQuantity(prodId, quantity);
                            });
                            $('.remove-btn').on('click', function() {
                                const prodId = $(this).data('prod-id');
                                const color = $(this).closest('.product-card-sub').find('.product-color').text().replace('Color: ', '');
                                const csrfToken = $('#csrf_token').val();
                                fetch('cart_api.php?action=remove', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: `prod_id=${prodId}&color=${encodeURIComponent(color)}&csrf_token=${csrfToken}`
                                })
                                .then(response => response.json())
                                .then(data => {
                                    alert(data.message);
                                    if (data.success) fetchCart();
                                });
                            });
                        } else {
                            container.html('<p>No items in your cart.</p>');
                            cartData = { items: [], total: 0 };
                        }
                        const shipping = subtotal > 0 ? 10.00 : 0.00;
                        const total = subtotal + shipping;
                        $('#subtotal').text(`RM ${subtotal.toFixed(2)}`);
                        $('#shipping').text(`RM ${shipping.toFixed(2)}`);
                        $('#total').text(`RM ${total.toFixed(2)}`);
                    })
                    .catch(error => {
                        console.error('Error fetching cart:', error);
                        $('#cart-items').html('<p>Error loading cart.</p>');
                        cartData = { items: [], total: 0 };
                    });
            }

            function updateQuantity(prodId, quantity) {
                const color = $(`input[data-prod-id="${prodId}"]`).closest('.product-card-sub').find('.product-color').text().replace('Color: ', '');
                const csrfToken = $('#csrf_token').val();
                fetch('cart_api.php?action=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `prod_id=${prodId}&quantity=${quantity}&color=${encodeURIComponent(color)}&csrf_token=${csrfToken}`
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) fetchCart();
                });
            }

            // Clear Cart Function
            function clearCart() {
                fetch('cart_api.php?action=clear', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `csrf_token=${$('#csrf_token').val()}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        fetchCart();
                    } else {
                        console.error('Error clearing cart:', data.message);
                    }
                })
                .catch(error => console.error('Error clearing cart:', error));
            }

            // Checkout Functionality
            $('#checkout-btn').on('click', function() {
                $('#checkout-panel').addClass('show');
                $('#cart-items-panel').addClass('checkout-active');
            });

            function closeCheckoutPanel() {
                $('#checkout-panel').removeClass('show');
                $('#cart-items-panel').removeClass('checkout-active');
                $('#checkout-form')[0].reset();
                $('#paypal-button-container').hide();
                $('#bank-transfer-options').hide();
                $('#confirm-order-btn').show();
                $('#paypal_order_id').val('');
            }

            // Payment Method Change Handler
            $('#payment-method').on('change', function() {
                const paymentMethod = $(this).val();
                const paypalContainer = $('#paypal-button-container');
                const bankOptionsDiv = $('#bank-transfer-options');
                const confirmButton = $('#confirm-order-btn');

                // Validate form fields
                const fullName = $('#full-name').val().trim();
                const email = $('#email').val().trim();
                const address = $('#address').val().trim();
                const city = $('#city').val().trim();
                const postalCode = $('#postal-code').val().trim();

                if (!fullName || !email || !address || !city || !postalCode) {
                    alert('Please fill in all required fields (Full Name, Email, Address, City, Postal Code) before selecting a payment method.');
                    $(this).val(''); // Reset payment method
                    paypalContainer.hide();
                    bankOptionsDiv.hide();
                    confirmButton.show();
                    return;
                }

                if (paymentMethod === 'credit_card') {
                    paypalContainer.show();
                    bankOptionsDiv.hide();
                    confirmButton.hide();
                    renderPaypalButtons();
                } else if (paymentMethod === 'bank_transfer') {
                    paypalContainer.hide();
                    bankOptionsDiv.show();
                    confirmButton.show();
                } else {
                    paypalContainer.hide();
                    bankOptionsDiv.hide();
                    confirmButton.show();
                }
            });

            $('#bank-transfer-type').on('change', function() {
                const bankType = $(this).val();
                if (bankType) {
                    $('#confirm-order-btn').show();
                }
            });

            // Render PayPal Buttons
            function renderPaypalButtons() {
                if (!cartData || !cartData.total) {
                    alert('Cart is empty or not loaded. Please try again.');
                    return;
                }

                $('#paypal-button-container').empty();
                paypal.Buttons({
                    createOrder: function(data, actions) {
                        const fullName = $('#full-name').val().trim();
                        const address = $('#address').val().trim();
                        const city = $('#city').val().trim();
                        const postalCode = $('#postal-code').val().trim();

                        return actions.order.create({
                            purchase_units: [{
                                amount: {
                                    value: cartData.total,
                                    currency_code: 'MYR'
                                },
                                shipping: {
                                    name: {
                                        full_name: fullName
                                    },
                                    address: {
                                        address_line_1: address,
                                        admin_area_2: city,
                                        postal_code: postalCode,
                                        country_code: 'MY'
                                    }
                                }
                            }],
                            application_context: {
                                shipping_preference: 'SET_PROVIDED_ADDRESS'
                            }
                        });
                    },
                    onApprove: function(data, actions) {
                        $('#paypal_order_id').val(data.orderID);
                        $('#checkout-form').submit(); // This now triggers the AJAX handler
                    },
                    onCancel: function(data) {
                        $('#paypal_order_id').val(data.orderID || ''); 
                        $('#checkout-form').submit(); // This now triggers the AJAX handler
                    },
                    onError: function(err) {
                        console.error('PayPal error. Submitting order as Pending:', err);
                        $('#checkout-form').submit(); // This now triggers the AJAX handler
                    }
                }).render('#paypal-button-container');
            }

            // Checkout Form Submission Handler
            $('#checkout-form').on('submit', function(e) {
                e.preventDefault();
                
                if (!cartData || !cartData.items || cartData.items.length === 0) {
                    alert('Your cart is empty. Add items before checking out.');
                    return;
                }

                const paymentMethod = $('#payment-method').val();
                const bankType = $('#bank-transfer-type').val();

                if (paymentMethod === 'credit_card' && !$('#paypal_order_id').val()) {
                    alert('Please complete the PayPal payment process.');
                    return;
                }

                if (paymentMethod === 'bank_transfer' && !bankType) {
                    alert('Please select a bank for bank transfer.');
                    return;
                }

                const formData = {
                    full_name: $('#full-name').val(),
                    email: $('#email').val(),
                    address: $('#address').val(),
                    city: $('#city').val(),
                    postal_code: $('#postal-code').val(),
                    payment_method: paymentMethod,
                    bank_transfer_type: bankType || null,
                    paypal_order_id: $('#paypal_order_id').val(),
                    csrf_token: $('#csrf_token_checkout').val(),
                    cart: cartData
                };

                console.log('Form Data:', formData);

                fetch('checkout_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `data=${encodeURIComponent(JSON.stringify(formData))}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.text().then(text => {
                        console.log('Raw response from checkout_api.php:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error(`Invalid JSON from checkout_api.php: ${text}`);
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        if (data.redirectUrl) {
                            // Store order ID in localStorage to handle return from bank
                            localStorage.setItem('recent_order_id', data.order_id);
                            window.location.href = data.redirectUrl; // Redirect to bank website
                        } else {
                            closeCheckoutPanel();
                            clearCart();
                            window.location.href = `order_confirmation.php?order_id=${data.order_id}`;
                        }
                    } else {
                        throw new Error(data.message || 'Checkout failed');
                    }
                })
                .catch(error => {
                    console.error('Checkout error:', error);
                    alert('An error occurred during checkout: ' + error.message);
                });
            });

            // Check for recent order on page load
            checkRecentOrder();

            // Handle return from bank
            const urlParams = new URLSearchParams(window.location.search);
            const returnOrderId = urlParams.get('order_id') || localStorage.getItem('recent_order_id');
            if (returnOrderId) {
                localStorage.removeItem('recent_order_id');
                window.location.href = `order_confirmation.php?order_id=${returnOrderId}`;
            }

            // Payment Method Toggle
            const paymentMethodSelect = document.getElementById('payment-method');
            const bankOptionsDiv = document.getElementById('bank-transfer-options');
            const paypalButtonDiv = document.getElementById('paypal-button-container');

            function togglePaymentOptions() {
                bankOptionsDiv.style.display = 'none';
                paypalButtonDiv.style.display = 'none';

                const selectedValue = paymentMethodSelect.value;
                if (selectedValue === 'bank_transfer') {
                    bankOptionsDiv.style.display = 'block';
                } else if (selectedValue === 'credit_card') {
                    paypalButtonDiv.style.display = 'block';
                }
            }

            paymentMethodSelect.addEventListener('change', togglePaymentOptions);
            togglePaymentOptions();
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

        $(document).ready(function() {
            $('#checkout-form').on('submit', function(e) {
                // Prevent the default form submission
                e.preventDefault();

                // Show the loading indicator
                $('#loading-indicator').show();

                // Prepare the form data for submission via AJAX
                var formData = new FormData(this);

                // Submit the form using AJAX
                $.ajax({
                    url: 'checkout_api.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        // Hide the loading indicator
                        $('#loading-indicator').hide();

                        if (response.success) {
                            // Check for a redirect URL from the server
                            if (response.redirectUrl) {
                                window.location.href = response.redirectUrl;
                            } else {
                                // Redirect to the order confirmation page
                                window.location.href = 'order_confirmation.php?order_id=' + response.order_id;
                            }
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Hide the loading indicator in case of an error
                        $('#loading-indicator').hide();
                        // alert('An error occurred during checkout. Please try again.');
                        // console.error(error);
                    }
                });
            });
        });

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