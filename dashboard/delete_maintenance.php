<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Ellenőrizzük, hogy POST kérés érkezett-e
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés típus!']);
    exit;
}

// Adatok ellenőrzése
if (!isset($_POST['maintenance_id']) || !isset($_POST['stuffs_id'])) {
    echo json_encode(['success' => false, 'message' => 'Hiányzó adatok!']);
    exit;
}

$maintenance_id = intval($_POST['maintenance_id']);
$stuffs_id = intval($_POST['stuffs_id']);

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Adatbázis kapcsolódási hiba!']);
    exit;
}

// Tranzakció kezdése
mysqli_begin_transaction($conn);

try {
    // 1. Lekérjük a "Kiszelektálás alatt" státusz ID-ját
    $status_sql = "SELECT id FROM stuff_status WHERE name = 'Kiszelektálás alatt'";
    $status_result = mysqli_query($conn, $status_sql);
    
    if (!$status_result || mysqli_num_rows($status_result) === 0) {
        throw new Exception('Nem található a "Kiszelektálás alatt" státusz!');
    }
    
    $status_row = mysqli_fetch_assoc($status_result);
    $new_status_id = $status_row['id'];
    
    // 2. Eszköz státuszának frissítése
    $update_stuff_sql = "UPDATE stuffs SET stuff_status_id = ? WHERE id = ?";
    $update_stuff_stmt = mysqli_prepare($conn, $update_stuff_sql);
    mysqli_stmt_bind_param($update_stuff_stmt, "ii", $new_status_id, $stuffs_id);
    
    if (!mysqli_stmt_execute($update_stuff_stmt)) {
        throw new Exception('Hiba az eszköz státuszának frissítésekor!');
    }
    
    // 3. Karbantartás törlése (vagy státuszának módosítása "Törölve"-re)
    $delete_sql = "UPDATE maintenance SET maintenance_status_id = (
        SELECT id FROM maintenance_status WHERE name = 'Törölve'
    ) WHERE id = ?";
    
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "i", $maintenance_id);
    
    if (!mysqli_stmt_execute($delete_stmt)) {
        throw new Exception('Hiba a karbantartás törlésekor!');
    }
    
    // Ha minden sikeres, véglegesítjük a tranzakciót
    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Karbantartás sikeresen törölve!']);
    
} catch (Exception $e) {
    // Hiba esetén visszavonjuk a tranzakciót
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Kapcsolat lezárása
mysqli_close($conn); 