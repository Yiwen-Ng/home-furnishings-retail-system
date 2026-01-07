<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session if needed
session_start();

include 'db_config.php';

if (!$pdo) {
    die("Database connection failed!");
}

// Pagination Setup
$limit = 9;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subcategory Page</title>

    <link rel="stylesheet" href="subcategory.css">
    <link rel="stylesheet" href="search.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="chat.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        .sidepanel {
            width: 0;
            position: fixed;
            z-index: 100;
            height: 100vh;
            top: 0;
            left: 0;
            background-color: #f7f7f7;
            overflow: hidden;
            transition: transform 0.3s ease, font-size 0.3s ease;
            padding-top: 100px;
            display: flex;
            align-items: flex-start;
        }

        .sidepanel.expanded {
            width: 600px; /* Expands properly */
            max-width: 600px;
        }

        .categories {
            width: 45%; /* Left column */
        }

        .subcategories {
            width: 60%; /* Right column */
            height: 100vh;
            background-color:rgb(228, 228, 228);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .sidepanel a {
            padding: 20px 32px;
            text-decoration: none;
            font-size: 22px;
            font-weight: 300;
            color: #555;
            display: block;
            transition: 0.3s;
            line-height: 3;
            margin-left: 20px;
        }

        .sidepanel a:hover {
            font-size: 24px;
            font-weight: 700;
            transform: translateX(10px); /* Move to the right */
        }

        .sidepanel .closebtn {
            position: absolute;
            top: 25px;
            right: 25px;
            font-size: 36px;
        }

        .sidepanel .closebtn:hover {
            font-size: 40px;
            font-weight: 700;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: -50px;
            margin-bottom: 100px;
        }

        .pagination a, .pagination span {
            padding: 12px 15px;
            margin: 0 5px;
            font-size: 16px;
            text-decoration: none;
            color: #555;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .pagination a:hover {
            background-color: #f0f0f0;
        }

        .pagination-active {
            font-weight: bold;
            background-color: #555;
            color: white;
            border: 1px solid #333;
        }
    </style>
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
        <!-- <p>No user session found.</p> -->
    <?php endif; ?>

    <?php
    if (isset($_GET['subcategories'])) {
        $subcategories = $_GET['subcategories'];

        // Fetch subcategory details
        $stmt = $pdo->prepare("SELECT * FROM subcategories WHERE subcat_Name = ?");
        $stmt->execute([$subcategories]);
        $subcategoryData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($subcategoryData) {
            echo "<div class='subcategory-container'>";

            // Left Column
            echo "<div class='subcategory-left'>";
            echo "  <div class='subcategory-text-wrapper'>";
            echo "    <h3 class='subcategory-name'>" . htmlspecialchars($subcategoryData['subcat_Name']) . "</h3>";
            echo "    <p class='subcategory-desc'>" . htmlspecialchars($subcategoryData['subcat_Desc']) . "</p>";
            echo "    <a href='#filter-section' class='scroll-more-btn' id='moreBtn'>More →</a>";
            echo "  </div>";
            echo "</div>";

            // Right Column
            echo "<div class='subcategory-right'>";
            echo "  <div class='image-top'>";
            echo "    <div class='image-top-left'>";
            echo "      <div class='top-left-empty'></div>";
            echo "      <div class='top-left-image'>";
            
            if (!empty($subcategoryData['subcat_Img3'])) {
                echo "<img src='" . htmlspecialchars($subcategoryData['subcat_Img3']) . "' alt='Image 3'>";
            }
            echo "      </div>";
            echo "    </div>";
            echo "    <div class='image-top-right'>";
            
            if (!empty($subcategoryData['subcat_Img2'])) {
                echo "<img src='" . htmlspecialchars($subcategoryData['subcat_Img2']) . "' alt='Image 2'>";
            }
            echo "    </div>";
            echo "  </div>";
            echo "  <div class='image-bottom'>";
            
            if (!empty($subcategoryData['subcat_Img'])) {
                echo "<img src='" . htmlspecialchars($subcategoryData['subcat_Img']) . "' alt='Image 1'>";
            }
            echo "  </div>";
            echo "</div>"; // End Right

            echo "</div>"; // End Container
    ?>

    <!-- Filter & Sort Bar -->
    <div class="filter-sort-container" id="filter-section">
        <div class="filter-by">
            <div class="filter-by-inline">
                <span class="filter-label">Filter By</span>
                <?php
                // Ensure these variables exist with default values
                $currentSubcategory = isset($_GET['subcategories']) ? $_GET['subcategories'] : '';
                $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
                $currentFilter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
                ?>
                <a href="?subcategories=<?= urlencode($currentSubcategory) ?>&filter=all&sort=<?= $currentSort ?>" 
                class="filter-option <?= ($currentFilter == 'all') ? 'active' : '' ?>">All</a>
                <a href="?subcategories=<?= urlencode($currentSubcategory) ?>&filter=featured&sort=<?= $currentSort ?>" 
                class="filter-option <?= ($currentFilter == 'featured') ? 'active' : '' ?>">Featured Item</a>
                <a href="?subcategories=<?= urlencode($currentSubcategory) ?>&filter=sale&sort=<?= $currentSort ?>" 
                class="filter-option <?= ($currentFilter == 'sale') ? 'active' : '' ?>">On Sale</a>
            </div>
        </div>

        <div class="sort-by">
            <label for="sort">Sort By</label>
            <select id="sort" name="sort" onchange="applySort()">
                <option value="name_asc" <?= ($currentSort == 'name_asc') ? 'selected' : '' ?>>Name A - Z</option>
                <option value="name_desc" <?= ($currentSort == 'name_desc') ? 'selected' : '' ?>>Name Z - A</option>
                <option value="price_low" <?= ($currentSort == 'price_low') ? 'selected' : '' ?>>Price Low to High</option>
                <option value="price_high" <?= ($currentSort == 'price_high') ? 'selected' : '' ?>>Price High to Low</option>
            </select>
        </div>
    </div>

    <?php
            // Determine sort order
            $sortOption = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
            $sortSql = '';
            
            switch ($sortOption) {
                case 'name_asc':
                    $sortSql = 'ORDER BY prod_Name ASC';
                    break;
                case 'name_desc':
                    $sortSql = 'ORDER BY prod_Name DESC';
                    break;
                case 'price_low':
                    $sortSql = 'ORDER BY prod_Price ASC';
                    break;
                case 'price_high':
                    $sortSql = 'ORDER BY prod_Price DESC';
                    break;
                default:
                    $sortSql = 'ORDER BY prod_Name ASC';
            }

            // Determine filter
            $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
            $filterSql = '';

            switch ($filter) {
                case 'featured':
                    $filterSql = 'AND is_featured = 1';
                    break;
                case 'sale':
                    $filterSql = 'AND is_on_sale = 1';
                    break;
                default:
                    $filterSql = '';
            }

            // Get total number of products with filter
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE subcat_Id = ? $filterSql");
            $countStmt->execute([$subcategoryData['subcat_Id']]);
            $totalProducts = $countStmt->fetchColumn();
            $totalPages = ceil($totalProducts / $limit);

            // Fetch paginated products with sorting and filtering
            $stmt = $pdo->prepare("SELECT * FROM products WHERE subcat_Id = ? $filterSql $sortSql LIMIT ? OFFSET ?");
            $stmt->bindParam(1, $subcategoryData['subcat_Id'], PDO::PARAM_INT);
            $stmt->bindParam(2, $limit, PDO::PARAM_INT);
            $stmt->bindParam(3, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($products) {
                echo "<div id='product-section' class='products-container'>";
                foreach ($products as $product) {
                    $product_url = "product.php?product_id=" . htmlspecialchars($product['prod_Id']);
                    echo "<div class='product-card-sub'>";
                    echo "<a href='" . $product_url . "' class='product-link'>";
                    
                    echo "<div class='product-img-container'>";
                    // Add featured/sale badges if applicable
                    if ($product['is_featured']) {
                        echo "<span class='product-badge featured'>Featured</span>";
                    }
                    if ($product['is_on_sale']) {
                        echo "<span class='product-badge sale'>Sale</span>";
                    }
                    echo "<img src='" . htmlspecialchars($product['prod_Image']) . "' alt='" . htmlspecialchars($product['prod_Name']) . "' class='product-img'>";
                    echo "</div>";
                    
                    echo "<div class='product-info'>";
                    echo "<p class='product-name'>" . htmlspecialchars($product['prod_Name']) . "</p>";
                    
                    // Show sale price if on sale
                    if ($product['is_on_sale'] && $product['sale_price'] > 0) {
                        echo "<p class='product-price'>";
                        echo "<span class='original-price'>RM " . htmlspecialchars($product['prod_Price']) . "</span> ";
                        echo "<span class='sale-price'>RM " . htmlspecialchars($product['sale_price']) . "</span>";
                        echo "</p>";
                    } else {
                        echo "<p class='product-price'>RM " . htmlspecialchars($product['prod_Price']) . "</p>";
                    }
                    
                    echo "</div>";
                    echo "</a>";
                    echo "</div>";
                }
                echo "</div>";

                // Pagination
                if ($totalPages > 1) {
                    echo "<div class='pagination'>";
                    
                    // Previous button
                    if ($page > 1) {
                        echo "<a href='?subcategories=" . urlencode($subcategories) . "&filter=$filter&sort=$sortOption&page=" . ($page - 1) . "#filter-section' class='pagination-prev'>&lt;</a>";
                    }
                
                    // Page numbers
                    for ($i = 1; $i <= $totalPages; $i++) {
                        if ($i == $page) {
                            echo "<span class='pagination-active'>$i</span>";
                        } else {
                            echo "<a href='?subcategories=" . urlencode($subcategories) . "&filter=$filter&sort=$sortOption&page=$i#filter-section' class='pagination-link'>$i</a>";
                        }
                    }
                
                    // Next button
                    if ($page < $totalPages) {
                        echo "<a href='?subcategories=" . urlencode($subcategories) . "&filter=$filter&sort=$sortOption&page=" . ($page + 1) . "#filter-section' class='pagination-next'>&gt;</a>";
                    }
                    
                    echo "</div>";
                }              
            } else {
                echo "<p class='no-products'>No products found in this subcategory.</p>";
            }
        } else {
            echo "<p class='no-subcategory'>Subcategory not found.</p>";
        }
    }
    ?>

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

        function applySort() {
            const sortValue = document.getElementById('sort').value;
            const urlParams = new URLSearchParams(window.location.search);
            const subcategories = urlParams.get('subcategories') || '';
            const filter = urlParams.get('filter') || 'all';
            
            // Build new URL with all parameters
            let newUrl = `subcategory.php?subcategories=${encodeURIComponent(subcategories)}&filter=${filter}&sort=${sortValue}&page=1#filter-section`;
            
            window.location.href = newUrl;
        }

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