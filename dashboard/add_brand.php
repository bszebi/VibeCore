<?php
// Prevent any output before headers
ob_start();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display_errors to prevent HTML output
ini_set('log_errors', 1);     // Enable error logging

// Log the start of the script
error_log('add_brand.php script started');

try {
    // Include the database file
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    error_log('Database file included successfully');
    
    session_start();
    error_log('Session started');

    // Clear any previous output
    ob_clean();

    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Csak POST kérés megengedett');
    }

    // Log the database connection attempt
    error_log('Attempting to get database connection');
    
    // Check if the DatabaseConnection class exists
    if (!class_exists('DatabaseConnection')) {
        error_log('DatabaseConnection class not found');
        throw new Exception('DatabaseConnection class not found');
    }
    
    // Get database connection
    $db = DatabaseConnection::getInstance()->getConnection();
    error_log('Database connection established');
    
    // Log incoming data
    error_log('POST data: ' . print_r($_POST, true));
    
    // Ellenőrizzük a kötelező mezőket
    if (empty($_POST['secondtype_id']) || empty($_POST['name'])) {
        throw new Exception('Minden mező kitöltése kötelező');
    }

    $secondtype_id = $_POST['secondtype_id'];
    $name = trim($_POST['name']);

    // Log the values we're working with
    error_log('Processing brand: secondtype_id=' . $secondtype_id . ', name=' . $name);

    // Ellenőrizzük, hogy létezik-e már ilyen nevű márka ennél az altípusnál
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM stuff_brand 
        WHERE stuff_secondtype_id = ? AND name = ?
    ");
    $stmt->execute([$secondtype_id, $name]);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Ez a márka már létezik ennél az altípusnál');
    }

    // Márka beszúrása
    $stmt = $db->prepare("
        INSERT INTO stuff_brand (name, stuff_secondtype_id) 
        VALUES (?, ?)
    ");
    $stmt->execute([$name, $secondtype_id]);
    $brand_id = $db->lastInsertId();

    // Clear any output and send JSON response
    ob_clean();
    echo json_encode([
        'success' => true,
        'id' => $brand_id,
        'name' => $name,
        'message' => 'Márka sikeresen hozzáadva'
    ]);

} catch (PDOException $e) {
    error_log('Database error in add_brand.php: ' . $e->getMessage());
    error_log('PDO error code: ' . $e->getCode());
    error_log('PDO error info: ' . print_r($e->errorInfo, true));
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Adatbázis hiba történt: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('Error in add_brand.php: ' . $e->getMessage());
    error_log('Error trace: ' . $e->getTraceAsString());
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    // End output buffering and flush
    ob_end_flush();
} 