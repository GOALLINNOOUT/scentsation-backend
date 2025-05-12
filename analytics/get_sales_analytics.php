<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/cors_handler.php';
require_once __DIR__ . '/../config/db_connect.php';

// Set execution time limit and memory limit for analytics
set_time_limit(120); // 2 minutes
ini_set('memory_limit', '256M');

try {
    // Create cache directory if it doesn't exist
    $cacheDir = __DIR__ . '/../cache';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    // Get the period and date range parameters
    $period = $_GET['period'] ?? 'today';
    $startDate = $_GET['startDate'] ?? null;
    $endDate = $_GET['endDate'] ?? null;
    
    // Validate date parameters if provided
    if ($startDate) {
        $startDate = date('Y-m-d', strtotime($startDate));
        if (!$startDate) {
            throw new Exception('Invalid start date format');
        }
    }
    
    if ($endDate) {
        $endDate = date('Y-m-d', strtotime($endDate));
        if (!$endDate) {
            throw new Exception('Invalid end date format');
        }
    }

    // If dates aren't provided, fallback to period-based calculation
    if (!$startDate || !$endDate) {
        $now = new DateTime();
        $endDate = null;
        
        switch($period) {
            case 'today':
                // Exact date match for today
                $startDate = $now->format('Y-m-d 00:00:00');
                $endDate = $now->format('Y-m-d 23:59:59');
                break;
                
            case 'week':
                // Get start of current week (Sunday)
                $startOfWeek = new DateTime();
                $startOfWeek->setTime(0, 0, 0);
                $startOfWeek->modify('-' . $startOfWeek->format('w') . ' days'); // Go back to Sunday
                $startDate = $startOfWeek->format('Y-m-d H:i:s');
                
                $endOfWeek = clone $startOfWeek;
                $endOfWeek->modify('+6 days'); // Go to Saturday
                $endOfWeek->setTime(23, 59, 59);
                $endDate = $endOfWeek->format('Y-m-d H:i:s');
                break;
                
            case 'month':
                // Start of current month
                $startDate = new DateTime($now->format('Y-m-01 00:00:00'));
                
                // End of current month
                $endOfMonth = new DateTime($now->format('Y-m-t 23:59:59'));
                $endDate = $endOfMonth->format('Y-m-d H:i:s');
                break;
                
            case '3months':
                $startDate = clone $now;
                $startDate->modify('-3 months');
                $startDate->setTime(0, 0, 0);
                $startDate = $startDate->format('Y-m-d H:i:s');
                $endDate = $now->format('Y-m-d 23:59:59');
                break;
                
            case '6months':
                $startDate = clone $now;
                $startDate->modify('-6 months');
                $startDate->setTime(0, 0, 0);
                $startDate = $startDate->format('Y-m-d H:i:s');
                $endDate = $now->format('Y-m-d 23:59:59');
                break;
                
            case '1year':
                $startDate = clone $now;
                $startDate->modify('-1 year');
                $startDate->setTime(0, 0, 0);
                $startDate = $startDate->format('Y-m-d H:i:s');
                $endDate = $now->format('Y-m-d 23:59:59');
                break;
                
            case '2years':
                $startDate = clone $now;
                $startDate->modify('-2 years');
                $startDate->setTime(0, 0, 0);
                $startDate = $startDate->format('Y-m-d H:i:s');
                $endDate = $now->format('Y-m-d 23:59:59');
                break;
                
            case '5years':
                $startDate = clone $now;
                $startDate->modify('-5 years');
                $startDate->setTime(0, 0, 0);
                $startDate = $startDate->format('Y-m-d H:i:s');
                $endDate = $now->format('Y-m-d 23:59:59');
                break;
                
            default:
                throw new Exception('Invalid period specified');
        }
        
        if (!$endDate) {
            $endDate = $now->format('Y-m-d 23:59:59');
        }
    }

    $pdo = getConnection();
    
    // Check if orders and order_items tables exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('orders', $tables) || !in_array('order_items', $tables)) {
        throw new Exception('Required tables do not exist');
    }
      try {
        // Get sales data for the selected date range with zero-filled missing dates
        $salesQuery = "
            WITH RECURSIVE date_range AS (
                SELECT CAST(:start_date AS DATE) AS date
                UNION ALL
                SELECT DATE_ADD(date, INTERVAL 1 DAY)
                FROM date_range
                WHERE date < CAST(:end_date AS DATE)
            )
            SELECT 
                dr.date,
                COUNT(DISTINCT o.id) as order_count,
                COALESCE(SUM(
                    (oi.quantity * oi.price) + 
                    COALESCE(o.deliveryFee, 0) - 
                    COALESCE(o.discount, 0)
                ), 0) as total_sales,
                COALESCE(SUM(oi.quantity), 0) as items_sold
            FROM date_range dr
            LEFT JOIN orders o ON DATE(o.order_date) = dr.date AND o.status_id IN (
                SELECT id FROM order_status WHERE status_code <> 'cancelled'
            )
            LEFT JOIN order_items oi ON o.id = oi.order_id
            GROUP BY dr.date
            ORDER BY dr.date ASC";

        $salesStmt = $pdo->prepare($salesQuery);
        $salesStmt->execute([
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        $salesData = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Return empty arrays if no data found
        if (empty($salesData)) {
            $salesData = [];
        }

        // Get sales by status within the date range
        $statusQuery = "
            SELECT 
                os.status_code as status,
                COUNT(DISTINCT o.id) as count,
                COALESCE(SUM(
                    (oi.quantity * oi.price) + 
                    COALESCE(o.deliveryFee, 0) - 
                    COALESCE(o.discount, 0)
                ), 0) as total_amount
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            INNER JOIN order_status os ON o.status_id = os.id
            WHERE DATE(o.order_date) BETWEEN :start_date AND :end_date
            GROUP BY os.status_code
            ORDER BY count DESC";

        $statusStmt = $pdo->prepare($statusQuery);
        $statusStmt->execute([
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        $statusData = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($statusData)) {
            $statusData = [];
        }

        // Format the response
        $response = [
            'success' => true,
            'data' => [
                'daily' => array_map(function($day) {
                    return [
                        'date' => $day['date'],
                        'orders' => (int)$day['order_count'],
                        'sales' => (float)$day['total_sales'],
                        'items' => (int)$day['items_sold']
                    ];
                }, $salesData),
                'status' => array_map(function($status) {
                    return [
                        'status' => $status['status'],
                        'count' => (int)$status['count'],
                        'amount' => (float)$status['total_amount']
                    ];
                }, $statusData)
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);

    } catch (Exception $e) {
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in get_sales_analytics.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching sales analytics data: ' . $e->getMessage()
    ]);
}