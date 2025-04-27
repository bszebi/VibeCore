<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Nincs bejelentkezve!'
    ]);
    exit;
}

// Ellenőrizzük, hogy POST kérés-e
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Érvénytelen kérés típus!'
    ]);
    exit;
}

try {
    // Ellenőrizzük, hogy van-e notification_ids
    if (!isset($_POST['notification_ids'])) {
        throw new Exception('Nincsenek kiválasztva értesítések!');
    }

    // JSON dekódolás
    $notification_ids = json_decode($_POST['notification_ids'], true);
    if (!is_array($notification_ids)) {
        throw new Exception('Érvénytelen értesítés azonosítók!');
    }

    // Ellenőrizzük a felhasználó jogosultságait
    $user_roles = explode(',', $_SESSION['user_role']);
    $is_admin = false;
    foreach ($user_roles as $role) {
        $role = trim($role);
        if ($role === 'Cég tulajdonos' || $role === 'Manager') {
            $is_admin = true;
            break;
        }
    }

    // Kezdjük a tranzakciót
    $pdo->beginTransaction();

    if ($is_admin) {
        // Admin felhasználók bármely értesítést törölhetnek
        $delete_sql = "DELETE FROM notifications WHERE id IN (" . str_repeat('?,', count($notification_ids) - 1) . "?)";
    } else {
        // Normál felhasználók csak a saját értesítéseiket törölhetik
        $delete_sql = "DELETE FROM notifications WHERE id IN (" . str_repeat('?,', count($notification_ids) - 1) . "?) AND receiver_user_id = ?";
    }

    $stmt = $pdo->prepare($delete_sql);
    
    if ($is_admin) {
        $stmt->execute($notification_ids);
    } else {
        $params = array_merge($notification_ids, [$_SESSION['user_id']]);
        $stmt->execute($params);
    }

    // Ellenőrizzük, hogy történt-e törlés
    if ($stmt->rowCount() === 0) {
        throw new Exception('Nincs jogosultsága minden kiválasztott értesítés törléséhez!');
    }

    // Véglegesítjük a tranzakciót
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Az értesítések sikeresen törölve lettek!'
    ]);

} catch (Exception $e) {
    // Hiba esetén visszagörgetjük a tranzakciót
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 