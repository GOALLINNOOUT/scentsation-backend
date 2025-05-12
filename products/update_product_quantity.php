<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Enable error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

function sendJsonResponse($response) {
    echo json_encode($response);
    exit;
}

try {
    require_once __DIR__ . '/../config/db_connect.php';
    $pdo = getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $productId = isset($_POST['id']) ? intval($_POST['id']) : null;
        $quantityChange = isset($_POST['quantity']) ? intval($_POST['quantity']) : null;

        if (!$productId || $quantityChange === null) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Invalid product ID or quantity change.'
            ]);
        }

        // Start a database transaction to ensure atomicity
        $pdo->beginTransaction();

        try {
            // Get current quantity with lock
            $stmt = $pdo->prepare("SELECT quantity FROM products WHERE id = :id FOR UPDATE");
            $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to fetch current quantity');
            }

            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                throw new Exception('Product not found');
            }

            // Set new quantity directly instead of adding
            $newQuantity = $quantityChange;

            // Check if we have enough stock (only for negative quantities)
            if ($newQuantity < 0) {
                $pdo->rollBack();
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Invalid quantity.',
                    'availableQuantity' => $product['quantity']
                ]);
            }

            // Update the quantity
            $stmt = $pdo->prepare("UPDATE products SET quantity = :quantity WHERE id = :id");
            $stmt->bindParam(':quantity', $newQuantity, PDO::PARAM_INT);
            $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update quantity');
            }

            // Commit the transaction
            $pdo->commit();

            sendJsonResponse([
                'success' => true,
                'message' => 'Product quantity updated successfully',
                'availableQuantity' => $newQuantity
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } else {
        sendJsonResponse([
            'success' => false,
            'message' => 'Invalid request method.'
        ]);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'An error occurred while processing your request.',
        'error' => $e->getMessage()
    ]);
}