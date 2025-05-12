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
    
    if (!isset($data['code']) || !isset($data['user_id'])) {
        throw new Exception('Coupon code and user ID are required');
    }

    $code = strtoupper($data['code']);
    $user_id = $data['user_id'];
    $subtotal = $data['subtotal'] ?? 0;
    
    $pdo = getConnection();
    
    // First check if user has already used this coupon
    $usageStmt = $pdo->prepare("
        SELECT id FROM coupon_usage 
        WHERE user_id = :user_id AND coupon_code = :code
    ");
    
    $usageStmt->execute([
        ':user_id' => $user_id,
        ':code' => $code
    ]);

    if ($usageStmt->fetch()) {
        echo json_encode([
            'success' => false,
            'error' => 'You have already used this coupon'
        ]);
        exit;
    }

    // Check if coupon exists and is valid
    $stmt = $pdo->prepare("
        SELECT * FROM coupons 
        WHERE code = :code 
        AND is_active = 1 
        AND (expiry_date IS NULL OR expiry_date > NOW())
        AND (max_uses IS NULL OR uses < max_uses)
    ");
    
    $stmt->execute([':code' => $code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or expired coupon code'
        ]);
        exit;
    }

    // Check minimum purchase requirement
    if ($subtotal < $coupon['min_purchase']) {
        echo json_encode([
            'success' => false,
            'error' => 'Order total must be at least ₦' . number_format($coupon['min_purchase'], 2) . ' to use this coupon'
        ]);
        exit;
    }

    // Calculate discount
    $discount = 0;
    if ($coupon['discount_type'] === 'percentage') {
        $discount = $subtotal * ($coupon['discount_value'] / 100);
    } else {
        $discount = $coupon['discount_value'];
    }

    // Return coupon details
    echo json_encode([
        'success' => true,
        'coupon' => [
            'code' => $coupon['code'],
            'discount_type' => $coupon['discount_type'],
            'discount_value' => (float)$coupon['discount_value'],
            'discount_amount' => (float)$discount,
            'min_purchase' => (float)$coupon['min_purchase'],
            'description' => $coupon['description']
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in validate_coupon.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>