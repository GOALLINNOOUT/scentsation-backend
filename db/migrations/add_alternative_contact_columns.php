<?php
require_once __DIR__ . '/../../config/db_connect.php';

try {
    $pdo = getConnection();

    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE orders");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $alterStatements = [];
    
    if (!in_array('alternative_contact_name', $columns)) {
        $alterStatements[] = "ADD COLUMN alternative_contact_name VARCHAR(255) DEFAULT NULL";
    }
    
    if (!in_array('alternative_contact_phone', $columns)) {
        $alterStatements[] = "ADD COLUMN alternative_contact_phone VARCHAR(20) DEFAULT NULL";
    }
    
    if (!empty($alterStatements)) {
        $sql = "ALTER TABLE orders " . implode(", ", $alterStatements);
        $pdo->exec($sql);
        echo "Successfully added alternative contact columns to orders table\n";
    } else {
        echo "Columns already exist in orders table\n";
    }

} catch (Exception $e) {
    error_log("Error adding alternative contact columns: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}