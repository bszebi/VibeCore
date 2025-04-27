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
    
    // Check work table
    if (in_array('work', $tables)) {
        echo "<h2>work Table Structure:</h2>";
        $stmt = $db->query("DESCRIBE work");
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
        $stmt = $db->query("SELECT COUNT(*) as count FROM work");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>Number of records in work: $count</p>";
        
        // Show a sample record if any exist
        if ($count > 0) {
            echo "<h3>Sample Record:</h3>";
            $stmt = $db->query("SELECT * FROM work LIMIT 1");
            $sample = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<pre>";
            print_r($sample);
            echo "</pre>";
        }
    } else {
        echo "<p>work table does not exist!</p>";
    }
    
    // Check work_stuff table
    if (in_array('work_stuff', $tables)) {
        echo "<h2>work_stuff Table Structure:</h2>";
        $stmt = $db->query("DESCRIBE work_stuff");
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
    } else {
        echo "<p>work_stuff table does not exist!</p>";
    }
    
    // Check work_to_stuffs table
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
    } else {
        echo "<p>work_to_stuffs table does not exist!</p>";
    }
    
    // Check stuffs table
    if (in_array('stuffs', $tables)) {
        echo "<h2>stuffs Table Structure:</h2>";
        $stmt = $db->query("DESCRIBE stuffs");
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
    } else {
        echo "<p>stuffs table does not exist!</p>";
    }
    
    // Check stuff_type table
    if (in_array('stuff_type', $tables)) {
        echo "<h2>stuff_type Table Structure:</h2>";
        $stmt = $db->query("DESCRIBE stuff_type");
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
    } else {
        echo "<p>stuff_type table does not exist!</p>";
    }
    
    // Check stuff_brand table
    if (in_array('stuff_brand', $tables)) {
        echo "<h2>stuff_brand Table Structure:</h2>";
        $stmt = $db->query("DESCRIBE stuff_brand");
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
    } else {
        echo "<p>stuff_brand table does not exist!</p>";
    }
    
    // Check stuff_model table
    if (in_array('stuff_model', $tables)) {
        echo "<h2>stuff_model Table Structure:</h2>";
        $stmt = $db->query("DESCRIBE stuff_model");
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
    } else {
        echo "<p>stuff_model table does not exist!</p>";
    }
    
    // Check user table
    if (in_array('user', $tables)) {
        echo "<h2>user Table Structure:</h2>";
        $stmt = $db->query("DESCRIBE user");
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
    } else {
        echo "<p>user table does not exist!</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
} 