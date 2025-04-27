<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Közvetlen adatbázis kapcsolat
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "vizsgaremek";

// Kapcsolat létrehozása
$conn = new mysqli($servername, $username, $password, $dbname);

// Kapcsolat ellenőrzése
if ($conn->connect_error) {
    die("Kapcsolati hiba: " . $conn->connect_error);
}

echo "<h1>Admin Logs Diagnosztika</h1>";

// Ellenőrizzük, hogy létezik-e az admin_logs tábla
$table_check = $conn->query("SHOW TABLES LIKE 'admin_logs'");
if (!$table_check || $table_check->num_rows === 0) {
    echo "<p style='color: red;'>Az admin_logs tábla NEM létezik az adatbázisban!</p>";
    
    // Tábla szerkezetet javasolunk, ha nincs
    echo "<h2>Javasolt tábla létrehozása:</h2>";
    echo "<pre>
CREATE TABLE `admin_logs` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `user_id` integer NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `table_name` varchar(255),
  `record_id` integer,
  `old_values` text,
  `new_values` text,
  `ip_address` varchar(45),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
);</pre>";
} else {
    echo "<p style='color: green;'>Az admin_logs tábla létezik az adatbázisban.</p>";
    
    // Ellenőrizzük a tábla szerkezetét
    $col_result = $conn->query("SHOW COLUMNS FROM admin_logs");
    echo "<h2>Admin Logs tábla oszlopai:</h2>";
    echo "<table border='1'><tr><th>Oszlop neve</th><th>Típus</th><th>Null</th><th>Kulcs</th><th>Alapértelmezett</th><th>Extra</th></tr>";
    
    $columns = [];
    while ($col = $col_result->fetch_assoc()) {
        $columns[] = $col['Field'];
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "<td>" . $col['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p>Talált oszlopok: " . implode(', ', $columns) . "</p>";
    
    // Ellenőrizzük, hogy milyen rekordok vannak már benne
    $log_count = $conn->query("SELECT COUNT(*) as count FROM admin_logs");
    $count_row = $log_count->fetch_assoc();
    echo "<p>Jelenlegi rekordok száma: " . $count_row['count'] . "</p>";
    
    if ($count_row['count'] > 0) {
        $recent_logs = $conn->query("SELECT * FROM admin_logs ORDER BY id DESC LIMIT 5");
        echo "<h2>Legutóbbi 5 naplóbejegyzés:</h2>";
        echo "<table border='1'><tr>";
        
        // Oszlopfejlécek
        foreach ($columns as $column) {
            echo "<th>" . $column . "</th>";
        }
        echo "</tr>";
        
        // Adatsorok
        while ($log = $recent_logs->fetch_assoc()) {
            echo "<tr>";
            foreach ($columns as $column) {
                echo "<td>" . (isset($log[$column]) ? htmlspecialchars($log[$column]) : '') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Próbálunk beilleszteni egy új bejegyzést
    echo "<h2>Teszt bejegyzés beillesztése:</h2>";
    
    if (in_array('action_type', $columns)) {
        // Új szerkezetű tábla
        echo "<p>action_type oszlop észlelve, az új szerkezetű táblát fogjuk használni.</p>";
        
        try {
            $admin_id = 1; // Feltételezzük, hogy van egy admin felhasználó 1-es ID-val
            $action_type = 'TEST';
            $table_name = 'company';
            $record_id = 1;
            $old_values = json_encode(['name' => 'Teszt cég']);
            $new_values = json_encode(['name' => 'Módosított teszt cég']);
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $current_date = date('Y-m-d H:i:s');
            
            if ($stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, table_name, record_id, old_values, new_values, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")) {
                $stmt->bind_param("isssssss", $admin_id, $action_type, $table_name, $record_id, $old_values, $new_values, $ip_address, $current_date);
                
                if ($stmt->execute()) {
                    echo "<p style='color: green;'>A teszt bejegyzés sikeresen beillesztve az új szerkezetű táblába!</p>";
                } else {
                    echo "<p style='color: red;'>Hiba történt a beillesztés során: " . $stmt->error . "</p>";
                    
                    // Megpróbáljuk közvetlenül a lekérdezést
                    $direct_query = "INSERT INTO admin_logs (admin_id, action_type, table_name, record_id, old_values, new_values, ip_address, created_at) VALUES 
                        ($admin_id, '$action_type', '$table_name', $record_id, '" . $conn->real_escape_string($old_values) . "', '" . $conn->real_escape_string($new_values) . "', '$ip_address', '$current_date')";
                    
                    if ($conn->query($direct_query)) {
                        echo "<p style='color: green;'>A közvetlen beillesztés sikerült!</p>";
                    } else {
                        echo "<p style='color: red;'>A közvetlen beillesztés is hibát adott: " . $conn->error . "</p>";
                    }
                }
                
                $stmt->close();
            } else {
                echo "<p style='color: red;'>Hiba a prepared statement létrehozásakor: " . $conn->error . "</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Kivétel: " . $e->getMessage() . "</p>";
        }
    } elseif (in_array('admin_id', $columns)) {
        // Régi szerkezetű admin_id-val
        echo "<p>admin_id oszlop észlelve, a régi szerkezetű táblát fogjuk használni.</p>";
        
        $admin_id = 1; // Feltételezzük, hogy van egy admin felhasználó 1-es ID-val
        $action = 'test_action';
        $details = "Admin #$admin_id, teszt művelet, régi érték, új érték, " . date('Y-m-d H:i:s');
        
        $query = "INSERT INTO admin_logs (admin_id, action, details) VALUES ($admin_id, '$action', '" . $conn->real_escape_string($details) . "')";
        
        if ($conn->query($query)) {
            echo "<p style='color: green;'>A teszt bejegyzés sikeresen beillesztve az admin_id szerkezetű táblába!</p>";
        } else {
            echo "<p style='color: red;'>Hiba történt a beillesztés során: " . $conn->error . "</p>";
        }
    } elseif (in_array('user_id', $columns) && in_array('action', $columns) && in_array('details', $columns)) {
        // Régi szerkezetű user_id-val
        echo "<p>user_id, action, details oszlopok észlelve, alternatív szerkezetű táblát fogjuk használni.</p>";
        
        $user_id = 1; // Feltételezzük, hogy van egy admin felhasználó 1-es ID-val
        $action = 'test_action';
        $details = "Admin #$user_id, teszt művelet, régi érték, új érték, " . date('Y-m-d H:i:s');
        
        $query = "INSERT INTO admin_logs (user_id, action, details) VALUES ($user_id, '$action', '" . $conn->real_escape_string($details) . "')";
        
        if ($conn->query($query)) {
            echo "<p style='color: green;'>A teszt bejegyzés sikeresen beillesztve a user_id szerkezetű táblába!</p>";
        } else {
            echo "<p style='color: red;'>Hiba történt a beillesztés során: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: red;'>Az admin_logs tábla nem tartalmazza sem az action_type, sem az admin_id, sem a (user_id, action, details) oszlopokat, nem lehet naplózni.</p>";
    }
}

// Kapcsolat bezárása
$conn->close();
?> 