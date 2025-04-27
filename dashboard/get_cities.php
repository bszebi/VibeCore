<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die(json_encode(['error' => "Kapcsolódási hiba: " . mysqli_connect_error()]));
}

// Debug: Kiírjuk a bejövő paramétereket
error_log('GET paraméterek: ' . print_r($_GET, true));

if (isset($_GET['district_id'])) {
    // Ha van district_id, akkor kerület alapján keresünk
    $district_id = (int)$_GET['district_id'];
    $query = "SELECT c.id, c.name 
              FROM cities c
              WHERE c.county_id = (
                  SELECT county_id 
                  FROM districts 
                  WHERE id = ?
              )
              AND c.county_id IN (
                  SELECT co.id 
                  FROM counties co
                  INNER JOIN countries ct ON co.country_id = ct.id
                  WHERE ct.has_districts = 1
              )";
    $param = $district_id;
    
    error_log('Kerület alapján keresés SQL: ' . $query . ' [district_id=' . $district_id . ']');
} elseif (isset($_GET['county_id'])) {
    // Ha nincs district_id, de van county_id, akkor először ellenőrizzük, hogy az adott megye szlovák vagy magyar-e
    $county_id = (int)$_GET['county_id'];
    $query = "SELECT c.id, c.name 
              FROM cities c
              WHERE c.county_id = ?
              AND c.county_id IN (
                  SELECT co.id 
                  FROM counties co
                  INNER JOIN countries ct ON co.country_id = ct.id
                  WHERE ct.has_districts = 0
              )
              ORDER BY c.name";
    $param = $county_id;
    
    error_log('Megye alapján keresés SQL: ' . $query . ' [county_id=' . $county_id . ']');
} else {
    die(json_encode(['error' => 'Hiányzó district_id vagy county_id paraméter']));
}

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $param);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$cities = [];
while ($row = mysqli_fetch_assoc($result)) {
    $cities[] = $row;
}

error_log('Talált városok: ' . print_r($cities, true));

echo json_encode($cities);

mysqli_close($conn); 