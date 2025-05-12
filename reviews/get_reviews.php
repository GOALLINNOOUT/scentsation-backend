<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

require_once __DIR__ . '/../config/db_connect.php';

try {
    $conn = getConnection();
    
    // Get the limit parameter and ensure it's an integer
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
    
    // Check if product_id is provided
    $productId = isset($_GET['product_id']) ? $_GET['product_id'] : null;
    
    if ($productId) {
        // Get product-specific reviews
        $stmt = $conn->prepare("
            SELECT r.*, u.username 
            FROM reviews r 
            LEFT JOIN users u ON r.user_id = u.user_id 
            WHERE r.product_id = ? 
            ORDER BY r.date DESC
            LIMIT $limit
        ");
        $stmt->execute([$productId]);
    } else {
        // Get general reviews
        $stmt = $conn->prepare("
            SELECT r.*, u.username 
            FROM reviews r 
            LEFT JOIN users u ON r.user_id = u.user_id 
            WHERE r.product_id IS NULL 
            ORDER BY r.date DESC
            LIMIT $limit
        ");
        $stmt->execute();
    }
    
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'reviews' => $reviews
    ]);
    
} catch(PDOException $e) {
    error_log("Error fetching reviews: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch reviews'
    ]);
}