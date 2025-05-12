<?php
require_once __DIR__ . '/../config/cors_handler.php';
require_once __DIR__ . '/../config/db_connect.php';

try {
    // Get PDO connection to users database
    $pdo = getUsersConnection();
    
    // Query to get all active users
    $stmt = $pdo->prepare("SELECT user_id, name, email FROM users WHERE status = 'active' ORDER BY name ASC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);

} catch (Exception $e) {
    error_log("Error in get_users.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}