<?php
require 'vendor/autoload.php';
include 'db_config.php';

try {
    // Fetch all products from your database
    $stmt = $pdo->prepare("SELECT prod_Id, prod_Name, prod_Desc, subcat_Name FROM products JOIN subcategories ON products.subcat_Id = subcategories.subcat_Id");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Clear existing embeddings
    $pdo->exec("DELETE FROM product_embeddings");

    $insertStmt = $pdo->prepare("INSERT INTO product_embeddings (product_id, embedding) VALUES (:id, :embedding)");

    foreach ($products as $product) {
        $textToEmbed = "Product Name: " . $product['prod_Name'] . ". Category: " . $product['subcat_Name'] . ". Description: " . $product['prod_Desc'];

        // Use curl to call the Ollama embeddings endpoint
        $ch = curl_init("http://localhost:11434/api/embeddings");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'nomic-embed-text',
                'prompt' => $textToEmbed,
            ]),
        ]);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("Curl error: " . curl_error($ch));
        }
        curl_close($ch);
        $response = json_decode($response, true);

        if (!isset($response['embedding'])) {
            throw new Exception("Failed to generate embedding for product {$product['prod_Id']}: " . json_encode($response));
        }

        $embedding = json_encode($response['embedding']);

        // Save the embedding to the new table
        $insertStmt->execute([
            ":id" => $product['prod_Id'],
            ":embedding" => $embedding
        ]);

        echo "Generated embedding for product " . $product['prod_Id'] . "\n";
    }

    echo "Embedding generation complete.\n";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>