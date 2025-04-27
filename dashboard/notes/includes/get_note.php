<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Hiányzó jegyzet azonosító']);
    exit;
}

$id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, title, content FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($note) {
        echo json_encode($note);
    } else {
        echo json_encode(['error' => 'Jegyzet nem található']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Adatbázis hiba: ' . $e->getMessage()]);
}
