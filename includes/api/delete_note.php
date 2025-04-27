<?php
require_once '../config.php';
require_once '../auth_check.php';

header('Content-Type: application/json');

if (!isset($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Hiányzó jegyzet azonosító']);
    exit;
}

$noteId = $_POST['id'];
$userId = $_SESSION['user_id'];

try {
    // Ellenőrizzük, hogy a jegyzet a felhasználóhoz tartozik-e
    $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$noteId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Jegyzet nem található vagy nem törölhető']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Adatbázis hiba történt']);
}
