<?php
require_once('../includes/config.php');
require_once('../includes/db_config.php');

header('Content-Type: application/json');

if (!isset($_GET['brand_id'])) {
    echo json_encode([]);
    exit;
}

$brandId = intval($_GET['brand_id']);
$sql = "SELECT * FROM stuff_model WHERE brand_id = ? ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $brandId);
$stmt->execute();
$result = $stmt->get_result();

$models = [];
while ($row = $result->fetch_assoc()) {
    $models[] = [
        'id' => $row['id'],
        'name' => $row['name']
    ];
}

echo json_encode($models); 