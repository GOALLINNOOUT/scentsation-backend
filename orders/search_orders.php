<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db_connect.php';

$response = ['success' => false, 'orders' => [], 'message' => ''];

try {
    $pdo = getConnection();

    // Search by transaction reference
    if (isset($_GET['ref'])) {
        $ref = '%' . $_GET['ref'] . '%';
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE transactionReference LIKE :ref");
        $stmt->bindParam(':ref', $ref);
    }
    // Search by customer name
    elseif (isset($_GET['customer'])) {
        $name = '%' . $_GET['customer'] . '%';
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_name LIKE :name");
        $stmt->bindParam(':name', $name);
    }
    // Search by state
    elseif (isset($_GET['state'])) {
        $state = $_GET['state'];
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE state = :state");
        $stmt->bindParam(':state', $state);
    }
    else {
        throw new Exception('No search criteria provided');
    }

    $stmt->execute();
    $orders = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $orders[] = [
            'order_id' => $row['id'],            'transaction_ref' => $row['transactionReference'],
            'customer_name' => $row['customer_name'],
            'date' => $row['order_date'],
            'total' => '₦' . number_format($row['total'], 2),
            'status' => $row['status'],
            'state' => $row['state']
        ];
    }

    $response['success'] = true;
    $response['orders'] = $orders;

} catch (Exception $e) {
    error_log("Error in search_orders.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Error: ' . $e->getMessage();
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

echo json_encode($response);