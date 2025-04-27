<?php
// A regisztrációs oldal tetején
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'home.php';

// A sikeres regisztráció után
if ($registration_successful) {
    // Átirányítás a megfelelő oldalra
    header("Location: " . $redirect);
    exit;
} 