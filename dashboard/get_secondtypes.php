<?php
require_once('../includes/config.php');
require_once('../includes/db_config.php');

header('Content-Type: application/json');

if (!isset($_GET['type_id'])) {
    echo json_encode([]);
    exit;
}

$typeId = intval($_GET['type_id']);
$sql = "SELECT * FROM stuff_secondtype WHERE type_id = ? ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $typeId);
$stmt->execute();
$result = $stmt->get_result();

$secondTypes = [];
while ($row = $result->fetch_assoc()) {
    $secondTypes[] = [
        'id' => $row['id'],
        'name' => $row['name']
    ];
}

echo json_encode($secondTypes); 