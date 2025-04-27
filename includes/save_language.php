<?php
// Hibakezelés bekapcsolása
error_reporting(E_ALL);
ini_set('display_errors', 0); // Kikapcsoljuk a hibák megjelenítését, mert JSON választ küldünk

// Először küldjük el a JSON header-t
header('Content-Type: application/json');

// Session kezelés
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helyes elérési utak beállítása
define('ROOT_PATH', dirname(__DIR__));

try {
    // Ellenőrizzük a POST kérést
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Ellenőrizzük a form adatokat
    if (!isset($_POST['language'])) {
        throw new Exception('Missing language parameter');
    }

    $language = $_POST['language'];
    
    // Ellenőrizzük a választható nyelveket
    $allowedLanguages = ['hu', 'en', 'de', 'sk'];
    if (!in_array($language, $allowedLanguages)) {
        throw new Exception('Invalid language selection');
    }

    // Session ellenőrzés
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('No user logged in');
    }
    
    // Fájlok betöltése
    require_once ROOT_PATH . '/includes/config.php';
    require_once ROOT_PATH . '/includes/Database.php';
    
    try {
        // Adatbázis művelet
        if (!class_exists('DatabaseConnection')) {
            throw new Exception('Database class not found');
        }
        $db = DatabaseConnection::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE user SET language = :language WHERE id = :user_id");
        $success = $stmt->execute([
            ':language' => $language,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        if (!$success) {
            throw new Exception('Database update failed');
        }
        
        // Session frissítése
        $_SESSION['language'] = $language;
        
        // Sikeres válasz
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        throw new Exception('Database connection error: ' . $e->getMessage());
    }

} catch (Exception $e) {
    error_log('Language save error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
    exit;
} 