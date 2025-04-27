<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Kikapcsoljuk a hibák megjelenítését
header('Content-Type: application/json'); // Mindig JSON választ küldünk

// Session indítása
session_start();

try {
    require_once __DIR__ . '/../includes/database.php';

    // Adatbázis kapcsolat létrehozása
    $db = DatabaseConnection::getInstance();
    $conn = $db->getConnection();

    // Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
        throw new Exception('Nincs bejelentkezve');
    }

    // Cég azonosító
    $companyId = $_SESSION['company_id'];

    // Felhasználók számának lekérése
    $userQuery = "SELECT COUNT(*) as user_count FROM user WHERE company_id = ?";
    $stmt = $conn->prepare($userQuery);
    $stmt->execute([$companyId]);
    $userResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $userCount = $userResult['user_count'];

    // Eszközök számának lekérése
    $deviceQuery = "SELECT COUNT(*) as device_count FROM stuffs WHERE company_id = ?";
    $stmt = $conn->prepare($deviceQuery);
    $stmt->execute([$companyId]);
    $deviceResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $deviceCount = $deviceResult['device_count'];

    // Debug információk
    error_log('Company ID: ' . $companyId);
    error_log('User Count: ' . $userCount);
    error_log('Device Count: ' . $deviceCount);

    // Sikeres válasz küldése
    echo json_encode([
        'success' => true,
        'userCount' => $userCount,
        'deviceCount' => $deviceCount
    ]);

} catch (Exception $e) {
    // Hibaüzenet küldése
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 