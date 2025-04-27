<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die(json_encode(['error' => "Kapcsolódási hiba: " . mysqli_connect_error()]));
}

// County ID ellenőrzése
if (!isset($_GET['county_id'])) {
    die(json_encode(['error' => 'Hiányzó county_id paraméter']));
}

$county_id = (int)$_GET['county_id'];

// Kerületek lekérése
$query = "SELECT id, name FROM districts WHERE county_id = ? ORDER BY name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $county_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$districts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $districts[] = $row;
}

// Debug információ
error_log('Districts data for county_id ' . $county_id . ': ' . print_r($districts, true));

echo json_encode($districts);

mysqli_close($conn); 