<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Ha már be van jelentkezve, átirányítjuk
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_GET['redirect'] ?? '../dashboard/index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Email és jelszó ellenőrzése
        $stmt = $db->prepare("
            SELECT 
                u.*,
                GROUP_CONCAT(DISTINCT r.role_name) as roles,
                c.id as company_id,
                c.company_name,
                c.company_address,
                c.company_email,
                c.company_telephone
            FROM user u
            LEFT JOIN user_to_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            LEFT JOIN company c ON u.company_id = c.id
            WHERE u.email = ?
            GROUP BY u.id
        ");
        
        $stmt->execute([$_POST['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($_POST['password'], $user['password'])) {
            // Debug információ
            error_log('User roles: ' . print_r($user['roles'], true));
            
            // Ellenőrizzük, hogy a felhasználó cégtulajdonos-e
            $roles = $user['roles'] ? explode(',', $user['roles']) : [];
            
            // Debug információ
            error_log('Roles array: ' . print_r($roles, true));
            
            // Módosított szerepkör ellenőrzés
            if (!in_array('Cég tulajdonos', $roles) && !in_array('admin', $roles)) {
                throw new Exception('Csak cégtulajdonosok jelentkezhetnek be a megrendeléshez!');
            }

            // Sikeres bejelentkezés
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_role'] = $user['roles'];
            $_SESSION['company_id'] = $user['company_id'];

            // Átirányítás vissza a megrendelés oldalra
            $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '../megrendeles.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            throw new Exception('Hibás email cím vagy jelszó!');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cégtulajdonosi Bejelentkezés - VibeCore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #666;
            font-size: 14px;
        }

        .info-text {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Cégtulajdonosi Bejelentkezés</h1>
            <p>A megrendeléshez kérjük, jelentkezzen be cégtulajdonosi fiókjával</p>
        </div>

        <div class="info-text">
            <p><strong>Fontos:</strong> Ezen az oldalon csak cégtulajdonosi jogosultsággal rendelkező felhasználók tudnak bejelentkezni.</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email cím</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Jelszó</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn">Bejelentkezés</button>
        </form>

        <div class="text-center" style="margin-top: 20px;">
            <p>Még nincs fiókja? <a href="../auth/register.php">Regisztráció</a></p>
            <p><a href="../home.php">Vissza a főoldalra</a></p>
        </div>
    </div>
</body>
</html> 