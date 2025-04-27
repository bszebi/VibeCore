<?php
// Include error handler
require_once 'error_handler.php';

// Include required files
require_once '../config.php';
require_once '../functions.php';
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ensureJsonResponse(['success' => false, 'message' => 'Invalid request method']);
}

$email = $_POST['email'] ?? '';

if (empty($email)) {
    ensureJsonResponse(['success' => false, 'message' => 'Email is required']);
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if email exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM user WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $count = $stmt->fetchColumn();
    
    ensureJsonResponse([
        'success' => true,
        'exists' => $count > 0
    ]);
} catch (PDOException $e) {
    error_log("Database error in check_email.php: " . $e->getMessage());
    ensureJsonResponse([
        'success' => false, 
        'message' => 'Database error occurred',
        'debug' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in check_email.php: " . $e->getMessage());
    ensureJsonResponse([
        'success' => false, 
        'message' => 'An error occurred',
        'debug' => $e->getMessage()
    ]);
} 