<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

try {
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // List all tables
    echo "<h2>Available Tables:</h2>";
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<pre>";
    print_r($tables);
    echo "</pre>";

    // Check work_stuff table if it exists
    if (in_array('work_stuff', $tables)) {
        echo "<h2>work_stuff Table Structure:</h2>";
        $stmt = $db->query("DESCRIBE work_stuff");
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";

        echo "<h2>Sample Data from work_stuff:</h2>";
        $stmt = $db->query("SELECT * FROM work_stuff LIMIT 5");
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";
    }

    // Check work_to_stuffs table if it exists
    if (in_array('work_to_stuffs', $tables)) {
        echo "<h2>work_to_stuffs Table Structure:</h2>";
        $stmt = $db->query("DESCRIBE work_to_stuffs");
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";

        echo "<h2>Sample Data from work_to_stuffs:</h2>";
        $stmt = $db->query("SELECT * FROM work_to_stuffs LIMIT 5");
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";
    }

    // Check work table
    echo "<h2>work Table Structure:</h2>";
    $stmt = $db->query("DESCRIBE work");
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";

    echo "<h2>Sample Data from work:</h2>";
    $stmt = $db->query("SELECT * FROM work LIMIT 5");
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";

} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo "<pre>";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    echo "</pre>";
} 