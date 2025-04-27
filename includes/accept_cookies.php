<?php
// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Disable error reporting to prevent HTML errors from being output
error_reporting(0);
ini_set('display_errors', 0);

// Set content type to JSON
header('Content-Type: application/json');

require_once 'config.php';
require_once 'functions.php';
require_once 'db.php'; // Include the db.php file

// Csak akkor próbáljuk meg kezelni a cookie-t, ha van aktív session és user_id
if (isset($_SESSION['user_id'])) {
    try {
        $db = Database::getInstance()->getConnection();
        
        if (!$db) {
            echo json_encode(['success' => false, 'message' => 'Adatbázis kapcsolat nem sikerült']);
            exit;
        }
        
        // Lekérjük a felhasználó cookie_id-ját
        $stmt = $db->prepare("SELECT cookie_id FROM user WHERE id = :user_id");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['cookie_id']) {
            // Frissítjük a cookie elfogadás státuszát
            $stmt = $db->prepare("UPDATE cookies SET acceptedornot = true WHERE id = :cookie_id");
            $stmt->execute([':cookie_id' => $user['cookie_id']]);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nem található cookie azonosító']);
        }
    } catch (PDOException $e) {
        error_log("Cookie elfogadási hiba: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Adatbázis hiba történt']);
    }
} else {
    // Ha nincs bejelentkezett felhasználó, akkor is küldjünk sikeres választ
    // hogy ne jelenjen meg hibaüzenet kijelentkezéskor
    echo json_encode(['success' => true]);
} 