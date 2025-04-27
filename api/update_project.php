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

// Ellenőrizzük, hogy van-e ID
if (!isset($input['id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Hiányzó projekt azonosító']));
}

// Dinamikusan építjük fel az UPDATE utasítást
$updateFields = [];
$types = '';
$params = [];

// Név frissítése
if (isset($input['name'])) {
    $updateFields[] = 'name = ?';
    $types .= 's';
    $params[] = $input['name'];
}

// Dátumok frissítése
if (isset($input['start_date'])) {
    $updateFields[] = 'project_startdate = ?';
    $types .= 's';
    $params[] = $input['start_date'];
}

if (isset($input['end_date'])) {
    $updateFields[] = 'project_enddate = ?';
    $types .= 's';
    $params[] = $input['end_date'];
}

// Helyszín adatok frissítése
if (isset($input['country_id'])) {
    $updateFields[] = 'country_id = ?';
    $types .= 'i';
    $params[] = $input['country_id'];
}

if (isset($input['county_id'])) {
    $updateFields[] = 'county_id = ?';
    $types .= 'i';
    $params[] = $input['county_id'];
}

if (isset($input['city_id'])) {
    $updateFields[] = 'city_id = ?';
    $types .= 'i';
    $params[] = $input['city_id'];
}

if (isset($input['location_name'])) {
    $updateFields[] = 'location_name = ?';
    $types .= 's';
    $params[] = $input['location_name'];
}

// Ha nincs mit frissíteni
if (empty($updateFields)) {
    http_response_code(400);
    die(json_encode(['error' => 'Nincsenek frissítendő mezők']));
}

// SQL lekérdezés összeállítása
$query = "UPDATE project SET " . implode(', ', $updateFields) . " WHERE id = ?";
$types .= 'i';
$params[] = $input['id'];

$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    // Paraméterek dinamikus kötése
    mysqli_stmt_bind_param($stmt, $types, ...$params);

    // Lekérdezés végrehajtása
    if (mysqli_stmt_execute($stmt)) {
        // Frissített adatok lekérése
        $select_query = "SELECT p.*, pt.name as type_name, 
                        c.name as country_name,
                        co.name as county_name,
                        ci.name as city_name
                        FROM project p
                        LEFT JOIN project_type pt ON p.type_id = pt.id
                        LEFT JOIN countries c ON p.country_id = c.id
                        LEFT JOIN counties co ON p.county_id = co.id
                        LEFT JOIN cities ci ON p.city_id = ci.id
                        WHERE p.id = ?";
        
        $select_stmt = mysqli_prepare($conn, $select_query);
        mysqli_stmt_bind_param($select_stmt, "i", $input['id']);
        mysqli_stmt_execute($select_stmt);
        $result = mysqli_stmt_get_result($select_stmt);
        $updated_project = mysqli_fetch_assoc($result);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'project' => $updated_project
        ]);
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