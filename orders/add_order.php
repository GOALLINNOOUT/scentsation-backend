<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Sanitize and validate inputs
    $customerName = filter_var($_POST['customerName'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $location = filter_var($_POST['location'], FILTER_SANITIZE_STRING);
    $pickupLocation = filter_var($_POST['pickupLocation'], FILTER_SANITIZE_STRING);
    $total = filter_var($_POST['total'], FILTER_VALIDATE_FLOAT);
    $status = 'pending';
    $transactionReference = filter_var($_POST['transactionReference'], FILTER_SANITIZE_STRING);
    $date = date('Y-m-d H:i:s');

    // Begin transaction
    $pdo->beginTransaction();

    // Insert order
    $stmt = $pdo->prepare("INSERT INTO orders (customerName, email, location, pickupLocation, total, status, transactionReference, date)
                          VALUES (:customerName, :email, :location, :pickupLocation, :total, :status, :transactionReference, :date)");
    
    $stmt->bindParam(':customerName', $customerName);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':location', $location);
    $stmt->bindParam(':pickupLocation', $pickupLocation);
    $stmt->bindParam(':total', $total);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':transactionReference', $transactionReference);
    $stmt->bindParam(':date', $date);

    if (!$stmt->execute()) {
        throw new Exception("Error adding order");
    }

    $orderId = $pdo->lastInsertId();

    // Insert order items
    $items = json_decode($_POST['items'], true);
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_name, quantity, price) VALUES (:orderId, :productName, :quantity, :price)");
    
    foreach ($items as $item) {
        $stmt->bindParam(':orderId', $orderId);
        $stmt->bindParam(':productName', $item['name']);
        $stmt->bindParam(':quantity', $item['quantity']);
        $stmt->bindParam(':price', $item['price']);
        
        if (!$stmt->execute()) {
            throw new Exception("Error adding order item");
        }
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Order added successfully', 'order_id' => $orderId]);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Error in add_order.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


