<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

include 'db_config.php';

// Ensure no output before JSON
ob_start();

// Check database connection
if (!$pdo) {
    error_log("Database connection failed in search.php");
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['html' => "<p class='no-results'>Database connection error. Please try again later.</p>", 'categories' => []]);
        ob_end_flush();
        exit;
    } else {
        die("Database connection failed!");
    }
}

// Handle AJAX search request
if (isset($_GET['q']) && isset($_GET['ajax'])) {
    $searchQuery = trim($_GET['q']);
    $limit = 9;

    error_log("AJAX Search query: " . $searchQuery);

    try {
        // Fetch products (search only prod_Name, case-insensitive)
        $stmt = $pdo->prepare("
            SELECT p.* 
            FROM products p 
            WHERE LOWER(p.prod_Name) LIKE LOWER(?) 
            LIMIT ?
        ");
        $stmt->bindParam(1, $likeQuery, PDO::PARAM_STR);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $likeQuery = '%' . $searchQuery . '%';
        $stmt->execute();

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Log products for debugging
        error_log("Products found: " . json_encode(array_column($products, 'prod_Name')));

        // Fetch distinct categories based on subcat_Id of matching products
        $catStmt = $pdo->prepare("
            SELECT DISTINCT c.cat_Name 
            FROM products p 
            JOIN subcategories s ON p.subcat_Id = s.subcat_Id 
            JOIN categories c ON s.cat_Id = c.cat_Id 
            WHERE LOWER(p.prod_Name) LIKE LOWER(?)
        ");
        $catStmt->execute(['%' . $searchQuery . '%']);
        $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

        // Log categories for debugging
        error_log("Categories found: " . json_encode($categories));

        $html = '';
        if ($products) {
            foreach ($products as $product) {
                $product_url = "product.php?product_id=" . htmlspecialchars($product['prod_Id']);
                $html .= "<div class='product-card-search'>";
                $html .= "<a href='" . $product_url . "' class='product-link'>";
                $html .= "<div class='product-box-search'>";
                $html .= "<div class='product-img-container-search'>";
                $html .= "<img src='" . htmlspecialchars($product['prod_Image']) . "' alt='" . htmlspecialchars($product['prod_Name']) . "' class='product-img-search'>";
                $html .= "</div>";
                $html .= "<div class='product-info-search'>";
                $html .= "<p class='product-name-search'>" . htmlspecialchars($product['prod_Name']) . "</p>";
                if ($product['is_on_sale'] && $product['sale_price'] > 0) {
                    $html .= "<p class='product-price-search'>";
                    $html .= "<span class='original-price-search'>RM " . htmlspecialchars($product['prod_Price']) . "</span> ";
                    $html .= "<span class='sale-price-search'>RM " . htmlspecialchars($product['sale_price']) . "</span>";
                    $html .= "</p>";
                } else {
                    $html .= "<p class='product-price-search'>RM " . htmlspecialchars($product['prod_Price']) . "</p>";
                }
                $html .= "</div>";
                $html .= "</div>";
                $html .= "</a>";
                $html .= "</div>";
            }
        } else {
            $html = "<p class='no-results'>No products found for \"" . htmlspecialchars($searchQuery) . "\".</p>";
        }
        header('Content-Type: application/json');
        echo json_encode(['html' => $html, 'categories' => $categories]);
    } catch (Exception $e) {
        error_log("Error executing AJAX query: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['html' => "<p class='no-results'>Error loading results: " . htmlspecialchars($e->getMessage()) . "</p>", 'categories' => []]);
    }
    ob_end_flush();
    exit;
}

// Handle AJAX suggestion request
if (isset($_GET['suggest'])) {
    $searchQuery = trim($_GET['suggest']);
    $suggestions = [];

    error_log("Suggestion query: " . $searchQuery);

    try {
        // Fetch suggestions (search only prod_Name, case-insensitive)
        $stmt = $pdo->prepare("
            SELECT DISTINCT prod_Name 
            FROM products 
            WHERE LOWER(prod_Name) LIKE LOWER(?) 
            LIMIT ?
        ");
        $stmt->bindParam(1, $likeQuery, PDO::PARAM_STR);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $likeQuery = '%' . $searchQuery . '%';
        $limit = 5;
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Log suggestions for debugging
        error_log("Suggestions found: " . json_encode(array_column($results, 'prod_Name')));

        foreach ($results as $result) {
            $suggestions[] = $result['prod_Name'];
        }
        header('Content-Type: application/json');
        echo json_encode($suggestions);
    } catch (Exception $e) {
        error_log("Error executing suggestion query: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([]);
    }
    ob_end_flush();
    exit;
}

// Handle standalone search page
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 9;
$offset = ($page - 1) * $limit;

$products = [];
$totalResults = 0;

if ($searchQuery) {
    error_log("Standalone Search query: " . $searchQuery);

    try {
        // Count total results (search only prod_Name)
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM products 
            WHERE LOWER(prod_Name) LIKE LOWER(?)
        ");
        $countStmt->execute(['%' . $searchQuery . '%']);
        $totalResults = $countStmt->fetchColumn();

        error_log("Found " . $totalResults . " products for standalone query: " . $searchQuery);

        // Fetch products (search only prod_Name)
        $stmt = $pdo->prepare("
            SELECT * 
            FROM products 
            WHERE LOWER(prod_Name) LIKE LOWER(?) 
            LIMIT ? OFFSET ?
        ");
        $stmt->bindParam(1, $likeQuery, PDO::PARAM_STR);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $likeQuery = '%' . $searchQuery . '%';
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error executing standalone query: " . $e->getMessage());
        $products = [];
        $totalResults = 0;
    }
}

$totalPages = ceil($totalResults / $limit);
ob_end_clean();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results</title>
    <link rel="stylesheet" href="search.css">
    <link rel="stylesheet" href="homepage.css">
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
            transition: width 0.3s ease;
            padding-top: 100px;
            display: flex;
            align-items: flex-start;
        }

        .sidepanel.expanded {
            width: 600px;
            max-width: 600px;
        }

        .categories {
            width: 45%;
        }

        .subcategories {
            width: 60%;
            height: 100vh;
            background-color: rgb(228, 228, 228);
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
            transform: translateX(10px);
        }

        .sidepanel .closebtn {
            position: absolute;
            top: 25px;
            right: 25px;
            font-size: 36px;
            cursor: pointer;
        }

        .sidepanel .closebtn:hover {
            font-size: 40px;
            font-weight: 700;
        }

        .search-results-page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            text-align: center;
        }

        .search-results-page h2 {
            font-size: 2rem;
            margin-bottom: 20px;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .pagination a {
            padding: 10px 15px;
            text-decoration: none;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .pagination a:hover {
            background-color: #f0f0f0;
        }

        .pagination a.active {
            background-color: #333;
            color: #fff;
            border-color: #333;
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

    <!-- Search Results -->
    <div class="search-results-page">
        <h2>Search Results for "<?php echo htmlspecialchars($searchQuery); ?>"</h2>
        <?php if ($products): ?>
            <div class="results-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card-search">
                        <a href="product.php?product_id=<?php echo htmlspecialchars($product['prod_Id']); ?>" class="product-link">
                            <div class="product-box-search">
                                <div class="product-img-container-search">
                                    <?php if ($product['is_featured']): ?>
                                        <span class="product-badge featured">Featured</span>
                                    <?php endif; ?>
                                    <?php if ($product['is_on_sale']): ?>
                                        <span class="product-badge sale">Sale</span>
                                    <?php endif; ?>
                                    <img src="<?php echo htmlspecialchars($product['prod_Image']); ?>" alt="<?php echo htmlspecialchars($product['prod_Name']); ?>" class="product-img">
                                </div>
                                <div class="product-info-search">
                                    <p class="product-name-search"><?php echo htmlspecialchars($product['prod_Name']); ?></p>
                                    <?php if ($product['is_on_sale'] && $product['sale_price'] > 0): ?>
                                        <p class="product-price-search">
                                            <span class="original-price-search">RM <?php echo htmlspecialchars($product['prod_Price']); ?></span>
                                            <span class="sale-price-search">RM <?php echo htmlspecialchars($product['sale_price']); ?></span>
                                        </p>
                                    <?php else: ?>
                                        <p class="product-price-search">RM <?php echo htmlspecialchars($product['prod_Price']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="no-results">No products found for "<?php echo htmlspecialchars($searchQuery); ?>".</p>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?q=<?php echo urlencode($searchQuery); ?>&page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

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
            panel.style.width = "35%";
            document.body.classList.add("sidebar-open");
            document.querySelectorAll(".subcategory-list").forEach(el => el.style.display = "none");
            document.querySelector(".subcategories").style.display = "none";
        }

        function closeNav() {
            let panel = document.getElementById("mySidepanel");
            panel.style.width = "0";
            document.body.classList.remove("sidebar-open");
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

        document.addEventListener("DOMContentLoaded", function() {
            const searchInput = document.getElementById("searchInput");
            const searchIcon = document.querySelector(".search-icon");
            const suggestionsContainer = document.getElementById("suggestions");
            const searchResults = document.getElementById("searchResults");
            const searchCollectionList = document.getElementById("searchCollectionList");
            const searchPanel = document.getElementById("searchPanel");

            document.querySelector("a[onclick='openNav()']").addEventListener("click", function(event) {
                event.preventDefault();
                openNav();
            });

            document.querySelectorAll(".category-item").forEach(category => {
                category.addEventListener("mouseenter", function() {
                    let panel = document.getElementById("mySidepanel");
                    panel.style.width = "800px";
                    document.querySelector(".subcategories").style.display = "block";
                    document.querySelectorAll(".subcategory-list").forEach(el => el.style.display = "none");
                    const targetId = this.dataset.target;
                    const targetEl = document.getElementById(targetId);
                    if (targetEl) {
                        targetEl.style.display = "block";
                    }
                });
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
                        } else {;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching search results:', error.message);
                        searchResults.innerHTML = "<p class='no-results'>Error loading results: " + error.message + ". Please try again.</p>";
                        searchCollectionList.innerHTML = "<li>Error loading collections</li>";
                    }
                );
            }
        });
    </script>
</body>
</html>