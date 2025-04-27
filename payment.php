<?php 
require_once 'includes/config.php';
require_once 'includes/db.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Minden kimenet pufferelése
ob_start();

// Ellenőrizzük, hogy be van-e jelentkezve a felhasználó
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Adatbázis kapcsolat létrehozása
$db = Database::getInstance();
$conn = $db->getConnection();

// Ellenőrizzük, hogy cég tulajdonos-e
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT u.*, c.id as company_id, r.role_name 
                       FROM user u 
                       JOIN company c ON u.company_id = c.id 
                       JOIN user_to_roles utr ON u.id = utr.user_id 
                       JOIN roles r ON utr.role_id = r.id 
                       WHERE u.id = ? AND r.role_name = 'Cég tulajdonos'");
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    header('Location: arak.php');
    exit;
}

$user_data = $result;
$company_id = $user_data['company_id'];

// Fizetési adatok feldolgozása
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Töröljük a kimeneti puffert és állítsuk be a JSON fejlécet
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        // Debug információ
        error_log("POST adatok: " . print_r($_POST, true));
        error_log("GET adatok: " . print_r($_GET, true));
        
        // Ellenőrizzük, hogy van-e már kimenő tartalom
        if (headers_sent($filename, $linenum)) {
            error_log("Headers already sent in $filename on line $linenum");
        }
        
        // Tranzakció kezdése
        $conn->beginTransaction();

        // Kártya adatok ellenőrzése
        if (!isset($_POST['card_number']) || !isset($_POST['card_holder']) || 
            !isset($_POST['expiry']) || !isset($_POST['cvv'])) {
            throw new Exception("Hiányzó kártya adatok");
        }

        // Kártya adatok validálása
        $card_number = preg_replace('/\s+/', '', $_POST['card_number']); // Szóközök eltávolítása
        $card_holder = $_POST['card_holder'];
        $expiry = $_POST['expiry'];
        $cvv = $_POST['cvv'];

        // Kártyaszám Luhn algoritmus ellenőrzése
        function validateCreditCard($number) {
            $number = preg_replace('/\D/', '', $number);
            $length = strlen($number);
            $parity = $length % 2;
            $sum = 0;
            
            for($i = $length-1; $i >= 0; $i--) {
                $digit = intval($number[$i]);
                if ($i % 2 == $parity) {
                    $digit *= 2;
                    if ($digit > 9) {
                        $digit -= 9;
                    }
                }
                $sum += $digit;
            }
            
            return ($sum % 10) == 0;
        }

        // Kártyaszám validálása
        if (!validateCreditCard($card_number)) {
            throw new Exception("Érvénytelen kártyaszám");
        }

        // Kártya típus meghatározása
        $card_type = '';
        if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $card_number)) {
            $card_type = 'Visa';
        } elseif (preg_match('/^5[1-5][0-9]{14}$/', $card_number)) {
            $card_type = 'Mastercard';
        } elseif (preg_match('/^3[47][0-9]{13}$/', $card_number)) {
            $card_type = 'American Express';
        } else {
            throw new Exception("Nem támogatott kártyatípus");
        }
        
        // Lejárati dátum validálása
        $expiry_parts = explode('/', $expiry);
        if (count($expiry_parts) !== 2) {
            throw new Exception("Érvénytelen lejárati dátum formátum");
        }
        
        $expiry_month = intval($expiry_parts[0]);
        $expiry_year = intval('20' . $expiry_parts[1]);
        $current_year = intval(date('Y'));
        $current_month = intval(date('m'));
        
        if ($expiry_month < 1 || $expiry_month > 12) {
            throw new Exception("Érvénytelen lejárati hónap");
        }
        
        if ($expiry_year < $current_year || 
            ($expiry_year == $current_year && $expiry_month < $current_month)) {
            throw new Exception("A kártya lejárt");
        }

        // CVV validálása
        if (!preg_match('/^[0-9]{3,4}$/', $cvv)) {
            throw new Exception("Érvénytelen CVV kód");
        }
        
        // Titkosítási kulcs generálása
        $encryption_key = openssl_random_pseudo_bytes(32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        
        // Adatok titkosítása
        $encrypted_card = openssl_encrypt($card_number, 'aes-256-cbc', $encryption_key, 0, $iv);
        $encrypted_cvv = openssl_encrypt($cvv, 'aes-256-cbc', $encryption_key, 0, $iv);
        
        // Utolsó 4 számjegy
        $last_four = substr($card_number, -4);
        
        // Kártya adatok mentése
        $stmt = $conn->prepare("INSERT INTO payment_methods (user_id, card_holder_name, CVC, card_expiry_month, card_expiry_year, card_type, last_four_digits, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $expiry_parts = explode('/', $expiry);
        if (count($expiry_parts) !== 2) throw new Exception("Érvénytelen lejárati dátum formátum");
        
        $stmt->execute([$user_id, $card_holder, $encrypted_cvv, $expiry_parts[0], $expiry_parts[1], $card_type, $last_four]);
        $payment_method_id = $conn->lastInsertId();
        
        // Előfizetés létrehozása
        $csomag = isset($_POST['csomag']) ? $_POST['csomag'] : $_GET['csomag'];
        $ar = isset($_POST['ar']) ? $_POST['ar'] : $_GET['ar'];
        $period = isset($_POST['period']) ? $_POST['period'] : $_GET['period'];
        $felhasznalok = isset($_POST['felhasznalok']) ? $_POST['felhasznalok'] : $_GET['felhasznalok'];
        $eszkozok = isset($_POST['eszkozok']) ? $_POST['eszkozok'] : $_GET['eszkozok'];
        
        if (!$csomag || !$ar || !$period) {
            throw new Exception("Hiányzó előfizetési adatok");
        }
        
        // Debug információ
        error_log("Csomag: $csomag, Ár: $ar, Periódus: $period, Felhasználók: $felhasznalok, Eszközök: $eszkozok");
        
        // Csomag név módosítása a periódus alapján
        $csomag_nev = $csomag;
        if ($period === 'ev') {
            $csomag_nev .= '_eves';
        }
        
        // Csomag ID és eredeti ár lekérése
        $stmt = $conn->prepare("SELECT id, price FROM subscription_plans WHERE name = ?");
        $stmt->execute([$csomag_nev]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            throw new Exception("A kiválasztott előfizetési csomag nem található ($csomag_nev)");
        }
        
        $plan = $result;
        $subscription_plan_id = $plan['id'];
        $original_price = $plan['price'];
        
        // Előfizetés létrehozása
        $start_date = date('Y-m-d H:i:s');
        $end_date = $period === 'ev' ? date('Y-m-d H:i:s', strtotime('+1 year')) : date('Y-m-d H:i:s', strtotime('+1 month'));
        $next_billing_date = $end_date;
        
        $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, company_id, subscription_plan_id, payment_method_id, subscription_status_id, start_date, end_date, next_billing_date) VALUES (?, ?, ?, ?, 1, ?, ?, ?)");
        $stmt->execute([$user_id, $company_id, $subscription_plan_id, $payment_method_id, $start_date, $end_date, $next_billing_date]);
        $subscription_id = $conn->lastInsertId();
        
        // Ha az ár vagy a paraméterek módosultak, mentsük el a módosításokat
        if (($felhasznalok != null && $felhasznalok != '') || ($eszkozok != null && $eszkozok != '')) {
            $stmt = $conn->prepare("INSERT INTO subscription_modifications (subscription_id, original_plan_id, modified_plan_id, modification_date, modified_by_user_id, price_difference, modification_reason) VALUES (?, ?, ?, NOW(), ?, ?, ?)");
            $price_difference = $ar - $original_price;
            $modification_reason = "Csomag testreszabása: " . $felhasznalok . " felhasználó, " . $eszkozok . " eszköz";
            $stmt->execute([$subscription_id, $subscription_plan_id, $subscription_plan_id, $user_id, $price_difference, $modification_reason]);
        }
        
        // Fizetés rögzítése a módosított árral
        $stmt = $conn->prepare("INSERT INTO payment_history (subscription_id, payment_method_id, amount, payment_status_id, payment_date, payment_method_type, transaction_status) VALUES (?, ?, ?, 1, NOW(), 'credit_card', 'completed')");
        $stmt->execute([$subscription_id, $payment_method_id, $ar]);
        
        // Analytics frissítése a módosított árral
        $stmt = $conn->prepare("INSERT INTO subscription_analytics 
            (subscription_plan_id, total_subscriptions, active_subscriptions, total_revenue) 
            VALUES (?, 1, 1, ?) 
            ON DUPLICATE KEY UPDATE 
            total_subscriptions = total_subscriptions + 1,
            active_subscriptions = active_subscriptions + 1,
            total_revenue = total_revenue + ?");
        $stmt->execute([$subscription_plan_id, $ar, $ar]);

        // Email küldés a fizetés után
        try {
            // Felhasználó és cég adatainak lekérése
            $stmt = $conn->prepare("SELECT u.email as user_email, u.firstname, u.lastname, 
                                         c.company_name, c.company_email, c.company_address
                                  FROM user u 
                                  JOIN company c ON u.company_id = c.id 
                                  WHERE u.id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Csomag adatainak lekérése
            $stmt = $conn->prepare("SELECT name, description, price 
                                  FROM subscription_plans 
                                  WHERE id = ?");
            $stmt->execute([$subscription_plan_id]);
            $plan_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Email tárgya
            $subject = "Köszönjük a vásárlást! - VibeCore";

            // Email tartalom
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #3498db; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .footer { text-align: center; padding: 20px; color: #666; }
                    .details { background: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
                    .amount { font-size: 24px; color: #2ecc71; font-weight: bold; }
                    .section { margin-bottom: 20px; }
                    .section-title { color: #2c3e50; font-size: 18px; font-weight: bold; margin-bottom: 10px; }
                    .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
                    .info-label { color: #666; }
                    .info-value { color: #2c3e50; font-weight: 500; }
                    .package-features { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 10px; }
                    .feature-item { margin-bottom: 5px; color: #2c3e50; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Köszönjük a vásárlást!</h1>
                    </div>
                    
                    <div class='content'>
                        <p>Kedves {$user_data['firstname']} {$user_data['lastname']}!</p>
                        
                        <p>Örülünk, hogy minket választott! A megrendelését sikeresen feldolgoztuk.</p>
                        
                        <div class='details'>
                            <div class='section'>
                                <div class='section-title'>Megrendelő adatai</div>
                                <div class='info-row'>
                                    <span class='info-label'>Név:</span>
                                    <span class='info-value'>{$user_data['firstname']} {$user_data['lastname']}</span>
                                </div>
                                <div class='info-row'>
                                    <span class='info-label'>Cég neve:</span>
                                    <span class='info-value'>{$user_data['company_name']}</span>
                                </div>
                                <div class='info-row'>
                                    <span class='info-label'>Cég címe:</span>
                                    <span class='info-value'>{$user_data['company_address']}</span>
                                </div>
                            </div>

                            <div class='section'>
                                <div class='section-title'>Előfizetés részletei</div>
                                <div class='info-row'>
                                    <span class='info-label'>Csomag típusa:</span>
                                    <span class='info-value'>" . 
                                    str_replace(
                                        ['alap_eves', 'kozepes_eves', 'uzleti_eves', 'alap', 'kozepes', 'uzleti'],
                                        ['Alap csomag / éves', 'Közepes csomag / éves', 'Üzleti csomag / éves', 'Alap csomag / havi', 'Közepes csomag / havi', 'Üzleti csomag / havi'],
                                        $plan_data['name']
                                    ) . "</span>
                                </div>
                                <div class='package-features'>
                                    <div class='feature-item'>✓ " . ($felhasznalok ? $felhasznalok : '20') . " felhasználó</div>
                                    <div class='feature-item'>✓ " . ($eszkozok ? $eszkozok : '500') . " eszköz</div>
                                    <div class='feature-item'>✓ Korlátlan munkalap kezelés</div>
                                    <div class='feature-item'>✓ Eszköz nyilvántartás</div>
                                    <div class='feature-item'>✓ Projekt menedzsment</div>
                                    <div class='feature-item'>✓ Email értesítések</div>
                                    <div class='feature-item'>✓ 24/7 support</div>
                                </div>
                            </div>

                            <div class='section'>
                                <div class='section-title'>Fizetési információk</div>
                                <div class='info-row'>
                                    <span class='info-label'>Fizetett összeg:</span>
                                    <span class='amount'>" . number_format($ar, 0, ',', ' ') . " Ft</span>
                                </div>
                                " . ($period === 'ev' ? "
                                <div class='info-row'>
                                    <span class='info-label'>Eredeti ár (éves):</span>
                                    <span class='info-value' style='text-decoration: line-through;'>" . number_format($ar / 0.85, 0, ',', ' ') . " Ft</span>
                                </div>
                                <div class='info-row'>
                                    <span class='info-label'>Megtakarítás (15%):</span>
                                    <span class='info-value' style='color: #2ecc71;'>" . number_format($ar / 0.85 - $ar, 0, ',', ' ') . " Ft</span>
                                </div>
                                " : "") . "
                                <div class='info-row'>
                                    <span class='info-label'>Fizetés dátuma:</span>
                                    <span class='info-value'>" . date('Y.m.d') . "</span>
                                </div>
                                <div class='info-row'>
                                    <span class='info-label'>Előfizetés kezdete:</span>
                                    <span class='info-value'>" . date('Y.m.d') . "</span>
                                </div>
                                <div class='info-row'>
                                    <span class='info-label'>Előfizetés vége:</span>
                                    <span class='info-value'>" . date('Y.m.d', strtotime($period === 'ev' ? '+1 year' : '+1 month')) . "</span>
                                </div>
                            </div>
                        </div>
                        
                        <p>A szolgáltatás aktiválásáról hamarosan értesítjük. Az előfizetéshez kapcsolódó összes funkció " . ($period === 'ev' ? 'egy évig' : 'egy hónapig') . " lesz elérhető.</p>
                        
                        <p>Ha bármilyen kérdése van, kérem, ne habozzon felvenni velünk a kapcsolatot az alábbi elérhetőségeken:</p>
                        <p>Email: support@vibecore.hu<br>Telefon: +36 30 123 4567</p>
                    </div>
                    
                    <div class='footer'>
                        <p>Üdvözlettel,<br>VibeCore Csapata</p>
                    </div>
                </div>
            </body>
            </html>";

            // PHPMailer inicializálása
            $mail = new PHPMailer(true);
            
            // SMTP beállítások
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'kurinczjozsef@gmail.com';
            $mail->Password = 'qtmayweajrtybnck';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            
            // Email beállítások
            $mail->setFrom('kurinczjozsef@gmail.com', 'VibeCore');
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            
            // Email küldése a felhasználónak
            $mail->clearAddresses();
            $mail->addAddress($user_data['user_email']);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->send();
            
            // Email küldése a cégnek (ha különbözik a felhasználó email címétől)
            if ($user_data['company_email'] && $user_data['company_email'] !== $user_data['user_email']) {
                $mail->clearAddresses();
                $mail->addAddress($user_data['company_email']);
                $mail->send();
            }

            error_log("Email sikeresen elküldve: " . $user_data['user_email']);
            
        } catch (Exception $e) {
            error_log("Email küldési hiba: " . $e->getMessage());
            // Ne szakítsuk meg a tranzakciót email hiba esetén
        }

        // Tranzakció véglegesítése
        $conn->commit();
        
        // Sikeres válasz küldése - győződjünk meg róla, hogy csak ez kerül kiküldésre
        ob_clean(); // Tisztítsuk meg újra a puffert a biztonság kedvéért
        echo json_encode(['success' => true, 'subscription_id' => $subscription_id]);
        exit;
        
    } catch (Exception $e) {
        // Hiba esetén visszagörgetés
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Fizetési hiba: " . $e->getMessage());
        
        // Tisztítsuk meg a puffert és küldjünk hibaüzenetet
        ob_clean();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fizetés - VibeCore</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <style>
        .payment-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            padding: 20px;
        }

        .form {
            background: #fff;
            width: 400px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 24px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            color: #666;
            margin-bottom: 10px;
            font-size: 15px;
        }

        input {
            width: 100%;
            padding: 15px;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            background: #fff;
        }

        input:focus {
            outline: none;
            border-color: #3498db;
        }

        .split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .payment-button {
            width: 100%;
            text-align: center;
            margin-top: 20px;
        }

        .payment-button button {
            background: none;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            color: #333;
            font-size: 20px;
            cursor: pointer;
            padding: 15px 0;
            width: 100%;
            transition: all 0.3s ease;
        }

        .payment-button button:hover {
            border-color: #3498db;
        }

        .payment-button button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .thank-you-message {
            display: none;
            text-align: center;
            padding: 30px;
        }

        .thank-you-message h2 {
            color: #3498db;
            margin-bottom: 20px;
            font-size: 28px;
        }

        .thank-you-message p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .thank-you-message a {
            display: inline-block;
            color: #3498db;
            text-decoration: none;
            margin-top: 20px;
            padding: 10px 20px;
            border: 1px solid #3498db;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .thank-you-message a:hover {
            background: #3498db;
            color: white;
        }

        /* Animált fizetési gomb stílusok */
        .container {
            background-color: #ffffff;
            display: none;
            width: 270px;
            height: 120px;
            position: relative;
            border-radius: 6px;
            transition: 0.3s ease-in-out;
            margin: 0 auto;
        }

        .container.show .left-side {
            width: 100%;
            transition: width 0.3s ease;
        }

        .left-side {
            background-color: #5de2a3;
            width: 130px;
            height: 120px;
            border-radius: 4px;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: 0.3s;
            flex-shrink: 0;
            overflow: hidden;
        }

        .right-side {
            display: flex;
            align-items: center;
            overflow: hidden;
            cursor: pointer;
            justify-content: space-between;
            white-space: nowrap;
            transition: 0.3s;
        }

        .right-side:hover {
            background-color: #f9f7f9;
        }

        .new {
            font-size: 23px;
            font-family: "Arial", sans-serif;
            margin-left: 20px;
        }

        .card {
            width: 70px;
            height: 46px;
            background-color: #c7ffbc;
            border-radius: 6px;
            position: absolute;
            display: flex;
            z-index: 10;
            flex-direction: column;
            align-items: center;
            box-shadow: 9px 9px 9px -2px rgba(77, 200, 143, 0.72);
        }

        .card-line {
            width: 65px;
            height: 13px;
            background-color: #80ea69;
            border-radius: 2px;
            margin-top: 7px;
        }

        .buttons {
            width: 8px;
            height: 8px;
            background-color: #379e1f;
            box-shadow: 0 -10px 0 0 #26850e, 0 10px 0 0 #56be3e;
            border-radius: 50%;
            margin-top: 5px;
            transform: rotate(90deg);
            margin: 10px 0 0 -30px;
        }

        .post {
            width: 63px;
            height: 75px;
            background-color: #dddde0;
            position: absolute;
            z-index: 11;
            bottom: 10px;
            top: 120px;
            border-radius: 6px;
            overflow: hidden;
        }

        .post-line {
            width: 47px;
            height: 9px;
            background-color: #545354;
            position: absolute;
            border-radius: 0px 0px 3px 3px;
            right: 8px;
            top: 8px;
        }

        .post-line:before {
            content: "";
            position: absolute;
            width: 47px;
            height: 9px;
            background-color: #757375;
            top: -8px;
        }

        .screen {
            width: 47px;
            height: 23px;
            background-color: #ffffff;
            position: absolute;
            top: 22px;
            right: 8px;
            border-radius: 3px;
        }

        .numbers {
            width: 12px;
            height: 12px;
            background-color: #838183;
            box-shadow: 0 -18px 0 0 #838183, 0 18px 0 0 #838183;
            border-radius: 2px;
            position: absolute;
            transform: rotate(90deg);
            left: 25px;
            top: 52px;
        }

        .numbers-line2 {
            width: 12px;
            height: 12px;
            background-color: #aaa9ab;
            box-shadow: 0 -18px 0 0 #aaa9ab, 0 18px 0 0 #aaa9ab;
            border-radius: 2px;
            position: absolute;
            transform: rotate(90deg);
            left: 25px;
            top: 68px;
        }

        .card.animate {
            animation: slide-top 1.2s cubic-bezier(0.645, 0.045, 0.355, 1) both;
        }

        .post.animate {
            animation: slide-post 1s cubic-bezier(0.165, 0.84, 0.44, 1) both;
        }

        .dollar.animate {
            animation: fade-in-fwd 0.3s 1s backwards;
        }

        /* Módosítsuk a megjelenés/eltűnés animációját */
        .container {
            opacity: 0;
            display: none;
            transition: opacity 0.3s ease;
        }

        .container.show {
            display: flex;
            opacity: 1;
        }

        .payment-button {
            transition: opacity 0.3s ease;
        }

        .payment-button.hide {
            opacity: 0;
            pointer-events: none;
        }

        @keyframes slide-top {
            0% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-70px) rotate(90deg);
            }
            60% {
                transform: translateY(-70px) rotate(90deg);
            }
            100% {
                transform: translateY(-8px) rotate(90deg);
            }
        }

        @keyframes slide-post {
            50% {
                transform: translateY(0);
            }
            100% {
                transform: translateY(-70px);
            }
        }

        @keyframes fade-in-fwd {
            0% {
                opacity: 0;
                transform: translateY(-5px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Smooth átmenetek */
        .payment-button {
            opacity: 1;
            transition: opacity 0.5s ease;
        }

        .payment-button.hide {
            opacity: 0;
        }

        .container {
            opacity: 0;
            display: none;
        }

        .container.show {
            opacity: 1;
            display: flex;
        }

        .dollar {
            position: absolute;
            font-size: 16px;
            font-family: "Arial", sans-serif;
            width: 100%;
            left: 0;
            top: 0;
            color: #4b953b;
            text-align: center;
        }

        .auto-redirect {
            margin-top: 30px;
            text-align: center;
            color: #666;
            font-size: 15px;
            padding: 15px;
            border-top: 1px solid #eee;
        }

        .auto-redirect span {
            display: block;
            margin-top: 5px;
        }

        #countdown {
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
        }

        .redirect-text {
            color: #888;
            font-size: 14px;
        }

        /* Fizetési módok stílusa */
        .payment-methods {
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .payment-method {
            position: relative;
            border: 1px solid #e1e1e1;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .payment-method:hover {
            border-color: #3498db;
            background-color: #f8f9fa;
        }

        .payment-method input[type="radio"] {
            display: none;
        }

        .payment-method label {
            margin: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
        }

        .payment-method-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .payment-method-icon img {
            max-width: 100%;
            height: auto;
        }

        .payment-method.selected {
            border-color: #3498db;
            background-color: #ebf5fb;
        }

        .payment-method-text {
            font-size: 15px;
            color: #2c3e50;
        }

        /* Digital payment methods */
        .digital-payments {
            display: flex;
            flex-direction: row;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .digital-payment-btn {
            padding: 12px 20px;
            border-radius: 8px;
            border: 1px solid #e1e1e1;
            background: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            height: 55px;
            flex: 1;
            justify-content: center;
            max-width: 150px;
        }

        .digital-payment-btn:hover {
            background-color: #f8f9fa;
            border-color: #ddd;
        }

        .digital-payment-btn img {
            height: 40px;
            width: auto;
            object-fit: contain;
        }

        .payment-divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
        }

        .payment-divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e1e1e1;
        }

        .payment-divider span {
            background: #fff;
            padding: 0 15px;
            color: #666;
            position: relative;
            font-size: 14px;
        }

        /* Értesítés stílusok */
        .notification {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            max-width: 300px;
            animation: slideIn 0.3s ease-out;
        }

        .notification-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification-icon {
            background: #f8d7da;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notification-text {
            color: #333;
            font-size: 14px;
            line-height: 1.5;
        }

        .notification-title {
            font-weight: bold;
            margin-bottom: 4px;
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
    </style>
</head>
<body>
    <?php include 'includes/header2.php'; ?>

    <section class="payment-page">
        <div class="form">
            <h2>Bankkártyás fizetés</h2>
            
            <div class="digital-payments">
                <button type="button" class="digital-payment-btn" onclick="showNotification('Apple Pay')">
                    <img src="assets/img/apple-pay.png" alt="Apple Pay">
                </button>
                <button type="button" class="digital-payment-btn" onclick="showNotification('Google Pay')">
                    <img src="assets/img/google-pay.png" alt="Google Pay">
                </button>
            </div>

            <div class="payment-divider">
                <span>vagy fizess bankkártyával</span>
            </div>

            <form id="cardForm" method="POST">
                <div class="form-group">
                    <label>Kártyabirtokos neve</label>
                    <input type="text" name="card_holder" required placeholder="Kártyán szereplő név">
                </div>

                <div class="form-group">
                    <label>Kártyaszám</label>
                    <input type="text" name="card_number" required 
                           placeholder="1234 5678 9012 3456"
                           maxlength="19"
                           onkeyup="formatCardNumber(this)">
                </div>

                <div class="form-group split">
                    <div>
                        <label>Lejárati dátum</label>
                        <input type="text" name="expiry" required 
                               placeholder="HH/ÉÉ"
                               maxlength="5"
                               onkeyup="formatExpiryDate(this)">
                    </div>
                    <div>
                        <label>CVV kód</label>
                        <input type="password" name="cvv" required 
                               placeholder="***"
                               maxlength="3">
                    </div>
                </div>

                <div class="payment-button">
                    <button type="submit">Fizetés</button>
                </div>
            </form>

            <!-- Adjuk hozzá az animált gombot -->
            <div class="container" id="animatedButton">
                <div class="left-side">
                    <div class="card">
                        <div class="card-line"></div>
                        <div class="buttons"></div>
                    </div>
                    <div class="post">
                        <div class="post-line"></div>
                        <div class="screen">
                            <div class="dollar">$</div>
                        </div>
                        <div class="numbers"></div>
                        <div class="numbers-line2"></div>
                    </div>
                </div>
                <div class="right-side">
                    <div class="new">Fizetés</div>
                </div>
            </div>

            <div class="thank-you-message" id="thankYouMessage">
                <h2>Köszönjük a vásárlást!</h2>
                <p>Örülünk, hogy minket választott! A megrendelését sikeresen feldolgoztuk.</p>
                <p>A szolgáltatás aktiválásáról hamarosan e-mailben értesítjük.</p>
                <p><a href="home.php">Vissza a főoldalra</a></p>
                <div class="auto-redirect">
                    <span id="countdown">10</span>
                    <span class="redirect-text">Automatikus visszairányítás másodperc múlva</span>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer2.php'; ?>

    <!-- Hiba modal (letisztult, képen látható stílus) -->
    <div id="errorModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.25); z-index:2000; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; max-width:340px; margin:auto; box-shadow:0 4px 24px rgba(0,0,0,0.18); padding:32px 24px 24px 24px; text-align:center; position:relative;">
            <div style="background:#ffeaea; border-radius:50%; width:56px; height:56px; display:flex; align-items:center; justify-content:center; margin:0 auto 16px auto;">
                <svg width="32" height="32" fill="#e74c3c" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#ffeaea"/><path d="M12 7v4m0 4h.01" stroke="#e74c3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div style="color:#e74c3c; font-weight:700; font-size:20px; margin-bottom:8px;">Érvénytelen kártyaszám</div>
            <div style="color:#444; font-size:15px; margin-bottom:24px;">A megadott kártyaszám nem érvényes. Kérjük, ellenőrizze és próbálja újra.</div>
            <button id="closeErrorModal" style="background:#3498db; color:#fff; border:none; border-radius:8px; padding:10px 32px; font-size:16px; font-weight:600; cursor:pointer;">Rendben</button>
        </div>
    </div>

    <script>
        function formatCardNumber(input) {
            let value = input.value.replace(/\D/g, '');
            value = value.replace(/(\d{4})/g, '$1 ').trim();
            input.value = value;
        }

        function formatExpiryDate(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length >= 2) {
                const month = parseInt(value.substring(0, 2));
                if (month > 12) value = '12' + value.substring(2);
                if (month < 1) value = '01' + value.substring(2);
                
                if (value.length >= 4) {
                    const year = parseInt('20' + value.substring(2, 4));
                    const currentYear = new Date().getFullYear();
                    if (year < currentYear) {
                        value = value.substring(0, 2) + currentYear.toString().substring(2);
                    }
                }
                
                value = value.substring(0,2) + '/' + value.substring(2);
            }
            input.value = value;
        }

        document.getElementById('cardForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!this.checkValidity()) return;

            const form = document.querySelector('.form');
            
            // Digitális fizetési módok és elválasztó eltávolítása
            const digitalPayments = document.querySelector('.digital-payments');
            const paymentDivider = document.querySelector('.payment-divider');
            
            if (digitalPayments) digitalPayments.remove();
            if (paymentDivider) paymentDivider.remove();

            // Eredeti gomb elrejtése
            const paymentButton = document.querySelector('.payment-button');
            paymentButton.classList.add('hide');

            // URL paraméterek hozzáadása a form adatokhoz
            const urlParams = new URLSearchParams(window.location.search);
            const formData = new FormData(this);
            formData.append('csomag', urlParams.get('csomag'));
            formData.append('ar', urlParams.get('ar'));
            formData.append('period', urlParams.get('period'));

            // Debug: Ellenőrizzük a formData tartalmát
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }

            // Várunk a gomb eltűnésére
            setTimeout(() => {
                // Form tartalom elrejtése
                this.style.display = 'none';
                
                // Animált gomb megjelenítése
                const animatedButton = document.getElementById('animatedButton');
                animatedButton.classList.add('show');

                // Automatikusan indítjuk az animációt
                setTimeout(() => {
                    const leftSide = animatedButton.querySelector('.left-side');
                    leftSide.style.width = '100%';

                    const card = animatedButton.querySelector('.card');
                    card.style.animation = 'slide-top 1.2s cubic-bezier(0.645, 0.045, 0.355, 1) both';

                    const post = animatedButton.querySelector('.post');
                    post.style.animation = 'slide-post 1s cubic-bezier(0.165, 0.84, 0.44, 1) both';

                    const dollar = animatedButton.querySelector('.dollar');
                    dollar.style.animation = 'fade-in-fwd 0.3s 1s backwards';

                    // Az animáció végén elküldjük a form adatait
                    setTimeout(() => {
                        // Az animáció végén elküldjük a form adatait
                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                return response.json().then(data => {
                                    throw new Error(data.error || 'Ismeretlen hiba történt');
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Várunk még 3 másodpercet az animáció után
                                setTimeout(() => {
                                    // Fokozatos elhalványulás
                                    animatedButton.style.transition = 'opacity 0.5s ease';
                                    animatedButton.style.opacity = '0';
                                    
                                    // Az elhalványulás után átirányítunk
                                    setTimeout(() => {
                                        window.location.href = 'payment_success.php?subscription_id=' + data.subscription_id;
                                    }, 500);
                                }, 3000);
                            } else {
                                throw new Error(data.error || 'Ismeretlen hiba történt');
                            }
                        })
                        .catch(handleCardError);
                    }, 2500);
                }, 100);
            }, 500);
        });

        function showNotification(paymentMethod) {
            // Létrehozzuk az értesítés elemet
            const notification = document.createElement('div');
            notification.className = 'notification';
            
            notification.innerHTML = `
                <div class="notification-content">
                    <div class="notification-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="#dc3545">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                    </div>
                    <div class="notification-text">
                        <div class="notification-title">${paymentMethod} jelenleg nem elérhető</div>
                        <div>Dolgozunk a szolgáltatás bevezetésén. Kérjük, használja a bankkártyás fizetést.</div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            notification.style.display = 'block';
            
            // 3 másodperc után eltűnik
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // Modal bezárása
        const errorModal = document.getElementById('errorModal');
        const closeErrorModal = document.getElementById('closeErrorModal');
        if (closeErrorModal) {
            closeErrorModal.onclick = function() {
                errorModal.style.display = 'none';
                window.location.reload();
            };
        }
        // Modal overlayre kattintásra is zárjon
        if (errorModal) {
            errorModal.onclick = function(e) {
                if (e.target === errorModal) errorModal.style.display = 'none';
            };
        }

        // Módosított hibakezelés csak érvénytelen kártyaszámra
        function handleCardError(error) {
            if (error.message && error.message.toLowerCase().includes('érvénytelen kártyaszám')) {
                errorModal.style.display = 'flex';
            } else {
                alert(error.message || 'Hiba történt a fizetés során. Kérjük, próbálja újra.');
                window.location.reload();
            }
        }
    </script>
</body>
</html> 