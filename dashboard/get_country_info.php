<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die(json_encode(['error' => "Kapcsolódási hiba: " . mysqli_connect_error()]));
}

// Country ID ellenőrzése
if (!isset($_GET['country_id'])) {
    die(json_encode(['error' => 'Hiányzó country_id paraméter']));
}

$country_id = (int)$_GET['country_id'];

// Ország adatainak lekérése
$query = "SELECT id, name, has_districts FROM countries WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $country_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($country = mysqli_fetch_assoc($result)) {
    // Debug információ
    error_log('Country data: ' . print_r($country, true));
    echo json_encode($country);
} else {
    echo json_encode(['error' => 'Ország nem található']);
}

mysqli_close($conn); 