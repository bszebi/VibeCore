<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Session ellenőrzése és inicializálása
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Adatbázis kapcsolat létrehozása
$pdo = DatabaseConnection::getInstance()->getConnection();

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    echo json_encode(['success' => false, 'message' => 'Nincs jogosultsága!']);
    exit;
}

// Check if it's an AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = $_POST['request_id'] ?? null;
    $response_message = $_POST['response_message'] ?? '';

    if (!$request_id) {
        echo json_encode(['success' => false, 'message' => 'Hiányzó azonosító!']);
        exit;
    }

    switch ($action) {
        case 'accept':
            try {
                $pdo->beginTransaction();

                // Get the leave request details first
                $stmt = $pdo->prepare("SELECT lr.*, u.company_id 
                                     FROM leave_requests lr
                                     JOIN user u ON lr.sender_user_id = u.id
                                     WHERE lr.id = ?");
                $stmt->execute([$request_id]);
                $leave_request = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$leave_request) {
                    throw new Exception('A kérelem nem található.');
                }

                // Update leave request status to accepted
                $stmt = $pdo->prepare("UPDATE leave_requests SET is_accepted = 1, response_message = ?, response_time = NOW() WHERE id = ?");
                $stmt->execute([$response_message, $request_id]);

                // Create calendar event
                $event_title = $leave_request['status_id'] == 4 ? 'Szabadság' : 'Betegállomány';
                $stmt = $pdo->prepare("INSERT INTO calendar_events 
                                     (title, description, start_date, end_date, status_id, user_id, company_id, is_accepted)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([
                    $event_title,
                    $response_message,
                    $leave_request['start_date'],
                    $leave_request['end_date'],
                    $leave_request['status_id'],
                    $leave_request['sender_user_id'],
                    $leave_request['company_id']
                ]);

                // Update user's current status
                $stmt = $pdo->prepare("UPDATE user SET current_status_id = ? WHERE id = ?");
                $stmt->execute([$leave_request['status_id'], $leave_request['sender_user_id']]);

                // Add status history record
                $stmt = $pdo->prepare("INSERT INTO status_history 
                                     (user_id, status_id, status_startdate, status_enddate)
                                     VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $leave_request['sender_user_id'],
                    $leave_request['status_id'],
                    $leave_request['start_date'],
                    $leave_request['end_date']
                ]);

                // Create notification for the requester
                $notification_text = "Az Ön " . ($leave_request['status_id'] == 4 ? "szabadság" : "betegállomány") . " kérelme elfogadásra került.";
                $stmt = $pdo->prepare("INSERT INTO notifications 
                                     (sender_user_id, receiver_user_id, notification_text, notification_time)
                                     VALUES (?, ?, ?, NOW())");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $leave_request['sender_user_id'],
                    $notification_text
                ]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Kérelem elfogadva!']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Hiba történt: ' . $e->getMessage()]);
            }
            break;

        case 'reject':
            try {
                $pdo->beginTransaction();

                // Get the leave request details first
                $stmt = $pdo->prepare("SELECT lr.*, u.company_id 
                                     FROM leave_requests lr
                                     JOIN user u ON lr.sender_user_id = u.id
                                     WHERE lr.id = ?");
                $stmt->execute([$request_id]);
                $leave_request = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$leave_request) {
                    throw new Exception('A kérelem nem található.');
                }

                // Update leave request status to rejected
                $stmt = $pdo->prepare("UPDATE leave_requests SET is_accepted = 0, response_message = ?, response_time = NOW() WHERE id = ?");
                $stmt->execute([$response_message, $request_id]);

                // Create notification for the requester
                $notification_text = "Az Ön " . ($leave_request['status_id'] == 4 ? "szabadság" : "betegállomány") . " kérelme elutasításra került.";
                $stmt = $pdo->prepare("INSERT INTO notifications 
                                     (sender_user_id, receiver_user_id, notification_text, notification_time)
                                     VALUES (?, ?, ?, NOW())");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $leave_request['sender_user_id'],
                    $notification_text
                ]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Kérelem elutasítva!']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Hiba történt: ' . $e->getMessage()]);
            }
            break;

        case 'delete':
            // Delete the notification and related leave request
            $pdo->beginTransaction();
            try {
                // First get the notification_id
                $stmt = $pdo->prepare("SELECT n.id as notification_id 
                                     FROM notifications n 
                                     JOIN leave_requests lr ON lr.sender_user_id = n.sender_user_id 
                                          AND lr.receiver_user_id = n.receiver_user_id
                                          AND lr.notification_time = n.notification_time
                                     WHERE lr.id = ?");
                $stmt->execute([$request_id]);
                $result = $stmt->fetch();

                if ($result) {
                    // Delete the notification
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
                    $stmt->execute([$result['notification_id']]);

                    // Delete the leave request
                    $stmt = $pdo->prepare("DELETE FROM leave_requests WHERE id = ?");
                    $stmt->execute([$request_id]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Értesítés törölve!']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Hiba történt a törlés során!']);
            }
            break;

        case 'get_info':
            try {
                if (!isset($_POST['request_id'])) {
                    throw new Exception('Hiányzó request_id paraméter');
                }
                
                $request_id = $_POST['request_id'];
                error_log("Processing get_info for request_id: " . $request_id);
                
                $stmt = $pdo->prepare("SELECT lr.*, 
                                     CONCAT(u.lastname, ' ', u.firstname) as sender_name,
                                     u.email as user_email,
                                     u.telephone as user_telephone,
                                     r.role_name,
                                     s.name as status_name,
                                     lr.notification_text as description
                                     FROM leave_requests lr
                                     JOIN user u ON lr.sender_user_id = u.id
                                     LEFT JOIN user_to_roles ur ON u.id = ur.user_id
                                     LEFT JOIN roles r ON ur.role_id = r.id
                                     JOIN status s ON lr.status_id = s.id
                                     WHERE lr.id = ?");
                
                $stmt->execute([$request_id]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                error_log("Query result: " . print_r($request, true));
                
                if (!$request) {
                    throw new Exception('A kérelem nem található (ID: ' . $request_id . ')');
                }
                
                $response = [
                    'success' => true,
                    'data' => [
                        'employee' => $request['sender_name'],
                        'role' => $request['role_name'],
                        'email' => $request['user_email'],
                        'telephone' => $request['user_telephone'],
                        'type' => $request['status_name'],
                        'start_date' => $request['start_date'],
                        'end_date' => $request['end_date'],
                        'message' => $request['description'] ?? $request['notification_text'] ?? null,
                        'status' => $request['is_accepted'],
                        'response_message' => $request['response_message']
                    ]
                ];
                
                error_log("Sending response: " . print_r($response, true));
                header('Content-Type: application/json');
                echo json_encode($response);
                
            } catch (Exception $e) {
                error_log("Error in get_info: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Érvénytelen művelet!']);
            break;
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés!']);
exit; 