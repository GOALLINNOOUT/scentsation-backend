<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, OPTIONS');

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/validate_token.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Get authorization header
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$auth_header || !preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
    echo json_encode(['success' => false, 'message' => 'No authorization token provided']);
    exit;
}

$token = $matches[1];

try {
    $conn = getConnection();
    
    // Get user_id from user_sessions table
    $stmt = $conn->prepare("SELECT user_id FROM user_sessions WHERE token = ? AND token_expiry > NOW()");
    $stmt->execute([$token]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired session']);
        exit;
    }

    // Validate the token
    if (!validateUserToken($result['user_id'], $token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid session']);
        exit;
    }

    // Update last_active timestamp
    $stmt = $conn->prepare("UPDATE user_sessions SET last_active = NOW() WHERE token = ?");
    $stmt->execute([$token]);

    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    error_log("Session validation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}