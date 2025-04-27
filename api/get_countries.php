<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$query = "SELECT id, name FROM countries ORDER BY name";
$result = mysqli_query($conn, $query);

$countries = [];
while ($row = mysqli_fetch_assoc($result)) {
    $countries[] = $row;
}

header('Content-Type: application/json');
echo json_encode($countries); 