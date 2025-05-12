<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/cors_handler.php';
require_once __DIR__ . '/../config/db_connect.php';

// Set execution time limit for analytics queries
set_time_limit(120); // 2 minutes
ini_set('memory_limit', '256M');

try {
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }
    
    // Check cache
    $cacheFile = __DIR__ . '/../cache/demographics_' . date('Y-m-d') . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) { // Cache for 1 hour
        header('Content-Type: application/json');
        header('X-Cache: HIT');
        readfile($cacheFile);
        exit();
    }

    $pdo = getConnection();
      // Get demographics by state with time period filtering
    $locationQuery = "
        SELECT 
            o.state,
            COUNT(DISTINCT o.id) as order_count,
            COUNT(DISTINCT o.customer_name) as customer_count,
            ROUND(SUM(o.total), 2) as total_sales,
            ROUND(AVG(o.total), 2) as avg_order_value
        FROM orders o
        USE INDEX (idx_order_date, idx_status)
        WHERE o.status != 'cancelled'
        AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY o.state
        ORDER BY total_sales DESC
    ";
    
    try {
        $locationStmt = $pdo->prepare($locationQuery);
        $locationStmt->execute();
        $locationData = $locationStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Demographics analytics - Location query error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw $e;
    }

    // Get top customers
    $customerQuery = "
        SELECT 
            customer_name,
            COUNT(*) as order_count,
            SUM(total) as total_spent
        FROM orders
        WHERE status != 'cancelled'
        GROUP BY customer_name
        ORDER BY total_spent DESC
        LIMIT 10
    ";

    try {
        $customerStmt = $pdo->prepare($customerQuery);
        $customerStmt->execute();
        $customerData = $customerStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Demographics analytics - Customer query error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw $e;
    }

    // Format and send response
    $response = [
        'success' => true,
        'data' => [
            'locations' => array_map(function($location) {
                return [
                    'state' => $location['state'],
                    'orders' => (int)$location['order_count'],
                    'customers' => (int)$location['customer_count'],
                    'sales' => (float)$location['total_sales']
                ];
            }, $locationData),
            'topCustomers' => array_map(function($customer) {
                return [
                    'name' => $customer['customer_name'],
                    'orders' => (int)$customer['order_count'],
                    'spent' => (float)$customer['total_spent']
                ];
            }, $customerData)
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Demographics analytics - Critical error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching demographics analytics'
    ]);
}