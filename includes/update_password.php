<?php
require_once 'config.php';
require_once 'functions.php';

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Nincs bejelentkezve!']);
    exit;
}

// Ellenőrizzük, hogy POST kérés-e és JSON adatokat tartalmaz-e
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$input) {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés!']);
    exit;
}

// Adatok kinyerése
$current_password = $input['current_password'] ?? '';
$new_password = $input['new_password'] ?? '';

// Ellenőrizzük, hogy minden szükséges adat megvan-e
if (empty($current_password) || empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'Hiányzó adatok!']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Jelenlegi jelszó ellenőrzése
    $stmt = $db->prepare("SELECT password FROM user WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Felhasználó nem található!']);
        exit;
    }
    
    // Jelszó ellenőrzése
    if (!password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'A jelenlegi jelszó helytelen!']);
        exit;
    }
    
    // Új jelszó hash-elése
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Jelszó frissítése
    $update_stmt = $db->prepare("UPDATE user SET password = :password WHERE id = :user_id");
    $result = $update_stmt->execute([
        ':password' => $hashed_password,
        ':user_id' => $_SESSION['user_id']
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Jelszó sikeresen módosítva!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Hiba történt a jelszó módosítása során!']);
    }
    
} catch (PDOException $e) {
    error_log('Jelszó módosítási hiba: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Adatbázis hiba történt!']);
} 