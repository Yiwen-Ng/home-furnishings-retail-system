<?php
session_start();
include 'db_config.php';

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Fetch user data
try {
    $user = getUserData($pdo, $_SESSION['user']);
    $user_id = $user['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';

        // Validate input
        if (empty($password)) {
            throw new Exception("Password is required to delete your account.");
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            throw new Exception("Incorrect password.");
        }

        // Delete user data from database
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        if (!$stmt->execute([$user_id])) {
            $error_info = $stmt->errorInfo();
            error_log("SQL Error: " . json_encode($error_info));
            throw new Exception("Failed to delete account: " . $error_info[2]);
        }

        // Delete profile picture if it exists and is not the default
        if ($user['profile_picture'] && $user['profile_picture'] !== 'assets/default_avatar.png' && file_exists($user['profile_picture'])) {
            if (!unlink($user['profile_picture'])) {
                error_log("Failed to delete profile picture: " . $user['profile_picture']);
            }
        }

        // Clear session and redirect to homepage
        session_destroy();
        header("Location: homepage.php?deleted=1");
        exit;
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

function getUserData($pdo, $session_user) {
    if (is_array($session_user) && isset($session_user['user_id'])) {
        $stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_number, address, profile_picture, password FROM users WHERE user_id = ?");
        $stmt->execute([$session_user['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return $result;
        }
    }
    return [
        'user_id' => 0,
        'full_name' => 'Guest',
        'email' => 'user@email.com',
        'phone_number' => '',
        'address' => '',
        'profile_picture' => '',
        'password' => ''
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Account Page</title>

    <link rel="stylesheet" href="search.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="detail.css">
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
            <?php if (isset($_SESSION['user'])): ?>
                <a href="wishlist.php">Wishlist</a>
                <a href="cart.php">Cart</a>
                <a href="#" onclick="openProfilePanel()">Profile</a>
            <?php else: ?>
                <a href="login.php?redirect=wishlist.php">Wishlist</a>
                <a href="login.php?redirect=cart.php">Cart</a>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Profile Side Panel -->
    <?php if (isset($_SESSION['user'])): ?>
        <div id="profilePanel" class="side-panel-profile">
            <a href="javascript:void(0)" class="closebtn-profile" onclick="closeProfilePanel()">×</a>
            <h2>Hey <?php echo isset($_SESSION['user']['full_name']) ? htmlspecialchars($_SESSION['user']['full_name']) : 'Guest'; ?> !</h2>
            
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
        <!-- <p>No user session found.</p> -->
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

    <!-- Profile Section -->
    <div class="profile-container">
        <div class="profile-sidebar">
            <img src="<?php echo htmlspecialchars($user['profile_picture'] ?: 'assets/default_avatar.png'); ?>" alt="Avatar" class="avatar">
            <h3><?php echo htmlspecialchars($user['full_name'] ?? 'Guest'); ?></h3>
            <ul>
                <li><a href="profile.php"><img src="assets/icon/user.png" alt="Profile">Profile Details</a></li>
                <li><a href="order.php"><img src="assets/icon/box.png" alt="Orders">Online Orders</a></li>
                <li><a href="enquiry.php"><img src="assets/icon/help.png" alt="Enquiries">Product Enquiries</a></li>
                <li><a href="change_password.php"><img src="assets/icon/padlock.png" alt="Password">Change Password</a></li>
                <li><a href="delete_account.php" class="active"><img src="assets/icon/delete.png" alt="Delete">Delete Account</a></li>
                <li><a href="logout.php"><img src="assets/icon/logout.png" alt="Logout">Logout</a></li>
            </ul>
        </div>
        <div class="profile-content">
            <div class="profile-header">
                <h1>Delete Account</h1>
                <p style="color: #dc3545; font-size: 16px;">Warning: This action is permanent and cannot be undone.</p>
                <?php if (isset($error)): ?>
                    <p class="error"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
            </div>
            <form class="profile-form" method="POST">
                <div class="form-group">
                    <label for="password">Enter Password to Confirm</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="save-btn" style="background-color: #dc3545; color: #fff; border: none;">Delete Account</button>
            </form>
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

    <script>
        function openNav() {
            let panel = document.getElementById("mySidepanel");
            panel.style.width = "35%"; // Initially only show category part
            document.body.classList.add("sidebar-open"); // Apply overlay effect

            // Hide all subcategories when opening the panel
            document.querySelectorAll(".subcategory-list").forEach(el => el.style.display = "none");
            document.querySelector(".subcategories").style.display = "none"; // Hide right column initially
        }

        function closeNav() {
            let panel = document.getElementById("mySidepanel");
            panel.style.width = "0";
            document.body.classList.remove("sidebar-open"); // Remove overlay effect
        }

        function openSearch() {
            let panel = document.getElementById("searchPanel");
            panel.classList.add("partial-open");
            document.body.classList.add("search-open");
            document.body.classList.add("no-scroll");
            document.getElementById("searchInput").focus();
        }

        function closeSearch() {
            let panel = document.getElementById("searchPanel");
            panel.classList.remove("partial-open", "open");
            document.body.classList.remove("search-open");
            document.body.classList.remove("no-scroll");
            document.getElementById("searchInput").value = "";
            document.getElementById("searchResults").innerHTML = "";
            document.getElementById("searchCollectionList").innerHTML = "";
            document.getElementById("suggestions").classList.remove("show");
        }

        // Ensure clicking 'Collection' opens the side panel with only categories
        document.querySelector("a[href='#']").addEventListener("click", function(event) {
            event.preventDefault();
            openNav();
        });

        // Expand panel when hovering over a category
        document.querySelectorAll(".category-item").forEach(category => {
            category.addEventListener("mouseenter", function () {
                let panel = document.getElementById("mySidepanel");
                panel.style.width = "800px"; // Expand to show subcategories

                document.querySelector(".subcategories").style.display = "block"; // Show right column

                // Hide all subcategory lists
                document.querySelectorAll(".subcategory-list").forEach(el => el.style.display = "none");

                // Show the targeted subcategory list
                const targetId = this.dataset.target;
                const targetEl = document.getElementById(targetId);
                if (targetEl) {
                    targetEl.style.display = "block";
                }
            });
        });

        document.addEventListener("DOMContentLoaded", function() {
            if (window.location.hash === "#product-section") {
                document.getElementById("product-section").scrollIntoView({ behavior: "smooth" });
            }

            const searchInput = document.getElementById("searchInput");
            const searchIcon = document.querySelector(".search-icon");
            const suggestionsContainer = document.getElementById("suggestions");
            const searchResults = document.getElementById("searchResults");
            const searchCollectionList = document.getElementById("searchCollectionList");
            const searchPanel = document.getElementById("searchPanel");

            // Ensure clicking 'Search' opens the search panel
            document.querySelector("a[onclick='openSearch()']").addEventListener("click", function(event) {
                event.preventDefault();
                openSearch();
            });

            searchInput.addEventListener("input", function() {
                const query = this.value.trim();
                console.log("Input event with query:", query);
                if (query.length > 0) {
                    fetchSuggestions(query);
                    fetchSearchResults(query);
                    suggestionsContainer.classList.add("show");
                    searchPanel.classList.remove("partial-open");
                    searchPanel.classList.add("open");
                } else {
                    suggestionsContainer.classList.remove("show");
                    searchResults.innerHTML = "";
                    searchCollectionList.innerHTML = "";
                    searchPanel.classList.remove("open");
                    searchPanel.classList.add("partial-open");
                }
            });

            searchInput.addEventListener("keydown", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault();
                    const query = this.value.trim();
                    console.log("Enter pressed with query:", query);
                    if (query.length > 0) {
                        fetchSearchResults(query);
                        suggestionsContainer.classList.remove("show");
                        searchPanel.classList.remove("partial-open");
                        searchPanel.classList.add("open");
                    }
                }
            });

            searchIcon.addEventListener("click", function() {
                const query = searchInput.value.trim();
                console.log("Search icon clicked with query:", query);
                if (query.length > 0) {
                    fetchSearchResults(query);
                    suggestionsContainer.classList.remove("show");
                    searchPanel.classList.remove("partial-open");
                    searchPanel.classList.add("open");
                }
            });

            suggestionsContainer.addEventListener("click", function(e) {
                if (e.target.classList.contains("suggestion-item")) {
                    searchInput.value = e.target.textContent;
                    suggestionsContainer.classList.remove("show");
                    fetchSearchResults(searchInput.value);
                    searchPanel.classList.remove("partial-open");
                    searchPanel.classList.add("open");
                }
            });

            document.addEventListener("click", function(e) {
                if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target) && !searchIcon.contains(e.target)) {
                    suggestionsContainer.classList.remove("show");
                }
            });

            function animateResults() {
                const cards = searchResults.querySelectorAll(".product-card-search");
                const observer = new IntersectionObserver((entries, observer) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            const card = entry.target;
                            const index = [...cards].indexOf(card);
                            card.style.animation = `fadeInUp 0.6s ease forwards`;
                            card.style.animationDelay = `${index * 0.15}s`;
                            observer.unobserve(card);
                        }
                    });
                }, { threshold: 0.2 });
                cards.forEach(card => observer.observe(card));
            }

            function fetchSuggestions(query) {
                fetch(`search.php?suggest=${encodeURIComponent(query)}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        suggestionsContainer.innerHTML = "";
                        data.forEach(keyword => {
                            const div = document.createElement("div");
                            div.className = "suggestion-item";
                            div.textContent = keyword;
                            suggestionsContainer.appendChild(div);
                        });
                        if (data.length > 0) {
                            suggestionsContainer.classList.add("show");
                        } else {
                            suggestionsContainer.classList.remove("show");
                        }
                    })
                    .catch(error => console.error('Error fetching suggestions:', error));
            }

            function fetchSearchResults(query) {
                console.log("Fetching search results for query:", query);
                fetch(`search.php?q=${encodeURIComponent(query)}&ajax=1`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}, URL: search.php?q=${encodeURIComponent(query)}&ajax=1`);
                        }
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
                            searchResults.innerHTML = data.html;
                            animateResults();
                        } else {
                            searchResults.innerHTML = "<p class='no-results'>No results found for \"" + query + "\".</p>";
                        }
                        // Render collections as plain text
                        searchCollectionList.innerHTML = "";
                        if (data.categories && data.categories.length > 0) {
                            console.log("Rendering collections:", data.categories); // Debug log
                            data.categories.forEach(category => {
                                const li = document.createElement("li");
                                const span = document.createElement("span");
                                span.textContent = category;
                                li.appendChild(span);
                                searchCollectionList.appendChild(li);
                            });
                        } else {
                            searchCollectionList.innerHTML = "";
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching search results:', error.message);
                        searchResults.innerHTML = "<p class='no-results'>Error loading results: " + error.message + ". Please try again.</p>";
                        searchCollectionList.innerHTML = "<li>Error loading collections</li>";
                    });
            }

            const moreBtn = document.getElementById("moreBtn");
            const productSection = document.getElementById("filter-section");
            const cards = document.querySelectorAll(".product-card-sub");

            // Scroll to product section on "More →" click
            moreBtn.addEventListener("click", function (e) {
                e.preventDefault();
                productSection.scrollIntoView({ behavior: "smooth" });
            });

            // Animate cards on first time entering viewport
            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const card = entry.target;
                        const index = [...cards].indexOf(card);
                        card.style.animation = `fadeInUp 0.6s ease forwards`;
                        card.style.animationDelay = `${index * 0.15}s`;
                        observer.unobserve(card); // Stop observing after animation plays once
                    }
                });
            }, {
                threshold: 0.2
            });

            cards.forEach(card => observer.observe(card));
        });

        // Add click handler for menu items
        document.addEventListener('DOMContentLoaded', function() {
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    if (e.target.closest('a') || e.target.classList.contains('arrow')) return;

                    const href = this.getAttribute('data-href');
                    if (href) {
                        console.log('Redirecting to:', href);
                        window.location.href = href;
                    }
                });
            });

            window.onclick = function(event) {
                if (!event.target.closest('.side-panel-profile') && !event.target.closest('.nav-right a[onclick="openProfilePanel()"]')) {
                    closeProfilePanel();
                }
            };
        });

        // Profile Panel Functions
        function openProfilePanel() {
            document.getElementById("profilePanel").classList.add("expanded");
        }

        function closeProfilePanel() {
            document.getElementById("profilePanel").classList.remove("expanded");
        }

        window.onclick = function(event) {
            if (!event.target.closest('.side-panel-profile') && !event.target.closest('.nav-right a[onclick="openProfilePanel()"]')) {
                closeProfilePanel();
            }
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