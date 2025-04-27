<?php 
// Először betöltjük a konfigurációt
require_once 'includes/config.php';

// Ellenőrizzük, hogy ez egy oldal frissítés-e
// Beállítunk egy session változót, ami jelzi, hogy az oldal már be volt töltve
if (isset($_SESSION['page_loaded']) && $_SESSION['page_loaded'] === true) {
    // Ez egy frissítés, töröljük a session-t
    $_SESSION = array();
    
    // Session cookie törlése
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Session megsemmisítése
    session_destroy();
    
    // Indítsunk egy új session-t
    session_start();
} else {
    // Ez az első betöltés, jelöljük, hogy az oldal be lett töltve
    $_SESSION['page_loaded'] = true;
}

// A session_start() hívást töröljük innen, mert már a config.php-ben megtörtént

// Session frissességének ellenőrzése
$session_timeout = 60; // 60 másodperc (1 perc)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // Ha a session régebbi mint a megadott időkorlát, töröljük
    session_unset();
    session_destroy();
    // Átirányítás a bejelentkező oldalra
    header("Location: login.php");
    exit;
}

// Frissítsük az utolsó aktivitás időbélyegét
$_SESSION['last_activity'] = time();

// Debug link - only visible during development
$debug_mode = false; // Set to false in production
if ($debug_mode) {
    echo '<div style="position: fixed; bottom: 10px; right: 10px; background: #f1f1f1; border: 1px solid #ddd; padding: 5px; z-index: 9999;">';
    echo '<a href="session_check.php" target="_blank">Session Debug</a> | ';
    echo '<a href="role_checker.php" target="_blank">Role Check</a> | ';
    echo '<a href="login_debug.php" target="_blank">Login Debug</a> | ';
    echo '<a href="check_company_owner.php" target="_blank">Check Company Owner</a> | ';
    echo '<a href="assign_company_owner.php" target="_blank">Assign Company Owner</a>';
    echo '</div>';
}

// Ellenőrizzük, hogy létezik-e a kapcsolat, ha nem, létrehozzuk
if (!isset($conn) || $conn === null) {
    // Adatbázis kapcsolat létrehozása
    $db_host = "localhost";
    $db_user = "root";
    $db_password = "";
    $db_name = "vizsgaremek";

    $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
    
    // Ellenőrizzük a kapcsolatot
    if ($conn->connect_error) {
        die("Adatbázis kapcsolódási hiba: " . $conn->connect_error);
    }
    
    // Karakterkódolás beállítása
    $conn->set_charset("utf8");
}

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
$logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$is_company_owner = false;
$user_data = null;
$company_data = null;

if ($logged_in) {
    // Lekérjük a felhasználó adatait és szerepköröket egy lekérdezésben
    $query = "SELECT u.*, GROUP_CONCAT(r.role_name) as roles 
              FROM user u 
              LEFT JOIN user_to_roles ur ON u.id = ur.user_id 
              LEFT JOIN roles r ON ur.role_id = r.id 
              WHERE u.id = ? 
              GROUP BY u.id";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $roles = explode(',', $user_data['roles']);
        $is_company_owner = in_array('Cég tulajdonos', $roles);
        
        // Lekérjük a cég adatait, ha cégtulajdonos
        if ($is_company_owner && $user_data['company_id']) {
            $query = "SELECT * FROM company WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_data['company_id']);
            $stmt->execute();
            $company_data = $stmt->get_result()->fetch_assoc();
        }
    } else {
        // Ha nem találjuk a felhasználót, töröljük a session-t
        session_destroy();
        $logged_in = false;
    }
}

// Csomag adatainak lekérése
$csomag = isset($_GET['csomag']) ? strtolower($_GET['csomag']) : '';
$ar = isset($_GET['ar']) ? $_GET['ar'] : '';
$period = isset($_GET['period']) ? $_GET['period'] : 'ho';
$idoszak = isset($_GET['idoszak']) ? $_GET['idoszak'] : 'havi';

// Alapértelmezett felhasználók és eszközök száma csomagonként
$alapertelmezett = [
    'alap' => [
        'felhasznalok' => 5,
        'eszkozok' => 100,
        'alapar_havi' => 29990,
        'alapar_eves' => 305990
    ],
    'kozepes' => [
        'felhasznalok' => 10,
        'eszkozok' => 250,
        'alapar_havi' => 55990,
        'alapar_eves' => 571098
    ],
    'uzleti' => [
        'felhasznalok' => 20,
        'eszkozok' => 500,
        'alapar_havi' => 80990,
        'alapar_eves' => 826098
    ]
];

// Árazási paraméterek
$felhasznalo_ar_havi = 2000; // Ft/felhasználó/hó
$eszkoz_ar_havi = 100; // Ft/eszköz/hó

// Csomag nevek és jellemzők
$csomagok = [
    'alap' => [
        'nev' => 'Alap csomag',
        'jellemzok' => [
            'Alapvető jelentések',
            'Email támogatás'
        ]
    ],
    'kozepes' => [
        'nev' => 'Közepes csomag',
        'jellemzok' => [
            'Részletes jelentések',
            'Prioritásos támogatás'
        ]
    ],
    'uzleti' => [
        'nev' => 'Üzleti csomag',
        'jellemzok' => [
            'Részletes jelentések',
            'Prioritásos támogatás',
            'Telefonos segítségnyújtás'
        ]
    ]
];

// Ellenőrizzük, hogy érvényes-e a csomag
$valid_packages = ['alap', 'kozepes', 'uzleti'];
$valid_package = in_array($csomag, $valid_packages);

if (!$valid_package) {
    // Ha nem érvényes a csomag, átirányítjuk az árak oldalra
    header('Location: arak.php');
    exit;
}

// Kezdeti értékek beállítása
$kezdeti_felhasznalok = isset($alapertelmezett[$csomag]) ? $alapertelmezett[$csomag]['felhasznalok'] : 5;
$kezdeti_eszkozok = isset($alapertelmezett[$csomag]) ? $alapertelmezett[$csomag]['eszkozok'] : 100;
$alapar = isset($alapertelmezett[$csomag]) ? ($period == 'ev' ? $alapertelmezett[$csomag]['alapar_eves'] : $alapertelmezett[$csomag]['alapar_havi']) : $ar;

// JavaScript kódban használt árak frissítése
$js_csomagarak = [
    'alap' => [
        'havi' => 29990,
        'ev' => 305990
    ],
    'kozepes' => [
        'havi' => 55990,
        'ev' => 571098
    ],
    'uzleti' => [
        'havi' => 80990,
        'ev' => 826098
    ]
];
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Megrendelés - VibeCore</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Fizetési módok stílusa */
        .payment-options {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .payment-method-card {
            flex: 1;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fff;
            position: relative;
        }

        .payment-method-card:hover {
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .payment-method-card input[type="radio"] {
            display: none;
        }

        /* Kiválasztott kártya stílusa */
        .payment-method-card input[type="radio"]:checked ~ label {
            color: #3498db;
        }

        .payment-method-card input[type="radio"]:checked ~ label i {
            color: #3498db;
        }

        .payment-method-card input[type="radio"]:checked ~ .check-icon {
            opacity: 1;
        }

        /* Kiválasztott kártya kerete és háttere */
        .payment-method-card input[type="radio"]:checked ~ * {
            border-color: #3498db;
        }

        .payment-method-card input[type="radio"]:checked + label {
            color: #3498db;
        }

        /* A kiválasztott kártya teljes stílusa */
        .payment-method-card input[type="radio"]:checked {
            ~ .check-icon {
                opacity: 1;
            }
            & + label {
                color: #3498db;
            }
            & ~ .payment-method-card {
                border-color: #3498db;
                background-color: #f0f7ff;
                box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
            }
        }

        .payment-method-card label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            color: #666;
        }

        .payment-method-card img {
            width: 32px;
            height: 32px;
            transition: transform 0.3s ease;
        }

        .payment-method-card:hover img {
            transform: scale(1.1);
        }

        /* Kiválasztott állapot */
        .payment-method-card input[type="radio"]:checked + label img {
            transform: scale(1.1);
        }

        .check-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #3498db;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        /* JavaScript-el hozzáadható selected osztály */
        .payment-method-card.selected {
            border-color: #3498db;
            background-color: #f0f7ff;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
        }

        /* Csomag testreszabás stílusok - új, professzionálisabb megjelenés */
        .package-customization {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            display: none; /* Kezdetben elrejtve */
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1010;
            width: 450px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes modalFadeIn {
            from {opacity: 0; transform: translate(-50%, -50%);}
            to {opacity: 1; transform: translate(-50%, -50%);}
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1005;
            display: none;
        }

        .modal-overlay.active {
            display: block;
        }

        .customization-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #f5f7fa;
            padding-bottom: 15px;
        }

        .customization-title-text {
            display: flex;
            align-items: center;
        }

        .customization-title i {
            margin-right: 10px;
            color: #3498db;
            font-size: 20px;
        }

        .close-modal {
            background: none;
            border: none;
            color: #64748b;
            font-size: 20px;
            cursor: pointer;
            transition: color 0.2s;
            padding: 5px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
        }

        .close-modal:hover {
            background: #f1f5f9;
            color: #e74c3c;
        }

        .slider-container {
            margin-bottom: 25px;
            background: #f8f9fa;
            padding: 18px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .slider-container:hover {
            background: #f0f7ff;
            box-shadow: 0 3px 10px rgba(52, 152, 219, 0.1);
        }

        .slider-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .slider-label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 15px;
            display: flex;
            align-items: center;
        }

        .slider-label i {
            margin-right: 8px;
            color: #3498db;
        }

        .slider-value {
            font-weight: 700;
            color: #ffffff;
            background: linear-gradient(135deg, #3498db, #2980b9);
            padding: 5px 12px;
            border-radius: 20px;
            min-width: 60px;
            text-align: center;
            box-shadow: 0 3px 6px rgba(52, 152, 219, 0.2);
            transition: all 0.3s ease;
        }

        .range-slider {
            -webkit-appearance: none;
            width: 100%;
            height: 8px;
            border-radius: 5px;
            background: #dfe6e9;
            outline: none;
            position: relative;
        }

        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db, #2980b9);
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
            border: 2px solid #fff;
            margin-top: -8px; /* Center the thumb on the track */
        }

        .range-slider::-webkit-slider-thumb:hover {
            transform: scale(1.15);
            box-shadow: 0 2px 12px rgba(52, 152, 219, 0.4);
        }

        .range-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db, #2980b9);
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
            border: 2px solid #fff;
        }

        .range-slider::-moz-range-thumb:hover {
            transform: scale(1.15);
            box-shadow: 0 2px 12px rgba(52, 152, 219, 0.4);
        }

        .range-slider::-webkit-slider-runnable-track {
            height: 8px;
            border-radius: 5px;
        }

        .range-slider::-moz-range-track {
            height: 8px;
            border-radius: 5px;
        }

        .price-calculation {
            background: #f8f9fa;
            border: none;
            border-radius: 12px;
            padding: 20px;
            margin-top: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #edf2f7;
        }

        .price-row:last-child {
            border-bottom: none;
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.1em;
            padding-top: 15px;
            margin-top: 5px;
            border-top: 2px solid #edf2f7;
        }

        .price-label {
            color: #64748b;
            font-weight: 500;
        }

        .price-value {
            font-weight: 600;
            color: #2c3e50;
        }

        .price-row:last-child .price-value {
            color: #3498db;
            font-size: 1.1em;
        }

        .price-update-message {
            color: #27ae60;
            font-size: 14px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0fff4;
            padding: 10px;
            border-radius: 8px;
            border-left: 3px solid #27ae60;
        }

        .price-update-message i {
            margin-right: 8px;
            animation: spin 4s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-reset {
            background: #f1f5f9;
            color: #475569;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-reset i {
            margin-right: 8px;
        }

        .btn-reset:hover {
            background: #e2e8f0;
            color: #334155;
        }

        .customization-actions {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        /* Testreszabás gomb */
        .customize-button {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
            font-weight: 600;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.2);
        }

        .customize-button i {
            margin-right: 10px;
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .customize-button:hover {
            background: linear-gradient(135deg, #2980b9, #1c6ea4);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(52, 152, 219, 0.3);
        }

        .customize-button:hover i {
            transform: rotate(90deg);
        }

        .customize-button.active {
            background: #e74c3c;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.2);
        }

        .customize-button.active:hover {
            background: #c0392b;
            box-shadow: 0 6px 18px rgba(231, 76, 60, 0.3);
        }

        .customize-button.active i {
            transform: rotate(0deg);
        }

        .customize-button.active:hover i {
            transform: rotate(90deg);
        }

        /* Login and Registration Styles */
        .login-register-container {
            text-align: center;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .login-info-text {
            color: #4a5568;
            margin-bottom: 25px;
            font-size: 16px;
            line-height: 1.6;
        }

        .login-register-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-bottom: 20px;
        }

        .login-btn, .register-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 150px;
            text-decoration: none;
        }

        .register-btn {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }

        .login-btn i, .register-btn i {
            margin-right: 8px;
            font-size: 16px;
        }

        .login-btn:hover, .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }

        .login-btn:hover {
            background: linear-gradient(135deg, #2980b9, #1c6ea4);
        }

        .register-btn:hover {
            background: linear-gradient(135deg, #27ae60, #219655);
        }

        /* Login Modal módosítása - középre helyezés animáció nélkül */
        .login-modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
        }

        .login-modal-content {
            background-color: #fff;
            margin: 0;
            padding: 30px;
            width: 400px;
            max-width: 90%;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            /* Az animáció eltávolítva */
        }

        .close-login {
            position: absolute;
            right: 20px;
            top: 15px;
            color: #718096;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }

        .close-login:hover {
            color: #e53e3e;
        }

        #login-form .form-group {
            margin-bottom: 20px;
        }

        #login-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }

        #login-form input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.3s;
        }

        #login-form input:focus {
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
            outline: none;
        }

        .login-submit-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 12px 20px;
            width: 100%;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .login-submit-btn:hover {
            background: linear-gradient(135deg, #2980b9, #1c6ea4);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(41, 128, 185, 0.2);
        }

        .login-footer {
            margin-top: 20px;
            text-align: center;
            color: #718096;
            font-size: 14px;
        }

        .login-footer a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .error-message {
            background-color: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #e53e3e;
        }

        @keyframes modalFadeIn {
            from {opacity: 0; transform: translate(-50%, -50%);}
            to {opacity: 1; transform: translate(-50%, -50%);}
        }

        /* Megrendelési oldal stílusai */
        .order-container {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .package-details, .order-form {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .package-details {
            flex: 1;
            min-width: 300px;
        }

        .order-form {
            flex: 2;
            min-width: 500px;
        }

        h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 24px;
            font-weight: 600;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 15px;
        }

        .login-register-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 5px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #f1f1f1;
            color: #333;
        }

        .btn-secondary:hover {
            background: #ddd;
        }

        .user-company-data {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 30px;
        }

        .data-section {
            flex: 1;
            min-width: 250px;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
        }

        .data-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: 600;
        }

        .data-row {
            display: flex;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .data-row:last-child {
            border-bottom: none;
        }

        .data-label {
            font-weight: 600;
            color: #666;
            width: 100px;
        }

        .data-value {
            color: #333;
            flex: 1;
        }

        .payment-method-selector {
            margin: 30px 0;
        }

        .payment-methods {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }

        .payment-method {
            flex: 1;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .payment-method:hover {
            background: #f1f1f1;
        }

        .payment-method input[type="radio"] {
            margin-right: 10px;
        }

        .terms-conditions {
            margin: 30px 0;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-container a {
            color: #3498db;
            text-decoration: none;
        }

        .checkbox-container a:hover {
            text-decoration: underline;
        }

        .continue-button {
            margin-top: 30px;
        }

        .continue-button button {
            background: #3498db;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .continue-button button:hover {
            background: #2980b9;
        }

        .continue-button button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .alert {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }

        /* Jelszó mező stílusa - javított pozicionálás */
        .form-group {
            position: relative;
        }

        #login-form input[type="password"],
        #login-form input[type="text"] {
            padding-right: 40px; /* Hely a jelszó ikonnak */
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            pointer-events: auto;
        }

        .password-toggle img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* Eltávolítottuk a .save-button és .save-confirmation stílusokat */

        .price-update-message {
            color: #27ae60;
            font-size: 14px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0fff4;
            padding: 10px;
            border-radius: 8px;
            border-left: 3px solid #27ae60;
        }
    </style>
</head>
<body>
    <?php include 'includes/header2.php'; ?>

    <main class="order-section">
        <div class="order-intro">
            <h1>Megrendelés</h1>
            <p>Köszönjük, hogy szolgáltatásunkat választotta!</p>
        </div>

        <div class="container">
            <?php if ($valid_package): ?>
                <!-- Bal oldali panel a választott csomaggal -->
                <div class="order-summary">
                    <h2>Választott csomag</h2>
                    <div class="package-details">
                        <h3><?php echo htmlspecialchars($csomagok[$csomag]['nev']); ?></h3>
                        <p class="price" id="display-price">
                            <?php echo $ar == 'egyedi' ? 'Egyedi ár' : number_format($ar, 0, ',', ' ') . ' Ft/' . ($period == 'ev' ? 'év' : 'hó'); ?>
                        </p>
                        <ul>
                            <li id="felhasznalok-li"><i class="fas fa-check"></i> <span id="felhasznalok-szam"><?php echo $kezdeti_felhasznalok; ?></span>&nbsp;felhasználó</li>
                            <li id="eszkozok-li"><i class="fas fa-check"></i> <span id="eszkozok-szam"><?php echo $kezdeti_eszkozok; ?></span>&nbsp;eszköz kezelése</li>
                            <?php foreach ($csomagok[$csomag]['jellemzok'] as $jellemzo): ?>
                                <li><i class="fas fa-check"></i> <?php echo $jellemzo; ?></li>
                            <?php endforeach; ?>
                        </ul>

                        <!-- Testreszabás gomb -->
                        <button class="customize-button" id="customize-toggle">
                            <i class="fas fa-sliders-h"></i> Csomag testreszabása
                        </button>
                    </div>
                </div>

                <!-- Jobb oldali panel -->
                <div class="order-form">
                    <h2>Megrendelő adatai</h2>
                    
                    <?php if (!$logged_in): ?>
                        <p>A megrendeléshez kérjük jelentkezzen be cégfiókjával, vagy regisztráljon, ha még nem rendelkezik fiókkal.</p>
                        
                        <div class="login-register-buttons">
                            <a href="#" class="btn btn-primary" id="login-btn">Bejelentkezés</a>
                            <a href="auth/register.php" class="btn btn-secondary" id="register-btn">Regisztráció</a>
                        </div>
                    <?php else: ?>
                        <?php if ($is_company_owner && $company_data): ?>
                            <!-- Cégtulajdonos adatai -->
                            <div class="user-company-data">
                                <div class="data-section">
                                    <h3>Személyes adatok</h3>
                                    <div class="data-row">
                                        <span class="data-label">Név:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($user_data['lastname'] . ' ' . $user_data['firstname']); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Email:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($user_data['email']); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Telefon:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($user_data['telephone']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="data-section">
                                    <h3>Cég adatok</h3>
                                    <div class="data-row">
                                        <span class="data-label">Cégnév:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($company_data['company_name']); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Cím:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($company_data['company_address']); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Email:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($company_data['company_email']); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Telefon:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($company_data['company_telephone']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Fizetési módszer választó -->
                            <div class="payment-method-selector">
                                <h3>Fizetési módszer</h3>
                                <div class="payment-methods">
                                <div class="payment-method">
                                        <input type="radio" id="credit-card" name="payment_method" value="credit_card" checked>
                                        <img src="assets/img/credit-card.png" alt="Bankkártya" style="width: 32px; height: 32px;">
                                        <label for="credit-card">Bankkártyás fizetés</label>
                                    </div>

                                    <div class="payment-method">
                                        <input type="radio" id="bank-transfer" name="payment_method" value="bank_transfer">
                                        <img src="assets/img/bank.png" alt="Bank" style="width: 32px; height: 32px;">
                                        <label for="bank-transfer">Banki átutalás</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Általános szerződési feltételek -->
                            <div class="terms-conditions">
                                <div class="checkbox-container">
                                    <input type="checkbox" id="accept-terms" name="accept_terms" required>
                                    <label for="accept-terms">Elfogadom az <a href="hasznos-linkek/aszf.php" target="_blank">Általános Szerződési Feltételeket</a></label>
                                </div>
                            </div>
                            
                            <!-- Tovább gomb -->
                            <div class="continue-button">
                                <button type="button" id="continue-btn" disabled>Tovább a fizetéshez</button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <p>Az Ön fiókja nem rendelkezik cégtulajdonosi jogosultsággal. Kérjük, jelentkezzen be egy cégtulajdonosi fiókkal, vagy regisztráljon egy új céget.</p>
                                <div class="login-register-buttons">
                                    <a href="logout.php" class="btn btn-secondary">Kijelentkezés</a>
                                    <a href="register.php" class="btn btn-primary">Új cég regisztrálása</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="error-message">
                    <p>Érvénytelen csomag választás. Kérjük, válasszon a <a href="arak.php">csomagjaink</a> közül.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Csomag testreszabás modal (moved outside the container) -->
        <div class="modal-overlay" id="modal-overlay"></div>
        <div class="package-customization" id="customization-panel">
            <div class="customization-title">
                <div class="customization-title-text">
                    <i class="fas fa-cog"></i> Csomag testreszabása
                </div>
                <button class="close-modal" id="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="slider-container">
                <div class="slider-header">
                    <span class="slider-label"><i class="fas fa-users"></i> Felhasználók száma</span>
                    <span class="slider-value" id="felhasznalok-ertek"><?php echo $kezdeti_felhasznalok; ?></span>
                </div>
                <input type="range" min="<?php echo $kezdeti_felhasznalok; ?>" max="<?php echo $kezdeti_felhasznalok * 5; ?>" value="<?php echo $kezdeti_felhasznalok; ?>" class="range-slider" id="felhasznalok-slider">
            </div>
            
            <div class="slider-container">
                <div class="slider-header">
                    <span class="slider-label"><i class="fas fa-laptop"></i> Eszközök száma</span>
                    <span class="slider-value" id="eszkozok-ertek"><?php echo $kezdeti_eszkozok; ?></span>
                </div>
                <input type="range" min="<?php echo $kezdeti_eszkozok; ?>" max="<?php echo $kezdeti_eszkozok * 5; ?>" value="<?php echo $kezdeti_eszkozok; ?>" class="range-slider" id="eszkozok-slider">
            </div>

            <div class="price-calculation">
                <div class="price-row">
                    <span class="price-label">Alapcsomag ára</span>
                    <span class="price-value" id="alapcsomag-ar">
                        <?php echo $ar == 'egyedi' ? 'Egyedi' : number_format($ar, 0, ',', ' ') . ' Ft'; ?>
                    </span>
                </div>
                <div class="price-row" id="felhasznalo-ar-row" style="display: none;">
                    <span class="price-label">Extra felhasználók</span>
                    <span class="price-value" id="felhasznalo-ar">0 Ft</span>
                </div>
                <div class="price-row" id="eszkoz-ar-row" style="display: none;">
                    <span class="price-label">Extra eszközök</span>
                    <span class="price-value" id="eszkoz-ar">0 Ft</span>
                </div>
                <div class="price-row">
                    <span class="price-label">Végösszeg</span>
                    <span class="price-value" id="vegosszeg">
                        <?php echo $ar == 'egyedi' ? 'Egyedi' : number_format($ar, 0, ',', ' ') . ' Ft'; ?>
                    </span>
                </div>
            </div>

            <div class="price-update-message">
                <i class="fas fa-sync-alt"></i> Az ár automatikusan frissül a csúszkák mozgatásával
            </div>

            <div class="customization-actions">
                <button class="btn-reset" id="reset-btn"><i class="fas fa-undo"></i> Alapértelmezett</button>
            </div>
        </div>
    </main>

    <!-- Login Modal -->
    <div class="login-modal" id="login-modal">
        <div class="login-modal-content">
            <span class="close-login">&times;</span>
            <h2>Bejelentkezés</h2>
            <div class="error-message" id="login-error" style="display: none;"></div>
            <form id="login-form">
                <div class="form-group">
                    <label for="login-email">Email cím</label>
                    <input type="email" id="login-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Jelszó</label>
                    <input type="password" id="login-password" name="password" required>
                </div>
                <button type="submit" class="login-submit-btn">Bejelentkezés</button>
            </form>
            <div class="login-footer">
                <p>Nincs még fiókja? <a href="auth/register.php">Regisztráljon itt</a></p>
                <p><a href="auth/forgot_password.php">Elfelejtett jelszó</a></p>
            </div>
        </div>
    </div>

    <?php include 'includes/footer2.php'; ?>         

    <script>
        // Fizetési mód kiválasztásának kezelése
        document.querySelectorAll('.payment-method-card input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Minden kártyáról eltávolítjuk a selected osztályt
                document.querySelectorAll('.payment-method-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                // A kiválasztott kártya szülőeleméhez hozzáadjuk a selected osztályt
                this.closest('.payment-method-card').classList.add('selected');
            });
        });

        // Kezdeti állapot beállítása
        document.querySelector('.payment-method-card input[type="radio"]:checked')
            ?.closest('.payment-method-card')
            .classList.add('selected');

        // Testreszabás panel megjelenítése/elrejtése
        document.addEventListener('DOMContentLoaded', function() {
            const customizeToggle = document.getElementById('customize-toggle');
            const customizationPanel = document.getElementById('customization-panel');
            const modalOverlay = document.getElementById('modal-overlay');
            const closeModal = document.getElementById('close-modal');
            
            function openModal() {
                if (customizationPanel) {
                    customizationPanel.style.display = 'block';
                    modalOverlay.classList.add('active');
                    document.body.style.overflow = 'hidden'; // Prevent scrolling
                    customizeToggle.classList.add('active');
                }
            }
            
            function closeModalFunc() {
                if (customizationPanel) {
                    customizationPanel.style.display = 'none';
                    modalOverlay.classList.remove('active');
                    document.body.style.overflow = ''; // Re-enable scrolling
                    customizeToggle.classList.remove('active');
                }
            }
            
            if (customizeToggle) {
                customizeToggle.addEventListener('click', function(e) {
                    e.preventDefault(); // Megakadályozza az alapértelmezett működést
                    openModal();
                });
            }
            
            if (closeModal) {
                closeModal.addEventListener('click', function(e) {
                    // Megakadályozzuk az alapértelmezett működést és a buborékolást
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Bezárjuk a modalt animáció nélkül
                    closeModalFunc();
                });
            }
            
            if (modalOverlay) {
                modalOverlay.addEventListener('click', function() {
                    closeModalFunc();
                });
            }
            
            // Escape key to close modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modalOverlay && modalOverlay.classList.contains('active')) {
                    closeModalFunc();
                }
            });

            // Csomag testreszabás kezelése
            const felhasznalokSlider = document.getElementById('felhasznalok-slider');
            const eszkozokSlider = document.getElementById('eszkozok-slider');
            const felhasznalokErtek = document.getElementById('felhasznalok-ertek');
            const eszkozokErtek = document.getElementById('eszkozok-ertek');
            const felhasznalokSzam = document.getElementById('felhasznalok-szam');
            const eszkozokSzam = document.getElementById('eszkozok-szam');
            const alapcsomagAr = document.getElementById('alapcsomag-ar');
            const felhasznaloAr = document.getElementById('felhasznalo-ar');
            const eszkozAr = document.getElementById('eszkoz-ar');
            const vegosszeg = document.getElementById('vegosszeg');
            const displayPrice = document.getElementById('display-price');
            const resetBtn = document.getElementById('reset-btn');
            const felhasznaloArRow = document.getElementById('felhasznalo-ar-row');
            const eszkozArRow = document.getElementById('eszkoz-ar-row');
            const arHidden = document.getElementById('ar-hidden');
            const felhasznalokHidden = document.getElementById('felhasznalok-hidden');
            const eszkozokHidden = document.getElementById('eszkozok-hidden');

            // Árazási paraméterek
            const felhasznaloArHavi = <?php echo $felhasznalo_ar_havi; ?>;
            const eszkozArHavi = <?php echo $eszkoz_ar_havi; ?>;
            const idoszak = '<?php echo $period; ?>';
            const idoszakSzorzo = idoszak === 'ev' ? 12 * 0.85 : 1; // Éves előfizetésnél 15% kedvezmény
            
            // Kezdeti értékek
            const kezdetiFelhasznalok = <?php echo $kezdeti_felhasznalok; ?>;
            const kezdetiEszkozok = <?php echo $kezdeti_eszkozok; ?>;
            const csomag = '<?php echo $csomag; ?>';

            // Csomag alapárak
            const csomagArak = {
                'alap': {
                    'ho': 29990,
                    'ev': 305990
                },
                'kozepes': {
                    'ho': 55990,
                    'ev': 571098
                },
                'uzleti': {
                    'ho': 80990,
                    'ev': 826098
                }
            };

            // Alapár beállítása a csomag alapján
            let alapar = csomagArak[csomag] ? csomagArak[csomag][idoszak] : 'egyedi';

            // Kezdeti ár frissítése
            updatePrice();

            // Eseménykezelők hozzáadása a csúszkákhoz
            if (felhasznalokSlider) {
                felhasznalokSlider.addEventListener('input', function() {
                    const ertek = this.value;
                    felhasznalokErtek.textContent = ertek;
                    document.getElementById('felhasznalok-szam').textContent = ertek + ' ';
                    updatePrice();
                });
            }

            if (eszkozokSlider) {
                eszkozokSlider.addEventListener('input', function() {
                    const ertek = this.value;
                    eszkozokErtek.textContent = ertek;
                    document.getElementById('eszkozok-szam').textContent = ertek + ' ';
                    updatePrice();
                });
            }

            // Reset gomb kezelése
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    // Csúszkák visszaállítása
                    felhasznalokSlider.value = kezdetiFelhasznalok;
                    eszkozokSlider.value = kezdetiEszkozok;
                    
                    // Értékek megjelenítésének frissítése
                    felhasznalokErtek.textContent = kezdetiFelhasznalok;
                    eszkozokErtek.textContent = kezdetiEszkozok;
                    document.getElementById('felhasznalok-szam').textContent = kezdetiFelhasznalok + ' ';
                    document.getElementById('eszkozok-szam').textContent = kezdetiEszkozok + ' ';
                    
                    // Árak frissítése
                    updatePrice();
                });
            }

            // Ár frissítése
            function updatePrice() {
                // Ellenőrizzük, hogy az alapár érvényes szám-e
                if (alapar === 'egyedi' || isNaN(parseInt(alapar))) {
                    alapcsomagAr.textContent = 'Egyedi ár';
                    vegosszeg.textContent = 'Egyedi ár';
                    displayPrice.textContent = 'Egyedi ár';
                    return;
                }

                const felhasznalokSzama = parseInt(felhasznalokSlider.value) || kezdetiFelhasznalok;
                const eszkozokSzama = parseInt(eszkozokSlider.value) || kezdetiEszkozok;
                
                // Extra felhasználók és eszközök számítása
                const extraFelhasznalok = Math.max(0, felhasznalokSzama - kezdetiFelhasznalok);
                const extraEszkozok = Math.max(0, eszkozokSzama - kezdetiEszkozok);
                
                // Extra költségek számítása
                const extraFelhasznaloKoltseg = Math.round(extraFelhasznalok * felhasznaloArHavi * idoszakSzorzo);
                const extraEszkozKoltseg = Math.round(extraEszkozok * eszkozArHavi * idoszakSzorzo);
                
                // Sorok megjelenítése/elrejtése
                felhasznaloArRow.style.display = extraFelhasznalok > 0 ? 'flex' : 'none';
                eszkozArRow.style.display = extraEszkozok > 0 ? 'flex' : 'none';
                
                // Értékek frissítése
                const alaparInt = parseInt(alapar);
                alapcsomagAr.textContent = `${numberWithSpaces(alaparInt)} Ft`;
                felhasznaloAr.textContent = `${numberWithSpaces(extraFelhasznaloKoltseg)} Ft`;
                eszkozAr.textContent = `${numberWithSpaces(extraEszkozKoltseg)} Ft`;
                
                // Végösszeg számítása
                const ujVegosszeg = alaparInt + extraFelhasznaloKoltseg + extraEszkozKoltseg;
                vegosszeg.textContent = `${numberWithSpaces(ujVegosszeg)} Ft`;
                displayPrice.textContent = `${numberWithSpaces(ujVegosszeg)} Ft/${idoszak === 'ev' ? 'év' : 'hó'}`;
                
                // Rejtett mezők frissítése
                if (arHidden) arHidden.value = ujVegosszeg;
                if (felhasznalokHidden) felhasznalokHidden.value = felhasznalokSzama;
                if (eszkozokHidden) eszkozokHidden.value = eszkozokSzama;
            }

            // Számok formázása ezres elválasztóval
            function numberWithSpaces(x) {
                // Kerekítés egész számra a túl sok tizedesjegy elkerülése érdekében
                const roundedX = Math.round(x);
                return roundedX.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
            }

            // Bejelentkezési modal kezelése - pontos ID-k használatával
            const loginBtn = document.getElementById('login-btn');
            const loginModal = document.getElementById('login-modal');
            
            console.log('Login button:', loginBtn);
            console.log('Login modal:', loginModal);
            
            if (loginBtn) {
                loginBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Login button clicked');
                    if (loginModal) {
                        loginModal.style.display = 'block';
                        console.log('Modal should be visible now');
                    } else {
                        console.error('Login modal not found!');
                    }
                });
            } else {
                console.error('Login button not found!');
            }
            
            // Modal bezárása
            const closeLogin = document.querySelector('.close-login');
            if (closeLogin) {
                closeLogin.addEventListener('click', function() {
                    loginModal.style.display = 'none';
                });
            }
            
            // Kattintás a modalon kívülre bezárja azt
            window.addEventListener('click', function(event) {
                if (event.target === loginModal) {
                    loginModal.style.display = 'none';
                }
            });
            
            // Bejelentkezési form kezelése
            const loginForm = document.getElementById('login-form');
            const loginError = document.getElementById('login-error');
            
            // Jelszó megjelenítés/elrejtés funkció
            const passwordField = document.getElementById('login-password');
            if (passwordField) {
                // Jelszó láthatóság kapcsoló létrehozása
                const passwordToggle = document.createElement('span');
                passwordToggle.className = 'password-toggle';
                
                // Kezdetben a jelszó rejtve van, így a "csukott szem" ikont mutatjuk (hide.png)
                passwordToggle.innerHTML = '<img src="assets/img/hide.png" alt="Jelszó mutatása" style="width: 20px; height: 20px;">';
                
                // Jelszó mező szülőelemének relatív pozícionálása
                const passwordParent = passwordField.parentElement;
                
                // Jelszó kapcsoló hozzáadása közvetlenül a jelszó mező után
                passwordField.insertAdjacentElement('afterend', passwordToggle);
                
                passwordToggle.style.top = 'calc(50% + 16px)';
                
                // Eseménykezelő a jelszó láthatóság kapcsolásához
                passwordToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const img = this.querySelector('img');
                    if (passwordField.type === 'password') {
                        // Ha a jelszó rejtve volt és most láthatóvá tesszük
                        passwordField.type = 'text';
                        // Átváltunk a "view.png"-re (nyitott szem), mert most már látható a jelszó
                        img.src = 'assets/img/view.png';
                        img.alt = 'Jelszó elrejtése';
                    } else {
                        // Ha a jelszó látható volt és most elrejtjük
                        passwordField.type = 'password';
                        // Átváltunk a "hide.png"-re (csukott szem), mert most már rejtve van a jelszó
                        img.src = 'assets/img/hide.png';
                        img.alt = 'Jelszó mutatása';
                    }
                });
                
                // Jelszó mező fókusz eseményeinek kezelése
                passwordField.addEventListener('focus', function() {
                    passwordToggle.style.opacity = '1';
                });
                
                passwordField.addEventListener('blur', function() {
                    passwordToggle.style.opacity = '0.7';
                });
            }

            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const email = document.getElementById('login-email').value;
                    const password = document.getElementById('login-password').value;
                    const submitBtn = document.querySelector('.login-submit-btn');
                    
                    // Gomb letiltása az ajax kérés idejére
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = 'Bejelentkezés...';
                    
                    // AJAX kérés a szervernek
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'login_process.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    
                    xhr.onload = function() {
                        // Gomb visszaállítása
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Bejelentkezés';
                        
                        console.log('Server response:', xhr.responseText);
                        
                        // Megpróbáljuk JSON-ként értelmezni a választ
                        let response;
                        try {
                            response = JSON.parse(xhr.responseText);
                        } catch (e) {
                            console.error('Error parsing JSON response:', e);
                            loginError.textContent = 'Hiba történt a szerver válaszának feldolgozásakor. Kérjük, próbálja újra.';
                            loginError.style.display = 'block';
                            return;
                        }
                        
                        if (response.success) {
                            // Sikeres bejelentkezés, újratöltjük az oldalt
                            loginError.style.display = 'none';
                            window.location.reload();
                        } else {
                            // Hibaüzenet megjelenítése
                            loginError.textContent = response.message || 'Sikertelen bejelentkezés. Kérjük, ellenőrizze adatait.';
                            loginError.style.display = 'block';
                        }
                    };
                    
                    xhr.onerror = function() {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Bejelentkezés';
                        loginError.textContent = 'Hálózati hiba történt. Kérjük, próbálja újra később.';
                        loginError.style.display = 'block';
                    };
                    
                    // Adatok küldése
                    const redirect = window.location.href;
                    xhr.send('email=' + encodeURIComponent(email) + '&password=' + encodeURIComponent(password) + '&redirect=' + encodeURIComponent(redirect));
                });
            }

            // Regisztráció gomb kezelése
            const registerBtn = document.getElementById('register-btn');
            if (registerBtn) {
                registerBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Átirányítás az auth/register.php oldalra, visszatérési URL-lel
                    window.location.href = 'auth/register.php?redirect=' + encodeURIComponent(window.location.href);
                });
            }
            
            // Általános szerződési feltételek elfogadásának kezelése
            const acceptTerms = document.getElementById('accept-terms');
            const continueBtn = document.getElementById('continue-btn');
            
            if (acceptTerms && continueBtn) {
                acceptTerms.addEventListener('change', function() {
                    continueBtn.disabled = !this.checked;
                });
                
                // Tovább gomb kezelése
                continueBtn.addEventListener('click', function() {
                    const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
                    
                    // Aktuális értékek lekérése
                    const felhasznalokSzama = parseInt(felhasznalokSlider.value) || kezdetiFelhasznalok;
                    const eszkozokSzama = parseInt(eszkozokSlider.value) || kezdetiEszkozok;
                    const aktualisAr = vegosszeg.textContent.replace(/[^0-9]/g, '');
                    
                    // URL paraméterek összeállítása
                    const params = new URLSearchParams(window.location.search);
                    params.set('felhasznalok', felhasznalokSzama);
                    params.set('eszkozok', eszkozokSzama);
                    params.set('ar', aktualisAr);
                    
                    // Átirányítás a megfelelő oldalra a fizetési mód alapján
                    if (selectedPaymentMethod === 'bank_transfer') {
                        window.location.href = `bank_transfer.php?${params.toString()}`;
                    } else {
                        window.location.href = `payment.php?${params.toString()}`;
                    }
                });
            }
        });

        // Session törlése oldalváltáskor
        window.addEventListener('beforeunload', function() {
            // AJAX kérés a session törlésére
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'logout_session.php', false); // Szinkron kérés
            xhr.send();
        });

        // Bejelentkezési modal tartalmának módosítása
        document.addEventListener('DOMContentLoaded', function() {
            // Keressük meg a regisztráció linket a bejelentkezési modalban
            const registerLink = document.querySelector('.login-footer a[href="register.php"]');
            
            if (registerLink) {
                // Módosítsuk a link célját auth/register.php-re
                registerLink.href = 'auth/register.php';
                
                // Adjunk hozzá eseménykezelőt, hogy átadja a visszatérési URL-t
                registerLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = 'auth/register.php?redirect=' + encodeURIComponent(window.location.href);
                });
            }
        });

        // Csomag árak és limitek
        const csomagArak = {
            alap: {
                ho: 29990,
                ev: 299990,
                felhasznaloLimit: 5,
                eszkozLimit: 100
            },
            kozepes: {
                ho: 55990,
                ev: 559990,
                felhasznaloLimit: 10,
                eszkozLimit: 250
            },
            uzleti: {
                ho: 80990,
                ev: 826098,
                felhasznaloLimit: 20,
                eszkozLimit: 500
            }
        };

        // Elemek lekérése
        const felhasznalokSlider = document.getElementById('felhasznalok-slider');
        const eszkozokSlider = document.getElementById('eszkozok-slider');
        const felhasznalokErtek = document.getElementById('felhasznalok-ertek');
        const eszkozokErtek = document.getElementById('eszkozok-ertek');
        const felhasznalokSzam = document.getElementById('felhasznalok-szam');
        const eszkozokSzam = document.getElementById('eszkozok-szam');
        const vegosszegElem = document.getElementById('vegosszeg');
        const displayPrice = document.getElementById('display-price');

        // localStorage kezelő függvények
        function saveToLocalStorage(key, value) {
            try {
                localStorage.setItem(key, JSON.stringify(value));
                console.log('Mentve localStorage-ba:', key, value);
            } catch (e) {
                console.error('Hiba a localStorage mentés során:', e);
            }
        }

        function getFromLocalStorage(key) {
            try {
                const value = localStorage.getItem(key);
                const parsed = value ? JSON.parse(value) : null;
                console.log('Betöltve localStorage-ból:', key, parsed);
                return parsed;
            } catch (e) {
                console.error('Hiba a localStorage betöltés során:', e);
                return null;
            }
        }

        function removeFromLocalStorage(key) {
            try {
                localStorage.removeItem(key);
                console.log('Törölve localStorage-ból:', key);
            } catch (e) {
                console.error('Hiba a localStorage törlés során:', e);
            }
        }

        // Módosítások mentése
        function savePackageModifications() {
            if (!felhasznalokSlider || !eszkozokSlider || !vegosszegElem) {
                console.error('Hiányzó elemek a mentéshez');
                return;
            }

            const modifications = {
                felhasznalok: parseInt(felhasznalokSlider.value),
                eszkozok: parseInt(eszkozokSlider.value),
                csomag: '<?php echo $csomag; ?>',
                fizetesiIdo: '<?php echo $period; ?>',
                ar: parseInt(vegosszegElem.textContent.replace(/[^0-9]/g, '')),
                lastUpdated: new Date().toISOString(),
                isLoggedIn: <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>
            };

            saveToLocalStorage('packageModifications', modifications);
        }

        // Módosítások betöltése
        function loadPackageModifications() {
            console.log('Módosítások betöltése kezdődik...');
            
            const savedModifications = getFromLocalStorage('packageModifications');
            if (!savedModifications) {
                console.log('Nincsenek mentett módosítások');
                return;
            }

            console.log('Mentett módosítások:', savedModifications);
            
            // Ellenőrizzük, hogy ugyanaz a csomag van-e kiválasztva
            if (savedModifications.csomag !== '<?php echo $csomag; ?>') {
                console.log('Különböző csomag, módosítások törlése');
                removeFromLocalStorage('packageModifications');
                return;
            }

            // Értékek beállítása
            if (felhasznalokSlider && felhasznalokErtek && felhasznalokSzam) {
                felhasznalokSlider.value = savedModifications.felhasznalok;
                felhasznalokErtek.textContent = savedModifications.felhasznalok;
                felhasznalokSzam.textContent = savedModifications.felhasznalok + ' ';
            }

            if (eszkozokSlider && eszkozokErtek && eszkozokSzam) {
                eszkozokSlider.value = savedModifications.eszkozok;
                eszkozokErtek.textContent = savedModifications.eszkozok;
                eszkozokSzam.textContent = savedModifications.eszkozok + ' ';
            }

            // Ár frissítése
            updatePrice();
            console.log('Módosítások betöltése befejezve');
        }

        // Ár frissítése
        function updatePrice() {
            if (!felhasznalokSlider || !eszkozokSlider || !vegosszegElem) {
                console.error('Hiányzó elemek az ár frissítéséhez');
                return;
            }

            const csomag = '<?php echo $csomag; ?>';
            const period = '<?php echo $period; ?>';
            const alapar = csomagArak[csomag][period === 'ev' ? 'ev' : 'ho'];
            
            const felhasznalokSzama = parseInt(felhasznalokSlider.value);
            const eszkozokSzama = parseInt(eszkozokSlider.value);
            
            const felhasznaloLimit = csomagArak[csomag].felhasznaloLimit;
            const eszkozLimit = csomagArak[csomag].eszkozLimit;
            
            // Extra költségek számítása
            const extraFelhasznalok = Math.max(0, felhasznalokSzama - felhasznaloLimit);
            const extraEszkozok = Math.max(0, eszkozokSzama - eszkozLimit);
            
            const felhasznaloArHavi = <?php echo $felhasznalo_ar_havi; ?>;
            const eszkozArHavi = <?php echo $eszkoz_ar_havi; ?>;
            const idoszakSzorzo = period === 'ev' ? 12 * 0.85 : 1;
            
            const extraFelhasznaloKoltseg = Math.round(extraFelhasznalok * felhasznaloArHavi * idoszakSzorzo);
            const extraEszkozKoltseg = Math.round(extraEszkozok * eszkozArHavi * idoszakSzorzo);
            
            // Végösszeg számítása
            const ujVegosszeg = alapar + extraFelhasznaloKoltseg + extraEszkozKoltseg;
            
            // Értékek megjelenítése
            vegosszegElem.textContent = numberWithSpaces(ujVegosszeg) + ' Ft';
            if (displayPrice) {
                displayPrice.textContent = numberWithSpaces(ujVegosszeg) + ' Ft/' + (period === 'ev' ? 'év' : 'hó');
            }

            // Automatikus mentés az ár frissítésekor
            savePackageModifications();
        }

        // Számok formázása
        function numberWithSpaces(x) {
            return Math.round(x).toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
        }

        // Eseménykezelők beállítása
        if (felhasznalokSlider) {
            felhasznalokSlider.addEventListener('input', function() {
                const ertek = this.value;
                felhasznalokErtek.textContent = ertek;
                document.getElementById('felhasznalok-szam').textContent = ertek + ' ';
                updatePrice();
            });
        }

        if (eszkozokSlider) {
            eszkozokSlider.addEventListener('input', function() {
                const ertek = this.value;
                eszkozokErtek.textContent = ertek;
                document.getElementById('eszkozok-szam').textContent = ertek + ' ';
                updatePrice();
            });
        }

        // Módosítások betöltése az oldal betöltésekor
        loadPackageModifications();
    </script>
</body>
</html> 