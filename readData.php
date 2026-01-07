<?php
require 'db_config.php'; // Include database connection

try {
    // SQL query to fetch data from a table
    $sql = "SELECT * FROM subcategories"; 
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    // Fetch all results as an associative array
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Display data
    if ($data) {
        echo "<table border='1'>";
        echo "<tr>";
        foreach (array_keys($data[0]) as $column) {
            echo "<th>$column</th>";
        }
        echo "</tr>";

        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>$value</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No records found.";
    }
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>
