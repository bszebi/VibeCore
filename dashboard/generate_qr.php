<?php
// Prevent any output before our JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Ensure no other output has been sent
if (headers_sent($filename, $linenum)) {
    error_log("Headers already sent in $filename on line $linenum");
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
    exit;
}

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Nincs jogosultsága!']);
    exit;
}

// Ellenőrizzük, hogy kaptunk-e POST adatot
$input = file_get_contents('php://input');
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Hiányzó bemeneti adatok!']);
    exit;
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen JSON formátum!']);
    exit;
}

if (!isset($data['id']) || !is_numeric($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Hiányzó vagy érvénytelen azonosító!']);
    exit;
}

try {
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Lekérjük az eszköz adatait
    $stmt = $db->prepare("
        SELECT s.id, s.company_id, s.type_id, s.secondtype_id, s.brand_id, s.model_id
        FROM stuffs s 
        INNER JOIN user u ON u.company_id = s.company_id 
        WHERE s.id = ? AND u.id = ?
    ");
    $stmt->execute([$data['id'], $_SESSION['user_id']]);
    
    $stuff = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$stuff) {
        echo json_encode(['success' => false, 'message' => 'Nincs jogosultsága az eszköz módosításához!']);
        exit;
    }
    
    // Új QR kód generálása a közös függvénnyel
    $new_qr = generateQRCode(
        $stuff['type_id'],
        $stuff['secondtype_id'],
        $stuff['brand_id'],
        $stuff['model_id']
    );
    
    // QR kód frissítése az adatbázisban
    $update = $db->prepare("UPDATE stuffs SET qr_code = ? WHERE id = ?");
    $update->execute([$new_qr, $data['id']]);
    
    echo json_encode([
        'success' => true,
        'new_qr' => $new_qr,
        'message' => 'QR kód sikeresen frissítve!'
    ]);
    
} catch (PDOException $e) {
    error_log("QR kód generálási hiba: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Hiba történt a QR kód generálása során!'
    ]);
} catch (Exception $e) {
    error_log("QR kód generálási hiba: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Hiba történt a QR kód generálása során!'
    ]);
} 