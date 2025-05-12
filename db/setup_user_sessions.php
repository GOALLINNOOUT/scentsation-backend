<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getConnection();
    
    // Create user_sessions table
    $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
        session_id VARCHAR(255) PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        token_expiry TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 24 HOUR),
        last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(user_id),
        INDEX(token),
        INDEX(token_expiry)
    )";
    
    $pdo->exec($sql);
    echo "User sessions table created successfully\n";
    
} catch (PDOException $e) {
    error_log("Error creating user sessions table: " . $e->getMessage());
    die("Error creating user sessions table: " . $e->getMessage() . "\n");
}
?>