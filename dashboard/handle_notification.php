<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Kapcsolódási hiba: " . mysqli_connect_error());
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['notification_id']) || !isset($_POST['is_accepted'])) {
        echo json_encode(['success' => false, 'message' => 'Hiányzó adatok']);
        exit;
    }

    $notification_id = intval($_POST['notification_id']);
    $is_accepted = ($_POST['is_accepted'] == 1 || $_POST['is_accepted'] === 'true') ? 1 : 0;

    mysqli_begin_transaction($conn);
    try {
        // Eredeti értesítés lekérése
        $notification_query = "SELECT * FROM notifications WHERE id = ?";
        $stmt = mysqli_prepare($conn, $notification_query);
        mysqli_stmt_bind_param($stmt, "i", $notification_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $notification = mysqli_fetch_assoc($result);

        if (!$notification) {
            throw new Exception("Értesítés nem található");
        }

        // Minden kapcsolódó értesítés frissítése a work_id alapján
        $update_sql = "UPDATE notifications 
                      SET is_accepted = ? 
                      WHERE work_id = ?";
        
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "ii", 
            $is_accepted, 
            $notification['work_id']
        );
        mysqli_stmt_execute($stmt);

        // Új értesítés szövegének előkészítése
        $original_text = str_replace('Új munka lett hozzárendelve: ', '', $notification['notification_text']);
        $response_text = $original_text . ($is_accepted ? " - Elfogadva" : " - Elutasítva");

        // Új értesítés küldése a cég tulajdonosnak
        $sender_notification_sql = "INSERT INTO notifications 
                                  (sender_user_id, receiver_user_id, notification_text, 
                                   notification_time, work_id, is_accepted) 
                                  VALUES (?, ?, ?, NOW(), ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sender_notification_sql);
        mysqli_stmt_bind_param($stmt, "iisii", 
            $_SESSION['user_id'],                // A munkás (aki válaszolt)
            $notification['sender_user_id'],     // A cégtulajdonos (eredeti küldő)
            $response_text,
            $notification['work_id'],
            $is_accepted
        );
        mysqli_stmt_execute($stmt);

        mysqli_commit($conn);
        
        echo json_encode([
            'success' => true, 
            'message' => $is_accepted ? 'Munka elfogadva!' : 'Munka elutasítva!',
            'status' => $is_accepted
        ]);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error in handle_notification.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés']);
}

mysqli_close($conn); 