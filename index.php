<?php
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set error reporting based on environment
if ($_ENV['APP_DEBUG'] === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Handle CORS
header('Access-Control-Allow-Origin: ' . $_ENV['CORS_ALLOWED_ORIGINS']);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Parse the URL
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = ltrim($path, '/');

// Route the request
try {
    switch (true) {
        // Auth routes
        case preg_match('#^auth/login#', $path):
            require __DIR__ . '/auth/login.php';
            break;
        
        case preg_match('#^auth/register#', $path):
            require __DIR__ . '/auth/register.php';
            break;

        // Product routes
        case preg_match('#^products/get_product_details#', $path):
            require __DIR__ . '/products/get_product_details.php';
            break;

        case preg_match('#^products/search_products#', $path):
            require __DIR__ . '/products/search_products.php';
            break;

        // Order routes
        case preg_match('#^orders/create_order#', $path):
            require __DIR__ . '/orders/create_order.php';
            break;

        case preg_match('#^orders/get_orders#', $path):
            require __DIR__ . '/orders/get_orders.php';
            break;

        // Default route
        default:
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Route not found']);
            break;
    }
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    error_log($e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}
