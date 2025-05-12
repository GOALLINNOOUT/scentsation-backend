<?php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
error_reporting(E_ALL);

// Get the requesting origin
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// List of allowed origins
$allowed_origins = [
    'https://apiscentsation.great-site.net',
		'https://scentsation-admin.great-site.net',
		'https://scentsation.great-site.net',
];

// Check if the origin is allowed
if (in_array($origin, $allowed_origins) || $origin === 'null') {
    header("Access-Control-Allow-Origin: " . $origin);
} else {
    header("Access-Control-Allow-Origin: https://apiscentsation.great-site.net");
}

header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once __DIR__ . '/../config/db_connect.php';
    
    // Get and validate input
    $orderId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $newStatus = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    
    if (!$orderId || !$newStatus) {
        throw new Exception('Missing or invalid parameters');
    }
    
    // Define allowed status values
    $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($newStatus, $allowedStatuses)) {
        throw new Exception('Invalid status value. Allowed values: ' . implode(', ', $allowedStatuses));
    }

    $pdo = getConnection();
    $pdo->beginTransaction();
    
    try {
        // Get status_id from order_status table
        $statusStmt = $pdo->prepare("SELECT id FROM order_status WHERE status_code = :status_code");
        $statusStmt->bindParam(':status_code', $newStatus, PDO::PARAM_STR);
        $statusStmt->execute();
        $statusRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$statusRow) {
            throw new Exception('Invalid status value');
        }
        
        $statusId = $statusRow['id'];

        // Check if order exists and get user_id
        $check = $pdo->prepare("SELECT id, user_id FROM orders WHERE id = :id");
        $check->bindParam(':id', $orderId, PDO::PARAM_INT);
        $check->execute();
        
        $orderRow = $check->fetch(PDO::FETCH_ASSOC);
        if (!$orderRow) {
            throw new Exception('Order not found');
        }

        $userId = $orderRow['user_id'];

        // Update order status using status_id
        $stmt = $pdo->prepare("UPDATE orders SET status_id = :status_id WHERE id = :id");
        $stmt->bindParam(':status_id', $statusId, PDO::PARAM_INT);
        $stmt->bindParam(':id', $orderId, PDO::PARAM_INT);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update order status');
        }

        // Commit transaction
        $pdo->commit();

        // Clear output buffer and send success response
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully',
            'user_id' => $userId // Include user_id in response
        ]);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    ob_clean();
    error_log("Database error in admin_update_order_status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    ob_clean();
    error_log("Error in admin_update_order_status.php: " . $e->getMessage());
    http_response_code(400);
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