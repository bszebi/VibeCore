<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['admin_id'])) {
    // Session újraindítása
    $_SESSION['last_activity'] = time();
    
    echo json_encode([
        'success' => true,
        'message' => 'Session successfully reset'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No valid session found'
    ]);
}
