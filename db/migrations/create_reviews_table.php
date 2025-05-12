<?php
require_once __DIR__ . '/../../config/db_connect.php';

try {
    $conn = getConnection();
    
    // Create reviews table with proper foreign key constraints
    $sql = "
    CREATE TABLE IF NOT EXISTS reviews (
        review_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL DEFAULT 'anonymous',
        product_id INT NULL,
        rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
        review_text TEXT NOT NULL,
        date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_reviews (user_id),
        INDEX idx_product_reviews (product_id),
        INDEX idx_review_date (date),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->exec($sql);
    
    // Create review_responses table for admin responses
    $sql_responses = "
    CREATE TABLE IF NOT EXISTS review_responses (
        response_id INT AUTO_INCREMENT PRIMARY KEY,
        review_id INT NOT NULL,
        admin_id VARCHAR(255) NOT NULL,
        response_text TEXT NOT NULL,
        date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (review_id) REFERENCES reviews(review_id) ON DELETE CASCADE,
        FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE,
        INDEX idx_review_responses (review_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->exec($sql_responses);

    echo "Reviews tables created successfully\n";
    
} catch(PDOException $e) {
    error_log("Error creating reviews tables: " . $e->getMessage());
    die("Error creating reviews tables: " . $e->getMessage() . "\n");
}