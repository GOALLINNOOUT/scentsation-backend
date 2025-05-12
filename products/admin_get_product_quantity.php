<?php
require_once '../config/admin_cors_handler.php';
require_once '../config/db_connect.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    $productId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$productId) {
        throw new Exception('Product ID is required');
    }

    $pdo = getConnection();
    
    // Get product details
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to fetch product details');
    }

    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        throw new Exception('Product not found');
    }

    // Return success response with product details
    echo json_encode([
        'success' => true,
        'product' => $product
    ]);

} catch (Exception $e) {
    error_log("Error in admin_get_product_quantity.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}