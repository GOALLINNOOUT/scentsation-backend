<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../config/db_connect.php');

try {
    // Get database connection
    $pdo = getMainConnection();
    
    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/create_admin_users.sql');
    if (!$sql) {
        throw new Exception("Could not read SQL file");
    }
    echo "Executing SQL:\n" . $sql . "\n\n";
    $pdo->exec($sql);
    
    // Create a default admin user
    $default_username = 'admin';
    $default_password = password_hash('admin123', PASSWORD_DEFAULT);
    $default_email = 'admin@scentsation.com';
    
    // Check if default admin already exists
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute([$default_username]);
    
    if (!$stmt->fetch()) {
        // Insert default admin user
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, email) VALUES (?, ?, ?)");
        $stmt->execute([$default_username, $default_password, $default_email]);
    }
    
    echo "Admin users table created successfully with default admin user.\n";
    echo "Default credentials:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
    echo "Please change these credentials after first login!";
    
} catch (PDOException $e) {
    die("Error creating admin_users table: " . $e->getMessage());
}
