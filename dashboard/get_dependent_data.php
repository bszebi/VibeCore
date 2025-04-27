<?php
// Prevent any output before headers
ob_start();

require_once __DIR__ . '/../includes/database.php';

// Clear any previous output
ob_clean();

header('Content-Type: application/json');

try {
    $db = DatabaseConnection::getInstance()->getConnection();
    $action = $_GET['action'] ?? '';
    
    switch($action) {
        case 'secondtypes':
            $type_id = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
            if ($type_id <= 0) {
                throw new Exception('Érvénytelen típus ID');
            }
            
            $stmt = $db->prepare("SELECT id, name FROM stuff_secondtype WHERE stuff_type_id = ?");
            $stmt->execute([$type_id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($result)) {
                echo json_encode([]);
            } else {
                echo json_encode($result);
            }
            break;
            
        case 'brands':
            $secondtype_id = isset($_GET['secondtype_id']) ? (int)$_GET['secondtype_id'] : 0;
            if ($secondtype_id <= 0) {
                throw new Exception('Érvénytelen altípus ID');
            }
            
            $stmt = $db->prepare("SELECT id, name FROM stuff_brand WHERE stuff_secondtype_id = ?");
            $stmt->execute([$secondtype_id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($result)) {
                echo json_encode([]);
            } else {
                echo json_encode($result);
            }
            break;
            
        case 'models':
            $brand_id = isset($_GET['brand_id']) ? (int)$_GET['brand_id'] : 0;
            if ($brand_id <= 0) {
                throw new Exception('Érvénytelen márka ID');
            }
            
            $stmt = $db->prepare("SELECT id, name FROM stuff_model WHERE brand_id = ?");
            $stmt->execute([$brand_id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($result)) {
                echo json_encode([]);
            } else {
                echo json_encode($result);
            }
            break;
            
        case 'years':
            $model_id = isset($_GET['model_id']) ? (int)$_GET['model_id'] : 0;
            if ($model_id <= 0) {
                throw new Exception('Érvénytelen modell ID');
            }
            
            $stmt = $db->prepare("SELECT id, year FROM stuff_manufacture_date WHERE stuff_model_id = ?");
            $stmt->execute([$model_id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($result)) {
                echo json_encode([]);
            } else {
                echo json_encode($result);
            }
            break;
            
        default:
            throw new Exception('Ismeretlen művelet');
    }
} catch(Exception $e) {
    ob_clean(); // Clear any output in case of error
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    ob_end_flush(); // Flush the output buffer
} 