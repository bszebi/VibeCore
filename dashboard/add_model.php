<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Only start a session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure no output before headers
ob_start();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Csak POST kérés megengedett');
    }

    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Ellenőrizzük a kötelező mezőket
    if (empty($_POST['brand_id']) || empty($_POST['name'])) {
        throw new Exception('Minden mező kitöltése kötelező');
    }

    $brand_id = $_POST['brand_id'];
    $name = trim($_POST['name']);

    // Ellenőrizzük, hogy létezik-e már ilyen nevű modell ennél a márkánál
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM stuff_model 
        WHERE brand_id = ? AND name = ?
    ");
    $stmt->execute([$brand_id, $name]);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Ez a modell már létezik ennél a márkánál');
    }

    // Modell beszúrása
    $stmt = $db->prepare("
        INSERT INTO stuff_model (name, brand_id) 
        VALUES (?, ?)
    ");
    $stmt->execute([$name, $brand_id]);
    $model_id = $db->lastInsertId();

    // Clear any output buffers
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'id' => $model_id,
        'name' => $name,
        'message' => 'Modell sikeresen hozzáadva'
    ]);

} catch (Exception $e) {
    // Clear any output buffers
    ob_clean();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 

// End output buffering and flush
ob_end_flush(); 