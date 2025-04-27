<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => "Kapcsolódási hiba: " . mysqli_connect_error()]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mysqli_begin_transaction($conn);
    try {
        // Adatok validálása
        if (!isset($_POST['event_type']) || !isset($_POST['start_date']) || !isset($_POST['end_date'])) {
            throw new Exception("Hiányzó adatok!");
        }

        $event_type = $_POST['event_type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $description = $_POST['description'] ?? '';

        // Státusz ID lekérése (szabadság/betegállomány)
        $status_query = "SELECT id FROM status WHERE name = ?";
        $status_name = $event_type === 'vacation' ? 'Szabadság' : 'Betegállomány';
        $stmt = mysqli_prepare($conn, $status_query);
        if (!$stmt) {
            throw new Exception("Hiba a státusz lekérdezés során: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "s", $status_name);
        mysqli_stmt_execute($stmt);
        $status_result = mysqli_stmt_get_result($stmt);
        $status = mysqli_fetch_assoc($status_result);
        
        if (!$status) {
            throw new Exception("Érvénytelen státusz!");
        }

        // Cég tulajdonos vagy manager keresése
        $manager_query = "SELECT u.id 
                         FROM user u 
                         JOIN user_to_roles utr ON u.id = utr.user_id 
                         JOIN roles r ON utr.role_id = r.id 
                         WHERE u.company_id = (SELECT company_id FROM user WHERE id = ?)
                         AND (r.role_name = 'Cég tulajdonos' OR r.role_name = 'Manager') 
                         LIMIT 1";
        $stmt = mysqli_prepare($conn, $manager_query);
        if (!$stmt) {
            throw new Exception("Hiba a manager lekérdezés során: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $manager_result = mysqli_stmt_get_result($stmt);
        $manager = mysqli_fetch_assoc($manager_result);

        if (!$manager) {
            throw new Exception("Nem található cég tulajdonos vagy manager!");
        }

        // Leave request létrehozása
        $leave_request_sql = "INSERT INTO leave_requests 
                            (sender_user_id, receiver_user_id, start_date, end_date, 
                             notification_text, status_id) 
                            VALUES (?, ?, ?, ?, ?, ?)";
        
        $notification_text = "Új " . ($event_type === 'vacation' ? "szabadság" : "betegállomány") . " kérelem: " . 
                           date('Y.m.d', strtotime($start_date)) . 
                           (($start_date !== $end_date) ? " - " . date('Y.m.d', strtotime($end_date)) : "");
        
        $stmt = mysqli_prepare($conn, $leave_request_sql);
        if (!$stmt) {
            throw new Exception("Hiba a leave request létrehozása során: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "iisssi", 
            $_SESSION['user_id'],
            $manager['id'],
            $start_date,
            $end_date,
            $notification_text,
            $status['id']
        );
        mysqli_stmt_execute($stmt);
        $request_id = mysqli_insert_id($conn);

        // Értesítés küldése a managernek
        $notification_sql = "INSERT INTO notifications 
                           (sender_user_id, receiver_user_id, notification_text, 
                            notification_time) 
                           VALUES (?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($conn, $notification_sql);
        if (!$stmt) {
            throw new Exception("Hiba az értesítés létrehozása során: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "iis", 
            $_SESSION['user_id'],
            $manager['id'],
            $notification_text
        );
        mysqli_stmt_execute($stmt);

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Sikeres kérelem benyújtása!']);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error in submit_leave_request.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés']);
}

mysqli_close($conn); 