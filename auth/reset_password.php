<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Adatbázis kapcsolat létrehozása
$db = Database::getInstance();
$conn = $db->getConnection();

// Session indítása
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';
$token = '';
$validToken = false;
$email = '';
$firstname = '';
$lastname = '';
$userId = '';

// Token ellenőrzése
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        // Token ellenőrzése az adatbázisban
        $stmt = $conn->prepare("
            SELECT t.user_id, t.expires_at, u.id, u.firstname, u.lastname, u.email
            FROM password_reset_tokens t
            JOIN user u ON t.user_id = u.id 
            WHERE t.token = ? 
            AND t.expires_at > NOW() 
            AND t.used = 0
        ");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tokenData) {
            $validToken = true;
            $email = $tokenData['email'];
            $firstname = $tokenData['firstname'];
            $lastname = $tokenData['lastname'];
            $userId = $tokenData['id'];
        } else {
            $error = 'Érvénytelen vagy lejárt token. Kérjük, kérjen új jelszó-visszaállítási linket.';
        }
        
    } catch (Exception $e) {
        $error = 'Hiba történt a token ellenőrzésekor. Kérjük, próbálja újra később.';
    }
} else {
    $error = 'Hiányzó token. Kérjük, használja az emailben kapott linket.';
}

// Jelszó visszaállítása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Jelszó ellenőrzése
    if (empty($password) || empty($password_confirm)) {
        $error = 'Minden mező kitöltése kötelező!';
    } else if ($password !== $password_confirm) {
        $error = 'A megadott jelszavak nem egyeznek!';
    } else if (strlen($password) < 8) {
        $error = 'A jelszónak legalább 8 karakter hosszúnak kell lennie!';
    } else if (!preg_match('/[A-Z]/', $password)) {
        $error = 'A jelszónak tartalmaznia kell legalább egy nagybetűt!';
    } else if (!preg_match('/[a-z]/', $password)) {
        $error = 'A jelszónak tartalmaznia kell legalább egy kisbetűt!';
    } else if (!preg_match('/[0-9]/', $password)) {
        $error = 'A jelszónak tartalmaznia kell legalább egy számot!';
    } else {
        try {
            // Tranzakció indítása
            $conn->beginTransaction();
            
            // Jelszó frissítése
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE user SET password = ? WHERE id = ?");
            $success = $stmt->execute([$hashedPassword, $userId]);
            
            if ($success) {
                // Token érvénytelenítése
                $stmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
                $stmt->execute([$token]);
                
                // Tranzakció véglegesítése
                $conn->commit();
                
                $success = 'A jelszó sikeresen frissítve! Most már bejelentkezhet új jelszavával.';
                
                // Átirányítás a login oldalra 3 másodperc múlva
                header("Refresh: 3; URL=login.php?reset=success");
                
            } else {
                $conn->rollBack();
                $error = 'Nem sikerült frissíteni a jelszót. Kérjük, próbálja újra.';
            }
            
        } catch (Exception $e) {
            // Tranzakció visszavonása hiba esetén
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = 'Hiba történt a jelszó frissítésekor. Kérjük, próbálja újra később.';
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
        .reset-password-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            padding: 20px;
        }

        .reset-password-container {
            background: white;
            width: 100%;
            max-width: 500px;
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

        .user-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }

        .user-info p {
            margin: 5px 0;
            color: #2c3e50;
        }

        .password-requirements {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 8px;
            padding-left: 20px;
        }

        .password-requirements li {
            margin-bottom: 5px;
        }

        .password-requirements li.valid {
            color: #28a745;
        }

        .password-requirements li.invalid {
            color: #dc3545;
        }

        .password-field-wrapper {
            position: relative;
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            width: 20px;
            height: 20px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.6;
            transition: opacity 0.3s ease;
            z-index: 1;
        }

        .password-toggle:hover {
            opacity: 1;
        }

        .password-toggle.hide {
            background-image: url('../assets/img/hide.png');
        }

        .password-toggle.view {
            background-image: url('../assets/img/view.png');
        }

        .form-group input[type="password"],
        .form-group input[type="text"] {
            padding-right: 40px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header2.php'; ?>

    <div class="reset-password-page">
        <div class="reset-password-container">
            <h1>Jelszó visszaállítása</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($validToken): ?>
                <div class="user-info">
                    <p><strong>Név:</strong> <?php echo htmlspecialchars($lastname . ' ' . $firstname); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                </div>
                
                <p>Kérjük, adjon meg egy új jelszót a fiókjához.</p>
                
                <form method="POST" action="" id="passwordResetForm">
                    <div class="form-group">
                        <label for="password">Új jelszó</label>
                        <div class="password-field-wrapper">
                            <input type="password" id="password" name="password" required>
                            <div class="password-toggle hide" onclick="togglePasswordVisibility('password')"></div>
                        </div>
                        <ul class="password-requirements">
                            <li id="length-check">Legalább 8 karakter</li>
                            <li id="uppercase-check">Legalább egy nagybetű</li>
                            <li id="lowercase-check">Legalább egy kisbetű</li>
                            <li id="number-check">Legalább egy szám</li>
                        </ul>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm">Új jelszó megerősítése</label>
                        <div class="password-field-wrapper">
                            <input type="password" id="password_confirm" name="password_confirm" required>
                            <div class="password-toggle hide" onclick="togglePasswordVisibility('password_confirm')"></div>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-button">Jelszó mentése</button>
                </form>
            <?php else: ?>
                <div class="alert alert-error">
                    A jelszó visszaállítási link érvénytelen vagy lejárt. Kérjük, kérjen új linket.
                </div>
                <div class="back-to-login">
                    <a href="forgot_password.php">Vissza a jelszó-visszaállítás kéréshez</a>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
                <div class="back-to-login">
                    <a href="login.php">Vissza a bejelentkezéshez</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer2.php'; ?>

    <script>
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

        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const lengthCheck = document.getElementById('length-check');
            const uppercaseCheck = document.getElementById('uppercase-check');
            const lowercaseCheck = document.getElementById('lowercase-check');
            const numberCheck = document.getElementById('number-check');
            
            // Hossz ellenőrzése
            if (password.length >= 8) {
                lengthCheck.classList.add('valid');
                lengthCheck.classList.remove('invalid');
            } else {
                lengthCheck.classList.add('invalid');
                lengthCheck.classList.remove('valid');
            }
            
            // Nagybetű ellenőrzése
            if (/[A-Z]/.test(password)) {
                uppercaseCheck.classList.add('valid');
                uppercaseCheck.classList.remove('invalid');
            } else {
                uppercaseCheck.classList.add('invalid');
                uppercaseCheck.classList.remove('valid');
            }
            
            // Kisbetű ellenőrzése
            if (/[a-z]/.test(password)) {
                lowercaseCheck.classList.add('valid');
                lowercaseCheck.classList.remove('invalid');
            } else {
                lowercaseCheck.classList.add('invalid');
                lowercaseCheck.classList.remove('valid');
            }
            
            // Szám ellenőrzése
            if (/[0-9]/.test(password)) {
                numberCheck.classList.add('valid');
                numberCheck.classList.remove('invalid');
            } else {
                numberCheck.classList.add('invalid');
                numberCheck.classList.remove('valid');
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirm').value;
            const submitBtn = document.querySelector('.submit-button');
            
            if (password === confirm && password.length > 0) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        // Event listeners
        document.getElementById('password').addEventListener('input', checkPasswordStrength);
        document.getElementById('password_confirm').addEventListener('input', checkPasswordMatch);
        
        // Kezdeti ellenőrzés
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            if (passwordField) {
                passwordField.focus();
                checkPasswordStrength();
            }
        });
    </script>
</body>
</html> 