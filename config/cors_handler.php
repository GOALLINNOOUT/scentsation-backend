<?php
// Start output buffering
ob_start();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

function handleCors() {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    if (empty($origin)) {
        $origin = isset($_SERVER['HTTP_REFERER']) ? rtrim($_SERVER['HTTP_REFERER'], '/') : '';
    }
    if (empty($origin) || $origin === 'null') {
        $origin = 'null'; // Handle requests from file:// protocol or null origin
    }

    // Allow from any origin
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // cache for 1 day

    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        }

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        } else {
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        }

        http_response_code(200); // Ensure OPTIONS request returns 200
        exit(0);
    }

    // Set content type for non-OPTIONS requests
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
}

handleCors();

// Only flush if this is the main script
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    ob_end_flush();
}