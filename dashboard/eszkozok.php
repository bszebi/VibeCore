<?php 
// Remove the session_start() from here since it will be handled in config.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/language_handler.php';
require_once __DIR__ . '/../includes/translation_helper.php';

// Session ellenőrzése a fájl elején
if (!isset($_SESSION['user_id'])) {
    header("Location: /Vizsga_oldal/login.php");
    exit;
}

// Felhasználó company_id lekérése
$db = DatabaseConnection::getInstance()->getConnection();
$stmt = $db->prepare("SELECT company_id FROM user WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company_id = $stmt->fetchColumn();

if (!$company_id) {
    $_SESSION['error'] = translate("Nincs jogosultsága az eszközök megtekintéséhez!");
    header("Location: /Vizsga_oldal/dashboard/index.php");
    exit;
}

// Szerkeszthető státuszok lekérdezése
$editable_statuses = $db->query("
    SELECT * FROM stuff_status 
    WHERE name IN ('" . translate('Hibás') . "', '" . 
    translate('Törött') . "', '" . 
    translate('Kiszelektálás alatt') . "')
")->fetchAll(PDO::FETCH_ASSOC);

// POST kérés feldolgozása
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Új eszköz hozzáadása
    if (isset($_POST['type_id'])) {
        try {
            // Check subscription plan device limit
            $stmt = $db->prepare("
                SELECT 
                    sp.description as plan_description,
                    (SELECT COUNT(*) FROM stuffs WHERE company_id = ?) as current_device_count
                FROM subscriptions s
                JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
                WHERE s.company_id = ? 
                AND s.subscription_status_id = 1
                ORDER BY s.start_date DESC 
                LIMIT 1
            ");
            $stmt->execute([$company_id, $company_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                $error = translate("No active subscription found.");
            } else {
                // Extract device limit from plan description
                preg_match('/(\d+)\s+eszköz/', $result['plan_description'], $matches);
                $device_limit = isset($matches[1]) ? (int)$matches[1] : 0;
                $current_device_count = (int)$result['current_device_count'];
                $mennyiseg = isset($_POST['mennyiseg']) ? intval($_POST['mennyiseg']) : 1;

                if (($current_device_count + $mennyiseg) > $device_limit) {
                    $error = translate("Device limit exceeded. Your plan allows") . " {$device_limit} " . 
                            translate("devices, you currently have") . " {$current_device_count} " . 
                            translate("devices.");
                    $show_limit_modal = true;
                }
            }

            if (!isset($error)) {
                $stmt = $db->prepare("
                    INSERT INTO stuffs (
                        type_id,
                        secondtype_id,
                        brand_id,
                        model_id,
                        manufacture_date,
                        stuff_status_id,
                        qr_code,
                        company_id
                    ) VALUES (
                        :type_id,
                        :secondtype_id,
                        :brand_id,
                        :model_id,
                        :manufacture_date,
                        :stuff_status_id,
                        :qr_code,
                        :company_id
                    )
                ");
                
                $stmt->execute([
                    ':type_id' => $_POST['type_id'],
                    ':secondtype_id' => $_POST['secondtype_id'],
                    ':brand_id' => $_POST['brand_id'],
                    ':model_id' => $_POST['model_id'],
                    ':manufacture_date' => $_POST['manufacture_date'],
                    ':stuff_status_id' => $_POST['stuff_status_id'],
                    ':qr_code' => $_POST['qr_code'],
                    ':company_id' => $company_id
                ]);
                
                $_SESSION['success_message'] = translate("Az eszköz sikeresen hozzáadva!");
                header("Location: /Vizsga_oldal/dashboard/eszkozok.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = translate("Hiba történt a mentés során: ") . $e->getMessage();
        }
    }
    
    // Törlés kezelése
    if (isset($_POST['delete_id'])) {
        try {
            // Ellenőrizzük, hogy a törölni kívánt eszköz a felhasználó cégéhez tartozik-e
            $stmt = $db->prepare("SELECT company_id FROM stuffs WHERE id = ?");
            $stmt->execute([$_POST['delete_id']]);
            $stuff_company_id = $stmt->fetchColumn();

            if ($stuff_company_id != $company_id) {
                throw new Exception(translate("Nincs jogosultsága törölni ezt az eszközt!"));
            }

            $stmt = $db->prepare("DELETE FROM stuffs WHERE id = ? AND company_id = ?");
            $stmt->execute([$_POST['delete_id'], $company_id]);
            
            $_SESSION['success_message'] = translate("Az eszköz sikeresen törölve!");
            header("Location: /Vizsga_oldal/dashboard/eszkozok.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = translate("Hiba történt a törlés során: ") . $e->getMessage();
            header("Location: /Vizsga_oldal/dashboard/eszkozok.php");
            exit;
        }
    }
}

// ... existing code ...
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_eszkoz') {
    $eszkoz_nev = $_POST['eszkoz_nev'];
    $mennyiseg = isset($_POST['mennyiseg']) ? (int)$_POST['mennyiseg'] : 1;
    
    // Ellenőrizzük, hogy a mennyiség pozitív szám-e
    if ($mennyiseg < 1) {
        $mennyiseg = 1;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO eszkozok (nev, status_id, created_at) VALUES (:nev, :status_id, NOW())");
        
        // Többszörös beszúrás végrehajtása
        $pdo->beginTransaction();
        for ($i = 0; $i < $mennyiseg; $i++) {
            $stmt->execute([
                'nev' => $eszkoz_nev,
                'status_id' => 1 // Alapértelmezett státusz ID
            ]);
        }
        $pdo->commit();
        
        $_SESSION['success_message'] = translate("Sikeresen hozzáadva ") . $mennyiseg . translate(" db eszköz!");
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = translate("Hiba történt az eszközök hozzáadása közben: ") . $e->getMessage();
    }
    
    header('Location: eszkozok.php');
    exit();
}
// ... existing code ...

// Layout betöltése - javított útvonal
require_once '../includes/layout/header.php'; 

// Get subscription info and device count
try {
    $stmt = $db->prepare("
        SELECT 
            sp.description as plan_description,
            (SELECT COUNT(*) FROM stuffs WHERE company_id = ?) as current_device_count
        FROM subscriptions s
        JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
        WHERE s.company_id = ? 
        AND s.subscription_status_id = 1
        ORDER BY s.start_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$company_id, $company_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Extract device limit from plan description
        preg_match('/(\d+)\s+eszköz/', $result['plan_description'], $matches);
        $device_limit = isset($matches[1]) ? (int)$matches[1] : 0;
        $current_device_count = (int)$result['current_device_count'];
        
        echo '<div class="subscription-info" style="position: fixed; top: 70px; left: 20px; z-index: 1000; margin: 0; padding: 10px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; text-align: left; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 300px;">';
        echo translate('A csomaggal felvehető eszközök') . ': <span style="font-weight: bold;">' . $current_device_count . '/' . $device_limit . '</span>';
        echo '</div>';
    }
} catch (Exception $e) {
    error_log("Error getting subscription info: " . $e->getMessage());
}

// Database kapcsolat inicializálása
$db = DatabaseConnection::getInstance()->getConnection();

// Márkák, modellek és állapotok lekérdezése
$stuff_types = $db->query("SELECT * FROM stuff_type")->fetchAll(PDO::FETCH_ASSOC);
$stuff_brands = $db->query("SELECT * FROM stuff_brand")->fetchAll(PDO::FETCH_ASSOC);
$stuff_models = $db->query("SELECT * FROM stuff_model")->fetchAll(PDO::FETCH_ASSOC);
$stuff_statuses = $db->query("SELECT * FROM stuff_status")->fetchAll(PDO::FETCH_ASSOC);
$stuff_secondtypes = $db->query("SELECT * FROM stuff_secondtype")->fetchAll(PDO::FETCH_ASSOC);
// Gyártási évek lekérdezése
$manufacture_dates = $db->query("SELECT DISTINCT id, year FROM stuff_manufacture_date ORDER BY year DESC")->fetchAll(PDO::FETCH_ASSOC);

// Sikeres üzenet megjelenítése és törlése
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Előre definiált színek a típusokhoz
$type_colors = [
    'Hangtechnika' => '#FF6B6B',
    'Fénytechnika' => '#4ECDC4',
    'Vizuáltechnika' => '#45B7D1',
    'Színpad' => '#96CEB4',
    'Pyrotechnika' => '#FF9F43',
    'Színpad fedés' => '#A3CB38',
    'Minden' => '#786FA6'
];

// Alapértelmezett szín ha nincs definiálva
$default_color = '#808080';

// Eszközök lekérdezése módosítása
$stmt = $db->prepare("
    SELECT s.*, 
           st.name as type_name,
           sst.name as secondtype_name,
           sb.name as brand_name,
           sm.name as model_name,
           ss.name as status_name,
           smd.year as manufacture_year,
           MAX(m.id) as maintenance_id,
           MAX(m.maintenance_status_id) as maintenance_status_id
    FROM stuffs s
    LEFT JOIN stuff_type st ON s.type_id = st.id
    LEFT JOIN stuff_secondtype sst ON s.secondtype_id = sst.id
    LEFT JOIN stuff_brand sb ON s.brand_id = sb.id
    LEFT JOIN stuff_model sm ON s.model_id = sm.id
    LEFT JOIN stuff_status ss ON s.stuff_status_id = ss.id
    LEFT JOIN stuff_manufacture_date smd ON s.manufacture_date = smd.id
    LEFT JOIN maintenance m ON s.id = m.stuffs_id AND m.maintenance_status_id NOT IN (
        SELECT id FROM maintenance_status WHERE name IN ('Befejezve', 'Törölve')
    )
    WHERE s.company_id = ?
    GROUP BY s.id
    ORDER BY s.favourite DESC, s.id ASC
");

$stmt->execute([$company_id]);

// Típusok és darabszámok lekérdezése módosítása
$type_counts = $db->prepare("
    SELECT 
        st.id,
        st.name,
        COUNT(s.id) as count
    FROM stuff_type st
    LEFT JOIN stuffs s ON st.id = s.type_id AND s.company_id = ?
    GROUP BY st.id, st.name
    ORDER BY st.id ASC  /* Módosítva az id szerinti rendezésre */
");
$type_counts->execute([$company_id]);
$type_stats = $type_counts->fetchAll(PDO::FETCH_ASSOC);

// Státuszok lekérdezése és számolása - csak a megjelenítendő státuszok
$status_counts = $db->prepare("
    SELECT 
        ss.id,
        ss.name,
        COUNT(s.id) as count
    FROM stuff_status ss
    LEFT JOIN stuffs s ON ss.id = s.stuff_status_id AND s.company_id = ?
    WHERE ss.name IN ('" . translate('Raktáron') . "', '" . translate('Használatban') . "', '" . translate('Hibás') . "', '" . translate('Karbantartónál') . "', '" . translate('Törött') . "', '" . translate('Kiszelektálás alatt') . "')
    GROUP BY ss.id, ss.name
    ORDER BY CASE ss.name 
        WHEN '" . translate('Raktáron') . "' THEN 1
        WHEN '" . translate('Használatban') . "' THEN 2
        WHEN '" . translate('Hibás') . "' THEN 3
        WHEN '" . translate('Karbantartónál') . "' THEN 4
        WHEN '" . translate('Törött') . "' THEN 5
        WHEN '" . translate('Kiszelektálás alatt') . "' THEN 6
    END");

// Státusz színek definiálása - élénkebb színekkel, csak a megjelenítendő státuszokhoz
$status_colors = [
    translate('Raktáron') => '#00E676',        // Élénk zöld
    translate('Használatban') => '#00B0FF',    // Élénk kék
    translate('Hibás') => '#FF1744',          // Élénk piros
    translate('Karbantartónál') => '#AA00FF',  // Élénk lila
    translate('Törött') => '#FF9900',         // Narancssárga
    translate('Kiszelektálás alatt') => '#795548' // Barna
];
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<h1 class="page-title"><?php echo translate('Eszközök'); ?></h1>

<!-- Értesítés sáv -->
<div id="notification" class="notification" style="display: none;">
    <div class="notification-content">
        <i class="fas fa-check-circle"></i>
        <span id="notification-message"></span>
    </div>
</div>

<!-- A head részbe, vagy külön CSS fájlba -->
<style>
.table-container {
    width: 100%;
    max-width: none;
    margin-top: 10px; /* Hozzáadva egy kis felső margó */
    background: #fff;
    border: none;
    border-radius: 4px 4px 0 0;
    padding: 0 20px;
    height: auto;
    min-height: 400px;
    position: relative;
    overflow: hidden;
}

.table {
    width: 100%;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0 8px;
    table-layout: fixed;
}

.table th:nth-child(1), .table td:nth-child(1) { 
    width: 60px; 
    min-width: 60px;
    text-align: center;
} /* # */
.table th:nth-child(2), .table td:nth-child(2) { width: 120px; } /* Típus */
.table th:nth-child(3), .table td:nth-child(3) { width: 120px; } /* Altípus */
.table th:nth-child(4), .table td:nth-child(4) { width: 100px; } /* Márka */
.table th:nth-child(5), .table td:nth-child(5) { width: 100px; } /* Modell */
.table th:nth-child(6), .table td:nth-child(6) { width: 100px; } /* Gyártási év */
.table th:nth-child(7), .table td:nth-child(7) { width: 100px; } /* Státusz */
.table th:nth-child(8), .table td:nth-child(8) { width: 150px; } /* QR kód */
.table th:nth-child(9), .table td:nth-child(9) { width: 80px; } /* Kedvenc */
.table th:nth-child(10), .table td:nth-child(10) { width: 50px; } /* Műveletek */

.table th, .table td {
    padding: 8px 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center; /* Középre igazítás hozzáadása */
    vertical-align: middle; /* Függőleges középre igazítás */
}

.table thead th:first-child {
    padding-left: 20px;
}

.table thead th:last-child {
    padding-right: 20px;
}

.table tbody tr td {
    padding: 8px 15px; /* Csökkentett padding */
    vertical-align: middle;
    border: none;
    background: #f8f9fa;
    position: relative;
    height: 45px; /* Fix sor magasság */
}

.table tbody tr td:first-child {
    border-top-left-radius: 4px;
    border-bottom-left-radius: 4px;
    padding-left: 20px;
}

.table tbody tr td:last-child {
    border-top-right-radius: 4px;
    border-bottom-right-radius: 4px;
    padding-right: 20px;
}

.table tr.favorite-row td {
    background-color: #fff8e1;
}

.table tbody tr:hover td {
    background-color: #e9ecef;
}

/* Modal stílus az új eszköz űrlaphoz */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1005;
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
}

/* Tartalom animáció finomítása */
.modal-content {
    position: relative;
    background: white;
    width: 90%;
    max-width: 500px;
    margin: 50px auto;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    animation: modalSlideIn 0.3s ease-out;
    transform: translateY(0);
    opacity: 1;
    z-index: 1006;
}

/* Modal animáció módosítása */
@keyframes modalSlideIn {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Modal bezárás animáció */
@keyframes modalSlideOut {
    from {
        transform: translateY(0);
        opacity: 1;
    }
    to {
        transform: translateY(-30px);
        opacity: 0;
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h2 {
    margin: 0;
    color: #212529;
}

.close-button {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: #495057;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}

.btn-primary {
    background: #0d6efd;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
}

.btn-primary:hover {
    background: #0b5ed7;
}

/* Új eszköz gomb */
.add-button {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0; /* Eltávolítva a 20px margó */
    background-color: #2c3e50;
    color: white;
    padding: 8px 15px;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.add-button:hover {
    background-color: #34495e; /* Sötétebb árnyalat hover esetén */
}

.print-button  {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0; /* Eltávolítva a 20px margó */
    background-color: #2c3e50;
    color: white;
    padding: 8px 15px;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.print-button:hover {
    background-color: #34495e; /* Sötétebb árnyalat hover esetén */
}

.add-button svg {
    width: 14px;
    height: 14px;
    fill: white;
}

/* Eszközök fejléc konténer a gomb pozicionálásához */
.eszközök-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px; /* Csökkentve 20px-ről 10px-re */
    padding: 0 20px;
}

.page-title {
    margin: 0;
}

.select-with-add {
    position: relative;
}

.input-group {
    display: flex;
    gap: 10px;
}

.btn-add {
    padding: 8px 12px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn-add:hover {
    background: #218838;
}

/* Kedvenc gomb stílusai */
.favorite-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #ddd;  /* Alapértelmezett szürke szín */
    transition: all 0.3s ease;  /* Animáció hozzáadása */
    padding: 5px;
    border-radius: 50%;
}

/* Aktív kedvenc gomb stílusa */
.favorite-btn.active {
    color: #ffd700;  /* Arany szín a kedvenceknek */
}

.favorite-btn:hover {
    transform: scale(1.1);  /* Hover effekt */
    color: #ffd700;  /* Arany szín hover-nél */
}

.favorite-btn.active {
    color: #ffd700;  /* Aktív állapot színe */
    text-shadow: 0 0 5px rgba(255, 215, 0, 0.5);  /* Fénylő effekt */
}

.favorite-row {
    background-color: #fff8e1 !important;  /* !important a táblázat alapértelmezett háttér felülírásához */
    transition: background-color 0.3s ease;  /* Animált átmenet */
}

.favorite-row:hover {
    background-color: #fff3cd !important;  /* Sötétebb árnyalat hover esetén */
}

/* Az új sor animációja */
@keyframes highlightNew {
    from {
        background-color: #e8f5e9;
    }
    to {
        background-color: transparent;
    }
}

.eszköz-sor {
    transition: opacity 0.3s ease;
}

.eszköz-sor.hidden {
    display: none;
}

.notification {
    position: fixed;
    top: 80px;
    right: 20px;
    background-color: #28a745;
    color: white;
    padding: 15px 25px;
    border-radius: 4px;
    display: none;
    z-index: 1000;
    animation: slideIn 0.5s ease-out;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
}

.notification i {
    font-size: 20px;
    color: white;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.notification.success {
    background-color: #28a745;
}

.notification.error {
    background-color: #dc3545;
}

/* Dropdown menü stílusok módosítása */
.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-toggle {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    font-size: 24px;
    color: #333;
}

.dropdown-menu {
    position: absolute;
    z-index: 1000;
    background-color: #fff;
    min-width: 180px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    border-radius: 4px;
    display: none;
    transition: all 0.2s ease;
    border: 1px solid rgba(0,0,0,0.1);
    padding: 8px 0;
    min-height: 135px; /* Módosított magasság az új gombnak */
}

/* Kontextus menü esetén */
.dropdown-menu[style*="position: fixed"] {
    margin-left: 0;
    max-width: 200px; /* Maximum szélesség beállítása */
    width: auto; /* Automatikus szélesség a tartalom alapján */
}

.dropdown-menu.show {
    opacity: 1;
    transform: scale(1);
}

/* Dropdown item-ek animációja */
.dropdown-item {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    text-decoration: none;
    color: #333;
    cursor: pointer;
    font-size: 15px;
    opacity: 0;
    transform: translateX(-10px);
    transition: all 0.3s ease;
    white-space: nowrap; /* Megakadályozza a sortörést */
    overflow: hidden; /* Elrejti a túlnyúló szöveget */
    text-overflow: ellipsis; /* Három pontot tesz a túlnyúló szöveg helyére */
}

.dropdown-menu.show .dropdown-item {
    opacity: 1;
    transform: translateX(0);
}

/* Késleltetett animáció minden következő elemnek */
.dropdown-menu.show .dropdown-item:nth-child(1) {
    transition-delay: 0.1s;
}

.dropdown-menu.show .dropdown-item:nth-child(2) {
    transition-delay: 0.2s;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

.dropdown-item i {
    margin-right: 12px;
    font-size: 18px;
}

/* Típus szűrők stílusa módosítása */
.type-filters {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 0;
    min-width: 50px;
    margin-top: 185px; /* Igazítás a táblázat első sorához */
}

.type-filter, .status-filter {
    position: relative;
    width: 40px;
    height: 40px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    opacity: 1;
}

/* Inaktív szűrő stílusa */
.type-filter.inactive, .status-filter.inactive {
    opacity: 0.4;
}

/* Aktív szűrő stílusa */
.type-filter.active, .status-filter.active {
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    opacity: 1;
}

/* Számláló stílusa */
.type-count, .status-count {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 14px;
    font-weight: 500;
}

/* Típus név konténer módosítása */
.type-name-container, .status-name-container {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    pointer-events: none;
    z-index: 10;
    overflow: hidden;
    width: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    opacity: 0;
}

/* Típus név konténer pozicionálása jobbra */
.type-name-container {
    right: calc(100% + 10px);
}

/* Státusz név konténer pozicionálása balra */
.status-name-container {
    left: calc(100% + 10px);
}

/* Név stílus módosítása */
.type-name, .status-name {
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 8px 15px;
    border-radius: 6px;
    font-weight: 500;
    white-space: nowrap;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(5px);
    transform: translateX(100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.status-name {
    transform: translateX(-100%);
}

/* Hover effektek */
.type-filter:hover .type-name-container,
.status-filter:hover .status-name-container {
    width: auto;
    opacity: 1;
}

.type-filter:hover .type-name,
.status-filter:hover .status-name {
    transform: translateX(0);
}

/* Aktív és inaktív állapotok */
.type-filter.inactive,
.status-filter.inactive {
    opacity: 0.4;
}

.type-filter.active,
.status-filter.active {
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    opacity: 1;
}

/* Hover effekt az aktív elemekre */
.type-filter:hover,
.status-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

/* Konténer a táblázatnak és a szűrőknek */
.content-wrapper {
    display: flex;
    gap: 5px; /* Csökkentett távolság */
    padding: 20px;
}

/* Táblázat konténer módosítása */
.card {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    max-width: 1200px; /* Maximum szélesség beállítása */
    margin: 0 auto; /* Középre igazítás */
}

/* "Nincs találat" üzenet stílusa */
.no-results {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: #6c757d;
    font-size: 1.2em;
    padding: 20px;
    background: transparent;
    z-index: 10;
    width: 100%;
    display: none;
}

/* Opcionális: hozzáadhatunk egy ikont is az üzenethez */
.no-results::before {
    content: '\f119'; /* Szomorú emoji ikon */
    font-family: 'Font Awesome 5 Free';
    display: block;
    font-size: 2em;
    margin-bottom: 10px;
    color: #6c757d;
}

/* Lapozó stílusa */
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 12px 20px;
    gap: 5px;
    background: #f8f9fa;
    border-radius: 0 0 4px 4px;
    border-top: 1px solid #dee2e6;
    margin: 0;
}

.pagination-button {
    padding: 6px 12px;
    border: 1px solid #dee2e6;
    background: #fff;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 35px;
    text-align: center;
    color: #495057;
}

.pagination-button:hover {
    background: #e9ecef;
    border-color: #ced4da;
}

.pagination-button.active {
    background: #2c3e50; /* Navbar szín */
    color: white;
    border-color: #2c3e50; /* Navbar szín */
}

.pagination-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f8f9fa;
}

/* Lapozó oldalszámok konténere */
.pagination-numbers {
    display: flex;
    gap: 5px;
    align-items: center;
}

/* Három pont jelzés */
.pagination-dots {
    color: #6c757d;
    margin: 0 2px;
}

/* Információs szöveg */
.pagination-info {
    color: #6c757d;
    font-size: 13px;
    margin-left: 15px;
    padding-left: 15px;
    border-left: 1px solid #dee2e6;
}

/* Kártya konténer módosítása */
.card {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    max-width: 1200px;
    margin: 0 auto;
    background: transparent;
    box-shadow: none;
}

/* Tooltip stílus a hosszú szövegekhez */
.table td.tooltip-cell {
    position: relative;
    cursor: help;
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tooltip-cell .tooltip {
    display: none;
    position: fixed;
    background: rgba(0, 0, 0, 0.9);
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 13px;
    white-space: nowrap;
    z-index: 1000;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    pointer-events: none;
}

/* QR kód cella speciális kezelése */
.table td.tooltip-cell[data-full-text*="QR-"] {
    font-family: monospace;
}

/* A style részben adjuk hozzá az új animációt */
@keyframes highlightRow {
    0% {
        background-color: #fff3cd;
        transform: translateY(-5px);
    }
    50% {
        background-color: #fff3cd;
        transform: translateY(0);
    }
    100% {
        background-color: inherit;
    }
}

/* Státusz szűrők stílusa */
.status-filters {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 0;
    min-width: 50px;
    margin-top: 185px;
}

.status-filter {
    position: relative;
    width: 40px;
    height: 40px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    opacity: 1;
}

.status-filter.inactive {
    opacity: 0.4;
}

.status-filter.active {
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    opacity: 1;
}

.status-count {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 14px;
    font-weight: 500;
}

.status-name-container {
    position: absolute;
    left: calc(100% + 10px);
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    pointer-events: none;
    opacity: 0;
    transition: all 0.3s ease;
    z-index: 10;
}

.status-name {
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 8px 15px;
    border-radius: 4px;
    font-weight: 500;
    white-space: nowrap;
}

.status-filter:hover .status-name-container {
    opacity: 1;
}

/* Dropdown menü stílusok */
.dropdown-toggle {
    background: none;
    border: none;
    padding: 8px;
    cursor: pointer;
    color: #495057;
    border-radius: 4px;
}

.dropdown-toggle:hover {
    background-color: #f8f9fa;
}

.dropdown-menu {
    position: absolute;
    right: 0;
    min-width: 160px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    padding: 8px 0;
    z-index: 1000;
    display: none;
}

.dropdown-item {
    display: flex;
    align-items: center;
    padding: 8px 16px;
    color: #495057;
    text-decoration: none;
    transition: background-color 0.2s;
    font-size: 14px;
}

.dropdown-item i {
    margin-right: 8px;
    width: 16px;
    text-align: center;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

.dropdown-item.text-danger {
    color: #dc3545 !important; /* Piros szín */
    transition: background-color 0.2s, color 0.2s;
}

.dropdown-item.text-danger:hover {
    background-color: #dc3545 !important;
    color: white !important;
}

/* QR kód cella és tooltip stílusok módosítása */
.qr-code-cell {
    font-family: monospace;
    font-size: 13px;
    color: #495057;
    position: relative;
    cursor: pointer;
}

.qr-code-cell .tooltip {
    visibility: hidden;
    position: absolute;
    background-color: rgba(0, 0, 0, 0.8);
    color: white;
    text-align: center;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    opacity: 0;
    transition: opacity 0.3s;
}

.qr-code-cell:hover .tooltip {
    visibility: visible;
    opacity: 1;
}

/* Nyíl a tooltip alján */
.qr-code-cell .tooltip::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: rgba(0, 0, 0, 0.8) transparent transparent transparent;
}

/* A style részhez adjuk hozzá */
@keyframes fadeOut {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(-20px);
    }
}

/* Törlés megerősítő modal stílusok módosítása */
#deleteConfirmModal {
    background-color: rgba(0, 0, 0, 0.5); /* Félig átlátszó fekete háttér */
    backdrop-filter: blur(5px); /* Háttér elhomályosítása */
    -webkit-backdrop-filter: blur(5px); /* Safari támogatás */
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1050;
    transition: all 0.3s ease;
}

#deleteConfirmModal .modal-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    animation: modalSlideIn 0.3s ease-out;
    position: relative;
    margin: 10vh auto; /* A modal 10%-kal a képernyő tetejétől */
}

/* Modal animáció finomítása */
@keyframes modalSlideIn {
    from {
        transform: translateY(-30px) scale(0.95);
        opacity: 0;
    }
    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

#deleteConfirmModal button {
    transition: all 0.2s ease;
}

#deleteConfirmModal #cancelDeleteBtn:hover {
    background: #5a6268;
    border-color: #5a6268;
}

#deleteConfirmModal #confirmDeleteBtn:hover {
    background: #c82333;
}

/* Tooltip stílus módosítása */
.tooltip-cell {
    position: relative;
    cursor: help;
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.tooltip-cell:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.tooltip-cell .tooltip {
    display: none;
    position: fixed;
    background: rgba(0, 0, 0, 0.9);
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 13px;
    white-space: nowrap;
    z-index: 1000;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    pointer-events: none;
    animation: tooltipFadeIn 0.2s ease-out;
}

@keyframes tooltipFadeIn {
    from {
        opacity: 0;
        transform: translateY(5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Típus és márka cellák specifikus stílusai */
.table td:nth-child(2).tooltip-cell, /* Típus */
.table td:nth-child(4).tooltip-cell  /* Márka */ {
    font-weight: 500;
}

/* A dropdown-item alap stílusok után */

/* Szerkesztés gomb */
.dropdown-item.edit-action {
    transition: background-color 0.2s, color 0.2s;
}

.dropdown-item.edit-action:hover {
    background-color: #17a2b8 !important; /* Kékes szín */
    color: white !important;
}

/* QR kód generálás gomb */
.dropdown-item.qr-action {
    transition: background-color 0.2s, color 0.2s;
}

.dropdown-item.qr-action:hover {
    background-color: #28a745 !important; /* Zöldes szín */
    color: white !important;
}

/* A style részben */
.header-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

/* Módosítjuk a kereső konténer és input stílusait */
.search-container {
    position: relative;
    width: 300px;
}

.search-input {
    width: 100%;
    padding: 8px 15px;
    text-indent: 20px; /* Ez tolja el a beírt szöveget */
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 14px;
    height: 38px;
}

.search-input::placeholder {
    padding-left: 15px; /* Placeholder szöveg eltolása */
    color: #6c757d;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    font-size: 14px;
    pointer-events: none;
}

/* Módosítjuk az eszközök-header stílust */
.eszközök-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding: 0 20px;
}

/* Státusz alapú sor színezés */
.table tbody tr[data-status="Használatban"] td {
    background-color: rgba(0, 176, 255, 0.1) !important;
}

.table tbody tr[data-status="Hibás"] td {
    background-color: rgba(255, 23, 68, 0.15) !important; /* Élénk piros */
}

.table tbody tr[data-status="Karbantartónál"] td {
    background-color: rgba(170, 0, 255, 0.1) !important;
}

.table tbody tr[data-status="Törött"] td {
    background-color: rgba(255, 153, 0, 0.15) !important; /* Narancssárga */
}

.table tbody tr[data-status="Kiszelektálás alatt"] td {
    background-color: rgba(121, 85, 72, 0.15) !important; /* Barnás szín */
}

/* Kedvencek sor színe */
.table tbody tr.favorite-row td {
    background-color: rgba(255, 215, 0, 0.15) !important; /* Élénk arany szín */
}

/* Hover effekt azonnal, animáció nélkül */
.table tbody tr[data-status]:hover td,
.table tbody tr.favorite-row:hover td {
    background-color: rgba(0, 0, 0, 0.05) !important;
    transition: none;
}

.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    padding: 15px;
    border-radius: 4px;
    animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Print-specific styles */
@media print {
    body * {
        visibility: hidden;
    }
    
    .table-container, .table-container * {
        visibility: visible;
    }
    
    .table-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    
    .no-print, .pagination {
        display: none !important;
    }

    /* Táblázat optimalizálása nyomtatáshoz */
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 7.5pt;
        table-layout: fixed;
        line-height: 1;
    }

    th, td {
        padding: 2px 4px;
        text-align: left;
        border: none;
        border-bottom: 0.5px solid #000 !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-height: 12px;
        height: 12px;
    }

    thead th {
        border: none;
        border-bottom: 1px solid #000 !important;
        background-color: #f8f8f8 !important;
        font-weight: bold;
    }

    /* Sorok közötti elválasztó vonal erősítése */
    tbody tr {
        border-bottom: 0.5px solid #000 !important;
    }

    /* Páros sorok háttérszíne a jobb olvashatóságért */
    tbody tr:nth-child(even) {
        background-color: #f9f9f9 !important;
    }

    /* Oszlopok szélességének optimalizálása */
    th:first-child, td:first-child { width: 3%; } /* # */
    th:nth-child(2), td:nth-child(2) { width: 12%; } /* Típus */
    th:nth-child(3), td:nth-child(3) { width: 12%; } /* Altípus */
    th:nth-child(4), td:nth-child(4) { width: 12%; } /* Márka */
    th:nth-child(5), td:nth-child(5) { width: 12%; } /* Modell */
    th:nth-child(6), td:nth-child(6) { width: 7%; } /* Gyártási év */
    th:nth-child(7), td:nth-child(7) { width: 10%; } /* Státusz */
    th:nth-child(8), td:nth-child(8) { width: 20%; } /* QR kód */

    /* Kedvencek és műveletek oszlop elrejtése */
    th:last-child, td:last-child,
    th:nth-last-child(2), td:nth-last-child(2) {
        display: none !important;
    }

    /* Töréspontok kezelése */
    tr {
        page-break-inside: avoid;
        height: 10px;
    }

    /* Fejléc minden oldalon */
    thead {
        display: table-header-group;
    }

    /* Háttérszínek és árnyékok eltávolítása */
    tr, td, th {
        background-color: white !important;
        box-shadow: none !important;
    }

    /* Papír orientáció és margók */
    @page {
        size: landscape;
        margin: 0.2cm;
    }

    /* Táblázat konténer optimalizálása */
    .table-container {
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Táblázat sorok közötti térköz minimalizálása */
    tbody tr + tr {
        margin-top: 0;
    }

    /* Minden sor megjelenítése */
    .table tr {
        display: table-row !important;
    }

    /* Lapozó és egyéb korlátozások eltávolítása */
    .table tbody tr {
        display: table-row !important;
    }

    /* Táblázat magasságának optimalizálása */
    .table-container {
        height: auto !important;
        overflow: visible !important;
    }
}

@media screen {
    .print-header {
        display: none;
    }
}

@media print {
    .print-header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 80px; /* Növeltem a fejléc magasságát */
        padding: 10px 20px;
        visibility: visible !important;
        background: white;
        z-index: 1000;
        display: flex !important;
        justify-content: space-between;
        align-items: center;
    }

    .print-header * {
        visibility: visible !important;
    }

    .print-header img {
        height: 60px; /* Növeltem a logó magasságát 40px-ről 60px-re */
        display: block !important;
        margin-left: auto;
    }

    .print-header h1 {
        margin: 0;
        font-size: 18pt; /* Növeltem a szöveg méretét is */
        color: #333;
        display: block !important;
        text-align: left;
    }

    .table-container {
        margin-top: 90px !important; /* Növeltem a táblázat felső margóját */
    }

    body * {
        visibility: hidden;
    }
    
    .table-container, .table-container * {
        visibility: visible;
    }
}

@media screen {
    .print-header {
        display: none;
    }
}

/* Add right-click context menu styles */
.context-menu {
    display: none;
    position: fixed;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    z-index: 1000;
}

.context-menu-item {
    padding: 8px 15px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.context-menu-item:hover {
    background-color: #f0f0f0;
}

/* Add cursor style to indicate right-clickable rows */
.table tbody tr {
    cursor: context-menu;
}

/* Add button disabled state styles */
.add-button.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    position: relative;
}

.add-button.disabled:hover::after {
    content: "<?php echo translate('A csapat létszáma elérte a maximális ' . $device_limit . ' főt. A limit növeléséhez váltson magasabb csomagra vagy módosítsa jelenlegi előfizetését'); ?>";
    position: fixed;
    background: rgb(0, 0, 0);
    color: white;
    padding: 15px 20px;
    border-radius: 4px;
    font-size: 14px;
    width: auto;
    white-space: nowrap;
    top: 80px;
    right: 20px;
    left: auto;
    transform: none;
    z-index: 9999;
    text-align: center;
    line-height: 1.4;
    box-shadow: none;
    font-weight: 500;
}

/* Remove the arrow since it's now at the top */
.add-button.disabled:hover::before {
    display: none;
}
</style>

<!-- Nyomtatási fejléc -->
<div class="print-header">
    <h1><?php echo translate('Eszközök listája'); ?></h1>
    <img src="../assets/img/VIBECORE.png" alt="Logo">
</div>

<!-- A HTML részben módosítjuk az űrlapot -->
<!-- Az "Új eszköz hozzáadása" form -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="addItemTitle">Új elem hozzáadása</h2>
            <button type="button" class="close-button" onclick="toggleModal()">&times;</button>
        </div>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form id="addItemForm" method="post">
            <div class="form-group">
                <label for="type_id"><?php echo translate('Típus'); ?></label>
                <select id="type_id" name="type_id" class="form-control" required>
                    <option value=""><?php echo translate('Válasszon típust...'); ?></option>
                    <?php foreach ($stuff_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>">
                            <?php echo htmlspecialchars(translate($type['name'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="secondtype_id"><?php echo translate('Altípus'); ?></label>
                <div class="input-group">
                    <select id="secondtype_id" name="secondtype_id" class="form-control" required>
                        <option value=""><?php echo translate('Válasszon altípust...'); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="brand_id"><?php echo translate('Márka'); ?></label>
                <div class="input-group">
                    <select id="brand_id" name="brand_id" class="form-control" required>
                        <option value=""><?php echo translate('Válasszon márkát...'); ?></option>
                    </select>
                    <button type="button" class="btn-add" onclick="showAddBrandModal()">+</button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="model_id"><?php echo translate('Modell'); ?></label>
                <div class="input-group">
                    <select id="model_id" name="model_id" class="form-control" required>
                        <option value=""><?php echo translate('Válasszon modellt...'); ?></option>
                    </select>
                    <button type="button" class="btn-add" onclick="showAddModelModal()">+</button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="manufacture_date"><?php echo translate('Gyártási év'); ?></label>
                <div class="input-group">
                    <input type="hidden" id="manufacture_date" name="manufacture_date" required>
                    <input type="text" 
                           id="manufacture_date_display" 
                           class="form-control" 
                           readonly 
                           placeholder="<?php echo translate('Válasszon először modellt'); ?>">
                    <button type="button" class="btn-add" onclick="showAddYearModal()">+</button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="mennyiseg"><?php echo translate('Darabszám:'); ?></label>
                <input type="number" class="form-control" id="mennyiseg" name="mennyiseg" min="1" value="1" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary"><?php echo translate('Mentés'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Új márka hozzáadása modal -->
<div class="modal" id="addBrandModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php echo translate('Új márka hozzáadása'); ?></h2>
            <button type="button" class="close-button" onclick="closeModal('addBrandModal')">&times;</button>
        </div>
        <form id="addBrandForm">
            <div class="form-group">
                <label for="brandTypeId"><?php echo translate('Típus'); ?></label>
                <select id="brandTypeId" name="type_id" class="form-control" required onchange="updateSecondtypesForBrand(this.value)">
                    <option value=""><?php echo translate('Válasszon típust...'); ?></option>
                    <?php foreach ($stuff_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars(translate($type['name'])); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="brandSecondtypeId"><?php echo translate('Altípus'); ?></label>
                <select id="brandSecondtypeId" name="secondtype_id" class="form-control" required>
                    <option value=""><?php echo translate('Először válasszon típust...'); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label for="brandName"><?php echo translate('Márka neve'); ?></label>
                <input type="text" id="brandName" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary"><?php echo translate('Mentés'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Új modell hozzáadása modal -->
<div class="modal" id="addModelModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php echo translate('Új modell hozzáadása'); ?></h2>
            <button type="button" class="close-button" onclick="closeModal('addModelModal')">&times;</button>
        </div>
        <form id="addModelForm">
            <div class="form-group">
                <label for="modelTypeId"><?php echo translate('Típus'); ?></label>
                <select id="modelTypeId" name="type_id" class="form-control" required onchange="updateSecondtypesForModel(this.value)">
                    <option value=""><?php echo translate('Válasszon típust...'); ?></option>
                    <?php foreach ($stuff_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars(translate($type['name'])); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="modelSecondtypeId"><?php echo translate('Altípus'); ?></label>
                <select id="modelSecondtypeId" name="secondtype_id" class="form-control" required onchange="updateBrandsForModel(this.value)">
                    <option value=""><?php echo translate('Először válasszon típust...'); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label for="modelBrandId"><?php echo translate('Márka'); ?></label>
                <select id="modelBrandId" name="brand_id" class="form-control" required>
                    <option value=""><?php echo translate('Először válasszon altípust...'); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label for="modelName"><?php echo translate('Modell neve'); ?></label>
                <input type="text" 
                       id="modelName" 
                       name="name" 
                       class="form-control" 
                       required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary"><?php echo translate('Mentés'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Új gyártási év hozzáadása modal -->
<div class="modal" id="addYearModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php echo translate('Új gyártási év hozzáadása'); ?></h2>
            <button type="button" class="close-button" onclick="closeModal('addYearModal')">&times;</button>
        </div>
        <form id="addYearForm" method="POST">
            <div class="form-group">
                <label for="yearTypeId"><?php echo translate('Típus'); ?></label>
                <select id="yearTypeId" name="type_id" class="form-control" required onchange="updateSecondtypesForYear(this.value)">
                    <option value=""><?php echo translate('Válasszon típust...'); ?></option>
                    <?php foreach ($stuff_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars(translate($type['name'])); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="yearSecondtypeId"><?php echo translate('Altípus'); ?></label>
                <select id="yearSecondtypeId" name="secondtype_id" class="form-control" required onchange="updateBrandsForYear(this.value)">
                    <option value=""><?php echo translate('Először válasszon típust...'); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label for="yearBrandId"><?php echo translate('Márka'); ?></label>
                <select id="yearBrandId" name="brand_id" class="form-control" required onchange="updateModelsForYear(this.value)">
                    <option value=""><?php echo translate('Először válasszon altípust...'); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label for="yearModelId"><?php echo translate('Modell'); ?></label>
                <select id="yearModelId" name="model_id" class="form-control" required>
                    <option value=""><?php echo translate('Először válasszon márkát...'); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label for="yearValue"><?php echo translate('Gyártási év'); ?></label>
                <input type="number" 
                       id="yearValue" 
                       name="year" 
                       class="form-control" 
                       required 
                       min="1980" 
                       max="<?php echo date('Y'); ?>" 
                       value="<?php echo date('Y'); ?>"
                       oninput="validateYear(this)">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary"><?php echo translate('Mentés'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- A táblázat és szűrők konténere -->
<div class="content-wrapper">
    <!-- Típus szűrők -->
    <div class="type-filters">
        <!-- Kedvencek szűrő -->
        <div class="type-filter favorites-filter" data-type="favorites" style="background-color: #FFD700;">
            <span class="type-count">
                <?php
                // Kedvencek számának lekérdezése
                $favorites_count = $db->prepare("SELECT COUNT(*) FROM stuffs WHERE company_id = ? AND favourite = 1");
                $favorites_count->execute([$company_id]);
                echo $favorites_count->fetchColumn();
                ?>
            </span>
            <div class="type-name-container">
                <span class="type-name"><?php echo translate('Kedvencek'); ?></span>
            </div>
        </div>
        
        <!-- Meglévő típus szűrők -->
        <?php foreach ($type_stats as $type): ?>
            <div class="type-filter" 
                 data-type="<?= htmlspecialchars($type['name']) ?>"
                 style="background-color: <?= isset($type_colors[$type['name']]) ? $type_colors[$type['name']] : $default_color ?>">
                <span class="type-count"><?= $type['count'] ?></span>
                <div class="type-name-container">
                    <span class="type-name"><?php echo translate(htmlspecialchars($type['name'])); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Eszközök listázása -->
    <div class="card">
        <div class="eszközök-header">
            <h2 class="page-title"><?php echo translate('Eszközök listája'); ?></h2>
            <div class="header-actions">
                <!-- Kereső mező -->
                <div class="search-container">
                    <input type="text" 
                           id="qrSearch" 
                           class="search-input" 
                           placeholder="<?php echo translate('QR kód keresése...'); ?>"
                           autocomplete="off">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <!-- Új eszköz gomb -->
                <a href="#" class="add-button <?php echo ($current_device_count >= $device_limit) ? 'disabled' : ''; ?>" 
                   onclick="<?php echo ($current_device_count >= $device_limit) ? 'return false' : 'toggleModal()'; ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14"></path>
                    </svg>
                    <span><?php echo translate('Új eszköz'); ?></span>
                </a>
                <div>
                    <button class="print-button" onclick="window.print()">
                        <i class="fas fa-print"></i> <?php echo translate('Nyomtatás'); ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="table-container">
            <table id="eszközökTábla" class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Típus</th>
                        <th>Altípus</th>
                        <th>Márka</th>
                        <th>Modell</th>
                        <th>Gyártási év</th>
                        <th>Státusz</th>
                        <th>QR kód</th>
                        <th>Kedvenc</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sorszam = 1;
                    try {
                        while ($eszkoz = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $favorite_class = $eszkoz['favourite'] ? 'active' : '';
                            echo "<tr class='eszköz-sor" . ($eszkoz['favourite'] ? " favorite-row" : "") . "' data-id='" . $eszkoz['id'] . "' data-status='" . htmlspecialchars($eszkoz['status_name']) . "'>";
                            echo "<td>" . $sorszam++ . "</td>";
                            echo "<td>" . translate(htmlspecialchars($eszkoz['type_name'])) . "</td>";
                            echo "<td class='tooltip-cell' data-full-text='" . translate(htmlspecialchars($eszkoz['secondtype_name'])) . "'>" . 
                                 translate(htmlspecialchars($eszkoz['secondtype_name'])) . "</td>";
                            echo "<td class='tooltip-cell' data-full-text='" . htmlspecialchars($eszkoz['brand_name']) . "'>" . 
                                 htmlspecialchars($eszkoz['brand_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($eszkoz['model_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($eszkoz['manufacture_year']) . "</td>";
                            echo "<td data-status='" . htmlspecialchars($eszkoz['status_name']) . "'>" . translate(htmlspecialchars($eszkoz['status_name'])) . "</td>";
                            echo "<td class='tooltip-cell' data-full-text='" . htmlspecialchars($eszkoz['qr_code']) . "'>" . 
                                 htmlspecialchars($eszkoz['qr_code']) . "</td>";
                            echo "<td>
                                    <button class='favorite-btn {$favorite_class}' onclick='toggleFavorite(" . $eszkoz['id'] . ")'>
                                        ★
                                    </button>
                                  </td>";
                            echo "</tr>";
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='10'>Az eszközök betöltése sikertelen.</td></tr>";
                        error_log("Hiba az eszközök lekérdezésekor: " . $e->getMessage());
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Státusz szűrők -->
    <div class="status-filters">
        <?php
        // Státusz színek definiálása
        $status_colors = [
            translate('Raktáron') => '#00E676',        // Élénk zöld
            translate('Használatban') => '#00B0FF',    // Élénk kék
            translate('Hibás') => '#FF1744',          // Élénk piros
            translate('Karbantartónál') => '#AA00FF',  // Élénk lila
            translate('Törött') => '#FF9900',         // Narancssárga
            translate('Kiszelektálás alatt') => '#795548' // Barna
        ];

        // Státuszok lekérdezése és számolása
        $status_counts = $db->prepare("
            SELECT 
                ss.id,
                ss.name,
                COUNT(s.id) as count
            FROM stuff_status ss
            LEFT JOIN stuffs s ON ss.id = s.stuff_status_id AND s.company_id = ?
            WHERE ss.name IN ('" . translate('Raktáron') . "', '" . translate('Használatban') . "', '" . translate('Hibás') . "', '" . translate('Karbantartónál') . "', '" . translate('Törött') . "', '" . translate('Kiszelektálás alatt') . "')
            GROUP BY ss.id, ss.name
            ORDER BY CASE ss.name 
                WHEN '" . translate('Raktáron') . "' THEN 1
                WHEN '" . translate('Használatban') . "' THEN 2
                WHEN '" . translate('Hibás') . "' THEN 3
                WHEN '" . translate('Karbantartónál') . "' THEN 4
                WHEN '" . translate('Törött') . "' THEN 5
                WHEN '" . translate('Kiszelektálás alatt') . "' THEN 6
            END");
        $status_counts->execute([$company_id]);
        $status_stats = $status_counts->fetchAll(PDO::FETCH_ASSOC);

        // Státusz szűrők kirajzolása
        foreach ($status_stats as $status): ?>
            <div class="status-filter" 
                 data-status="<?= htmlspecialchars($status['name']) ?>"
                 style="background-color: <?= isset($status_colors[$status['name']]) ? $status_colors[$status['name']] : '#gray' ?>">
                <span class="status-count"><?= $status['count'] ?></span>
                <div class="status-name-container">
                    <span class="status-name"><?= translate(htmlspecialchars($status['name'])) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- A táblázat után -->
<div class="pagination-container"></div>



<!-- A script közvetlenül a footer előtt -->
<script>
// Az eszkozok.php JavaScript kódjának elején:
document.addEventListener('DOMContentLoaded', () => {
    // Dropdown toggle kezelése
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            // Minden más dropdown bezárása
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                if (menu !== toggle.nextElementSibling) {
                    menu.classList.remove('show');
                }
            });
            
            // Aktuális dropdown ki/be kapcsolása
            const dropdownMenu = toggle.nextElementSibling;
            if (dropdownMenu) {
                dropdownMenu.classList.toggle('show');
            }
        });
    });
    
    // Kattintás a dokumentum bármely részére bezárja a dropdown-ot
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown-toggle') && !e.target.closest('.dropdown-menu')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
    
    // Megakadályozzuk az eseményterjedést a táblázat dropdown menüinél
    document.querySelectorAll('.table .dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    });
    
    // ... többi inicializáló kód
});

// Modal kezelő függvények
function toggleModal() {
    const modal = document.getElementById('addModal');
    const content = modal.querySelector('.modal-content');
    
    if (modal.style.display === 'none' || modal.style.display === '') {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Görgetés letiltása
        setTimeout(() => {
            content.style.transform = 'translateY(0)';
            content.style.opacity = '1';
        }, 10);
    } else {
        content.style.transform = 'translateY(-30px)';
        content.style.opacity = '0';
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Görgetés visszaállítása
        }, 300);
    }
}

// Új modal megnyitó függvények
function showAddBrandModal() {
    const modal = document.getElementById('addBrandModal');
    const content = modal.querySelector('.modal-content');
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        content.style.transform = 'translateY(0)';
        content.style.opacity = '1';
    }, 10);
}

function showAddModelModal() {
    const modal = document.getElementById('addModelModal');
    const content = modal.querySelector('.modal-content');
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        content.style.transform = 'translateY(0)';
        content.style.opacity = '1';
    }, 10);
}

function showAddYearModal() {
    const modal = document.getElementById('addYearModal');
    const content = modal.querySelector('.modal-content');
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        content.style.transform = 'translateY(0)';
        content.style.opacity = '1';
    }, 10);
}

// Modal bezáró függvény
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    
    if (modal) {
        const content = modal.querySelector('.modal-content');
        if (content) {
            content.style.transform = 'translateY(-30px)';
            content.style.opacity = '0';
        }
        
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Görgetés visszaállítása
        }, 300);
    }
}

// Függő mezők kezelő függvények - globális scope-ba helyezve
async function updateSecondtypesForYear(typeId) {
    const secondtypeSelect = document.getElementById('yearSecondtypeId');
    const brandSelect = document.getElementById('yearBrandId');
    const modelSelect = document.getElementById('yearModelId');
    
    if (!typeId) {
        secondtypeSelect.innerHTML = '<option value="">Először válasszon típust...</option>';
        brandSelect.innerHTML = '<option value="">Először válasszon altípust...</option>';
        modelSelect.innerHTML = '<option value="">Először válasszon márkát...</option>';
        return;
    }
    
    try {
        const response = await fetch(`get_dependent_data.php?action=secondtypes&type_id=${typeId}`);
        const data = await response.json();
        
        secondtypeSelect.innerHTML = '<option value="">Válasszon altípust...</option>';
        data.forEach(secondtype => {
            secondtypeSelect.add(new Option(secondtype.name, secondtype.id));
        });
        
        brandSelect.innerHTML = '<option value="">Először válasszon altípust...</option>';
        modelSelect.innerHTML = '<option value="">Először válasszon márkát...</option>';
    } catch (error) {
        console.error('Hiba az altípusok lekérésénél:', error);
    }
}

async function updateBrandsForYear(secondtypeId) {
    const brandSelect = document.getElementById('yearBrandId');
    const modelSelect = document.getElementById('yearModelId');
    
    if (!secondtypeId) {
        brandSelect.innerHTML = '<option value="">Először válasszon altípust...</option>';
        modelSelect.innerHTML = '<option value="">Először válasszon márkát...</option>';
        return;
    }
    
    try {
        const response = await fetch(`get_dependent_data.php?action=brands&secondtype_id=${secondtypeId}`);
        const data = await response.json();
        
        brandSelect.innerHTML = '<option value="">Válasszon márkát...</option>';
        data.forEach(brand => {
            brandSelect.add(new Option(brand.name, brand.id));
        });
        
        modelSelect.innerHTML = '<option value="">Először válasszon márkát...</option>';
    } catch (error) {
        console.error('Hiba a márkák lekérésénél:', error);
        brandSelect.innerHTML = '<option value="">Hiba történt a márkák betöltésekor</option>';
    }
}

async function updateModelsForYear(brandId) {
    const modelSelect = document.getElementById('yearModelId');
    
    if (!brandId) {
        modelSelect.innerHTML = '<option value="">Először válasszon márkát...</option>';
        return;
    }
    
    try {
        const response = await fetch(`get_dependent_data.php?action=models&brand_id=${brandId}`);
        const data = await response.json();
        
        modelSelect.innerHTML = '<option value="">Válasszon modellt...</option>';
        data.forEach(model => {
            modelSelect.add(new Option(model.name, model.id));
        });
    } catch (error) {
        console.error('Hiba a modellek lekérésénél:', error);
        modelSelect.innerHTML = '<option value="">Hiba történt a modellek betöltésekor</option>';
    }
}

// Függő mezők kezelő függvények - globális scope-ba helyezve
async function updateSecondtypesForBrand(typeId) {
    const secondtypeSelect = document.getElementById('brandSecondtypeId');
    const brandNameInput = document.getElementById('brandName');
    
    if (!typeId) {
        secondtypeSelect.innerHTML = '<option value="">Először válasszon típust...</option>';
        brandNameInput.setAttribute('readonly', true);
        brandNameInput.value = '';
        brandNameInput.placeholder = 'Először válasszon altípust';
        return;
    }
    
    try {
        const response = await fetch(`/Vizsga_oldal/dashboard/get_dependent_data.php?action=secondtypes&type_id=${typeId}`);
        const data = await response.json();
        
        secondtypeSelect.innerHTML = '<option value="">Válasszon altípust...</option>';
        if (Array.isArray(data) && data.length > 0) {
            data.forEach(secondtype => {
                secondtypeSelect.add(new Option(secondtype.name, secondtype.id));
            });
            brandNameInput.placeholder = 'Válasszon altípust';
        } else {
            secondtypeSelect.innerHTML = '<option value="">Nincsenek elérhető altípusok</option>';
            brandNameInput.placeholder = 'Először válasszon altípust';
        }
        
        brandNameInput.setAttribute('readonly', true);
        brandNameInput.value = '';
    } catch (error) {
        console.error('Hiba az altípusok lekérésénél:', error);
        secondtypeSelect.innerHTML = '<option value="">Hiba történt az altípusok betöltésekor</option>';
        brandNameInput.setAttribute('readonly', true);
        brandNameInput.value = '';
        brandNameInput.placeholder = 'Hiba történt';
    }
}

// Altípus változás kezelése a márka hozzáadása formnál
document.getElementById('brandSecondtypeId').addEventListener('change', function() {
    const brandNameInput = document.getElementById('brandName');
    if (this.value) {
        brandNameInput.removeAttribute('readonly');
        brandNameInput.placeholder = 'Adja meg a márka nevét';
    } else {
        brandNameInput.setAttribute('readonly', true);
        brandNameInput.value = '';
        brandNameInput.placeholder = 'Először válasszon altípust';
    }
});

// Modell űrlap függő mezők kezelése
async function updateSecondtypesForModel(typeId) {
    const secondtypeSelect = document.getElementById('modelSecondtypeId');
    const brandSelect = document.getElementById('modelBrandId');
    const modelNameInput = document.getElementById('modelName');
    
    if (!typeId) {
        secondtypeSelect.innerHTML = '<option value="">Először válasszon típust...</option>';
        brandSelect.innerHTML = '<option value="">Először válasszon altípust...</option>';
        modelNameInput.setAttribute('readonly', true);
        modelNameInput.value = '';
        modelNameInput.placeholder = 'Először válasszon márkát';
        return;
    }
    
    try {
        const response = await fetch(`/Vizsga_oldal/dashboard/get_dependent_data.php?action=secondtypes&type_id=${typeId}`);
        const data = await response.json();
        
        secondtypeSelect.innerHTML = '<option value="">Válasszon altípust...</option>';
        if (Array.isArray(data) && data.length > 0) {
            data.forEach(secondtype => {
                secondtypeSelect.add(new Option(secondtype.name, secondtype.id));
            });
        } else {
            secondtypeSelect.innerHTML = '<option value="">Nincsenek elérhető altípusok</option>';
        }
        
        brandSelect.innerHTML = '<option value="">Először válasszon altípust...</option>';
        modelNameInput.setAttribute('readonly', true);
        modelNameInput.value = '';
        modelNameInput.placeholder = 'Először válasszon márkát';
    } catch (error) {
        console.error('Hiba az altípusok lekérésénél:', error);
        secondtypeSelect.innerHTML = '<option value="">Hiba történt az altípusok betöltésekor</option>';
    }
}

async function updateBrandsForModel(secondtypeId) {
    const brandSelect = document.getElementById('modelBrandId');
    const modelNameInput = document.getElementById('modelName');
    
    if (!secondtypeId) {
        brandSelect.innerHTML = '<option value="">Először válasszon altípust...</option>';
        modelNameInput.setAttribute('readonly', true);
        modelNameInput.value = '';
        modelNameInput.placeholder = 'Először válasszon márkát';
        return;
    }
    
    try {
        const response = await fetch(`/Vizsga_oldal/dashboard/get_dependent_data.php?action=brands&secondtype_id=${secondtypeId}`);
        const data = await response.json();
        
        brandSelect.innerHTML = '<option value="">Válasszon márkát...</option>';
        if (Array.isArray(data) && data.length > 0) {
            data.forEach(brand => {
                brandSelect.add(new Option(brand.name, brand.id));
            });
        } else {
            brandSelect.innerHTML = '<option value="">Nincsenek elérhető márkák</option>';
        }
        
        modelNameInput.setAttribute('readonly', true);
        modelNameInput.value = '';
        modelNameInput.placeholder = 'Először válasszon márkát';
    } catch (error) {
        console.error('Hiba a márkák lekérésénél:', error);
        brandSelect.innerHTML = '<option value="">Hiba történt a márkák betöltésekor</option>';
    }
}

// Márka változás kezelése
const brandSelect = document.getElementById('brand_id');
if (brandSelect) {
    brandSelect.addEventListener('change', async function() {
        const brandId = this.value;
        const modelSelect = document.getElementById('model_id');
        const yearSelect = document.getElementById('manufacture_date');
        
        if (!brandId) {
            modelSelect.innerHTML = '<option value="">Válasszon modellt...</option>';
            yearSelect.value = '';
            yearSelect.setAttribute('readonly', true);
            return;
        }
        
        try {
            const response = await fetch(`/Vizsga_oldal/dashboard/get_dependent_data.php?action=models&brand_id=${brandId}`);
            const data = await response.json();
            
            modelSelect.innerHTML = '<option value="">Válasszon modellt...</option>';
            if (data.length > 0) {
                data.forEach(model => {
                    modelSelect.add(new Option(model.name, model.id));
                });
            } else {
                modelSelect.innerHTML = '<option value="">Nincsenek elérhető modellek</option>';
            }
            
            // Reset year field
            yearSelect.value = '';
            yearSelect.setAttribute('readonly', true);
        } catch (error) {
            console.error('Hiba a modellek lekérésénél:', error);
            modelSelect.innerHTML = '<option value="">Hiba történt a modellek betöltésekor</option>';
        }
    });
}

// Modell változás kezelése
const modelSelect = document.getElementById('model_id');
if (modelSelect) {
    modelSelect.addEventListener('change', async function() {
        const modelId = this.value;
        const yearInput = document.getElementById('manufacture_date');
        const yearDisplay = document.getElementById('manufacture_date_display');
        
        if (!modelId) {
            yearInput.value = '';
            yearDisplay.value = '';
            yearDisplay.setAttribute('readonly', true);
            return;
        }
        
        try {
            const response = await fetch(`/Vizsga_oldal/dashboard/get_dependent_data.php?action=years&model_id=${modelId}`);
            const data = await response.json();
            
            if (data.length > 0) {
                yearDisplay.removeAttribute('readonly');
                // Az első év ID-jét és értékét beállítjuk
                yearInput.value = data[0].id;
                yearDisplay.value = data[0].year;
            } else {
                yearInput.value = '';
                yearDisplay.value = '';
                yearDisplay.setAttribute('readonly', true);
            }
        } catch (error) {
            console.error('Hiba az évek lekérésénél:', error);
            yearInput.value = '';
            yearDisplay.value = '';
            yearDisplay.setAttribute('readonly', true);
        }
    });
}

// Kedvenc toggle kezelése
async function toggleFavorite(id) {
    try {
        const response = await fetch('/Vizsga_oldal/dashboard/toggle_favorite.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        });

        const data = await response.json();
        console.log('Toggle favorite response:', data);

        if (data.success) {
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (!row) {
                console.error('Row not found for id:', id);
                return;
            }
            
            const button = row.querySelector('.favorite-btn');
            if (!button) {
                console.error('Favorite button not found in row:', row);
                return;
            }
            
            const tbody = row.parentElement;
            
            // Kedvenc státusz frissítése
            if (data.is_favorite) {
                button.classList.add('active');
                button.classList.remove('btn-outline-warning');
                button.classList.add('btn-warning');
                row.classList.add('favorite-row');
                
                // Kedvencek közé mozgatás
                const firstNonFavorite = tbody.querySelector('tr:not(.favorite-row)');
                if (firstNonFavorite) {
                    tbody.insertBefore(row, firstNonFavorite);
                }
            } else {
                button.classList.remove('active');
                button.classList.remove('btn-warning');
                button.classList.add('btn-outline-warning');
                row.classList.remove('favorite-row');
                
                // Nem kedvencek közé mozgatás
                const lastFavorite = Array.from(tbody.querySelectorAll('.favorite-row')).pop();
                if (lastFavorite) {
                    tbody.insertBefore(row, lastFavorite.nextSibling);
                } else {
                    tbody.insertBefore(row, tbody.firstChild);
                }
            }

            // Animáció hozzáadása
            row.style.animation = 'none';
            row.offsetHeight; // Reflow trigger
            row.style.animation = 'highlightRow 0.8s ease-out';

            // Szűrők és számozás frissítése
            updateFilterCounts();
            updateRowNumbers();

            // Lapozás frissítése
            filteredRows = Array.from(document.querySelectorAll('.eszköz-sor'))
                .filter(row => !row.classList.contains('hidden'));
            
            const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
            updatePagination(totalPages);
            showPage(currentPage);

            // Sikeres művelet visszajelzése
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message || 'Hiba történt a kedvenc státusz módosításakor!', 'error');
        }
    } catch (error) {
        console.error('Hiba:', error);
        showNotification('Hiba történt a kedvenc státusz módosításakor!', 'error');
    }
}

// Értesítés megjelenítése függvény
function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    const messageElement = document.getElementById('notification-message');
    
    // Notification stílus beállítása
    notification.className = 'notification';
    notification.classList.add(type === 'success' ? 'success' : 'error');
    
    messageElement.textContent = message;
    notification.style.display = 'block';
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.5s ease-out';
        setTimeout(() => {
            notification.style.display = 'none';
            notification.style.animation = 'slideIn 0.5s ease-out';
        }, 500);
    }, 3000);
}

// Eszköz hozzáadása után frissítő függvény módosítása
async function addNewItem(formData) {
    try {
        // Ellenőrizzük a gyártási év mezőt
        const manufactureDate = formData.get('manufacture_date');
        if (!manufactureDate) {
            throw new Error('Kérem válasszon gyártási évet!');
        }

        const response = await fetch('/Vizsga_oldal/dashboard/add_stuff.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Raw response:', responseText);
            throw new Error('Hibás válasz a szervertől');
        }
        
        if (result.success) {
            // Sikeres üzenet megjelenítése
            showNotification(result.message, 'success');
            
            // Form törlése
            document.getElementById('addItemForm').reset();
            
            // Modal bezárása ha van
            if (typeof closeModal === 'function') {
                closeModal('addItemModal');
            }
            
            // Az oldal azonnali frissítése
            window.location.reload();
        } else {
            showNotification(result.message || 'Hiba történt az eszköz hozzáadásakor!', 'error');
        }
    } catch (error) {
        console.error('Hiba:', error);
        showNotification(error.message || 'Hiba történt az eszköz hozzáadásakor!', 'error');
    }
}

// Új függvény a táblázat frissítéséhez
function refreshTable() {
    // Frissítjük a szűrőket és számokat
    updateFilterCounts();
    
    // Frissítjük a filteredRows tömböt
    filteredRows = Array.from(document.querySelectorAll('.eszköz-sor')).filter(row => !row.classList.contains('hidden'));
    
    // Frissítjük a lapozó gombokat
    const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
    updatePagination(totalPages);
    
    // Az első oldalon maradunk
    showPage(1);
    
    // Frissítjük a sorszámokat
    updateRowNumbers();
}

// Tooltip inicializáló függvény
function initializeTooltips(container) {
    container.querySelectorAll('.tooltip-cell').forEach(cell => {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = cell.dataset.fullText;
        cell.appendChild(tooltip);

        cell.addEventListener('mousemove', function(e) {
            const tooltip = this.querySelector('.tooltip');
            if (tooltip) {
                tooltip.style.display = 'block';
                tooltip.style.left = (e.pageX + 10) + 'px';
                tooltip.style.top = (e.pageY - 25) + 'px';
            }
        });

        cell.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.tooltip');
            if (tooltip) {
                tooltip.style.display = 'none';
            }
        });
    });
}

// Form submit eseménykezelő módosítása
document.getElementById('addItemForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    await addNewItem(formData);
});

// Form submit kezelők
document.getElementById('addBrandForm').addEventListener('submit', async function(e) {
    e.preventDefault(); // Megakadályozzuk az oldal újratöltését
    
    try {
        const formData = new FormData(this);
        
        // Debug: Log the data being sent
        console.log('Sending data:', Object.fromEntries(formData));
        
        const response = await fetch('/Vizsga_oldal/dashboard/add_brand.php', {
            method: 'POST',
            body: formData
        });
        
        // Debug: Log the raw response
        const responseText = await response.text();
        console.log('Server response status:', response.status);
        console.log('Raw server response:', responseText);
        
        // Try to parse JSON only if we have content
        let data;
        if (responseText.trim()) {
            try {
                data = JSON.parse(responseText);
            } catch (error) {
                console.error('JSON parse error:', error);
                throw new Error('A szervertől érkező válasz nem megfelelő formátumú');
            }
        } else {
            throw new Error('A szerver nem küldött választ');
        }
        
        if (!response.ok) {
            throw new Error(data?.error || 'Szerver hiba történt');
        }
        
        if (data.success) {
            // Frissítjük a márka select mezőt az új márkával
            const brandSelect = document.getElementById('brand_id');
            const option = new Option(data.name, data.id);
            brandSelect.add(option);
            brandSelect.value = data.id;
            
            // Modal bezárása
            closeModal('addBrandModal');
            
            // Form törlése
            this.reset();
            
            // Értesítés megjelenítése
            showNotification('Márka sikeresen hozzáadva!');
        } else {
            throw new Error(data.error || 'Ismeretlen hiba történt');
        }
    } catch (error) {
        console.error('Error:', error);
        alert(error.message || 'Hiba történt a mentés során');
    }
});

// Form submit kezelők
document.getElementById('addModelForm').addEventListener('submit', async function(e) {
    e.preventDefault(); // Megakadályozzuk az oldal újratöltését
    
    try {
        const formData = new FormData(this);
        
        // Debug: Ellenőrizzük a küldendő adatokat
        console.log('Küldendő adatok:', Object.fromEntries(formData));
        
        const response = await fetch('/Vizsga_oldal/dashboard/add_model.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        // Debug: Ellenőrizzük a választ
        const responseText = await response.text();
        console.log('Szerver válasz:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (error) {
            console.error('JSON parse error:', error);
            throw new Error('Hibás válasz a szervertől');
        }
        
        if (data.success) {
            // Frissítjük a modell select mezőt az új modellel
            const modelSelect = document.getElementById('model_id');
            const option = new Option(data.name, data.id);
            modelSelect.add(option);
            modelSelect.value = data.id;
            
            // Modal bezárása
            closeModal('addModelModal');
            
            // Form törlése
            this.reset();
            
            // Értesítés megjelenítése
            showNotification('Modell sikeresen hozzáadva!');
        } else {
            throw new Error(data.error || 'Ismeretlen hiba történt');
        }
    } catch (error) {
        console.error('Error:', error);
        alert(error.message || 'Hiba történt a mentés során');
    }
});

// Típus változás kezelése
const typeSelect = document.getElementById('type_id');
if (typeSelect) {
    typeSelect.addEventListener('change', async function() {
        const typeId = this.value;
        const secondtypeSelect = document.getElementById('secondtype_id');
        const brandSelect = document.getElementById('brand_id');
        const modelSelect = document.getElementById('model_id');
        const yearSelect = document.getElementById('manufacture_date');
        
        if (!typeId) {
            secondtypeSelect.innerHTML = '<option value="">Válasszon altípust...</option>';
            brandSelect.innerHTML = '<option value="">Válasszon márkát...</option>';
            modelSelect.innerHTML = '<option value="">Válasszon modellt...</option>';
            yearSelect.value = '';
            yearSelect.setAttribute('readonly', true);
            return;
        }
        
        try {
            const response = await fetch(`/Vizsga_oldal/dashboard/get_dependent_data.php?action=secondtypes&type_id=${typeId}`);
            const data = await response.json();
            
            secondtypeSelect.innerHTML = '<option value="">Válasszon altípust...</option>';
            if (Array.isArray(data) && data.length > 0) {
                data.forEach(secondtype => {
                    secondtypeSelect.add(new Option(secondtype.name, secondtype.id));
                });
            } else {
                secondtypeSelect.innerHTML = '<option value="">Nincsenek elérhető altípusok</option>';
            }
            
            // Töröljük a függő mezők tartalmát
            brandSelect.innerHTML = '<option value="">Válasszon márkát...</option>';
            modelSelect.innerHTML = '<option value="">Válasszon modellt...</option>';
            yearSelect.value = '';
            yearSelect.setAttribute('readonly', true);
        } catch (error) {
            console.error('Hiba az altípusok lekérésénél:', error);
            secondtypeSelect.innerHTML = '<option value="">Hiba történt az altípusok betöltésekor</option>';
        }
    });
}

// Altípus változás kezelése
const secondtypeSelect = document.getElementById('secondtype_id');
if (secondtypeSelect) {
    secondtypeSelect.addEventListener('change', async function() {
        const secondtypeId = this.value;
        const brandSelect = document.getElementById('brand_id');
        const modelSelect = document.getElementById('model_id');
        const yearSelect = document.getElementById('manufacture_date');
        
        if (!secondtypeId) {
            brandSelect.innerHTML = '<option value="">Válasszon márkát...</option>';
            modelSelect.innerHTML = '<option value="">Válasszon modellt...</option>';
            yearSelect.value = '';
            yearSelect.setAttribute('readonly', true);
            return;
        }
        
        try {
            const response = await fetch(`/Vizsga_oldal/dashboard/get_dependent_data.php?action=brands&secondtype_id=${secondtypeId}`);
            const data = await response.json();
            
            brandSelect.innerHTML = '<option value="">Válasszon márkát...</option>';
            if (Array.isArray(data) && data.length > 0) {
                data.forEach(brand => {
                    brandSelect.add(new Option(brand.name, brand.id));
                });
            } else {
                brandSelect.innerHTML = '<option value="">Nincsenek elérhető márkák</option>';
            }
            
            // Reset dependent fields
            modelSelect.innerHTML = '<option value="">Válasszon modellt...</option>';
            yearSelect.value = '';
            yearSelect.setAttribute('readonly', true);
        } catch (error) {
            console.error('Hiba a márkák lekérésénél:', error);
            brandSelect.innerHTML = '<option value="">Hiba történt a márkák betöltésekor</option>';
        }
    });
}

// Form submit kezelők
document.getElementById('addYearForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    try {
        const formData = new FormData(this);
        
        const response = await fetch('/Vizsga_oldal/dashboard/add_year.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Frissítjük mindkét mezőt
            const yearInput = document.getElementById('manufacture_date');
            const yearDisplay = document.getElementById('manufacture_date_display');
            
            yearInput.value = data.id;
            yearDisplay.value = data.display;
            yearDisplay.removeAttribute('readonly');
            
            // Modal bezárása
            closeModal('addYearModal');
            
            // Form törlése
            this.reset();
            
            // Értesítés megjelenítése
            showNotification('Gyártási év sikeresen hozzáadva!');
        } else {
            throw new Error(data.error || 'Ismeretlen hiba történt');
        }
    } catch (error) {
        console.error('Error:', error);
        alert(error.message || 'Hiba történt a mentés során');
    }
});

// Márka változás kezelése a modell hozzáadásánál
document.getElementById('modelBrandId').addEventListener('change', function() {
    const modelNameInput = document.getElementById('modelName');
    if (this.value) {
        modelNameInput.removeAttribute('readonly');
        modelNameInput.placeholder = 'Írja be a modell nevét';
    } else {
        modelNameInput.setAttribute('readonly', true);
        modelNameInput.value = '';
        modelNameInput.placeholder = 'Először válasszon márkát';
    }
});

function validateYear(input) {
    // Csak számokat engedünk meg
    input.value = input.value.replace(/[^0-9]/g, '').substring(0, 4);
    
    // Ellenőrizzük a minimum és maximum értékeket
    const currentYear = new Date().getFullYear();
    const year = parseInt(input.value);
    
    if (year < 1980) {
        input.value = '1980';
    } else if (year > currentYear) {
        input.value = currentYear;
    }
}

function toggleDropdown(event, button) {
    event.preventDefault();
    event.stopPropagation();
    
    // Minden más dropdown bezárása
    const dropdowns = document.querySelectorAll('.dropdown-menu');
    dropdowns.forEach(dropdown => {
        if (dropdown !== button.nextElementSibling) {
            closeDropdown(dropdown);
        }
    });
    
    const menu = button.nextElementSibling;
    if (menu.classList.contains('show')) {
        closeDropdown(menu);
    } else {
        // A dropdown pozicionálása a gomb mellé
        const buttonRect = button.getBoundingClientRect();
        menu.style.top = buttonRect.top + 'px';
        menu.style.left = (buttonRect.right + 5) + 'px';
        
        menu.classList.add('show');
        menu.style.display = 'block';
        menu.style.opacity = '1';
        menu.style.transform = 'scale(1)';
    }
}

// Sorok eseménykezelőinek beállítása
document.querySelectorAll('.eszköz-sor').forEach(row => {
    // Jobb kattintás eseménykezelő
    row.addEventListener('contextmenu', function(event) {
        event.preventDefault(); // Alapértelmezett kontextus menü megakadályozása
        
        // Megkeressük a dropdown menüt a sorban
        const dropdownMenu = this.querySelector('.dropdown-menu');
        
        if (dropdownMenu) {
            // Minden más dropdown bezárása
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                if (menu !== dropdownMenu) {
                    closeDropdown(menu);
                }
            });
            
            // A dropdown pozicionálása a kattintás helyére
            dropdownMenu.style.position = 'fixed';
            dropdownMenu.style.display = 'block';
            dropdownMenu.style.visibility = 'hidden';
            
            // Méretek kiszámítása
            const menuRect = dropdownMenu.getBoundingClientRect();
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            
            // Pozíció számítása
            let left = event.clientX;
            let top = event.clientY;
            
            // Ha jobbra kilógna
            if (left + menuRect.width > windowWidth) {
                left = windowWidth - menuRect.width - 5;
            }
            
            // Ha lefelé kilógna
            if (top + menuRect.height > windowHeight) {
                top = windowHeight - menuRect.height - 5;
            }
            
            // Pozíció beállítása
            dropdownMenu.style.left = left + 'px';
            dropdownMenu.style.top = top + 'px';
            dropdownMenu.style.visibility = 'visible';
            dropdownMenu.style.opacity = '0';
            dropdownMenu.style.transform = 'scale(0.95)';
            
            // Animáció indítása
            requestAnimationFrame(() => {
                dropdownMenu.classList.add('show');
                dropdownMenu.style.opacity = '1';
                dropdownMenu.style.transform = 'scale(1)';
            });
        }
    });
});

// Módosított bezárás függvény
function closeDropdown(menu) {
    menu.classList.remove('show');
    menu.style.opacity = '0';
    menu.style.transform = 'scale(0.95)';
    
    setTimeout(() => {
        menu.style.display = 'none';
    }, 200);
}

// Dokumentum kattintás eseménykezelő
document.addEventListener('click', function(event) {
    if (!event.target.matches('.dropdown-toggle') && !event.target.closest('.dropdown-menu')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            closeDropdown(menu);
        });
    }
});

// Görgetés eseménykezelő
window.addEventListener('scroll', function() {
    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
        closeDropdown(menu);
    });
});

// Típus szűrő funkció módosítása
document.querySelectorAll('.type-filter').forEach(filter => {
    filter.addEventListener('click', function() {
        // Az összes többi szűrő deaktiválása
        document.querySelectorAll('.type-filter').forEach(f => {
            if (f !== this) {
                f.classList.remove('active');
                f.classList.add('inactive');
            }
        });
        
        // A kiválasztott szűrő toggle-olása
        const wasActive = this.classList.contains('active');
        this.classList.toggle('active');
        
        if (wasActive) {
            // Ha már aktív volt, akkor minden szűrőt visszaállítunk
            document.querySelectorAll('.type-filter').forEach(f => {
                f.classList.remove('active', 'inactive');
            });
        } else {
            // Ha most lett aktív, akkor ezt kiemeljük
            this.classList.remove('inactive');
        }
        
        // Sorok szűrése
        const activeType = wasActive ? null : this.dataset.type;
        let visibleRows = 0;
        
        console.log('Aktív szűrő típusa:', activeType); // Debug log

        document.querySelectorAll('.eszköz-sor').forEach((row, index) => {
            if (activeType === 'favorites') {
                // Kedvencek szűrése
                if (!wasActive && !row.classList.contains('favorite-row')) {
                    row.classList.add('hidden');
                } else {
                    row.classList.remove('hidden');
                    visibleRows++;
                }
            } else {
                // Típus szerinti szűrés
                const typeCell = row.querySelector('td:nth-child(2)');
                
                // Debug logok
                console.log(`Sor ${index + 1}:`);
                console.log('- TypeCell létezik:', !!typeCell);
                console.log('- TypeCell tartalma:', typeCell ? typeCell.textContent : 'null');
                console.log('- TypeCell trim után:', typeCell ? typeCell.textContent.trim() : '');
                
                // Itt javítjuk a duplikációt - csak az első előfordulást vesszük
                const rowType = typeCell ? typeCell.textContent.trim().match(/^[^0-9]*/)[0] : '';
                
                console.log('- Tisztított típus:', rowType);
                console.log('- Összehasonlítás:', rowType, '===', activeType);
                console.log('- Egyezik?:', rowType === activeType);
                
                if (!activeType || rowType === activeType) {
                    row.classList.remove('hidden');
                    visibleRows++;
                    console.log('- Sor megjelenítve');
                } else {
                    row.classList.add('hidden');
                    console.log('- Sor elrejtve');
                }
            }
        });
        
        console.log('Látható sorok száma:', visibleRows); // Debug log
        
        // Frissítjük a filteredRows tömböt a szűrés után
        filteredRows = Array.from(document.querySelectorAll('.eszköz-sor'))
            .filter(row => !row.classList.contains('hidden'));
        
        // "Nincs találat" üzenet kezelése
        handleNoResults(visibleRows);
        
        // Lapozás frissítése
        currentPage = 1;
        const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
        updatePagination(totalPages);
        showPage(1);
    });
});

// Összes elem számának frissítése
function updateTotalCounts() {
    const typeCounts = {};
    
    // Összes sor megszámolása típusonként
    document.querySelectorAll('.eszköz-sor').forEach(row => {
        const type = row.querySelector('td:nth-child(2)').textContent.trim();
        typeCounts[type] = (typeCounts[type] || 0) + 1;
    });
    
    // Számok frissítése a szűrőkben
    document.querySelectorAll('.type-filter').forEach(filter => {
        const type = filter.dataset.type;
        const countElement = filter.querySelector('.type-count');
        countElement.textContent = typeCounts[type] || 0;
    });
}

// Oldalankénti elemszám kiszámítása függvény módosítása
function calculateItemsPerPage() {
    return 9; // Fix 9 elem oldalanként
}

// Lapozó rendszer módosítása - itemsPerPage változó módosítása
let itemsPerPage = 9; // Fix érték 9-re módosítva
let currentPage = 1;
let filteredRows = [];

// Inicializálás az oldal betöltésekor
document.addEventListener('DOMContentLoaded', () => {
    initPagination();
});

function initPagination() {
    const tableBody = document.querySelector('.table tbody');
    const rows = Array.from(tableBody.querySelectorAll('.eszköz-sor'));
    filteredRows = rows.filter(row => !row.classList.contains('hidden'));
    const totalPages = Math.ceil(filteredRows.length / itemsPerPage);

    // Lapozó gombok létrehozása
    updatePagination(totalPages);
    
    // Sorok megjelenítése
    showPage(currentPage);
}

function updatePagination(totalPages) {
    const paginationContainer = document.querySelector('.pagination-container') || createPaginationContainer();
    paginationContainer.innerHTML = '';

    // Előző gomb
    const prevButton = document.createElement('button');
    prevButton.className = 'pagination-button';
    prevButton.innerHTML = '&laquo;';
    prevButton.disabled = currentPage === 1;
    prevButton.onclick = () => showPage(currentPage - 1);
    paginationContainer.appendChild(prevButton);

    // Oldalszámok konténer
    const numbersContainer = document.createElement('div');
    numbersContainer.className = 'pagination-numbers';
    
    // Oldalszámok logika
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);
    
    // Első oldal megjelenítése
    if (startPage > 1) {
        numbersContainer.appendChild(createPageButton(1));
        if (startPage > 2) {
            const dots = document.createElement('span');
            dots.className = 'pagination-dots';
            dots.textContent = '...';
            numbersContainer.appendChild(dots);
        }
    }
    
    // Középső oldalszámok
    for (let i = startPage; i <= endPage; i++) {
        numbersContainer.appendChild(createPageButton(i));
    }
    
    // Utolsó oldal megjelenítése
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const dots = document.createElement('span');
            dots.className = 'pagination-dots';
            dots.textContent = '...';
            numbersContainer.appendChild(dots);
        }
        numbersContainer.appendChild(createPageButton(totalPages));
    }
    
    paginationContainer.appendChild(numbersContainer);

    // Következő gomb
    const nextButton = document.createElement('button');
    nextButton.className = 'pagination-button';
    nextButton.innerHTML = '&raquo;';
    nextButton.disabled = currentPage === totalPages;
    nextButton.onclick = () => showPage(currentPage + 1);
    paginationContainer.appendChild(nextButton);

    // Információs szöveg
    const infoText = document.createElement('span');
    infoText.className = 'pagination-info';
    infoText.textContent = `${filteredRows.length} <?php echo translate('eszköz összesen'); ?>`;
    paginationContainer.appendChild(infoText);
}

function createPageButton(pageNum) {
    const button = document.createElement('button');
    button.className = `pagination-button ${currentPage === pageNum ? 'active' : ''}`;
    button.textContent = pageNum;
    button.onclick = () => showPage(pageNum);
    return button;
}

function createPaginationContainer() {
    const container = document.createElement('div');
    container.className = 'pagination-container';
    document.querySelector('.card').appendChild(container);
    return container;
}

function showPage(pageNumber) {
    currentPage = pageNumber;
    const startIndex = (pageNumber - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    
    // Minden sort elrejtünk
    filteredRows.forEach(row => {
        row.style.display = 'none';
    });
    
    // Csak az aktuális oldalhoz tartozó sorokat jelenítjük meg
    for (let i = startIndex; i < endIndex && i < filteredRows.length; i++) {
        filteredRows[i].style.display = '';
    }
    
    // Lapozó gombok frissítése
    const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
    updatePagination(totalPages);
    
    // Sorszámok frissítése
    updateRowNumbers();
}

// Kezdeti lapozó inicializálása
document.addEventListener('DOMContentLoaded', () => {
    initPagination();
});

// Tooltip pozicionálás
document.querySelectorAll('.tooltip-cell').forEach(cell => {
    cell.addEventListener('mousemove', function(e) {
        const tooltip = this.querySelector('::before');
        if (tooltip) {
            tooltip.style.left = e.pageX + 'px';
            tooltip.style.top = e.pageY + 'px';
        }
    });
});

// Tooltip kezelés
document.querySelectorAll('.tooltip-cell').forEach(cell => {
    // Tooltip elem létrehozása
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = cell.dataset.fullText;
    cell.appendChild(tooltip);

    // Egér mozgás eseménykezelő
    cell.addEventListener('mousemove', function(e) {
        const tooltip = this.querySelector('.tooltip');
        if (tooltip) {
            tooltip.style.display = 'block';
            tooltip.style.left = (e.pageX + 10) + 'px';
            tooltip.style.top = (e.pageY - 25) + 'px';
        }
    });

    // Egér távozás eseménykezelő
    cell.addEventListener('mouseleave', function() {
        const tooltip = this.querySelector('.tooltip');
        if (tooltip) {
            tooltip.style.display = 'none';
        }
    });
});

// Segédfüggvények
function handleNoResults(show) {
    const noResultsDiv = document.getElementById('no-results');
    if (noResultsDiv) {  // Add null check
        noResultsDiv.style.display = show ? 'block' : 'none';
    }
}

function updatePaginationAfterFilter() {
    setTimeout(() => {
        currentPage = 1;
        initPagination();
    }, 300);
}

// QR kód generálás függvény módosítása
async function generateNewQR(id) {
    try {
        const response = await fetch('/Vizsga_oldal/dashboard/generate_qr.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        });

        const data = await response.json();

        if (data.success) {
            // QR kód mező frissítése az adott sorban
            const row = document.querySelector(`tr[data-id="${id}"]`);
            const qrCell = row.querySelector('td:nth-child(8)'); // A QR kód oszlop
            
            // Frissítjük a cella tartalmát és attribútumait
            qrCell.className = 'tooltip-cell';
            qrCell.dataset.fullText = data.new_qr;
            qrCell.textContent = data.new_qr;
            
            // Tooltip újrainicializálása
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = data.new_qr;
            
            // Régi tooltip eltávolítása ha létezik
            const oldTooltip = qrCell.querySelector('.tooltip');
            if (oldTooltip) {
                oldTooltip.remove();
            }
            
            // Új tooltip hozzáadása
            qrCell.appendChild(tooltip);
            
            // Eseménykezelők újra hozzáadása
            qrCell.addEventListener('mousemove', function(e) {
                const tooltip = this.querySelector('.tooltip');
                if (tooltip) {
                    tooltip.style.display = 'block';
                    tooltip.style.left = (e.pageX + 10) + 'px';
                    tooltip.style.top = (e.pageY - 25) + 'px';
                }
            });

            qrCell.addEventListener('mouseleave', function() {
                const tooltip = this.querySelector('.tooltip');
                if (tooltip) {
                    tooltip.style.display = 'none';
                }
            });
            
            // Értesítés megjelenítése
            showNotification('QR kód sikeresen frissítve!', 'success');
        } else {
            showNotification(data.message || 'Hiba történt a QR kód generálása során!', 'error');
        }
    } catch (error) {
        console.error('Hiba:', error);
        showNotification('Hiba történt a QR kód generálása során!', 'error');
    }
}

// Új függvény a sorszámok frissítéséhez
function updateRowNumbers() {
    let currentNumber = 1;
    document.querySelectorAll('.eszköz-sor:not(.hidden)').forEach(row => {
        const numberCell = row.querySelector('td:first-child');
        if (numberCell) {
            numberCell.textContent = currentNumber++;
        }
    });
}

// Új függvény a szűrők számainak frissítéséhez
function updateFilterCounts() {
    const typeCounts = {};
    let favoritesCount = 0;
    
    document.querySelectorAll('.eszköz-sor:not(.hidden)').forEach(row => {
        const type = row.querySelector('td:nth-child(2)').textContent.trim();
        typeCounts[type] = (typeCounts[type] || 0) + 1;
        
        if (row.classList.contains('favorite-row')) {
            favoritesCount++;
        }
    });
    
    // Frissítjük a típus szűrők számait
    document.querySelectorAll('.type-filter').forEach(filter => {
        const type = filter.dataset.type;
        const countElement = filter.querySelector('.type-count');
        
        if (type === 'favorites') {
            countElement.textContent = favoritesCount;
        } else {
            countElement.textContent = typeCounts[type] || 0;
        }
    });
}

// A script részben a többi függvény után

// Státusz szűrő kezelése
document.querySelectorAll('.status-filter').forEach(filter => {
    filter.addEventListener('click', function() {
        // Típus szűrők visszaállítása
        document.querySelectorAll('.type-filter').forEach(f => {
            f.classList.remove('active', 'inactive');
        });
        
        // Státusz szűrők kezelése
        document.querySelectorAll('.status-filter').forEach(f => {
            if (f !== this) {
                f.classList.remove('active');
                f.classList.add('inactive');
            }
        });
        
        const wasActive = this.classList.contains('active');
        this.classList.toggle('active');
        
        if (wasActive) {
            document.querySelectorAll('.status-filter').forEach(f => {
                f.classList.remove('active', 'inactive');
            });
        } else {
            this.classList.remove('inactive');
        }
        
        // Sorok szűrése
        const activeStatus = wasActive ? null : this.dataset.status;
        let visibleRows = 0;
        
        document.querySelectorAll('.eszköz-sor').forEach(row => {
            const statusCell = row.querySelector('td:nth-child(7)');
            if (!activeStatus || statusCell.textContent.trim() === activeStatus) {
                row.style.opacity = '1';
                row.classList.remove('hidden');
                visibleRows++;
            } else {
                row.style.opacity = '0';
                setTimeout(() => row.classList.add('hidden'), 300);
            }
        });
        
        handleNoResults(visibleRows);
        updatePaginationAfterFilter();
    });
});

// Módosítsuk az updateFilterCounts függvényt
function updateFilterCounts() {
    const typeCounts = {};
    const statusCounts = {};
    let favoritesCount = 0;
    
    document.querySelectorAll('.eszköz-sor:not(.hidden)').forEach(row => {
        const type = row.querySelector('td:nth-child(2)').textContent.trim();
        const status = row.querySelector('td:nth-child(7)').textContent.trim();
        
        typeCounts[type] = (typeCounts[type] || 0) + 1;
        statusCounts[status] = (statusCounts[status] || 0) + 1;
        
        if (row.classList.contains('favorite-row')) {
            favoritesCount++;
        }
    });
    
    // Típus szűrők frissítése
    document.querySelectorAll('.type-filter').forEach(filter => {
        const type = filter.dataset.type;
        const countElement = filter.querySelector('.type-count');
        
        if (type === 'favorites') {
            countElement.textContent = favoritesCount;
        } else {
            countElement.textContent = typeCounts[type] || 0;
        }
    });
    
    // Státusz szűrők frissítése
    document.querySelectorAll('.status-filter').forEach(filter => {
        const status = filter.dataset.status;
        const countElement = filter.querySelector('.status-count');
        countElement.textContent = statusCounts[status] || 0;
    });
}

// Eszköz szerkesztése függvény
function editEszköz(id) {
    const modal = document.getElementById('editStatusModal');
    const content = modal.querySelector('.modal-content');
    const itemIdInput = document.getElementById('editItemId');
    
    itemIdInput.value = id;
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => {
        content.style.transform = 'translateY(0)';
        content.style.opacity = '1';
    }, 10);
    
    // Dropdown menü bezárása
    const dropdowns = document.querySelectorAll('.dropdown-menu.show');
    dropdowns.forEach(dropdown => closeDropdown(dropdown));
}

// Várjuk meg, amíg a DOM betöltődik
document.addEventListener('DOMContentLoaded', function() {
    // Státusz szerkesztése form kezelése
    const editStatusForm = document.getElementById('editStatusForm');
    if (editStatusForm) {
        editStatusForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('/Vizsga_oldal/dashboard/update_status.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    // Státusz frissítése a táblázatban
                    const row = document.querySelector(`tr[data-id="${formData.get('item_id')}"]`);
                    if (!row) {
                        throw new Error('Az eszköz nem található a táblázatban!');
                    }
                    
                    const statusCell = row.querySelector('td:nth-child(7)');
                    if (!statusCell) {
                        throw new Error('A státusz cella nem található!');
                    }
                    
                    statusCell.textContent = data.new_status_name;
                    
                    // Sor data-status attribútum és szín frissítése
                    row.setAttribute('data-status', data.new_status_name);
                    
                    // Minden státusz-specifikus háttérszín eltávolítása
                    row.querySelectorAll('td').forEach(td => {
                        td.style.backgroundColor = '';
                    });
                    
                    // Új státusz szín alkalmazása
                    const statusColors = {
                        'Használatban': 'rgba(0, 176, 255, 0.1)',
                        'Hibás': 'rgba(255, 23, 68, 0.15)',
                        'Karbantartónál': 'rgba(170, 0, 255, 0.1)',
                        'Törött': 'rgba(255, 153, 0, 0.15)',
                        'Kiszelektálás alatt': 'rgba(121, 85, 72, 0.15)'
                    };
                    
                    if (statusColors[data.new_status_name]) {
                        row.querySelectorAll('td').forEach(td => {
                            td.style.backgroundColor = statusColors[data.new_status_name];
                        });
                    }
                    
                    // Modal bezárása
                    closeModal('editStatusModal');
                    
                    // Form törlése
                    this.reset();
                    
                    // Értesítés megjelenítése
                    showNotification('Státusz sikeresen módosítva!', 'success');
                    
                    // Szűrők és számok frissítése
                    updateFilterCounts();
                } else {
                    throw new Error(data.message || 'Ismeretlen hiba történt a státusz módosítása során!');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification(error.message || 'Hiba történt a státusz módosítása során!', 'error');
                
                // Modal bezárása hiba esetén is
                closeModal('editStatusModal');
                
                // Form törlése hiba esetén is
                this.reset();
            }
        });
    }
});

// Eszköz törlése függvény módosítása
async function deleteEszköz(id) {
    const deleteModal = document.getElementById('deleteConfirmModal');
    const content = deleteModal.querySelector('.modal-content');
    
    // Eszköz adatainak összegyűjtése
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) {
        showNotification('Az eszköz nem található!', 'error');
        return;
    }

    const brand = row.querySelector('td:nth-child(4)').textContent;
    const model = row.querySelector('td:nth-child(5)').textContent;
    const qrCell = row.querySelector('td:nth-child(8)');
    const qrCode = qrCell.dataset.fullText || qrCell.textContent;
    
    // Részletek megjelenítése
    const deleteItemDetails = document.getElementById('deleteItemDetails');
    deleteItemDetails.innerHTML = `
        <div style="text-align: left; margin: 20px 0;">
            <p style="margin: 5px 0;"><strong>Márka:</strong> ${brand}</p>
            <p style="margin: 5px 0;"><strong>Modell:</strong> ${model}</p>
            <p style="margin: 5px 0;"><strong>QR kód:</strong> ${qrCode}</p>
        </div>
    `;
    
    deleteModal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Animáció hozzáadása
    content.style.transform = 'translateY(0)';
    content.style.opacity = '1';
    
    // Törlés megerősítése gomb eseménykezelő
    const handleDelete = async () => {
        try {
            const formData = new FormData();
            formData.append('item_id', id);
            
            const response = await fetch('delete_stuff.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // A sor megkeresése és eltávolítása
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    row.style.animation = 'fadeOut 0.5s ease-out';
                    setTimeout(() => {
                        row.remove();
                        
                        // Frissítjük a filteredRows tömböt
                        filteredRows = Array.from(document.querySelectorAll('.eszköz-sor'))
                            .filter(row => !row.classList.contains('hidden'));
                        
                        // Lapozás frissítése
                        const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
                        updatePagination(totalPages);
                        
                        if (currentPage > totalPages) {
                            currentPage = Math.max(1, totalPages);
                        }
                        showPage(currentPage);
                        
                        updateRowNumbers();
                        updateFilterCounts();
                    }, 500);
                }
                
                // Modal bezárása
                deleteModal.style.display = 'none';
                document.body.style.overflow = '';
                
                showNotification('Eszköz sikeresen törölve!', 'success');
            } else {
                throw new Error(data.message || 'Hiba történt a törlés során!');
            }
        } catch (error) {
            console.error('Hiba:', error);
            showNotification(error.message || 'Hiba történt a törlés során!', 'error');
            
            // Modal bezárása hiba esetén is
            deleteModal.style.display = 'none';
            document.body.style.overflow = '';
        }
        
        // Eseménykezelő eltávolítása
        document.getElementById('confirmDeleteBtn').removeEventListener('click', handleDelete);
    };

    // Megerősítő gomb eseménykezelő hozzáadása
    document.getElementById('confirmDeleteBtn').addEventListener('click', handleDelete);
    
    // Mégsem gomb kezelése
    document.getElementById('cancelDeleteBtn').onclick = () => {
        deleteModal.style.display = 'none';
        document.body.style.overflow = '';
        document.getElementById('confirmDeleteBtn').removeEventListener('click', handleDelete);
    };
}

// A script részben
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('qrSearch');
    
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase().trim();
        let visibleRows = 0;
        
        document.querySelectorAll('.eszköz-sor').forEach(row => {
            const qrCell = row.querySelector('td:nth-child(8)'); // QR kód oszlop
            const qrCode = qrCell.textContent.toLowerCase().trim();
            
            if (qrCode.includes(searchTerm)) {
                row.classList.remove('hidden');
                visibleRows++;
            } else {
                row.classList.add('hidden');
            }
        });
        
        // Frissítjük a filteredRows tömböt
        filteredRows = Array.from(document.querySelectorAll('.eszköz-sor'))
            .filter(row => !row.classList.contains('hidden'));
        
        // "Nincs találat" üzenet kezelése
        handleNoResults(visibleRows);
        
        // Lapozás frissítése
        currentPage = 1;
        const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
        updatePagination(totalPages);
        showPage(1);
        
        // Eltávolítottam a szűrők számainak frissítését
        // updateFilterCounts();
    });
    
    // Keresés törlése ha a szűrőkre kattintanak
    document.querySelectorAll('.type-filter, .status-filter').forEach(filter => {
        filter.addEventListener('click', function() {
            searchInput.value = '';
        });
    });
});


function showNotification(type, message) {
    const notificationDiv = document.createElement('div');
    notificationDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} notification`;
    notificationDiv.textContent = message;
    
    document.body.appendChild(notificationDiv);
    
    setTimeout(() => {
        notificationDiv.remove();
    }, 3000);
}

// JavaScript a sikeres eszköz hozzáadás után
$(document).ready(function() {
    // Ellenőrizzük, hogy a form létezik-e
    console.log('Form keresése:', $('#addStuffForm').length);
    
    $('#addStuffForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Form submitted');
        
        $.ajax({
            url: 'add_stuff.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                console.log('Response:', response);
                if (response.success) {
                    // Először mutatjuk az értesítést
                    showNotification('success', response.message);
                    // Majd átirányítjuk az oldalt
                    setTimeout(function() {
                        window.location.href = window.location.href;
                    }, 1500);
                } else {
                    showNotification('error', response.error || 'Hiba történt az eszköz hozzáadása során.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax hiba:', error);
                showNotification('error', 'Hiba történt: ' + error);
            }
        });
    });
});

function generateNewQR(id) {
    Swal.fire({
        title: 'Új QR kód generálása',
        text: 'Biztosan szeretne új QR kódot generálni? A régi QR kód érvénytelenné válik.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Igen, generálás',
        cancelButtonText: 'Mégsem'
    }).then((result) => {
        if (result.isConfirmed) {
            // Loading állapot mutatása
            Swal.fire({
                title: 'Folyamatban...',
                text: 'QR kód generálása folyamatban',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('/Vizsga_oldal/dashboard/generate_qr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Próbáljuk megtalálni és frissíteni a QR kód elemet
                    const qrElement = document.querySelector(`#qr_code_${id}, [data-qr="${id}"], .qr-code-${id}`);
                    
                    if (qrElement) {
                        // Frissítjük a QR kód megjelenítését
                        qrElement.textContent = data.new_qr;
                        
                        // Sikeres frissítés üzenet
                        Swal.fire({
                            icon: 'success',
                            title: 'Sikeres művelet!',
                            text: data.message,
                            confirmButtonColor: '#3085d6',
                            confirmButtonText: 'Rendben'
                        });
                    } else {
                        console.log('QR kód elem nem található, oldal frissítése szükséges');
                        // Ha nem találtuk meg az elemet, frissítjük az oldalt
                        Swal.fire({
                            icon: 'success',
                            title: 'QR kód sikeresen generálva',
                            confirmButtonColor: '#3085d6',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.reload();
                            }
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Sikertelen művelet',
                        text: data.message || 'A QR kód generálása sikertelen volt. Kérjük, próbálja újra!',
                        confirmButtonColor: '#d33',
                        confirmButtonText: 'Értem'
                    });
                }
            })
            .catch(error => {
                console.error('QR kód generálási hiba:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Rendszerhiba történt',
                    text: 'A QR kód generálása közben váratlan hiba lépett fel. Kérjük, ellenőrizze az internetkapcsolatát és próbálja újra!',
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Bezárás'
                });
            });
        }
    });
}

// Add the device limit modal
function closeDeviceLimitModal() {
    document.getElementById('deviceLimitModal').style.display = 'none';
}

<?php if (isset($show_limit_modal) && $show_limit_modal): ?>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('deviceLimitModal');
    const message = document.getElementById('deviceLimitMessage');
    message.textContent = <?php echo json_encode($error); ?>;
    modal.style.display = 'block';
});
<?php endif; ?>

// Context menu functionality
document.addEventListener('DOMContentLoaded', function() {
    // Create context menu element
    const contextMenu = document.createElement('div');
    contextMenu.className = 'context-menu';
    document.body.appendChild(contextMenu);

    // Add menu items
    const menuItems = [
        { text: 'Státusz módosítása', action: 'edit' },
        { text: 'QR kód generálása', action: 'generateQR' },
        { text: 'Törlés', action: 'delete' }
    ];

    menuItems.forEach(item => {
        const menuItem = document.createElement('div');
        menuItem.className = 'context-menu-item';
        menuItem.textContent = item.text;
        menuItem.addEventListener('click', () => {
            const row = contextMenu.dataset.rowId;
            switch(item.action) {
                case 'edit':
                    editEszköz(row);
                    break;
                case 'generateQR':
                    generateNewQR(row);
                    break;
                case 'delete':
                    deleteEszköz(row);
                    break;
            }
            contextMenu.style.display = 'none';
        });
        contextMenu.appendChild(menuItem);
    });

    // Handle right-click on table rows
    document.querySelectorAll('.table tbody tr').forEach(row => {
        row.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            const rowId = this.dataset.id;
            contextMenu.dataset.rowId = rowId;
            
            // Position the context menu at cursor
            contextMenu.style.display = 'block';
            contextMenu.style.left = e.pageX + 'px';
            contextMenu.style.top = e.pageY + 'px';
        });
    });

    // Close context menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!contextMenu.contains(e.target)) {
            contextMenu.style.display = 'none';
        }
    });
});
</script>

<!-- Státusz szerkesztése modal -->
<div class="modal" id="editStatusModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Státusz módosítása</h2>
            <button type="button" class="close-button" onclick="closeModal('editStatusModal')">&times;</button>
        </div>
        <form id="editStatusForm">
            <input type="hidden" id="editItemId" name="item_id">
            <div class="form-group">
                <label for="newStatus">Új státusz</label>
                <select id="newStatus" name="new_status" class="form-control" required>
                    <option value="">Válasszon státuszt...</option>
                    <?php foreach ($editable_statuses as $status): ?>
                        <option value="<?php echo $status['id']; ?>">
                            <?php echo htmlspecialchars($status['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="status_comment">Megjegyzés</label>
                <textarea id="status_comment" name="status_comment" class="form-control" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Mentés</button>
            </div>
        </form>
    </div>
</div>

<!-- Törlés megerősítő modal -->
<div class="modal" id="deleteConfirmModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="border: none; padding-bottom: 0;">
            <div style="width: 100%; text-align: center;">
                <i class="fas fa-trash-alt" style="font-size: 48px; color: #dc3545; margin-bottom: 15px;"></i>
                <h2 style="margin: 0; color: #343a40;">Biztosan törli?</h2>
                <p id="deleteItemDetails" style="color: #6c757d; margin: 15px 0;"></p>
                <p style="color: #6c757d; margin: 15px 0;">Ez a művelet nem vonható vissza, és az eszköz törlésre kerül!</p>
            </div>
        </div>
        <div style="display: flex; gap: 10px; padding: 20px;">
            <button id="cancelDeleteBtn" class="btn" style="flex: 1; padding: 10px; border-radius: 4px; border: 1px solid #6c757d; background: #6c757d; color: white; cursor: pointer;">
                Mégsem
            </button>
            <button id="confirmDeleteBtn" class="btn" style="flex: 1; padding: 10px; border-radius: 4px; border: none; background: #dc3545; color: white; cursor: pointer;">
                Törlés
            </button>
        </div>
    </div>
</div>

<!-- Add the device limit modal -->
<div class="modal" id="deviceLimitModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php echo translate('Device Limit Warning'); ?></h2>
            <button type="button" class="close-button" onclick="closeDeviceLimitModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p id="deviceLimitMessage"></p>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeviceLimitModal()"><?php echo translate('Close'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php 
// Footer betöltése - javított útvonal 
require_once __DIR__ . '/../includes/layout/footer.php'; 
?> 

<!-- jQuery betöltése -->

<!-- SweetAlert2 betöltése -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php
// Itt véget ér a fájl, minden ami ez után volt (a teljes <!DOCTYPE html>-től kezdődő rész) törlésre kerül
?>


