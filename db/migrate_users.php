<?php
require_once __DIR__ . '/../config/db_connect.php';

try {
    // Get connections to both databases
    $usersConn = getUsersConnection();
    $mainConn = getMainConnection();

    // First create users table in main database if it doesn't exist
    $mainConn->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Copy users from users database to main database
    $users = $usersConn->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($users)) {
        $stmt = $mainConn->prepare("INSERT IGNORE INTO users (user_id, username, email, password, created_at) 
                                  VALUES (?, ?, ?, ?, ?)");
        
        foreach ($users as $user) {
            $stmt->execute([
                $user['user_id'],
                $user['username'],
                $user['email'],
                $user['password'],
                $user['created_at']
            ]);
        }
    }

    echo "Users migration completed successfully\n";

} catch(PDOException $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}