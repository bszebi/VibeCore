<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Only start a session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure no output before headers
ob_start();

header('Content-Type: application/json');

// Debug: Log session content
error_log('Session content: ' . print_r($_SESSION, true));

// Check if it's a POST request first
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST requests are allowed']);
    exit;
}

// Check login and get company_id from database
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'You must be logged in to perform this action.']);
    exit;
}

try {
    // Debug: Log POST data
    error_log('POST data: ' . print_r($_POST, true));
    
    // Validate required fields
    $required_fields = ['type_id', 'secondtype_id', 'brand_id', 'model_id', 'manufacture_date'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "All fields are required! Missing: " . $field]);
            exit;
        }
    }

    // Get database connection
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Get user's company_id
    $stmt = $db->prepare("SELECT company_id FROM user WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company_id = $stmt->fetchColumn();
    
    if (!$company_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => "You don't have permission to add equipment."]);
        exit;
    }

    // Check subscription plan device limit
    $stmt = $db->prepare("
        SELECT 
            sp.description as plan_description,
            sm.modification_reason,
            (SELECT COUNT(*) FROM stuffs WHERE company_id = ?) as current_device_count
        FROM subscriptions s
        JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
        LEFT JOIN subscription_modifications sm ON s.id = sm.subscription_id
        WHERE s.company_id = ? 
        AND s.subscription_status_id = 1
        ORDER BY sm.modification_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$company_id, $company_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => "No active subscription found."]);
        exit;
    }

    // Extract device limit from plan description or modification
    $device_limit = 0;
    
    // Először ellenőrizzük, van-e módosítás
    if ($result['modification_reason']) {
        // Módosításból próbáljuk kinyerni az eszközök számát
        if (preg_match('/(\d+)\s+eszköz/', $result['modification_reason'], $matches)) {
            $device_limit = (int)$matches[1];
        }
    }
    
    // Ha nincs módosítás vagy nem sikerült kinyerni belőle az értéket,
    // akkor használjuk az alap csomag leírásából
    if ($device_limit === 0) {
        preg_match('/(\d+)\s+eszköz/', $result['plan_description'], $matches);
        $device_limit = isset($matches[1]) ? (int)$matches[1] : 0;
    }
    
    $current_device_count = (int)$result['current_device_count'];
    $mennyiseg = isset($_POST['mennyiseg']) ? intval($_POST['mennyiseg']) : 1;

    if (($current_device_count + $mennyiseg) > $device_limit) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'error' => "Device limit exceeded. Your plan allows {$device_limit} devices, you currently have {$current_device_count} devices."
        ]);
        exit;
    }

    // Validate manufacture date - check if it's a valid ID
    $manufacture_date = $_POST['manufacture_date'];
    if (!is_numeric($manufacture_date)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Invalid manufacture date format. Please select a valid year."]);
        exit;
    }
    
    // Check if the manufacture date exists in the database
    $stmt = $db->prepare("SELECT id FROM stuff_manufacture_date WHERE id = ?");
    $stmt->execute([$manufacture_date]);
    if (!$stmt->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Selected manufacture date does not exist in the database."]);
        exit;
    }

    // Get "in stock" status ID
    $stmt = $db->prepare("SELECT id FROM stuff_status WHERE name = 'raktáron' LIMIT 1");
    $stmt->execute();
    $statusId = $stmt->fetchColumn();
    
    if (!$statusId) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "System error: Could not determine equipment status."]);
        exit;
    }

    // Start transaction
    $db->beginTransaction();
    $inserted_ids = [];

    try {
        for ($i = 0; $i < $mennyiseg; $i++) {
            // Generate QR code
            $qr_code = generateQRCode(
                $_POST['type_id'],
                $_POST['secondtype_id'],
                $_POST['brand_id'],
                $_POST['model_id']
            );
            
            // Insert equipment
            $stmt = $db->prepare("
                INSERT INTO stuffs (
                    type_id, secondtype_id, brand_id, model_id,
                    manufacture_date, stuff_status_id, qr_code,
                    company_id, favourite
                ) VALUES (
                    :type_id, :secondtype_id, :brand_id, :model_id,
                    :manufacture_date, :stuff_status_id, :qr_code,
                    :company_id, 0
                )
            ");
            
            $stmt->execute([
                ':type_id' => $_POST['type_id'],
                ':secondtype_id' => $_POST['secondtype_id'],
                ':brand_id' => $_POST['brand_id'],
                ':model_id' => $_POST['model_id'],
                ':manufacture_date' => $manufacture_date,
                ':stuff_status_id' => $statusId,
                ':qr_code' => $qr_code,
                ':company_id' => $company_id
            ]);
            
            $inserted_ids[] = $db->lastInsertId();
        }
        
        // Commit transaction
        $db->commit();
        
        // Get all equipment for updated list
        $stmt = $db->prepare("
            SELECT 
                s.id,
                st.name as type_name,
                sst.name as secondtype_name,
                sb.name as brand_name,
                sm.name as model_name,
                ss.name as status_name,
                smd.year as manufacture_year,
                s.qr_code,
                s.favourite
            FROM stuffs s
            LEFT JOIN stuff_type st ON s.type_id = st.id
            LEFT JOIN stuff_secondtype sst ON s.secondtype_id = sst.id
            LEFT JOIN stuff_brand sb ON s.brand_id = sb.id
            LEFT JOIN stuff_model sm ON s.model_id = sm.id
            LEFT JOIN stuff_status ss ON s.stuff_status_id = ss.id
            LEFT JOIN stuff_manufacture_date smd ON s.manufacture_date = smd.id
            WHERE s.company_id = ?
            ORDER BY s.id DESC
        ");
        $stmt->execute([$company_id]);
        $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Clear any output buffers
        ob_clean();
        
        // Prepare response
        $response = [
            'success' => true,
            'message' => "Successfully added " . count($inserted_ids) . " item(s)!",
            'inserted_count' => count($inserted_ids),
            'inserted_ids' => $inserted_ids,
            'allItems' => $allItems
        ];

        echo json_encode($response);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    // Clear any output buffers
    ob_clean();
    
    error_log('Database error in add_stuff.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'A database error occurred. Please try again later.'
    ]);
    exit;
} catch (Exception $e) {
    // Clear any output buffers
    ob_clean();
    
    error_log('Error in add_stuff.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}

// End output buffering and flush
ob_end_flush(); 