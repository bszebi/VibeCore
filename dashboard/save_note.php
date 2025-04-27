<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['title']) || empty($data['content'])) {
        throw new Exception('A cím és a tartalom megadása kötelező!');
    }
    
    $db = Database::getInstance()->getConnection();
    
    if (empty($data['id'])) {
        // Új jegyzet létrehozása
        $stmt = $db->prepare("
            INSERT INTO notes 
            (user_id, title, content, text_color, background_color, font_size) 
            VALUES 
            (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $data['title'],
            $data['content'],
            $data['text_color'],
            $data['background_color'],
            $data['font_size']
        ]);
    } else {
        // Meglévő jegyzet frissítése
        $stmt = $db->prepare("
            UPDATE notes 
            SET title = ?, 
                content = ?, 
                text_color = ?, 
                background_color = ?, 
                font_size = ? 
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([
            $data['title'],
            $data['content'],
            $data['text_color'],
            $data['background_color'],
            $data['font_size'],
            $data['id'],
            $_SESSION['user_id']
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Jegyzet sikeresen mentve!'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 