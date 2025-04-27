<?php
header('Content-Type: application/json');
require_once('../includes/database.php');

if (!isset($_GET['company_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Company ID is required']);
    exit;
}

$company_id = intval($_GET['company_id']);

try {
    $db = DatabaseConnection::getInstance();
    $conn = $db->getConnection();
    
    $sql = "SELECT 
                u.id, 
                u.firstname, 
                u.lastname, 
                u.email,
                u.telephone,
                u.profile_pic,
                u.created_date,
                GROUP_CONCAT(r.role_name SEPARATOR ', ') as role_names
            FROM user u
            LEFT JOIN user_to_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.company_id = :company_id
            GROUP BY u.id, u.firstname, u.lastname, u.email, u.telephone, u.profile_pic, u.created_date
            ORDER BY u.lastname, u.firstname";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $members = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $profile_pic = $row['profile_pic'];
        if (empty($profile_pic)) {
            $profile_pic = 'user.png';
        }
        
        $members[] = array(
            'id' => $row['id'],
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'email' => $row['email'],
            'telephone' => $row['telephone'],
            'profile_picture' => $profile_pic,
            'created_date' => $row['created_date'],
            'role_names' => $row['role_names']
        );
    }

    echo json_encode($members);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 