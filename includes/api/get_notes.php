<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config.php';
require_once '../db.php';
require_once '../auth_check.php';

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT 
            id, 
            title, 
            content, 
            DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as created_at,
            DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i') as updated_at 
        FROM notes 
        WHERE user_id = ? 
        ORDER BY updated_at DESC, created_at DESC"
    );
    $stmt->execute([$userId]);
    
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $notes
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>
