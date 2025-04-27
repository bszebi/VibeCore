<?php
session_start();

// Minden kimenet előtt állítsuk be a JSON header-t
header('Content-Type: application/json');

// Hibakezelés bekapcsolása fejlesztés alatt
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Ellenőrizzük, hogy a database.php létezik-e
    if (!file_exists('../config/database.php')) {
        throw new Exception('Database configuration file not found');
    }

    require_once '../config/database.php';
    
    // Ellenőrizzük a kapcsolatot
    global $conn;
    
    if (!$conn) {
        throw new Exception('Database connection failed: ' . mysqli_connect_error());
    }
    
    // Admin ellenőrzése
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized access - No admin session');
    }
    
    // Ellenőrizzük, hogy megkaptuk-e a member_id-t
    if (!isset($_GET['member_id']) || empty($_GET['member_id'])) {
        throw new Exception('Missing member ID parameter');
    }
    
    $memberId = intval($_GET['member_id']);
    
    // Ellenőrizzük, hogy létezik-e a felhasználó
    $userCheckQuery = "SELECT id FROM user WHERE id = ?";
    $userCheckStmt = mysqli_prepare($conn, $userCheckQuery);
    mysqli_stmt_bind_param($userCheckStmt, "i", $memberId);
    mysqli_stmt_execute($userCheckStmt);
    mysqli_stmt_store_result($userCheckStmt);
    
    if (mysqli_stmt_num_rows($userCheckStmt) === 0) {
        throw new Exception('User not found');
    }
    
    mysqli_stmt_close($userCheckStmt);
    
    // Lekérjük a felhasználó jelenlegi szerepkörét
    $roleQuery = "SELECT r.id AS role_id, r.role_name 
                  FROM user_to_roles utr
                  JOIN roles r ON utr.role_id = r.id
                  WHERE utr.user_id = ?";
    
    $roleStmt = mysqli_prepare($conn, $roleQuery);
    mysqli_stmt_bind_param($roleStmt, "i", $memberId);
    mysqli_stmt_execute($roleStmt);
    $roleResult = mysqli_stmt_get_result($roleStmt);
    
    // Ha nincs szerepköre, akkor visszaadunk egy üres választ
    if (mysqli_num_rows($roleResult) === 0) {
        echo json_encode([
            'success' => true,
            'has_role' => false,
            'message' => 'User has no role assigned'
        ]);
        exit;
    }
    
    // Különben visszaadjuk a szerepkört
    $roleData = mysqli_fetch_assoc($roleResult);
    
    echo json_encode([
        'success' => true,
        'has_role' => true,
        'role_id' => $roleData['role_id'],
        'role_name' => $roleData['role_name']
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_member_role.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 