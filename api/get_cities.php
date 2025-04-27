<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$county_id = $_GET['county_id'] ?? 0;
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$query = "SELECT id, name FROM cities WHERE county_id = ? ORDER BY name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $county_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$cities = [];
while ($row = mysqli_fetch_assoc($result)) {
    $cities[] = $row;
}

header('Content-Type: application/json');
echo json_encode($cities); 