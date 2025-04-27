<?php
// Suppress PHP errors in the output but log them
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

// Start output buffering to ensure clean output
ob_start();

// Start the session
session_start();

// Load database configuration
require_once 'includes/config.php';

// Establish database connection if it doesn't exist
if (!isset($conn) || $conn === null) {
    $db_host = "localhost";
    $db_user = "root";
    $db_password = "";
    $db_name = "vizsgaremek";

    $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8");
}

// Get the email from query string or use a sample
$test_email = isset($_GET['email']) ? $_GET['email'] : '';

// Initialize the output array
$result = [
    'database_connection' => $conn ? true : false,
    'user_exists' => false,
    'user_details' => null,
    'has_owner_role' => false,
    'roles' => [],
    'message' => ''
];

// Check if the email was provided
if (!empty($test_email)) {
    // Step 1: Check if the user exists
    $query = "SELECT * FROM user WHERE email = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        $result['message'] = "Prepare statement error: " . $conn->error;
    } else {
        $stmt->bind_param("s", $test_email);
        $stmt->execute();
        $user_result = $stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $result['user_exists'] = true;
            $user = $user_result->fetch_assoc();
            
            // Remove sensitive data before returning
            if (isset($user['password'])) {
                $user['password'] = 'HIDDEN';
            }
            
            $result['user_details'] = $user;
            
            // Step 2: Check if the user has the 'Cégtulajdonos' role
            $role_query = "SELECT r.id, r.role_name 
                        FROM user_to_roles ur 
                        JOIN roles r ON ur.role_id = r.id 
                        WHERE ur.user_id = ?";
                        
            $role_stmt = $conn->prepare($role_query);
            
            if (!$role_stmt) {
                $result['message'] = "Role query prepare error: " . $conn->error;
            } else {
                $role_stmt->bind_param("i", $user['id']);
                $role_stmt->execute();
                $role_result = $role_stmt->get_result();
                
                if ($role_result->num_rows > 0) {
                    while ($role = $role_result->fetch_assoc()) {
                        $result['roles'][] = $role;
                        
                        if ($role['role_name'] === 'Cégtulajdonos') {
                            $result['has_owner_role'] = true;
                        }
                    }
                } else {
                    $result['message'] = "A felhasználónak nincs szerepköre.";
                }
            }
        } else {
            $result['message'] = "Nem található felhasználó ezzel az email címmel.";
        }
    }
} else {
    $result['message'] = "Kérjük, adjon meg egy email címet a teszteléshez.";
}

// Step 3: Check available roles in the system
$roles_query = "SELECT * FROM roles";
$roles_result = $conn->query($roles_query);
$system_roles = [];

if ($roles_result && $roles_result->num_rows > 0) {
    while ($role = $roles_result->fetch_assoc()) {
        $system_roles[] = $role;
    }
}

$result['system_roles'] = $system_roles;

// Clear the output buffer and return JSON
ob_clean();
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
exit; 