<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve és van-e company_id-ja
if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    header('Location: ../error.php?msg=unauthorized');
    exit;
}

// Ellenőrizzük a felhasználó szerepköreit
$user_roles = explode(',', $_SESSION['user_role']);
$is_admin = false;
foreach ($user_roles as $role) {
    if (trim($role) === 'Cég tulajdonos' || trim($role) === 'Manager') {
        $is_admin = true;
        break;
    }
}

if (!$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Nincs jogosultsága ehhez a művelethez!']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$conn) {
            throw new Exception("Adatbázis kapcsolódási hiba: " . mysqli_connect_error());
        }

        mysqli_begin_transaction($conn);

        // Adatok validálása
        if (!isset($_POST['user_ids']) || !isset($_POST['event_type']) || 
            !isset($_POST['start_date']) || !isset($_POST['end_date'])) {
            throw new Exception("Hiányzó adatok!");
        }

        $user_ids = $_POST['user_ids'];
        $event_type = $_POST['event_type'];
        
        // Dátumok formázása timestamp formátumba
        $start_date = date('Y-m-d 00:00:00', strtotime($_POST['start_date']));
        $end_date = date('Y-m-d 23:59:59', strtotime($_POST['end_date']));
        
        $description = $_POST['description'] ?? '';

        // Státusz ID lekérése
        $status_sql = "SELECT id FROM status WHERE name = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $status_sql);
        $status_name = $event_type === 'vacation' ? 'szabadság' : 'betegállomány';
        mysqli_stmt_bind_param($stmt, "s", $status_name);
        mysqli_stmt_execute($stmt);
        $status_result = mysqli_stmt_get_result($stmt);
        $status = mysqli_fetch_assoc($status_result);

        if (!$status) {
            throw new Exception("Érvénytelen státusz!");
        }

        // Request státusz ID lekérése (alapértelmezetten elfogadott)
        $request_status_sql = "SELECT id FROM request_status WHERE name = 'Elfogadott' LIMIT 1";
        $request_status_result = mysqli_query($conn, $request_status_sql);
        $request_status = mysqli_fetch_assoc($request_status_result);

        foreach ($user_ids as $user_id) {
            // Leave request létrehozása
            $leave_request_sql = "INSERT INTO leave_requests 
                                (user_id, leave_type_id, start_date, end_date, 
                                 status_id, approved_by, description) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $leave_request_sql);
            mysqli_stmt_bind_param($stmt, "iissiis", 
                $user_id,
                $status['id'],
                $start_date,
                $end_date,
                $request_status['id'],
                $_SESSION['user_id'],
                $description
            );
            mysqli_stmt_execute($stmt);
            $request_id = mysqli_insert_id($conn);

            // Leave history létrehozása
            $leave_history_sql = "INSERT INTO leave_history 
                                (user_id, leave_type_id, start_date, end_date, request_id) 
                                VALUES (?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $leave_history_sql);
            mysqli_stmt_bind_param($stmt, "iissi", 
                $user_id,
                $status['id'],
                $start_date,
                $end_date,
                $request_id
            );
            mysqli_stmt_execute($stmt);

            // Felhasználó nevének lekérése
            $user_sql = "SELECT CONCAT(lastname, ' ', firstname) as full_name 
                        FROM user WHERE id = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $user_sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $user_result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($user_result);

            // Calendar event létrehozása
            $calendar_sql = "INSERT INTO calendar_events 
                           (title, description, start_date, end_date, 
                            status_id, user_id, company_id) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $title = $user['full_name'] . ' - ' . ($event_type === 'vacation' ? 'Szabadság' : 'Betegállomány');
            
            $stmt = mysqli_prepare($conn, $calendar_sql);
            mysqli_stmt_bind_param($stmt, "ssssiii", 
                $title,
                $description,
                $start_date,
                $end_date,
                $status['id'],
                $user_id,
                $_SESSION['company_id']
            );
            mysqli_stmt_execute($stmt);

            // Értesítés küldése a munkásnak
            $notification_text = "Ön " . 
                               ($event_type === 'vacation' ? "szabadságra" : "betegállományba") . 
                               " lett beírva a következő időszakra: " . 
                               date('Y.m.d', strtotime($start_date)) . 
                               " - " . date('Y.m.d', strtotime($end_date));

            $notification_sql = "INSERT INTO notifications 
                               (sender_user_id, receiver_user_id, notification_text, 
                                notification_time, is_accepted) 
                               VALUES (?, ?, ?, NOW(), 1)";
            
            $stmt = mysqli_prepare($conn, $notification_sql);
            mysqli_stmt_bind_param($stmt, "iis", 
                $_SESSION['user_id'],
                $user_id,
                $notification_text
            );
            mysqli_stmt_execute($stmt);
        }

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Sikeres mentés!']);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error in save_leave_request.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés']);
}

mysqli_close($conn); 