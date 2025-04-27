<?php
session_start();
require_once 'check_admin.php';

header('Content-Type: application/json');

if (!isset($_FILES['profile_picture']) || !isset($_POST['member_id'])) {
    echo json_encode(['success' => false, 'message' => 'Hiányzó adatok']);
    exit;
}

$member_id = intval($_POST['member_id']);
$file = $_FILES['profile_picture'];

// Ellenőrizzük a fájl típusát
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Nem támogatott fájltípus. Csak JPG, PNG és GIF képek engedélyezettek.']);
    exit;
}

// Ellenőrizzük a fájl méretét (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'A fájl túl nagy. Maximum 5MB engedélyezett.']);
    exit;
}

// Generálunk egy egyedi fájlnevet
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '_' . time() . '.' . $extension;
$upload_path = '../uploads/profiles/';

// Létrehozzuk a mappát, ha nem létezik
if (!file_exists($upload_path)) {
    mkdir($upload_path, 0777, true);
}

// Feltöltjük a fájlt
if (move_uploaded_file($file['tmp_name'], $upload_path . $filename)) {
    require_once '../config.php';
    
    try {
        // Lekérjük a régi profilképet
        $stmt = $conn->prepare("SELECT profile_pic FROM user WHERE id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $old_profile = $result->fetch_assoc();
        
        // Frissítjük az adatbázist
        $stmt = $conn->prepare("UPDATE user SET profile_pic = ? WHERE id = ?");
        $stmt->bind_param("si", $filename, $member_id);
        
        if ($stmt->execute()) {
            // Töröljük a régi profilképet, ha létezik és nem az alapértelmezett
            if ($old_profile && $old_profile['profile_pic'] && file_exists($upload_path . $old_profile['profile_pic'])) {
                unlink($upload_path . $old_profile['profile_pic']);
            }
            
            // Naplózzuk a módosítást
            $admin_id = $_SESSION['admin_id'];
            $stmt = $conn->prepare("INSERT INTO admin_logs (user_id, action_type, table_name, record_id, old_values, new_values) VALUES (?, 'update', 'user', ?, ?, ?)");
            $old_values = json_encode(['profile_pic' => $old_profile['profile_pic']]);
            $new_values = json_encode(['profile_pic' => $filename]);
            $stmt->bind_param("iiss", $admin_id, $member_id, $old_values, $new_values);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'filename' => $filename]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Adatbázis hiba történt.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Adatbázis hiba történt: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Nem sikerült feltölteni a fájlt.']);
} 