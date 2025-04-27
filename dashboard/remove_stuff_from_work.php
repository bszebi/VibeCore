<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// JSON adat beolvasása
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['stuff_id']) || !isset($input['work_id'])) {
    echo json_encode(['success' => false, 'message' => 'Hiányzó paraméterek']);
    exit;
}

$stuff_id = (int)$input['stuff_id'];
$work_id = (int)$input['work_id'];

// Adatbázis kapcsolat ellenőrzése
if (!isset($conn) || !$conn) {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Adatbázis kapcsolódási hiba']);
        exit;
    }
    mysqli_set_charset($conn, "utf8");
}

// Ellenőrizzük, hogy a felhasználó jogosult-e az eszköz eltávolítására
$check_query = "SELECT w.id 
                FROM work w 
                JOIN work_to_stuffs wts ON w.id = wts.work_id
                JOIN stuffs s ON wts.stuffs_id = s.id
                WHERE w.id = ? AND s.id = ? AND w.company_id = ?";

$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "iii", $work_id, $stuff_id, $_SESSION['company_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Nincs jogosultság vagy érvénytelen azonosítók']);
    exit;
}

// Eszköz eltávolítása a munkából
$delete_query = "DELETE FROM work_to_stuffs WHERE work_id = ? AND stuffs_id = ?";
$stmt = mysqli_prepare($conn, $delete_query);
mysqli_stmt_bind_param($stmt, "ii", $work_id, $stuff_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Hiba történt az eltávolítás során']);
}
?> 