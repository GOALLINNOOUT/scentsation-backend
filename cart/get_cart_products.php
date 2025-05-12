<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/cors_handler.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Set content type after CORS headers
header("Content-Type: application/json");

ob_start();

try {
    $pdo = getMainConnection();
    
    // Get input from request
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Handle both cart_items and productIds formats
    $product_ids = [];
    if (isset($input['cart_items'])) {
        $product_ids = array_map(function($item) {
            return (int)$item['product_id'];
        }, $input['cart_items']);
    } elseif (isset($input['productIds'])) {
        $product_ids = array_map('intval', $input['productIds']);
    }
    
    if (empty($product_ids)) {
        echo json_encode([
            'success' => true,
            'products' => []
        ]);
        exit;
    }
    
    // Create placeholders for SQL query
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    
    // Get product details
    $stmt = $pdo->prepare("
        SELECT id, product_name as name, price, quantity, image, description, category
        FROM products 
        WHERE id IN ($placeholders)
    ");
    
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_cart_products.php: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($pdo)) {
        $pdo = null;
    }
    // End output buffering
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}