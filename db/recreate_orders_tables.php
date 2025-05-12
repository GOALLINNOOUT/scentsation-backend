<?php
require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Drop existing tables
    $pdo->exec("DROP TABLE IF EXISTS order_items");
    $pdo->exec("DROP TABLE IF EXISTS coupon_usage");
    $pdo->exec("DROP TABLE IF EXISTS orders");
    
    // Create orders table with new schema
    $create_orders = "CREATE TABLE orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        phoneNumber VARCHAR(20),
        street_address VARCHAR(255),
        apartment VARCHAR(100),
        city VARCHAR(100),
        state VARCHAR(100),
        zip_code VARCHAR(20),
        country VARCHAR(100) DEFAULT 'Nigeria',
        location TEXT,
        alternative_contact_name VARCHAR(255),
        alternative_contact_phone VARCHAR(20),
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        deliveryFee DECIMAL(10,2),
        discount DECIMAL(10,2) DEFAULT 0.00,
        coupon_code VARCHAR(50),
        transactionReference VARCHAR(255),
        status_id INT NOT NULL,
        user_id VARCHAR(255),
        INDEX idx_email (email),
        INDEX idx_phone (phoneNumber),
        INDEX idx_status_id (status_id),
        INDEX idx_user_id (user_id),
        FOREIGN KEY (status_id) REFERENCES order_status(id) ON UPDATE CASCADE,
        FOREIGN KEY (coupon_code) REFERENCES coupons(code) ON DELETE SET NULL ON UPDATE CASCADE
    )";
    
    $create_items = "CREATE TABLE order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT,
        product_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id)
    )";
    
    $pdo->exec($create_orders);
    $pdo->exec($create_items);
    
    echo "Tables recreated successfully!";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>