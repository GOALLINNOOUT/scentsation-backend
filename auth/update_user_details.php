<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require_once __DIR__ . '/../config/cors_handler.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

try {
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit();
    }

    // Get JSON input
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data) {
        throw new Exception('Invalid input data');
    }

    // Validate required fields
    if (!isset($data['user_id']) || !isset($data['type'])) {
        throw new Exception('Missing required fields');
    }

    $pdo = getUsersConnection();
    $user_id = htmlspecialchars(strip_tags($data['user_id']));
    $type = htmlspecialchars(strip_tags($data['type']));

    // Verify user exists and is active
    $stmt = $pdo->prepare("SELECT status FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    if ($user['status'] !== 'active') {
        throw new Exception('Account is not active');
    }

    // Update based on type
    switch ($type) {
        case 'personal':
            // Validate personal info fields
            if (!isset($data['name']) || !isset($data['email'])) {
                throw new Exception('Missing required personal information fields');
            }

            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }

            // Check if email exists for another user
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$data['email'], $user_id]);
            if ($stmt->fetch()) {
                throw new Exception('Email is already in use');
            }

            // Update personal information
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = ?, 
                    email = ?, 
                    phone_number = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['phone_number'] ?? null,
                $user_id
            ]);
            break;

        case 'address':
            // Update address information
            $stmt = $pdo->prepare("
                UPDATE users 
                SET street_address = ?,
                    apartment_unit = ?,
                    state = ?,
                    location = ?,
                    zip_code = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ?
            ");
            $stmt->execute([
                $data['street_address'] ?? null,
                $data['apartment_unit'] ?? null,
                $data['state'] ?? null,
                $data['location'] ?? null,
                $data['zip_code'] ?? null,
                $user_id
            ]);
            break;

        default:
            throw new Exception('Invalid update type');
    }

    // Check if update was successful
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'User details updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'No changes were made'
        ]);
    }

} catch (Exception $e) {
    error_log("Error in update_user_details.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>