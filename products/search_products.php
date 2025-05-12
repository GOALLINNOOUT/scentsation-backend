<?php
// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/db_connect.php';
header('Content-Type: application/json');

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

$response = ['success' => false, 'products' => [], 'message' => ''];

try {
    $pdo = getConnection();

    if (isset($_GET['name'])) {
        $name = '%' . $_GET['name'] . '%';
        $stmt = $pdo->prepare("SELECT id, product_name as name, category, price, quantity as stock FROM products WHERE product_name LIKE :name");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'category' => $row['category'],
                'price' => number_format($row['price'], 2),
                'stock' => $row['stock']
            ];
        }

        $response['success'] = true;
        $response['products'] = $products;
    } else {
        throw new Exception('No search criteria provided');
    }
} catch (Exception $e) {
    error_log("Error in search_products.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);