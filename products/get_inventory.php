<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/cors_handler.php';

try {
    // Include the database connection
    require_once __DIR__ . '/../config/db_connect.php';

    $pdo = getConnection();
    
    // Select all product fields including image path
    $stmt = $pdo->query("SELECT id, product_name, price, quantity, category, description, image FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $products
    ]);
    
} catch (PDOException $e) {
    error_log("Inventory fetch error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching inventory',
        'error' => $e->getMessage()
    ]);
}
?>