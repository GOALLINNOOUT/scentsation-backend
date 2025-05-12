<?php
// Enable detailed error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Database configuration constants
define('DB_HOST', 'sql308.infinityfree.com');
define('DB_USER', 'if0_38951715');
define('DB_PASS', 'adeLOLA10');
define('DB_NAME_USERS', 'if0_38951715_scentsation_users_db');
define('DB_NAME_MAIN', 'if0_38951715_scentsation_db');

// SMTP configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'adeyekunadelola2009@gmail.com');
define('SMTP_PASS', 'afhl rzxt czix hhsq');
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'adeyekunadelola2009@gmail.com');

// JWT configuration
define('JWT_SECRET', 'your-secret-key');
define('JWT_EXPIRY', 3600); // 1 hour

// Other configuration 
define('ROOT_URL', 'https://apiscentsation.great-site.net');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

try {
    // Create databases if they don't exist
    $temp_conn = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
    ));
    
    // Create users database if it doesn't exist
    $temp_conn->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME_USERS);
    
    // Create main database if it doesn't exist
    $temp_conn->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME_MAIN);
    
    // Log successful database creation/connection
    error_log("Databases initialized successfully");
    
} catch (PDOException $e) {
    error_log("Database configuration error: " . $e->getMessage());
    throw new Exception("Database configuration error: " . $e->getMessage());
}
?>