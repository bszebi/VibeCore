<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Ensure we're sending JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nincs bejelentkezve']);
    exit;
}

if (!isset($_POST['title']) || !isset($_POST['content'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Hiányzó adatok']);
    exit;
}

$title = trim($_POST['title']);
$content = $_POST['content'];
$userId = $_SESSION['user_id'];

try {
    if (isset($_POST['id'])) {
        // Meglévő jegyzet frissítése
        $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$title, $content, $_POST['id'], $userId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'id' => $_POST['id']]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Jegyzet nem található']);
        }
    } else {
        // Új jegyzet létrehozása
        $stmt = $pdo->prepare("INSERT INTO notes (title, content, user_id, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt->execute([$title, $content, $userId]);
        
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    }
} catch (PDOException $e) {
    error_log("Database error in save_note.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Adatbázis hiba történt']);
    exit;
}
