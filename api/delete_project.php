<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php-error.log');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    die(json_encode(['error' => 'Csak POST kérés megengedett']));
}

if (!isset($_POST['project_id'])) {
    error_log('Missing project_id in POST data');
    die(json_encode(['error' => 'Hiányzó projekt azonosító']));
}

$project_id = (int)$_POST['project_id'];

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    die(json_encode(['error' => 'Adatbázis kapcsolódási hiba']));
}

// Először lekérjük a projekt képének elérési útját
$stmt = mysqli_prepare($conn, "SELECT picture FROM project WHERE id = ? AND company_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $project_id, $_SESSION['company_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$project = mysqli_fetch_assoc($result);

if (!$project) {
    error_log('Project not found or not authorized: ' . $project_id);
    die(json_encode(['error' => 'A projekt nem található vagy nincs jogosultsága a törléshez']));
}

// Kezdjük a tranzakciót
mysqli_begin_transaction($conn);

try {
    // Először lekérjük a projekthez tartozó munkák azonosítóit
    $stmt = mysqli_prepare($conn, "SELECT id FROM work WHERE project_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Hiba a munkák lekérdezése során: ' . mysqli_error($conn));
    }
    $work_result = mysqli_stmt_get_result($stmt);
    $work_ids = [];
    while ($work_row = mysqli_fetch_assoc($work_result)) {
        $work_ids[] = $work_row['id'];
    }
    
    // Töröljük a kapcsolódó user_to_work bejegyzéseket
    if (!empty($work_ids)) {
        $placeholders = str_repeat('?,', count($work_ids) - 1) . '?';
        $stmt = mysqli_prepare($conn, "DELETE FROM user_to_work WHERE work_id IN ($placeholders)");
        $types = str_repeat('i', count($work_ids));
        $params = array_merge([$types], $work_ids);
        call_user_func_array([$stmt, 'bind_param'], $params);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Hiba a user_to_work bejegyzések törlése során: ' . mysqli_error($conn));
        }
    }
    
    // Töröljük a kapcsolódó munkákat
    $stmt = mysqli_prepare($conn, "DELETE FROM work WHERE project_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Hiba a munkák törlése során: ' . mysqli_error($conn));
    }

    // Ellenőrizzük, hogy létezik-e a logged_event tábla
    $table_exists = false;
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'logged_event'");
    if (mysqli_num_rows($result) > 0) {
        $table_exists = true;
    }

    // Töröljük a kapcsolódó eseményeket, ha a tábla létezik
    if ($table_exists) {
        $stmt = mysqli_prepare($conn, "DELETE FROM logged_event WHERE project_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $project_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Hiba az események törlése során: ' . mysqli_error($conn));
        }
    }
    
    // Ellenőrizzük, hogy létezik-e a work_history tábla
    $table_exists = false;
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'work_history'");
    if (mysqli_num_rows($result) > 0) {
        $table_exists = true;
    }
    
    // Töröljük a kapcsolódó work_history bejegyzéseket, ha a tábla létezik
    if ($table_exists) {
        $stmt = mysqli_prepare($conn, "DELETE FROM work_history WHERE project_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $project_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Hiba a work_history bejegyzések törlése során: ' . mysqli_error($conn));
        }
    }

    // Töröljük magát a projektet
    $stmt = mysqli_prepare($conn, "DELETE FROM project WHERE id = ? AND company_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $project_id, $_SESSION['company_id']);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Hiba a projekt törlése során: ' . mysqli_error($conn));
    }

    // Ha minden sikeres, véglegesítjük a tranzakciót
    mysqli_commit($conn);

    // Ha van kép, töröljük a fájlrendszerből
    if ($project && $project['picture']) {
        $file_path = '../' . $project['picture'];
        if (file_exists($file_path)) {
            if (!unlink($file_path)) {
                error_log('Failed to delete project image: ' . $file_path);
            }
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Hiba esetén visszavonjuk a tranzakciót
    mysqli_rollback($conn);
    error_log('Project deletion error: ' . $e->getMessage());
    echo json_encode(['error' => 'Hiba történt a projekt törlésekor: ' . $e->getMessage()]);
}

mysqli_close($conn); 