<?php
session_start();

// Set JSON content type for AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
}

// Check if it's an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    if ($isAjax) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Nincs bejelentkezve']);
    } else {
        header("Location: login.php");
    }
    exit();
}

// Include database connection
require_once __DIR__ . '/../config/database.php';

try {
    // Get database connection - using mysqli instead of PDO
    global $conn; // Use the mysqli connection from database.php
    
    if (!$conn) {
        throw new Exception("Adatbázis kapcsolódási hiba");
    }
    
    // Verify admin status in database using mysqli
    $admin_id = $_SESSION['admin_id'];
    $stmt = $conn->prepare("SELECT id FROM admin_users WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Clear session if admin is not found or is inactive
        session_destroy();
        if ($isAjax) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Az admin felhasználó nem található vagy inaktív']);
        } else {
            header("Location: login.php");
        }
        exit();
    }

    // If we get here, the user is authenticated
    if ($isAjax) {
        // For AJAX requests, just return true
        echo json_encode(['success' => true]);
        exit();
    }
} catch (Exception $e) {
    if ($isAjax) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Adatbázis hiba: ' . $e->getMessage()]);
    } else {
        die("Database error: " . $e->getMessage());
    }
    exit();
}
?> 