<?php
session_start();

// Minden kimenet előtt állítsuk be a JSON header-t
header('Content-Type: application/json');

// Hibakezelés bekapcsolása fejlesztés alatt
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Ellenőrizzük, hogy a database.php létezik-e
    if (!file_exists('../config/database.php')) {
        throw new Exception('Database configuration file not found');
    }

    require_once '../config/database.php';
    
    // Ellenőrizzük a kapcsolatot
    global $conn;
    
    if (!$conn) {
        throw new Exception('Database connection failed: ' . mysqli_connect_error());
    }
    
    // Lekérjük a szerepköröket
    $query = "SELECT id, role_name FROM roles ORDER BY role_name";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception('Failed to execute query: ' . mysqli_error($conn));
    }
    
    $roles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $roles[] = $row;
    }
    
    // Sikeres válasz
    echo json_encode([
        'success' => true,
        'roles' => $roles
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_roles.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 