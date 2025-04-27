<?php
// Prevent any output before headers
ob_start();

// Only start a session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/database.php';

// Clear any previous output
ob_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nincs bejelentkezve!']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Érvénytelen kérés!']);
    exit;
}

try {
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Ellenőrizzük, hogy minden szükséges adat megvan-e
    if (!isset($_POST['type_id'], $_POST['secondtype_id'], $_POST['brand_id'], $_POST['model_id'], $_POST['year'])) {
        throw new Exception('Hiányzó adatok!');
    }
    
    $model_id = intval($_POST['model_id']);
    $year = intval($_POST['year']);
    
    // Ellenőrizzük, hogy az év érvényes-e és számként érkezett
    if (!is_numeric($_POST['year'])) {
        throw new Exception('Az évnek számnak kell lennie!');
    }
    
    $currentYear = date('Y');
    if ($year < 1980 || $year > $currentYear) {
        throw new Exception("Az évnek 1980 és {$currentYear} között kell lennie!");
    }
    
    // Ellenőrizzük, hogy létezik-e már ez az év ehhez a modellhez
    $stmt = $db->prepare("SELECT id FROM stuff_manufacture_date WHERE year = ? AND stuff_model_id = ?");
    $stmt->execute([$year, $model_id]);
    $existingYear = $stmt->fetch();
    
    if ($existingYear) {
        $yearId = $existingYear['id'];
    } else {
        // Ha nem létezik, akkor beszúrjuk az új évet a modell ID-vel együtt
        $stmt = $db->prepare("INSERT INTO stuff_manufacture_date (year, stuff_model_id) VALUES (?, ?)");
        $stmt->execute([$year, $model_id]);
        $yearId = $db->lastInsertId();
    }
    
    // Válasz összeállítása
    echo json_encode([
        'success' => true,
        'id' => $yearId,
        'display' => $year,
        'message' => 'Gyártási év sikeresen hozzáadva'
    ]);
    
} catch (Exception $e) {
    error_log('Error in add_year.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    ob_end_flush(); // Flush the output buffer
} 