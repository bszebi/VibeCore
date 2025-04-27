<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../email/phpmailer/src/Exception.php';
require_once '../email/phpmailer/src/PHPMailer.php';
require_once '../email/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Adatbázis kapcsolat létrehozása
$db = Database::getInstance();
$conn = $db->getConnection();

// Ellenőrizzük, hogy van-e már aktív session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ha már be van jelentkezve, átirányítjuk a főoldalra
if (isset($_SESSION['user_id'])) {
    header('Location: ../home.php');
    exit;
}

// Ha POST kérés érkezett
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Érvénytelen email cím formátum!";
    } else {
        // Ellenőrizzük, hogy létezik-e a felhasználó
        $stmt = $conn->prepare("SELECT id, firstname, lastname FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Generálunk egy egyedi tokent
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Mentsük el a tokent az adatbázisba
            $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at, created_at, used) VALUES (?, ?, ?, NOW(), 0)");
            $stmt->execute([$user['id'], $token, $expires]);
            
            // Email küldése PHPMailer használatával
            $mail = new PHPMailer(true);
            
            try {
                // SMTP beállítások
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'kurinczjozsef@gmail.com'; // Gmail felhasználónév
                $mail->Password = 'yxsyntnvrwvezode'; // Gmail alkalmazás jelszó
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->CharSet = 'UTF-8';

                // Feladó és címzett beállítása
                $mail->setFrom('kurinczjozsef@gmail.com', 'VibeCore');
                $mail->addAddress($email, $user['firstname'] . ' ' . $user['lastname']);

                // Email tartalom
                $mail->isHTML(true);
                $mail->Subject = 'Jelszó visszaállítás - VibeCore';
                
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/Vizsga_oldal/auth/reset_password.php?token=" . $token;
                
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #2c3e50;'>Jelszó visszaállítás</h2>
                        <p>Kedves {$user['firstname']} {$user['lastname']}!</p>
                        <p>A jelszava visszaállításához kattintson az alábbi gombra:</p>
                        <p style='text-align: center;'>
                            <a href='{$reset_link}' 
                               style='background-color: #3498db; 
                                      color: white; 
                                      padding: 12px 25px; 
                                      text-decoration: none; 
                                      border-radius: 5px; 
                                      display: inline-block;'>
                                Jelszó visszaállítása
                            </a>
                        </p>
                        <p>A link egy óráig érvényes.</p>
                        <p>Ha nem Ön kérte a jelszó visszaállítását, kérjük, hagyja figyelmen kívül ezt az emailt.</p>
                        <hr style='border: 1px solid #eee; margin: 20px 0;'>
                        <p style='color: #666; font-size: 12px;'>
                            Üdvözlettel,<br>
                            VibeCore Csapat
                        </p>
                    </div>
                ";

                $mail->send();
                $success = "A jelszó visszaállítási link elküldve az email címére!";
            } catch (Exception $e) {
                error_log("Email küldési hiba: " . $mail->ErrorInfo);
                $error = "Hiba történt az email küldése során. Kérjük, próbálja újra később.";
            }
        } else {
            // Biztonsági okokból ugyanazt az üzenetet jelenítjük meg
            $success = "A jelszó visszaállítási link elküldve az email címére!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jelszó visszaállítása - VibeCore</title>
    <link rel="stylesheet" href="../assets/css/home.css">
    <style>
        .forgot-password-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            padding: 20px;
        }

        .forgot-password-container {
            background: white;
            width: 100%;
            max-width: 400px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
        }

        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 24px;
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
        }

        input:focus {
            outline: none;
            border-color: #3498db;
        }

        .submit-button {
            width: 100%;
            padding: 15px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .submit-button:hover {
            background: #2980b9;
        }

        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }

        .back-to-login a {
            color: #3498db;
            text-decoration: none;
        }

        .back-to-login a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php include '../includes/header2.php'; ?>

    <div class="forgot-password-page">
        <div class="forgot-password-container">
            <h1>Jelszó visszaállítása</h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email cím</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="Adja meg az email címét">
                </div>

                <button type="submit" class="submit-button">Jelszó visszaállítása</button>
            </form>

            <div class="back-to-login">
                <a href="login.php">Vissza a bejelentkezéshez</a>
            </div>
        </div>
    </div>

    <?php include '../includes/footer2.php'; ?>
</body>
</html> 