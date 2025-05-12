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
    if (!isset($_GET['user_id'])) {
        throw new Exception('User ID is required');
    }

    $user_id = htmlspecialchars(strip_tags($_GET['user_id']), ENT_QUOTES, 'UTF-8');
    $pdo = getUsersConnection();

    // Get user details from the database
    $stmt = $pdo->prepare("
        SELECT 
            user_id,
            name,
            email,
            phone_number,
            street_address,
            apartment_unit,
            state,
            location,
            zip_code,
            last_login,
            created_at
        FROM users 
        WHERE user_id = ? AND status = 'active'
    ");
    
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    echo json_encode([
        'success' => true,
        'user' => $user
    ]);

} catch (Exception $e) {
    error_log("Error in get_user_details.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}