<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Create coupons table
    $sql = "CREATE TABLE IF NOT EXISTS coupons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        discount_type ENUM('percentage', 'fixed') NOT NULL,
        discount_value DECIMAL(10,2) NOT NULL,
        min_purchase DECIMAL(10,2) DEFAULT 0,
        description TEXT,
        is_active BOOLEAN DEFAULT 1,
        max_uses INT DEFAULT NULL,
        uses INT DEFAULT 0,
        expiry_date DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (code),
        INDEX (is_active)
    )";
    
    $pdo->exec($sql);
    
    // Insert initial coupons
    $stmt = $pdo->prepare("INSERT IGNORE INTO coupons 
        (code, discount_type, discount_value, min_purchase, description) VALUES 
        (:code, :discount_type, :discount_value, :min_purchase, :description)");

    $initialCoupons = [
        [
            'code' => 'WELCOME10',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'min_purchase' => 0,
            'description' => '10% off your first order'
        ],
        [
            'code' => 'SAVE20',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'min_purchase' => 5000,
            'description' => '20% off orders above ₦5000'
        ],
        [
            'code' => 'SCENT500',
            'discount_type' => 'fixed',
            'discount_value' => 500.00,
            'min_purchase' => 2000,
            'description' => '₦500 off orders above ₦2000'
        ]
    ];

    foreach ($initialCoupons as $coupon) {
        $stmt->execute($coupon);
    }

    echo "Coupons table created and initialized successfully\n";

} catch (PDOException $e) {
    error_log("Error initializing coupons table: " . $e->getMessage());
    die("Error initializing coupons table: " . $e->getMessage() . "\n");
}
?>