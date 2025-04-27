<?php
// Hibakezelés beállítása
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Minden kimenetet pufferelünk
ob_start();

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// JSON fejléc beállítása
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Adatbázis kapcsolat létrehozása
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        throw new Exception("Kapcsolódási hiba: " . mysqli_connect_error());
    }

    // Karakterkódolás beállítása
    mysqli_set_charset($conn, "utf8mb4");

    // Lekérdezés a hibás eszközökről
    $sql = "SELECT DISTINCT s.*, ss.name as status_name, 
            sb.name as brand_name,
            sm.name as model_name,
            st.name as type_name,
            (SELECT description FROM stuff_history 
             WHERE stuffs_id = s.id 
             AND (stuff_status_id IN (SELECT id FROM stuff_status WHERE name IN ('" . translate('Hibás') . "', '" . translate('Törött') . "'))
                  OR EXISTS (SELECT 1 FROM stuff_status WHERE id = s.stuff_status_id AND name IN ('" . translate('Hibás') . "', '" . translate('Törött') . "')))
             ORDER BY created_at DESC LIMIT 1) as last_report
            FROM stuffs s
            LEFT JOIN stuff_status ss ON s.stuff_status_id = ss.id
            LEFT JOIN stuff_brand sb ON s.brand_id = sb.id
            LEFT JOIN stuff_model sm ON s.model_id = sm.id
            LEFT JOIN stuff_type st ON s.type_id = st.id
            WHERE s.company_id = ?
            AND (ss.name IN ('" . translate('Hibás') . "', '" . translate('Törött') . "') 
                 OR EXISTS (
                     SELECT 1 FROM stuff_history sh 
                     WHERE sh.stuffs_id = s.id 
                     AND sh.stuff_status_id IN (
                         SELECT id FROM stuff_status 
                         WHERE name IN ('" . translate('Hibás') . "', '" . translate('Törött') . "')
                     )
                 ))
            AND s.id NOT IN (
                SELECT stuffs_id 
                FROM maintenance m 
                WHERE m.maintenance_status_id IN (
                    SELECT id 
                    FROM maintenance_status 
                    WHERE name NOT IN ('" . translate('Befejezve') . "', '" . translate('Törölve') . "')
                )
            )
            AND s.id NOT IN (
                SELECT id FROM stuffs 
                WHERE stuff_status_id IN (
                    SELECT id FROM stuff_status WHERE name = '" . translate('Kiszelektálás alatt') . "'
                )
            )";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Prepare statement error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $_SESSION['company_id']);

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $broken_items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $broken_items[] = $row;
    }

    // Debug információk
    error_log("Debug - Hibás eszközök lekérdezése:");
    error_log("Company ID: " . $_SESSION['company_id']);
    error_log("SQL: " . str_replace('?', $_SESSION['company_id'], $sql));
    error_log("Találatok száma: " . count($broken_items));
    error_log("Eredmény: " . json_encode($broken_items));

    // Puffer törlése
    ob_clean();

    // Ellenőrizzük a stuff_status tábla tartalmát
    $status_check = mysqli_query($conn, "SELECT id, name FROM stuff_status");
    $statuses = [];
    while ($row = mysqli_fetch_assoc($status_check)) {
        $statuses[] = $row;
    }
    error_log("Elérhető státuszok: " . json_encode($statuses));

    // Ellenőrizzük a maintenance_status tábla tartalmát
    $maint_status_check = mysqli_query($conn, "SELECT id, name FROM maintenance_status");
    $maint_statuses = [];
    while ($row = mysqli_fetch_assoc($maint_status_check)) {
        $maint_statuses[] = $row;
    }
    error_log("Karbantartási státuszok: " . json_encode($maint_statuses));

    // JSON válasz küldése
    echo json_encode([
        'success' => true,
        'data' => $broken_items
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Puffer törlése hiba esetén is
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn) {
        mysqli_close($conn);
    }
}

// Puffer kiürítése és lezárása
ob_end_flush(); 