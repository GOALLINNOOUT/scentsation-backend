<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
// Get the requesting origin
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// List of allowed origins - add your frontend domains here
$allowed_origins = [
    'https://apiscentsation.great-site.net',
		'https://scentsation-admin.great-site.net',
		'https://scentsation.great-site.net'
];

// Check if the origin is allowed
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $origin);
} else {
    // Default to the first allowed origin if the requesting origin is not allowed
    header("Access-Control-Allow-Origin: " . $allowed_origins[0]);
}

// Set other CORS headers
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

require_once('../config/db_connect.php');

// Get the authorization header
$headers = apache_request_headers();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$auth_header || !preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
    echo json_encode([
        'success' => false,
        'error' => 'No token provided'
    ]);
    exit;
}

$token = $matches[1];

try {
    // Initialize database connection
    $pdo = getMainConnection();

    // Check if token exists and is valid
    $stmt = $pdo->prepare("
        SELECT id, username, last_login, last_activity, 
               TIMESTAMPDIFF(HOUR, last_login, NOW()) as hours_since_login 
        FROM admin_users 
        WHERE session_token = ?
    ");
    $stmt->execute([$token]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        // Update last activity timestamp
        $updateStmt = $pdo->prepare("
            UPDATE admin_users 
            SET last_activity = NOW() 
            WHERE session_token = ?
        ");
        $updateStmt->execute([$token]);

        echo json_encode(['success' => true]);
    } else {
        // Clear invalid session token
        $clearStmt = $pdo->prepare("
            UPDATE admin_users 
            SET session_token = NULL 
            WHERE session_token = ?
        ");
        $clearStmt->execute([$token]);

        // Log invalid token attempt with detailed information
        $timestamp = date('Y-m-d H:i:s T');
        $requestIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? 'unknown';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $referer = $_SERVER['HTTP_REFERER'] ?? 'unknown';
        
        // Get any matching token regardless of time to see if it's expired
        $diagnosticStmt = $pdo->prepare("
            SELECT id, username, last_login, last_activity, 
                   TIMESTAMPDIFF(HOUR, last_login, NOW()) as hours_since_login 
            FROM admin_users 
            WHERE session_token = ?
        ");
        $diagnosticStmt->execute([$token]);
        $diagnosticResult = $diagnosticStmt->fetch(PDO::FETCH_ASSOC);
        
        $diagnosticInfo = '';
        if ($diagnosticResult) {
            $diagnosticInfo = sprintf(
                "\nDiagnostic Info:\nUsername: %s\nLast Login: %s\nLast Activity: %s\nHours Since Login: %d\nToken Status: %s",
                $diagnosticResult['username'],
                $diagnosticResult['last_login'],
                $diagnosticResult['last_activity'],
                $diagnosticResult['hours_since_login'],
                $diagnosticResult['hours_since_login'] >= 24 ? 'Expired' : 'Invalid'
            );
        } else {
            $diagnosticInfo = "\nDiagnostic Info: No matching token found in database";
        }
        
        $errorMessage = sprintf("[%s] Invalid token attempt:\nToken: %s\nIP: %s\nOrigin: %s\nMethod: %s\nUser-Agent: %s\nReferer: %s%s\n-----------------\n", 
            $timestamp, 
            $token,
            $requestIP,
            $requestOrigin,
            $requestMethod,
            $userAgent,
            $referer,
            $diagnosticInfo
        );
        error_log($errorMessage, 3, "../logs/error.log");

        echo json_encode([
            'success' => false,
            'error' => 'Invalid or expired token'
        ]);
    }
} catch (Exception $e) {
    $errorMessage = date('Y-m-d H:i:s') . " - Token verification error: " . $e->getMessage() . "\n";
    $errorMessage .= "Stack trace: " . $e->getTraceAsString() . "\n";
    error_log($errorMessage, 3, "../logs/error.log");
    
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred during verification'
    ]);
}
