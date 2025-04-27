<?php
require_once 'includes/config.php';

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Kapcsolódási hiba: " . mysqli_connect_error());
}

// Ellenőrizzük, hogy létezik-e már az is_accepted oszlop
$check_column = "SHOW COLUMNS FROM work LIKE 'is_accepted'";
$column_result = mysqli_query($conn, $check_column);

if (mysqli_num_rows($column_result) == 0) {
    // Az oszlop még nem létezik, hozzáadjuk
    $alter_table = "ALTER TABLE work ADD COLUMN is_accepted TINYINT(1) DEFAULT NULL";
    if (mysqli_query($conn, $alter_table)) {
        echo "Az is_accepted oszlop sikeresen hozzáadva a work táblához.";
    } else {
        echo "Hiba történt az oszlop hozzáadásakor: " . mysqli_error($conn);
    }
} else {
    echo "Az is_accepted oszlop már létezik a work táblában.";
}

mysqli_close($conn);
