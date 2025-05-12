<?php
// Prevent PHP errors from being displayed
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    $envPath = __DIR__ . '/../../scentsation/.env';
    
    if (!file_exists($envPath)) {
        throw new Exception('Environment file not found: ' . $envPath);
    }

    $envContents = file_get_contents($envPath);
    if ($envContents === false) {
        throw new Exception('Unable to read environment file');
    }

    // Parse the .env file manually since we might have composer dependency issues
    $lines = array_filter(explode("\n", $envContents));
    $env = [];
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }

    $publicKey = $env['PAYSTACK_PUBLIC_KEY'] ?? null;
    
    if (!$publicKey) {
        throw new Exception('Paystack public key not found in environment variables');
    }

    echo json_encode([
        'success' => true,
        'PAYSTACK_PUBLIC_KEY' => $publicKey
    ]);
} catch (Exception $e) {
    error_log('Config error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Configuration error',
        'message' => $e->getMessage()
    ]);
}