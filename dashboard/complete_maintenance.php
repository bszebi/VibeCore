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

// Függvény a hibák JSON formátumban való visszaadásához
function returnError($message) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Függvény a sikeres válasz JSON formátumban való visszaadásához
function returnSuccess($message = 'Sikeres művelet') {
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Session ellenőrzése
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
        returnError("Nincs bejelentkezve!");
    }

    // POST adatok ellenőrzése
    if (!isset($_POST['maintenance_id']) || !isset($_POST['stuffs_id']) || !isset($_POST['description'])) {
        returnError("Hiányzó adatok!");
    }

    // Adatok tisztítása és validálása
    $maintenance_id = filter_var($_POST['maintenance_id'], FILTER_VALIDATE_INT);
    $stuffs_id = filter_var($_POST['stuffs_id'], FILTER_VALIDATE_INT);
    $description = trim($_POST['description']);

    if ($maintenance_id === false || $stuffs_id === false || empty($description)) {
        returnError("Érvénytelen adatok!");
    }

    // Adatbázis kapcsolat létrehozása
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        returnError("Adatbázis kapcsolódási hiba: " . mysqli_connect_error());
    }

    // Karakterkódolás beállítása
    mysqli_set_charset($conn, "utf8mb4");

    // Tranzakció kezdése
    mysqli_begin_transaction($conn);

    try {
        // Debug: Státuszok lekérdezése és kiírása
        $debug_sql = "SELECT id, name FROM maintenance_status";
        $debug_result = mysqli_query($conn, $debug_sql);
        $available_statuses = [];
        while ($row = mysqli_fetch_assoc($debug_result)) {
            $available_statuses[] = $row;
        }
        error_log("Available maintenance statuses: " . print_r($available_statuses, true));

        // 1. "Kesz" státusz ID beállítása
        $completed_status_id = 1; // Közvetlenül az 1-es ID használata

        // 2. Raktáron státusz kezelése
        $stuff_status_sql = "SELECT id FROM stuff_status WHERE name = 'Raktáron' LIMIT 1";
        $stuff_status_result = mysqli_query($conn, $stuff_status_sql);
        if (!$stuff_status_result || mysqli_num_rows($stuff_status_result) === 0) {
            throw new Exception("A 'Raktáron' státusz nem található");
        }
        $in_stock_status_id = mysqli_fetch_assoc($stuff_status_result)['id'];

        // 3. Karbantartás ellenőrzése
        $check_sql = "SELECT id FROM maintenance WHERE id = ? AND company_id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $check_sql);
        if (!$stmt) {
            throw new Exception("Hiba a karbantartás ellenőrzésekor");
        }
        mysqli_stmt_bind_param($stmt, "ii", $maintenance_id, $_SESSION['company_id']);
        mysqli_stmt_execute($stmt);
        $check_result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($check_result) === 0) {
            throw new Exception("A karbantartás nem található");
        }

        // 4. Karbantartás befejezése
        $complete_sql = "UPDATE maintenance 
                        SET maintenance_status_id = ?,
                            servis_currectenddate = NOW(),
                            description = ?
                        WHERE id = ? AND company_id = ?";
        
        $stmt = mysqli_prepare($conn, $complete_sql);
        if (!$stmt) {
            throw new Exception("Hiba a karbantartás frissítésekor");
        }
        
        mysqli_stmt_bind_param($stmt, "isii", $completed_status_id, $description, $maintenance_id, $_SESSION['company_id']);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Hiba a karbantartás befejezésekor");
        }

        // 5. Eszköz státuszának módosítása - egyszerűsített verzió
        $update_stuff_sql = "UPDATE stuffs 
                            SET stuff_status_id = ?
                            WHERE id = ? 
                            AND company_id = ?";
        
        $stmt = mysqli_prepare($conn, $update_stuff_sql);
        if (!$stmt) {
            throw new Exception("Hiba az eszköz frissítésekor");
        }
        
        mysqli_stmt_bind_param($stmt, "iii", $in_stock_status_id, $stuffs_id, $_SESSION['company_id']);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Hiba az eszköz státuszának módosításakor");
        }

        // Ellenőrizzük, hogy történt-e módosítás
        if (mysqli_affected_rows($conn) === 0) {
            throw new Exception("Az eszköz státusza nem lett módosítva");
        }

        // 6. Log bejegyzés
        $log_sql = "INSERT INTO maintenance_logs (maintenance_id, user_id, old_status_id, new_status_id, description) 
                    SELECT ?, ?, maintenance_status_id, ?, ? 
                    FROM maintenance 
                    WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $log_sql);
        if (!$stmt) {
            throw new Exception("Hiba a log létrehozásakor");
        }
        
        mysqli_stmt_bind_param($stmt, "iiisi", $maintenance_id, $_SESSION['user_id'], $completed_status_id, $description, $maintenance_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Hiba a log mentésekor");
        }

        // Tranzakció véglegesítése
        mysqli_commit($conn);
        
        // Sikeres válasz
        returnSuccess('A karbantartás sikeresen befejezve!');

    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }

} catch (Exception $e) {
    returnError($e->getMessage());
} finally {
    // Kapcsolat lezárása
    if (isset($conn) && $conn) {
        mysqli_close($conn);
    }
}

// Puffer kiürítése és lezárása
ob_end_flush();
?> 