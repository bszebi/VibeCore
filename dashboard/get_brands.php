<?php
require_once('../includes/config.php');
require_once('../includes/db_config.php');

header('Content-Type: application/json');

if (!isset($_GET['secondtype_id'])) {
    echo json_encode([]);
    exit;
}

$secondTypeId = intval($_GET['secondtype_id']);
$sql = "SELECT DISTINCT b.* FROM stuff_brand b 
        INNER JOIN stuff_model m ON b.id = m.brand_id 
        WHERE m.secondtype_id = ? 
        ORDER BY b.name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $secondTypeId);
$stmt->execute();
$result = $stmt->get_result();

$brands = [];
while ($row = $result->fetch_assoc()) {
    $brands[] = [
        'id' => $row['id'],
        'name' => $row['name']
    ];
}

echo json_encode($brands); 