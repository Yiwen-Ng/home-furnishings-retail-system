<?php
session_start();
include 'db_config.php';

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
            <?php if (isset($_SESSION['user'])): ?>
                <a href="wishlist.php">Wishlist</a>
                <a href="cart.php">Cart</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php?redirect=wishlist.php">Wishlist</a>
                <a href="login.php?redirect=cart.php">Cart</a>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </nav>

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
                    <button type="submit" class="checkout-confirm">Confirm Order</button>
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

            // Cart Functionality
            function fetchCart() {
                fetch('cart_api.php?action=view')
                    .then(response => response.json())
                    .then(data => {
                        const container = $('#cart-items');
                        const summary = $('#cart-summary');
                        container.html('');
                        let subtotal = 0;
                        if (data.success && data.items.length > 0) {
                            data.items.forEach(item => {
                                const itemPrice = item.is_on_sale && item.sale_price > 0 ? item.sale_price : item.prod_Price;
                                const itemTotal = itemPrice * item.quantity;
                                subtotal += itemTotal;
                                const dimensionLines = item.dimension !== 'N/A' ? item.dimension.split('\n').join('<br>') : 'N/A';
                                const div = $('<div>').addClass('product-card-sub');
                                div.html(`
                                    <div class="product-img-container">
                                        ${item.is_featured ? '<span class="product-badge featured">Featured</span>' : ''}
                                        ${item.is_on_sale ? '<span class="product-badge sale">Sale</span>' : ''}
                                        <img src="${item.prod_Image}" alt="${item.prod_Name}">
                                    </div>
                                    <div class="product-info">
                                        <p class="product-name">${item.prod_Name}</p>
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
                                const csrfToken = $('#csrf_token').val();
                                fetch('cart_api.php?action=remove', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: `prod_id=${prodId}&csrf_token=${csrfToken}`
                                })
                                    .then(response => response.json())
                                    .then(data => {
                                        alert(data.message);
                                        if (data.success) fetchCart();
                                    });
                            });
                        } else {
                            container.html('<p>No items in your cart.</p>');
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
                    });

                function updateQuantity(prodId, quantity) {
                    const csrfToken = $('#csrf_token').val();
                    fetch('cart_api.php?action=update', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `prod_id=${prodId}&quantity=${quantity}&csrf_token=${csrfToken}`
                    })
                        .then(response => response.json())
                        .then(data => {
                            alert(data.message);
                            if (data.success) fetchCart();
                        });
                }
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
            }

            $('#checkout-form').on('submit', function(e) {
                e.preventDefault();
                const csrfToken = $('#csrf_token_checkout').val();
                const formData = {
                    full_name: $('#full-name').val(),
                    email: $('#email').val(),
                    address: $('#address').val(),
                    city: $('#city').val(),
                    postal_code: $('#postal-code').val(),
                    payment_method: $('#payment-method').val(),
                    csrf_token: csrfToken
                };
                console.log("Checkout form data:", formData);

                fetch('cart_api.php?action=view')
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success || !data.items || data.items.length === 0) {
                            alert('Your cart is empty.');
                            return Promise.reject(new Error('Cart is empty'));
                        }
                        console.log("Cart data:", data.items);
                        const cartData = { items: data.items, total: $('#total').text() };
                        return fetch('checkout_api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `data=${encodeURIComponent(JSON.stringify({ ...formData, cart: cartData }))}`
                        });
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log("Checkout response:", data);
                        if (data.success && data.order_id) {
                            closeCheckoutPanel();
                            fetchCart();
                            window.location.href = `order_confirmation.php?order_id=${data.order_id}`;
                        } else {
                            alert('Error placing order: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Checkout fetch error:', error);
                        alert('An error occurred during checkout. Please try again.');
                    });
            });

            fetchCart();
        });
    </script>
</body>
</html>