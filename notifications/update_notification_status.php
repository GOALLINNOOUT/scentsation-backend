<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Get the requesting origin
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed_origins = array(
    'https://apiscentsation.great-site.net',
		'https://scentsation-admin.great-site.net',
		'https://scentsation.great-site.net',
);

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    header("Access-Control-Allow-Origin: " . $allowed_origins[0]);
}

header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400"); // Cache preflight for 24 hours
header("Content-Type: application/json");

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No content needed for preflight
    exit();
}

try {
    $pdo = getUsersConnection(); // Use the users database connection
    
    // Get and validate input
    $data = json_decode(file_get_contents("php://input"));
    if (!$data || !isset($data->notification_id) || !isset($data->user_id)) {
        throw new Exception('Missing required fields: notification_id and user_id');
    }

    // Validate input types
    $notification_id = filter_var($data->notification_id, FILTER_VALIDATE_INT);
    if ($notification_id === false) {
        throw new Exception('Invalid notification ID');
    }

    // Update the read status - using correct column name 'read_status'
    $stmt = $pdo->prepare("UPDATE user_notifications SET read_status = 1 WHERE notification_id = :notification_id AND user_id = :user_id");
    $stmt->bindParam(':notification_id', $notification_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $data->user_id, PDO::PARAM_STR);
    
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        throw new Exception('No notification found with the given ID for this user');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read'
    ]);

} catch (PDOException $e) {
    error_log("Database error in update_notification_status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("Error in update_notification_status.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}