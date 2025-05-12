<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Create order_analytics table
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_analytics (
        date DATE PRIMARY KEY,
        order_count INT NOT NULL DEFAULT 0,
        total_sales DECIMAL(10,2) NOT NULL DEFAULT 0,
        items_sold INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
} catch (PDOException $e) {
    error_log("Error creating analytics tables: " . $e->getMessage());
    throw new Exception("Error creating analytics tables: " . $e->getMessage());
}
?>