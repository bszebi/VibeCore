<?php
require_once __DIR__ . '/../includes/layout/header.php';
require_once __DIR__ . '/../includes/database.php';

// --- ÁRAK AUTOMATIKUS JAVÍTÁSA (egyszeri futás, utána törölhető) ---
try {
    $db = DatabaseConnection::getInstance();
    $conn = $db->getConnection();
    $conn->exec("UPDATE subscription_plans SET price = 305898 WHERE name = 'alap_eves'");
    $conn->exec("UPDATE subscription_plans SET price = 571098 WHERE name = 'kozepes_eves'");
} catch (Exception $e) {
    // error_log('Ár frissítés hiba: ' . $e->getMessage());
}
// --- JAVÍTÁS VÉGE ---

// Adatbázis kapcsolat létrehozása
$db = DatabaseConnection::getInstance();
$conn = $db->getConnection();

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    header('Location: /Vizsga_oldal/auth/login.php');
    exit;
}

// Lekérjük a cég csomag adatait
$company_id = $_SESSION['company_id'];
$stmt = $conn->prepare("
    SELECT 
        sp.name as plan_name,
        sp.price,
        sp.description,
        DATE(s.next_billing_date) as next_billing_date,
        DATE(s.trial_end_date) as trial_end_date,
        CASE 
            WHEN sp.name = 'alap' THEN 'Alap csomag'
            WHEN sp.name = 'kozepes' THEN 'Közepes csomag'
            WHEN sp.name = 'uzleti' THEN 'Üzleti csomag'
            WHEN sp.name = 'alap_eves' THEN 'Alap csomag (Éves)'
            WHEN sp.name = 'kozepes_eves' THEN 'Közepes csomag (Éves)'
            WHEN sp.name = 'uzleti_eves' THEN 'Üzleti csomag (Éves)'
            WHEN sp.name = 'free-trial' THEN 'Ingyenes próbaidőszak'
            ELSE sp.name
        END as display_name,
        CASE 
            WHEN sp.name = 'alap' OR sp.name = 'alap_eves' THEN 5
            WHEN sp.name = 'kozepes' OR sp.name = 'kozepes_eves' THEN 10
            WHEN sp.name = 'uzleti' OR sp.name = 'uzleti_eves' THEN 20
            WHEN sp.name = 'free-trial' THEN 2
            ELSE 0
        END as base_users,
        CASE 
            WHEN sp.name = 'alap' OR sp.name = 'alap_eves' THEN 100
            WHEN sp.name = 'kozepes' OR sp.name = 'kozepes_eves' THEN 250
            WHEN sp.name = 'uzleti' OR sp.name = 'uzleti_eves' THEN 500
            WHEN sp.name = 'free-trial' THEN 10
            ELSE 0
        END as base_devices,
        s.subscription_status_id
    FROM subscriptions s
    JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
    WHERE s.company_id = :company_id 
    ORDER BY s.created_at DESC 
    LIMIT 1
");

$stmt->execute(['company_id' => $company_id]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Debug információ
error_log('Subscription data: ' . print_r($subscription, true));

// Ha nincs előfizetés vagy nem sikerült lekérni
if (!$subscription) {
    $subscription = [
        'plan_name' => 'Nincs aktív előfizetés',
        'price' => 0,
        'description' => '',
        'next_billing_date' => null,
        'trial_end_date' => null,
        'display_name' => 'Nincs aktív előfizetés',
        'base_users' => 0,
        'base_devices' => 0,
        'subscription_status_id' => null
    ];
}

// Debug log hozzáadása
error_log('Company ID: ' . $company_id);
error_log('Subscription Plan Name: ' . ($subscription['plan_name'] ?? 'null'));
error_log('Subscription Status ID: ' . ($subscription['subscription_status_id'] ?? 'null'));

// Lekérjük a legutóbbi módosítást, ha van
$modificationQuery = "SELECT modification_reason, price_difference, modification_date 
                     FROM subscription_modifications 
                     WHERE subscription_id = (SELECT id FROM subscriptions WHERE company_id = :company_id) 
                     ORDER BY modification_date DESC LIMIT 1";
$stmt = $conn->prepare($modificationQuery);
$stmt->execute(['company_id' => $company_id]);
$modification = $stmt->fetch(PDO::FETCH_ASSOC);

// Felhasználók és eszközök száma a csomag alapján
$users = $subscription['base_users'] ?? 0;
$devices = $subscription['base_devices'] ?? 0;
$packageName = $subscription['display_name'] ?? 'Nincs aktív előfizetés';

// Ha van módosítás, akkor azt használjuk
if ($modification && preg_match('/(\d+)\s+felhasználó,\s+(\d+)\s+eszköz/', $modification['modification_reason'], $matches)) {
    $users = $matches[1];
    $devices = $matches[2];
}

// Teljes ár számítása
$basePrice = $subscription['price'] ?? 0;
$totalPrice = $basePrice;

// Extra költségek számítása csak ha van módosítás
if ($users > ($subscription['base_users'] ?? 0) || $devices > ($subscription['base_devices'] ?? 0)) {
    $additionalUsers = $users - ($subscription['base_users'] ?? 0);
    $additionalDevices = $devices - ($subscription['base_devices'] ?? 0);
    
    $additionalUserCost = max(0, $additionalUsers) * 2000;
    $additionalDeviceCost = max(0, $additionalDevices) * 100;
    $totalPrice = $basePrice + $additionalUserCost + $additionalDeviceCost;
}

// Fetch user language
$user_id = $_SESSION['user_id'];
$languageQuery = "SELECT language FROM user WHERE id = :user_id";
$stmt = $conn->prepare($languageQuery);
$stmt->execute(['user_id' => $user_id]);
$userLanguage = $stmt->fetch(PDO::FETCH_ASSOC)['language'];

// Lekérjük az összes előfizetési csomagot
$stmt = $conn->prepare("SELECT id, name, price, description FROM subscription_plans ORDER BY price ASC");
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debugging: Check if package_id is set
error_log('GET package_id: ' . ($_GET['package_id'] ?? 'not set'));

// Ensure $packageId is initialized
$packageId = $_GET['package_id'] ?? null;

if ($packageId === null) {
    // Alapértelmezett csomag azonosító beállítása vagy üzenet elrejtése
    $packageId = 1; // Például az alapértelmezett csomag ID
    // echo '<p>Hiányzó csomag azonosító</p>';
    // $packagePrice = 'N/A';
}

// Csomag árának megjelenítése
$stmt = $conn->prepare("
    SELECT sp.id, sp.price, sp.name, sp.billing_interval_id, bi.name as interval_name 
    FROM subscription_plans sp
    JOIN billing_intervals bi ON sp.billing_interval_id = bi.id
    WHERE sp.id = :package_id
");
$stmt->execute(['package_id' => $packageId]);
$package = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$package) {
    $packagePrice = 'N/A';
} else {
    $packagePrice = number_format($package['price'], 2, '.', ',');
    $intervalName = $package['interval_name'];
}

// Debug információk
error_log('Package Price: ' . $packagePrice);
error_log('Package Name: ' . $packageName);
error_log('Interval Name: ' . $intervalName);

// Távolítsuk el az árat megjelenítő kódot a kártya felett
// echo '<p>Csomag ára: ' . htmlspecialchars((string)$packagePrice) . ' Ft</p>';
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Csomag/Előfizetés</title>
    <link rel="stylesheet" href="/Vizsga_oldal/assets/css/style.css">
    <style>
        .wrapper {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 30px;
            padding: 20px;
            margin-top: 50px;
        }

        .container {
            min-height: 400px;
            width: 450px;
            padding: 20px;
            background-color: #2c3e50;
            border-radius: 10px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            text-align: left;
            color: #fff;
            position: relative;
        }

        .modification-card {
            min-height: 400px;
            width: 450px;
            padding: 30px;
            background-color: #2c3e50;
            border-radius: 10px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            text-align: left;
            color: #fff;
            display: none;
            position: relative;
            overflow: hidden;
        }
        
        .subscription-details {
            position: relative;
        }
        
        .btn-cancel {
            background-color: transparent;
            border: none;
            cursor: pointer;
            position: absolute;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            transition: all 0.3s ease;
            padding: 0;
            top: 10px;
            right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            opacity: 1;
        }
        
        .modification-card h2 {
            font-size: 24px;
            margin-bottom: 25px;
            color: #fff;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
        }
        
        .modification-card .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .modification-card .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #fff;
            font-weight: 500;
        }
        
        .modification-card .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 16px;
        }
        
        .modification-card .form-group .info-text {
            display: block;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 8px;
            font-size: 14px;
        }
        
        .modification-card .form-group .cost-info {
            display: block;
            margin-top: 8px;
            font-size: 15px;
            font-weight: 500;
        }
        
        .modification-card .price-summary {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .modification-card .price-summary .total-price {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .modification-card .price-summary .new-price {
            font-size: 22px;
            font-weight: 700;
            color: #4CAF50;
        }
        
        .modification-card button[type="submit"] {
            width: 100%;
            padding: 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .modification-card button[type="submit"]:hover {
            background-color: #45a049;
            transform: translateY(-2px);
        }

        .btn-cancel img {
            width: 30px;
            height: 30px;
            object-fit: contain;
            border-radius: 50%;
        }

        .subscription-details h2 {
            padding-right: 40px;
            margin-top: 0;
        }

        .btn-cancel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #4CAF50;
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: -1;
        }

        .btn-cancel:hover::before {
            opacity: 0.2;
        }

        .subscription-details p, .modification-card label {
            font-size: 16px;
            margin: 5px 0;
            color: white;
            font-weight: bold;
        }
        .subscription-details .price {
            font-size: 20px;
            font-weight: bold;
            margin: 10px 0;
        }
        .modify-package-btn, .change-package-btn, .btn-cancel, .modification-card button[type="submit"] {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            transition: background-color 0.3s;
        }
        .modify-package-btn:hover, .change-package-btn:hover, .btn-cancel:hover, .modification-card button[type="submit"]:hover {
            background-color: #45a049;
        }
        .modification-card input[type="number"] {
            width: 100%;
            padding: 8px;
            margin: 5px 0 10px 0;
            border: none;
            border-radius: 4px;
            box-sizing: border-box;
        }
        #additionalCost {
            margin-top: 20px;
            padding: 10px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            color: white;
        }
        .modification-card form {
            flex-grow: 1;
        }
        .modification-card .form-group {
            margin-bottom: 20px;
        }
        .modification-card .form-group label {
            display: block;
            margin-bottom: 5px;
            color: white;
        }
        .modification-card .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
            color: #333;
        }
        .modification-card .form-group .cost-info {
            color: white;
            margin-top: 5px;
            font-size: 14px;
        }
        .modification-card .bottom-section {
            margin-top: 20px;
        }
        .modification-card .bottom-section button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .modification-card .bottom-section button:hover {
            background-color: #45a049;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
            display: none;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        .pricing-container {
            display: flex;
            justify-content: center;
            gap: 30px;
            padding: 20px;
            flex-wrap: nowrap;
            margin: 0 auto;
        }

        .pricing-card {
            flex: 1;
            min-width: 300px;
            max-width: 400px;
            background: #ffffff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }

        .card-header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 20px;
            font-weight: 600;
        }

        .card-body {
            padding: 30px 20px;
            text-align: center;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .price {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 700;
            min-height: 80px;
        }

        .price small {
            font-size: 16px;
            color: #666;
            font-weight: normal;
        }

        .features-list {
            list-style: none;
            padding: 0;
            margin: 0 0 30px;
            flex-grow: 1;
        }

        .features-list li {
            padding: 12px 0;
            color: #666;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .features-list li:last-child {
            border-bottom: none;
        }

        .btn-choose {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            width: 100%;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 3px 10px rgba(52, 152, 219, 0.2);
        }

        .btn-choose:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .package-box {
            background-color: #fff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin: 20px auto;
            display: grid;
            grid-template-columns: repeat(3, minmax(300px, 1fr)) 2px minmax(300px, 1fr);
            gap: 20px;
            max-width: 1800px;
            width: 98%;
            align-items: stretch;
            position: relative;
            overflow: visible;
        }

        .package-divider {
            width: 2px;
            height: auto;
            background: #34495e;
            margin: 0 -10px 0 -30px;
            position: relative;
            grid-column: 4;
            align-self: stretch;
        }

        .current-package {
            grid-column: 5;
            border: 5px solid #3498db;
            box-shadow: 0 8px 24px rgba(52, 152, 219, 0.3);
            position: relative;
            overflow: visible;
            background: #fff;
            margin: 0;
            transform: none;
            min-width: 300px;
            height: 100%;
        }

        .current-package::before {
            content: '✓ Aktív csomag';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: #3498db;
            color: white;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: bold;
            white-space: nowrap;
            z-index: 1;
        }

        .package-divider::before {
            content: 'Jelenlegi előfizetés';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(90deg);
            background: #fff;
            padding: 10px 20px;
            color: #34495e;
            font-weight: bold;
            white-space: nowrap;
            margin-left: -10px;
        }

        .card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .features-list {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .features-list li {
            padding: 12px 0;
            color: #666;
            border-bottom: 1px solid #eee;
            text-align: center;
        }

        .features-list li:last-child {
            border-bottom: none;
        }

        .package-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2002;
            overflow-y: auto;
            padding: 20px;
        }

        .package-modal-content {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 1400px;
            padding: 40px;
            position: relative;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .close-package-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            font-weight: bold;
            color: #ff0000;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
        }

        .subscription-toggle {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 30px 0;
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 50px;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }

        .toggle-button {
            padding: 12px 25px;
            border: none;
            background: none;
            color: #666;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 16px;
        }

        .toggle-button.active {
            background: #4CAF50;
            color: white;
            box-shadow: 0 4px 8px rgba(76, 175, 80, 0.3);
        }

        .pricing-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .pricing-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #eee;
            position: relative;
            overflow: hidden;
        }

        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .pricing-card h4 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .features-list {
            list-style: none;
            padding: 0;
            margin: 0 0 30px 0;
            text-align: left;
        }

        .features-list li {
            padding: 10px 0;
            color: #666;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }

        .features-list li:before {
            content: "✓";
            color: #4CAF50;
            margin-right: 10px;
            font-weight: bold;
        }

        .features-list li:last-child {
            border-bottom: none;
        }

        .price {
            font-size: 36px;
            color: #333;
            margin: 20px 0;
            font-weight: 700;
        }

        .price small {
            font-size: 16px;
            color: #999;
            font-weight: normal;
        }

        .select-package-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .select-package-btn:hover {
            background-color: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 18px;
            display: block;
            margin-bottom: 5px;
        }

        /* Package Change Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow-y: auto;
            padding: 20px;
        }

        .modal-content {
            background-color: #fff;
            margin: 50px auto;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            width: 90%;
            max-width: 1400px;
            text-align: center;
        }

        #confirmDialog .modal-content {
            padding: 30px;
            text-align: center;
        }

        #confirmDialog h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
        }

        #confirmDialog p {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        #confirmDialog .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }

        #confirmDialog button {
            padding: 12px 40px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        #confirmDialog #confirmYes {
            background-color: #4CAF50;
            color: white;
        }

        #confirmDialog #confirmNo {
            background-color: #e0e0e0;
            color: #333;
        }

        #confirmDialog button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        #confirmDialog #confirmYes:hover {
            background-color: #45a049;
        }

        #confirmDialog #confirmNo:hover {
            background-color: #d5d5d5;
        }

        @media (max-width: 768px) {
            .pricing-container {
                flex-direction: column;
            }
            
            .pricing-card {
                width: 100%;
            }
        }

        .warning-message {
            background-color: rgba(255, 68, 68, 0.1);
            border-left: 4px solid #ff4444;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
            line-height: 1.5;
        }

        .warning-message ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .warning-message li {
            margin-bottom: 5px;
        }

        .blurred {
            filter: blur(5px) brightness(0.8);
            transition: filter 0.3s;
            pointer-events: none;
        }
        
        /* MODAL OVERLAY Z-INDEX JAVÍTÁS */
        .modal, #packageModal, .package-modal {
            z-index: 2002 !important;
        }

        /* Lemondás modal stílusok */
        #cancelModal .modal-content, #secondCancelModal .modal-content {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            padding: 40px 30px 30px 30px;
            max-width: 900px;
            margin: 40px auto;
            text-align: center;
            position: relative;
        }
        #cancelModal h2, #secondCancelModal h2 {
            font-size: 2rem;
            margin-bottom: 18px;
            font-weight: 700;
        }
        #cancelModal p, #secondCancelModal p {
            font-size: 1.15rem;
            margin-bottom: 30px;
        }
        #cancelModal .close, #secondCancelModal .close {
            position: absolute;
            top: 18px;
            right: 24px;
            font-size: 2rem;
            color: #888;
            cursor: pointer;
            background: none;
            border: none;
            z-index: 2;
        }
        #cancelModal .close:hover, #secondCancelModal .close:hover {
            color: #e74c3c;
        }
        .confirm-btn {
            background: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 14px 32px;
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0 10px 0 0;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
            box-shadow: 0 2px 8px rgba(76,175,80,0.08);
        }
        .confirm-btn:hover {
            background: #388e3c;
            transform: translateY(-2px) scale(1.03);
        }
        .cancel-btn {
            background: #e0e0e0;
            color: #333;
            border: none;
            border-radius: 8px;
            padding: 14px 32px;
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0 0 0 10px;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .cancel-btn:hover {
            background: #bdbdbd;
            color: #222;
            transform: translateY(-2px) scale(1.03);
        }
        @media (max-width: 700px) {
            #cancelModal .modal-content, #secondCancelModal .modal-content {
                padding: 20px 8px 18px 8px;
                max-width: 98vw;
            }
            .confirm-btn, .cancel-btn {
                width: 100%;
                margin: 10px 0 0 0;
                font-size: 1rem;
                padding: 12px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Package Change Modal -->
    
    <h1 style="text-align: center; margin-top: -120px; margin-bottom: 120px; font-size: 50px;">Csomag/Előfizetés</h1>
    
    <div class="alert alert-success" id="successAlert">A módosítás sikeresen megtörtént!</div>
    <div class="alert alert-danger" id="errorAlert"></div>
    
    <div class="wrapper">
        <div class="container">
            <div class="subscription-details">
                <button class="btn-cancel" onclick="openModal()" <?php echo ($subscription['plan_name'] === 'free-trial') ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                    <img src="/Vizsga_oldal/assets/img/power.png" alt="Cancel">
                </button>
                <h2>Csomag név: <?php echo htmlspecialchars($packageName); ?></h2>
                <p>Felhasználók száma: <?php echo $users; ?></p>
                <p>Eszközök száma: <?php echo $devices; ?></p>
                <?php if ($subscription['plan_name'] === 'free-trial'): ?>
                <p>Próbaidőszak lejárata: <?php echo htmlspecialchars($subscription['trial_end_date'] ?? 'Nincs megadva'); ?></p>
                <?php else: ?>
                <p>Következő fizetési időpont: <?php echo htmlspecialchars($subscription['next_billing_date'] ?? ''); ?></p>
                <?php endif; ?>
                <div style="margin-top: 70px; border-top: 1px solid #fff; padding-top: 10px;">
                    <p class="price">Ár: <?php echo number_format($totalPrice, 2); ?> Ft</p>
                </div>
                <?php if ($subscription['plan_name'] === 'free-trial'): ?>
                <button class="modify-package-btn" disabled style="opacity: 0.5; cursor: not-allowed;">Csomag módosítás</button>
                <?php else: ?>
                <button class="modify-package-btn">Csomag módosítás</button>
                <?php endif; ?>
                <button class="change-package-btn">Csomag váltás</button>
            </div>
        </div>

        <div class="modification-card" id="modificationCard">
            <h2>Módosítási lehetőségek</h2>
            <form id="modifyForm">
                <div class="form-group">
                    <label for="users">Felhasználók számának módosítása</label>
                    <input type="number" id="users" name="users" value="0" min="<?php echo -max(0, $users - $subscription['base_users']); ?>">
                    <span class="cost-info" id="userCostInfo"></span>
                    <small class="info-text">Jelenlegi: <?php echo $users; ?> felhasználó (Minimum: <?php echo $subscription['base_users']; ?>)</small>
                </div>
                
                <div class="form-group">
                    <label for="devices">Eszközök számának módosítása</label>
                    <input type="number" id="devices" name="devices" value="0" min="<?php echo -max(0, $devices - $subscription['base_devices']); ?>">
                    <span class="cost-info" id="deviceCostInfo"></span>
                    <small class="info-text">Jelenlegi: <?php echo $devices; ?> eszköz (Minimum: <?php echo $subscription['base_devices']; ?>)</small>
                </div>

                <div class="price-summary">
                    <div class="total-price">Összesen: <span id="additionalCost">0 Ft</span></div>
                    <div class="new-price">Új fizetendő ár: <span id="newTotalPrice"><?php echo number_format($basePrice, 0, '.', ' '); ?> Ft</span></div>
                </div>

                <button type="submit">Mentés</button>
            </form>
        </div>
    </div>
    <div id="packageModal" class="modal">
        <div class="modal-content">
            <span class="close-package-modal" onclick="closePackageModal()">×</span>
            <h2 class="modal-title">Fizetős csomagok</h2>
            
            <div class="subscription-toggle">
                <button class="toggle-button active" onclick="togglePricing('monthly')">Havi előfizetés</button>
                <button class="toggle-button" onclick="togglePricing('yearly')">Éves előfizetés <span style="color: #ff4444;">(15% kedvezmény)</span></button>
            </div>

            <div class="pricing-container">
                <div class="pricing-card">
                    <h4>Alap csomag</h4>
                    <div class="price">
                        <span class="monthly-price">29,990 Ft</span>
                        <small>/hó</small>
                    </div>
                    <ul class="features-list">
                        <li>5 felhasználó</li>
                        <li>100 eszköz kezelése</li>
                        <li>Alapvető jelentések</li>
                        <li>Email támogatás</li>
                    </ul>
                    <button class="select-package-btn" data-package-type="basic">Kiválasztás</button>
                </div>

                <div class="pricing-card">
                    <h4>Közepes csomag</h4>
                    <div class="price">
                        <span class="monthly-price">55,990 Ft</span>
                        <small>/hó</small>
                    </div>
                    <ul class="features-list">
                        <li>10 felhasználó</li>
                        <li>250 eszköz kezelése</li>
                        <li>Részletes jelentések</li>
                        <li>Prioritásos támogatás</li>
                    </ul>
                    <button class="select-package-btn" data-package-type="pro">Kiválasztás</button>
                </div>

                <div class="pricing-card">
                    <h4>Üzleti csomag</h4>
                    <div class="price">
                        <span class="monthly-price">80,990 Ft</span>
                        <small>/hó</small>
                    </div>
                    <ul class="features-list">
                        <li>20 felhasználó</li>
                        <li>500 eszköz kezelése</li>
                        <li>Részletes jelentések</li>
                        <li>Prioritásos támogatás</li>
                        <li>Telefonos segítségnyújtás</li>
                    </ul>
                    <button class="select-package-btn" data-package-type="enterprise">Kiválasztás</button>
                </div>
            </div>
        </div>
    </div>
    <div id="confirmDialog" class="modal">
        <div class="modal-content">
            <h3>Csomag váltás megerősítése</h3>
            <p>Biztosan szeretné váltani a csomagot <span id="selectedPackageName" style="color: #4CAF50; font-weight: bold;"></span> csomagra?</p>
            <div class="modal-buttons">
                <button id="confirmYes">Igen</button>
                <button id="confirmNo">Nem</button>
            </div>
        </div>
    </div>
    <div id="loadingIndicator" class="loading-overlay" style="display: none;">
        <div class="loading-spinner"></div>
        <p id="loadingMessage">Csomag váltás folyamatban...</p>
    </div>
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Előfizetés lemondása</h2>
            <p>Biztosan le szeretné mondani az előfizetését? Ha igen, a cégének az előfizetési státusza lemondott lesz.</p>
            <button class="confirm-btn" onclick="confirmCancellation()">Igen, lemondom</button>
            <button class="cancel-btn" onclick="closeModal()">Mégsem</button>
        </div>
    </div>
    <div id="secondCancelModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeSecondModal()">&times;</span>
            <h2>Biztosan le szeretné mondani?</h2>
            <p>Ha bármilyen problémája van a csomaggal, az árakkal, vagy az egész weboldallal, kérjük írjon nekünk a <a href="mailto:vibecore@example.com">vibecore@example.com</a> email címre.</p>
            <button class="confirm-btn" onclick="finalizeCancellation()">Igen, lemondom</button>
            <button class="cancel-btn" onclick="closeSecondModal()">Mégsem</button>
        </div>
    </div>
    <div id="farewellLoading" class="modal" style="display: none;">
        <div class="modal-content">
            <h2 id="farewellMessage"></h2>
            <div class="loading-spinner"></div>
        </div>
    </div>
    <script>
        const userLanguage = "<?php echo $userLanguage; ?>";

        // Jelenlegi csomag minimum értékei
        const packageMinimums = {
            'alap': { users: 5, devices: 100 },
            'kozepes': { users: 10, devices: 250 },
            'uzleti': { users: 20, devices: 500 }
        };

        // Aktuális csomag adatai
        const currentPackage = '<?php echo strtolower(explode(' ', $packageName)[0]); ?>'; // Első szó, kisbetűvel
        const currentUsers = <?php echo $users; ?>;
        const currentDevices = <?php echo $devices; ?>;
        const basePrice = <?php echo $basePrice; ?>;

        document.querySelector('.modify-package-btn').addEventListener('click', function() {
            <?php if ($subscription['plan_name'] === 'free-trial'): ?>
            // Prevent action for free-trial packages
            return;
            <?php else: ?>
            const modificationCard = document.getElementById('modificationCard');
            if (modificationCard.style.display === 'none' || modificationCard.style.display === '') {
                // Amikor megnyitjuk a módosítási kártyát, számoljuk ki a kezdeti árat
                const basePrice = <?php echo $basePrice; ?>;
                const baseUsers = <?php echo $subscription['base_users']; ?>;
                const baseDevices = <?php echo $subscription['base_devices']; ?>;
                const currentUsers = <?php echo $users; ?>;
                const currentDevices = <?php echo $devices; ?>;
                const userCost = 2000;
                const deviceCost = 100;

                // Input mezők alaphelyzetbe állítása
                document.getElementById('users').value = '0';
                document.getElementById('devices').value = '0';

                // Minimum értékek beállítása (nem mehet a csomag alapértékei alá)
                document.getElementById('users').min = -Math.max(0, currentUsers - baseUsers);
                document.getElementById('devices').min = -Math.max(0, currentDevices - baseDevices);

                // Különbözet számítása az aktuális és az alapcsomag között
                const additionalUsers = Math.max(0, currentUsers - baseUsers);
                const additionalDevices = Math.max(0, currentDevices - baseDevices);

                // Plusz költségek számítása
                const additionalUserCost = additionalUsers * userCost;
                const additionalDeviceCost = additionalDevices * deviceCost;
                const additionalCost = additionalUserCost + additionalDeviceCost;

                // Teljes ár számítása
                const totalPrice = basePrice + additionalCost;

                // Árak megjelenítése
                document.getElementById('additionalCost').textContent = `${additionalCost.toLocaleString('hu-HU')} Ft`;
                document.getElementById('newTotalPrice').textContent = `${totalPrice.toLocaleString('hu-HU')} Ft`;

                // Költség információk megjelenítése
                if (additionalUsers > 0) {
                    document.getElementById('userCostInfo').innerHTML = `+${additionalUsers} felhasználó (+${additionalUserCost.toLocaleString('hu-HU')} Ft)`;
                    document.getElementById('userCostInfo').style.color = '#4CAF50';
                }
                if (additionalDevices > 0) {
                    document.getElementById('deviceCostInfo').innerHTML = `+${additionalDevices} eszköz (+${additionalDeviceCost.toLocaleString('hu-HU')} Ft)`;
                    document.getElementById('deviceCostInfo').style.color = '#4CAF50';
                }
            }
            modificationCard.style.display = modificationCard.style.display === 'none' || modificationCard.style.display === '' ? 'block' : 'none';
            <?php endif; ?>
        });

        document.getElementById('modifyForm').addEventListener('input', function() {
            const basePrice = <?php echo $basePrice; ?>;
            const baseUsers = <?php echo $subscription['base_users']; ?>;
            const baseDevices = <?php echo $subscription['base_devices']; ?>;
            const currentUsers = <?php echo $users; ?>;
            const currentDevices = <?php echo $devices; ?>;
            const userCost = 2000;
            const deviceCost = 100;

            // Az input mezők értékeinek lekérése (a módosítás mértéke)
            const userChange = parseInt(document.getElementById('users').value) || 0;
            const deviceChange = parseInt(document.getElementById('devices').value) || 0;

            // Az új értékek kiszámítása
            const newUsers = currentUsers + userChange;
            const newDevices = currentDevices + deviceChange;

            // Különbözet számítása az új értékek és az alapcsomag között
            const additionalUsers = Math.max(0, newUsers - baseUsers);
            const additionalDevices = Math.max(0, newDevices - baseDevices);

            // Költségek számítása
            const additionalUserCost = additionalUsers * userCost;
            const additionalDeviceCost = additionalDevices * deviceCost;
            const totalAdditionalCost = additionalUserCost + additionalDeviceCost;

            // Új teljes ár számítása
            const newTotalPrice = basePrice + totalAdditionalCost;

            // Költség információk megjelenítése
            if (userChange !== 0) {
                const userCostDiff = userChange * userCost;
                document.getElementById('userCostInfo').innerHTML = `${userChange > 0 ? '+' : ''}${userChange} felhasználó (${userCostDiff > 0 ? '+' : ''}${userCostDiff.toLocaleString('hu-HU')} Ft)`;
                document.getElementById('userCostInfo').style.color = userChange > 0 ? '#4CAF50' : '#ff4444';
            } else {
                document.getElementById('userCostInfo').innerHTML = '';
            }

            if (deviceChange !== 0) {
                const deviceCostDiff = deviceChange * deviceCost;
                document.getElementById('deviceCostInfo').innerHTML = `${deviceChange > 0 ? '+' : ''}${deviceChange} eszköz (${deviceCostDiff > 0 ? '+' : ''}${deviceCostDiff.toLocaleString('hu-HU')} Ft)`;
                document.getElementById('deviceCostInfo').style.color = deviceChange > 0 ? '#4CAF50' : '#ff4444';
            } else {
                document.getElementById('deviceCostInfo').innerHTML = '';
            }

            // Összesítés megjelenítése
            const totalChange = (userChange * userCost) + (deviceChange * deviceCost);
            document.getElementById('additionalCost').textContent = `${totalChange.toLocaleString('hu-HU')} Ft`;
            document.getElementById('newTotalPrice').textContent = `${newTotalPrice.toLocaleString('hu-HU')} Ft`;
        });

        document.getElementById('modifyForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const users = parseInt(document.getElementById('users').value) || 0;
            const devices = parseInt(document.getElementById('devices').value) || 0;
            
            // Aktuális értékek hozzáadása a módosításokhoz
            const currentUsers = <?php echo $users; ?>;
            const currentDevices = <?php echo $devices; ?>;
            
            // Végső értékek számítása
            const finalUsers = currentUsers + users;
            const finalDevices = currentDevices + devices;

            // Loading indicator megjelenítése
            document.getElementById('loadingIndicator').style.display = 'flex';

            // AJAX call to update the database
            fetch('/Vizsga_oldal/dashboard/update_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    users: finalUsers,
                    devices: finalDevices,
                    changes: {
                        users: users,
                        devices: devices
                    }
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Loading indicator elrejtése
                document.getElementById('loadingIndicator').style.display = 'none';
                
                if (data.success) {
                    // Show success message
                    showAlert('success', 'A módosítás sikeresen mentve! Az oldal újratöltődik...');
                    
                    // Hide modification card
                    document.getElementById('modificationCard').style.display = 'none';
                    
                    // Reload the page after 2 seconds
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    // Show error message
                    showAlert('error', data.error || 'Hiba történt a módosítás során.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Loading indicator elrejtése
                document.getElementById('loadingIndicator').style.display = 'none';
                
                showAlert('error', 'Hiba történt a kérés során. Kérjük, próbálja újra.');
            });
        });

        document.querySelector('.change-package-btn').addEventListener('click', function() {
            document.getElementById('packageModal').style.display = 'block';
        });

        document.querySelectorAll('.toggle-button').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.toggle-button').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Check if this is the yearly button
                const isYearly = this.textContent.toLowerCase().includes('éves');
                togglePricing(isYearly ? 'yearly' : 'monthly');
            });
        });

        function togglePricing(type) {
            const prices = document.querySelectorAll('.monthly-price');
            prices.forEach(price => {
                let basePrice = parseInt(price.textContent.replace(/[^\d]/g, ''));
                
                // Ha először váltunk éves előfizetésre, akkor először osszuk el 12-vel
                if (price.dataset.originalPrice === undefined) {
                    price.dataset.originalPrice = basePrice;
                } else {
                    basePrice = parseInt(price.dataset.originalPrice);
                }
                
                if (type === 'yearly') {
                    // 15% kedvezmény az éves előfizetésnél
                    const yearlyPrice = Math.round(basePrice * 12 * 0.85);
                    price.textContent = (yearlyPrice).toLocaleString('hu-HU') + ' Ft';
                    price.nextElementSibling.textContent = '/év';
                } else {
                    price.textContent = basePrice.toLocaleString('hu-HU') + ' Ft';
                    price.nextElementSibling.textContent = '/hó';
                }
            });
        }

        function showAlert(type, message) {
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');
            
            if (type === 'success') {
                successAlert.textContent = message;
                successAlert.style.display = 'block';
                errorAlert.style.display = 'none';
            } else {
                errorAlert.textContent = message;
                errorAlert.style.display = 'block';
                successAlert.style.display = 'none';
            }
        }

        let selectedPackageId = null;
        let isYearly = false; // Globális változó az éves/havi előfizetéshez

        function selectPackage(packageType) {
            let packageId;
            let packageName;
            let users;
            let devices;
            
            // Check if yearly subscription is selected
            const yearlyButton = document.querySelector('.toggle-button:not(.active)');
            const isYearly = yearlyButton && yearlyButton.textContent.includes('Havi');
            
            console.log('Package Type:', packageType);
            console.log('Is Yearly:', isYearly);
            
            // Csomag adatok beállítása
            switch(packageType) {
                case 'basic':
                    packageId = isYearly ? 'alap_eves' : 'alap';
                    packageName = 'Alap csomag' + (isYearly ? ' (Éves)' : '');
                    users = 5;
                    devices = 100;
                    break;
                case 'pro':
                    packageId = isYearly ? 'kozepes_eves' : 'kozepes';
                    packageName = 'Közepes csomag' + (isYearly ? ' (Éves)' : '');
                    users = 10;
                    devices = 250;
                    break;
                case 'enterprise':
                    packageId = isYearly ? 'uzleti_eves' : 'uzleti';
                    packageName = 'Üzleti csomag' + (isYearly ? ' (Éves)' : '');
                    users = 20;
                    devices = 500;
                    break;
                default:
                    console.error('Érvénytelen csomag típus');
                    return;
            }

            console.log('Selected Package ID:', packageId);
            console.log('Selected Package Name:', packageName);

            // Get current user and device counts
            fetch('/Vizsga_oldal/dashboard/get_company_counts.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const currentUsers = data.userCount;
                        const currentDevices = data.deviceCount;
                        
                        // Check if the new package has lower limits
                        const excessUsers = Math.max(0, currentUsers - users);
                        const excessDevices = Math.max(0, currentDevices - devices);
                        
                        if (excessUsers > 0 || excessDevices > 0) {
                            // Show warning about excess users/devices
                            let warningMessage = 'A kiválasztott csomag kevesebb felhasználót és/vagy eszközt engedélyez, mint amennyi jelenleg van a rendszerben.<br><br>';
                            
                            if (excessUsers > 0) {
                                warningMessage += `- ${excessUsers} felhasználót kell törölnie a rendszerből<br>`;
                            }
                            
                            if (excessDevices > 0) {
                                warningMessage += `- ${excessDevices} eszközt kell törölnie a rendszerből<br>`;
                            }
                            
                            warningMessage += '<br>Kérjük, törölje a felesleges felhasználókat és/vagy eszközöket a csomag váltása előtt.';
                            
                            // Update the confirmation dialog with the warning
                            const confirmDialog = document.getElementById('confirmDialog');
                            const confirmContent = confirmDialog.querySelector('.modal-content');
                            
                            // Remove any existing warning messages
                            const existingWarnings = confirmContent.querySelectorAll('.warning-message');
                            existingWarnings.forEach(warning => warning.remove());
                            
                            // Remove any existing notes
                            const existingNotes = confirmContent.querySelectorAll('p:not(:first-child)');
                            existingNotes.forEach(note => {
                                if (note.textContent.includes('Figyelem:')) {
                                    note.remove();
                                }
                            });
                            
                            // Add warning message before the buttons
                            const warningDiv = document.createElement('div');
                            warningDiv.className = 'warning-message';
                            warningDiv.innerHTML = warningMessage;
                            warningDiv.style.color = '#ff4444';
                            warningDiv.style.marginBottom = '20px';
                            warningDiv.style.textAlign = 'left';
                            
                            // Insert warning before the buttons
                            const buttonsDiv = confirmContent.querySelector('.modal-buttons');
                            confirmContent.insertBefore(warningDiv, buttonsDiv);
                            
                            // Update the confirmation message
                            const confirmMessage = confirmContent.querySelector('p');
                            confirmMessage.innerHTML = `Biztosan szeretné váltani a csomagot <span id="selectedPackageName" style="color: #4CAF50; font-weight: bold;">${packageName}</span> csomagra?`;
                            
                            // Add a note about the warning
                            const noteDiv = document.createElement('p');
                            noteDiv.innerHTML = '<strong>Figyelem:</strong> A csomag váltás után a felesleges felhasználók és eszközök nem lesznek elérhetők.';
                            noteDiv.style.color = '#ff4444';
                            noteDiv.style.marginTop = '10px';
                            confirmContent.insertBefore(noteDiv, buttonsDiv);
                        } else {
                            // No excess users/devices, proceed normally
                            const selectedPackageNameSpan = document.getElementById('selectedPackageName');
                            if (selectedPackageNameSpan) {
                                selectedPackageNameSpan.textContent = packageName;
                            }
                            
                            // Remove any existing warning messages
                            const confirmDialog = document.getElementById('confirmDialog');
                            const confirmContent = confirmDialog.querySelector('.modal-content');
                            
                            const existingWarnings = confirmContent.querySelectorAll('.warning-message');
                            existingWarnings.forEach(warning => warning.remove());
                            
                            // Remove any existing notes
                            const existingNotes = confirmContent.querySelectorAll('p:not(:first-child)');
                            existingNotes.forEach(note => {
                                if (note.textContent.includes('Figyelem:')) {
                                    note.remove();
                                }
                            });
                            
                            // Update the confirmation message
                            const confirmMessage = confirmContent.querySelector('p');
                            confirmMessage.innerHTML = `Biztosan szeretné váltani a csomagot <span id="selectedPackageName" style="color: #4CAF50; font-weight: bold;">${packageName}</span> csomagra?`;
                        }
                        
                        // Store the selected package ID
                        selectedPackageId = packageId;
                        
                        // Show the confirmation dialog
                        document.getElementById('confirmDialog').style.display = 'block';
                    } else {
                        console.error('Failed to get company counts:', data.error);
                        alert('Hiba történt a csomag adatok lekérése során. Kérjük, próbálja újra.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching company counts:', error);
                    alert('Hiba történt a csomag adatok lekérése során. Kérjük, próbálja újra.');
                });
        }

        function confirmPackageChange() {
            if (!selectedPackageId) {
                console.error('Nincs kiválasztott csomag');
                return;
            }

            console.log('Confirming package change with ID:', selectedPackageId);

            // Loading indicator megjelenítése
            document.getElementById('loadingIndicator').style.display = 'flex';
            
            // Ellenőrizzük, hogy melyik gomb van kiválasztva
            const yearlyButton = document.querySelector('.toggle-button:not(.active)');
            const isYearly = yearlyButton && yearlyButton.textContent.includes('Havi');

            console.log('Package change parameters:', {
                packageId: selectedPackageId,
                isYearly: isYearly
            });

            // Megerősítő ablak bezárása azonnal
            document.getElementById('confirmDialog').style.display = 'none';

            // AJAX kérés a csomag váltáshoz
            fetch('/Vizsga_oldal/dashboard/update_package.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    package_name: selectedPackageId,
                    is_yearly: isYearly
                })
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                
                // Loading indicator elrejtése
                document.getElementById('loadingIndicator').style.display = 'none';
                
                if (data.success) {
                    // Sikeres üzenet megjelenítése
                    showAlert('success', 'A csomag sikeresen módosítva! Az oldal újratöltődik...');
                    
                    // Csomag modal bezárása
                    document.getElementById('packageModal').style.display = 'none';
                    
                    // Oldal újratöltése 2 másodperc múlva
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    // Hiba üzenet megjelenítése
                    showAlert('error', data.error || 'Hiba történt a csomag módosítása során.');
                }
            })
            .catch(error => {
                console.error('Error during package change:', error);
                // Loading indicator elrejtése
                document.getElementById('loadingIndicator').style.display = 'none';
                
                showAlert('error', 'Hiba történt a kérés során.');
            });
        }

        // Eseménykezelők hozzáadása a megerősítő ablak gombjaihoz
        document.getElementById('confirmYes').addEventListener('click', confirmPackageChange);
        document.getElementById('confirmNo').addEventListener('click', function() {
            document.getElementById('confirmDialog').style.display = 'none';
        });

        function openModal() {
            <?php if ($subscription['plan_name'] === 'free-trial'): ?>
            alert('Az ingyenes próbaidőszak előfizetése nem mondható le.');
            return;
            <?php else: ?>
            document.getElementById('cancelModal').style.display = 'block';
            <?php endif; ?>
        }

        function closeModal() {
            document.getElementById('cancelModal').style.display = 'none';
        }

        function confirmCancellation() {
            closeModal();
            document.getElementById('secondCancelModal').style.display = 'block';
        }

        function closeSecondModal() {
            document.getElementById('secondCancelModal').style.display = 'none';
        }

        function finalizeCancellation() {
            fetch('/Vizsga_oldal/dashboard/cancel_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showFarewellMessage();
                } else {
                    alert('Hiba történt a lemondás során: ' + (data.error || 'Ismeretlen hiba'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Hiba történt a kérés során.');
            });
            closeSecondModal();
        }

        function showFarewellMessage() {
            const messages = {
                'hu': 'Köszönjük, hogy velünk dolgozott, mihamarabb várjuk vissza',
                'en': 'Thank you for working with us, we look forward to welcoming you back soon'
                // Add more languages as needed
            };

            const message = messages[userLanguage] || messages['en']; // Default to English if language not found
            document.getElementById('farewellMessage').textContent = message;
            document.getElementById('farewellLoading').style.display = 'block';

            // Redirect after 3 seconds
            setTimeout(() => {
                window.location.href = '/Vizsga_oldal/home.php';
            }, 3000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Eseménykezelők az Igen és Nem gombokhoz
            const confirmYesButton = document.getElementById('confirmYes');
            const confirmNoButton = document.getElementById('confirmNo');

            if (confirmYesButton) {
                confirmYesButton.addEventListener('click', confirmPackageChange);
            } else {
                console.error('A confirmYes gomb nem található');
            }

            if (confirmNoButton) {
                confirmNoButton.addEventListener('click', function() {
                    document.getElementById('confirmDialog').style.display = 'none';
                });
            } else {
                console.error('A confirmNo gomb nem található');
            }
        });

        // Package change modal functions
        function openPackageModal() {
            document.getElementById('packageModal').style.display = 'block';
            blurHeader();
        }

        function closePackageModal() {
            document.getElementById('packageModal').style.display = 'none';
            // Reset the toggle buttons to default state (monthly)
            document.querySelectorAll('.toggle-button').forEach(btn => {
                if (btn.textContent.toLowerCase().includes('havi')) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            // Reset prices to monthly
            togglePricing('monthly');
            unblurHeader();
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for(let modal of modals) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    unblurHeader();
                }
            }
        }

        // Bezárás ESC gombra
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.getElementsByClassName('modal');
                for(let modal of modals) {
                    modal.style.display = 'none';
                }
                unblurHeader();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Add click handlers to all package selection buttons
            document.querySelectorAll('.select-package-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const packageType = this.getAttribute('data-package-type');
                    console.log('Button clicked:', packageType);
                    if (packageType) {
                        selectPackage(packageType);
                    }
                });
            });
        });

        // HEADER BLUR SEGÉDFÜGGVÉNYEK
        function blurHeader() {
            const header = document.querySelector('.main-header');
            if (header) header.classList.add('blurred');
        }
        function unblurHeader() {
            const header = document.querySelector('.main-header');
            if (header) header.classList.remove('blurred');
        }
    </script>
</body>
</html> 