<?php
session_start();
require_once __DIR__ . '/../config/cors_handler.php';

try {
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['user_role']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'authenticated' => false
        ]);
    }
} catch (Exception $e) {
    error_log("Error in check_session.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}