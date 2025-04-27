<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Set the response header to JSON
header('Content-Type: application/json');

// Check if user is admin (Cég tulajdonos or Manager)
$user_roles = explode(',', $_SESSION['user_role']);
$is_admin = false;
foreach ($user_roles as $role) {
    $role = trim($role);
    if ($role === 'Cég tulajdonos' || $role === 'Manager') {
        $is_admin = true;
        break;
    }
}

if (!$is_admin) {
    echo json_encode([
        'success' => false,
        'message' => 'Nincs jogosultsága a művelet végrehajtásához.'
    ]);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Érvénytelen kérés típus.'
    ]);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Validate required fields
    $required_fields = ['request_id', 'action', 'response_message'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception('Minden mező kitöltése kötelező.');
        }
    }

    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $response_message = $_POST['response_message'];

    // Get leave request details
    $leave_request_sql = "SELECT lr.*, u.company_id 
                         FROM leave_requests lr
                         JOIN user u ON lr.sender_user_id = u.id
                         WHERE lr.id = ? AND lr.is_accepted IS NULL";
    $stmt = $pdo->prepare($leave_request_sql);
    $stmt->execute([$request_id]);
    $leave_request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave_request) {
        throw new Exception('A kérelem nem található vagy már feldolgozásra került.');
    }

    // Update leave request status
    $is_accepted = $action === 'accept' ? 1 : 0;
    $update_request_sql = "UPDATE leave_requests 
                          SET is_accepted = ?, 
                              response_message = ?,
                              response_time = NOW()
                          WHERE id = ?";
    $stmt = $pdo->prepare($update_request_sql);
    $stmt->execute([$is_accepted, $response_message, $request_id]);

    if ($is_accepted) {
        // Create calendar event for accepted requests
        $insert_event_sql = "INSERT INTO calendar_events 
                            (title, description, start_date, end_date, status_id, user_id, company_id, is_accepted)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        
        $event_title = $leave_request['status_id'] == 4 ? 'Szabadság' : 'Betegállomány';
        
        $stmt = $pdo->prepare($insert_event_sql);
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
        $update_user_sql = "UPDATE user SET current_status_id = ? WHERE id = ?";
        $stmt = $pdo->prepare($update_user_sql);
        $stmt->execute([$leave_request['status_id'], $leave_request['sender_user_id']]);

        // Add status history record
        $insert_history_sql = "INSERT INTO status_history 
                             (user_id, status_id, status_startdate, status_enddate)
                             VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($insert_history_sql);
        $stmt->execute([
            $leave_request['sender_user_id'],
            $leave_request['status_id'],
            $leave_request['start_date'],
            $leave_request['end_date']
        ]);
    }

    // Send notification to the requester
    $notification_text = $is_accepted 
        ? "Az Ön " . ($leave_request['status_id'] == 4 ? "szabadság" : "betegállomány") . " kérelme elfogadásra került."
        : "Az Ön " . ($leave_request['status_id'] == 4 ? "szabadság" : "betegállomány") . " kérelme elutasításra került.";
    
    $insert_notification_sql = "INSERT INTO notifications 
                              (sender_user_id, receiver_user_id, notification_text, notification_time)
                              VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
    $stmt = $pdo->prepare($insert_notification_sql);
    $stmt->execute([
        $_SESSION['user_id'],
        $leave_request['sender_user_id'],
        $notification_text
    ]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'A kérelem sikeresen ' . ($is_accepted ? 'elfogadva' : 'elutasítva') . '.'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 