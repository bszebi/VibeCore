<?php
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!isset($_GET['work_id'])) {
    echo json_encode(['error' => 'Hiányzó munka azonosító']);
    exit;
}

$work_id = intval($_GET['work_id']);
$db = Database::getInstance()->getConnection();

// Eszközök lekérdezése a munkához
$query = "SELECT 
            st.name as type_name,
            sb.name as brand_name,
            sm.name as model_name,
            smd.year as manufacture_year,
            ss.name as status_name
          FROM work_to_stuffs wts
          JOIN stuffs s ON wts.stuffs_id = s.id
          JOIN stuff_type st ON s.type_id = st.id
          JOIN stuff_brand sb ON s.brand_id = sb.id
          JOIN stuff_model sm ON s.model_id = sm.id
          JOIN stuff_manufacture_date smd ON s.manufacture_date = smd.id
          JOIN stuff_status ss ON s.stuff_status_id = ss.id
          WHERE wts.work_id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param('i', $work_id);
$stmt->execute();
$result = $stmt->get_result();

$equipment = [];
while ($row = $result->fetch_assoc()) {
    $equipment[] = $row;
}

echo json_encode($equipment);
?> 