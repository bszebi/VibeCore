<?php
require_once '../includes/config.php';

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Kapcsolódási hiba: " . mysqli_connect_error());
}

$country_id = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;

$counties_sql = "SELECT * FROM counties WHERE country_id = ? ORDER BY name";
$stmt = mysqli_prepare($conn, $counties_sql);
mysqli_stmt_bind_param($stmt, "i", $country_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$counties = array();
while ($row = mysqli_fetch_assoc($result)) {
    $counties[] = $row;
}

echo json_encode($counties);
mysqli_close($conn); 