<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Jogosultság ellenőrzése
checkPageAccess();

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $project_id = $_GET['id'] ?? null;
    
    if ($project_id) {
        try {
            // Kapcsolódó bejegyzések törlése a logged_event táblából
            $stmt = $pdo->prepare("DELETE FROM logged_event WHERE project_id = ?");
            $stmt->execute([$project_id]);
            
            // Kapcsolódó bejegyzések törlése a stuff_history táblából
            $stmt = $pdo->prepare("DELETE FROM stuff_history WHERE project_id = ?");
            $stmt->execute([$project_id]);
            
            // Projekt törlése
            $stmt = $pdo->prepare("DELETE FROM project WHERE id = ?");
            $stmt->execute([$project_id]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No project ID provided']);
    }
} 