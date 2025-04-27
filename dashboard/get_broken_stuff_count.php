<?php
// Hibák megjelenítésének kikapcsolása
error_reporting(0);
ini_set('display_errors', 0);

// Győződjünk meg róla, hogy semmi nem került még kiírásra
if (headers_sent()) {
    die(json_encode(['error' => true, 'message' => 'Headers already sent']));
}

// JSON header beállítása
header('Content-Type: application/json');

// Session indítása előtt töröljük a kimeneti puffert
ob_clean();

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Ellenőrizzük a munkamenet létezését
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    die(json_encode(['error' => true, 'message' => 'Nincs bejelentkezve!']));
}

try {
    // Adatbázis kapcsolat létrehozása
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        throw new Exception("Kapcsolódási hiba: " . mysqli_connect_error());
    }

    // Először lekérjük a státusz ID-kat
    $hibas_id = mysqli_query($conn, "SELECT id FROM stuff_status WHERE name = '" . translate('Hibás') . "' LIMIT 1");
    $torott_id = mysqli_query($conn, "SELECT id FROM stuff_status WHERE name = '" . translate('Törött') . "' LIMIT 1");
    $befejezve_id = mysqli_query($conn, "SELECT id FROM maintenance_status WHERE name = '" . translate('Befejezve') . "' LIMIT 1");
    $torolve_id = mysqli_query($conn, "SELECT id FROM maintenance_status WHERE name = '" . translate('Törölve') . "' LIMIT 1");
    
    if (!$hibas_id || !$torott_id || !$befejezve_id || !$torolve_id) {
        throw new Exception("Státusz lekérdezési hiba");
    }

    $hibas_row = mysqli_fetch_assoc($hibas_id);
    $torott_row = mysqli_fetch_assoc($torott_id);
    $befejezve_row = mysqli_fetch_assoc($befejezve_id);
    $torolve_row = mysqli_fetch_assoc($torolve_id);

    if (!$hibas_row || !$torott_row || !$befejezve_row || !$torolve_row) {
        throw new Exception("Státuszok nem találhatók");
    }

    // Hibás eszközök számának lekérdezése
    $sql = "SELECT COUNT(*) as count 
            FROM stuffs s
            WHERE s.company_id = ? 
            AND s.stuff_status_id IN (?, ?)
            AND s.id NOT IN (
                SELECT stuffs_id FROM maintenance 
                WHERE maintenance_status_id NOT IN (?, ?)
            )";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Előkészítési hiba: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "iiiii", 
        $_SESSION['company_id'], 
        $hibas_row['id'], 
        $torott_row['id'],
        $befejezve_row['id'],
        $torolve_row['id']
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Végrehajtási hiba: " . mysqli_stmt_error($stmt));
    }

    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        throw new Exception("Eredmény lekérési hiba");
    }

    $row = mysqli_fetch_assoc($result);
    if (!$row) {
        throw new Exception("Nincs eredmény");
    }

    $count = (int)$row['count'];

    // Visszaadjuk az eredményt JSON formátumban
    echo json_encode(['success' => true, 'count' => $count]);

} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if (isset($stmt)) mysqli_stmt_close($stmt);
    if (isset($conn)) mysqli_close($conn);
} 