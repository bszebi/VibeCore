<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

try {
    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['work_id'])) {
        throw new Exception('Work ID is required');
    }

    $work_id = intval($data['work_id']);
    
    // Get database connection
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Check if the work belongs to the user's company
        $check_sql = "SELECT w.id, w.work_start_date 
                     FROM work w
                     WHERE w.id = ? 
                     AND w.company_id = ?";
                     
        $stmt = $db->prepare($check_sql);
        $stmt->execute([$work_id, $_SESSION['company_id']]);
        $work = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$work) {
            throw new Exception('Unauthorized access or invalid work');
        }

        // Check if the work has started
        $now = new DateTime();
        $start_date = new DateTime($work['work_start_date']);
        
        if ($now >= $start_date) {
            // Get all tools associated with this work
            $tools_sql = "SELECT wts.stuffs_id 
                         FROM work_to_stuffs wts
                         WHERE wts.work_id = ?";
            
            $stmt = $db->prepare($tools_sql);
            $stmt->execute([$work_id]);
            $tools = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Get the "Munkában" status ID
            $status_sql = "SELECT id FROM stuff_status WHERE name = 'Munkában'";
            $stmt = $db->prepare($status_sql);
            $stmt->execute();
            $status_id = $stmt->fetchColumn();

            if (!$status_id) {
                throw new Exception('Munkában status not found');
            }

            // Update each tool's status to "Munkában"
            foreach ($tools as $tool_id) {
                // Update the tool status
                $update_sql = "UPDATE stuffs 
                              SET stuff_status_id = ?
                              WHERE id = ? AND company_id = ?";
                
                $stmt = $db->prepare($update_sql);
                $stmt->execute([$status_id, $tool_id, $_SESSION['company_id']]);

                // Add entry to stuff_history
                $history_sql = "INSERT INTO stuff_history 
                               (stuffs_id, work_id, user_id, stuff_status_id, description, created_at)
                               VALUES (?, ?, ?, ?, 'Munka kezdése után automatikusan Munkában státuszba került', NOW())";
                
                $stmt = $db->prepare($history_sql);
                $stmt->execute([$tool_id, $work_id, $_SESSION['user_id'], $status_id]);
            }

            // Commit transaction
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Tool statuses updated to Munkában successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Work has not started yet'
            ]);
        }
        
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