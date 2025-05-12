<?php
require_once __DIR__ . '/../config/db_connect.php';

function validateUserToken($user_id, $token) {
    try {
        $conn = getConnection();
        
        // Check if token exists and is valid for this user
        $stmt = $conn->prepare("SELECT token FROM user_sessions WHERE user_id = ? AND token = ? AND token_expiry > NOW()");
        $stmt->execute([$user_id, $token]);
        
        return $stmt->fetch() !== false;
    } catch(PDOException $e) {
        error_log("Token validation error: " . $e->getMessage());
        return false;
    }
}