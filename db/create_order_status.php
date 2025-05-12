<?php
require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Create order_status table
    $sql = "CREATE TABLE IF NOT EXISTS order_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        status_code VARCHAR(20) NOT NULL UNIQUE,
        status_name VARCHAR(50) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(status_code)
    )";
    
    $pdo->exec($sql);
    
    // Insert default status values
    $statuses = [
        ['paid', 'Order Placed', 'Payment received, order confirmed'],
        ['pending', 'Order Confirmed', 'Order is confirmed and awaiting processing'],
        ['processing', 'Processing', 'Order is being prepared for shipment'],
        ['shipped', 'Shipped', 'Order has been shipped and is in transit'],
        ['delivered', 'Delivered', 'Order has been delivered successfully'],
        ['cancelled', 'Cancelled', 'Order has been cancelled']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO order_status (status_code, status_name, description) VALUES (?, ?, ?)");
    
    foreach ($statuses as $status) {
        $stmt->execute($status);
    }
    
    // Add status_id column to orders table if it doesn't exist
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS status_id INT AFTER transactionReference");
    $pdo->exec("ALTER TABLE orders ADD CONSTRAINT fk_status FOREIGN KEY (status_id) REFERENCES order_status(id)");
    
    // Update existing orders to map their status to status_id
    $updateStmt = $pdo->prepare("
        UPDATE orders o 
        JOIN order_status os ON o.status = os.status_code 
        SET o.status_id = os.id 
        WHERE o.status_id IS NULL
    ");
    $updateStmt->execute();
    
    echo "Order status table created and populated successfully!\n";
    
} catch (PDOException $e) {
    error_log("Error creating order status table: " . $e->getMessage());
    die("Error creating order status table: " . $e->getMessage() . "\n");
}
?>