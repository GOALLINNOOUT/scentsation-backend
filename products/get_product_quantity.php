<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Handle CORS
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (empty($origin)) {
    $origin = isset($_SERVER['HTTP_REFERER']) ? rtrim($_SERVER['HTTP_REFERER'], '/') : '';
}
if (empty($origin)) {
    $origin = 'null'; // Handle requests from file:// protocol or null origin
}

// List of allowed origins
$allowed_origins = array(
    'https://apiscentsation.great-site.net',
		'https://scentsation-admin.great-site.net',
		'https://scentsation.great-site.net',
);

// Check if the origin is allowed
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
} else {
    header("Access-Control-Allow-Origin: " . $allowed_origins[0]);
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400"); // Cache for 1 day

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/../config/db_connect.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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
            'product' => [
                'id' => $product['id'],
                'product_name' => $product['product_name'],
                'price' => $product['price'],
                'quantity' => $product['quantity'],
                'category' => $product['category'],
                'description' => $product['description']
            ]
        ]);
        
    } else {
        throw new Exception('Invalid request method');
    }
    
} catch (Exception $e) {
    error_log("Error in get_product_quantity.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}