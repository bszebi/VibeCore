<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Only start a session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Nincs jogosultsága ehhez a művelethez!'
    ]);
    exit;
}

// Ellenőrizzük, hogy kaptunk-e ID-t
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Hiányzó eszköz azonosító!'
    ]);
    exit;
}

try {
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Ellenőrizzük, hogy a felhasználó cégéhez tartozik-e az eszköz
    $stmt = $db->prepare("
        SELECT s.company_id, s.favourite 
        FROM stuffs s 
        INNER JOIN user u ON s.company_id = u.company_id 
        WHERE s.id = ? AND u.id = ?
    ");
    $stmt->execute([$data['id'], $_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Nincs jogosultsága az eszköz módosításához!'
        ]);
        exit;
    }
    
    // Kedvenc státusz megfordítása
    $newFavoriteStatus = !$result['favourite'];
    
    // Frissítjük az eszköz kedvenc státuszát
    $updateStmt = $db->prepare("
        UPDATE stuffs 
        SET favourite = ?
        WHERE id = ?
    ");
    $updateStmt->execute([$newFavoriteStatus ? 1 : 0, $data['id']]);
    
    echo json_encode([
        'success' => true,
        'is_favorite' => $newFavoriteStatus,
        'message' => $newFavoriteStatus ? 'Eszköz hozzáadva a kedvencekhez!' : 'Eszköz eltávolítva a kedvencek közül!'
    ]);
    
} catch (PDOException $e) {
    error_log("Hiba a kedvenc státusz módosításakor: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Hiba történt a kedvenc státusz módosításakor!'
    ]);
} 