<?php
// Először betöltjük a konfigurációt
require_once 'includes/config.php';

// Ellenőrizzük, hogy a felhasználó már be van-e jelentkezve
if (isset($_SESSION['user_id'])) {
    // Ha már be van jelentkezve, átirányítjuk a főoldalra
    header("Location: index.php");
    exit;
}

// Ellenőrizzük, hogy van-e átirányítási URL
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <!-- Head rész... -->
</head>
<body>
    <!-- Bejelentkezési űrlap... -->
    <form id="login-form" action="login_process.php" method="post">
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
        <!-- Űrlap mezők... -->
    </form>
    
    <script>
        // AJAX bejelentkezés kezelése...
    </script>
</body>
</html> 