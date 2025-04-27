<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if(isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if(in_array($file['type'], $allowed_types)) {
        $upload_dir = __DIR__ . '/../uploads/profile_images/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        
        if(move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            try {
                $db = Database::getInstance()->getConnection();
                
                $stmt = $db->prepare("SELECT profile_pic FROM user WHERE id = :user_id");
                $stmt->execute([':user_id' => $_SESSION['user_id']]);
                $old_image = $stmt->fetchColumn();
                
                if($old_image && file_exists($upload_dir . $old_image)) {
                    unlink($upload_dir . $old_image);
                }
                
                $stmt = $db->prepare("UPDATE user SET profile_pic = :profile_pic WHERE id = :user_id");
                if($stmt->execute([
                    ':profile_pic' => $filename,
                    ':user_id' => $_SESSION['user_id']
                ])) {
                    $response['success'] = true;
                    $response['message'] = 'Sikeres feltöltés!';
                    $_SESSION['profile_pic'] = $filename;
                } else {
                    $response['message'] = 'Adatbázis hiba történt!';
                }
            } catch(PDOException $e) {
                $response['message'] = 'Adatbázis hiba: ' . $e->getMessage();
                if(file_exists($upload_dir . $filename)) {
                    unlink($upload_dir . $filename);
                }
            }
        } else {
            $response['message'] = 'Fájl feltöltési hiba!';
        }
    } else {
        $response['message'] = 'Nem megengedett fájltípus!';
    }
} else {
    $response['message'] = 'Nincs feltöltött fájl!';
}

echo json_encode($response);
exit;