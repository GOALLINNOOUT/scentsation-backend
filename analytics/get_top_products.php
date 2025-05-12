<?php
require_once '../config/admin_cors_handler.php';
require_once '../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Get period from query string, default to 'today'
    $period = isset($_GET['period']) ? $_GET['period'] : 'today';
    
    // Calculate date range based on period
    switch($period) {
        case 'today':
            $days = 1;
            break;
        case 'week':
            $days = 7;
            break;
        case 'month':
            $days = 30;
            break;
        case '3months':
            $days = 90;
            break;
        default:
            $days = 1;
    }

    // Query for top performing products
    $topQuery = "
        SELECT 
            p.product_name,
            p.category,
            COUNT(oi.product_id) as order_count,
            SUM(oi.quantity * oi.price) as revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE o.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
        AND o.status != 'cancelled'
        GROUP BY p.id, p.product_name, p.category
        HAVING order_count > 0
        ORDER BY order_count DESC
        LIMIT 10";

    $topStmt = $pdo->prepare($topQuery);
    $topStmt->execute([$days]);
    $topProducts = $topStmt->fetchAll(PDO::FETCH_ASSOC);

    // Query for bottom performing products
    $lowQuery = "
        SELECT 
            p.product_name,
            p.category,
            COUNT(oi.product_id) as order_count,
            COALESCE(SUM(oi.quantity * oi.price), 0) as revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE (o.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY) OR o.order_date IS NULL)
        AND (o.status != 'cancelled' OR o.status IS NULL)
        GROUP BY p.id, p.product_name, p.category
        HAVING order_count = 0 OR order_count IS NULL
        ORDER BY order_count ASC, revenue ASC
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