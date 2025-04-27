<?php
// Prevent any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers for JSON response
header('Content-Type: application/json');

try {
    require_once '../includes/config.php';
    require_once '../includes/functions.php';
    require_once '../includes/auth_check.php';

    // Check if stuff_id is provided
    if (!isset($_GET['stuff_id'])) {
        throw new Exception('Hiányzó eszköz azonosító!');
    }

    $stuff_id = intval($_GET['stuff_id']);

    // Create database connection
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        throw new Exception('Adatbázis kapcsolódási hiba: ' . mysqli_connect_error());
    }

    // Get the latest report for the stuff from stuff_history instead of stuff_report
    $sql = "SELECT 
        sh.description,
        sh.created_at as report_date,
        CONCAT(u.lastname, ' ', u.firstname) as reporter_name
        FROM stuff_history sh
        LEFT JOIN user u ON sh.user_id = u.id
        LEFT JOIN stuffs s ON sh.stuffs_id = s.id
        LEFT JOIN stuff_status ss ON s.stuff_status_id = ss.id
        WHERE sh.stuffs_id = ? 
        AND (ss.name IN ('" . translate('Hibás') . "', '" . translate('Törött') . "') OR sh.stuff_status_id IN (
            SELECT id FROM stuff_status 
            WHERE name IN ('" . translate('Hibás') . "', '" . translate('Törött') . "')
        ))
        ORDER BY sh.created_at DESC
        LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('SQL hiba: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $stuff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode([
            'success' => true,
            'reporter_name' => $row['reporter_name'],
            'report_date' => $row['report_date'],
            'description' => $row['description']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Nem található bejelentés az eszközhöz!'
        ]);
    }

    mysqli_close($conn);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 