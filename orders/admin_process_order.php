<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/admin_cors_handler.php';

// Handle CORS with admin handler
handleAdminCors();

try {
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    if (!$orderId) {
        throw new Exception('Invalid order ID');
    }

    // Get database connection
    $pdo = getConnection();

    // First check if the order exists and is in a valid state
    $checkStmt = $pdo->prepare("
        SELECT o.id, os.status_code 
        FROM orders o
        JOIN order_status os ON o.status_id = os.id
        WHERE o.id = ?
    ");
    
    $checkStmt->execute([$orderId]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    if ($order['status_code'] !== 'pending') {
        throw new Exception('Order cannot be processed because it is not in pending state');
    }

    // Update order status to processing
    $stmt = $pdo->prepare("
        UPDATE orders o 
        JOIN order_status os ON os.status_code = 'processing'
        SET o.status_id = os.id 
        WHERE o.id = ? AND o.status_id = (
            SELECT id FROM order_status WHERE status_code = 'pending'
        )
    ");
    
    if (!$stmt->execute([$orderId])) {
        throw new Exception('Failed to process order');
    }

    if ($stmt->rowCount() === 0) {
        throw new Exception('Order could not be processed. It may have been modified by another user.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Order processed successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in admin_process_order.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}