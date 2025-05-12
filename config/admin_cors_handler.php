<?php
// Start output buffering
ob_start();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

function handleAdminCors() {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    if (empty($origin)) {
        $origin = isset($_SERVER['HTTP_REFERER']) ? rtrim($_SERVER['HTTP_REFERER'], '/') : '';
    }
      // List of allowed origins for admin endpoints
    $allowed_origins = array(
        'https://apiscentsation.great-site.net',
        'https://scentsation-admin.great-site.net',
        'https://scentsation.great-site.net',
        'null'
    );

    // Check if the origin is allowed
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: {$origin}");
    } else {
        header("Access-Control-Allow-Origin: " . $allowed_origins[0]);
    }

    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // cache for 1 day

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        }

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        } else {
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        }

        exit(0);
    }

    // Additional security headers for admin endpoints
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // Set content type for non-OPTIONS requests
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
}

// Automatically handle CORS when included
handleAdminCors();

// Only flush if this is the main script
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    ob_end_flush();
}