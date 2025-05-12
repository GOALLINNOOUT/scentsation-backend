<?php
require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Create order_items table if it doesn't exist
    $create_items = "CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT,
        product_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id)
    )";
    
    $pdo->exec($create_items);
    echo "Order items table created successfully!\n";
    
} catch (PDOException $e) {
    error_log("Error creating order items table: " . $e->getMessage());
    die("Error creating order items table: " . $e->getMessage() . "\n");
}
?>