<?php
require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Create refund_details table
    $sql = "CREATE TABLE IF NOT EXISTS refund_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        bank_name VARCHAR(100) NOT NULL,
        account_number VARCHAR(20) NOT NULL,
        account_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed BOOLEAN DEFAULT FALSE,
        processed_at TIMESTAMP NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id),
        INDEX(order_id)
    )";
    
    $pdo->exec($sql);
    
    echo "Refund details table created successfully!\n";
    
} catch (PDOException $e) {
    error_log("Error creating refund_details table: " . $e->getMessage());
    die("Error creating refund_details table: " . $e->getMessage() . "\n");
}
?>