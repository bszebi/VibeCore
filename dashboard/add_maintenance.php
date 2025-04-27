<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Válasz előkészítése
header('Content-Type: application/json');

try {
    // Kapcsolódás az adatbázishoz
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        throw new Exception("Kapcsolódási hiba: " . mysqli_connect_error());
    }

    // Session-ből kiolvassuk a felhasználó ID-ját és company_id-ját
    $user_id = $_SESSION['user_id'] ?? null;
    $company_id = $_SESSION['company_id'] ?? null;
    
    if (!$user_id || !$company_id) {
        throw new Exception("Nem azonosított felhasználó vagy cég!");
    }

    // Adatok validálása
    $stuff_id = filter_input(INPUT_POST, 'stuff_id', FILTER_VALIDATE_INT);
    $start_date = filter_input(INPUT_POST, 'start_date');
    $planned_end_date = filter_input(INPUT_POST, 'planned_end_date');
    $delay_end_date = filter_input(INPUT_POST, 'delay_end_date') ?: null;

    if (!$stuff_id || !$start_date || !$planned_end_date) {
        throw new Exception("Hiányzó vagy érvénytelen adatok!");
    }

    // Ellenőrizzük, hogy létezik-e az eszköz és a céghez tartozik-e
    $check_stuff = mysqli_query($conn, "SELECT id FROM stuffs WHERE id = " . intval($stuff_id) . " AND company_id = " . intval($company_id));
    if (!mysqli_num_rows($check_stuff)) {
        throw new Exception("A megadott eszköz nem található vagy nem tartozik a céghez!");
    }

    if (!strtotime($start_date)) {
        throw new Exception("A kezdés dátuma kötelező és érvényes dátumnak kell lennie!");
    }

    if (!strtotime($planned_end_date)) {
        throw new Exception("A tervezett befejezés dátuma kötelező és érvényes dátumnak kell lennie!");
    }

    // Ellenőrizzük, hogy a tervezett befejezés későbbi-e mint a kezdés
    if (strtotime($planned_end_date) <= strtotime($start_date)) {
        throw new Exception("A tervezett befejezés dátumának későbbinek kell lennie, mint a kezdés dátuma!");
    }

    // Opcionális delay_end_date validáció
    if ($delay_end_date && !strtotime($delay_end_date)) {
        throw new Exception("A csúszás végdátuma, ha meg van adva, érvényes dátumnak kell lennie!");
    }

    // Lekérjük a "Várakozik" státusz ID-ját
    $get_status_sql = "SELECT id FROM maintenance_status WHERE name = 'Várakozik'";
    $status_result = mysqli_query($conn, $get_status_sql);
    $status = mysqli_fetch_assoc($status_result);
    
    if (!$status) {
        throw new Exception("Nem található a várakozó státusz!");
    }
    
    $waiting_status_id = $status['id'];

    // Új karbantartás beszúrása
    $sql = "INSERT INTO maintenance (stuffs_id, user_id, company_id, servis_startdate, servis_planenddate, servis_currectenddate, maintenance_status_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iiisssi", 
        $stuff_id, 
        $user_id, 
        $company_id,
        $start_date, 
        $planned_end_date, 
        $delay_end_date,
        $waiting_status_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Hiba történt a mentés során: " . mysqli_error($conn));
    }

    // Eszköz státuszának frissítése "Karbantartónál"-ra
    $get_stuff_status = "SELECT id FROM stuff_status WHERE name = 'Karbantartónál'";
    $status_result = mysqli_query($conn, $get_stuff_status);
    $stuff_status = mysqli_fetch_assoc($status_result);
    
    if ($stuff_status) {
        // Tranzakció kezdése
        mysqli_begin_transaction($conn);
        try {
            $update_stuff = "UPDATE stuffs SET stuff_status_id = ? WHERE id = ? AND company_id = ?";
            $stmt = mysqli_prepare($conn, $update_stuff);
            mysqli_stmt_bind_param($stmt, "iii", $stuff_status['id'], $stuff_id, $company_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Hiba történt az eszköz státuszának frissítésekor!");
            }
            
            mysqli_commit($conn);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            throw $e;
        }
    }

    // Sikeres válasz
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Hiba esetén
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn); 