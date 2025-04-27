<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

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

    // JSON adatok beolvasása
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Érvénytelen JSON adat');
    }

    // Debug log
    error_log('Received data: ' . print_r($input, true));

    // Adatok validálása
    $users = isset($input['users']) ? (int)$input['users'] : 0;
    $devices = isset($input['devices']) ? (int)$input['devices'] : 0;

    if ($users < 0 || $devices < 0) {
        throw new Exception('Érvénytelen felhasználó vagy eszköz szám');
    }

    // Tranzakció kezdete
    $conn->beginTransaction();

    try {
        // Jelenlegi előfizetés adatainak lekérése
        $stmt = $conn->prepare("
            SELECT s.id, s.subscription_plan_id, sp.name as plan_name, sp.price as base_price
            FROM subscriptions s
            JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
            WHERE s.company_id = ? AND s.subscription_status_id = 1
        ");
        $stmt->execute([$_SESSION['company_id']]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subscription) {
            throw new Exception('Nem található aktív előfizetés');
        }

        // Módosítás rögzítése
        $changes = $input['changes'] ?? [];
        $modificationReason = sprintf(
            "%d felhasználó, %d eszköz",
            $users,
            $devices
        );

        // Ár különbség számítása
        $userCost = ($changes['users'] ?? 0) * 2000;
        $deviceCost = ($changes['devices'] ?? 0) * 100;
        $priceDifference = $userCost + $deviceCost;

        // Új teljes ár számítása
        $newTotalPrice = $subscription['base_price'] + $priceDifference;

        // Módosítás mentése a módosítások táblába
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
            $subscription['id'],
            $subscription['subscription_plan_id'],
            $subscription['subscription_plan_id'],
            $modificationReason,
            $priceDifference,
            $_SESSION['user_id']
        ]);

        // Tranzakció véglegesítése
        $conn->commit();

        // Sikeres válasz
        echo json_encode([
            'success' => true,
            'message' => 'A módosítás sikeresen mentve',
            'new_price' => $newTotalPrice,
            'updated_users' => $users,
            'updated_devices' => $devices
        ]);

    } catch (Exception $e) {
        // Hiba esetén rollback
        $conn->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    // Hibaüzenet küldése
    error_log('Error in update_subscription.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 