<?php
// Minden output pufferelés kezdése
ob_start();

// Hibák teljes kikapcsolása
error_reporting(0);
ini_set('display_errors', 0);

// JSON fejléc beállítása
header('Content-Type: application/json');

// Session indítása ha még nem fut
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Konfig és auth fájlok betöltése
    if (!file_exists('../includes/config.php') || !file_exists('../includes/auth_check.php')) {
        throw new Exception('Hiányzó rendszerfájlok');
    }
    
    require_once '../includes/config.php';
    require_once '../includes/auth_check.php';

    // POST ellenőrzés
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Csak POST kérés megengedett');
    }

    // JSON adat beolvasása
    $json = file_get_contents('php://input');
    if (!$json) {
        throw new Exception('Hiányzó JSON adat');
    }

    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Érvénytelen JSON formátum');
    }

    // Paraméterek ellenőrzése
    if (!isset($data['stuff_id'], $data['work_id'], $data['action'])) {
        throw new Exception('Hiányzó paraméterek');
    }

    // Session ellenőrzése
    if (!isset($_SESSION['company_id'])) {
        throw new Exception('Nincs bejelentkezve');
    }

    // Adatbázis kapcsolat
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        throw new Exception('Adatbázis kapcsolódási hiba');
    }
    mysqli_set_charset($conn, "utf8");

    // Adatok tisztítása
    $work_id = intval($data['work_id']);
    $stuff_id = intval($data['stuff_id']);
    $action = mysqli_real_escape_string($conn, $data['action']);
    $company_id = intval($_SESSION['company_id']);

    // Jogosultság ellenőrzése
    $check_query = "SELECT id FROM work WHERE id = ? AND company_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $check_query);
    if (!$stmt) {
        throw new Exception('SQL előkészítési hiba');
    }

    mysqli_stmt_bind_param($stmt, "ii", $work_id, $company_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('SQL végrehajtási hiba');
    }

    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) === 0) {
        throw new Exception('Nincs jogosultság a művelethez');
    }

    // Művelet végrehajtása
    if ($action === 'assign') {
        // Ellenőrizzük, hogy az eszköz nincs-e már hozzárendelve
        $check_query = "SELECT id FROM work_to_stuffs WHERE stuffs_id = ? AND work_id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "ii", $stuff_id, $work_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $response = array('success' => false, 'message' => 'Az eszköz már hozzá van rendelve ehhez a munkához');
            echo json_encode($response);
            exit;
        }

        // Beszúrjuk az új kapcsolatot
        $insert_query = "INSERT INTO work_to_stuffs (work_id, stuffs_id, is_packed, packed_date, packed_by) VALUES (?, ?, 0, NULL, NULL)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "ii", $work_id, $stuff_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $response = array('success' => true);
            echo json_encode($response);
        } else {
            $response = array('success' => false, 'message' => 'Hiba történt a hozzárendelés során');
            echo json_encode($response);
        }

    } elseif ($action === 'unassign') {
        // Törlés
        $delete_sql = "DELETE FROM work_to_stuffs WHERE work_id = ? AND stuffs_id = ?";
        $stmt = mysqli_prepare($conn, $delete_sql);
        if (!$stmt) {
            throw new Exception('SQL előkészítési hiba');
        }

        mysqli_stmt_bind_param($stmt, "ii", $work_id, $stuff_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Hiba az eltávolítás során');
        }

    } else {
        throw new Exception('Érvénytelen művelet');
    }

} catch (Exception $e) {
    // Hiba válasz
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
} finally {
    // Kapcsolat lezárása
    if (isset($conn) && $conn) {
        mysqli_close($conn);
    }
    
    // Puffer ürítése
    ob_clean();
    
    // JSON válasz küldése
    echo json_encode($response);
    exit;
}
?> 