<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
require_once '../includes/api/error_handler.php';

// Start output buffering
ob_start();

// Set JSON content type
header('Content-Type: application/json');

try {
    // Check user roles
    $user_roles = explode(',', $_SESSION['user_role']);
    $is_admin = false;
    $worker_roles = [
        'Vizuáltechnikus',
        'Villanyszerelő',
        'Szinpadtechnikus',
        'Szinpadfedés felelős',
        'Stagehand',
        'Karbantartó',
        'Hangtechnikus',
        'Fénytechnikus'
    ];

    $is_worker = false;
    foreach ($user_roles as $role) {
        $role = trim($role);
        if ($role === 'Cég tulajdonos' || $role === 'Manager') {
            $is_admin = true;
            break;
        }
        if (in_array($role, $worker_roles)) {
            $is_worker = true;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $db = DatabaseConnection::getInstance()->getConnection();
            $db->beginTransaction();

            // Validate required fields
            if (!isset($_POST['user_ids']) || !isset($_POST['status_type']) || 
                !isset($_POST['start_date']) || !isset($_POST['end_date'])) {
                throw new Exception("Hiányzó adatok!");
            }

            $user_ids = $_POST['user_ids'];
            $status_id = $_POST['status_type']; // 4 = szabadság, 5 = betegállomány
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $description = $_POST['description'] ?? '';

            // Ellenőrizzük, hogy van-e már kérelem vagy elfogadott esemény a megadott időszakra
            foreach ($user_ids as $user_id) {
                // Ellenőrizzük a leave_requests táblát
                $check_requests_sql = "SELECT lr.id, s.name as status_name, 
                                            DATE(lr.start_date) as start_date, 
                                            DATE(lr.end_date) as end_date
                                     FROM leave_requests lr
                                     JOIN status s ON lr.status_id = s.id
                                     WHERE lr.sender_user_id = ?
                                     AND lr.is_accepted IS NULL
                                     AND (
                                         (DATE(?) BETWEEN DATE(lr.start_date) AND DATE(lr.end_date))
                                         OR
                                         (DATE(?) BETWEEN DATE(lr.start_date) AND DATE(lr.end_date))
                                         OR
                                         (DATE(lr.start_date) BETWEEN DATE(?) AND DATE(?))
                                     )";
                
                $stmt = $db->prepare($check_requests_sql);
                $stmt->execute([$user_id, $start_date, $end_date, $start_date, $end_date]);
                $existing_request = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing_request) {
                    $error_message = "Már van függőben lévő " . strtolower($existing_request['status_name']) . 
                                   " kérelem erre az időszakra: " . 
                                   date('Y.m.d', strtotime($existing_request['start_date'])) .
                                   ($existing_request['start_date'] !== $existing_request['end_date'] 
                                    ? " - " . date('Y.m.d', strtotime($existing_request['end_date']))
                                    : "");
                    
                    echo json_encode([
                        'success' => false, 
                        'message' => $error_message,
                        'notification' => [
                            'type' => 'error',
                            'title' => 'Hiba!',
                            'message' => $error_message,
                            'position' => 'top-right',
                            'showIcon' => true
                        ]
                    ]);
                    exit;
                }

                // Ellenőrizzük a calendar_events táblát az elfogadott kérelmekhez
                $check_events_sql = "SELECT ce.id, s.name as status_name,
                                          DATE(ce.start_date) as start_date,
                                          DATE(ce.end_date) as end_date
                                   FROM calendar_events ce
                                   JOIN status s ON ce.status_id = s.id
                                   WHERE ce.user_id = ?
                                   AND ce.status_id IN (4, 5)
                                   AND ce.is_accepted = 1
                                   AND (
                                       (DATE(?) BETWEEN DATE(ce.start_date) AND DATE(ce.end_date))
                                       OR
                                       (DATE(?) BETWEEN DATE(ce.start_date) AND DATE(ce.end_date))
                                       OR
                                       (DATE(ce.start_date) BETWEEN DATE(?) AND DATE(?))
                                   )";
                
                $stmt = $db->prepare($check_events_sql);
                $stmt->execute([$user_id, $start_date, $end_date, $start_date, $end_date]);
                $existing_event = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing_event) {
                    throw new Exception("Már van elfogadott " . strtolower($existing_event['status_name']) . 
                                      " erre az időszakra: " . 
                                      date('Y.m.d', strtotime($existing_event['start_date'])) .
                                      ($existing_event['start_date'] !== $existing_event['end_date'] 
                                       ? " - " . date('Y.m.d', strtotime($existing_event['end_date']))
                                       : ""));
                }
            }

            // Get manager/admin user ID for the company
            $manager_sql = "SELECT u.id 
                           FROM user u 
                           JOIN user_to_roles utr ON u.id = utr.user_id 
                           JOIN roles r ON utr.role_id = r.id 
                           WHERE u.company_id = ? 
                           AND (r.role_name = 'Cég tulajdonos' OR r.role_name = 'Manager') 
                           LIMIT 1";
            $stmt = $db->prepare($manager_sql);
            $stmt->execute([$_SESSION['company_id']]);
            $manager = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$manager) {
                throw new Exception("Nem található manager vagy cég tulajdonos!");
            }

            foreach ($user_ids as $user_id) {
                // Check for overlapping events
                $check_sql = "SELECT COUNT(*) as count, u.firstname, u.lastname 
                             FROM calendar_events ce
                             JOIN user u ON ce.user_id = u.id
                             WHERE ce.user_id = ? 
                             AND ((ce.start_date BETWEEN ? AND ?) 
                             OR (ce.end_date BETWEEN ? AND ?))
                             AND ce.status_id IN (4,5)
                             GROUP BY u.id, u.firstname, u.lastname";
                
                $stmt = $db->prepare($check_sql);
                $stmt->execute([$user_id, $start_date, $end_date, $start_date, $end_date]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && $result['count'] > 0) {
                    throw new Exception("A következő személynek már van beütemezett szabadság vagy betegállomány erre az időszakra: " . 
                                      $result['lastname'] . ' ' . $result['firstname']);
                }

                if ($is_admin) {
                    // Admin/Manager directly creates calendar event
                    $title = ($status_id == 4) ? "Szabadság" : "Betegállomány";
                    
                    $event_sql = "INSERT INTO calendar_events 
                                 (title, description, start_date, end_date, status_id, user_id, company_id, is_accepted) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
                    
                    $stmt = $db->prepare($event_sql);
                    $stmt->execute([
                        $title,
                        $description,
                        $start_date,
                        $end_date,
                        $status_id,
                        $user_id,
                        $_SESSION['company_id']
                    ]);

                    // Update user status
                    $update_user_sql = "UPDATE user SET current_status_id = ? WHERE id = ?";
                    $stmt = $db->prepare($update_user_sql);
                    $stmt->execute([$status_id, $user_id]);

                    // Add to status history
                    $history_sql = "INSERT INTO status_history (user_id, status_id, status_startdate, status_enddate) 
                                  VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($history_sql);
                    $stmt->execute([$user_id, $status_id, $start_date, $end_date]);

                    // Send notification to worker
                    $notification_text = "Ön " . 
                                       ($status_id == 4 ? "szabadságra" : "betegállományba") . 
                                       " lett beírva a következő időszakra: " . 
                                       date('Y.m.d', strtotime($start_date)) . 
                                       (($start_date !== $end_date) ? " - " . date('Y.m.d', strtotime($end_date)) : "");

                    $notification_sql = "INSERT INTO notifications 
                                       (sender_user_id, receiver_user_id, notification_text, notification_time) 
                                       VALUES (?, ?, ?, NOW())";
                    
                    $stmt = $db->prepare($notification_sql);
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $user_id,
                        $notification_text
                    ]);
                } else {
                    // Worker creates leave request
                    $leave_request_sql = "INSERT INTO leave_requests 
                                        (sender_user_id, receiver_user_id, start_date, end_date, 
                                         notification_text, status_id) 
                                        VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $notification_text = $description;
                    
                    $stmt = $db->prepare($leave_request_sql);
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $manager['id'],
                        $start_date,
                        $end_date,
                        $notification_text,
                        $status_id
                    ]);

                    // Send notification to manager
                    $notification_sql = "INSERT INTO notifications 
                                       (sender_user_id, receiver_user_id, notification_text, notification_time) 
                                       VALUES (?, ?, ?, NOW())";
                    
                    $notification_message = "Új " . ($status_id == 4 ? "szabadság" : "betegállomány") . 
                                          " kérelem: " . date('Y.m.d', strtotime($start_date)) . 
                                          (($start_date !== $end_date) ? " - " . date('Y.m.d', strtotime($end_date)) : "") .
                                          "\n\nIndoklás:\n" . $description;
                    
                    $stmt = $db->prepare($notification_sql);
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $manager['id'],
                        $notification_message
                    ]);
                }
            }

            $db->commit();
            echo json_encode([
                'success' => true, 
                'message' => $is_admin ? 
                    (($status_id == 4 ? 'A szabadság' : 'A betegállomány') . ' sikeresen rögzítve!') :
                    (($status_id == 4 ? 'A szabadság' : 'A betegállomány') . ' kérelme sikeresen beküldve!')
            ]);

        } catch (Exception $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error in save_calendar_event.php: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés']);
        exit;
    }
} catch (Exception $e) {
    error_log("Error in save_calendar_event.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// End output buffering and return the buffered content
$output = ob_get_clean();
echo $output;