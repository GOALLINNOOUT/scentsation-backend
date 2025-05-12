<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db_connect.php';

try {
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method is allowed');
    }

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['orderId'], $data['status'])) {
        throw new Exception('Order ID and status are required');
    }

    $pdo = getConnection();
    $pdo->beginTransaction();

    // Update order status
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET status = :status, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = :orderId
    ");

    $stmt->execute([
        ':status' => $data['status'],
        ':orderId' => $data['orderId']
    ]);

    // Update customer analytics
    $stmt = $pdo->prepare("
        INSERT INTO customer_analytics 
            (customer_id, state, total_orders, total_spent, last_order_date)
        SELECT 
            o.customer_id,
            o.state,
            COUNT(*) as total_orders,
            SUM(o.total) as total_spent,
            MAX(o.order_date) as last_order_date
        FROM orders o
        WHERE o.customer_id = (SELECT customer_id FROM orders WHERE id = :orderId)
        AND o.status != 'cancelled'
        GROUP BY o.customer_id, o.state
        ON DUPLICATE KEY UPDATE
            total_orders = VALUES(total_orders),
            total_spent = VALUES(total_spent),
            last_order_date = VALUES(last_order_date)
    ");

    $stmt->execute([':orderId' => $data['orderId']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order status and analytics updated successfully'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error updating order status: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while updating the order status'
    ]);
}
?>