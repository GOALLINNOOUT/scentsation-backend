<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

session_start();
require_once __DIR__ . '/../config/admin_cors_handler.php';
require_once __DIR__ . '/../config/db_connect.php';

try {
    $ordersPdo = getOrdersConnection();
    $usersPdo = getUsersConnection();

    // Check if a specific order ID was requested
    $orderId = isset($_GET['id']) ? intval($_GET['id']) : null;

    // Prepare the base query
    $baseQuery = "
        SELECT o.*,
               os.status_code as status,
               os.status_name as status_text
        FROM orders o
        JOIN order_status os ON o.status_id = os.id
    ";

    if ($orderId) {
        // Query for specific order
        $stmt = $ordersPdo->prepare($baseQuery . " WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Order not found'
            ]);
            exit;
        }

        // Get order items
        try {
            $itemsStmt = $ordersPdo->prepare("
                SELECT oi.*, p.product_name, p.category 
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $itemsStmt->execute([$orderId]);
            $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching items for order ID {$orderId}: " . $e->getMessage());
            $order['items'] = [];
            $order['items_error'] = true;
        }

        echo json_encode([
            'success' => true,
            'order' => $order
        ]);
    } else {
        // Get all orders
        $stmt = $ordersPdo->query($baseQuery . " ORDER BY o.order_date DESC");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get items for each order
        foreach ($orders as &$order) {
            try {
                $itemsStmt = $ordersPdo->prepare("
                    SELECT oi.*, p.product_name, p.category 
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                ");
                $itemsStmt->execute([$order['id']]);
                $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Error fetching items for order ID {$order['id']}: " . $e->getMessage());
                $order['items'] = [];
                $order['items_error'] = true;
            }
        }

        echo json_encode([
            'success' => true,
            'orders' => $orders
        ]);
    }
} catch (Exception $e) {
    $errorMessage = "Error in get_admin_orders.php: " . $e->getMessage();
    error_log($errorMessage);
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching orders',
        'error_logged' => true
    ]);
}