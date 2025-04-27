<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Get database connection
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Get all tables
    $tables = [];
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    echo "<h2>Database Tables:</h2>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Check work_to_stuffs table if it exists
    if (in_array('work_to_stuffs', $tables)) {
        echo "<h2>work_to_stuffs Table Structure:</h2>";
        $stmt = $db->query("DESCRIBE work_to_stuffs");
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if there are any records
        $stmt = $db->query("SELECT COUNT(*) as count FROM work_to_stuffs");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>Number of records in work_to_stuffs: $count</p>";
        
        // Show a sample record if any exist
        if ($count > 0) {
            echo "<h3>Sample Record:</h3>";
            $stmt = $db->query("SELECT * FROM work_to_stuffs LIMIT 1");
            $sample = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<pre>";
            print_r($sample);
            echo "</pre>";
        }
    } else {
        echo "<p>work_to_stuffs table does not exist!</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
} 