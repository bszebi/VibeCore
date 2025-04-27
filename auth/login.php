<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

// Session indítása
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ha van aktív munkamenet, töröljük azt és indítsunk újat
if (isset($_SESSION['user_id'])) {
    session_destroy();
    session_start();
}

$error = '';
$success = '';

// Sikeres regisztráció üzenet kezelése
if (isset($_GET['registration']) && $_GET['registration'] === 'success') {
    $success = 'Sikeres regisztráció! Most már bejelentkezhet.';
}

// Sikeres jelszó visszaállítás üzenet kezelése
if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $success = 'Jelszavát sikeresen frissítettük! Most már bejelentkezhet az új jelszavával.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Minden mező kitöltése kötelező!';
    } else {
        // First check if it's an admin user
        $stmt = $conn->prepare("SELECT id, username, password, is_active FROM admin_users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            if (password_verify($password, $admin['password'])) {
                if ($admin['is_active']) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['is_admin'] = true;
                    
                    // AJAX kérés esetén
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        echo json_encode([
                            'success' => true,
                            'userName' => $admin['username'],
                            'isAdmin' => true,
                            'welcomeText' => 'Üdvözöljük',
                            'successText' => 'Sikeres bejelentkezés'
                        ]);
                        exit;
                    }
                    
                    header('Location: ../admin/index.html');
                    exit;
                } else {
                    $error = 'A fiók inaktív!';
                }
            } else {
                $error = 'Hibás email cím vagy jelszó!';
            }
        } else {
            // If not admin, check regular users
            $result = loginUser($email, $password);
            
            // AJAX kérés esetén
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                
                if ($result['success']) {
                    // Lekérjük a felhasználó nyelvét és nevét
                    $stmt = $conn->prepare("SELECT language, firstname, lastname FROM user WHERE id = ?");
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $langResult = $stmt->get_result();
                    $userData = $langResult->fetch_assoc();
                    
                    // Beállítjuk a nyelvet a fordításhoz
                    $userLang = $userData['language'] ?? 'hu';
                    $_SESSION['language'] = $userLang;
                    
                    // Név sorrendjének beállítása nyelv alapján
                    $displayName = '';
                    if ($userLang === 'hu') {
                        $displayName = $userData['lastname'] . ' ' . $userData['firstname'];
                    } else {
                        $displayName = $userData['firstname'] . ' ' . $userData['lastname'];
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'userName' => $displayName,
                        'isAdmin' => false,
                        'welcomeText' => translate('Üdvözöljük'),
                        'successText' => translate('Sikeres bejelentkezés')
                    ]);
                } else {
                    $errorMsg = isset($result['error']) && $result['error']
                        ? $result['error']
                        : 'Hibás email cím vagy jelszó!';
                    echo json_encode([
                        'success' => false,
                        'error' => $errorMsg
                    ]);
                }
                exit;
            }
            
            // Normál form submit esetén
            if (!$result['success']) {
                if (isset($result['error']) && $result['error']) {
                    $error = $result['error'];
                } else {
                    $error = 'Hibás email cím vagy jelszó!';
                }
            } else {
                // Ellenőrizzük, hogy a felhasználónak van-e company_id-ja
                if (!isset($_SESSION['company_id'])) {
                    try {
                        $db = Database::getInstance()->getConnection();
                        $stmt = $db->prepare("SELECT company_id, language, firstname, lastname FROM user WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($user_data && isset($user_data['company_id'])) {
                            $_SESSION['company_id'] = $user_data['company_id'];
                            
                            // Beállítjuk a nyelvet és a megfelelő névsorrendet
                            $userLang = $user_data['language'] ?? 'hu';
                            $_SESSION['language'] = $userLang;
                            
                            // Név sorrendjének beállítása nyelv alapján
                            if ($userLang === 'hu') {
                                $_SESSION['user_name'] = $user_data['lastname'] . ' ' . $user_data['firstname'];
                            } else {
                                $_SESSION['user_name'] = $user_data['firstname'] . ' ' . $user_data['lastname'];
                            }
                        } else {
                            // Ha nincs company_id, töröljük a session-t és hibaüzenetet jelenítünk meg
                            session_destroy();
                            $error = 'Hiba: A felhasználói fiók nincs hozzárendelve egy céghez!';
                            return;
                        }
                    } catch (PDOException $e) {
                        error_log("Database error during login: " . $e->getMessage());
                        session_destroy();
                        $error = 'Adatbázis hiba történt. Kérjük, próbálja újra később.';
                        return;
                    }
                }

                $_SESSION['show_welcome'] = true;
                // Átirányítás a dashboard-ra
                header('Location: ../dashboard/index.php');
                exit;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bejelentkezés</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .welcome-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0);
            backdrop-filter: blur(0);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.5s ease;
        }

        .welcome-message {
            background: white;
            padding: 2.5rem 4rem;
            border-radius: 15px;
            text-align: center;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.5s ease;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .welcome-overlay.show {
            opacity: 1;
            visibility: visible;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
        }

        .welcome-overlay.show .welcome-message {
            transform: translateY(0);
            opacity: 1;
        }

        .welcome-message h2 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .welcome-message p {
            color: #7f8c8d;
            font-size: 1.1rem;
            margin: 0;
        }

        #loginContent {
            transition: all 0.3s ease;
        }

        #loginContent.blur {
            filter: blur(5px);
        }

        /* Sikeres regisztráció üzenet stílusa */
        .success-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            animation: slideIn 0.5s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            min-width: 300px;
            max-width: 400px;
        }

        .success-notification i {
            font-size: 20px;
            color: #28a745;
        }

        @keyframes slideIn {
            from {
                transform: translateX(120%);
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
                transform: translateX(120%);
                opacity: 0;
            }
        }

        .success-notification .close-btn {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
            background: none;
            border: none;
            font-size: 20px;
            color: #155724;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }

        .success-notification .close-btn:hover {
            background-color: rgba(0,0,0,0.05);
        }

        .error-message {
            background-color: #fff5f5;
            color: #dc3545;
            padding: 12px 16px;
            border: 1px solid #ffcdd2;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-message::before {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            background-image: url('../assets/img/warning.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: #3498db;
            outline: none;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            width: 24px;
            height: 24px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.6;
            transition: opacity 0.3s ease;
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

        .form-group .password-field-wrapper {
            position: relative;
            width: 100%;
        }

        .form-group .password-field-wrapper input {
            padding-right: 45px;
            width: 100%;
            box-sizing: border-box;
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

        .forgot-password-link {
            display: inline-block;
            margin-top: 10px;
            color: #7f8c8d;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .forgot-password-link:hover {
            color: #3498db;
        }
    </style>
</head>
<body>
    <!-- Üdvözlő overlay -->
    <div class='welcome-overlay' id='welcomeOverlay'>
        <div class='welcome-message'>
            <h2><?php echo translate('Üdvözöljük') . ' ' . (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : ''); ?>!</h2>
            <p><?php echo translate('Sikeres bejelentkezés'); ?></p>
        </div>
    </div>

    <a href="../home.php" class="home-link">
        <img src="../assets/img/running.png" alt="Vissza">
        <span>Vissza a főoldalra</span>
    </a>

    <div class="container">
        <div id="loginContent">
            <h1>Bejelentkezés</h1>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-notification" id="successNotification">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                    <button class="close-btn" onclick="closeNotification()">&times;</button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Jelszó</label>
                    <div class="password-field-wrapper">
                        <input type="password" id="password" name="password" required>
                        <span class="password-toggle hide" onclick="togglePasswordVisibility('password')"></span>
                    </div>
                </div>
                
                <button type="submit" class="btn">BEJELENTKEZÉS</button>
            </form>
            
            <div class="login-link">
                Még nincs fiókja? <a href="register.php">Regisztráció</a>
                <br>
                <a href="forgot_password.php" class="forgot-password-link">Elfelejtette a jelszavát?</a>
            </div>
        </div>
    </div>
    
    <script>
    // Fordítások objektum létrehozása
    const translations = {
        welcome: '<?php echo translate('Üdvözöljük'); ?>',
        successfulLogin: '<?php echo translate('Sikeres bejelentkezés'); ?>'
    };

    // Form submit kezelése AJAX-szal
    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const overlay = document.getElementById('welcomeOverlay');
                const loginContent = document.getElementById('loginContent');
                const welcomeMessage = document.querySelector('.welcome-message h2');
                const successMessage = document.querySelector('.welcome-message p');
                
                // Az üdvözlő üzenet beállítása a visszakapott nyelven
                welcomeMessage.textContent = `${data.welcomeText} ${data.userName}!`;
                successMessage.textContent = data.successText;
                
                overlay.classList.add('show');
                loginContent.classList.add('blur');
                
                setTimeout(function() {
                    // Ellenőrizzük, hogy admin-e a felhasználó
                    if (data.isAdmin) {
                        window.location.href = '../admin/index.html';
                    } else {
                        window.location.href = '../dashboard/index.php';
                    }
                }, 2000);
            } else {
                // Hibaüzenet megjelenítése
                const errorDiv = document.querySelector('.error-message') || document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.textContent = data.error;
                
                // Ha még nincs hibaüzenet div, beszúrjuk a form elé
                if (!document.querySelector('.error-message')) {
                    const form = document.querySelector('form');
                    form.insertBefore(errorDiv, form.firstChild);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
    </script>

    <script>
        function closeNotification() {
            const notification = document.getElementById('successNotification');
            if (notification) {
                notification.style.animation = 'slideOut 0.5s ease-out forwards';
                setTimeout(() => {
                    notification.remove();
                }, 500);
            }
        }

        // Automatikus eltűnés 5 másodperc után
        if (document.getElementById('successNotification')) {
            setTimeout(() => {
                closeNotification();
            }, 5000);
        }

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
    </script>
</body>
</html>