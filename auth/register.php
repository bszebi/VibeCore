<?php
ob_start(); // Output buffering bekapcsolása

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Session indítása
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Adatbázis kapcsolat létrehozása
try {
    $db = DatabaseConnection::getInstance()->getConnection();
} catch (PDOException $e) {
    die('Adatbázis kapcsolódási hiba: ' . $e->getMessage());
}

// Ha van aktív munkamenet, töröljük azt
if (isset($_SESSION['user_id'])) {
    session_destroy();
    // Átirányítás a regisztrációs oldalra
    header('Location: register.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'firstname' => sanitizeInput($_POST['firstname']),
        'lastname' => sanitizeInput($_POST['lastname']),
        'email' => sanitizeInput($_POST['email']),
        'telephone' => sanitizeInput($_POST['telephone']),
        'company_name' => sanitizeInput($_POST['company_name']),
        'company_address' => sanitizeInput($_POST['company_address']),
        'company_email' => sanitizeInput($_POST['company_email']),
        'company_telephone' => sanitizeInput($_POST['company_telephone']),
        'password' => $_POST['password'],
        'password_confirmation' => $_POST['password_confirmation']
    ];

    // Profilkép kezelése
    $profile_pic = 'user.png'; // Alapértelmezett kép
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file = $_FILES['profile_pic'];
        
        if (in_array($file['type'], $allowed_types)) {
            $upload_dir = '../uploads/profiles/';
            
            // Ellenőrizzük/létrehozzuk a mappákat
            if (!file_exists('../uploads')) {
                mkdir('../uploads', 0777, true);
            }
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Egyedi fájlnév generálása
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $profile_pic = 'profile_' . time() . '_' . uniqid() . '.' . $extension;
            
            // Fájl mozgatása
            if (!move_uploaded_file($file['tmp_name'], $upload_dir . $profile_pic)) {
                $error = 'Hiba történt a profilkép feltöltése során!';
            }
        } else {
            $error = 'Nem megfelelő fájlformátum! Csak JPG, PNG és GIF képeket lehet feltölteni.';
        }
    }

    // Vállalati logó kezelése
    $company_logo = 'default_company.png'; // Alapértelmezett logó
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file = $_FILES['company_logo'];
        
        if (in_array($file['type'], $allowed_types)) {
            $upload_dir = '../uploads/company_logos/';
            
            // Ellenőrizzük/létrehozzuk a mappákat
            if (!file_exists('../uploads')) {
                mkdir('../uploads', 0777, true);
            }
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Egyedi fájlnév generálása
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $company_logo = 'company_' . time() . '_' . uniqid() . '.' . $extension;
            
            // Fájl mozgatása
            if (!move_uploaded_file($file['tmp_name'], $upload_dir . $company_logo)) {
                $error = 'Hiba történt a vállalati logó feltöltése során!';
            }
        } else {
            $error = 'Nem megfelelő fájlformátum! Csak JPG, PNG és GIF képeket lehet feltölteni.';
        }
    }
    
    if (empty($data['firstname']) || empty($data['lastname']) || empty($data['email']) || empty($data['password'])) {
        $error = 'A csillaggal jelölt mezők kitöltése kötelező!';
    } elseif (!validateEmail($data['email'])) {
        $error = 'Érvénytelen email cím!';
    } elseif ($data['password'] !== $data['password_confirmation']) {
        $error = 'A jelszavak nem egyeznek!';
    } elseif (strlen($data['password']) < 8) {
        $error = 'A jelszónak legalább 8 karakter hosszúnak kell lennie!';
    } elseif (!preg_match('/[A-Z]/', $data['password'])) {
        $error = 'A jelszónak tartalmaznia kell legalább egy nagybetűt!';
    } elseif (!preg_match('/[a-z]/', $data['password'])) {
        $error = 'A jelszónak tartalmaznia kell legalább egy kisbetűt!';
    } elseif (!preg_match('/[0-9]/', $data['password'])) {
        $error = 'A jelszónak tartalmaznia kell legalább egy számot!';
    } else {
        try {
            // Email cím ellenőrzése
            $stmt = $db->prepare("SELECT id FROM user WHERE email = :email");
            $stmt->execute([':email' => $data['email']]);
            if ($stmt->fetch()) {
                $error = 'Ez az email cím már regisztrálva van!';
            } else {
                // Telefonszám ellenőrzése
                $stmt = $db->prepare("SELECT id FROM user WHERE telephone = :telephone");
                $stmt->execute([':telephone' => $data['telephone']]);
                if ($stmt->fetch()) {
                    $error = 'Ez a telefonszám már regisztrálva van!';
                } else {
                    // Ha van cégnév, ellenőrizzük a vállalati telefonszámot is
                    if (!empty($data['company_name'])) {
                        $stmt = $db->prepare("SELECT id FROM company WHERE company_telephone = :company_telephone");
                        $stmt->execute([':company_telephone' => $data['company_telephone']]);
                        if ($stmt->fetch()) {
                            $error = 'Ez a vállalati telefonszám már regisztrálva van!';
                        } else {
                            $db->beginTransaction();
                            
                            // Először ellenőrizzük, hogy létezik-e már a cég
                            $companyId = null;
                            if (!empty($data['company_name'])) {
                                $companyStmt = $db->prepare("
                                    INSERT INTO company (company_name, company_address, company_email, company_telephone, profile_picture)
                                    VALUES (:company_name, :company_address, :company_email, :company_telephone, :profile_picture)
                                ");
                                
                                $companyStmt->execute([
                                    ':company_name' => $data['company_name'],
                                    ':company_address' => $data['company_address'],
                                    ':company_email' => $data['company_email'],
                                    ':company_telephone' => $data['company_telephone'],
                                    ':profile_picture' => $company_logo
                                ]);
                                
                                $companyId = $db->lastInsertId();
                            } else {
                                // Alapértelmezett cég létrehozása a felhasználó számára
                                $defaultCompanyStmt = $db->prepare("
                                    INSERT INTO company (company_name, company_address, company_email, company_telephone, profile_picture)
                                    VALUES (:company_name, :company_address, :company_email, :company_telephone, :profile_picture)
                                ");
                                
                                $defaultCompanyStmt->execute([
                                    ':company_name' => $data['firstname'] . ' ' . $data['lastname'] . ' Cége',
                                    ':company_address' => 'Nincs megadva',
                                    ':company_email' => $data['email'],
                                    ':company_telephone' => $data['telephone'],
                                    ':profile_picture' => 'default_company.png'
                                ]);
                                
                                $companyId = $db->lastInsertId();
                            }
                            
                            // Jelszó hashelése
                            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                            
                            // User létrehozása a profilképpel együtt
                            $userStmt = $db->prepare("
                                INSERT INTO user (
                                    firstname, 
                                    lastname, 
                                    email, 
                                    telephone,
                                    password, 
                                    company_id,
                                    current_status_id,
                                    profile_pic,
                                    is_email_verified
                                ) VALUES (
                                    :firstname,
                                    :lastname,
                                    :email,
                                    :telephone,
                                    :password,
                                    :company_id,
                                    1,
                                    :profile_pic,
                                    0
                                )
                            ");
                            
                            $userStmt->execute([
                                ':firstname' => $data['firstname'],
                                ':lastname' => $data['lastname'],
                                ':email' => $data['email'],
                                ':telephone' => $data['telephone'],
                                ':password' => $hashedPassword,
                                ':company_id' => $companyId,
                                ':profile_pic' => $profile_pic
                            ]);
                            
                            $userId = $db->lastInsertId();
                            
                            // Cég tulajdonos szerepkör hozzáadása
                            $roleStmt = $db->prepare("
                                INSERT INTO user_to_roles (user_id, role_id) 
                                SELECT :user_id, id 
                                FROM roles 
                                WHERE role_name = 'Cég tulajdonos'
                            ");
                            
                            $roleStmt->execute([':user_id' => $userId]);
                            
                            // Státusz történet létrehozása
                            $statusStmt = $db->prepare("
                                INSERT INTO status_history (user_id, status_id, status_startdate)
                                VALUES (:user_id, 1, NOW())
                            ");
                            
                            $statusStmt->execute([':user_id' => $userId]);
                            
                            // Alapértelmezett fizetési mód létrehozása a free-trial előfizetéshez
                            $defaultPaymentMethodStmt = $db->prepare("
                                INSERT INTO payment_methods (
                                    user_id, 
                                    card_holder_name, 
                                    CVC, 
                                    card_expiry_month, 
                                    card_expiry_year, 
                                    is_default
                                ) VALUES (
                                    :user_id,
                                    :card_holder_name,
                                    :CVC,
                                    :card_expiry_month,
                                    :card_expiry_year,
                                    1
                                )
                            ");
                            
                            // Alapértelmezett értékek a free-trial előfizetéshez
                            $defaultPaymentMethodStmt->execute([
                                ':user_id' => $userId,
                                ':card_holder_name' => $data['firstname'] . ' ' . $data['lastname'],
                                ':CVC' => '000',
                                ':card_expiry_month' => '12',
                                ':card_expiry_year' => '2025'
                            ]);
                            
                            $paymentMethodId = $db->lastInsertId();
                            
                            // Free-trial előfizetés létrehozása
                            $trialEndDate = date('Y-m-d H:i:s', strtotime('+14 days'));
                            
                            $subscriptionStmt = $db->prepare("
                                INSERT INTO subscriptions (
                                    user_id,
                                    company_id,
                                    subscription_plan_id,
                                    payment_method_id,
                                    subscription_status_id,
                                    start_date,
                                    trial_end_date
                                ) VALUES (
                                    :user_id,
                                    :company_id,
                                    9, -- free-trial plan ID
                                    :payment_method_id,
                                    1, -- aktív státusz
                                    NOW(),
                                    :trial_end_date
                                )
                            ");
                            
                            $subscriptionStmt->execute([
                                ':user_id' => $userId,
                                ':company_id' => $companyId,
                                ':payment_method_id' => $paymentMethodId,
                                ':trial_end_date' => $trialEndDate
                            ]);
                            
                            // Verifikációs kód generálása és mentése
                            $verification_code = sprintf("%06d", mt_rand(0, 999999));
                            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
                            
                            $stmt = $db->prepare("
                                INSERT INTO email_verification_codes (user_id, verification_code, expires_at)
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$userId, $verification_code, $expires_at]);
                            
                            // Email küldése a verifikációs kóddal
                            $to = $data['email'];
                            $subject = "Email verifikáció - VibeCore";
                            $message = "
                                <html>
                                <head>
                                    <style>
                                        body { 
                                            font-family: Arial, sans-serif;
                                            line-height: 1.6;
                                            margin: 0;
                                            padding: 0;
                                            background-color: #f4f4f4;
                                        }
                                        .email-container {
                                            max-width: 600px;
                                            margin: 0 auto;
                                            padding: 20px;
                                            background-color: #ffffff;
                                            border-radius: 10px;
                                            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                                        }
                                        .header {
                                            text-align: center;
                                            padding: 20px 0;
                                            border-bottom: 2px solid #f0f0f0;
                                        }
                                        .header img {
                                            max-width: 200px;
                                            height: auto;
                                        }
                                        .content {
                                            padding: 30px 20px;
                                            color: #333333;
                                        }
                                        .verification-code {
                                            background-color: #f8f9fa;
                                            border: 2px solid #e9ecef;
                                            border-radius: 8px;
                                            padding: 20px;
                                            margin: 20px 0;
                                            text-align: center;
                                            font-size: 32px;
                                            letter-spacing: 5px;
                                            color: #3498db;
                                            font-weight: bold;
                                        }
                                        .info {
                                            background-color: #f8f9fa;
                                            border-left: 4px solid #3498db;
                                            padding: 15px;
                                            margin: 20px 0;
                                            color: #666;
                                            font-size: 14px;
                                        }
                                        .footer {
                                            text-align: center;
                                            padding: 20px;
                                            color: #666;
                                            font-size: 12px;
                                            border-top: 2px solid #f0f0f0;
                                        }
                                        .button {
                                            display: inline-block;
                                            padding: 10px 20px;
                                            background-color: #3498db;
                                            color: white;
                                            text-decoration: none;
                                            border-radius: 5px;
                                            margin: 20px 0;
                                        }
                                    </style>
                                </head>
                                <body>
                                    <div class='email-container'>
                                        <div class='header'>
                                            <img src='../admin/VIBECORE.PNG' alt='VibeCore Logo' style='max-width: 200px; height: auto;'>
                                            <h2>Email Cím Megerősítése</h2>
                                        </div>
                                        <div class='content'>
                                            <p>Kedves " . $data['lastname'] . " " . $data['firstname'] . "!</p>
                                            
                                            <p>Köszönjük, hogy regisztráltál a VibeCore rendszerében! Az email címed megerősítéséhez kérjük, használd az alábbi verifikációs kódot:</p>
                                            
                                            <div class='verification-code'>" . $verification_code . "</div>
                                            
                                            <div class='info'>
                                                <strong>Fontos:</strong><br>
                                                • A verifikációs kód 24 óráig érvényes<br>
                                                • A kódot ne oszd meg senkivel<br>
                                                • Három próbálkozási lehetőséged van
                                            </div>
                                            
                                            <p>Ha nem te kezdeményezted a regisztrációt, kérjük, hagyd figyelmen kívül ezt az emailt.</p>
                                        </div>
                                        <div class='footer'>
                                            <p>Ez egy automatikus üzenet, kérjük, ne válaszolj rá.</p>
                                            <p>&copy; " . date('Y') . " VibeCore. Minden jog fenntartva.</p>
                                        </div>
                                    </div>
                                </body>
                                </html>";
                            
                            if (!sendEmail($to, $subject, $message)) {
                                throw new Exception('Hiba történt az email küldése során. Kérjük, próbálja újra később.');
                            }
                            
                            $db->commit();
                            
                            // Session-ben tároljuk a user_id-t a verifikációhoz
                            $_SESSION['temp_user_id'] = $userId;
                            
                            // Átirányítás a verifikációs oldalra
                            header('Location: verify_email.php');
                            exit;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $db->rollBack();
            // Ha hiba történt és új kép lett feltöltve, töröljük
            if ($profile_pic !== 'user.png' && file_exists('../uploads/profiles/' . $profile_pic)) {
                unlink('../uploads/profiles/' . $profile_pic);
            }
            if ($company_logo !== 'default_company.png' && file_exists('../uploads/company_logos/' . $company_logo)) {
                unlink('../uploads/company_logos/' . $company_logo);
            }
            $error = 'Hiba történt a regisztráció során: ' . $e->getMessage();
        }
    }
}

// A HTML kód előtt:
ob_end_flush();

?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vállalati Regisztráció - VibeCore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .main-title {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
            width: 100%;
            max-width: 1200px;
        }

        .main-title h1 {
            font-size: 2.5em;
            margin: 0;
            font-weight: 600;
        }

        .main-title p {
            font-size: 1.1em;
            color: #7f8c8d;
            margin: 10px 0 0 0;
        }

        .page-content {
            display: flex;
            gap: 0;
            max-width: 1200px;
            width: 100%;
            position: relative;
            justify-content: center;
            align-items: flex-start;
        }
        
        .register-container {
            flex: 0 1 700px;
            max-width: 700px;
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            position: relative;
            left: 0;
        }

        .profile-section {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .profile-upload {
            display: inline-block;
            text-align: center;
            width: 150px;
            height: 150px;
            position: relative;
            cursor: pointer;
            border-radius: 50%;
            overflow: hidden;
        }
        
        .profile-upload .upload-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: #f8f9fa;
            border: 2px dashed #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        
        .profile-upload:hover .upload-placeholder {
            border-color: #6c757d;
            background-color: #f8f9fa;
        }

        .profile-upload .hover-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(108, 117, 125, 0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 50%;
        }

        .profile-upload:hover .hover-overlay {
            opacity: 1;
        }

        .hover-overlay i {
            font-size: 2em;
            color: white;
            margin-bottom: 8px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .hover-overlay span {
            color: white;
            font-size: 0.9em;
            text-align: center;
            padding: 0 10px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .form-section {
            max-width: 600px;
            margin: 0 auto;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 12px;
        }

        .form-group {
            margin-bottom: 12px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.9em;
        }

        .form-group input:not([type="file"]) {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            border-color: #3498db;
            outline: none;
        }

        .company-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }

        .section-title {
            color: #2c3e50;
            font-size: 1.2em;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #3498db;
        }

        .btn-register {
            background: #3498db;
            color: white;
            padding: 15px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-size: 1em;
            font-weight: 600;
            transition: background-color 0.3s ease;
            margin-top: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-register:hover {
            background: #2980b9;
        }

        .error-message {
            background: #fff5f5;
            color: #e74c3c;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 4px solid #e74c3c;
            font-size: 0.9em;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 40px;
            cursor: pointer;
            width: 24px;
            height: 24px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }

        .password-toggle.hide {
            background-image: url('../assets/img/hide.png');
        }

        .password-toggle.view {
            background-image: url('../assets/img/view.png');
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #7f8c8d;
        }

        .login-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .company-logo-section {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        /* Új stílusok a vállalati logó feltöltéshez */
        .company-logo-section .profile-upload {
            width: 200px;
            height: 150px;
            border-radius: 10px;
        }

        .company-logo-section .profile-upload .upload-placeholder {
            width: 200px;
            height: 150px;
            border-radius: 10px;
        }

        .company-logo-section .profile-upload .hover-overlay {
            border-radius: 10px;
        }

        .company-logo-section .profile-upload img {
            width: 200px;
            height: 150px;
            border-radius: 10px;
            object-fit: contain;
            padding: 10px;
            background: #fff;
        }

        .phone-group {
            display: flex;
            gap: 0;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            height: 40px;
        }

        .country-select {
            min-width: 90px;
            padding: 0 30px 0 10px;
            border: none;
            border-right: 1px solid #e9ecef;
            font-size: 0.95rem;
            background-color: white;
            cursor: pointer;
            transition: border-color 0.3s ease;
            color: #2c3e50;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%232c3e50' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }

        .country-select:focus {
            outline: none;
        }

        .phone-input-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
        }

        .phone-input {
            width: 100%;
            height: 100%;
            padding: 0 15px;
            border: none;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
            color: #2c3e50;
        }

        .phone-input:focus {
            outline: none;
        }

        .phone-group:focus-within {
            border-color: #3498db;
        }

        .home-link {
            position: fixed;
            top: 20px;
            left: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: #333;
            padding: 15px;
            transition: transform 0.2s;
            z-index: 1000;
            background: none;
        }

        .home-link:hover {
            transform: translateY(-2px);
        }

        .home-link img {
            width: 50px;
            height: 50px;
        }

        .home-link span {
            font-size: 18px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <a href="../home.php" class="home-link">
        <img src="../assets/img/running.png" alt="Vissza">
        <span>Vissza a főoldalra</span>
    </a>

    <div class="main-title">
        <h1>Vállalati Regisztráció</h1>
        <p>Hozza létre vállalati fiókját a VibeCore rendszerében</p>
    </div>

    <div class="page-content">
        <div class="register-container">
            <?php if ($error): ?>
                <div class="error-message">
                    <p><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="form-section">
                <div class="profile-section">
                    <div class="profile-upload" onclick="document.getElementById('profile_pic').click()">
                        <div class="upload-placeholder" id="uploadPlaceholder">
                            <i class="fas fa-user-circle"></i>
                            <span class="upload-text">Profilkép feltöltése</span>
                        </div>
                        <input type="file" id="profile_pic" name="profile_pic" style="display: none" accept="image/*">
                        <div id="preview"></div>
                    </div>
                </div>

                <div class="section-title">
                    <i class="fas fa-user"></i> Személyes adatok
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="lastname"><i class="fas fa-user"></i> Vezetéknév</label>
                        <input type="text" id="lastname" name="lastname" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="firstname"><i class="fas fa-user"></i> Keresztnév</label>
                        <input type="text" id="firstname" name="firstname" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email cím</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="telephone"><i class="fas fa-phone"></i> Telefonszám</label>
                    <div class="phone-group">
                        <select class="country-select" id="country_code" name="country_code">
                            <option value="36" selected>HUN</option>
                            <option value="43">AUT</option>
                            <option value="385">CRO</option>
                            <option value="44">ENG</option>
                            <option value="33">FRA</option>
                            <option value="49">GER</option>
                            <option value="39">ITA</option>
                            <option value="48">POL</option>
                            <option value="40">ROM</option>
                            <option value="381">SRB</option>
                            <option value="421">SVK</option>
                            <option value="34">ESP</option>
                            <option value="1">USA</option>
                        </select>
                        <div class="phone-input-wrapper">
                            <input type="tel" id="telephone" name="telephone" class="phone-input" 
                                   value="<?php echo htmlspecialchars($telephone ?? ''); ?>" 
                                   placeholder="20 420 6942"
                                   pattern="[0-9]*"
                                   inputmode="numeric"
                                   required>
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Jelszó</label>
                        <div class="password-field-wrapper">
                            <input type="password" id="password" name="password" required onkeyup="checkPasswordStrength()">
                            <span class="password-toggle hide" onclick="togglePasswordVisibility('password')"></span>
                        </div>
                        <div style="width: 100%; height: 5px; background-color: #eee; border-radius: 3px; margin-top: 5px; overflow: hidden;">
                            <div id="password-strength-meter" style="height: 100%; width: 0%; transition: all 0.3s ease;"></div>
                        </div>
                        <div id="password-strength-text" style="font-size: 0.8rem; margin-top: 5px;"></div>
                        
                        <ul class="password-requirements" style="font-size: 0.8rem; color: #6c757d; margin-top: 8px; padding-left: 20px;">
                            <li id="length-check">Legalább 8 karakter</li>
                            <li id="uppercase-check">Legalább egy nagybetű</li>
                            <li id="lowercase-check">Legalább egy kisbetű</li>
                            <li id="number-check">Legalább egy szám</li>
                        </ul>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirmation"><i class="fas fa-lock"></i> Jelszó megerősítése</label>
                        <div class="password-field-wrapper">
                            <input type="password" id="password_confirmation" name="password_confirmation" required onkeyup="checkPasswordMatch()">
                            <span class="password-toggle hide" onclick="togglePasswordVisibility('password_confirmation')"></span>
                        </div>
                        <div id="password-match-text" style="font-size: 0.8rem; margin-top: 5px;"></div>
                    </div>
                </div>

                <div class="company-section">
                    <div class="section-title">
                        <i class="fas fa-building"></i> Vállalati adatok
                    </div>

                    <div class="company-logo-section">
                        <div class="profile-upload" onclick="document.getElementById('company_logo').click()">
                            <div class="upload-placeholder" id="companyLogoPlaceholder">
                                <i class="fas fa-building"></i>
                                <span class="upload-text">Vállalati logó feltöltése</span>
                            </div>
                            <input type="file" id="company_logo" name="company_logo" style="display: none" accept="image/*">
                            <div id="company_logo_preview"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="company_name"><i class="fas fa-building"></i> Vállalat neve</label>
                        <input type="text" id="company_name" name="company_name" required>
                    </div>

                    <div class="form-group">
                        <label for="company_address"><i class="fas fa-map-marker-alt"></i> Vállalat címe</label>
                        <input type="text" id="company_address" name="company_address" required>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="company_email"><i class="fas fa-envelope"></i> Vállalati email</label>
                            <input type="email" id="company_email" name="company_email" required>
                        </div>

                        <div class="form-group">
                            <label for="company_telephone"><i class="fas fa-phone"></i> Vállalati telefon</label>
                            <div class="phone-group">
                                <select class="country-select" id="company_country_code" name="company_country_code">
                                    <option value="36" selected>HUN</option>
                                    <option value="43">AUT</option>
                                    <option value="385">CRO</option>
                                    <option value="44">ENG</option>
                                    <option value="33">FRA</option>
                                    <option value="49">GER</option>
                                    <option value="39">ITA</option>
                                    <option value="48">POL</option>
                                    <option value="40">ROM</option>
                                    <option value="381">SRB</option>
                                    <option value="421">SVK</option>
                                    <option value="34">ESP</option>
                                    <option value="1">USA</option>
                                </select>
                                <div class="phone-input-wrapper">
                                    <input type="tel" id="company_telephone" name="company_telephone" class="phone-input" 
                                           placeholder="20 420 6942"
                                           pattern="[0-9]*"
                                           inputmode="numeric"
                                           required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-register">
                    <i class="fas fa-user-plus"></i> Regisztráció
                </button>
            </form>

            <div class="login-link">
                Már van fiókja? <a href="login.php">Jelentkezzen be!</a>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('profile_pic').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('preview');
                    const placeholder = document.getElementById('uploadPlaceholder');
                    if (placeholder) placeholder.style.display = 'none';
                    
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Profilkép előnézet">
                        <div class="hover-overlay">
                            <i class="fas fa-camera"></i>
                            <span>Profilkép módosítása</span>
                        </div>
                    `;
                }
                reader.readAsDataURL(file);
            }
        });

        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const toggle = input.parentElement.querySelector('.password-toggle');
            
            if (input.type === 'password') {
                input.type = 'text';
                toggle.classList.remove('hide');
                toggle.classList.add('view');
            } else {
                input.type = 'password';
                toggle.classList.remove('view');
                toggle.classList.add('hide');
            }
        }

        // Vállalati logó kezelése
        document.getElementById('company_logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('company_logo_preview');
                    const placeholder = document.getElementById('companyLogoPlaceholder');
                    if (placeholder) placeholder.style.display = 'none';
                    
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Vállalati logó előnézet">
                        <div class="hover-overlay">
                            <i class="fas fa-camera"></i>
                            <span>Logó módosítása</span>
                        </div>
                    `;
                }
                reader.readAsDataURL(file);
            }
        });

        // Telefonszám input kezelése
        document.getElementById('telephone').addEventListener('input', function(e) {
            // Csak számokat engedünk meg
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Maximum hossz ellenőrzése országonként
            const maxLengths = {
                '43': 11,  // AUT
                '385': 9,  // CRO
                '44': 11,  // ENG
                '33': 9,   // FRA
                '49': 11,  // GER
                '36': 9,   // HUN
                '39': 10,  // ITA
                '48': 9,   // POL
                '40': 9,   // ROM
                '381': 9,  // SRB
                '421': 9,  // SVK
                '34': 9,   // ESP
                '1': 10    // USA
            };
            
            const countryCode = document.getElementById('country_code').value;
            const maxLength = maxLengths[countryCode] || 9;
            
            if (this.value.length > maxLength) {
                this.value = this.value.slice(0, maxLength);
            }
        });

        // Országkód változás kezelése
        document.getElementById('country_code').addEventListener('change', function(e) {
            const phoneInput = document.getElementById('telephone');
            phoneInput.value = ''; // Töröljük a jelenlegi értéket
            
            // Placeholder beállítása az országkód alapján
            const placeholders = {
                '43': '664 1234567',    // AUT
                '385': '91 234 5678',   // CRO
                '44': '7700 123456',    // ENG
                '33': '6 12 34 56 78',  // FRA
                '49': '151 1234567',    // GER
                '36': '20 420 6942',    // HUN
                '39': '312 345 6789',   // ITA
                '48': '512 345 678',    // POL
                '40': '712 345 678',    // ROM
                '381': '63 1234567',    // SRB
                '421': '903 123 456',   // SVK
                '34': '612 345 678',    // ESP
                '1': '555 123 4567'     // USA
            };
            
            phoneInput.placeholder = placeholders[this.value] || '20 420 6942';
        });

        // Vállalati telefonszám input kezelése
        document.getElementById('company_telephone').addEventListener('input', function(e) {
            // Csak számokat engedünk meg
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Maximum hossz ellenőrzése országonként
            const maxLengths = {
                '43': 11,  // AUT
                '385': 9,  // CRO
                '44': 11,  // ENG
                '33': 9,   // FRA
                '49': 11,  // GER
                '36': 9,   // HUN
                '39': 10,  // ITA
                '48': 9,   // POL
                '40': 9,   // ROM
                '381': 9,  // SRB
                '421': 9,  // SVK
                '34': 9,   // ESP
                '1': 10    // USA
            };
            
            const countryCode = document.getElementById('company_country_code').value;
            const maxLength = maxLengths[countryCode] || 9;
            
            if (this.value.length > maxLength) {
                this.value = this.value.slice(0, maxLength);
            }
        });

        // Vállalati országkód változás kezelése
        document.getElementById('company_country_code').addEventListener('change', function(e) {
            const phoneInput = document.getElementById('company_telephone');
            phoneInput.value = ''; // Töröljük a jelenlegi értéket
            
            // Placeholder beállítása az országkód alapján
            const placeholders = {
                '43': '664 1234567',    // AUT
                '385': '91 234 5678',   // CRO
                '44': '7700 123456',    // ENG
                '33': '6 12 34 56 78',  // FRA
                '49': '151 1234567',    // GER
                '36': '20 420 6942',    // HUN
                '39': '312 345 6789',   // ITA
                '48': '512 345 678',    // POL
                '40': '712 345 678',    // ROM
                '381': '63 1234567',    // SRB
                '421': '903 123 456',   // SVK
                '34': '612 345 678',    // ESP
                '1': '555 123 4567'     // USA
            };
            
            phoneInput.placeholder = placeholders[this.value] || '20 420 6942';
        });

        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const meter = document.getElementById('password-strength-meter');
            const strengthText = document.getElementById('password-strength-text');
            
            // Check individual requirements
            document.getElementById('length-check').style.color = password.length >= 8 ? '#28a745' : '#6c757d';
            document.getElementById('uppercase-check').style.color = /[A-Z]/.test(password) ? '#28a745' : '#6c757d';
            document.getElementById('lowercase-check').style.color = /[a-z]/.test(password) ? '#28a745' : '#6c757d';
            document.getElementById('number-check').style.color = /[0-9]/.test(password) ? '#28a745' : '#6c757d';
            
            // Calculate strength
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[a-z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            
            // Update meter and text
            meter.style.width = strength + '%';
            
            if (strength === 0) {
                meter.style.backgroundColor = '#eee';
                strengthText.textContent = '';
            } else if (strength <= 25) {
                meter.style.backgroundColor = '#dc3545';
                strengthText.textContent = 'Gyenge';
                strengthText.style.color = '#dc3545';
            } else if (strength <= 50) {
                meter.style.backgroundColor = '#ffc107';
                strengthText.textContent = 'Közepes';
                strengthText.style.color = '#ffc107';
            } else if (strength <= 75) {
                meter.style.backgroundColor = '#17a2b8';
                strengthText.textContent = 'Jó';
                strengthText.style.color = '#17a2b8';
            } else {
                meter.style.backgroundColor = '#28a745';
                strengthText.textContent = 'Erős';
                strengthText.style.color = '#28a745';
            }
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirmation').value;
            const matchText = document.getElementById('password-match-text');
            
            // Csak akkor ellenőrizzük, ha van első jelszó és elkezdték írni a másodikat
            if (password && confirmPassword) {
                if (password === confirmPassword) {
                    matchText.textContent = 'A jelszavak egyeznek';
                    matchText.style.color = '#28a745';
                } else {
                    matchText.textContent = 'A jelszavak nem egyeznek';
                    matchText.style.color = '#dc3545';
                }
            } else {
                matchText.textContent = '';
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('password_confirmation');
            
            if (passwordField) {
                passwordField.addEventListener('input', checkPasswordStrength);
            }
            
            if (confirmPasswordField) {
                confirmPasswordField.addEventListener('input', checkPasswordMatch);
            }
        });
    </script>
</body>
</html>