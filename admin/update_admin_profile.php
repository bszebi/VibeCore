<?php
session_start();
require_once 'check_admin.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['field']) || !isset($data['value'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Hiányzó adatok'
    ]);
    exit;
}

$field = $data['field'];
$value = $data['value'];

// Csak a megengedett mezők módosíthatók
if (!in_array($field, ['username', 'email'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Érvénytelen mező'
    ]);
    exit;
}

try {
    $pdo = DatabaseConnection::getInstance()->getConnection();

    // Ellenőrizzük, hogy a username/email már foglalt-e
    if ($field === 'username' || $field === 'email') {
        $checkStmt = $pdo->prepare("SELECT id FROM admin_users WHERE $field = ? AND id != ?");
        $checkStmt->execute([$value, $_SESSION['admin_id']]);
        if ($checkStmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'Ez a ' . ($field === 'username' ? 'felhasználónév' : 'email cím') . ' már foglalt'
            ]);
            exit;
        }
    }

    $stmt = $pdo->prepare("UPDATE admin_users SET $field = ? WHERE id = ?");
    $stmt->execute([$value, $_SESSION['admin_id']]);

    if ($stmt->rowCount() > 0) {
        // Frissítjük a session-ben tárolt adatokat
        if ($field === 'username') {
            $_SESSION['admin_username'] = $value;
        } elseif ($field === 'email') {
            $_SESSION['admin_email'] = $value;
        }
        echo json_encode([
            'success' => true,
            'message' => 'Sikeres módosítás'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Nem történt módosítás'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Adatbázis hiba: ' . $e->getMessage()
    ]);
}
?> 