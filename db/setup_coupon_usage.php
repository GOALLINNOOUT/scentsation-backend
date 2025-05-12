<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Create coupon usage tracking table
    $sql = "CREATE TABLE IF NOT EXISTS coupon_usage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        coupon_code VARCHAR(50) NOT NULL,
        order_id INT,
        used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (coupon_code) REFERENCES coupons(code) ON DELETE CASCADE ON UPDATE CASCADE,
        UNIQUE KEY unique_user_coupon (user_id, coupon_code)
    )";
    
    $pdo->exec($sql);
    echo "Coupon usage table created successfully\n";
    
} catch (PDOException $e) {
    error_log("Error creating coupon usage table: " . $e->getMessage());
    die("Error creating coupon usage table: " . $e->getMessage() . "\n");
}
?>