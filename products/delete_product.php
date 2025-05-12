<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/admin_cors_handler.php';
require_once __DIR__ . '/../config/db_connect.php';

// Start output buffering
ob_start();

try {
    // Handle OPTIONS request for CORS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Make sure we're receiving a DELETE request or POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get input from POST or php://input for DELETE requests
    $input = $_SERVER['REQUEST_METHOD'] === 'DELETE' ? 
        json_decode(file_get_contents('php://input'), true) : 
        $_POST;

    // Validate input
    $id = isset($input['id']) ? filter_var($input['id'], FILTER_VALIDATE_INT) : null;
    if (!$id) {
        throw new Exception('Invalid product ID');
    }

    // Get database connection
    $pdo = getConnection();

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // First check if the product exists
        $check = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $check->bindParam(':id', $id, PDO::PARAM_INT);
        $check->execute([$id]);

        if (!$check->fetch()) {
            throw new Exception('Product not found');
        }

        // Delete wishlist entries first
        $wishlistStmt = $pdo->prepare("DELETE FROM wishlist WHERE product_id = ?");
        $wishlistStmt->execute([$id]);

        // Mark order items as deleted but keep the record (set product_id to NULL)
        $orderItemsStmt = $pdo->prepare("UPDATE order_items SET product_id = NULL WHERE product_id = ?");
        $orderItemsStmt->execute([$id]);

        // Reviews will be automatically deleted due to ON DELETE CASCADE

        // Finally delete the product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        if (!$stmt->execute([$id])) {
            throw new Exception('Failed to delete product');
        }

        // Commit the transaction
        $pdo->commit();

        // Return success response
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);

    } catch (Exception $e) {
        // Rollback the transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    error_log("Database error in delete_product.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    error_log("Error in delete_product.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    // Ensure we end output buffering
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}