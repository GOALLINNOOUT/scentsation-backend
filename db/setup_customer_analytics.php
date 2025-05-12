<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Create customer_analytics table
    $sql = "CREATE TABLE IF NOT EXISTS customer_analytics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        state VARCHAR(100),
        total_orders INT DEFAULT 0,
        total_spent DECIMAL(10,2) DEFAULT 0.00,
        last_order_date DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_customer (customer_id)
    )";
    
    $pdo->exec($sql);
    echo "Customer analytics table created successfully\n";

} catch (Exception $e) {
    error_log("Error creating customer analytics table: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo "Error: " . $e->getMessage() . "\n";
}