<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Csak POST kérés megengedett']));
}

if (!isset($_FILES['project_image']) || !isset($_POST['project_id'])) {
    die(json_encode(['error' => 'Hiányzó adatok']));
}

$project_id = (int)$_POST['project_id'];
$file = $_FILES['project_image'];

// Fájl típus ellenőrzése
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($file['type'], $allowed_types)) {
    die(json_encode(['error' => 'Nem megfelelő fájltípus']));
}

// Egyedi fájlnév generálása
$file_name = uniqid() . '_' . $file['name'];
$upload_path = '../uploads/projects/';

// Mappa létrehozása ha nem létezik
if (!file_exists($upload_path)) {
    mkdir($upload_path, 0777, true);
}

$target_file = $upload_path . $file_name;
$relative_path = 'uploads/projects/' . $file_name;

if (move_uploaded_file($file['tmp_name'], $target_file)) {
    // Adatbázis frissítése
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $stmt = mysqli_prepare($conn, "UPDATE project SET picture = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $relative_path, $project_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'picture_path' => $relative_path]);
    } else {
        echo json_encode(['error' => 'Adatbázis hiba']);
    }
    
    mysqli_close($conn);
} else {
    echo json_encode(['error' => 'Hiba a fájl feltöltésekor']);
} 