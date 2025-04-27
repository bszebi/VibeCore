<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function registerUser($data) {
    try {
        $db = DatabaseConnection::getInstance()->getConnection();
        
        // Tranzakció kezdése
        $db->beginTransaction();
        
        // Email ellenőrzés
        $stmt = $db->prepare("SELECT id, email FROM user WHERE email = :email");
        $stmt->execute([':email' => $data['email']]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingUser) {
            $db->rollBack();
            throw new Exception('Ez az email cím már foglalt!');
        }

        // Ha van cégnév, ellenőrizzük azt is
        if (!empty($data['company_name'])) {
            // Cégnév ellenőrzése
            $stmt = $db->prepare("SELECT id FROM company WHERE company_name = :company_name");
            $stmt->execute([':company_name' => $data['company_name']]);
            if ($stmt->fetch()) {
                $db->rollBack();
                throw new Exception('Ez a cégnév már foglalt! Kérjük, válasszon másik cégnevet.');
            }

            // Vállalati email ellenőrzése
            $stmt = $db->prepare("SELECT id FROM company WHERE company_email = :company_email");
            $stmt->execute([':company_email' => $data['company_email']]);
            if ($stmt->fetch()) {
                $db->rollBack();
                throw new Exception('Ez a vállalati email cím már foglalt! Kérjük, válasszon másik email címet.');
            }

            // Vállalati telefon ellenőrzése
            $stmt = $db->prepare("SELECT id FROM company WHERE company_telephone = :company_telephone");
            $stmt->execute([':company_telephone' => $data['company_telephone']]);
            if ($stmt->fetch()) {
                $db->rollBack();
                throw new Exception('Ez a vállalati telefonszám már foglalt! Kérjük, válasszon másik telefonszámot.');
            }
        }

        // Felhasználói telefonszám ellenőrzése
        $stmt = $db->prepare("SELECT id FROM user WHERE telephone = :telephone");
        $stmt->execute([':telephone' => $data['telephone']]);
        if ($stmt->fetch()) {
            $db->rollBack();
            throw new Exception('Ez a telefonszám már foglalt! Kérjük, válasszon másik telefonszámot.');
        }

        // Felhasználó létrehozása
        $insertStmt = $db->prepare("
            INSERT INTO user (
                firstname, 
                lastname, 
                email, 
                telephone, 
                password, 
                profile_pic,
                created_date,
                connect_date
            ) VALUES (
                :firstname,
                :lastname,
                :email,
                :telephone,
                :password,
                'user.png',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");
        
        $params = [
            ':firstname' => $data['firstname'],
            ':lastname' => $data['lastname'],
            ':email' => $data['email'],
            ':telephone' => $data['telephone'],
            ':password' => password_hash($data['password'], PASSWORD_DEFAULT)
        ];
        
        $success = $insertStmt->execute($params);
        
        if (!$success) {
            throw new Exception('Sikertelen felhasználó létrehozás');
        }

        $userId = $db->lastInsertId();

        // Adjuk hozzá a szerepkört a user_to_roles táblába
        $roleStmt = $db->prepare("
            INSERT INTO user_to_roles (user_id, role_id) 
            VALUES (:user_id, 1)
        ");
        $roleStmt->execute([':user_id' => $userId]);

        // Cookie beállítások létrehozása - módosított verzió
        $cookieStmt = $db->prepare("
            INSERT INTO cookies (acceptedornot) 
            VALUES (false)
        ");
        $cookieStmt->execute();
        $cookieId = $db->lastInsertId();

        // Frissítsük a user rekordot a cookie_id-val
        $updateUserStmt = $db->prepare("
            UPDATE user 
            SET cookie_id = :cookie_id 
            WHERE id = :user_id
        ");
        $updateUserStmt->execute([
            ':cookie_id' => $cookieId,
            ':user_id' => $userId
        ]);

        // Ha van cégnév, akkor létrehozzuk a céget is
        if (!empty($data['company_name'])) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO company (
                        company_name, 
                        company_address, 
                        company_email, 
                        company_telephone
                    ) VALUES (
                        :company_name,
                        :company_address,
                        :company_email,
                        :company_telephone
                    )
                ");
                
                $stmt->execute([
                    ':company_name' => $data['company_name'],
                    ':company_address' => $data['company_address'],
                    ':company_email' => $data['company_email'],
                    ':company_telephone' => $data['company_telephone']
                ]);
                
                $companyId = $db->lastInsertId();
                
                // Frissítjük a felhasználó company_id mezőjét
                $stmt = $db->prepare("UPDATE user SET company_id = :company_id WHERE id = :user_id");
                $stmt->execute([
                    ':company_id' => $companyId,
                    ':user_id' => $userId
                ]);
            } catch (PDOException $e) {
                throw new Exception('A cég létrehozása sikertelen: ' . $e->getMessage());
            }
        }

        // Tranzakció véglegesítése
        $db->commit();
        return true;

    } catch (PDOException $e) {
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        if ($e->getCode() == '23000') {
            if (strpos($e->getMessage(), 'company_name')) {
                throw new Exception('Ez a cégnév már foglalt! Kérjük, válasszon másik cégnevet.');
            } else {
                throw new Exception('Adatbázis hiba történt: ' . $e->getMessage());
            }
        }
        throw new Exception('Adatbázis hiba történt: ' . $e->getMessage());
    } catch (Exception $e) {
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function loginUser($email, $password) {
    try {
        $db = DatabaseConnection::getInstance()->getConnection();
        
        // Lekérjük a felhasználó adatait és a szerepköröket
        $stmt = $db->prepare("
            SELECT 
                u.*,
                GROUP_CONCAT(DISTINCT r.role_name) as roles,
                CONCAT(u.firstname, ' ', u.lastname) as full_name
            FROM user u
            LEFT JOIN user_to_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.email = :email
            GROUP BY u.id
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Cég előfizetés státusz ellenőrzése
            if (!empty($user['company_id'])) {
                $companyId = $user['company_id'];
                $subStmt = $db->prepare("SELECT subscription_status_id FROM subscriptions WHERE company_id = :company_id ORDER BY created_at DESC LIMIT 1");
                $subStmt->execute([':company_id' => $companyId]);
                $sub = $subStmt->fetch(PDO::FETCH_ASSOC);
                if ($sub && $sub['subscription_status_id'] == 2) {
                    // 2 = lemondott
                    return [
                        'success' => false,
                        'error' => 'A cég előfizetése lemondott, amíg nincs aktív csomag, nem lehet belépni.'
                    ];
                }
            }
            // Beállítjuk a session változókat
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_role'] = $user['roles'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['language'] = $user['language'] ?? 'hu'; // Alapértelmezett nyelv ha nincs beállítva
            $_SESSION['last_activity'] = time();
            // Frissítjük a bejelentkezés idejét
            $updateStmt = $db->prepare("UPDATE user SET connect_date = NOW() WHERE id = :id");
            $updateStmt->execute([':id' => $user['id']]);
            return ['success' => true];
        }
        return ['success' => false];
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false];
    }
}

function getUserData($userId) {
    global $conn;
    
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Módosítás: először a vezetéknév, aztán a keresztnév
        $user['full_name'] = $user['lastname'] . ' ' . $user['firstname'];
        return $user;
    }
    return null;
}

function generateQRCode($type_id, $secondtype_id, $brand_id, $model_id) {
    try {
        $db = DatabaseConnection::getInstance()->getConnection();
        
        // Típus első betűje
        $stmt = $db->prepare("SELECT UPPER(LEFT(name, 1)) as letter FROM stuff_type WHERE id = ?");
        $stmt->execute([$type_id]);
        $type_letter = $stmt->fetchColumn() ?: 'X';
        
        // Altípus első betűje
        $stmt = $db->prepare("SELECT UPPER(LEFT(name, 1)) as letter FROM stuff_secondtype WHERE id = ?");
        $stmt->execute([$secondtype_id]);
        $secondtype_letter = $stmt->fetchColumn() ?: 'X';
        
        // Márka első betűje
        $stmt = $db->prepare("SELECT UPPER(LEFT(name, 1)) as letter FROM stuff_brand WHERE id = ?");
        $stmt->execute([$brand_id]);
        $brand_letter = $stmt->fetchColumn() ?: 'X';
        
        // Modell első betűje
        $stmt = $db->prepare("SELECT UPPER(LEFT(name, 1)) as letter FROM stuff_model WHERE id = ?");
        $stmt->execute([$model_id]);
        $model_letter = $stmt->fetchColumn() ?: 'X';
        
        // Generálunk egy egyedi azonosítót
        $unique = strtoupper(substr(uniqid(), -6));
        
        // Összeállítjuk a QR kódot: QR-TAMB-XXXXXX-1234
        $qr_code = sprintf("QR-%s%s%s%s-%s-%s",
            $type_letter,
            $secondtype_letter,
            $brand_letter,
            $model_letter,
            $unique,
            rand(1000, 9999)
        );
        
        return $qr_code;
        
    } catch (PDOException $e) {
        error_log("Hiba a QR kód generálása során: " . $e->getMessage());
        throw new Exception("Hiba történt a QR kód generálása során.");
    }
}

function sendEmail($to, $subject, $message) {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Szerver beállítások
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';  // Gmail SMTP szerver
        $mail->SMTPAuth = true;
        $mail->Username = 'kurinczjozsef@gmail.com'; // Gmail email cím
        $mail->Password = 'qtmayweajrtybnck';    // Gmail alkalmazás jelszó
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        // Címzettek
        $mail->setFrom('kurinczjozsef@gmail.com', 'VibeCore');
        $mail->addAddress($to);

        // Tartalom
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email küldési hiba: {$mail->ErrorInfo}");
        return false;
    }
}