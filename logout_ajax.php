<?php
// Session indítása
session_start();

// Session változók törlése
$_SESSION = array();

// Session cookie törlése
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session megsemmisítése
session_destroy();

// Válasz küldése
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?> 