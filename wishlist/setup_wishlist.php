<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Create wishlist table directly with explicit database name
    $sql = "CREATE TABLE IF NOT EXISTS scentsation_db.wishlist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        product_id INT NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES scentsation_db.products(id),
        UNIQUE KEY unique_wishlist_item (user_id, product_id)
    )";
    
    $result = $pdo->exec($sql);
    echo "Wishlist table created successfully\n";
    
} catch (PDOException $e) {
    die("Error creating wishlist table: " . $e->getMessage() . "\n");
}