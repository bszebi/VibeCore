<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Kikapcsoljuk a hibák megjelenítését
header('Content-Type: application/json'); // Mindig JSON választ küldünk

// Session indítása
session_start();

try {
    require_once __DIR__ . '/../includes/database.php';

    // Adatbázis kapcsolat létrehozása
    $db = DatabaseConnection::getInstance();
    $conn = $db->getConnection();

    // Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
        throw new Exception('Nincs bejelentkezve');
    }

    // Ellenőrizzük, hogy POST kérés-e
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Érvénytelen kérés metódus');
    }

    // JSON adatok beolvasása
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Érvénytelen JSON adat');
    }

    // Debug információk
    error_log('Received input: ' . print_r($input, true));

    // Adatok kinyerése
    $packageName = $input['package_name'] ?? null;
    $isYearly = $input['is_yearly'] ?? false;

    if ($packageName === null) {
        throw new Exception('Hiányzó csomag név');
    }

    // Debug információk
    error_log('Package Name: ' . $packageName);
    error_log('Is yearly: ' . ($isYearly ? 'true' : 'false'));

    // Csomag adatok lekérése
    $packageQuery = "SELECT id, name, price FROM subscription_plans WHERE name = ?";
    $stmt = $conn->prepare($packageQuery);
    $stmt->execute([$packageName]);
    $packageData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$packageData) {
        throw new Exception('Érvénytelen csomag név');
    }

    // Alapértelmezett felhasználó és eszköz limitek meghatározása
    $userLimits = [
        'alap' => 5,
        'kozepes' => 10,
        'uzleti' => 20,
        'alap_eves' => 5,
        'kozepes_eves' => 10,
        'uzleti_eves' => 20
    ];

    $deviceLimits = [
        'alap' => 100,
        'kozepes' => 250,
        'uzleti' => 500,
        'alap_eves' => 100,
        'kozepes_eves' => 250,
        'uzleti_eves' => 500
    ];

    // Csomag típusának meghatározása
    $packageType = explode('_', $packageData['name'])[0];
    $userLimit = $userLimits[$packageType] ?? 5;
    $deviceLimit = $deviceLimits[$packageType] ?? 100;

    // Ellenőrizzük, hogy a cégnek nincs-e több felhasználója vagy eszköze, mint amennyit az új csomag engedélyez
    $companyId = $_SESSION['company_id'];
    
    // Felhasználók számának lekérése
    $userQuery = "SELECT COUNT(*) as user_count FROM user WHERE company_id = ?";
    $stmt = $conn->prepare($userQuery);
    $stmt->execute([$companyId]);
    $userResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $userCount = $userResult['user_count'];
    
    // Eszközök számának lekérése
    $deviceQuery = "SELECT COUNT(*) as device_count FROM stuffs WHERE company_id = ?";
    $stmt = $conn->prepare($deviceQuery);
    $stmt->execute([$companyId]);
    $deviceResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $deviceCount = $deviceResult['device_count'];
    
    // Ellenőrizzük, hogy a felhasználók és eszközök száma nem haladja-e meg a limitet
    $excessUsers = max(0, $userCount - $userLimit);
    $excessDevices = max(0, $deviceCount - $deviceLimit);
    
    if ($excessUsers > 0 || $excessDevices > 0) {
        $errorMessage = "Nem lehet csomagot váltani.";
        
        throw new Exception($errorMessage);
    }

    // Tranzakció kezdete
    $conn->beginTransaction();

    // Jelenlegi előfizetés lekérése
    $stmt = $conn->prepare("
        SELECT s.id AS subscription_id, s.subscription_plan_id, sp.price, sp.name AS plan_name
        FROM subscriptions s 
        JOIN subscription_plans sp ON s.subscription_plan_id = sp.id 
        WHERE s.company_id = ? AND s.subscription_status_id = 1
    ");
    $stmt->execute([$companyId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        throw new Exception('Nem található aktív előfizetés');
    }

    // Módosítás rögzítése
    $modificationReason = sprintf(
        "Csomag váltás: %s (%s) - Felhasználók: %d, Eszközök: %d",
        ucfirst($packageType) . " csomag",
        $isYearly ? "Éves" : "Havi",
        $userLimit,
        $deviceLimit
    );

    $stmt = $conn->prepare("
        INSERT INTO subscription_modifications (
            subscription_id, 
            original_plan_id, 
            modified_plan_id, 
            modification_reason, 
            price_difference, 
            modification_date, 
            modified_by_user_id
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([
        $subscription['subscription_id'],
        $subscription['subscription_plan_id'],
        $packageData['id'],
        $modificationReason,
        $packageData['price'] - $subscription['price'],
        $_SESSION['user_id']
    ]);

    // Következő fizetési időpont kiszámítása
    $currentDate = new DateTime();
    $nextBillingDate = clone $currentDate;
    $nextBillingDate->modify($isYearly ? '+1 year' : '+1 month');

    // Előfizetés frissítése
    $stmt = $conn->prepare("
        UPDATE subscriptions 
        SET subscription_plan_id = ?,
            next_billing_date = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $packageData['id'],
        $nextBillingDate->format('Y-m-d H:i:s'),
        $subscription['subscription_id']
    ]);

    // Debug információk
    error_log('Subscription updated successfully');
    error_log('Next billing date set to: ' . $nextBillingDate->format('Y-m-d H:i:s'));

    // Tranzakció véglegesítése
    $conn->commit();

    // Debug információk
    error_log('Transaction committed successfully');

    // Sikeres válasz küldése
    echo json_encode([
        'success' => true,
        'message' => 'Csomag sikeresen módosítva',
        'new_package_name' => $packageData['name'],
        'next_billing_date' => $nextBillingDate->format('Y-m-d')
    ]);

} catch (Exception $e) {
    // Hiba esetén rollback
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    // Hibaüzenet küldése
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 