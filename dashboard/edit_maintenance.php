<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        throw new Exception("Kapcsolódási hiba: " . mysqli_connect_error());
    }

    // Session ellenőrzés
    $user_id = $_SESSION['user_id'] ?? null;
    $company_id = $_SESSION['company_id'] ?? null;
    
    if (!$user_id || !$company_id) {
        throw new Exception("Nem azonosított felhasználó vagy cég!");
    }

    // Adatok validálása
    $maintenance_id = filter_input(INPUT_POST, 'maintenance_id', FILTER_VALIDATE_INT);
    $delay_end_date = filter_input(INPUT_POST, 'delay_end_date') ?: null;
    $maintenance_status_id = filter_input(INPUT_POST, 'maintenance_status_id', FILTER_VALIDATE_INT);

    if (!$maintenance_id || !$maintenance_status_id) {
        throw new Exception("Hiányzó vagy érvénytelen adatok!");
    }

    // Tranzakció kezdése
    mysqli_begin_transaction($conn);

    try {
        // Karbantartás adatainak lekérése
        $get_maintenance = "SELECT stuffs_id, maintenance_status_id FROM maintenance WHERE id = ? AND company_id = ?";
        $stmt = mysqli_prepare($conn, $get_maintenance);
        mysqli_stmt_bind_param($stmt, "ii", $maintenance_id, $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $maintenance = mysqli_fetch_assoc($result);

        if (!$maintenance) {
            throw new Exception("A karbantartás nem található vagy nem tartozik a céghez!");
        }

        // Karbantartás frissítése
        $update_maintenance = "UPDATE maintenance SET 
                servis_currectenddate = ?,
                maintenance_status_id = ?
                WHERE id = ? AND company_id = ?";

        $stmt = mysqli_prepare($conn, $update_maintenance);
        mysqli_stmt_bind_param($stmt, "siii", 
            $delay_end_date,
            $maintenance_status_id,
            $maintenance_id,
            $company_id
        );
        mysqli_stmt_execute($stmt);

        // Eszköz státuszának frissítése a karbantartás státusza alapján
        $get_status_name = "SELECT name FROM maintenance_status WHERE id = ?";
        $stmt = mysqli_prepare($conn, $get_status_name);
        mysqli_stmt_bind_param($stmt, "i", $maintenance_status_id);
        mysqli_stmt_execute($stmt);
        $status_result = mysqli_stmt_get_result($stmt);
        $status = mysqli_fetch_assoc($status_result);

        // Eszköz státuszának beállítása
        $stuff_status_id = null;
        switch ($status['name']) {
            case 'Javítás alatt':
            case 'Alkatrész beszerzése alatt':
            case 'Várakozik':
                // Beállítjuk az 5-ös ID-jű "Karbantartónál" státuszt
                $stuff_status_id = 5;  // Karbantartónál státusz ID
                break;
            case 'Befejezve':
                // Ha befejezték a karbantartást, állítsuk vissza "Raktáron" státuszra (1-es ID)
                $stuff_status_id = 1;  // Raktáron státusz ID
                break;
        }

        if ($stuff_status_id) {
            $update_stuff = "UPDATE stuffs SET stuff_status_id = ? WHERE id = ? AND company_id = ?";
            $stmt = mysqli_prepare($conn, $update_stuff);
            mysqli_stmt_bind_param($stmt, "iii", $stuff_status_id, $maintenance['stuffs_id'], $company_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Hiba történt az eszköz státuszának frissítésekor!");
            }
        }

        // Tranzakció véglegesítése
        mysqli_commit($conn);
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn); 