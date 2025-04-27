<?php
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

$error = '';
$success = '';
$attempts = isset($_SESSION['verification_attempts']) ? $_SESSION['verification_attempts'] : 0;

// Ha nincs user_id a session-ben, visszairányítjuk a regisztrációs oldalra
if (!isset($_SESSION['temp_user_id'])) {
    header('Location: register.php');
    exit;
}

// Kód újraküldése
if (isset($_POST['resend_code'])) {
    try {
        // Régi kód érvénytelenítése
        $stmt = $db->prepare("UPDATE email_verification_codes SET is_verified = 1 WHERE user_id = ? AND is_verified = 0");
        $stmt->execute([$_SESSION['temp_user_id']]);
        
        // Új kód generálása és mentése
        $verification_code = sprintf("%06d", mt_rand(0, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $db->prepare("INSERT INTO email_verification_codes (user_id, verification_code, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['temp_user_id'], $verification_code, $expires_at]);
        
        // Email küldése az új kóddal
        $stmt = $db->prepare("SELECT email, lastname, firstname FROM user WHERE id = ?");
        $stmt->execute([$_SESSION['temp_user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Email küldése HTML formátumban
            $to = $user['email'];
            $subject = "Új verifikációs kód - VibeCore";
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
                    </style>
                </head>
                <body>
                    <div class='email-container'>
                        <div class='header'>
                            <img src='../admin/VIBECORE.PNG' alt='VibeCore Logo' style='max-width: 200px; height: auto;'>
                            <h2>Új Verifikációs Kód</h2>
                        </div>
                        <div class='content'>
                            <p>Kedves " . $user['lastname'] . " " . $user['firstname'] . "!</p>
                            
                            <p>Az új verifikációs kódod megérkezett. Kérjük, használd az alábbi kódot az email címed megerősítéséhez:</p>
                            
                            <div class='verification-code'>" . $verification_code . "</div>
                            
                            <div class='info'>
                                <strong>Fontos:</strong><br>
                                • Az új verifikációs kód 24 óráig érvényes<br>
                                • A kódot ne oszd meg senkivel<br>
                                • Három próbálkozási lehetőséged van
                            </div>
                            
                            <p>Ha nem te kérted az új kódot, kérjük, hagyd figyelmen kívül ezt az emailt és jelentkezz be a fiókodba a biztonság érdekében.</p>
                        </div>
                        <div class='footer'>
                            <p>Ez egy automatikus üzenet, kérjük, ne válaszolj rá.</p>
                            <p>&copy; " . date('Y') . " VibeCore. Minden jog fenntartva.</p>
                        </div>
                    </div>
                </body>
                </html>";
            
            if (sendEmail($to, $subject, $message)) {
                $success = 'Új verifikációs kód elküldve!';
                $_SESSION['verification_attempts'] = 0;
            } else {
                $error = 'Hiba történt az email küldése során. Kérjük, próbálja újra később.';
            }
        }
    } catch (Exception $e) {
        $error = 'Hiba történt az új kód küldése során: ' . $e->getMessage();
    }
}

// Kód ellenőrzése
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verification_code'])) {
    if ($attempts >= 3) {
        $error = 'Túl sok sikertelen próbálkozás. Kérjen új kódot!';
    } else {
        try {
            $verification_code = $_POST['verification_code'];
            
            $stmt = $db->prepare("
                SELECT * FROM email_verification_codes 
                WHERE user_id = ? 
                AND verification_code = ? 
                AND expires_at > NOW() 
                AND is_verified = 0 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['temp_user_id'], $verification_code]);
            $code = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($code) {
                // Kód érvényesítése
                $stmt = $db->prepare("UPDATE email_verification_codes SET is_verified = 1 WHERE id = ?");
                $stmt->execute([$code['id']]);
                
                // Felhasználó verifikálása
                $stmt = $db->prepare("UPDATE user SET is_email_verified = 1 WHERE id = ?");
                $stmt->execute([$_SESSION['temp_user_id']]);
                
                // Session törlése
                unset($_SESSION['temp_user_id']);
                unset($_SESSION['verification_attempts']);
                
                // Átirányítás sikeres üzenettel
                header('Location: login.php?registration=success');
                exit;
            } else {
                $error = 'Érvénytelen vagy lejárt kód!';
                $_SESSION['verification_attempts'] = ++$attempts;
            }
        } catch (Exception $e) {
            $error = 'Hiba történt a kód ellenőrzése során: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verifikáció - VibeCore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .verification-container {
            background: white;
            padding: 3rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
            margin: 20px;
        }

        .verification-title {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
        }

        .verification-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .code-input {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin: 2rem 0;
            padding: 0 2rem;
        }

        .code-input input {
            width: 3.5rem;
            height: 3.5rem;
            text-align: center;
            font-size: 2rem;
            font-weight: 600;
            border: 2px solid #ddd;
            border-radius: 8px;
            outline: none;
            color: #2c3e50;
            background-color: #ffffff;
            transition: all 0.3s ease;
            caret-color: #3498db;
            appearance: textfield;
            -webkit-appearance: textfield;
            -moz-appearance: textfield;
        }

        .code-input input:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .code-input input:not(:placeholder-shown) {
            border-color: #3498db;
            background-color: #f8f9fa;
            color: #2c3e50;
        }

        /* Webkit böngészőkben a számok elrejtésének megakadályozása */
        .code-input input::-webkit-outer-spin-button,
        .code-input input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Firefox-ban a számok elrejtésének megakadályozása */
        .code-input input[type=number] {
            -moz-appearance: textfield;
        }

        .verify-button {
            background: #3498db;
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1rem;
            transition: background-color 0.3s;
            width: 80%;
            margin: 0 auto;
        }

        .verify-button:hover {
            background: #2980b9;
        }

        .resend-button {
            background: none;
            border: none;
            color: #3498db;
            cursor: pointer;
            font-size: 0.9rem;
            margin-top: 1rem;
            text-decoration: underline;
        }

        .resend-button:hover {
            color: #2980b9;
        }

        .error-message {
            color: #e74c3c;
            background: #fdf0ed;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .success-message {
            color: #27ae60;
            background: #edfdf5;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .attempts-left {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <h2 class="verification-title">Email Verifikáció</h2>
        <p>Kérjük, írja be az emailben kapott 6 jegyű kódot</p>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" class="verification-form">
            <div class="code-input">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <input type="number" name="code_<?php echo $i; ?>" min="0" max="9" placeholder="0" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                <?php endfor; ?>
            </div>

            <button type="submit" class="verify-button">Verifikálás</button>
        </form>

        <?php if ($attempts < 3): ?>
            <p class="attempts-left">Még <?php echo 3 - $attempts; ?> próbálkozása van</p>
        <?php endif; ?>

        <form method="POST">
            <button type="submit" name="resend_code" class="resend-button">
                Új kód kérése
            </button>
        </form>
    </div>

    <script>
        // Automatikus ugrás a következő input mezőre
        document.querySelectorAll('.code-input input').forEach((input, index) => {
            input.addEventListener('input', function() {
                if (this.value.length === 1) {
                    const nextInput = document.querySelector(`input[name="code_${index + 2}"]`);
                    if (nextInput) nextInput.focus();
                }
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !this.value) {
                    const prevInput = document.querySelector(`input[name="code_${index}"]`);
                    if (prevInput) prevInput.focus();
                }
            });
        });

        // Form submit előtt összefűzzük a kódot
        document.querySelector('.verification-form').addEventListener('submit', function(e) {
            e.preventDefault();
            let code = '';
            for (let i = 1; i <= 6; i++) {
                code += document.querySelector(`input[name="code_${i}"]`).value;
            }
            
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'verification_code';
            hiddenInput.value = code;
            this.appendChild(hiddenInput);
            
            this.submit();
        });
    </script>
</body>
</html> 