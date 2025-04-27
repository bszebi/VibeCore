<?php
// First start the session
session_start();

// Now include database
require_once '../includes/database.php';

// Check if the session is valid
$valid_session = false;
if (isset($_SESSION['admin_id']) && isset($_SESSION['last_activity'])) {
    $current_time = time();
    $last_activity = $_SESSION['last_activity'];
    $session_timeout = 3599;
    
    if (($current_time - $last_activity) <= $session_timeout) {
        // Session is valid
        $_SESSION['last_activity'] = time(); // Update last activity time
        $valid_session = true;
    }
}

if (!$valid_session) {
    // If called via AJAX, return JSON error
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Session expired or invalid'
    ]);
    exit;
}

// Clear any previous output and set proper headers
ob_clean();
header('Content-Type: application/json');

try {
    // Lekérjük az aktív előfizetéseket csomagonként
    $db = DatabaseConnection::getInstance();
    $conn = $db->getConnection();
    
    $query = "SELECT 
             CASE sp.name
                WHEN 'free-trial' THEN 'Ingyenes próba verzió'
                WHEN 'alap' THEN 'Alap - Havi'
                WHEN 'alap_eves' THEN 'Alap - Éves'
                WHEN 'kozepes' THEN 'Közepes - Havi'
                WHEN 'kozepes_eves' THEN 'Közepes - Éves'
                WHEN 'uzleti' THEN 'Üzleti - Havi'
                WHEN 'uzleti_eves' THEN 'Üzleti - Éves'
                ELSE sp.name
             END as package_name,
             COUNT(s.id) as count 
             FROM subscriptions s 
             JOIN subscription_plans sp ON s.subscription_plan_id = sp.id 
             WHERE s.subscription_status_id = 1
             GROUP BY sp.id, sp.name 
             ORDER BY count DESC";
             
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Adatok átstrukturálása Chart.js formátumra
    $labels = array_column($results, 'package_name');
    $counts = array_column($results, 'count');
    
    // Színek generálása
    $colors = [
        'rgba(75, 192, 192, 0.8)',
        'rgba(54, 162, 235, 0.8)',
        'rgba(153, 102, 255, 0.8)',
        'rgba(255, 159, 64, 0.8)',
        'rgba(255, 99, 132, 0.8)',
        'rgba(255, 206, 86, 0.8)'
    ];
    
    $data = [
        'success' => true,
        'data' => [
            'labels' => $labels,
            'datasets' => [[
                'data' => $counts,
                'backgroundColor' => array_slice($colors, 0, count($counts)),
                'borderColor' => array_map(function($color) {
                    return str_replace('0.8', '1', $color);
                }, array_slice($colors, 0, count($counts))),
                'borderWidth' => 1
            ]]
        ]
    ];
    
    echo json_encode($data);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hiba történt a statisztikák lekérdezése során: ' . $e->getMessage()
    ]);
} 