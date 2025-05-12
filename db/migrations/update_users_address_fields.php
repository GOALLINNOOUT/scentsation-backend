<?php
require_once __DIR__ . '/../../config/db_connect.php';

try {
    $pdo = getUsersConnection();

    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $alterStatements = [];
    
    // Add new address fields if they don't exist
    $newColumns = [
        'street_address' => 'VARCHAR(255)',
        'apartment_unit' => 'VARCHAR(100)',
        'zip_code' => 'VARCHAR(20)',
        'state' => 'VARCHAR(100)',
        'location' => 'VARCHAR(100)'
    ];
    
    foreach ($newColumns as $column => $type) {
        if (!in_array($column, $columns)) {
            $alterStatements[] = "ADD COLUMN $column $type DEFAULT NULL";
        }
    }
    
    if (!empty($alterStatements)) {
        $sql = "ALTER TABLE users " . implode(", ", $alterStatements);
        $pdo->exec($sql);
        echo "Successfully added address columns to users table\n";
    } else {
        echo "Address columns already exist in users table\n";
    }

} catch (Exception $e) {
    error_log("Error updating users table: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}