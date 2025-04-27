<?php
session_start();
require_once '../includes/config.php';

$response = ['success' => false];

try {
    // Régi kép lekérése
    $stmt = $db->prepare("SELECT profile_pic FROM user WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $old_image = $stmt->fetchColumn();
    
    // Kép törlése a szerverről
    if($old_image) {
        $upload_dir = '../uploads/';
        if(file_exists($upload_dir . $old_image)) {
            unlink($upload_dir . $old_image);
        }
    }
    
    // Kép törlése az adatbázisból (profile_pic mező nullázása)
    $stmt = $db->prepare("UPDATE user SET profile_pic = NULL WHERE id = ?");
    if($stmt->execute([$_SESSION['user_id']])) {
        $response['success'] = true;
    }
} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}

echo json_encode($response); 