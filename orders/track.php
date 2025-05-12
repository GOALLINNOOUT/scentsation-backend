<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Handle CORS
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (empty($origin)) {
    $origin = isset($_SERVER['HTTP_REFERER']) ? rtrim($_SERVER['HTTP_REFERER'], '/') : '';
}
if (empty($origin)) {
    $origin = '*'; // Fall back to allowing all origins
}

header("Access-Control-Allow-Origin: {$origin}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/auth_functions.php';

function logError($error, $context) {
    $logFile = '../logs/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] track.php: {$error} - Context: {$context}\n";
    
    if (!file_exists('../logs')) {
        mkdir('../logs', 0777, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

try {
    if (!isset($_GET['transactionRef'])) {
        $error = 'Transaction reference is required';
        logError($error, 'Parameter Validation');
        throw new Exception($error);
    }

    $transactionRef = $_GET['transactionRef'];
    $pdo = getConnection();

    // Query to get order details by transaction reference
    $query = "SELECT o.id, o.customer_name, o.email, o.phoneNumber, 
              o.street_address, o.apartment, o.city, o.state, o.location,
              o.order_date as date, o.total, o.deliveryFee, o.discount, o.coupon_code,
              o.transactionReference, os.status_code as status, os.status_name as statusText,
              GROUP_CONCAT(CONCAT(oi.product_name, ':', oi.quantity, ':', oi.price) SEPARATOR ',') as items
              FROM orders o
              LEFT JOIN order_items oi ON o.id = oi.order_id
              LEFT JOIN order_status os ON o.status_id = os.id
              WHERE o.transactionReference = :transactionRef
              GROUP BY o.id";

    $stmt = $pdo->prepare($query);

    if (!$stmt) {
        $error = 'Database query preparation failed';
        logError($error, 'Database Preparation');
        throw new Exception('Database error occurred');
    }

    $stmt->bindParam(':transactionRef', $transactionRef, PDO::PARAM_STR);

    if (!$stmt->execute()) {
        $error = 'Query execution failed: ' . implode(', ', $stmt->errorInfo());
        logError($error, 'Database Execution');
        throw new Exception('Database error occurred');
    }

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $error = "Order not found for transaction reference: $transactionRef";
        logError($error, 'No Results Found');
        throw new Exception('Order not found. Please check your transaction reference.');
    }

    // Parse items into array
    $itemsArray = array();
    if (!empty($order['items'])) {
        $items = explode(',', $order['items']);
        foreach ($items as $item) {
            list($name, $quantity, $price) = explode(':', $item);
            $itemsArray[] = array(
                'name' => $name,
                'quantity' => (int)$quantity,
                'price' => (float)$price
            );
        }
    }
    $order['items'] = $itemsArray;

    // Add estimated delivery date if order is pending or processing
    if (in_array($order['status'], ['pending', 'processing'])) {
        $order['estimatedDelivery'] = date('Y-m-d', strtotime($order['date'] . ' + 5 days'));
    }

    // Format delivery information
    $order['deliveryInfo'] = array_filter([
        'address' => $order['street_address'],
        'apartment' => $order['apartment'],
        'city' => $order['city'],
        'state' => $order['state'],
        'location' => $order['location']
    ]);

    // Send success response
    echo json_encode([
        'success' => true,
        'order' => $order
    ]);

} catch (Exception $e) {
    http_response_code(400);
    if (!isset($error)) {
        logError($e->getMessage(), 'Uncaught Exception');
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>