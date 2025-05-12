CREATE TABLE IF NOT EXISTS customer_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    state VARCHAR(100),
    total_orders INT DEFAULT 0,
    total_spent DECIMAL(10,2) DEFAULT 0.00,
    last_order_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer (customer_id)
);