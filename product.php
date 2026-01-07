<?php
session_start();
include 'db_config.php';

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Validate product ID
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($product_id <= 0) {
    echo "<p>Invalid product ID.</p>";
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, s.subcat_Name, s.subcat_Model
        FROM products p
        JOIN subcategories s ON s.subcat_Id = p.subcat_Id
        WHERE p.prod_Id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo "<p>Product not found.</p>";
        exit;
    }

    // Decode JSON data with proper error handling
    $colors_decoded = json_decode($product['colors'] ?? '[]', true);
    $product['colors'] = (json_last_error() === JSON_ERROR_NONE && is_array($colors_decoded)) ? $colors_decoded : [];

    $color_images_decoded = json_decode($product['color_images'] ?? '{}', true);
    $product['color_images'] = (json_last_error() === JSON_ERROR_NONE && is_array($color_images_decoded)) ? $color_images_decoded : [];

    $detail_images_decoded = json_decode($product['prod_detail_images'] ?? '{}', true);
    $product['prod_detail_images'] = (json_last_error() === JSON_ERROR_NONE && is_array($detail_images_decoded)) ? $detail_images_decoded : [];

    // Fallback to prod_Image if no color images
    if (empty($product['color_images']) && !empty($product['prod_Image'])) {
        $product['color_images']['Default'] = $product['prod_Image'];
        $product['colors'] = ['Default'];
    }

    $default_color = !empty($product['colors']) ? $product['colors'][0] : 'Default';
    $default_main_image = $product['color_images'][$default_color] ?? $product['prod_Image'] ?? 'assets/default.jpg';
    $default_detail_images = $product['prod_detail_images'][$default_color] ?? [];

    // Parse prod_Dimension
    $dimensions = ['height' => 45, 'seat_width' => 26, 'width' => 39]; // Default values
    if (!empty($product['prod_Dimension'])) {
        preg_match_all('/(\w+[\w\s]*):\s*(\d+)\s*cm/', $product['prod_Dimension'], $matches);
        $keys = array_map('strtolower', $matches[1]);
        $values = array_map('intval', $matches[2]);
        $parsed = array_combine($keys, $values);
        $dimensions = [
            'height'     => $parsed['height'] ?? 45,
            'seat_width' => $parsed['seat width'] ?? 26,
            'width'      => $parsed['width'] ?? 39
        ];
    }

    // Fetch customization options
    $stmt = $pdo->prepare("SELECT category, option_type, option_values FROM product_customizations WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $customization_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $customization_data = [];
    foreach ($customization_options as $option) {
        $values = json_decode($option['option_values'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($values)) {
            $customization_data[$option['option_type']] = $values;
        }
    }

} catch (PDOException $e) {
    error_log("Error fetching product: " . $e->getMessage());
    echo "<p>Error fetching product. Please try again later.</p>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Page</title>
    
    <link rel="stylesheet" href="product.css"><script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
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
            margin: 20px 0 100px;
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
            <!-- <p>No user session found. <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Please login</a>.</p> -->
        </div>
    <?php endif; ?>

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

    <!-- Enquiry Side Panel -->
    <div id="enquiryPanel" class="side-panel-enquiry">
        <span class="closebtn-enquiry" onclick="closeEnquiryPanel()">×</span>
        <h2>Product Enquiry</h2>
        <form id='enquiry-form'>
            <input type='hidden' name='product_id' value='<?php echo $product_id; ?>'>
            <input type='hidden' name='csrf_token' value='<?php echo $_SESSION['csrf_token']; ?>'>
            <p>Provide your preferences below and we’ll get back to you with a quote or further details.</p>
            
            <div class='form-group'>
                <label for='enquiry-full-name'>Full Name</label>
                <input type='text' id='enquiry-full-name' name='full_name' value='<?php echo isset($_SESSION['user']['full_name']) ? htmlspecialchars($_SESSION['user']['full_name']) : ''; ?>' required>
            </div>

            <div class='form-group'>
                <label for='enquiry-email'>Email</label>
                <input type='email' id='enquiry-email' name='email' value='<?php echo isset($_SESSION['user']['email']) ? htmlspecialchars($_SESSION['user']['email']) : ''; ?>' required>
            </div>

            <?php foreach ($customization_data as $option_type => $option): ?>
                    <div class='form-group'>
                        <label for='custom-<?php echo htmlspecialchars($option_type); ?>'><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $option_type))); ?>:</label>
                        <select id='custom-<?php echo htmlspecialchars($option_type); ?>' name='custom_<?php echo htmlspecialchars($option_type); ?>'>
                            <option value='' disabled selected>Select an option</option>
                            <?php foreach ($option['option_values'] as $value): ?>
                                <option value='<?php echo htmlspecialchars($value); ?>'><?php echo htmlspecialchars($value); ?></option>
                            <?php endforeach; ?>
                         </select>
                    </div>
            <?php endforeach; ?>

            <div class="form-group dimension-group">
                <label>Custom Dimensions (cm)</label>
                <div class="dimension-inputs">
                    <div class="dimension-field">
                        <input type="number" name="custom_width" id="custom_width" min="10" max="300" value="50">
                        <span>Width</span>
                    </div>
                    <div class="dimension-field">
                        <input type="number" name="custom_height" id="custom_height" min="10" max="300" value="50">
                        <span>Height</span>
                    </div>
                    <div class="dimension-field">
                        <input type="number" name="custom_depth" id="custom_depth" min="10" max="300" value="30">
                        <span>Depth</span>
                    </div>
                </div>
            </div>

            <div class='form-group'>
                <label for='custom-color'>Custom Color</label>
                <input type='color' id='custom-color' name='custom_color' value='#000000'>
            </div>
                        
            <div class='form-group'>
                <label for='engraving-image'>Image for Engraving / Printing (Optional, max 5MB)</label>
                <input type='file' id='engraving-image' name='engraving_image' accept='image/*'>
            </div>

            <div class='form-group'>
                <label for='custom-material'>Preferred Material (E.g., Oak, Leather)</label>
                <input type='text' id='custom-material' name='custom_material' placeholder='Enter preferred material'>
            </div>

            <!-- Preview Container -->
            <div class='preview-container'>
                <h4>Live Preview</h4>
                <div id="preview-canvas"></div>
            </div>

            <!-- Hidden inputs to pass to JS -->
            <input type="hidden" id="product-subcategory" value="<?= htmlspecialchars($product['subcat_Name'] ?? '') ?>">
            <input type="hidden" id="product-model-url" value="<?= htmlspecialchars($product['subcat_Model'] ?? '') ?>">

            <!-- Three.js using .glb -->
            <script type="module">
                import * as THREE from "https://esm.sh/three@0.150.1";
                import { GLTFLoader } from "https://esm.sh/three@0.150.1/examples/jsm/loaders/GLTFLoader.js";
                import { DRACOLoader } from "https://esm.sh/three@0.150.1/examples/jsm/loaders/DRACOLoader.js";
                import { OrbitControls } from "https://esm.sh/three@0.150.1/examples/jsm/controls/OrbitControls.js";

                const previewContainer = document.getElementById("preview-canvas");
                const modelUrl = document.getElementById("product-model-url").value;

                // Scene
                const scene = new THREE.Scene();
                scene.background = new THREE.Color(0xf9f9f9);

                // Camera
                const camera = new THREE.PerspectiveCamera(
                    45,
                    previewContainer.clientWidth / previewContainer.clientHeight,
                    0.1,
                    1000
                );
                camera.position.set(0, 1.2, 3);

                // Renderer (Retina-safe)
                const renderer = new THREE.WebGLRenderer({ antialias: true });
                renderer.setPixelRatio(window.devicePixelRatio);
                renderer.setSize(previewContainer.clientWidth, previewContainer.clientHeight);
                previewContainer.appendChild(renderer.domElement);

                // Lights
                const dirLight = new THREE.DirectionalLight(0xffffff, 1);
                dirLight.position.set(5, 5, 5);
                scene.add(dirLight);
                scene.add(new THREE.AmbientLight(0x888888));

                // Controls
                const controls = new OrbitControls(camera, renderer.domElement);
                controls.enableDamping = true;
                controls.dampingFactor = 0.05;
                controls.enableZoom = true;
                controls.autoRotate = false;
                controls.target.set(0, 0.5, 0);

                let currentGroup = null;
                let originalSize = new THREE.Vector3(); // to track base model size

                // Track chosen color
                const colorPicker = document.getElementById("custom-color");
                colorPicker.addEventListener("input", (e) => {
                    if (currentGroup) {
                        currentGroup.traverse((child) => {
                            if (child.isMesh && child.material) {
                                child.material = child.material.clone();
                                child.material.color.set(e.target.value);
                            }
                        });
                    }
                });

                // Dimension inputs
                const inputWidth = document.getElementById("custom_width");
                const inputHeight = document.getElementById("custom_height");
                const inputDepth = document.getElementById("custom_depth");

                function applyDimensions() {
                    if (!currentGroup) return;

                    const targetWidth = parseFloat(inputWidth.value) || originalSize.x;
                    const targetHeight = parseFloat(inputHeight.value) || originalSize.y;
                    const targetDepth = parseFloat(inputDepth.value) || originalSize.z;

                    // Scale ratio per axis
                    const scaleX = targetWidth / originalSize.x;
                    const scaleY = targetHeight / originalSize.y;
                    const scaleZ = targetDepth / originalSize.z;

                    currentGroup.scale.set(scaleX, scaleY, scaleZ);

                    // Recenter after scaling
                    const newBox = new THREE.Box3().setFromObject(currentGroup);
                    const center = new THREE.Vector3();
                    newBox.getCenter(center);

                    currentGroup.position.x -= center.x;
                    currentGroup.position.y -= center.y;
                    currentGroup.position.z -= center.z;
                    currentGroup.position.y -= newBox.min.y; // keep base at y=0
                }

                inputWidth.addEventListener("input", applyDimensions);
                inputHeight.addEventListener("input", applyDimensions);
                inputDepth.addEventListener("input", applyDimensions);

                // GLTF Loader
                const loader = new GLTFLoader();
                const dracoLoader = new DRACOLoader();
                dracoLoader.setDecoderPath("https://cdn.jsdelivr.net/npm/three@0.150.1/examples/jsm/libs/draco/");
                loader.setDRACOLoader(dracoLoader);

                function loadModel(url) {
                    loader.load(url, (gltf) => {
                        if (currentGroup) scene.remove(currentGroup);
                        currentGroup = gltf.scene;

                        // Get natural size
                        const box = new THREE.Box3().setFromObject(currentGroup);
                        box.getSize(originalSize); 
                        const center = new THREE.Vector3();
                        box.getCenter(center);

                        // Center model
                        currentGroup.position.x -= center.x;
                        currentGroup.position.y -= center.y;
                        currentGroup.position.z -= center.z;

                        // Put base on ground
                        const newBox = new THREE.Box3().setFromObject(currentGroup);
                        currentGroup.position.y -= newBox.min.y;

                        // Normalize scale
                        const maxDim = Math.max(originalSize.x, originalSize.y, originalSize.z);
                        const desiredSize = 2; // target scene units
                        const baseScale = desiredSize / maxDim;
                        currentGroup.scale.set(baseScale, baseScale, baseScale);

                        scene.add(currentGroup);

                        // 🔥 Get bounding box again AFTER scaling
                        const scaledBox = new THREE.Box3().setFromObject(currentGroup);
                        const scaledSize = new THREE.Vector3();
                        scaledBox.getSize(scaledSize);

                        // Compute best camera distance
                        const fov = camera.fov * (Math.PI / 180);
                        const maxDimScaled = Math.max(scaledSize.x, scaledSize.y, scaledSize.z);
                        let cameraZ = (maxDimScaled) / (2 * Math.tan(fov / 2));

                        cameraZ *= 50; // add generous padding so model is never too close
                        camera.position.set(0, scaledSize.y * 0.6, cameraZ);

                        // Focus camera at center
                        controls.target.set(0, scaledSize.y * 0.5, 0);
                        controls.update();

                        // Apply initial dimensions
                        applyDimensions();
                    }, undefined, (err) => {
                        console.error("Model failed to load:", err);
                    });
                }

                if (modelUrl) {
                    loadModel(modelUrl);
                }

                // Animate loop
                function animate() {
                    requestAnimationFrame(animate);
                    controls.update();
                    renderer.render(scene, camera);
                }
                animate();

                // Resize handling
                function resizeRenderer() {
                    const width = previewContainer.clientWidth;
                    const height = previewContainer.clientHeight;
                    renderer.setPixelRatio(window.devicePixelRatio);
                    renderer.setSize(width, height, false);
                    camera.aspect = width / height;
                    camera.updateProjectionMatrix();
                }

                resizeRenderer();
                window.addEventListener("resize", resizeRenderer);
            </script>

            <div class='form-group'>
                <label for='additional-notes'>Additional Notes</label>
                <textarea id='additional-notes' name='additional_notes' placeholder='Any other customization requests or comments'></textarea>
            </div>

            <div class='form-group'>
                <label>Contact Preference</label>
                <div class='enquiry-buttons'>
                    <button type='button' class='enquiry-btn contact-btn' data-contact='call'>Call Me</button>
                    <button type='button' class='enquiry-btn contact-btn' data-contact='email'>Email Me</button>
                </div>
                <input type='hidden' id='contact-preference' name='contact_preference' required>
            </div>

            <button type='submit' class='enquiry-btn submit-btn'>Submit Enquiry</button>
        </form>
    </div>

    <!-- Product Info Section -->
    <div class='product-container'>
        <div class='product-info'>
            <h1><?php echo htmlspecialchars($product['prod_Name']); ?></h1>
            <p class='price'>
                <?php
                if ($product['is_on_sale'] && $product['sale_price'] > 0) {
                    echo "<span class='original-price'>RM " . htmlspecialchars($product['prod_Price']) . "</span> ";
                    echo "<span class='sale-price'>RM " . htmlspecialchars($product['sale_price']) . "</span>";
                } else {
                    echo "RM " . htmlspecialchars($product['prod_Price']);
                }
                ?>
            </p>
            <p class='designer'>Made in Malaysia<br>Designed by Local Designer</p>

            <div class='tab-buttons'>
                <button class='tab-btn active' data-tab='shop-now'>Shop Now</button>
                <button class='tab-btn' data-tab='enquiry'>Enquiry & All Options</button>
            </div>

            <div class='tab-content'>
                <div id='shop-now' class='tab-pane active'>
                    <div class='option-box'>
                        <select id='colorSelect' name='color'>
                            <?php if (is_array($product['colors']) && !empty($product['colors'])): ?>
                                <?php foreach ($product['colors'] as $color): ?>
                                    <option 
                                        value="<?php echo htmlspecialchars($color); ?>"
                                        data-color-image="<?php echo htmlspecialchars($product['color_images'][$color] ?? $product['prod_Image']); ?>"
                                    >
                                        <?php echo htmlspecialchars(ucfirst($color)); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="Default">Default</option>
                            <?php endif; ?>
                        </select>
                        <p class='stock'><span class='dot'></span> <?php echo htmlspecialchars($product['prod_Stock']); ?> In Stock - Delivery 5 to 10 Days</p>
                        <div class='cart-controls'>
                            <button class='add-to-bag' data-prod-id='<?php echo htmlspecialchars($product['prod_Id']); ?>'>Add to cart</button>
                            <button class='quantity-btn minus' data-prod-id='<?php echo htmlspecialchars($product['prod_Id']); ?>'>−</button>
                            <span class='quantity'>1</span>
                            <button class='quantity-btn plus' data-prod-id='<?php echo htmlspecialchars($product['prod_Id']); ?>'>+</button>
                        </div>
                        <button class='wishlist' data-prod-id='<?php echo htmlspecialchars($product['prod_Id']); ?>'>♡ Save to Wishlist</button>
                        <input type='hidden' id='csrf_token' value='<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>'>
                    </div>
                </div>
                <div id='enquiry' class='tab-pane'>
                    <h3>Our design consultants are ready to help.</h3>
                    <p>This is just a small edit of our full collection, for more options and information on sizes, fabrics and finishes, please visit us in-store or talk to a team member.</p>
                    <div class='enquiry-buttons'>
                        <button class='enquiry-btn' onclick="openEnquiryPanel()">Send Enquiry</button>
                        <button class='enquiry-btn'>Visit Showroom</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Image Section -->
        <div class="image-section">
            <div id="image-container" class="image-container">
                <img id="product-image" src="<?php echo htmlspecialchars($product['prod_Image']); ?>" alt="<?php echo htmlspecialchars($product['prod_Name']); ?>" class="product-image">
                <div id="magnifier-lens" class="magnifier-lens"></div>
            </div>
            <div class="product-detail-images" id="detail-images-container">
                <?php foreach ($default_detail_images as $detail_image): ?>
                    <img src="<?php echo htmlspecialchars($detail_image); ?>" alt="Product Detail" class="detail-thumbnail">
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Product Details Section -->
    <div class='product-details'>
        <!-- Description Dropdown -->
        <div class='dropdown'>
            <h2>
                Description
                <button class='dropdown-btn'>
                    <img src='https://www.iconpacks.net/icons/2/free-arrow-down-icon-3101-thumb.png' class='arrow-icon' alt='arrow'>
                </button>
            </h2>
            <div class='dropdown-content'>
                <p class='prod-desc'><?php echo htmlspecialchars($product['prod_Desc']); ?></p>
            </div>
        </div>

        <!-- Material Dropdown -->
        <div class='dropdown'>
            <h2>
                Material
                <button class='dropdown-btn'>
                    <img src='https://www.iconpacks.net/icons/2/free-arrow-down-icon-3101-thumb.png' class='arrow-icon' alt='arrow'>
                </button>
            </h2>
            <div class='dropdown-content'>
                <p class='prod-material'><?php echo nl2br(htmlspecialchars($product['prod_Material'])); ?></p>
            </div>
        </div>

        <!-- Dimension Dropdown -->
        <div class='dropdown'>
            <h2>
                Dimensions
                <button class='dropdown-btn'>
                    <img src='https://www.iconpacks.net/icons/2/free-arrow-down-icon-3101-thumb.png' class='arrow-icon' alt='arrow'>
                </button>
            </h2>
            <div class='dropdown-content'>
                <p class='prod-dimension'><?php echo nl2br(htmlspecialchars($product['prod_Dimension'])); ?></p>
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

    <!-- Hidden CSRF Token -->
    <input type='hidden' id='csrf_token' value='<?php echo $_SESSION['csrf_token']; ?>'>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        document.querySelector("a[onclick='openNav()']").addEventListener("click", function(event) {
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

        $(document).ready(function() {
            $(".tab-btn").click(function() {
                // Remove 'active' class from all tab buttons and tab panes
                $(".tab-btn").removeClass("active");
                $(".tab-pane").removeClass("active");

                // Add 'active' class to the clicked tab button
                $(this).addClass("active");

                // Show the corresponding tab pane
                var tabId = $(this).attr("data-tab");
                $("#" + tabId).addClass("active");
            });

            // Toggle the visibility of dropdown content when the button is clicked
            $(".dropdown-btn").click(function() {
                // Find the closest parent dropdown and toggle the 'active' class
                $(this).closest('.dropdown').toggleClass("active");
            });

            // Search panel functionality
            const searchInput = document.getElementById("searchInput");
            const searchIcon = document.querySelector(".search-icon");
            const suggestionsContainer = document.getElementById("suggestions");
            const searchResults = document.getElementById("searchResults");
            const searchCollectionList = document.getElementById("searchCollectionList");
            const searchPanel = document.getElementById("searchPanel");

            searchInput.addEventListener("input", function() {
                const query = this.value.trim();
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
                            data.categories.forEach(category => {
                                const li = document.createElement("li");
                                const span = document.createElement("span");
                                span.textContent = category;
                                li.appendChild(span);
                                searchCollectionList.appendChild(li);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching search results:', error.message);
                        searchResults.innerHTML = "<p class='no-results'>Error loading results: " + error.message + ". Please try again.</p>";
                        searchCollectionList.innerHTML = "<li>Error loading collections</li>";
                    });
            }
        });

        // Add to Wishlist
        document.querySelector('.wishlist').addEventListener('click', function() {
            const prodId = this.getAttribute('data-prod-id');
            const csrfToken = document.getElementById('csrf_token').value;
            <?php if (!isset($_SESSION['user'])): ?>
                window.location.href = 'login.php?redirect=product.php?product_id=' + prodId;
            <?php else: ?>
                fetch('wishlist_api.php?action=add', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `prod_id=${prodId}&csrf_token=${csrfToken}`
                })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                    })
                    .catch(error => {
                        console.error('Error adding to wishlist:', error);
                        alert('Error adding to wishlist.');
                    });
            <?php endif; ?>
        });

        // Add to Cart with Color
        document.querySelector('.add-to-bag').addEventListener('click', function() {
            const prodId = this.getAttribute('data-prod-id');
            const quantity = parseInt(document.querySelector('.quantity').textContent);
            const selectedColor = document.getElementById('colorSelect').value || 'Default';
            const csrfToken = document.getElementById('csrf_token').value;
            
            <?php if (!isset($_SESSION['user'])): ?>
                window.location.href = 'login.php?redirect=product.php?product_id=' + prodId;
            <?php else: ?>
                fetch('cart_api.php?action=add', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `prod_id=${prodId}&quantity=${quantity}&color=${encodeURIComponent(selectedColor)}&csrf_token=${encodeURIComponent(csrfToken)}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Product added to cart successfully!');
                        } else {
                            alert('Error: ' + data.message);
                            if (data.message.includes('CSRF') || data.message.includes('Session')) {
                                window.location.reload();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error adding to cart:', error);
                        alert('Error adding to cart. Please try again.');
                    });
            <?php endif; ?>
        });

        // Enquiry Form Submission
        document.getElementById('enquiry-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            // Validate required fields
            const fullName = $('#enquiry-full-name').val().trim();
            const email = $('#enquiry-email').val().trim();
            const contactPreference = $('#contact-preference').val();
                
            if (!fullName || !email || !contactPreference) {
                alert('Please fill in all required fields (Full Name, Email, Contact Preference).');
                return;
            }

            // Validate file size (max 5MB)
            const fileInput = $('#engraving-image')[0];
            if (fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size / 1024 / 1024; // Size in MB
                if (fileSize > 5) {
                    alert('File size exceeds 5MB limit.');
                    return;
                }
            }

            fetch('enquiry_api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    console.log('Enquiry Response Status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.text().then(text => {
                        console.log('Raw Enquiry Response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error(`Invalid JSON from enquiry_api.php: ${text}`);
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed Enquiry Response:', data);
                    if (data.success) {
                        alert(data.message);
                        $('#enquiry-form')[0].reset();
                        $('#contact-preference').val('');
                        $('.contact-btn').removeClass('active');
                        closeEnquiryPanel();
                    } else {
                        throw new Error(data.message || 'Enquiry submission failed');
                    }
                })
                .catch(error => {
                    console.error('Enquiry submission error:', error);
                    alert('Error submitting enquiry: ' + error.message);
                });
        });

        // Contact Preference Buttons
        $('.contact-btn').on('click', function() {
            $('.contact-btn').removeClass('active');
            $(this).addClass('active');
            $('#contact-preference').val($(this).data('contact'));
        });

        // Quantity Controls
        document.querySelectorAll('.quantity-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const quantitySpan = document.querySelector('.quantity');
                let quantity = parseInt(quantitySpan.textContent);
                if (btn.classList.contains('plus')) {
                    quantity++;
                } else if (quantity > 1) {
                    quantity--;
                }
                quantitySpan.textContent = quantity;
            });
        });

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

        // Color Selection and Image Update
        document.getElementById('colorSelect')?.addEventListener('change', function() {
            const selectedColor = this.value;
            const colorImages = <?php echo json_encode($product['color_images']); ?>;
            const allDetailImages = <?php echo json_encode($product['prod_detail_images']); ?>;
            const productImage = document.getElementById('product-image');
            const detailImagesContainer = document.getElementById('detail-images-container');
            const container = document.getElementById('image-container');
            const defaultImage = '<?php echo htmlspecialchars($default_main_image); ?>';

            if (!productImage || !detailImagesContainer) {
                console.error('Image elements not found');
                return;
            }

            // Update main image
            const newImage = colorImages[selectedColor] || defaultImage;
            productImage.src = newImage;

            // Update detail thumbnails
            detailImagesContainer.innerHTML = '';
            const images = allDetailImages[selectedColor] || [];
            images.forEach(imageUrl => {
                const thumbnail = document.createElement('img');
                thumbnail.src = imageUrl;
                thumbnail.alt = "Product detail image";
                thumbnail.className = "detail-thumbnail";
                thumbnail.addEventListener('click', function() {
                    productImage.src = this.src;
                    isZoomed = false;
                    container.style.backgroundImage = 'none';
                    productImage.style.opacity = '1';
                    container.style.cursor = 'zoom-in';
                });
                detailImagesContainer.appendChild(thumbnail);
            });

            // Reset zoom
            isZoomed = false;
            container.style.backgroundImage = 'none';
            productImage.style.opacity = '1';
            container.style.cursor = 'zoom-in';
        });

        // Image zoom handling
        let isZoomed = false;
        const zoomFactor = 2;
        const container = document.getElementById('image-container');
        const image = document.getElementById('product-image');

        if (container && image) {
            container.addEventListener('click', function() {
                isZoomed = !isZoomed;
                if (isZoomed) {
                    container.style.backgroundImage = `url(${image.src})`;
                    container.style.backgroundSize = `${image.naturalWidth * zoomFactor}px ${image.naturalHeight * zoomFactor}px`;
                    image.style.opacity = '0';
                    container.style.cursor = 'zoom-out';
                } else {
                    container.style.backgroundImage = 'none';
                    image.style.opacity = '1';
                    container.style.cursor = 'zoom-in';
                }
            });

            container.addEventListener('mousemove', function(e) {
                if (!isZoomed) return;
                const rect = container.getBoundingClientRect();
                let x = ((e.clientX - rect.left) / rect.width) * 100;
                let y = ((e.clientY - rect.top) / rect.height) * 100;
                container.style.backgroundPosition = `${x}% ${y}%`;
            });

            container.addEventListener('mouseleave', function() {
                if (isZoomed) {
                    isZoomed = false;
                    container.style.backgroundImage = 'none';
                    image.style.opacity = '1';
                    container.style.cursor = 'zoom-in';
                }
            });

            // Add click listeners to initial thumbnails
            document.querySelectorAll('.detail-thumbnail').forEach(thumbnail => {
                thumbnail.addEventListener('click', function() {
                    image.src = this.src;
                    isZoomed = false;
                    container.style.backgroundImage = 'none';
                    image.style.opacity = '1';
                    container.style.cursor = 'zoom-in';
                });
            });
        }

        // Trigger initial image for default color
        document.getElementById('colorSelect')?.dispatchEvent(new Event('change'));

        // Image zoom and thumbnail handling
        document.addEventListener("DOMContentLoaded", function() {
            const container = document.getElementById('image-container');
            const image = document.getElementById('product-image');
            const detailImagesContainer = document.getElementById('detail-images-container');
            const colorSelect = document.getElementById('colorSelect');

            let isZoomed = false;
            const zoomFactor = 2;

            // PHP data is passed directly to a JavaScript variable for dynamic updates
            const allDetailImages = <?php echo json_encode($product['prod_detail_images']); ?>;
            
            console.log("All Detail Images Data:", allDetailImages);

            // This function dynamically updates the detail thumbnails
            function updateDetailThumbnails(color) {
                // Clear existing thumbnails
                detailImagesContainer.innerHTML = '';
                
                // Get the list of images for the selected color
                const images = allDetailImages[color] || [];
                
                // If there are images, create and append new thumbnails
                if (images.length > 0) {
                    images.forEach(imageUrl => {
                        const thumbnail = document.createElement('img');
                        thumbnail.src = imageUrl;
                        thumbnail.alt = "Product detail image";
                        thumbnail.className = "detail-thumbnail";
                        thumbnail.addEventListener('click', function() {
                            image.src = this.src;
                            isZoomed = false;
                            container.style.backgroundImage = 'none';
                            image.style.opacity = '1';
                            container.style.cursor = 'zoom-in';
                        });
                        detailImagesContainer.appendChild(thumbnail);
                    });
                }
            }

            // Attach a click event listener to toggle the zoom
            container.addEventListener('click', function() {
                isZoomed = !isZoomed;
                if (isZoomed) {
                    container.style.backgroundImage = `url(${image.src})`;
                    container.style.backgroundSize = `${image.width * zoomFactor}px ${image.height * zoomFactor}px`;
                    image.style.opacity = '0';
                    container.style.cursor = 'zoom-out';
                } else {
                    container.style.backgroundImage = 'none';
                    image.style.opacity = '1';
                    container.style.cursor = 'zoom-in';
                }
            });

            // Attach a mousemove event listener to the container for panning
            container.addEventListener('mousemove', function(e) {
                if (!isZoomed) return;

                const rect = container.getBoundingClientRect();
                let x = e.clientX - rect.left;
                let y = e.clientY - rect.top;

                x = Math.min(Math.max(x, 0), container.offsetWidth);
                y = Math.min(Math.max(y, 0), container.offsetHeight);
                
                const backgroundX = (x / container.offsetWidth) * 100;
                const backgroundY = (y / container.offsetHeight) * 100;
                container.style.backgroundPosition = `${backgroundX}% ${backgroundY}%`;
            });
            
            // Attach a mouseleave event to reset the image state
            container.addEventListener('mouseleave', function() {
                if (isZoomed) {
                    isZoomed = false;
                    container.style.backgroundImage = 'none';
                    image.style.opacity = '1';
                    container.style.cursor = 'zoom-in';
                }
            });

            // Event listener for the color dropdown
            if (colorSelect) {
                colorSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const newImageSrc = selectedOption.getAttribute('data-color-image');
                    const selectedColor = selectedOption.value;
                    
                    // Update the main product image
                    image.src = newImageSrc;
                    
                    // Update the detail thumbnails based on the new color
                    updateDetailThumbnails(selectedColor);
                    
                    // Reset zoom state
                    isZoomed = false;
                    container.style.backgroundImage = 'none';
                    image.style.opacity = '1';
                    container.style.cursor = 'zoom-in';
                });
            }
            
            // Initial setup for thumbnails on page load
            if (colorSelect) {
                updateDetailThumbnails(colorSelect.value);
            }
        });

        // Enquiry panel
        function openEnquiryPanel() {
            const enquiryPanel = document.getElementById('enquiryPanel');
            if (enquiryPanel) {
                enquiryPanel.classList.add('expanded');
                document.body.classList.add('enquiry-panel-open');
            }
        }

        function closeEnquiryPanel() {
            const enquiryPanel = document.getElementById('enquiryPanel');
            if (enquiryPanel) {
                enquiryPanel.classList.remove('expanded');
                document.body.classList.remove('enquiry-panel-open');
            }
        }

        // Close panel if clicking outside
        window.addEventListener('click', function(event) {
            const panel = document.getElementById("enquiryPanel");
            if (panel.classList.contains('expanded') && !panel.contains(event.target) && !event.target.closest('.enquiry-btn[onclick="openEnquiryPanel()"]')) {
                closeEnquiryPanel();
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