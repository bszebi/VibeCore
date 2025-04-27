<?php
require_once 'config.php';
require_once 'auth_check.php';

$note_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
$success = $stmt->execute([$note_id, $user_id]);

echo json_encode(['success' => $success]); 