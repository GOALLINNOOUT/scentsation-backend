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

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    // Get the period parameter
    $period = $_GET['period'] ?? 'today';
    
    // Define the interval based on period
    $interval = '1 DAY'; // default
    switch($period) {
        case 'today':
            $interval = '1 DAY';
            break;
        case 'week':
            $interval = '7 DAY';
            break;
        case 'month':
            $interval = '30 DAY';
            break;
        case '3months':
            $interval = '90 DAY';
            break;
        case '6months':
            $interval = '180 DAY';
            break;
        case '1year':
            $interval = '365 DAY';
            break;
        case '2years':
            $interval = '730 DAY';
            break;
        case '5years':
            $interval = '1825 DAY';
            break;
        default:
            $interval = '30 DAY';
    }

    $pdo = getConnection();
    
    // Get order analytics by location
    $locationQuery = "
        SELECT 
            o.state,
            COUNT(DISTINCT o.id) as order_count,
            COUNT(DISTINCT o.user_id) as customer_count,
            COALESCE(SUM(o.total), 0) as total_sales
        FROM orders o
        LEFT JOIN order_status os ON o.status_id = os.id
        WHERE (os.status_code != 'cancelled' OR os.status_code IS NULL)
        AND (o.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY))
        GROUP BY o.state
        ORDER BY total_sales DESC";

    // Convert interval string to number of days
    $days = (int)filter_var($interval, FILTER_SANITIZE_NUMBER_INT);
    
    $locationStmt = $pdo->prepare($locationQuery);
    $locationStmt->execute([$days]);
    $locationData = $locationStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $response = [
        'success' => true,
        'data' => [
            'locations' => array_map(function($loc) {
                return [
                    'state' => $loc['state'] ?? 'Unknown',
                    'orders' => (int)$loc['order_count'],
                    'customers' => (int)$loc['customer_count'],
                    'sales' => (float)$loc['total_sales']
                ];
            }, $locationData)
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_demographics.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching demographics data: ' . $e->getMessage()
    ]);
}