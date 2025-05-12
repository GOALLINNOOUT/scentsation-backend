<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/config.php';

function validateUser($user_id) {
    try {
        $pdo = getUsersConnection();
        $stmt = $pdo->prepare("SELECT status FROM users WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Exception $e) {
        error_log("Error in validateUser: " . $e->getMessage());
        return false;
    }
}

function validateSession($token = null) {
    if (!$token && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $token = $matches[1];
        }
    }

    if (!$token) {
        return false;
    }

    try {
        $pdo = getUsersConnection();
        $stmt = $pdo->prepare("
            SELECT user_id 
            FROM user_sessions 
            WHERE token = ? 
            AND token_expiry > CURRENT_TIMESTAMP
        ");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Exception $e) {
        error_log("Error in validateSession: " . $e->getMessage());
        return false;
    }
}

function getUserFromToken($token) {
    try {
        $pdo = getUsersConnection();
        $stmt = $pdo->prepare("
            SELECT u.* 
            FROM users u
            JOIN user_sessions s ON u.user_id = s.user_id
            WHERE s.token = ? 
            AND s.token_expiry > CURRENT_TIMESTAMP
            AND u.status = 'active'
        ");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getUserFromToken: " . $e->getMessage());
        return null;
    }
}