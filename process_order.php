<?php
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ellenőrizzük a fizetési módot
    if ($_POST['fizetesi_mod'] === 'bankkartya') {
        // Átirányítás a bankkártyás fizetési oldalra
        header('Location: payment.php');
        exit;
    } 
    else if ($_POST['fizetesi_mod'] === 'banki_atutalas') {
        // Az ár átadása GET paraméterként
        $ar = isset($_POST['ar']) ? $_POST['ar'] : '';
        header('Location: bank_transfer.php?ar=' . $ar);
        exit;
    }
    // ... egyéb fizetési módok kezelése
} 