<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/cors_handler.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_GET['id'])) {
        throw new Exception('Product ID is required');
    }

    $productId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($productId === false) {
        throw new Exception('Invalid product ID');
    }

    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to fetch product details');
    }

    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        throw new Exception('Product not found');
    }

    echo json_encode([
        'success' => true,
        'product' => $product
    ]);

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}