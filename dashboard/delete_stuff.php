<?php
// Disable error display and enable error logging
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set content type to JSON
header('Content-Type: application/json');

// Start output buffering to catch any unwanted output
ob_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    ob_end_clean(); // Clear any output buffer
    echo json_encode(['success' => false, 'message' => 'Nincs bejelentkezve!']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); // Clear any output buffer
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés!']);
    exit;
}

// Get the item ID from POST data
$item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

if ($item_id <= 0) {
    ob_end_clean(); // Clear any output buffer
    echo json_encode(['success' => false, 'message' => 'Érvénytelen eszköz azonosító!']);
    exit;
}

try {
    // Get database connection
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Start transaction
    $pdo->beginTransaction();

    // First check if the item exists and belongs to the user's company
    $stmt = $pdo->prepare("
        SELECT s.id 
        FROM stuffs s
        JOIN user u ON s.company_id = u.company_id
        WHERE s.id = ? AND u.id = ?
    ");
    $stmt->execute([$item_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Az eszköz nem található vagy nincs jogosultsága a törléshez!');
    }

    // Delete related records in stuff_history table
    $stmt = $pdo->prepare("DELETE FROM stuff_history WHERE stuffs_id = ?");
    $stmt->execute([$item_id]);

    // Delete related records in work_to_stuffs table
    $stmt = $pdo->prepare("DELETE FROM work_to_stuffs WHERE stuffs_id = ?");
    $stmt->execute([$item_id]);

    // Delete related records in maintenance table
    $stmt = $pdo->prepare("DELETE FROM maintenance WHERE stuffs_id = ?");
    $stmt->execute([$item_id]);

    // Delete the item
    $stmt = $pdo->prepare("DELETE FROM stuffs WHERE id = ?");
    $stmt->execute([$item_id]);

    // Commit transaction
    $pdo->commit();

    ob_end_clean(); // Clear any output buffer
    echo json_encode(['success' => true, 'message' => 'Eszköz sikeresen törölve!']);
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    ob_end_clean(); // Clear any output buffer
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 