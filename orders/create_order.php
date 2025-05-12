<?php
// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400'); // Cache preflight for 24 hours
    exit(0);
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_connect.php';

try {
    // Get database connection
    $pdo = getConnection();
    $pdo->beginTransaction();

    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    // Map incoming fields to database column names
    $orderData = [
        'customer_name' => $data['customerName'],
        'email' => $data['email'],
        'phoneNumber' => $data['phoneNumber'],
        'street_address' => $data['streetAddress'] ?? null,
        'apartment' => $data['apartment'] ?? null,
        'city' => $data['city'] ?? null,
        'state' => $data['state'],
        'zip_code' => $data['zipCode'] ?? null,
        'location' => $data['location'],
        'alternative_contact_name' => $data['alternativeContactName'] ?? null,
        'alternative_contact_phone' => $data['alternativeContactPhone'] ?? null,
        'total' => $data['total'],
        'deliveryFee' => $data['deliveryFee'],
        'discount' => $data['discount'],
        'coupon_code' => $data['couponCode'] ?? null,
        'transactionReference' => $data['transactionReference'],
        'status' => 'pending',
        'order_date' => $data['order_date'],
    ];

    // Get status ID for 'pending'
    $statusStmt = $pdo->prepare("SELECT id FROM order_status WHERE status_code = ?");
    $statusStmt->execute(['pending']);
    $statusRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
    if (!$statusRow) {
        throw new Exception('Invalid order status');
    }

    // Insert order
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            customer_name, email, phoneNumber, 
            street_address, apartment, city, state, zip_code, location,
            alternative_contact_name, alternative_contact_phone,
            total, deliveryFee, discount, coupon_code, 
            transactionReference, status, status_id, order_date, user_id
        ) VALUES (
            :customer_name, :email, :phoneNumber,
            :street_address, :apartment, :city, :state, :zip_code, :location,
            :alternative_contact_name, :alternative_contact_phone,
            :total, :deliveryFee, :discount, :coupon_code,
            :transactionReference, :status, :status_id, :order_date, :user_id
        )
    ");

    $stmt->execute([
        ':customer_name' => $orderData['customer_name'],
        ':email' => $orderData['email'],
        ':phoneNumber' => $orderData['phoneNumber'],
        ':street_address' => $orderData['street_address'],
        ':apartment' => $orderData['apartment'],
        ':city' => $orderData['city'],
        ':state' => $orderData['state'],
        ':zip_code' => $orderData['zip_code'],
        ':location' => $orderData['location'],
        ':alternative_contact_name' => $orderData['alternative_contact_name'],
        ':alternative_contact_phone' => $orderData['alternative_contact_phone'],
        ':total' => $orderData['total'],
        ':deliveryFee' => $orderData['deliveryFee'],
        ':discount' => $orderData['discount'],
        ':coupon_code' => $orderData['coupon_code'],
        ':transactionReference' => $orderData['transactionReference'],
        ':status' => $orderData['status'],
        ':status_id' => $statusRow['id'],
        ':order_date' => $orderData['order_date'],
        ':user_id' => $data['user_id']
    ]);
    $orderId = $pdo->lastInsertId();

    // Insert order items
    if (!empty($data['items'])) {
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, product_name, quantity, price)
            VALUES (:order_id, :product_id, :product_name, :quantity, :price)
        ");

        foreach ($data['items'] as $item) {
            $stmt->execute([
                ':order_id' => $orderId,
                ':product_id' => $item['product_id'],
                ':product_name' => $item['name'],
                ':quantity' => $item['quantity'],
                ':price' => $item['price']
            ]);
        }
    }

    // Record coupon usage if a coupon was used
    if (!empty($orderData['coupon_code'])) {
        try {
            // Check if coupon is still valid and unused by this user
            $validationStmt = $pdo->prepare("
                SELECT cu.id, c.max_uses, c.uses 
                FROM coupons c 
                LEFT JOIN coupon_usage cu ON cu.coupon_code = c.code AND cu.user_id = :user_id
                WHERE c.code = :code 
                AND c.is_active = 1 
                AND (c.expiry_date IS NULL OR c.expiry_date > NOW())
            ");
            
            $validationStmt->execute([
                ':user_id' => $data['user_id'],
                ':code' => $orderData['coupon_code']
            ]);
            
            $result = $validationStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['id']) {
                error_log("Coupon reuse attempt: Code=" . $orderData['coupon_code'] . ", User=" . $data['user_id']);
                throw new Exception('Unable to apply coupon');
            }
            
            if ($result && $result['max_uses'] !== null && $result['uses'] >= $result['max_uses']) {
                error_log("Coupon max uses exceeded: Code=" . $orderData['coupon_code']);
                throw new Exception('Unable to apply coupon');
            }

            // Insert coupon usage record
            $usageStmt = $pdo->prepare("
                INSERT INTO coupon_usage (user_id, coupon_code, order_id) 
                VALUES (:user_id, :code, :order_id)
            ");
            
            $usageStmt->execute([
                ':user_id' => $data['user_id'],
                ':code' => $orderData['coupon_code'],
                ':order_id' => $orderId
            ]);

            // Update coupon usage count
            $updateStmt = $pdo->prepare("
                UPDATE coupons 
                SET uses = uses + 1 
                WHERE code = :code
            ");
            
            $updateStmt->execute([':code' => $orderData['coupon_code']]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            // Log the detailed error but return a generic message
            error_log("Database error in create_order.php: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Order processing failed'
            ]);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error in create_order.php: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Unable to process order'
            ]);
            exit;
        }
    }

    // Commit the transaction
    $pdo->commit();

    error_log("Order created successfully with reference: " . $orderData['transactionReference']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'order_id' => $orderId
    ]);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Error in create_order.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Order processing failed'
    ]);
}