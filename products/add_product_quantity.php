<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/db_connect.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $product_id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $quantity_to_add = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);

    if (!$product_id || !$quantity_to_add) {
        throw new Exception('Invalid product ID or quantity');
    }

    $pdo = getConnection();
    $pdo->beginTransaction();

    // Get current quantity with lock
    $stmt = $pdo->prepare("SELECT quantity FROM products WHERE id = :id FOR UPDATE");
    $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to fetch current quantity');
    }

    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        throw new Exception('Product not found');
    }

    // Calculate new quantity
    $new_quantity = $product['quantity'] + $quantity_to_add;

    // Update the quantity
    $stmt = $pdo->prepare("UPDATE products SET quantity = :quantity WHERE id = :id");
    $stmt->bindParam(':quantity', $new_quantity, PDO::PARAM_INT);
    $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update quantity');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Product quantity updated successfully',
        'new_quantity' => $new_quantity
    ]);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Error in add_product_quantity.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>