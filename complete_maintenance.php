<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Ellenőrizzük, hogy POST kérés érkezett-e
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Csak POST kérés megengedett!']);
    exit;
}

// Adatok validálása
$maintenance_id = isset($_POST['maintenance_id']) ? intval($_POST['maintenance_id']) : 0;
$stuffs_id = isset($_POST['stuffs_id']) ? intval($_POST['stuffs_id']) : 0;
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

if (!$maintenance_id || !$stuffs_id || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Hiányzó vagy érvénytelen adatok!']);
    exit;
}

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Adatbázis kapcsolódási hiba!']);
    exit;
}

try {
    // Tranzakció kezdése
    mysqli_begin_transaction($conn);

    // 1. Karbantartás státuszának frissítése "Befejezve"-re
    $completed_status_sql = "SELECT id FROM maintenance_status WHERE name = 'Befejezve'";
    $completed_status_result = mysqli_query($conn, $completed_status_sql);
    $completed_status = mysqli_fetch_assoc($completed_status_result);
    
    if (!$completed_status) {
        throw new Exception('Nem található a "Befejezve" státusz!');
    }

    $update_maintenance_sql = "UPDATE maintenance 
        SET maintenance_status_id = ?, 
            servis_currectenddate = CURRENT_DATE,
            completion_description = ?
        WHERE id = ? AND company_id = ?";
    
    $stmt = mysqli_prepare($conn, $update_maintenance_sql);
    mysqli_stmt_bind_param($stmt, "isii", $completed_status['id'], $description, $maintenance_id, $_SESSION['company_id']);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Hiba a karbantartás frissítésekor!');
    }

    // 2. Eszköz státuszának frissítése "Működőképes"-re
    $working_status_sql = "SELECT id FROM stuff_status WHERE name = 'Működőképes'";
    $working_status_result = mysqli_query($conn, $working_status_sql);
    $working_status = mysqli_fetch_assoc($working_status_result);
    
    if (!$working_status) {
        throw new Exception('Nem található a "Működőképes" státusz!');
    }

    $update_stuff_sql = "UPDATE stuffs 
        SET stuff_status_id = ? 
        WHERE id = ? AND company_id = ?";
    
    $stmt = mysqli_prepare($conn, $update_stuff_sql);
    mysqli_stmt_bind_param($stmt, "iii", $working_status['id'], $stuffs_id, $_SESSION['company_id']);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Hiba az eszköz frissítésekor!');
    }

    // Tranzakció véglegesítése
    mysqli_commit($conn);
    
    echo json_encode(['success' => true, 'message' => 'Karbantartás sikeresen befejezve!']);

} catch (Exception $e) {
    // Hiba esetén visszagörgetjük a tranzakciót
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Kapcsolat lezárása
mysqli_close($conn); 