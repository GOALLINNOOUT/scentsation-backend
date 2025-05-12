<?php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

try {
    require_once __DIR__ . '/../config/db_connect.php';
    
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    if (!$orderId) {
        throw new Exception('Invalid order ID');
    }

    // Get database connection
    $pdo = getConnection();

    // Update order status using PDO prepared statement
    $stmt = $pdo->prepare("UPDATE orders SET status = 'processed' WHERE id = :orderId");
    $stmt->bindParam(':orderId', $orderId, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to process order');
    }

    if ($stmt->rowCount() === 0) {
        throw new Exception('Order not found');
    }

    ob_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order processed successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in process_order.php: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}
?>