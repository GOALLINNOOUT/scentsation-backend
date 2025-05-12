<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once __DIR__ . '/../config/cors_handler.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getUsersConnection();
    
    // Get user_id from request
    $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
    
    if (!$user_id) {
        throw new Exception('User ID is required');
    }
    
    // Get notifications for the user
    $stmt = $pdo->prepare("
        SELECT n.*, un.read_status
        FROM notifications n
        JOIN user_notifications un ON un.notification_id = n.notification_id
        WHERE un.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_notifications.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}