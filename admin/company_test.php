<?php
// Egyszerű teszt a company tábla ellenőrzésére
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

echo "<h1>Company tábla teszt</h1>";

// Kapcsolat ellenőrzése
if (!$conn) {
    die("Kapcsolódási hiba: " . mysqli_connect_error());
}
echo "<p>Adatbázis kapcsolat OK</p>";

// Ellenőrizzük a táblát
$table_check = $conn->query("SHOW TABLES LIKE 'company'");
if ($table_check->num_rows === 0) {
    die("<p>A 'company' tábla nem létezik!</p>");
}
echo "<p>A 'company' tábla létezik</p>";

// Tábla struktúra
echo "<h2>Tábla struktúra:</h2>";
$structure = $conn->query("DESCRIBE company");
echo "<table border='1'><tr><th>Mező</th><th>Típus</th><th>Null</th><th>Kulcs</th><th>Alapértelmezett</th><th>Extra</th></tr>";
while ($row = $structure->fetch_assoc()) {
    echo "<tr>
        <td>{$row['Field']}</td>
        <td>{$row['Type']}</td>
        <td>{$row['Null']}</td>
        <td>{$row['Key']}</td>
        <td>{$row['Default']}</td>
        <td>{$row['Extra']}</td>
    </tr>";
}
echo "</table>";

// Jogosultságok ellenőrzése
echo "<h2>Felhasználói jogosultságok:</h2>";
$grants = $conn->query("SHOW GRANTS FOR CURRENT_USER()");
echo "<ul>";
while ($row = $grants->fetch_row()) {
    echo "<li>" . htmlspecialchars($row[0]) . "</li>";
}
echo "</ul>";

// Teszt frissítés
echo "<h2>Teszt frissítés:</h2>";
try {
    // Lekérjük egy cég aktuális adatait
    $select = $conn->query("SELECT * FROM company LIMIT 1");
    if ($select->num_rows == 0) {
        echo "<p>Nincs cég a táblában.</p>";
    } else {
        $company = $select->fetch_assoc();
        echo "<p>Kiválasztott cég: ID=" . $company['id'] . ", Név=" . $company['company_name'] . "</p>";
        
        // Próbáljunk egy UPDATE-et
        $test_update = $conn->prepare("UPDATE company SET company_name = ? WHERE id = ?");
        $test_name = $company['company_name'] . " (Teszt)";
        $test_update->bind_param("si", $test_name, $company['id']);
        
        if ($test_update->execute()) {
            echo "<p>Teszt UPDATE OK: " . $test_update->affected_rows . " sor módosítva</p>";
            
            // Visszaállítjuk az eredeti értéket
            $restore = $conn->prepare("UPDATE company SET company_name = ? WHERE id = ?");
            $restore->bind_param("si", $company['company_name'], $company['id']);
            $restore->execute();
            
            if ($restore->affected_rows > 0) {
                echo "<p>Visszaállítás OK</p>";
            } else {
                echo "<p>Visszaállítás sikertelen</p>";
            }
        } else {
            echo "<p>Teszt UPDATE HIBA: " . $test_update->error . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p>Hiba: " . $e->getMessage() . "</p>";
}

// Az aktuális adatok listázása
echo "<h2>Aktuális company adatok:</h2>";
$companies = $conn->query("SELECT * FROM company ORDER BY id LIMIT 10");
echo "<table border='1'><tr><th>ID</th><th>Név</th><th>Cím</th><th>Email</th><th>Telefon</th><th>Létrehozva</th></tr>";
while ($row = $companies->fetch_assoc()) {
    echo "<tr>
        <td>{$row['id']}</td>
        <td>{$row['company_name']}</td>
        <td>{$row['company_address']}</td>
        <td>{$row['company_email']}</td>
        <td>{$row['company_telephone']}</td>
        <td>{$row['created_at']}</td>
    </tr>";
}
echo "</table>";
?> 