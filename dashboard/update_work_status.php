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

    if (!isset($data['action']) || !in_array($data['action'], ['start', 'end', 'update_tools', 'complete'])) {
        throw new Exception('Action must be either "start", "end", "update_tools" or "complete"');
    }

    $work_id = intval($data['work_id']);
    $action = $data['action'];
    
    // Get database connection
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Check if the work belongs to the user's company
        $check_sql = "SELECT w.id, w.work_start_date, w.work_end_date
                     FROM work w
                     WHERE w.id = ? 
                     AND w.company_id = ?";
                     
        $stmt = $db->prepare($check_sql);
        $stmt->execute([$work_id, $_SESSION['company_id']]);
        $work = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$work) {
            throw new Exception('Unauthorized access or invalid work');
        }

        if ($action === 'update_tools') {
            // Get all tools associated with this work
            $tools_sql = "SELECT s.id as stuff_id
                         FROM work_to_stuffs wts
                         INNER JOIN stuffs s ON wts.stuffs_id = s.id
                         WHERE wts.work_id = ?";
            
            $stmt = $db->prepare($tools_sql);
            $stmt->execute([$work_id]);
            $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get the appropriate status IDs
            $available_status_sql = "SELECT id FROM stuff_status WHERE name = 'Raktáron'";
            $stmt = $db->prepare($available_status_sql);
            $stmt->execute();
            $available_status_id = $stmt->fetchColumn();

            if (!$available_status_id) {
                throw new Exception('Raktáron status not found');
            }

            // Update each tool's status
            foreach ($tools as $tool) {
                $update_sql = "UPDATE stuffs 
                              SET stuff_status_id = ?
                              WHERE id = ?";
                
                $stmt = $db->prepare($update_sql);
                $stmt->execute([$available_status_id, $tool['stuff_id']]);
            }

            // Commit transaction
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Tool statuses updated successfully'
            ]);
            exit;
        }

        // Get assigned users if action is start or end
        $users_sql = "SELECT utw.user_id
                     FROM user_to_work utw
                     WHERE utw.work_id = ?";
                     
        $stmt = $db->prepare($users_sql);
        $stmt->execute([$work_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($action === 'start') {
            // Check if the work has started
            $now = new DateTime();
            $start_date = new DateTime($work['work_start_date']);
            
            if ($now >= $start_date) {
                // Get the "Munkában" status ID from status table
                $status_sql = "SELECT id FROM status WHERE name = 'Munkában'";
                $stmt = $db->prepare($status_sql);
                $stmt->execute();
                $status_id = $stmt->fetchColumn();

                if (!$status_id) {
                    throw new Exception('Munkában status not found');
                }

                // Update each user's current status
                foreach ($users as $user) {
                    // Update user's current status
                    $update_sql = "UPDATE user 
                                  SET current_status_id = ?
                                  WHERE id = ?";
                    
                    $stmt = $db->prepare($update_sql);
                    $stmt->execute([$status_id, $user['user_id']]);

                    // Add entry to status_history
                    $history_sql = "INSERT INTO status_history 
                                   (user_id, status_id, status_startdate)
                                   VALUES (?, ?, NOW())";
                    
                    $stmt = $db->prepare($history_sql);
                    $stmt->execute([$user['user_id'], $status_id]);
                }

                // Commit transaction
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Users status updated to Munkában successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Work has not started yet'
                ]);
            }
        } else if ($action === 'end') {
            // Check if the work has ended
            $now = new DateTime();
            $end_date = new DateTime($work['work_end_date']);
            
            if ($now >= $end_date) {
                // Get the "Elérhető" status ID
                $status_sql = "SELECT id FROM status WHERE name = 'Elérhető'";
                $stmt = $db->prepare($status_sql);
                $stmt->execute();
                $status_id = $stmt->fetchColumn();

                if (!$status_id) {
                    throw new Exception('Elérhető status not found');
                }

                // Update each user's current status
                foreach ($users as $user) {
                    // Update user's current status
                    $update_sql = "UPDATE user 
                                  SET current_status_id = ?
                                  WHERE id = ?";
                    
                    $stmt = $db->prepare($update_sql);
                    $stmt->execute([$status_id, $user['user_id']]);

                    // Set end date for the previous status in status_history
                    $update_history_sql = "UPDATE status_history 
                                         SET status_enddate = NOW()
                                         WHERE user_id = ? 
                                         AND status_enddate IS NULL";
                    
                    $stmt = $db->prepare($update_history_sql);
                    $stmt->execute([$user['user_id']]);

                    // Add new entry to status_history
                    $history_sql = "INSERT INTO status_history 
                                   (user_id, status_id, status_startdate)
                                   VALUES (?, ?, NOW())";
                    
                    $stmt = $db->prepare($history_sql);
                    $stmt->execute([$user['user_id'], $status_id]);
                }

                // Commit transaction
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Users status updated to Elérhető successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Work has not ended yet'
                ]);
            }
        } else if ($action === 'complete') {
            // Get the "Befejezve" status ID
            $status_sql = "SELECT id FROM status WHERE name = 'Befejezve'";
            $stmt = $db->prepare($status_sql);
            $stmt->execute();
            $status_id = $stmt->fetchColumn();

            if (!$status_id) {
                throw new Exception('Befejezve status not found');
            }

            // Update each user's current status
            foreach ($users as $user) {
                // Update user's current status
                $update_sql = "UPDATE user 
                              SET current_status_id = ?
                              WHERE id = ?";
                
                $stmt = $db->prepare($update_sql);
                $stmt->execute([$status_id, $user['user_id']]);

                // Set end date for the previous status in status_history
                $update_history_sql = "UPDATE status_history 
                                     SET status_enddate = NOW()
                                     WHERE user_id = ? 
                                     AND status_enddate IS NULL";
                
                $stmt = $db->prepare($update_history_sql);
                $stmt->execute([$user['user_id']]);

                // Add new entry to status_history
                $history_sql = "INSERT INTO status_history 
                               (user_id, status_id, status_startdate)
                               VALUES (?, ?, NOW())";
                
                $stmt = $db->prepare($history_sql);
                $stmt->execute([$user['user_id'], $status_id]);
            }

            // Update work status to Befejezve
            $update_work_sql = "UPDATE work 
                               SET status = 'Befejezve'
                               WHERE id = ?";
            
            $stmt = $db->prepare($update_work_sql);
            $stmt->execute([$work_id]);

            // Commit transaction
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Work completed successfully'
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