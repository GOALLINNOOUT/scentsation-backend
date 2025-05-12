<?php
require_once '../config/cors_handler.php';
require_once '../config/db_connect.php';

try {
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
    
    // Get top performing products
    $topQuery = "
        SELECT 
            p.id,
            p.product_name,
            COUNT(DISTINCT o.id) as order_count,
            COALESCE(SUM(oi.quantity), 0) as total_quantity,
            COALESCE(SUM(o.total), 0) as revenue,
            p.category
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id 
        LEFT JOIN order_status os ON o.status_id = os.id
        WHERE (os.status_code IS NULL OR os.status_code != 'cancelled')
        AND (o.order_date IS NULL OR o.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY))
        GROUP BY p.id, p.product_name, p.category
        ORDER BY total_quantity DESC
        LIMIT 10";

    // Convert interval string to number of days
    $days = (int)filter_var($interval, FILTER_SANITIZE_NUMBER_INT);
    
    $topStmt = $pdo->prepare($topQuery);
    $topStmt->execute([$days]);
    $topProducts = $topStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get lowest performing products
    $lowQuery = "
        SELECT 
            p.id,
            p.product_name,
            p.category,
            COUNT(DISTINCT o.id) as order_count,
            COALESCE(SUM(oi.quantity), 0) as total_quantity,
            COALESCE(SUM(o.total), 0) as revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        LEFT JOIN order_status os ON o.status_id = os.id
        WHERE (os.status_code IS NULL OR os.status_code != 'cancelled')
        AND (o.order_date IS NULL OR o.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY))
        GROUP BY p.id, p.product_name, p.category
        HAVING total_quantity = 0 OR total_quantity IS NULL
        ORDER BY total_quantity ASC
        LIMIT 10";

    $lowStmt = $pdo->prepare($lowQuery);
    $lowStmt->execute([$days]);
    $lowProducts = $lowStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    $response = [
        'success' => true,
        'data' => [
            'overall' => array_map(function($p) {
                return [
                    'name' => $p['product_name'],
                    'category' => $p['category'] ?? 'Uncategorized',
                    'count' => (int)$p['order_count'],
                    'revenue' => (float)$p['revenue']
                ];
            }, $topProducts),
            'lowPerformers' => array_map(function($p) {
                return [
                    'name' => $p['product_name'],
                    'category' => $p['category'] ?? 'Uncategorized',
                    'count' => (int)$p['order_count'],
                    'revenue' => (float)$p['revenue']
                ];
            }, $lowProducts)
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_top_products.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching product analytics data: ' . $e->getMessage()
    ]);
}