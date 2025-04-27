<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/error_handler.php';

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    ensureJsonResponse(['success' => false, 'message' => 'Nincs bejelentkezve']);
}

// Ellenőrizzük a felhasználó szerepköreit
$user_roles = explode(',', $_SESSION['user_role']);
$is_owner = false;
foreach ($user_roles as $role) {
    if (trim($role) === 'Cég tulajdonos') {
        $is_owner = true;
        break;
    }
}

if (!$is_owner) {
    ensureJsonResponse(['success' => false, 'message' => 'Nincs jogosultság a művelethez']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'] ?? null;
    
    if (!$userId) {
        ensureJsonResponse(['success' => false, 'message' => 'Hiányzó azonosító']);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Ellenőrizzük, hogy nem saját magát próbálja-e eltávolítani
        if ($userId == $_SESSION['user_id']) {
            ensureJsonResponse(['success' => false, 'message' => 'Saját magát nem távolíthatja el!']);
        }
        
        // Ellenőrizzük, hogy a felhasználó ugyanabban a cégben van-e
        $stmt = $db->prepare("SELECT company_id FROM user WHERE id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $userCompanyId = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT company_id FROM user WHERE id = :owner_id");
        $stmt->execute([':owner_id' => $_SESSION['user_id']]);
        $ownerCompanyId = $stmt->fetchColumn();
        
        if ($userCompanyId != $ownerCompanyId) {
            ensureJsonResponse(['success' => false, 'message' => 'A felhasználó nem az Ön cégéhez tartozik']);
        }
        
        // Company ID törlése és szerepkörök eltávolítása
        $db->beginTransaction();
        
        // Company ID törlése
        $stmt = $db->prepare("UPDATE user SET company_id = NULL WHERE id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        
        // Szerepkörök eltávolítása
        $stmt = $db->prepare("DELETE FROM user_to_roles WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        
        $db->commit();
        
        ensureJsonResponse(['success' => true, 'message' => 'A felhasználó sikeresen eltávolítva']);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        ensureJsonResponse(['success' => false, 'message' => 'Hiba történt a felhasználó eltávolítása során: ' . $e->getMessage()]);
    }
} else {
    ensureJsonResponse(['success' => false, 'message' => 'Érvénytelen kérés']);
}
?> 