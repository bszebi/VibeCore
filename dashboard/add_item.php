<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

header('Content-Type: application/json');

try {
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Debug: Kiíratjuk a beérkező POST adatokat
    error_log("POST data: " . print_r($_POST, true));
    
    // Ellenőrizzük a kötelező mezőket
    $required_fields = ['type_id', 'secondtype_id', 'brand_id', 'model_id', 'manufacture_date', 'qr_code'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("A(z) $field mező kötelező!");
        }
    }
    
    // Lekérjük a "raktáron" státusz ID-ját
    $stmt = $db->prepare("SELECT id FROM stuff_status WHERE name = 'raktáron' LIMIT 1");
    $stmt->execute();
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    $statusId = $status ? $status['id'] : 1;
    
    // Eszköz beszúrása
    $stmt = $db->prepare("
        INSERT INTO stuffs (
            type_id,
            secondtype_id,
            brand_id,
            model_id,
            manufacture_date,
            stuff_status_id,
            qr_code,
            favourite
        ) VALUES (
            :type_id,
            :secondtype_id,
            :brand_id,
            :model_id,
            :manufacture_date,
            :stuff_status_id,
            :qr_code,
            0
        )
    ");
    
    $stmt->execute([
        ':type_id' => $_POST['type_id'],
        ':secondtype_id' => $_POST['secondtype_id'],
        ':brand_id' => $_POST['brand_id'],
        ':model_id' => $_POST['model_id'],
        ':manufacture_date' => $_POST['manufacture_date'],
        ':stuff_status_id' => $statusId,
        ':qr_code' => $_POST['qr_code']
    ]);
    
    $id = $db->lastInsertId();
    
    // Lekérjük az új eszköz összes adatát
    $stmt = $db->prepare("
        SELECT s.*, 
               st.name as type_name,
               sst.name as secondtype_name,
               sb.name as brand_name,
               sm.name as model_name,
               ss.name as status_name,
               smd.year as manufacture_year
        FROM stuffs s
        LEFT JOIN stuff_type st ON s.type_id = st.id
        LEFT JOIN stuff_secondtype sst ON s.secondtype_id = sst.id
        LEFT JOIN stuff_brand sb ON s.brand_id = sb.id
        LEFT JOIN stuff_model sm ON s.model_id = sm.id
        LEFT JOIN stuff_status ss ON s.stuff_status_id = ss.id
        LEFT JOIN stuff_manufacture_date smd ON s.manufacture_date = smd.id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $newItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Eszköz sikeresen hozzáadva!',
        'id' => $id,
        'type_name' => $newItem['type_name'],
        'secondtype_name' => $newItem['secondtype_name'],
        'brand_name' => $newItem['brand_name'],
        'model_name' => $newItem['model_name'],
        'manufacture_year' => $newItem['manufacture_year'],
        'status_name' => $newItem['status_name'],
        'qr_code' => $newItem['qr_code']
    ]);
    
} catch (Exception $e) {
    error_log("Error in add_item.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Hiba történt az eszköz hozzáadásakor!',
        'error' => $e->getMessage()
    ]);
} 