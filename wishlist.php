<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php?redirect=wishlist.php");
    exit;
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist Page</title>
    
    <link rel="stylesheet" href="wishlist.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="chat.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>

<body>
    <!-- Sidebar Menu -->
    <div id="mySidepanel" class="sidepanel">

        <!-- Category -->
        <div class="categories">
            <span class="closebtn" onclick="closeNav()">×</span>
            <ul><a href="#" class="category-item" data-target="living-room-sub">Living Room</a></ul>
            <ul><a href="#" class="category-item" data-target="kitchen-sub">Kitchen</a></ul>
            <ul><a href="#" class="category-item" data-target="bedroom-sub">Bedroom</a></ul>
            <ul><a href="#" class="category-item" data-target="dining-room-sub">Dining Room</a></ul>
        </div>

        <!-- Subcategory -->
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

    <!-- Wishlist Container -->
    <div class="wishlist-container"> 
        <h2>My Wishlist</h2>
        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <div class="products-container" id="wishlist-items"></div>
    </div>

    <!-- Footer Section -->
    <footer class="footer">
        <div class="footer-container">
            
            <!-- Column 1: Quick Links -->
            <div class="footer-column">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="#">Home</a></li>
                    <li><a href="#">Shop</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">FAQs</a></li>
                </ul>
            </div>

            <!-- Column 2: Categories -->
            <div class="footer-column">
                <h3>Categories</h3>
                <ul>
                    <li>Living Room</li>
                    <li>Kitchen</li>
                    <li>Bedroom</li>
                    <li>Dining Room</li>
                </ul>
            </div>

            <!-- Column 3: Contact Us -->
            <div class="footer-column">
                <h3>Contact Us</h3>
                <p>+6012-3456789</p>
                <p>support@jellyhome.com</p>
                <p>123 Creative Street, Design City, Malaysia</p>
            </div>

            <!-- Column 4: About Us & Logo -->
            <div class="footer-column about-column">
                <h3>About Us</h3>
                <p>We blend art with design to create unique, high-quality products that inspire creativity and style.</p>
                <a href="homepage.php#our-creative-section" class="navigate-btn">Learn More →</a>
                <div class="footer-logo">
                    <img src="assets/home/logo.png" alt="Brand Logo">
                </div>
            </div>

        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <p>&copy; 2025 Your Brand. All Rights Reserved.</p>
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

            // Bind click events for navigation links
            $("a[onclick='openNav()']").on("click", function(e) {
                e.preventDefault();
                openNav();
            });

            $("a[onclick='openSearch()']").on("click", function(e) {
                e.preventDefault();
                openSearch();
            });

            // Hover event for category items
            $(".category-item").on("mouseenter", function() {
                let panel = $("#mySidepanel");
                panel.css("width", "800px");
                $(".subcategories").show();
                $(".subcategory-list").hide();
                const targetId = $(this).data("target");
                $(`#${targetId}`).show();
            });

            // Bind close button click
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

            // Bind close button click for search panel
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
                console.log("Input event with query:", query);
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
                    console.log("Enter pressed with query:", query);
                    if (query.length > 0) {
                        fetchSearchResults(query);
                        suggestionsContainer.removeClass("show");
                        searchPanel.removeClass("partial-open").addClass("open");
                    }
                }
            });

            searchIcon.on("click", function() {
                const query = searchInput.val().trim();
                console.log("Search icon clicked with query:", query);
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
                console.log("Fetching search results for query:", query);
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
                            console.log("Rendering collections:", data.categories);
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

            // Wishlist Functionality
            function fetchWishlist() {
                console.log("Fetching wishlist...");
                $.ajax({
                    url: "wishlist_api.php?action=view",
                    method: "GET",
                    dataType: "json",
                    success: function(data) {
                        console.log("Wishlist data received:", data);
                        const container = $("#wishlist-items");
                        container.empty();
                        if (data.success && Array.isArray(data.items) && data.items.length > 0) {
                            $.each(data.items, function(index, item) {
                                console.log("Processing item:", item);
                                const div = $("<div>")
                                    .addClass("product-card-sub")
                                    .attr("data-prod-id", item.prod_Id)
                                    .css("cursor", "pointer");
                                div.html(`
                                    <div class="product-img-container" style="width: 300px; height: 280px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                                        <img src="${item.prod_Image || "https://via.placeholder.com/200"}" alt="${item.prod_Name || "Unnamed Product"}" style="max-width: 100%; max-height: 100%; object-fit: contain;" onerror="this.src='https://via.placeholder.com/200'; console.log('Image load failed for:', this.src);">
                                    </div>
                                    <div class="product-info">
                                        <p class="product-name">${item.prod_Name || "Unnamed Product"}</p>
                                        <p class="product-price">
                                            ${item.is_on_sale && item.sale_price > 0
                                                ? `<span class="original-price">RM ${item.prod_Price || 0}</span> <span class="sale-price">RM ${item.sale_price || 0}</span>`
                                                : `RM ${item.prod_Price || 0}`}
                                        </p>
                                    </div>
                                    <button class="remove-btn" data-prod-id="${item.prod_Id || 0}" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 18px;">🗑️</button>
                                `);
                                div.on("click", function() {
                                    window.location.href = `product.php?product_id=${item.prod_Id}`;
                                });
                                container.append(div);
                            });
                            $(".remove-btn").on("click", function(e) {
                                e.stopPropagation();
                                const prodId = $(this).data("prod-id");
                                const csrfToken = $("#csrf_token").val();
                                console.log("Removing item with prodId:", prodId);
                                $.ajax({
                                    url: "wishlist_api.php?action=remove",
                                    method: "POST",
                                    data: { prod_id: prodId, csrf_token: csrfToken },
                                    dataType: "json",
                                    success: function(response) {
                                        console.log("Remove response:", response);
                                        alert(response.message);
                                        if (response.success) fetchWishlist();
                                    },
                                    error: function(xhr, status, error) {
                                        console.error("Error removing item:", error);
                                        alert("Error removing from wishlist.");
                                    }
                                });
                            });
                        } else {
                            console.log("No items or API error:", data.message || "No items found");
                            container.html('<p class="wishlist-message">Love It ? Add To My Wishlist</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error fetching wishlist:", error);
                        container.html('<p>Error loading wishlist.</p>');
                    }
                });
            }

            fetchWishlist();
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