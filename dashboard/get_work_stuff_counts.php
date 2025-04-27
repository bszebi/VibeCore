<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth_check.php';

// Először állítsuk be a fejlécet JSON-re
header('Content-Type: application/json');

try {
    // Hibakezelés beállítása
    error_reporting(0);
    ini_set('display_errors', 0);

    if (!isset($_GET['work_id'])) {
        throw new Exception('Work ID is required');
    }

    $work_id = intval($_GET['work_id']);

    // Get database connection
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // First check if the work exists and get project start date
    $stmt = $db->prepare("SELECT w.company_id, w.work_start_date, w.work_end_date, w.project_id, p.project_startdate 
                         FROM work w 
                         LEFT JOIN project p ON w.project_id = p.id 
                         WHERE w.id = ?");
    $stmt->execute([$work_id]);
    $work = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$work) {
        throw new Exception("Work with ID $work_id not found");
    }
    
    // Check if the project start date has been reached
    if ($work['project_id'] && $work['project_startdate']) {
        $current_date = new DateTime();
        $project_start_date = new DateTime($work['project_startdate']);
        
        if ($current_date >= $project_start_date) {
            echo json_encode([
                'success' => false,
                'message' => 'A projekt kezdő dátuma már elérkezett (' . $project_start_date->format('Y-m-d H:i') . '). A bepakolás már nem lehetséges.',
                'is_date_valid' => false
            ]);
            exit;
        }
    }
    
    // Check which table exists
    $tables = [];
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    // Determine which table to use
    $use_work_stuff = in_array('work_stuff', $tables);
    $use_work_to_stuffs = in_array('work_to_stuffs', $tables);
    
    if (!$use_work_stuff && !$use_work_to_stuffs) {
        throw new Exception('Neither work_stuff nor work_to_stuffs table exists');
    }
    
    // Get the counts
    if ($use_work_stuff) {
        $sql = "SELECT 
                SUM(CASE WHEN is_packed = 1 THEN 1 ELSE 0 END) as packed_count,
                SUM(CASE WHEN is_packed = 0 OR is_packed IS NULL THEN 1 ELSE 0 END) as unpacked_count
                FROM work_stuff 
                WHERE work_id = ? AND company_id = ?";
    } else {
        $sql = "SELECT 
                SUM(CASE WHEN wts.is_packed = 1 THEN 1 ELSE 0 END) as packed_count,
                SUM(CASE WHEN wts.is_packed = 0 OR wts.is_packed IS NULL THEN 1 ELSE 0 END) as unpacked_count
                FROM work_to_stuffs wts
                JOIN work w ON wts.work_id = w.id
                WHERE wts.work_id = ? AND w.company_id = ?";
    }
            
    $stmt = $db->prepare($sql);
    $stmt->execute([$work_id, $_SESSION['company_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'packed_count' => intval($result['packed_count']),
        'unpacked_count' => intval($result['unpacked_count']),
        'debug' => [
            'table_used' => $use_work_stuff ? 'work_stuff' : 'work_to_stuffs',
            'work_id' => $work_id,
            'company_id' => $_SESSION['company_id']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 