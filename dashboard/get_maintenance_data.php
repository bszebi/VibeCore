<?php
// Hibakezelés beállítása
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Minden kimenetet pufferelünk
ob_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// JSON fejléc beállítása
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        throw new Exception("Kapcsolódási hiba: " . mysqli_connect_error());
    }

    // Karakterkódolás beállítása
    mysqli_set_charset($conn, "utf8mb4");

    // Karbantartások lekérdezése - kizárva a "Kesz" és "Törölve" státuszúakat
    $sql = "SELECT 
        m.id,
        m.stuffs_id,
        sb.name as brand_name,
        sm.name as model_name,
        st.name as type_name,
        m.servis_startdate,
        m.servis_planenddate,
        m.servis_currectenddate,
        ms.name as maintenance_status,
        ms.id as maintenance_status_id,
        u.firstname,
        u.lastname
        FROM maintenance m
        LEFT JOIN stuffs s ON m.stuffs_id = s.id
        LEFT JOIN stuff_type st ON s.type_id = st.id
        LEFT JOIN stuff_brand sb ON s.brand_id = sb.id
        LEFT JOIN stuff_model sm ON s.model_id = sm.id
        LEFT JOIN maintenance_status ms ON m.maintenance_status_id = ms.id
        LEFT JOIN user u ON m.user_id = u.id
        WHERE m.company_id = ?
        AND m.maintenance_status_id != 1
        ORDER BY m.servis_startdate DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Lekérdezés előkészítési hiba: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $_SESSION['company_id']);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Lekérdezési hiba: " . mysqli_error($conn));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $maintenance_data = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Dátumok formázása
        $row['servis_startdate'] = $row['servis_startdate'] ? date('Y-m-d', strtotime($row['servis_startdate'])) : null;
        $row['servis_planenddate'] = $row['servis_planenddate'] ? date('Y-m-d', strtotime($row['servis_planenddate'])) : null;
        $row['servis_currectenddate'] = $row['servis_currectenddate'] ? date('Y-m-d', strtotime($row['servis_currectenddate'])) : null;
        
        $maintenance_data[] = $row;
    }

    // Puffer törlése
    ob_clean();

    // JSON válasz küldése
    echo json_encode($maintenance_data, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Puffer törlése hiba esetén is
    ob_clean();
    
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn) {
        mysqli_close($conn);
    }
}

// Puffer kiürítése és lezárása
ob_end_flush(); 