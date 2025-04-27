<?php
require_once '../includes/Auth.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Frissítjük az elfogadást
    $stmt = $db->prepare("
        UPDATE cookies 
        SET acceptedornot = true 
        WHERE id = (
            SELECT cookie_id 
            FROM user 
            WHERE id = :user_id
        )
    ");
    
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 