<?php
// Disable error display for production
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set content type to JSON
header('Content-Type: application/json');

// Check if config/database.php exists
if (!file_exists('../config/database.php')) {
    echo json_encode([
        'success' => false, 
        'error' => 'Adatbázis konfigurációs fájl nem található'
    ]);
    exit;
}

// Include database connection
require_once('../config/database.php');

// Initialize response array
$response = [
    'success' => false,
    'error' => null,
    'message' => null,
    'affected_rows' => 0
];

// Log function for debugging
function logAction($message) {
    $logFile = '../logs/member_updates.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    
    if (is_writable(dirname($logFile))) {
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}

// Verify user is logged in and is admin
session_start();
if (!isset($_SESSION['admin_id'])) {
    logAction("Session expired: admin_id not found in session");
    $response['error'] = 'Nincs bejelentkezve admin felhasználó';
    echo json_encode($response);
    exit;
}

// Only accept POST requests with JSON content
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logAction("Method not allowed: {$_SERVER['REQUEST_METHOD']} used");
    $response['error'] = 'Csak POST kérés engedélyezett';
    echo json_encode($response);
    exit;
}

// Get JSON data from the request body
$inputJSON = file_get_contents('php://input');
$inputData = json_decode($inputJSON, true);

// Log received data
logAction("Received data: " . $inputJSON);

// Check if JSON is valid
if ($inputData === null && json_last_error() !== JSON_ERROR_NONE) {
    logAction("Invalid JSON data: " . json_last_error_msg());
    $response['error'] = 'Érvénytelen JSON adat: ' . json_last_error_msg();
    echo json_encode($response);
    exit;
}

// Check required parameters
if (!isset($inputData['member_id']) || !isset($inputData['field']) || !isset($inputData['value'])) {
    logAction("Missing required parameters");
    $response['error'] = 'Hiányzó paraméterek (member_id, field, value)';
    echo json_encode($response);
    exit;
}

// Get the member ID, field and value
$memberId = intval($inputData['member_id']);
$field = $inputData['field'];
$value = $inputData['value'];

// Validate member ID
if ($memberId <= 0) {
    logAction("Invalid member ID: $memberId");
    $response['error'] = 'Érvénytelen tag azonosító';
    echo json_encode($response);
    exit;
}

// Define allowed fields for update
$allowedFields = ['firstname', 'lastname', 'email', 'telephone'];

// Check if the field is allowed
if (!in_array($field, $allowedFields)) {
    logAction("Invalid field: $field");
    $response['error'] = 'Érvénytelen mező: ' . $field;
    echo json_encode($response);
    exit;
}

// Execute update query using prepared statement
try {
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        logAction("Connection failed: " . $conn->connect_error);
        $response['error'] = 'Adatbázis kapcsolódási hiba';
        echo json_encode($response);
        exit;
    }
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
    // Verify that the user exists and get the old value
    $checkSql = "SELECT id, $field FROM user WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $memberId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        logAction("User not found with ID: $memberId");
        $response['error'] = 'A megadott azonosítójú tag nem található';
        echo json_encode($response);
        exit;
    }
    
    // Get the old value for logging
    $oldValueRow = $checkResult->fetch_assoc();
    $oldValue = $oldValueRow[$field];
    
    // Update user data with prepared statement
    $updateSql = "UPDATE user SET $field = ? WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("si", $value, $memberId);
    
    $result = $stmt->execute();
    
    if ($result) {
        $affectedRows = $stmt->affected_rows;
        
        // Successfully updated
        $response['success'] = true;
        $response['affected_rows'] = $affectedRows;
        
        if ($affectedRows > 0) {
            $response['message'] = 'Adatok sikeresen frissítve';
            logAction("Successfully updated member $memberId, field: $field, value: $value");
            
            // Log to admin_logs table
            try {
                // Check if the admin_logs table has the new structure
                $checkTable = $conn->query("SHOW COLUMNS FROM admin_logs LIKE 'action_type'");
                
                if ($checkTable && $checkTable->num_rows > 0) {
                    // Use new structure with proper fields
                    $adminId = (int)$_SESSION['admin_id'];
                    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $actionType = 'UPDATE';
                    $tableName = 'user';
                    $recordId = $memberId;
                    $currentDate = date('Y-m-d H:i:s');
                    
                    // Format JSON data for better readability
                    $oldValues = json_encode([$field => $oldValue], JSON_UNESCAPED_UNICODE);
                    $newValues = json_encode([$field => $value], JSON_UNESCAPED_UNICODE);
                    
                    // Use prepared statement for security
                    $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, table_name, record_id, old_values, new_values, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $logStmt->bind_param("isssssss", $adminId, $actionType, $tableName, $recordId, $oldValues, $newValues, $ipAddress, $currentDate);
                    $logStmt->execute();
                } else {
                    // Fall back to older structure if available
                    $checkTable = $conn->query("SHOW COLUMNS FROM admin_logs LIKE 'admin_id'");
                    
                    if ($checkTable && $checkTable->num_rows > 0) {
                        // admin_id column exists
                        $adminId = (int)$_SESSION['admin_id'];
                        $currentDate = date('Y-m-d H:i:s');
                        $details = "Admin #$adminId, felhasználó $field változtatás, $oldValue, $value, $currentDate";
                        $escapedDetails = $conn->real_escape_string($details);
                        
                        $logQuery = "INSERT INTO admin_logs (admin_id, action, details) VALUES ($adminId, 'user_update_$field', '$escapedDetails')";
                        $conn->query($logQuery);
                    } else {
                        // Check if the table has user_id column instead
                        $checkUserId = $conn->query("SHOW COLUMNS FROM admin_logs LIKE 'user_id'");
                        
                        if ($checkUserId && $checkUserId->num_rows > 0) {
                            // user_id column exists - original user_id fallback
                            $adminId = (int)$_SESSION['admin_id'];
                            $currentDate = date('Y-m-d H:i:s');
                            $details = "Admin #$adminId, felhasználó $field változtatás, $oldValue, $value, $currentDate";
                            $escapedDetails = $conn->real_escape_string($details);
                            
                            $logQuery = "INSERT INTO admin_logs (user_id, action, details) VALUES ($adminId, 'user_update_$field', '$escapedDetails')";
                            $conn->query($logQuery);
                        } else {
                            logAction("Nem sikerült a naplózás az admin_logs táblába, mert nincs megfelelő oszlop");
                        }
                    }
                }
            } catch (Exception $e) {
                logAction("Naplózási hiba az admin_logs táblába: " . $e->getMessage());
            }
        } else {
            $response['message'] = 'Nem történt változás az adatokban';
            logAction("No changes made for member $memberId, field: $field, value: $value");
        }
    } else {
        logAction("Query error: " . $stmt->error);
        $response['error'] = 'SQL hiba: ' . $stmt->error;
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    logAction("Exception: " . $e->getMessage());
    $response['error'] = 'Hiba: ' . $e->getMessage();
}

// Return JSON response
echo json_encode($response);
exit;
?> 