<?php
// Turn off error display for production
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
require_once('../includes/database.php');

try {
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Get all companies with their owners
    $sql = "SELECT c.*, 
            GROUP_CONCAT(CONCAT(u.lastname, ' ', u.firstname) SEPARATOR ', ') as owners
            FROM company c
            LEFT JOIN user u ON c.id = u.company_id
            LEFT JOIN user_to_roles ur ON u.id = ur.user_id
            WHERE ur.role_id = 1 OR ur.role_id IS NULL
            GROUP BY c.id";
            
    $stmt = $db->query($sql);
    if (!$stmt) {
        throw new Exception("Query failed: " . implode(", ", $db->errorInfo()));
    }

    $companies = array();
    while($row = $stmt->fetch()) {
        // Check if profile picture exists and is not empty
        $profile_picture = $row['profile_picture'];
        if (empty($profile_picture)) {
            $profile_picture = '../admin/VIBECORE.png';
        } else {
            // Ha van profile_picture, akkor ellenőrizzük, hogy létezik-e a fájl
            $file_path = '../uploads/company_logos/' . $profile_picture;
            if (!file_exists($file_path)) {
                $profile_picture = '../admin/VIBECORE.png';
            } else {
                $profile_picture = $profile_picture;
            }
        }

        $companies[] = array(
            'id' => $row['id'],
            'company_name' => $row['company_name'],
            'company_address' => $row['company_address'],
            'company_email' => $row['company_email'],
            'company_telephone' => $row['company_telephone'],
            'created_date' => $row['created_date'],
            'profile_picture' => $profile_picture,
            'owners' => $row['owners'] ? $row['owners'] : 'Nincs megadva'
        );
    }

    echo json_encode($companies);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 