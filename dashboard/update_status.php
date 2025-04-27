<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Only start a session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ellenőrizzük a bejelentkezést
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Nincs bejelentkezve!']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = DatabaseConnection::getInstance()->getConnection();
        
        // Felhasználó company_id lekérése
        $stmt = $db->prepare("SELECT company_id FROM user WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $company_id = $stmt->fetchColumn();
        
        if (!$company_id) {
            throw new Exception('Nincs jogosultsága a művelethez!');
        }
        
        // Eszköz company_id ellenőrzése
        $stmt = $db->prepare("SELECT company_id FROM stuffs WHERE id = ?");
        $stmt->execute([$_POST['item_id']]);
        $stuff_company_id = $stmt->fetchColumn();
        
        if (!$stuff_company_id) {
            throw new Exception('Az eszköz nem található az adatbázisban! (ID: ' . $_POST['item_id'] . ')');
        }
        
        if ($stuff_company_id != $company_id) {
            throw new Exception('Nincs jogosultsága az eszköz módosításához! (Cég ID: ' . $stuff_company_id . ', Felhasználó cég ID: ' . $company_id . ')');
        }
        
        // Új státusz név lekérése
        $stmt = $db->prepare("SELECT name FROM stuff_status WHERE id = ?");
        $stmt->execute([$_POST['new_status']]);
        $new_status_name = $stmt->fetchColumn();
        
        $allowed_statuses = [translate('Hibás'), translate('Törött'), translate('Kiszelektálás alatt')];
        if (!in_array($new_status_name, $allowed_statuses)) {
            throw new Exception('Csak Hibás, Törött vagy Kiszelektálás alatt státuszra lehet módosítani!');
        }
        
        // Státusz frissítése
        $stmt = $db->prepare("UPDATE stuffs SET stuff_status_id = ? WHERE id = ? AND company_id = ?");
        $result = $stmt->execute([$_POST['new_status'], $_POST['item_id'], $company_id]);
        
        if (!$result) {
            throw new Exception('A státusz módosítása sikertelen! (Adatbázis hiba)');
        }
        
        // Ellenőrizzük, hogy a státusz tényleg megváltozott-e
        $check_stmt = $db->prepare("SELECT stuff_status_id FROM stuffs WHERE id = ? AND company_id = ?");
        $check_stmt->execute([$_POST['item_id'], $company_id]);
        $current_status = $check_stmt->fetchColumn();
        
        if ($current_status == $_POST['new_status']) {
            // A státusz sikeresen megváltozott, folytatjuk a folyamatot
            // Megjegyzés ellenőrzése - kötelező mező
            if (empty($_POST['status_comment'])) {
                throw new Exception('A megjegyzés megadása kötelező a státuszváltozáshoz!');
            }
            
            // Megjegyzés mentése a stuff_history táblába
            $stmt = $db->prepare("
                INSERT INTO stuff_history (
                    stuffs_id, 
                    work_id,
                    user_id, 
                    stuff_status_id, 
                    description, 
                    created_at
                ) VALUES (?, NULL, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $_POST['item_id'],
                $_SESSION['user_id'],
                $_POST['new_status'],
                $_POST['status_comment']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Státusz sikeresen módosítva!',
                'new_status_name' => $new_status_name
            ]);
        } else {
            // A státusz nem változott meg, ellenőrizzük, hogy miért
            if ($stmt->rowCount() === 0) {
                // Log the error for debugging
                error_log("Status update failed for device ID: " . $_POST['item_id'] . ", company_id: " . $company_id);
                
                // Check if the device still exists
                $check_stmt = $db->prepare("SELECT id FROM stuffs WHERE id = ?");
                $check_stmt->execute([$_POST['item_id']]);
                $device_exists = $check_stmt->fetchColumn();
                
                if (!$device_exists) {
                    throw new Exception('Az eszköz nem található az adatbázisban! (ID: ' . $_POST['item_id'] . ')');
                } else {
                    throw new Exception('A státusz módosítása sikertelen! Ellenőrizze a jogosultságait és próbálja újra.');
                }
            }
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés!']);
} 