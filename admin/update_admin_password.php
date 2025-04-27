<?php
session_start();
require_once '../includes/database.php';

header('Content-Type: application/json');

// Debug log
error_log('Received password update request');

// Ellenőrizzük, hogy be van-e jelentkezve az admin
if (!isset($_SESSION['admin_id'])) {
    error_log('No admin session found');
    echo json_encode([
        'success' => false,
        'message' => 'Nincs bejelentkezve'
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Debug log
error_log('Received data: ' . print_r($data, true));

if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
    error_log('Missing data in request');
    echo json_encode([
        'success' => false,
        'message' => 'Hiányzó adatok'
    ]);
    exit;
}

try {
    $pdo = DatabaseConnection::getInstance()->getConnection();

    // Debug log
    error_log('Admin ID from session: ' . $_SESSION['admin_id']);

    // Jelenlegi jelszó ellenőrzése
    $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debug log
    error_log('Admin found: ' . ($admin ? 'yes' : 'no'));

    if (!$admin) {
        error_log('Admin not found in database or inactive');
        echo json_encode([
            'success' => false,
            'message' => 'Az admin felhasználó nem található vagy inaktív'
        ]);
        exit;
    }

    if (!password_verify($data['currentPassword'], $admin['password'])) {
        error_log('Current password verification failed');
        echo json_encode([
            'success' => false,
            'message' => 'A jelenlegi jelszó nem megfelelő'
        ]);
        exit;
    }

    // Új jelszó beállítása
    $hashedPassword = password_hash($data['newPassword'], PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
    $result = $updateStmt->execute([$hashedPassword, $_SESSION['admin_id']]);

    // Debug log
    error_log('Update result: ' . ($result ? 'success' : 'failed'));
    error_log('Rows affected: ' . $updateStmt->rowCount());

    if ($result && $updateStmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'A jelszó sikeresen módosítva'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Nem történt módosítás'
        ]);
    }
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Adatbázis hiba: ' . $e->getMessage()
    ]);
}
?> 