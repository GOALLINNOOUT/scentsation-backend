<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

try {
    require_once __DIR__ . '/../config/db_connect.php';
    
    // Get and sanitize transaction reference
    $ref = isset($_GET['ref']) ? htmlspecialchars(trim($_GET['ref']), ENT_QUOTES, 'UTF-8') : null;
    if (!$ref) {
        throw new Exception('Transaction reference is required');
    }

    // Get database connection
    $pdo = getConnection();

    // Debug: Log the incoming reference and attempt to find it
    error_log("DEBUG: Attempting to verify order with reference: " . $ref);
    
    // First check if the order exists
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE transactionReference = :ref");
    $checkStmt->bindParam(':ref', $ref);
    $checkStmt->execute();
    $count = $checkStmt->fetchColumn();
    
    error_log("DEBUG: Found {$count} orders with reference: {$ref}");

    // Query to get order details by transaction reference
    $query = "SELECT o.id, o.customer_name, o.email, o.phoneNumber, o.state, o.location, 
              o.order_date as date, o.total, o.status, o.deliveryFee, o.transactionReference,
              GROUP_CONCAT(CONCAT(oi.product_name, ':', oi.quantity, ':', oi.price) SEPARATOR ',') as items
              FROM orders o
              LEFT JOIN order_items oi ON o.id = oi.order_id
              WHERE o.transactionReference = :ref
              GROUP BY o.id";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':ref', $ref, PDO::PARAM_STR);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        error_log("DEBUG: Order details query returned no results for reference: " . $ref);
        // Debug: Check what orders exist in the database
        $debugStmt = $pdo->query("SELECT id, transactionReference FROM orders ORDER BY id DESC LIMIT 5");
        $recentOrders = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("DEBUG: Recent orders in database: " . print_r($recentOrders, true));
        
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Order not found'
        ]);
        exit;
    }

    error_log("DEBUG: Successfully found order with reference: " . $ref);

    // Parse the items string into an array
    $itemsArray = array();
    if (!empty($row['items'])) {
        $items = explode(',', $row['items']);
        foreach ($items as $item) {
            list($name, $quantity, $price) = explode(':', $item);
            $itemsArray[] = array(
                'name' => $name,
                'quantity' => (int)$quantity,
                'price' => (float)$price
            );
        }
    }

    // Format the order data
    $orderData = array(
        'id' => (int)$row['id'],
        'customer_name' => $row['customer_name'],
        'email' => $row['email'],
        'phoneNumber' => $row['phoneNumber'],
        'state' => $row['state'],
        'location' => $row['location'],
        'date' => $row['date'],
        'total' => (float)$row['total'],
        'status' => $row['status'],
        'deliveryFee' => (float)$row['deliveryFee'],
        'transactionReference' => $row['transactionReference'],
        'items' => $itemsArray
    );

    echo json_encode([
        'success' => true,
        'order' => $orderData
    ]);

} catch (Exception $e) {
    error_log("Error in verify_order.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error verifying order: ' . $e->getMessage()
    ]);
}
?>