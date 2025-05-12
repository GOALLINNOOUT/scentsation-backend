<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/db_connect.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['code']) || !isset($data['user_id']) || !isset($data['order_id'])) {
        throw new Exception('Invalid request parameters');
    }

    $code = strtoupper($data['code']);
    $user_id = $data['user_id'];
    $order_id = $data['order_id'];
    
    $pdo = getConnection();
    $pdo->beginTransaction();
    
    try {
        // Insert coupon usage record
        $usageStmt = $pdo->prepare("
            INSERT INTO coupon_usage (user_id, coupon_code, order_id) 
            VALUES (:user_id, :code, :order_id)
        ");
        
        $usageStmt->execute([
            ':user_id' => $user_id,
            ':code' => $code,
            ':order_id' => $order_id
        ]);

        // Update coupon usage count
        $updateStmt = $pdo->prepare("
            UPDATE coupons 
            SET uses = uses + 1 
            WHERE code = :code
        ");
        
        $updateStmt->execute([':code' => $code]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Coupon usage recorded successfully'
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        // Log the actual error for debugging but return a generic message
        error_log("Database error in record_coupon_use.php: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Unable to process coupon'
        ]);
    }

} catch (Exception $e) {
    error_log("Error in record_coupon_use.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request'
    ]);
}
?>