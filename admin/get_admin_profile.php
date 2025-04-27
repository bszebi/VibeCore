<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Debug információk
error_log('Session tartalom: ' . print_r($_SESSION, true));

// Ensure clean output
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['admin_id'])) {
    error_log('Nincs admin_id a sessionben');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nincs bejelentkezve']);
    exit;
}

try {
    $admin_id = $_SESSION['admin_id'];
    error_log('Admin ID: ' . $admin_id);
    
    // Lekérjük az admin felhasználó adatait
    $stmt = $conn->prepare("SELECT id, username, email FROM admin_users WHERE id = ?");
    if (!$stmt) {
        error_log('Prepare error: ' . $conn->error);
        throw new Exception('Prepare failed');
    }
    
    $stmt->bind_param("i", $admin_id);
    if (!$stmt->execute()) {
        error_log('Execute error: ' . $stmt->error);
        throw new Exception('Execute failed');
    }
    
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        error_log('Admin találat: ' . print_r($row, true));
        // Sikeres lekérés
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $row['id'],
                'username' => $row['username'],
                'email' => $row['email']
            ]
        ]);
    } else {
        error_log('Admin nem található');
        // Nem található a felhasználó
        echo json_encode([
            'success' => false,
            'error' => 'Felhasználó nem található'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log('Hiba történt: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Adatbázis hiba: ' . $e->getMessage()
    ]);
}

$conn->close();
?> 