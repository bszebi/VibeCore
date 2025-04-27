<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'api/error_handler.php';
require_once 'db.php';

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    ensureJsonResponse(['success' => false, 'message' => 'Nincs jogosultság']);
}

// Ellenőrizzük a felhasználó szerepköreit
$user_roles = explode(',', $_SESSION['user_role']);
$is_admin = false;
foreach ($user_roles as $role) {
    if (trim($role) === 'Cég tulajdonos' || trim($role) === 'Manager') {
        $is_admin = true;
        break;
    }
}

if (!$is_admin) {
    ensureJsonResponse(['success' => false, 'message' => 'Nincs jogosultság']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'] ?? null;
    $newRole = $_POST['role'] ?? null;
    
    if (!$userId || !$newRole) {
        ensureJsonResponse(['success' => false, 'message' => 'Hiányzó adatok']);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Ellenőrizzük, hogy létezik-e a szerepkör
        $stmt = $db->prepare("SELECT id FROM roles WHERE role_name = :role_name");
        $stmt->execute([':role_name' => $newRole]);
        $roleId = $stmt->fetchColumn();
        
        if (!$roleId) {
            ensureJsonResponse(['success' => false, 'message' => 'A megadott szerepkör nem létezik']);
        }

        // Ellenőrizzük, hogy van-e másik Cég tulajdonos
        if ($newRole !== 'Cég tulajdonos') {
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM user_to_roles utr 
                JOIN roles r ON utr.role_id = r.id 
                WHERE r.role_name = 'Cég tulajdonos' 
                AND utr.user_id != :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $otherOwnerCount = $stmt->fetchColumn();

            if ($otherOwnerCount === 0) {
                ensureJsonResponse(['success' => false, 'message' => 'Legalább egy Cég tulajdonosnak lennie kell!']);
            }
        }
        
        // Tranzakció kezdete
        $db->beginTransaction();
        
        try {
            // Régi szerepkör törlése
            $stmt = $db->prepare("DELETE FROM user_to_roles WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            
            // Új szerepkör hozzáadása
            $stmt = $db->prepare("INSERT INTO user_to_roles (user_id, role_id) VALUES (:user_id, :role_id)");
            $stmt->execute([
                ':user_id' => $userId,
                ':role_id' => $roleId
            ]);
            
            // Tranzakció véglegesítése
            $db->commit();
            
            ensureJsonResponse(['success' => true]);
        } catch (Exception $e) {
            // Ha hiba történt a tranzakcióban, visszavonjuk
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e; // Újradobjuk a kivételt a külső catch blokknak
        }
        
    } catch (Exception $e) {
        // Részletesebb hibaüzenet
        $errorMessage = 'Adatbázis hiba: ' . $e->getMessage();
        error_log("Role update error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        ensureJsonResponse([
            'success' => false, 
            'message' => $errorMessage,
            'debug' => [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]);
    }
}
?> 