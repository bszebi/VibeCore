<?php
session_start();

// Ha be van jelentkezve, átirányítjuk a dashboard-ra
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/index.php');
    exit;
}

// Ha nincs bejelentkezve, átirányítjuk a home oldalra
header('Location: home.php');
exit; 