<?php
require_once 'config.php';
require_once 'db.php';  // Adjuk hozzá a Database osztály betöltését
session_start();

// Tisztítsuk meg a kimeneti puffert
if (ob_get_level()) ob_end_clean();

// Állítsuk be a megfelelő header-eket
header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-cache, must-revalidate");

try {
    // Ellenőrizzük a bejelentkezést
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Nincs bejelentkezve');
    }

    // POST adat ellenőrzése
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Érvénytelen JSON formátum');
    }

    if (!isset($data['user_id'])) {
        throw new Exception('Hiányzó user_id');
    }

    $user_id = (int)$data['user_id'];
    if ($user_id !== (int)$_SESSION['user_id']) {
        throw new Exception('Jogosulatlan hozzáférés');
    }

    try {
        // Közvetlen adatbázis kapcsolat létrehozása
        $db = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
            DB_USER,
            DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );

        $db->beginTransaction();

        // Külső kulcs ellenőrzés kikapcsolása
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Felhasználó törlése
        $stmt = $db->prepare("DELETE FROM user WHERE id = ?");
        $success = $stmt->execute([$user_id]);

        if (!$success) {
            throw new Exception('Nem sikerült törölni a felhasználót');
        }

        // Külső kulcs ellenőrzés visszakapcsolása
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        $db->commit();
        
        // Session törlése
        session_destroy();

        die(json_encode([
            'success' => true,
            'message' => 'Felhasználó sikeresen törölve'
        ]));

    } catch (PDOException $e) {
        if (isset($db)) {
            $db->rollBack();
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        throw new Exception('Adatbázis hiba: ' . $e->getMessage());
    }

} catch (Exception $e) {
    die(json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]));
}
?> 