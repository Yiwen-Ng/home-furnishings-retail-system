<?php
session_start();
include 'db_config.php'; // Your database connection

header('Content-Type: application/json');

// --- CONFIGURATION ---
$apiKey = 'AIzaSyCCk5F5xSUtnINb_Jp5vXo_lfvz5rkSpt0'; // Paste your Gemini API Key here
$geminiApiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey; // FIX IS HERE

$data = json_decode(file_get_contents('php://input'), true);
$userMessage = $data['message'] ?? '';
$userId = $_SESSION['user']['user_id'] ?? null;

if (empty($userMessage)) {
    echo json_encode(['reply' => "I'm sorry, I didn't get that. Could you please repeat?"]);
    exit;
}

$contextData = '';
$productsForDisplay = []; // --- NEW --- Array to hold structured product data for the frontend
$promptInstruction = 'You are a friendly and helpful customer service chatbot for JellyHome, an e-commerce furniture store. Answer the user\'s question based ONLY on the context provided below. Be conversational and concise. If the information is not in the context, say "I\'m sorry, I don\'t have that information right now."';

$subcatNames = [];
$stmt = $pdo->query("SELECT subcat_Id, subcat_Name FROM subcategories");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $subcatNames[strtolower($row['subcat_Name'])] = $row['subcat_Id'];
}

// --- INTENT DETECTION & DATA FETCHING ---

// Intent for Product Recommendations
if (preg_match('/recommend|feature|suggest|popular products|show me|sofas|stools and benches/i', $userMessage)) {
    $promptInstruction .= ' When presenting products, list each product on a new line with a bullet point in the format: "* Name (RM Price) - Status".';
    
    // Check for specific category keywords in the user's message
    $categorySearch = '';
    if (preg_match('/\b(kitchen|dining|bedroom|living room)\b/i', $userMessage, $matches)) {
        $categorySearch = trim($matches[0]);
    }

    $sql = "SELECT p.prod_Id, p.prod_Name, p.prod_Price, p.prod_Stock, p.prod_Image, p.is_on_sale, p.sale_price
            FROM products p
            JOIN subcategories s ON p.subcat_Id = s.subcat_Id
            JOIN categories c ON s.cat_Id = c.cat_Id";
    
    $whereClause = " WHERE p.is_featured = 1"; // Default to featured products
    $params = [];

    // If a specific category is found, override the featured filter
    if ($categorySearch) {
        $whereClause = " WHERE c.cat_Name LIKE ? OR s.subcat_Name LIKE ? LIMIT 4";
        $params = ["%{$categorySearch}%", "%{$categorySearch}%"];
    } else {
        $whereClause .= " LIMIT 4";
    }

    $subcatId = null;
    $foundSubcat = null;

    // Check for specific subcategory keywords
    foreach ($subcatNames as $name => $id) {
        if (strpos(strtolower($userMessage), $name) !== false) {
            $subcatId = $id;
            $foundSubcat = $name;
            break;
        }
    }

    $sql = "SELECT p.prod_Id, p.prod_Name, p.prod_Price, p.prod_Stock, p.prod_Image, p.is_on_sale, p.sale_price
            FROM products p";
    
    $whereClause = " WHERE p.is_featured = 1"; // Default to featured products
    $params = [];

    if ($subcatId) {
        $whereClause = " WHERE p.subcat_Id = ? LIMIT 4";
        $params = [$subcatId];
        $contextData = "The user is asking for product recommendations for {$foundSubcat}. Here are some options:\n";
    } else {
        $whereClause .= " LIMIT 4";
        $contextData = "The user is asking for product recommendations. Here are some of our featured products:\n";
    }
    
    $stmt = $pdo->prepare($sql . $whereClause);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($products) {
        $contextData = "The user is asking for product recommendations. Here are some of our featured products:\n";
        foreach ($products as $product) {
            $stockStatus = ($product['prod_Stock'] > 0) ? "In Stock" : "Out of Stock";
            $price = ($product['is_on_sale'] && $product['sale_price'] > 0) ? $product['sale_price'] : $product['prod_Price'];
            
            // Data for the prompt with new line and bullet point
            $contextData .= "\n* {$product['prod_Name']} RM " . number_format((float)$price, 2) . " - {$stockStatus}\n";

            // Structured data for the frontend
            $productsForDisplay[] = [
                'id' => $product['prod_Id'],
                'name' => $product['prod_Name'],
                'price' => number_format((float)$price, 2),
                'stock' => $stockStatus,
                'image' => $product['prod_Image'],
                'url' => 'http://localhost/Home/product.php?product_id=' . $product['prod_Id']
            ];
        }
    } else {
        $contextData = "We currently have no products to recommend for that category.";
    }
}
// Intent for Order Status
elseif (preg_match('/order status|track my order|where is my order/i', $userMessage)) {
    if ($userId) {
        $stmt = $pdo->prepare("SELECT order_id, order_date, total_amount, order_status FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 5");
        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($orders) {
            $contextData = "The user has the following recent orders:\n";
            foreach ($orders as $order) {
                $contextData .= "\n- Order ID: {$order['order_id']}, Status: {$order['order_status']}, Date: {$order['order_date']}, Total: RM {$order['total_amount']}\n";
            }
        } else {
            $contextData = "The user has no orders.";
        }
    } else {
        echo json_encode(['reply' => "Please log in to check your order status."]);
        exit;
    }
}
// Intent for Payment Methods
elseif (preg_match('/payment|credit card|paypal|pay/i', $userMessage)) {
    $contextData = "We accept several payment methods: credit card (Visa, Mastercard), PayPal, direct bank transfer, and cash on delivery (for select areas).";
}
// Intent for Delivery Information
elseif (preg_match('/delivery|shipping|how long/i', $userMessage)) {
    $contextData = "Our standard delivery within Malaysia usually takes 5 to 10 working days, depending on your location and item availability.";
}
// Intent for Assembly Service
elseif (preg_match('/assembly|assemble|setup/i', $userMessage)) {
    $contextData = "Yes, we do! Our delivery team can provide assembly services for your furniture upon request. Please mention this when you place your order.";
}
// Intent for Warranty
elseif (preg_match('/warranty|guarantee/i', $userMessage)) {
    $contextData = "Most of our furniture comes with a standard 1-year warranty that covers manufacturing defects. This does not cover wear and tear.";
}
// Intent for Business Hours
elseif (preg_match('/hours|open|close/i', $userMessage)) {
    $contextData = "Our business hours are from 10:00 AM to 8:00 PM daily, including weekends.";
}
// Intent for Contact Information
elseif (preg_match('/contact|phone|email|whatsapp/i', $userMessage)) {
    $contextData = "You can contact our customer service team via WhatsApp at +6012-3456789 or by emailing us at support@jellyhome.com.";
}
// Fallback for general queries
else {
    $contextData = "The user is asking a general question. JellyHome is a home furnishing store based in Malaysia specializing in high-quality, locally designed furniture.";
}

// --- PREPARE & CALL GEMINI API ---
$fullPrompt = $promptInstruction . "\n\n---CONTEXT---\n" . $contextData . "\n\n---USER QUESTION---\n" . $userMessage;

$postData = [
    'contents' => [['parts' => [['text' => $fullPrompt]]]],
    'generationConfig' => ['temperature' => 0.5, 'topK' => 1, 'topP' => 1, 'maxOutputTokens' => 2048]
];

$ch = curl_init($geminiApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    error_log("cURL Error: " . $error);
    echo json_encode(['reply' => 'Sorry, I am having trouble connecting. Please try again later.']);
    exit;
}

$responseData = json_decode($response, true);

if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $reply = $responseData['candidates'][0]['content']['parts'][0]['text'];
    
    // Send back the text reply AND the structured product data if it exists
    $finalResponse = ['reply' => $reply];
    if (!empty($productsForDisplay)) {
        $finalResponse['products'] = $productsForDisplay;
    }
    echo json_encode($finalResponse);

} else {
    error_log("Gemini API Error: " . $response);
    echo json_encode(['reply' => 'I\'m sorry, I encountered an error. Please try asking in a different way.']);
}
?>