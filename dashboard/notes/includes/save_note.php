<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['title']) || !isset($data['content'])) {
    echo json_encode(['success' => false, 'message' => 'Hiányzó adatok']);
    exit;
}

$title = $data['title'];
$content = $data['content'];
$user_id = $_SESSION['user_id'];
$id = isset($data['id']) ? $data['id'] : null;

try {
    $db = Database::getInstance()->getConnection();
    
    if ($id) {
        // Update existing note
        $stmt = $db->prepare("UPDATE notes SET title = ?, content = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $success = $stmt->execute([$title, $content, $id, $user_id]);
        
        if ($success) {
            echo json_encode(['success' => true, 'note_id' => $id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Hiba történt a jegyzet mentése közben']);
        }
    } else {
        // Create new note
        $stmt = $db->prepare("INSERT INTO notes (user_id, title, content, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $success = $stmt->execute([$user_id, $title, $content]);
        
        if ($success) {
            echo json_encode(['success' => true, 'note_id' => $db->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Hiba történt a jegyzet létrehozása közben']);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Adatbázis hiba: ' . $e->getMessage()]);
}
