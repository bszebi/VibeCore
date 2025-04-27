<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Kikapcsoljuk a hibaüzenetek megjelenítését

// Minden kimenet előtt állítsuk be a JSON header-t
header('Content-Type: application/json');

try {
    // Ellenőrizzük, hogy létezik-e a database.php
    if (!file_exists('../config/database.php')) {
        throw new Exception('Database configuration file not found');
    }

    require_once '../config/database.php';
    
    // Ellenőrizzük, hogy van-e adatbázis kapcsolat
    global $conn;
    
    if (!$conn) {
        throw new Exception('Database connection failed: ' . mysqli_connect_error());
    }

    // Admin ellenőrzése
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized access - No admin session');
    }

    // Admin ID lekérése az adatbázisból
    $adminCheckStmt = $conn->prepare("SELECT id FROM admin_users WHERE id = ?");
    $adminCheckStmt->bind_param("i", $_SESSION['admin_id']);
    $adminCheckStmt->execute();
    $adminResult = $adminCheckStmt->get_result();
    
    if (!$adminResult->fetch_assoc()) {
        throw new Exception('Unauthorized access - Invalid admin ID');
    }

    // JSON adatok beolvasása
    $jsonData = file_get_contents('php://input');
    if (!$jsonData) {
        throw new Exception('No data received');
    }

    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data: ' . json_last_error_msg());
    }

    // Kompatibilitás miatt ellenőrizzük mindkét lehetséges mezőnevet
    $memberId = 0;
    $roleId = 0;
    
    if (isset($data['memberId'])) {
        $memberId = intval($data['memberId']);
    } elseif (isset($data['member_id'])) {
        $memberId = intval($data['member_id']);
    }
    
    if (isset($data['roleId'])) {
        $roleId = intval($data['roleId']);
    } elseif (isset($data['role_id'])) {
        $roleId = intval($data['role_id']);
    }

    // Ellenőrizzük, hogy érvényes-e a memberId és roleId
    if ($memberId <= 0 || $roleId <= 0) {
        throw new Exception('Invalid member ID or role ID');
    }

    // Ellenőrizzük, hogy létezik-e a felhasználó
    $userCheckStmt = $conn->prepare("SELECT id FROM user WHERE id = ?");
    $userCheckStmt->bind_param("i", $memberId);
    $userCheckStmt->execute();
    $userResult = $userCheckStmt->get_result();
    
    if (!$userResult->fetch_assoc()) {
        throw new Exception('User not found');
    }

    // Ellenőrizzük, hogy létezik-e a szerepkör
    $roleCheckStmt = $conn->prepare("SELECT id, role_name FROM roles WHERE id = ?");
    $roleCheckStmt->bind_param("i", $roleId);
    $roleCheckStmt->execute();
    $roleResult = $roleCheckStmt->get_result();
    $roleData = $roleResult->fetch_assoc();
    
    if (!$roleData) {
        throw new Exception('Role not found');
    }
    
    // Lekérdezzük a jelenlegi szerepkört naplózáshoz
    $currentRoleStmt = $conn->prepare(
        "SELECT r.id, r.role_name 
         FROM user_to_roles ur 
         JOIN roles r ON ur.role_id = r.id 
         WHERE ur.user_id = ?"
    );
    $currentRoleStmt->bind_param("i", $memberId);
    $currentRoleStmt->execute();
    $currentRoleResult = $currentRoleStmt->get_result();
    $oldRoleData = $currentRoleResult->fetch_assoc();
    $oldRoleText = $oldRoleData ? "{$oldRoleData['id']} ({$oldRoleData['role_name']})" : "Nincs szerepkör";

    // Töröljük a régi szerepkört
    $deleteStmt = $conn->prepare("DELETE FROM user_to_roles WHERE user_id = ?");
    $deleteStmt->bind_param("i", $memberId);
    $deleteStmt->execute();

    // Adjuk hozzá az új szerepkört
    $insertStmt = $conn->prepare("INSERT INTO user_to_roles (user_id, role_id) VALUES (?, ?)");
    $insertStmt->bind_param("ii", $memberId, $roleId);
    $insertStmt->execute();
    
    // Naplózzuk az admin_logs táblába
    try {
        // Ellenőrizzük, hogy létezik-e az új admin_logs szerkezet
        $checkActionTypeColumn = $conn->query("SHOW COLUMNS FROM admin_logs LIKE 'action_type'");
        
        if ($checkActionTypeColumn->num_rows > 0) {
            // Új admin_logs szerkezetet használunk
            $adminId = (int)$_SESSION['admin_id'];
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $actionType = 'UPDATE';
            $tableName = 'user_to_roles';
            $recordId = $memberId;
            $currentDate = date('Y-m-d H:i:s');
            
            // Részletes és olvasható JSON adatok
            $oldRoleName = $oldRoleData ? $oldRoleData['role_name'] : "Nincs szerepkör";
            $newRoleName = $roleData['role_name'];
            $oldValues = json_encode(['role_id' => $oldRoleData ? $oldRoleData['id'] : null, 'role_name' => $oldRoleName], JSON_UNESCAPED_UNICODE);
            $newValues = json_encode(['role_id' => $roleData['id'], 'role_name' => $newRoleName], JSON_UNESCAPED_UNICODE);
            
            // Prepared statement használata a biztonság érdekében
            $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, table_name, record_id, old_values, new_values, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $logStmt->bind_param("isssssss", $adminId, $actionType, $tableName, $recordId, $oldValues, $newValues, $ipAddress, $currentDate);
            $logStmt->execute();
        } else {
            // Régi szerkezet használata, ha létezik
            // Ellenőrizzük, hogy létezik-e az admin_id vagy user_id oszlop az admin_logs táblában
            $checkAdminIdColumn = $conn->query("SHOW COLUMNS FROM admin_logs LIKE 'admin_id'");
            
            if ($checkAdminIdColumn->num_rows > 0) {
                // admin_id oszlop létezik
                $adminId = (int)$_SESSION['admin_id'];
                $currentDate = date('Y-m-d H:i:s');
                $oldRoleName = $oldRoleData ? $oldRoleData['role_name'] : "Nincs szerepkör";
                $newRoleName = $roleData['role_name'];
                $details = "Admin #$adminId, szerepkör változtatás, $oldRoleName, $newRoleName, $currentDate";
                $escapedDetails = $conn->real_escape_string($details);
                
                $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'role_change', ?)");
                $logStmt->bind_param("is", $adminId, $escapedDetails);
                $logStmt->execute();
            } else {
                // Ellenőrizzük, hogy létezik-e a user_id oszlop
                $checkUserIdColumn = $conn->query("SHOW COLUMNS FROM admin_logs LIKE 'user_id'");
                
                if ($checkUserIdColumn->num_rows > 0) {
                    // user_id oszlop létezik
                    $adminId = (int)$_SESSION['admin_id'];
                    $currentDate = date('Y-m-d H:i:s');
                    $oldRoleName = $oldRoleData ? $oldRoleData['role_name'] : "Nincs szerepkör";
                    $newRoleName = $roleData['role_name'];
                    $details = "Admin #$adminId, szerepkör változtatás, $oldRoleName, $newRoleName, $currentDate";
                    $escapedDetails = $conn->real_escape_string($details);
                    
                    $logStmt = $conn->prepare("INSERT INTO admin_logs (user_id, action, details) VALUES (?, 'role_change', ?)");
                    $logStmt->bind_param("is", $adminId, $escapedDetails);
                    $logStmt->execute();
                } else {
                    error_log("Nem sikerült a naplózás az admin_logs táblába, mert nincs megfelelő oszlop");
                }
            }
        }
    } catch (Exception $e) {
        error_log("Naplózási hiba az admin_logs táblába: " . $e->getMessage());
    }

    // Sikeres válasz
    echo json_encode([
        'success' => true,
        'message' => 'Role updated successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in update_member_role.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 