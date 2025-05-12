<?php
// Prevent PHP errors from being displayed directly
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Set headers first before any output
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Function to send JSON response
function sendJsonResponse($data) {
    echo json_encode($data);
    exit;
}

try {
    // Include database connection
    require_once __DIR__ . '/../config/db_connect.php';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $productId = isset($_GET['id']) ? intval($_GET['id']) : null;
        $quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : null;

        if (!$productId || !$quantity) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Invalid product ID or quantity.'
            ]);
        }

        // Get database connection
        $pdo = getConnection();
        
        // Query the database for the product
        $stmt = $pdo->prepare("SELECT id, quantity, price FROM products WHERE id = :id");
        $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute query');
        }

        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Product not found.'
            ]);
        }

        if ($product['quantity'] < $quantity) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Insufficient stock available.',
                'availableQuantity' => $product['quantity']
            ]);
        }

        sendJsonResponse([
            'success' => true,
            'availableQuantity' => $product['quantity'],
            'price' => $product['price']
        ]);
    } else {
        sendJsonResponse([
            'success' => false,
            'message' => 'Invalid request method.'
        ]);
    }

} catch (Exception $e) {
    // Log the error
    error_log("Error in validate_product.php: " . $e->getMessage());
    
    sendJsonResponse([
        'success' => false,
        'message' => 'An error occurred while validating the product.',
        'error' => $e->getMessage()
    ]);
}
?>
