<?php
require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Create refunds table
    $sql = "CREATE TABLE IF NOT EXISTS refunds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        bank_name VARCHAR(100) NOT NULL,
        account_number VARCHAR(20) NOT NULL,
        account_name VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id),
        INDEX(status),
        INDEX(order_id)
    )";
    
    $pdo->exec($sql);
    
    echo "Refunds table created successfully!\n";
    
} catch (PDOException $e) {
    error_log("Error creating refunds table: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}