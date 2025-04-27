<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// JSON fejléc beállítása
header('Content-Type: application/json');

try {
    // Ellenőrizzük a work_id paramétert
    if (!isset($_GET['work_id']) || !is_numeric($_GET['work_id'])) {
        throw new Exception('Érvénytelen munka azonosító');
    }

    $work_id = intval($_GET['work_id']);

    // Adatbázis kapcsolat létrehozása
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        throw new Exception("Adatbázis kapcsolódási hiba: " . mysqli_connect_error());
    }

    mysqli_set_charset($conn, "utf8");

    // Lekérdezés előkészítése
    $query = "SELECT 
        (SELECT COUNT(*) FROM work_to_stuffs WHERE work_id = ?) as total_count,
        (SELECT COUNT(*) FROM work_to_stuffs WHERE work_id = ? AND is_packed = 1) as packed_count,
        (SELECT COUNT(*) FROM work_to_stuffs WHERE work_id = ? AND (is_packed = 0 OR is_packed IS NULL)) as unpacked_count";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception('SQL előkészítési hiba');
    }

    // Paraméterek kötése
    mysqli_stmt_bind_param($stmt, "iii", $work_id, $work_id, $work_id);
    
    // Lekérdezés végrehajtása
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('SQL végrehajtási hiba');
    }

    $result = mysqli_stmt_get_result($stmt);
    $counts = mysqli_fetch_assoc($result);

    // Válasz küldése
    echo json_encode([
        'success' => true,
        'total_count' => (int)$counts['total_count'],
        'packed_count' => (int)$counts['packed_count'],
        'unpacked_count' => (int)$counts['unpacked_count']
    ]);

} catch (Exception $e) {
    // Hiba esetén
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Kapcsolat lezárása
    if (isset($conn) && $conn) {
        mysqli_close($conn);
    }
} 