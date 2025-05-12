<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Get all categories first
    $categoryStmt = $pdo->query("SELECT DISTINCT category FROM products");
    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $result = [];
    
    // For each category, get top 10 most expensive products
    $stmt = $pdo->prepare("SELECT id, product_name as name, price, category 
                          FROM products 
                          WHERE category = :category 
                          ORDER BY price DESC 
                          LIMIT 10");
                 
    foreach ($categories as $category) {
        $stmt->bindParam(':category', $category, PDO::PARAM_STR);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result[$category] = $products;
    }
    
    echo json_encode(['success' => true, 'data' => $result]);
    
} catch (Exception $e) {
    error_log("Error in get_top_products_by_category.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>