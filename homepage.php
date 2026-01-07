<?php
// Start the session if needed
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Furnishings Retail System</title>
    
    <link rel="stylesheet" href="homepage.css">
    <link rel="stylesheet" href="search.css">
    <link rel="stylesheet" href="chat.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        /* Collection Side Panel Style */
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
        }

        .sidepanel .closebtn:hover {
            font-size: 40px;
            font-weight: 700;
        }

        /* Profile Side Panel Styles */
        .side-panel-profile {
            height: 100%;
            width: 0;
            position: fixed;
            top: 0;
            right: 0;
            background-color: #fff;
            overflow-x: hidden;
            transition: 0.5s;
            padding-top: 60px;
            box-shadow: -2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .side-panel-profile a {
            padding: 10px 20px;
            text-decoration: none;
            font-size: 20px; 
            font-weight: 500;
            color: #333; 
            display: block;
            transition: 0.3s;
            line-height: 1.5;
        }

        .side-panel-profile a:hover {
            background-color: #f9f9f9;
            color: #1e4b51; 
            font-weight: 500;
        }

        .side-panel-profile .closebtn-profile {
            position: absolute;
            top: 10px;
            right: 25px;
            font-size: 24px; 
            cursor: pointer;
            color: #555;
            padding: 5px;
        }

        .side-panel-profile .closebtn-profile:hover {
            color: #1e4b51;
            background-color: #f1f1f1; 
            border-radius: 50%;
        }

        /* Greeting Style (e.g., "Hey Ng!") */
        .side-panel-profile h2 {
            margin: 0 0 40px 20px;
            font-size: 24px;
            color: #333;
            font-weight: 600;
        }

        /* Ensure the panel expands to the full width shown in the image */
        .side-panel-profile.expanded {
            width: 500px; /* Adjusted width to match the image */
        }

        /* Styles for menu item */
        .menu-item {
            display: flex;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding: 20px 20px;
            margin-bottom: 10px;
        }

        .menu-item:last-child {
            border-bottom: none;
        }

        .menu-left {
            flex: 0 0 40px; /* Increased width to accommodate 50px icon */
            text-align: center;
            padding: 5px 0; /* Optional padding for better spacing */
        }

        .menu-left .icon img {
            width: 30px;
            height: 30px;
            object-fit: contain; /* Ensures the image maintains aspect ratio within 50px */
            vertical-align: middle; /* Aligns the image vertically with text if any */
        }

        .menu-center {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding-left: 20px;
        }

        .menu-title {
            text-decoration: none;
            color: #555;
            font-size: 18px;
            margin: 0;
            padding: 0;
        }

        .menu-title:hover {
            color: #1e4b51;
            font-weight: 500;
        }

        .profile-description {
            font-size: 16px;
            color: #777;
            margin-left: 20px;
            padding: 0;
            line-height: 1.4;
        }

        .menu-right {
            flex: 0 0 40px; /* Fixed width for arrow */
            text-align: center;
            margin-right: 10px;
        }

        .arrow {
            font-size: 24px;
            color: #555;
            transition: 0.3s;
        }

        .menu-item:hover .arrow {
            color: #1e4b51;
        }
    </style>
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

    <!-- Hero Section Section -->
    <div class="main-container">
        <!-- Two-column section -->
        <section class="two-column-section" id="heroSection">
            <div class="left-column">
                <div class="logo">
                    <div class="logo-line">
                        <span class="logo-letter grey">F</span>
                        <span class="logo-letter grey">U</span>
                        <span class="logo-letter grey">R</span>
                    </div>
                    <div class="logo-line">
                        <span class="logo-letter grey">N</span>
                        <span class="logo-letter grey">I</span>
                        <span class="logo-letter white">H</span>
                    </div>
                    <div class="logo-line">
                        <span class="logo-letter white">A</span>
                        <span class="logo-letter white">ü</span>
                        <span class="logo-letter white">S</span>
                    </div>
                </div>
            </div>
            
            <div class="right-column">
                <div class="right-content">
                    <h2>Modern Furniture for Inspired Living</h2>
                    <p>JELLYHOME brings you handcrafted furniture that combines timeless design with exceptional comfort. Each piece is designed to transform your space into a haven of style and relaxation.</p>
                    <button class="cta-button" id="collectionButton">View Collection</button>
                </div>
            </div>
        </section>
    </div>

    <!-- Category Collection section -->
    <section class="collection-section" id="collectionSection">
        <h2>Get to Know Our Collection</h2>
        <p>See all the collections we have to offer for you</p>

        <div class="collection-grid">
            <div class="collection-column collection-left">
                <div class="collection-box box-top" style="background-image: url('./assets/home/cat_sink.jpg');">
                    <a href="subcategory.php?subcategories=Kitchen Sink Cabinets">
                        <div class="box-content">
                            <h3>Sink Cabinet</h3>
                            <p>Function meets finesse</p>
                        </div>
                    </a>
                </div>
                
                <div class="collection-box box-bottom" style="background-image: url('./assets/home/cat_sofa.jpg');">
                    <a href="subcategory.php?subcategories=Sofas" class="box-link">
                        <div class="box-content">
                            <h3>Sofa</h3>
                            <p>Where comfort meets conversation</p>
                        </div>
                    </a>
                </div>
            </div>
            
            <div class="collection-column collection-right">
                <div class="collection-box box-top" style="background-image: url('./assets/home/cat_bed.jpg');">
                    <a href="subcategory.php?subcategories=Beds">
                        <div class="box-content">
                            <h3>Bed</h3>
                            <p>Dream in style, wake up refreshed</p>
                        </div>
                    </a>
                </div>
                
                <div class="collection-box box-bottom" style="background-image: url('./assets/home/cat_armchair.jpg');">
                    <a href="subcategory.php?subcategories=Armchairs">
                        <div class="box-content">
                            <h3>Armchair</h3>
                            <p>Your personal corner of calm</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="collection-section">
        <div class="collection-grid-2">
            <div class="collection-box-full" style="background-image: url('./assets/home/cat_dining.jpg');">
                <a href="subcategory.php?subcategories=Dining Tables">
                    <div class="box-content">
                        <h3>Dining Table</h3>
                        <p>Where meals turn into memories</p>
                    </div>
                </a>
            </div>

            <div class="collection-split">
                <div class="collection-box-half" style="background-image: url('./assets/home/cat_outdoor.jpg');">
                    <a href="subcategory.php?subcategories=Outdoor Benches">
                        <div class="box-content">
                            <h3>Outdoor Bench</h3>
                            <p>Sit back and soak in the moments</p>
                        </div>
                    </a>
                </div>
                
                <div class="collection-box-half" style="background-image: url('./assets/home/cat_pantry.jpg');">
                    <a href="subcategory.php?subcategories=Pantry Cupboards">
                        <div class="box-content">
                            <h3>Pantry Cupboard</h3>
                            <p>Tidy, Timeless & Totally essential</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php
    include 'db_config.php';
    $sql = "SELECT * FROM products WHERE is_featured = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <!-- Featured Items Section -->
    <section class="featured-section">
        <div class="featured-title">Featured Items</div>
        <div class="scroll-gallery" id="scrollGallery">
            <?php foreach ($featuredProducts as $product): ?>
                <div class="card-wrapper">
                    <?php
                    $product_url = "product.php?product_id=" . htmlspecialchars($product['prod_Id']);
                    ?>
                    <a href="<?php echo $product_url; ?>" class="card-link">
                        <div class="card">
                            <img src="<?php echo htmlspecialchars($product['prod_Image']); ?>" alt="<?php echo htmlspecialchars($product['prod_Name']); ?>" />
                            <h3><?php echo htmlspecialchars($product['prod_Name']); ?></h3>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

     <!-- Creative Section -->
    <section class="our-creative-section">
        <div class="creative-container">
            <div class="creative-text">
                <h2>Our Creative</h2>
                <p>
                    At Home Furnishings Retail System, we bring innovation and elegance to every piece we design. 
                    Our team is dedicated to crafting high-quality furniture that blends functionality with timeless aesthetics.
                    We believe that a well-designed home reflects personality, comfort, and a touch of inspiration.
                </p>
                <p>
                    From contemporary to classic designs, we focus on every detail to ensure our customers experience the best in home décor.
                    Join us in redefining modern living with our exclusive collections.
                </p>
            </div>
            <div class="creative-image">
                <img src="assets/home/aboutus.jpg" alt="Our Creative Image">
            </div>
        </div>
    </section>

     <!-- Art-Design Section -->
    <section class="art-design-section">
        <div class="art-design-container">
            <div class="art-design-image">
                <img src="assets/home/aboutus2.jpg" alt="Art Meets Design">
            </div>
            <div class="art-design-text">
                <h2>Where Art Meets Design</h2>
                <p>
                    Experience the perfect fusion of creativity and functionality with our elegantly crafted outdoor furniture. 
                    Designed for both beauty and durability, each piece transforms your space into a masterpiece, blending fine craftsmanship with premium materials. 
                    Elevate your outdoor living with furniture that's as stunning as it is practical.
                </p>
                <p>
                    Experience the fusion of art and design—where every detail matters.
                </p>
            </div>
        </div>
    </section>

    <!-- Promotion Banner Section -->
    <div class="promotion-banner">
        <div class="promotion-content">
            <h2>Exclusive Deals Just for You!</h2>
            <p>Get up to <strong>50% OFF</strong> on selected items. Limited time offer!</p>
            <a href="#" class="shop-now-btn">Shop Now</a>
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
        const button = document.getElementById('collectionButton');
        const heroSection = document.getElementById('heroSection');
        const collectionSection = document.getElementById('collectionSection');

        button.addEventListener('click', function(e) {
            e.preventDefault();
            collectionSection.scrollIntoView({ behavior: 'smooth' });
            const x = e.clientX - e.target.getBoundingClientRect().left;
            const y = e.clientY - e.target.getBoundingClientRect().top;
            const ripple = document.createElement('span');
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;
            ripple.classList.add('ripple');
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });

        window.addEventListener('scroll', function() {
            const scrollPosition = window.scrollY;
            const maxHeroTransform = 500;
            const heroProgress = Math.min(scrollPosition / maxHeroTransform, 1);
            heroSection.style.transform = `
                translateY(${10 * heroProgress}px) 
                scale(${1 - (0.015 * heroProgress)}) 
            `;
            heroSection.style.width = `${100 - (0.05 * 100 * heroProgress)}%`;
            heroSection.style.opacity = `${1 - (0.2 * heroProgress)}`;
            heroSection.style.borderRadius = `${30 * heroProgress}px`;
            heroSection.style.margin = `${15 * heroProgress}px auto`;
            heroSection.style.boxShadow = `
                0 ${10 * heroProgress}px 
                ${20 * heroProgress}px 
                rgba(0,0,0,${0.1 * heroProgress})
            `;
            heroSection.style.transition = 'all 0.3s ease-out';
        });

        const initObservers = () => {
            const sectionObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in-view');
                    }
                });
            }, { threshold: 0.1 });
            
            const boxObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('box-in-view');
                        boxObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -100px 0px' });

            const cardObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        entry.target.style.transitionDelay = `${index * 0.1}s`;
                        entry.target.classList.add('card-in-view');
                    }
                });
            }, { threshold: 0.1 });

            const creativeObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        if (entry.target.classList.contains('our-creative-section')) {
                            entry.target.classList.add('slide-in-left');
                        } else if (entry.target.classList.contains('art-design-section')) {
                            entry.target.classList.add('slide-in-right');
                        }
                    }
                });
            }, { threshold: 0.1 });

            const promotionObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('slide-up');
                    }
                });
            }, { threshold: 0.1 });

            const footerObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('footer-slide-up');
                        const columns = entry.target.querySelectorAll('.footer-column');
                        columns.forEach((column, index) => {
                            column.style.transitionDelay = `${index * 0.15}s`;
                        });
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.featured-title, .section-title').forEach(title => {
                sectionObserver.observe(title);
            });

            document.querySelectorAll('.collection-section').forEach(section => {
                sectionObserver.observe(section);
            });
            
            document.querySelectorAll(
                '.collection-left .box-top, ' +
                '.collection-left .box-bottom, ' +
                '.collection-right .box-top, ' +
                '.collection-right .box-bottom, ' +
                '.collection-box-full, ' +
                '.collection-box-half'
            ).forEach((box, index) => {
                box.style.transitionDelay = `${index * 0.2}s`;
                boxObserver.observe(box);
            });

            document.querySelectorAll('.card-wrapper').forEach((card, index) => {
                card.style.transitionDelay = `${index * 0.15}s`;
                cardObserver.observe(card);
            });

            document.querySelectorAll('.our-creative-section, .art-design-section').forEach(section => {
                creativeObserver.observe(section);
            });

            const promotionBanner = document.querySelector('.promotion-banner');
            if (promotionBanner) {
                promotionObserver.observe(promotionBanner);
            }

            const footer = document.querySelector('.footer');
            if (footer) {
                footerObserver.observe(footer);
            }
        };

        document.addEventListener('DOMContentLoaded', initObservers);

        const gallery = document.getElementById('scrollGallery');
        let scrollAmount = 1;

        function autoScroll() {
            if (gallery.scrollLeft + gallery.clientWidth >= gallery.scrollWidth) {
                gallery.scrollLeft = 0;
            } else {
                gallery.scrollLeft += scrollAmount;
            }
        }

        let scrollInterval = setInterval(autoScroll, 30);

        gallery.addEventListener('mouseenter', () => clearInterval(scrollInterval));
        gallery.addEventListener('mouseleave', () => {
            scrollInterval = setInterval(autoScroll, 30);
        });

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
                        } else {
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