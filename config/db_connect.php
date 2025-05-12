<?php
// Start output buffering and disable error display
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
error_reporting(E_ALL);

// Include config file
require_once __DIR__ . '/config.php';

// Function to establish database connection
function getMainConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME_MAIN, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

// Function to establish database connection (legacy support)
function getConnection() {
    return getMainConnection();
}

// Function to get orders database connection
function getOrdersConnection() {
    return getMainConnection();
}

// Function to get users database connection
function getUsersConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME_USERS, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Ensure tables exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_status BOOLEAN DEFAULT FALSE,
            INDEX (user_id),
            INDEX (notification_type)
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            used TINYINT(1) DEFAULT 0,
            INDEX (token),
            INDEX (user_id)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_status BOOLEAN DEFAULT FALSE
        )");
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Users database connection error: " . $e->getMessage());
        throw new Exception("Users database connection failed: " . $e->getMessage());
    }
}

// Return single connection when needed
if (defined('SINGLE_CONNECTION') && SINGLE_CONNECTION === true) {
    return getUsersConnection();
}