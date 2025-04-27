<?php
session_start();

// Set the content type to JSON
header('Content-Type: application/json');

// Session időkorlát (3599 másodperc = 59 perc 59 másodperc)
$session_timeout = 3599;
// Figyelmeztető időkorlát (utolsó 1 perc)
$warning_timeout = 60;

// Check if the session is valid and active
function get_session_info() {
    global $session_timeout, $warning_timeout;
    
    if (isset($_SESSION['admin_id']) && isset($_SESSION['last_activity'])) {
        $current_time = time();
        $last_activity = $_SESSION['last_activity'];
        $time_elapsed = $current_time - $last_activity;
        $time_remaining = $session_timeout - $time_elapsed;
        
        if ($time_elapsed > $session_timeout) {
            // Session lejárt
            session_destroy();
            return [
                'status' => 'expired',
                'message' => 'Your session has expired.'
            ];
        } else {
            // Csak akkor frissítjük az aktivitást, ha valódi felhasználói interakció történt
            if (isset($_GET['update_activity']) && $_GET['update_activity'] === 'true') {
                $_SESSION['last_activity'] = time();
            }
            
            return [
                'status' => 'active',
                'time_remaining' => $time_remaining,
                'warning_needed' => $time_remaining <= $warning_timeout,
                'minutes' => floor($time_remaining / 60),
                'seconds' => $time_remaining % 60,
                'message' => 'Session is active'
            ];
        }
    } else {
        return [
            'status' => 'invalid',
            'message' => 'No valid session found'
        ];
    }
}

// Get session information
$session_info = get_session_info();

// Direct access check to decide if we output JSON or redirect
if ($session_info['status'] === 'invalid' || $session_info['status'] === 'expired') {
    if (basename($_SERVER['PHP_SELF']) == "check_session.php") {
        // Return JSON response when directly accessed
        echo json_encode($session_info);
    } else {
        // Redirect to login page when included in another file
        header('Location: ../login.php');
        exit;
    }
} else {
    // Valid session, return JSON data
    echo json_encode($session_info);
}
