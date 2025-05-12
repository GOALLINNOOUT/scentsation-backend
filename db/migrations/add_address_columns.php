<?php
require_once __DIR__ . '/../../config/db_connect.php';

try {
    $pdo = getUsersConnection();

    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $alterStatements = [];
    
    if (!in_array('state', $columns)) {
        $alterStatements[] = "ADD COLUMN state VARCHAR(100) DEFAULT NULL";
    }
    
    if (!in_array('location', $columns)) {
        $alterStatements[] = "ADD COLUMN location VARCHAR(100) DEFAULT NULL";
    }
    
    if (!empty($alterStatements)) {
        $sql = "ALTER TABLE users " . implode(", ", $alterStatements);
        $pdo->exec($sql);
        echo "Successfully added state and location columns to users table\n";
    } else {
        echo "Columns already exist in users table\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}