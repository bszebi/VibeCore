<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    $user_id = $_SESSION['user_id'];
    
    $stmt = $db->prepare("SELECT id, title, content, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as created_at FROM notes WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($notes);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Adatbázis hiba: ' . $e->getMessage()]);
}
