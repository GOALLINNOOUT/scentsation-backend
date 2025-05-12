<?php
require_once __DIR__ . '/../config/db_connect.php';

try {
    $conn = getConnection();
    
    // Drop existing reviews table to recreate with new schema
    $conn->exec("DROP TABLE IF EXISTS reviews");
    
    // Create reviews table with optional product_id
    $sql = "CREATE TABLE reviews (
        review_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        product_id INT NULL,
        rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
        review_text TEXT NOT NULL,
        date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id),
        INDEX(product_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->exec($sql);
    echo "Reviews table created successfully\n";
    
} catch(PDOException $e) {
    error_log("Error creating reviews table: " . $e->getMessage());
    die("Error creating reviews table: " . $e->getMessage() . "\n");
}
?>