<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Include CORS handler
require_once __DIR__ . '/../config/admin_cors_handler.php';

// Include database connection
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
        throw new Exception('Only GET method is allowed');
    }

    $pdo = getConnection();

    // Get period from query string, default to 'today'
    $period = isset($_GET['period']) ? $_GET['period'] : 'today';
    
    // Calculate date range based on period
    $dateRange = '';
    switch($period) {
        case 'today':
            $dateRange = 'DATE(o.order_date) = CURDATE()';
            break;
        case 'week':
            $dateRange = 'o.order_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)';
            break;
        case 'month':
            $dateRange = 'o.order_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)';
            break;
        case '3months':
            $dateRange = 'o.order_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)';
            break;
        default:
            $dateRange = 'DATE(o.order_date) = CURDATE()';
    }

    // Get customer analytics data with time period filter
    $query = "
        SELECT 
            o.customer_name,
            COUNT(*) as total_orders,
            SUM(o.total) as total_spent,
            MAX(o.state) as state,
            MAX(o.order_date) as last_order_date
        FROM orders o
        WHERE o.status != 'cancelled'
        AND {$dateRange}
        GROUP BY o.customer_name
        ORDER BY total_spent DESC
        LIMIT 20
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $customerData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $customerData
    ]);

} catch (Exception $e) {
    error_log("Error fetching customer analytics: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching customer analytics'
    ]);
}