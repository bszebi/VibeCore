<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$country_id = $_GET['country_id'] ?? 0;
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$query = "SELECT id, name FROM counties WHERE country_id = ? ORDER BY name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $country_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$counties = [];
while ($row = mysqli_fetch_assoc($result)) {
    $counties[] = $row;
}

header('Content-Type: application/json');
echo json_encode($counties); 