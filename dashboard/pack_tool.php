<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

try {
    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['tool_id']) || !isset($data['work_id'])) {
        throw new Exception('Tool ID and Work ID are required');
    }

    $tool_id = intval($data['tool_id']);
    $work_id = intval($data['work_id']);
    
    // Get database connection
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Check if the work exists and get project start date
    $stmt = $db->prepare("SELECT w.company_id, w.work_start_date, w.work_end_date, w.project_id, p.project_startdate 
                         FROM work w 
                         LEFT JOIN project p ON w.project_id = p.id 
                         WHERE w.id = ?");
    $stmt->execute([$work_id]);
    $work = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$work) {
        throw new Exception("Work with ID $work_id not found");
    }
    
    // Check if the project start date has been reached
    if ($work['project_id'] && $work['project_startdate']) {
        $current_date = new DateTime();
        $project_start_date = new DateTime($work['project_startdate']);
        
        if ($current_date >= $project_start_date) {
            echo json_encode([
                'success' => false,
                'message' => 'A projekt kezdő dátuma már elérkezett (' . $project_start_date->format('Y-m-d H:i') . '). A bepakolás már nem lehetséges.'
            ]);
            exit;
        }
    }
    
    // Check which table exists
    $tables = [];
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    // Determine which table to use
    $use_work_stuff = in_array('work_stuff', $tables);
    $use_work_to_stuffs = in_array('work_to_stuffs', $tables);
    
    if (!$use_work_stuff && !$use_work_to_stuffs) {
        throw new Exception('Neither work_stuff nor work_to_stuffs table exists');
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Check if the tool belongs to the user's company
        if ($use_work_stuff) {
            $check_sql = "SELECT ws.id 
                         FROM work_stuff ws
                         JOIN work w ON ws.work_id = w.id
                         WHERE ws.id = ? 
                         AND w.id = ? 
                         AND w.company_id = ?";
        } else {
            $check_sql = "SELECT wts.id 
                         FROM work_to_stuffs wts
                         JOIN work w ON wts.work_id = w.id
                         WHERE wts.id = ? 
                         AND w.id = ? 
                         AND w.company_id = ?";
        }
                     
        $stmt = $db->prepare($check_sql);
        $stmt->execute([$tool_id, $work_id, $_SESSION['company_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Unauthorized access or invalid tool');
        }
        
        // Update the tool status
        if ($use_work_stuff) {
            $update_sql = "UPDATE work_stuff 
                          SET is_packed = 1,
                              packed_date = NOW(),
                              packed_by = ?
                          WHERE id = ?";
        } else {
            $update_sql = "UPDATE work_to_stuffs 
                          SET is_packed = 1,
                              packed_date = NOW(),
                              packed_by = ?
                          WHERE id = ?";
        }
                      
        $stmt = $db->prepare($update_sql);
        $stmt->execute([$_SESSION['user_id'], $tool_id]);
        
        // Get the stuff_id from work_to_stuffs
        if (!$use_work_stuff) {
            $stuff_query = "SELECT stuffs_id FROM work_to_stuffs WHERE id = ?";
            $stmt = $db->prepare($stuff_query);
            $stmt->execute([$tool_id]);
            $stuff_result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($stuff_result && isset($stuff_result['stuffs_id'])) {
                // Update the stuff status to "Használatban" (ID: 1)
                $status_update = "UPDATE stuffs SET stuff_status_id = 1 WHERE id = ?";
                $stmt = $db->prepare($status_update);
                $stmt->execute([$stuff_result['stuffs_id']]);
                
                // Add entry to stuff_history
                $history_insert = "INSERT INTO stuff_history (stuffs_id, work_id, user_id, stuff_status_id, description, created_at) 
                                  VALUES (?, ?, ?, 1, 'Eszköz bepakolva a munkához', NOW())";
                $stmt = $db->prepare($history_insert);
                $stmt->execute([$stuff_result['stuffs_id'], $work_id, $_SESSION['user_id']]);
            }
        }
        
        // Commit transaction
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Tool successfully packed',
            'debug' => [
                'table_used' => $use_work_stuff ? 'work_stuff' : 'work_to_stuffs',
                'tool_id' => $tool_id,
                'work_id' => $work_id
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 