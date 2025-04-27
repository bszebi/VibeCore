<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// JSON adatok fogadása
$input = json_decode(file_get_contents('php://input'), true);

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    http_response_code(500);
    die(json_encode(['error' => 'Kapcsolódási hiba: ' . mysqli_connect_error()]));
}

// Adatok validálása
if (!isset($input['name']) || !isset($input['type_id']) || 
    !isset($input['start_date']) || !isset($input['end_date'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Hiányzó kötelező mezők']));
}

// SQL lekérdezés előkészítése
$query = "INSERT INTO project (name, type_id, project_startdate, project_enddate, 
          country_id, county_id, city_id, location_name) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    // Értékek előkészítése
    $name = $input['name'];
    $type_id = (int)$input['type_id'];
    $start_date = $input['start_date'];
    $end_date = $input['end_date'];
    $country_id = isset($input['country_id']) ? (int)$input['country_id'] : null;
    $county_id = isset($input['county_id']) ? (int)$input['county_id'] : null;
    $city_id = isset($input['city_id']) ? (int)$input['city_id'] : null;
    $location_name = isset($input['location_name']) ? $input['location_name'] : null;

    // Paraméterek kötése
    mysqli_stmt_bind_param($stmt, 'sisssssss', 
        $name,
        $type_id,
        $start_date,
        $end_date,
        $country_id,
        $county_id,
        $city_id,
        $location_name
    );

    // Lekérdezés végrehajtása
    if (mysqli_stmt_execute($stmt)) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Hiba történt a mentés során: ' . mysqli_error($conn)]);
    }

    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Hiba történt a lekérdezés előkészítése során: ' . mysqli_error($conn)]);
}

mysqli_close($conn); 