<?php
// Output pufferelés indítása
ob_start();

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Ellenőrizzük a bejelentkezést
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Nincs bejelentkezve!']);
    exit;
}

// Ellenőrizzük, hogy van-e work_id paraméter
if (!isset($_GET['work_id']) || empty($_GET['work_id'])) {
    echo json_encode(['success' => false, 'message' => 'Hiányzó work_id paraméter!']);
    exit;
}

$work_id = $_GET['work_id'];
$db = Database::getInstance()->getConnection();

try {
    // Ellenőrizzük, hogy a munka létezik-e és a felhasználóhoz tartozik-e
    $stmt = $db->prepare("
        SELECT id FROM work 
        WHERE id = ? AND (user_id = ? OR id IN (
            SELECT work_id FROM stuff_history WHERE user_id = ?
        ))
    ");
    $stmt->execute([$work_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'A munka nem található vagy nincs hozzáférése!']);
        exit;
    }
    
    // Lekérjük a munkához tartozó eszközöket
    $stmt = $db->prepare("
        SELECT DISTINCT s.id, s.name, s.qr_code
        FROM stuffs s
        JOIN stuff_history sh ON s.id = sh.stuffs_id
        WHERE sh.work_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$work_id]);
    $stuffs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $stuffs]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Adatbázis hiba: ' . $e->getMessage()]);
}

// Output puffer kiürítése és küldése
ob_end_flush(); 