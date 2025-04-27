<?php
// Adatbázis kapcsolat beállításai
define('DB_HOST', 'localhost');
define('DB_NAME', 'vizsgaremek');
define('DB_USER', 'root');
define('DB_PASS', '');
define('SITE_NAME', 'Vizsga Oldal');

// Session élettartam beállítása (30 perc)
ini_set('session.gc_maxlifetime', 1800); // 30 perc

// Csak akkor állítjuk be a cookie paramétereket, ha a session még nem aktív
if (session_status() === PHP_SESSION_NONE) {
    // Cookie élettartam beállítása (30 perc)
    session_set_cookie_params([
        'lifetime' => 1800,
        'path' => '/Vizsga_oldal',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Session indítása
    session_start();
    
    // Session alapértelmezett beállítások
    $_SESSION['last_activity'] = time();
    $_SESSION['created'] = time();
}

// Debug session information
error_log('Session ID: ' . session_id());
error_log('Session Cookie Path: ' . session_get_cookie_params()['path']);
error_log('Session Status: ' . session_status());
error_log('Session Content: ' . print_r($_SESSION, true));

// Időzóna beállítása
date_default_timezone_set('Europe/Budapest');

// Hibakezelés beállítása
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Base URL beállítása
$base_url = '/vizsga_oldal';  // vagy az aktuális projekt mappája 

// Add this line before any translation function calls
require_once __DIR__ . '/translation.php';

// Adatbázis kapcsolat beállításai
$db_host = "localhost";
$db_user = "root";
$db_password = "";
$db_name = "vizsgaremek";

// Karakterkódolás beállítása
mb_internal_encoding("UTF-8");

// SMTP beállítások
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'kurinczjozsef@gmail.com');
define('SMTP_PASSWORD', 'yxsyntnvrwvezode');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_FROM_EMAIL', 'kurinczjozsef@gmail.com');
define('SMTP_FROM_NAME', 'Céges meghívó');

// Adatbázis kapcsolat létrehozása
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Adatbázis kapcsolódási hiba']);
    exit;
}

// Ensure proper content type
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
?> 