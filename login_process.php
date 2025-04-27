<?php
// Suppress PHP errors in output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

// Elindítjuk a session-t
session_start();

// Betöltjük a konfigurációt
require_once 'includes/config.php';

// Ellenőrizzük, hogy létezik-e a kapcsolat, ha nem, létrehozzuk
if (!isset($conn) || $conn === null) {
    // Adatbázis kapcsolat létrehozása
    $db_host = "localhost";
    $db_user = "root";
    $db_password = "";
    $db_name = "vizsgaremek";

    $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
    
    // Ellenőrizzük a kapcsolatot
    if ($conn->connect_error) {
        $response = [
            'success' => false,
            'message' => 'Adatbázis kapcsolódási hiba. Kérjük, próbálja újra később.'
        ];
        
        // Tiszta output buffer a JSON előtt
        ob_clean();
        
        // Visszaadjuk a választ JSON formátumban
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Karakterkódolás beállítása
    $conn->set_charset("utf8");
}

// Alapértelmezett válasz
$response = [
    'success' => false,
    'message' => 'Ismeretlen hiba történt.'
];

// Ellenőrizzük, hogy POST kérés-e
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Bekérjük az adatokat
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'index.php';

        // Validáljuk az adatokat
        if (empty($email) || empty($password)) {
            $response['message'] = 'Kérjük, adja meg az email címet és jelszót.';
        } else {
            // Ellenőrizzük, hogy létezik-e a felhasználó és lekérjük a szerepköröket is
            $query = "SELECT u.*, GROUP_CONCAT(DISTINCT r.role_name) as roles 
                      FROM user u 
                      LEFT JOIN user_to_roles ur ON u.id = ur.user_id 
                      LEFT JOIN roles r ON ur.role_id = r.id 
                      WHERE u.email = ? 
                      GROUP BY u.id";
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Adatbázis hiba: " . $conn->error);
            }
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Jelszó ellenőrzés
                if (password_verify($password, $user['password'])) {
                    // Ellenőrizzük a szerepköröket
                    $roles = explode(',', $user['roles']);
                    $is_company_owner = in_array('Cég tulajdonos', $roles);
                    
                    if ($is_company_owner) {
                        // Töröljük a régi session-t
                        if (session_status() === PHP_SESSION_ACTIVE) {
                            session_destroy();
                        }
                        
                        // Indítunk egy új session-t
                        session_start();
                        
                        // Beállítjuk a session változókat
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['user_role'] = $user['roles'];
                        $_SESSION['company_id'] = $user['company_id'];
                        $_SESSION['language'] = $user['language'] ?? 'hu'; // Alapértelmezett nyelv ha nincs beállítva
                        $_SESSION['last_activity'] = time();
                        
                        // Frissítjük a bejelentkezés idejét
                        $update_query = "UPDATE user SET connect_date = NOW() WHERE id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bind_param("i", $user['id']);
                        $update_stmt->execute();
                        
                        $response['success'] = true;
                        $response['message'] = 'Sikeres bejelentkezés!';
                        $response['redirect'] = $redirect;
                    } else {
                        $response['message'] = 'Csak cégtulajdonosi fiókkal tud megrendelést leadni.';
                    }
                } else {
                    $response['message'] = 'Hibás e-mail cím vagy jelszó.';
                }
            } else {
                $response['message'] = 'Ilyen felhasználó nem létezik. Kérjük regisztráljon!';
            }
        }
    } catch (Exception $e) {
        $response['message'] = 'Hiba történt a bejelentkezés során: ' . $e->getMessage();
    }
}

// Tiszta output buffer a JSON előtt
ob_clean();

// Visszaadjuk a választ JSON formátumban
header('Content-Type: application/json');
echo json_encode($response);
exit; 