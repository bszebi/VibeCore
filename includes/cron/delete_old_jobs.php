<?php
require_once dirname(dirname(__DIR__)) . '/includes/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Kapcsolódási hiba: " . mysqli_connect_error());
}

try {
    // Tranzakció kezdése
    mysqli_begin_transaction($conn);

    // 7 napnál régebbi befejeződött munkák azonosítóinak lekérése
    $old_jobs_sql = "SELECT w.id, w.company_id 
                     FROM work w 
                     WHERE w.work_end_date < DATE_SUB(NOW(), INTERVAL 7 DAY)";
    
    $old_jobs_result = mysqli_query($conn, $old_jobs_sql);
    
    while ($job = mysqli_fetch_assoc($old_jobs_result)) {
        // Először töröljük a kapcsolódó értesítéseket
        $delete_notifications = "DELETE FROM notifications WHERE work_id = ?";
        $stmt = mysqli_prepare($conn, $delete_notifications);
        mysqli_stmt_bind_param($stmt, "i", $job['id']);
        mysqli_stmt_execute($stmt);

        // Majd töröljük a work_to_stuffs kapcsolatokat
        $delete_work_to_stuffs = "DELETE FROM work_to_stuffs WHERE work_id = ?";
        $stmt = mysqli_prepare($conn, $delete_work_to_stuffs);
        mysqli_stmt_bind_param($stmt, "i", $job['id']);
        mysqli_stmt_execute($stmt);

        // Majd töröljük a stuff_history bejegyzéseket
        $delete_stuff_history = "DELETE FROM stuff_history WHERE work_id = ?";
        $stmt = mysqli_prepare($conn, $delete_stuff_history);
        mysqli_stmt_bind_param($stmt, "i", $job['id']);
        mysqli_stmt_execute($stmt);

        // Végül töröljük magát a munkát
        $delete_work = "DELETE FROM work WHERE id = ? AND company_id = ?";
        $stmt = mysqli_prepare($conn, $delete_work);
        mysqli_stmt_bind_param($stmt, "ii", $job['id'], $job['company_id']);
        mysqli_stmt_execute($stmt);
        
        // Log the deletion
        error_log("Deleted old job ID: " . $job['id'] . " for company ID: " . $job['company_id']);
    }

    // Commit the transaction
    mysqli_commit($conn);
    
    echo "Successfully deleted old jobs\n";

} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    error_log("Error deleting old jobs: " . $e->getMessage());
    echo "Error deleting old jobs: " . $e->getMessage() . "\n";
} finally {
    // Close the database connection
    mysqli_close($conn);
} 