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
    // Ellenőrizzük a GET kérést
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    // Ellenőrizzük a nyelvi paramétert
    $language = isset($_GET['lang']) ? $_GET['lang'] : ($_SESSION['language'] ?? 'hu');
    
    // Ellenőrizzük a választható nyelveket
    $allowedLanguages = ['hu', 'en', 'de', 'sk'];
    if (!in_array($language, $allowedLanguages)) {
        throw new Exception('Invalid language selection');
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
        $stmt = $db->prepare("SELECT translation_key, translation_value FROM translations WHERE language_code = :lang");
        $stmt->execute([':lang' => $language]);
        
        $translations = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $translations[$row['translation_key']] = $row['translation_value'];
        }
        
        // Sikeres válasz
        echo json_encode($translations);
        exit;
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        throw new Exception('Database connection error: ' . $e->getMessage());
    }

} catch (Exception $e) {
    error_log('Translation fetch error: ' . $e->getMessage());
    echo json_encode([
        'error' => $e->getMessage()
    ]);
    exit;
} 