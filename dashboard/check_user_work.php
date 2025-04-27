<?php
// Start output buffering to catch any unwanted output
ob_start();

session_start();
require_once('../includes/config.php');

// Kikapcsoljuk a PHP hibaüzeneteket
error_reporting(0);
ini_set('display_errors', 0);

// Set error handler to catch all errors and convert them to JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean(); // Clear any output buffer
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'PHP Error: ' . $errstr
    ]);
    exit;
});

// Set exception handler to catch all exceptions and convert them to JSON
set_exception_handler(function($e) {
    ob_clean(); // Clear any output buffer
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Exception: ' . $e->getMessage()
    ]);
    exit;
});

// Clear any output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['user_id']) || !isset($_GET['start_date']) || !isset($_GET['end_date'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

try {
    $user_id = $_GET['user_id'];
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $company_id = $_SESSION['company_id'];

    // Check if $pdo is available
    if (!isset($pdo)) {
        throw new Exception("Database connection not available");
    }

    // Ellenőrizzük, hogy van-e átfedő munka az adott időszakban
    $query = "SELECT COUNT(*) as overlapping_work_count 
              FROM user_to_work utw
              JOIN work w ON utw.work_id = w.id
              WHERE utw.user_id = ?
              AND w.company_id = ?
              AND (
                  (w.work_start_date BETWEEN ? AND ?) OR
                  (w.work_end_date BETWEEN ? AND ?) OR
                  (? BETWEEN w.work_start_date AND w.work_end_date) OR
                  (? BETWEEN w.work_start_date AND w.work_end_date)
              )";

    $stmt = $pdo->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . print_r($pdo->errorInfo(), true));
    }

    $stmt->execute([
        $user_id, 
        $company_id, 
        $start_date, $end_date,
        $start_date, $end_date,
        $start_date, $end_date
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_overlapping_work = $row['overlapping_work_count'] > 0;

    echo json_encode([
        'has_overlapping_work' => $has_overlapping_work
    ]);

} catch (Exception $e) {
    ob_clean(); // Clear any output buffer
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) {
        $stmt = null;
    }
    
    // End output buffering and flush
    ob_end_flush();
}
?> 