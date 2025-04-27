<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/language_handler.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Check page access (this already includes session handling)
checkPageAccess();

// Check if user has company_id
if (!isset($_SESSION['company_id'])) {
    header('Location: /Vizsga_oldal/auth/login.php');
    exit;
}

// Initialize variables
$users = [];
$dbEvents = [];
$error = null;

// Check user roles
$user_roles = explode(',', $_SESSION['user_role']);
$is_admin = false;
$worker_roles = [
    'Vizuáltechnikus',
    'Villanyszerelő',
    'Szinpadtechnikus',
    'Szinpadfedés felelős',
    'Stagehand',
    'Karbantartó',
    'Hangtechnikus',
    'Fénytechnikus'
];

$is_worker = false;
foreach ($user_roles as $role) {
    $role = trim($role);
    if ($role === 'Cég tulajdonos' || $role === 'Manager') {
        $is_admin = true;
        break;
    }
    if (in_array($role, $worker_roles)) {
        $is_worker = true;
    }
}

// Debug információ
error_log('User roles: ' . $_SESSION['user_role']);
error_log('Is admin: ' . ($is_admin ? 'true' : 'false'));
error_log('Is worker: ' . ($is_worker ? 'true' : 'false'));

// Csak akkor kérjük le az összes felhasználót, ha admin
if ($is_admin) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Lekérjük a csapattagokat
        $stmt = $db->prepare("
            SELECT 
                u.*,
                GROUP_CONCAT(DISTINCT r.role_name) as roles,
                s.name as status_name,
                DATE_FORMAT(u.connect_date, '%Y. %m. %d.') as formatted_date
            FROM user u
            LEFT JOIN user_to_roles utr ON u.id = utr.user_id
            LEFT JOIN roles r ON utr.role_id = r.id
            LEFT JOIN status s ON u.current_status_id = s.id
            WHERE u.company_id = (
                SELECT company_id 
                FROM user 
                WHERE id = :user_id
            )
            GROUP BY u.id, s.name
            ORDER BY u.firstname
        ");
        
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debug információ
        error_log('Users found: ' . count($users));
        foreach ($users as $user) {
            error_log('User ' . $user['firstname'] . ' profile pic path: ' . $user['profile_pic']);
        }
        
    } catch (PDOException $e) {
        $error = 'Adatbázis hiba: ' . $e->getMessage();
        error_log($e->getMessage());
    }
}

// Debug információk
if (empty($users) && $is_admin) {
    error_log('No users found for user_id: ' . $_SESSION['user_id']);
}

// A hónapok nevei tömb módosítása
$months = [
    1 => translate('január'),
    2 => translate('február'),
    3 => translate('március'),
    4 => translate('április'),
    5 => translate('május'),
    6 => translate('június'),
    7 => translate('július'),
    8 => translate('augusztus'),
    9 => translate('szeptember'),
    10 => translate('október'),
    11 => translate('november'),
    12 => translate('december')
];

// Napok nevei
$days = [
    1 => translate('Hétfő'),
    2 => translate('Kedd'),
    3 => translate('Szerda'),
    4 => translate('Csüt'),
    5 => translate('Pént'),
    6 => translate('Szom'),
    7 => translate('Vas')
];

require_once '../includes/layout/header.php';

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Kapcsolódási hiba: " . mysqli_connect_error());
}

// Munkák lekérése - módosított lekérdezés a jogosultságok alapján
if ($is_admin) {
    // Adminisztrátorok minden elfogadott munkát és szabadságot látnak
    $works_sql = "SELECT 
        w.id,
        w.work_start_date,
        w.work_end_date,
        TIME(w.work_start_date) as work_start_time,
        p.name as project_name,
        pt.name as project_type,
        GROUP_CONCAT(DISTINCT CONCAT(u.lastname, ' ', u.firstname)) as workers,
        DATE(w.work_start_date) as start_date,
        DATE(w.work_end_date) as end_date,
        'work' as event_type
    FROM work w
    LEFT JOIN project p ON w.project_id = p.id
    LEFT JOIN project_type pt ON p.type_id = pt.id
    LEFT JOIN user_to_work utw ON w.id = utw.work_id
    LEFT JOIN user u ON utw.user_id = u.id
    LEFT JOIN notifications n ON w.id = n.work_id
    WHERE w.company_id = ? 
    AND w.work_start_date IS NOT NULL 
    AND w.work_end_date IS NOT NULL
    GROUP BY w.id, w.work_start_date, w.work_end_date, p.name, pt.name

    UNION ALL

    SELECT 
        ce.id,
        ce.start_date as work_start_date,
        ce.end_date as work_end_date,
        TIME(ce.start_date) as work_start_time,
        ce.title as project_name,
        NULL as project_type,
        CONCAT(u.lastname, ' ', u.firstname) as workers,
        DATE(ce.start_date) as start_date,
        DATE(ce.end_date) as end_date,
        CASE 
            WHEN s.name = 'szabadság' THEN 'vacation'
            WHEN s.name = 'betegállomány' THEN 'sick'
            ELSE 'other'
        END as event_type
    FROM calendar_events ce
    JOIN user u ON ce.user_id = u.id
    JOIN status s ON ce.status_id = s.id
    WHERE ce.company_id = ?
    AND (s.name = 'szabadság' OR s.name = 'betegállomány')
    AND (ce.is_accepted IS NULL OR ce.is_accepted = 1)

    UNION ALL

    SELECT 
        lr.id,
        lr.start_date as work_start_date,
        lr.end_date as work_end_date,
        TIME(lr.start_date) as work_start_time,
        CASE 
            WHEN s.name = 'szabadság' THEN 'Szabadság kérelem'
            ELSE 'Betegállomány kérelem'
        END as project_name,
        NULL as project_type,
        CONCAT(u.lastname, ' ', u.firstname) as workers,
        DATE(lr.start_date) as start_date,
        DATE(lr.end_date) as end_date,
        'pending' as event_type
    FROM leave_requests lr
    JOIN user u ON lr.sender_user_id = u.id
    JOIN status s ON lr.status_id = s.id
    WHERE lr.is_accepted IS NULL";
    
    $stmt = mysqli_prepare($conn, $works_sql);
    mysqli_stmt_bind_param($stmt, "ii", $_SESSION['company_id'], $_SESSION['company_id']);
} else {
    // Nem adminisztrátorok csak a saját munkáikat és szabadságukat látják
    $works_sql = "SELECT 
        w.id,
        w.work_start_date,
        w.work_end_date,
        TIME(w.work_start_date) as work_start_time,
        p.name as project_name,
        pt.name as project_type,
        GROUP_CONCAT(DISTINCT CONCAT(u.lastname, ' ', u.firstname)) as workers,
        DATE(w.work_start_date) as start_date,
        DATE(w.work_end_date) as end_date,
        'work' as event_type
    FROM work w
    LEFT JOIN project p ON w.project_id = p.id
    LEFT JOIN project_type pt ON p.type_id = pt.id
    LEFT JOIN user_to_work utw ON w.id = utw.work_id
    LEFT JOIN user u ON utw.user_id = u.id
    LEFT JOIN notifications n ON w.id = n.work_id
    WHERE w.company_id = ? 
    AND w.work_start_date IS NOT NULL 
    AND w.work_end_date IS NOT NULL
    AND utw.user_id = ?
    GROUP BY w.id, w.work_start_date, w.work_end_date, p.name, pt.name

    UNION ALL

    SELECT 
        ce.id,
        ce.start_date as work_start_date,
        ce.end_date as work_end_date,
        TIME(ce.start_date) as work_start_time,
        ce.title as project_name,
        NULL as project_type,
        CONCAT(u.lastname, ' ', u.firstname) as workers,
        DATE(ce.start_date) as start_date,
        DATE(ce.end_date) as end_date,
        CASE 
            WHEN s.name = 'szabadság' THEN 'vacation'
            WHEN s.name = 'betegállomány' THEN 'sick'
            ELSE 'other'
        END as event_type
    FROM calendar_events ce
    JOIN user u ON ce.user_id = u.id
    JOIN status s ON ce.status_id = s.id
    WHERE ce.company_id = ?
    AND ce.user_id = ?
    AND (s.name = 'szabadság' OR s.name = 'betegállomány')

    UNION ALL

    SELECT 
        lr.id,
        lr.start_date as work_start_date,
        lr.end_date as work_end_date,
        TIME(lr.start_date) as work_start_time,
        CASE 
            WHEN s.name = 'szabadság' THEN 'Szabadság kérelem'
            ELSE 'Betegállomány kérelem'
        END as project_name,
        NULL as project_type,
        CONCAT(u.lastname, ' ', u.firstname) as workers,
        DATE(lr.start_date) as start_date,
        DATE(lr.end_date) as end_date,
        'pending' as event_type
    FROM leave_requests lr
    JOIN user u ON lr.sender_user_id = u.id
    JOIN status s ON lr.status_id = s.id
    WHERE lr.sender_user_id = ?
    AND lr.is_accepted IS NULL";
    
    $stmt = mysqli_prepare($conn, $works_sql);
    mysqli_stmt_bind_param($stmt, "iiiii", 
        $_SESSION['company_id'], 
        $_SESSION['user_id'], 
        $_SESSION['company_id'], 
        $_SESSION['user_id'],
        $_SESSION['user_id']
    );
}

mysqli_stmt_execute($stmt);
$works_result = mysqli_stmt_get_result($stmt);

$workEvents = [];
while ($work = mysqli_fetch_assoc($works_result)) {
    if ($work['work_start_date'] && $work['work_end_date']) {
        $start = new DateTime($work['work_start_date']);
        $end = new DateTime($work['work_end_date']);
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($start, $interval, $end->modify('+1 second'));

        foreach ($dateRange as $date) {
            $event = [
                'event_date' => $date->format('Y-m-d'),
                'event_type' => $work['event_type'],
                'workers' => $work['workers'],
                'start_date' => $work['start_date'],
                'end_date' => $work['end_date']
            ];

            // Csak munka eseményeknél adjuk hozzá a project_name és work_start_time mezőket
            if ($work['event_type'] === 'work') {
                $event['project_name'] = $work['project_name'];
                $event['work_start_time'] = $work['work_start_time'];
                $event['project_type'] = $work['project_type'];
            }

            $workEvents[] = $event;
        }
    }
}

// Események összefűzése - csak a munka események
$events = $workEvents;

require_once '../includes/layout/header.php';

?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../assets/img/monitor.png">
    <title>Naptár - VibeCore</title>
    <style>
        .calendar-wrapper {
            width: 95%;
            max-width: 1400px;
            margin: 1rem auto;
            display: flex;
            gap: 20px;
            align-items: flex-start;
            justify-content: center;
            margin-left: 140px;
        }

        .calendar-container {
            width: 1000px;
            margin: 0;
            padding: 2rem;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .calendar-filters {
            width: 200px;
            margin: 0;
            margin-top: 2rem;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 15px;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            height: fit-content;
            transition: all 0.3s ease;
            position: relative;
        }

        .calendar-filters.collapsed {
            height: 50px;
            padding: 8px 15px;
            overflow: hidden;
        }

        .filter-toggle {
            width: 100%;
            padding: 8px;
            background-color: white;
            border: 2px solid #3498db;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 500;
            color: inherit;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }

        .filter-toggle:hover {
            background: #3498db;
            color: white;
        }

        .filter-toggle i {
            font-size: 1rem;
            transition: transform 0.3s ease;
        }

        .calendar-filters.collapsed .filter-toggle i {
            transform: rotate(180deg);
        }

        .filter-content {
            display: flex;
            flex-direction: column;
            gap: 10px;
            opacity: 1;
            transition: opacity 0.3s ease;
        }

        .calendar-filters.collapsed .filter-content {
            opacity: 0;
        }

        .filter-button {
            width: 100%;
            padding: 10px;
            border: 2px solid #3498db;
            border-radius: 8px;
            background-color: white;
            color: inherit;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            font-weight: 500;
            text-align: center;
        }

        .filter-button:hover {
            background: #3498db;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .filter-button.active {
            background: #3498db;
            color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-template-rows: auto repeat(6, minmax(80px, 1fr));
            gap: 4px;
            padding: 4px;
            border-radius: 10px;
            margin: 0 auto;
            aspect-ratio: 7/5;
            height: calc(98vh - 240px);
            width: 100%;
        }

        .calendar-header {
            width: 100%;
            padding: 0.5rem 1rem;
            border-bottom: 1px solid #465c71;
        }

        .month-nav {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 0.5rem 0;
        }

        .nav-btn {
            background: #2c3e50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.2s ease;
            margin: 0 15px;
        }

        .nav-btn:hover {
            background: #34495e;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        #currentMonth {
            font-size: 1.5rem;
            font-weight: 500;
            margin: 0;
            min-width: 200px;
            text-align: center;
        }

        .calendar-header-cell {
            background: #2c3e50;
            color: white;
            padding: 0.8rem;
            text-align: center;
            font-weight: bold;
            font-size: 1.1rem;
            border-radius: 5px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .calendar-cell {
            position: relative;
            cursor: pointer;
            border-radius: 5px;
            transition: transform 0.2s, box-shadow 0.2s;
            padding: 0.8rem;
            display: flex;
            flex-direction: column;
            min-height: 100px;
            border: 1px solid #465c71;
            max-height: 150px;
            overflow-y: auto;
            background-color: white;
        }

        .calendar-cell:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .calendar-cell.today {
            border: 2px solid #3498db;
        }

        .events-container {
            margin-top: 25px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .event {
            padding: 6px 10px;
            margin: 2px 0;
            border-radius: 6px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: white;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .event:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.15);
        }

        .event.work {
            background-color: #3498db;
            color: white;
            border-left: 4px solid #2980b9;
            font-weight: 500;
        }
        
        .event.vacation {
            background-color: #2ecc71;
            color: white;
            border-left: 4px solid #27ae60;
            font-weight: 500;
        }
        
        .event.sick {
            background-color: #e74c3c;
            color: white;
            border-left: 4px solid #c0392b;
            font-weight: 500;
        }
        
        .event.pending {
            background-color: #f1c40f;
            color: white;
            border-left: 4px solid #f39c12;
            font-weight: 500;
        }
        
        .event-icon {
            margin-right: 6px;
            font-size: 1.1em;
        }
        
        .event-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        .calendar-cell.today .date-number {
            color: #3498db;
            font-weight: bold;
        }

        .date-number {
            position: absolute;
            top: 5px;
            left: 5px;
            font-size: 1rem;
            z-index: 2;
        }

        .event[title]:hover::after {
            content: attr(title);
            position: absolute;
            z-index: 1000;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: pre-wrap;
            max-width: 250px;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-bottom: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .event[title]:hover::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.8);
            margin-bottom: -1px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5) !important;
            z-index: 9999 !important;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white !important;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 10000 !important;
            position: relative;
        }

        #eventForm {
            flex: 1;
            overflow-y: auto;
            padding-right: 10px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 0.8rem;
        }

        .form-group textarea {
            width: 100%;
            min-height: 80px;
            max-height: 300px;
            padding: 0.6rem;
            border: 2px solid #465c71;
            border-radius: 8px;
            font-size: 0.9rem;
            color: inherit;
            resize: vertical;
            background-color: white;
            overflow-y: auto;
        }

        .form-group textarea::-webkit-scrollbar {
            width: 8px;
        }

        .form-group textarea::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .form-group textarea::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .form-group textarea::-webkit-scrollbar-thumb:hover {
            background: #666;
        }

        .form-buttons {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 15px 30px;
            background-color: white !important;
            border-top: 1px solid rgba(70, 92, 113, 0.5);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            z-index: 100;
            margin: 0;
            backdrop-filter: blur(10px);
        }

        .modal h3 {
            margin: 0 0 1.2rem 0;
            color: #2c3e50;
            font-size: 1.3rem;
            padding-right: 30px;
        }

        .event-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding-bottom: 70px;
        }

        .event-form select, 
        .event-form textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #465c71;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .event-form select:focus,
        .event-form textarea:focus {
            border-color: #3498db;
            outline: none;
        }

        .event-form button {
            background: #2c3e50;
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .event-form button:hover {
            background: #34495e;
        }

        .close-modal {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 18px;
            color: #000;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .close-modal:hover {
            opacity: 0.7;
        }

        .selected-date {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .form-group label {
            font-weight: 500;
            color: #2c3e50;
        }

        .date-inputs {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .date-input-group {
            flex: 1;
        }

        .date-input-group label {
            display: block;
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
            color: #666;
        }

        .date-input-group input[type="date"] {
            width: 100%;
            padding: 0.6rem;
            border: 2px solid #465c71;
            border-radius: 8px;
            font-size: 0.9rem;
            color: inherit;
            background-color: white;
        }

        select#eventType {
            width: 200px;
            min-width: unset;
            padding: 0.6rem;
            border: 2px solid #465c71;
            border-radius: 8px;
            font-size: 0.9rem;
            color: inherit;
            cursor: pointer;
            background-color: white;
        }

        select#eventType:focus {
            border-color: #3498db;
            outline: none;
        }

        .date-input-group input[type="date"]:focus {
            border-color: #3498db;
            outline: none;
        }

        #selectedUsers {
            width: 100%;
            min-height: 150px;
            padding: 8px;
            border: 2px solid #465c71;
            border-radius: 8px;
            margin-bottom: 5px;
        }

        #selectedUsers option {
            padding: 8px;
            margin: 2px 0;
            border-radius: 4px;
        }

        #selectedUsers option:checked {
            background-color: #3498db;
            color: white;
        }

        .form-group small {
            color: #666;
            font-size: 0.8rem;
            margin-top: 5px;
            display: block;
        }

        .team-members-select {
            max-height: 250px;
            overflow-y: auto;
            border: 1px solid rgba(70, 92, 113, 0.5);
            border-radius: 8px;
            padding: 8px;
            margin-top: 8px;
            background-color: white;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
        }

        .member-card {
            background-color: white !important;
            padding: 6px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid rgba(70, 92, 113, 0.5);
            width: 100%;
        }

        .member-card:hover {
            background: rgba(52, 152, 219, 0.1) !important;
            border-color: #3498db;
        }

        .member-avatar {
            position: relative;
            width: 30px;
            height: 30px;
            flex-shrink: 0;
            margin-right: 4px;
        }

        .member-avatar img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }

        .member-info {
            flex: 1;
            min-width: 0;
            font-size: 0.9rem;
        }

        .member-name {
            font-size: 0.85rem;
            font-weight: 500;
            color: #2c3e50;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .member-role {
            font-size: 0.75rem;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .team-member-option {
            width: 100%;
        }

        .status-indicator {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid white;
        }

        .status-indicator.elérhető {
            background-color: #2ecc71;
        }

        .status-indicator.munkában {
            background-color: #e74c3c;
        }

        .status-indicator.lefoglalt {
            background-color: #f39c12;
        }

        .status-indicator.szabadság {
            background-color: #3498db;
        }

        .status-indicator.betegállomány {
            background-color: #9b59b6;
        }

        .team-member-option input[type="checkbox"] {
            display: none;
        }

        .team-member-option input[type="checkbox"]:checked + .member-card {
            background: rgba(52, 152, 219, 0.2) !important;
            border-color: #3498db;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .notification {
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 1rem 2rem;
            border-radius: 8px;
            background: #4CAF50;
            color: white;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 300px;
            transform: translateX(120%);
            opacity: 0;
            transition: all 0.5s ease;
        }

        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }

        .notification.hide {
            transform: translateX(120%);
            opacity: 0;
        }

        .notification.error {
            background: #f44336;
        }

        .notification i {
            font-size: 1.2rem;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        .modal-options {
            text-align: center;
            padding: 20px;
                background-color: white !important;
        }

        .modal-options h3 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 1.5rem;
        }

        .option-btn {
            display: inline-block;
            width: 250px;
            margin: 10px;
            padding: 15px 25px;
            background-color: white !important;
            border: 2px solid #3498db;
            border-radius: 8px;
            color: #3498db;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .option-btn:hover {
            background: rgba(52, 152, 219, 0.2) !important;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .option-btn i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .daily-timeline {
            display: flex;
            flex-direction: column;
            gap: 5px;
            max-height: 60vh;
            overflow-y: auto;
            padding-right: 10px;
            margin-bottom: 60px;
            background-color: white;
        }

        .timeline-hour {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 8px;
            border-radius: 8px;
            transition: background-color 0.2s;
        }

        .timeline-hour:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .hour-label {
            min-width: 60px;
            font-weight: 500;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .hour-events {
            flex: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            align-items: center;
        }

        .time-slot {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: white;
        }

        .time-slot:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .time-slot i {
            font-size: 1rem;
        }

        .daily-timeline::-webkit-scrollbar {
            width: 8px;
        }

        .daily-timeline::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .daily-timeline::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .daily-timeline::-webkit-scrollbar-thumb:hover {
            background: #666;
        }

        .secondary-btn {
            background: #2c3e50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            margin-right: 10px;
        }

        .secondary-btn:hover {
            background: #34495e;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .alert-notification {
            padding: 15px 40px;
            border-radius: 8px;
            font-weight: 500;
        }

        .vacation-event {
            background-color: #4CAF50 !important;
            border-color: #45a049 !important;
            color: white !important;
        }

        .sick-leave-event {
            background-color: #f44336 !important;
            border-color: #da190b !important;
            color: white !important;
        }

        .fc-event-title {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .fc-event-title::before {
            font-size: 1.2em;
            line-height: 1;
        }

        .vacation-event .fc-event-title::before {
            content: '🌴';
        }

        .sick-leave-event .fc-event-title::before {
            content: '🏥';
        }

        .modal-content {
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .modal .btn {
            border-radius: 4px;
            padding: 8px 16px;
        }

        .modal .btn-primary {
            background-color: #007bff;
            border-color: #0056b3;
        }

        .modal .btn-primary:hover {
            background-color: #0056b3;
            border-color: #004085;
        }

        .modal .btn-secondary {
            background-color: #6c757d;
            border-color: #545b62;
        }

        .modal .btn-secondary:hover {
            background-color: #545b62;
            border-color: #4e555b;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 4px;
            border: 1px solid #ced4da;
            padding: 8px 12px;
        }

        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .member-search-input {
            background-color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: inherit;
            padding: 8px;
            border-radius: 4px;
            width: 100%;
        }

        .member-search-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .role-dropdown-content {
            background-color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            padding: 8px 0;
        }

        .role-option {
            padding: 8px 16px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .role-option:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .role-option.active {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .filter-controls {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
            position: relative;
        }

        .filter-dropdown {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .active-filter {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
            font-weight: 500;
            font-size: 0.9rem;
            display: none;
            padding: 6px 12px;
            padding-right: 30px;
            border-radius: 6px;
            border: 1px solid rgba(52, 152, 219, 0.3);
            margin-left: 10px;
            align-items: center;
            gap: 6px;
            position: relative;
        }

        .active-filter .remove-filter {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #3498db;
            font-size: 0.8rem;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .active-filter .remove-filter:hover {
            background-color: rgba(52, 152, 219, 0.2);
        }

        .active-filter.show {
            display: inline-flex;
        }

        .active-filter.show::before {
            content: '•';
            color: #3498db;
            font-size: 1.2rem;
        }

        .filter-btn {
            background-color: #2c3e50;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .filter-btn:hover {
            background: #34495e;
        }

        .filter-btn i {
            font-size: 0.9rem;
        }

        .role-dropdown-content {
            position: absolute;
            left: 0;
            top: calc(100% + 5px);
            background-color: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 8px 0;
            min-width: 200px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .role-option {
            padding: 10px 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #2c3e50;
        }

        .role-option:hover {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .role-option.active {
            background-color: rgba(52, 152, 219, 0.2);
            color: #3498db;
            font-weight: 500;
        }

        .member-search-input {
            width: 100%;
            padding: 10px;
            border: 2px solid #3498db;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .member-search-input:focus {
            outline: none;
            border-color: #2980b9;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }

        .blurred {
            filter: blur(5px) !important;
        }
    </style>
</head>
<body>
    <div class="calendar-wrapper">
        <div class="calendar-container">
            <div class="calendar-header">
                <div class="month-nav">
                    <button class="nav-btn prev" onclick="previousMonth()">←</button>
                    <h2 id="currentMonth"></h2>
                    <button class="nav-btn next" onclick="nextMonth()">→</button>
                </div>
            </div>

            <div class="calendar-grid">
                <div class="calendar-header-cell"><?php echo translate('Hétfő'); ?></div>
                <div class="calendar-header-cell"><?php echo translate('Kedd'); ?></div>
                <div class="calendar-header-cell"><?php echo translate('Szerda'); ?></div>
                <div class="calendar-header-cell"><?php echo translate('Csüt'); ?></div>
                <div class="calendar-header-cell"><?php echo translate('Pént'); ?></div>
                <div class="calendar-header-cell"><?php echo translate('Szom'); ?></div>
                <div class="calendar-header-cell"><?php echo translate('Vas'); ?></div>
            </div>
        </div>

        <div class="calendar-filters collapsed">
            <button class="filter-toggle" onclick="toggleFilters()">
                <i class="fas fa-filter"></i>
                <?php echo translate('Szűrők'); ?>
            </button>
            <div class="filter-content">
                <button class="filter-button active" data-filter="all"><?php echo translate('Összes megjelenítése'); ?></button>
                <button class="filter-button" data-filter="work"><?php echo translate('Munkák'); ?></button>
                <button class="filter-button" data-filter="vacation"><?php echo translate('Szabadság'); ?></button>
                <button class="filter-button" data-filter="sick"><?php echo translate('Betegállomány'); ?></button>
            </div>
        </div>
    </div>

    <!-- Esemény hozzáadása modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content" style="background-color: white !important;">
            <span class="close-modal" onclick="closeModal('eventModal')">&times;</span>
            <div id="modalOptions" class="modal-options" style="background-color: white !important;">
                <h3><?php echo translate('Válasszon műveletet'); ?></h3>
                <button onclick="showEventForm()" class="option-btn" style="background-color: white !important;">
                    <i class="fas fa-calendar-plus"></i>
                    <?php echo translate('Szabadság / Betegállomány beírása'); ?>
                </button>
                <button onclick="showDailySchedule()" class="option-btn" id="viewScheduleBtn" style="background-color: white !important;">
                    <i class="fas fa-clock"></i>
                    <?php echo translate('Napi időbeosztás megtekintése'); ?>
                </button>
            </div>

                <div id="eventForm" class="modal-section" style="display: none; background-color: white !important;">
                <h3><?php echo $is_worker ? translate('Szabadság / Betegállomány kérelem') : translate('Szabadság / Betegállomány beírása'); ?></h3>
                <div class="selected-date" id="selectedDateDisplay"></div>
                <form class="event-form" id="leaveForm" style="background-color: white !important;">
                    <input type="hidden" id="selectedDate" name="date">
                    
                    <?php if (!$is_worker): ?>
                    <div class="form-group">
                        <div class="filter-controls">
                            <div class="filter-dropdown">
                                <button type="button" class="filter-btn" onclick="toggleRoleFilter()">
                                    <i class="fas fa-filter"></i> <?php echo translate('Szűrés'); ?>
                                </button>
                                <span class="active-filter"></span>
                                <div id="roleFilterDropdown" class="role-dropdown-content" style="display: none;">
                                    <div class="role-option" onclick="selectRole('')"><?php echo translate('Összes szerepkör'); ?></div>
                                    <?php foreach ($worker_roles as $role): ?>
                                        <div class="role-option" onclick="selectRole('<?php echo $role; ?>')"><?php echo translate($role); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <input type="text" 
                                   id="memberSearch" 
                                   placeholder="<?php echo translate('Keresés név alapján...'); ?>"
                                   class="member-search-input">
                        </div>
                        <div class="team-members-select">
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <div class="team-member-option" 
                                        data-search="<?php echo htmlspecialchars(strtolower($user['firstname'] . ' ' . 
                                             $user['lastname'] . ' ' . $user['roles'])); ?>"
                                        data-roles="<?php echo htmlspecialchars($user['roles']); ?>">
                                        <input type="checkbox" 
                                               name="user_ids[]" 
                                               value="<?php echo htmlspecialchars($user['id']); ?>" 
                                               id="user_<?php echo $user['id']; ?>">
                                        <label for="user_<?php echo $user['id']; ?>" class="member-card">
                                            <div class="member-avatar">
                                                <img src="<?php 
                                                    $profile_pic = $user['profile_pic'] ?? 'user.png';
                                                    echo file_exists('../uploads/profiles/' . $profile_pic) 
                                                        ? '../uploads/profiles/' . $profile_pic 
                                                        : '../assets/img/user.png';
                                                ?>" alt="<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>" 
                                                    class="member-image">
                                                <span class="status-indicator <?php echo mb_strtolower($user['status_name'], 'UTF-8'); ?>"></span>
                                            </div>
                                            <div class="member-info">
                                                <div class="member-name"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) . 
                                                    ($user['id'] == $_SESSION['user_id'] ? ' (Ön)' : ''); ?></div>
                                                <div class="member-role"><?php echo htmlspecialchars($user['roles']); ?></div>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-members"><?php echo translate('Nincsenek elérhető munkások'); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="user_ids[]" value="<?php echo $_SESSION['user_id']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="eventType"><?php echo translate('Típus:'); ?></label>
                        <select id="eventType" name="event_type" required>
                            <option value=""><?php echo translate('Válasszon...'); ?></option>
                            <option value="vacation"><?php echo translate('Szabadság'); ?></option>
                            <option value="sick"><?php echo translate('Betegállomány'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?php echo translate('Időszak:'); ?></label>
                        <div class="date-inputs">
                            <div class="date-input-group">
                                <label for="startDate"><?php echo translate('Kezdő dátum'); ?></label>
                                <input type="date" id="startDate" name="start_date" required>
                            </div>
                            <div class="date-input-group">
                                <label for="endDate"><?php echo translate('Befejező dátum'); ?></label>
                                <input type="date" id="endDate" name="end_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="workDescription"><?php echo translate('Megjegyzés'); ?> <?php echo $is_worker ? '' : '(' . translate('nem kötelező') . ')'; ?>:</label>
                        <textarea id="workDescription" name="description" 
                                  placeholder="<?php echo $is_worker ? translate('Írja le a kérelem indoklását...') : translate('Írjon megjegyzést...'); ?>"
                                  <?php echo $is_worker ? 'required' : ''; ?>></textarea>
                    </div>

                    <div class="form-buttons">
                        <button type="button" onclick="backToOptions()" class="secondary-btn"><?php echo translate('Vissza'); ?></button>
                        <button type="submit" class="primary-btn">
                            <?php echo $is_worker ? translate('Kérelem beküldése') : translate('Mentés'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <div id="dailySchedule" class="modal-section" style="display: none;">
                <h3><?php echo translate('Napi időbeosztás'); ?></h3>
                <div class="selected-date" id="selectedDateDisplaySchedule"></div>
                <div class="daily-timeline">
                    <!-- Az órák és események itt jelennek meg dinamikusan -->
                </div>
                <div class="form-buttons">
                    <button type="button" onclick="backToOptions()" class="secondary-btn"><?php echo translate('Vissza'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <div id="notification" class="notification">
        <i class="fas fa-exclamation-circle"></i>
        <span id="notification-message"></span>
    </div>

    <script>
        let currentDate = new Date();
        let events = <?php echo json_encode($workEvents); ?>;
        let currentFilter = 'all';

        // Add type colors array before the events processing
        const typeColors = {
            'Fesztivál': '#3498db',      // kék
            'Konferancia': '#c2ae1b',    // piszkos sárga
            'Rendezvény': '#9b59b6',     // lila
            'Előadás': '#34495e',        // sötétszürke
            'Kiállitás': '#e67e22',      // narancssárga
            'Jótékonysági': '#fa93ce',   // halvány rózsaszín
            'Ünnepség': '#16a085',       // türkiz
            'Egyéb': '#95a5a6'           // szürke
        };

        function initCalendar() {
            const grid = document.querySelector('.calendar-grid');
            const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
            
            // Töröljük a meglévő cellákat, de megtartjuk a fejlécet
            while (grid.children.length > 7) {
                grid.removeChild(grid.lastChild);
            }

            // Az első nap helyének kiszámítása (0 = vasárnap, 1 = hétfő, stb.)
            let firstDayOfWeek = firstDay.getDay();
            firstDayOfWeek = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1;

            // Üres cellák hozzáadása a hónap első napja előtt
            for (let i = 0; i < firstDayOfWeek; i++) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'calendar-cell empty';
                grid.appendChild(emptyCell);
            }

            // Mai dátum lekérése az összehasonlításhoz
            const today = new Date();
            const isCurrentMonth = today.getMonth() === currentDate.getMonth() && 
                                  today.getFullYear() === currentDate.getFullYear();

            // Naptár feltöltése napokkal
            for (let i = 1; i <= lastDay.getDate(); i++) {
                const cell = document.createElement('div');
                cell.className = 'calendar-cell';
                
                // Ha ez a mai nap, adjunk hozzá egy extra class-t
                if (isCurrentMonth && i === today.getDate()) {
                    cell.classList.add('today');
                }

                // Dátum hozzáadása
                const dateNumber = document.createElement('div');
                dateNumber.className = 'date-number';
                dateNumber.textContent = i;
                cell.appendChild(dateNumber);

                // Események konténer
                const eventsContainer = document.createElement('div');
                eventsContainer.className = 'events-container';
                cell.appendChild(eventsContainer);

                // Események hozzáadása
                const currentDateStr = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                const dayEvents = events.filter(event => {
                    if (currentFilter === 'all') {
                        return event.event_date === currentDateStr;
                    } else {
                        return event.event_date === currentDateStr && event.event_type === currentFilter;
                    }
                });

                dayEvents.forEach(event => {
                    const eventDiv = document.createElement('div');
                    eventDiv.className = `event ${event.event_type}`;
                    
                    let displayText = '';
                    let tooltipText = '';
                    let icon = '';
                    let bgColor = '';
                    
                    if (event.event_type === 'work') {
                        displayText = event.project_name;
                        tooltipText = `${<?php echo json_encode(translate('Projekt')); ?>}: ${event.project_name}\n${<?php echo json_encode(translate('Dolgozók')); ?>}: ${event.workers}\n${new Date(event.start_date).toLocaleDateString('hu-HU')} - ${new Date(event.end_date).toLocaleDateString('hu-HU')}`;
                        bgColor = typeColors[event.project_type] || '#95a5a6';
                    } else if (event.event_type === 'vacation') {
                        displayText = <?php echo $is_admin ? 'event.workers' : json_encode(translate('Szabadság')); ?>;
                        tooltipText = `${event.workers}\n${<?php echo json_encode(translate('Szabadság')); ?>}\n${new Date(event.start_date).toLocaleDateString('hu-HU')} - ${new Date(event.end_date).toLocaleDateString('hu-HU')}`;
                        icon = '🌴';
                        bgColor = '#2ecc71';
                    } else if (event.event_type === 'sick') {
                        displayText = <?php echo $is_admin ? 'event.workers' : json_encode(translate('Betegállomány')); ?>;
                        tooltipText = `${event.workers}\n${<?php echo json_encode(translate('Betegállomány')); ?>}\n${new Date(event.start_date).toLocaleDateString('hu-HU')} - ${new Date(event.end_date).toLocaleDateString('hu-HU')}`;
                        icon = '🏥';
                        bgColor = '#e74c3c';
                    } else if (event.event_type === 'pending') {
                        displayText = <?php echo $is_admin ? 'event.workers.split(" ")[0]' : json_encode(translate('Függőben')); ?>;
                        tooltipText = `${event.project_name}\n${new Date(event.start_date).toLocaleDateString('hu-HU')} - ${new Date(event.end_date).toLocaleDateString('hu-HU')}`;
                        icon = '⏳';
                        bgColor = '#f1c40f';
                    }
                    
                    if (icon) {
                        const iconSpan = document.createElement('span');
                        iconSpan.className = 'event-icon';
                        iconSpan.textContent = icon;
                        eventDiv.appendChild(iconSpan);
                    }
                    
                    const textSpan = document.createElement('span');
                    textSpan.className = 'event-text';
                    textSpan.textContent = displayText;
                    
                    eventDiv.appendChild(textSpan);
                    eventDiv.title = tooltipText;
                    
                    eventDiv.style.backgroundColor = bgColor;
                    
                    eventsContainer.appendChild(eventDiv);
                });

                cell.onclick = () => openModal(i);
                grid.appendChild(cell);
            }

            // Frissítjük a hónap kijelzést
            updateMonthDisplay();
        }

        function updateMonthDisplay() {
            // A hónapok neveit a PHP translate függvénnyel fordítjuk
            const months = [
                <?php echo json_encode(translate('január')); ?>,
                <?php echo json_encode(translate('február')); ?>,
                <?php echo json_encode(translate('március')); ?>,
                <?php echo json_encode(translate('április')); ?>,
                <?php echo json_encode(translate('május')); ?>,
                <?php echo json_encode(translate('június')); ?>,
                <?php echo json_encode(translate('július')); ?>,
                <?php echo json_encode(translate('augusztus')); ?>,
                <?php echo json_encode(translate('szeptember')); ?>,
                <?php echo json_encode(translate('október')); ?>,
                <?php echo json_encode(translate('november')); ?>,
                <?php echo json_encode(translate('december')); ?>
            ];
            
            // Az év szót is lefordítjuk
            const yearText = currentDate.getFullYear();
            document.getElementById('currentMonth').textContent = `${months[currentDate.getMonth()]} ${yearText}`;
        }

        function previousMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            initCalendar();
        }

        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            initCalendar();
        }

        function openModal(day) {
            const modal = document.getElementById('eventModal');
            const selectedDateInput = document.getElementById('selectedDate');
            const dateDisplay = document.getElementById('selectedDateDisplay');
            
            // Dátum formázása
            const monthNames = [
                <?php echo json_encode(translate('január')); ?>,
                <?php echo json_encode(translate('február')); ?>,
                <?php echo json_encode(translate('március')); ?>,
                <?php echo json_encode(translate('április')); ?>,
                <?php echo json_encode(translate('május')); ?>,
                <?php echo json_encode(translate('június')); ?>,
                <?php echo json_encode(translate('július')); ?>,
                <?php echo json_encode(translate('augusztus')); ?>,
                <?php echo json_encode(translate('szeptember')); ?>,
                <?php echo json_encode(translate('október')); ?>,
                <?php echo json_encode(translate('november')); ?>,
                <?php echo json_encode(translate('december')); ?>
            ];
            
            // Kiválasztott dátum beállítása
            const selectedDate = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            selectedDateInput.value = selectedDate;
            
            // Dátum megjelenítése a lefordított hónapnevekkel
            dateDisplay.textContent = `${currentDate.getFullYear()}. ${monthNames[currentDate.getMonth()]} ${day}.`;
            
            // Modal megjelenítése
            modal.style.display = 'block';
            // Fejléc blur
            const header = document.querySelector('.main-header');
            if (header) header.classList.add('blurred');
            
            // Alapértelmezett nézet beállítása
            showModalOptions();
        }

        function showModalOptions() {
            document.getElementById('modalOptions').style.display = 'block';
            document.getElementById('eventForm').style.display = 'none';
            document.getElementById('dailySchedule').style.display = 'none';
        }

        function showEventForm() {
            document.getElementById('modalOptions').style.display = 'none';
            document.getElementById('eventForm').style.display = 'block';
            document.getElementById('dailySchedule').style.display = 'none';

            // Alapértelmezett dátum beállítása
            const selectedDate = document.getElementById('selectedDate').value;
            document.getElementById('startDate').value = selectedDate;
            document.getElementById('endDate').value = selectedDate;
            
            // Minimum dátum beállítása
            document.getElementById('startDate').min = selectedDate;
            document.getElementById('endDate').min = selectedDate;

            // Ellenőrizzük, hogy a felhasználó munkás-e
            const isWorker = <?php echo $is_worker ? 'true' : 'false'; ?>;
            if (isWorker) {
                // Ha munkás, akkor csak a saját magát választhatja
                const userSelect = document.getElementById('userSelect');
                if (userSelect) {
                    userSelect.style.display = 'none';
                    const currentUserId = <?php echo $_SESSION['user_id']; ?>;
                    const currentUserName = <?php echo json_encode($_SESSION['user_name']); ?>;
                    userSelect.innerHTML = `
                        <div class="form-group">
                            <label>${<?php echo json_encode(translate('Dolgozó')); ?>}:</label>
                            <div class="selected-user">
                                <input type="hidden" name="user_ids[]" value="${currentUserId}">
                                <span>${currentUserName}</span>
                            </div>
                        </div>
                    `;
                }
            }
        }

        function showDailySchedule() {
            document.getElementById('modalOptions').style.display = 'none';
            document.getElementById('eventForm').style.display = 'none';
            document.getElementById('dailySchedule').style.display = 'block';

            const selectedDate = document.getElementById('selectedDate').value;
            const dailyScheduleDiv = document.getElementById('dailySchedule');
            const timelineDiv = dailyScheduleDiv.querySelector('.daily-timeline');
            const dateDisplay = document.getElementById('selectedDateDisplaySchedule');
            
            // Dátum formázása és megjelenítése
            const formattedDate = new Date(selectedDate).toLocaleDateString('hu-HU', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            dateDisplay.textContent = formattedDate;
            
            // Szűrjük az eseményeket a kiválasztott dátumra
            const dayEvents = events.filter(event => {
                const eventStartDate = new Date(event.start_date);
                const eventEndDate = new Date(event.end_date);
                const selectedDateTime = new Date(selectedDate);
                
                // Csak azokat az eseményeket mutatjuk, amelyek a kiválasztott napon történnek
                return selectedDateTime >= eventStartDate && selectedDateTime <= eventEndDate;
            });

            // Időpontok generálása (00:00-tól 23:00-ig)
            let timelineHTML = '';
            for (let hour = 0; hour < 24; hour++) {
                const hourFormatted = hour.toString().padStart(2, '0') + ':00';
                const currentDateTime = new Date(selectedDate);
                currentDateTime.setHours(hour, 0, 0, 0);

                // Szűrjük az eseményeket az adott órára
                const eventsInHour = dayEvents.filter(event => {
                    const eventStartDate = new Date(event.start_date);
                    const eventEndDate = new Date(event.end_date);
                    
                    // Ha munka esemény
                    if (event.event_type === 'work') {
                        const startHour = eventStartDate.getHours();
                        const endHour = eventEndDate.getHours();
                        
                        // Ha ez az első nap
                        if (eventStartDate.toDateString() === currentDateTime.toDateString()) {
                            return hour >= startHour;
                        }
                        // Ha ez az utolsó nap
                        else if (eventEndDate.toDateString() === currentDateTime.toDateString()) {
                            return hour <= endHour;
                        }
                        // Ha köztes nap
                        else if (currentDateTime > eventStartDate && currentDateTime < eventEndDate) {
                            return true;
                        }
                    }
                    // Ha szabadság vagy betegség
                    else {
                        return true; // Egész nap megjelenik
                    }

                    return false;
                });

                // Eltávolítjuk a duplikált eseményeket
                const uniqueEvents = [];
                eventsInHour.forEach(event => {
                    if (!uniqueEvents.some(e => 
                        e.event_type === event.event_type && 
                        e.workers === event.workers && 
                        (e.event_type === 'work' ? e.project_name === event.project_name : true)
                    )) {
                        uniqueEvents.push(event);
                    }
                });

                timelineHTML += `
                    <div class="timeline-hour">
                        <div class="hour-label">
                            ${hourFormatted}
                        </div>
                        <div class="hour-events">
                            ${uniqueEvents.map(event => {
                                let icon = '';
                                let bgColor = '';
                                let eventText = '';

                                // Esemény típus alapján állítjuk be a színt és ikont
                                if (event.event_type === 'work') {
                                    icon = '💼';
                                    bgColor = typeColors[event.project_type] || '#3498db';
                                    eventText = event.project_name;
                                } else if (event.event_type === 'vacation') {
                                    icon = '🌴';
                                    bgColor = '#2ecc71';
                                    eventText = event.workers + ' - ' + <?php echo json_encode(translate('Szabadság')); ?>;
                                } else if (event.event_type === 'sick') {
                                    icon = '🏥';
                                    bgColor = '#e74c3c';
                                    eventText = event.workers + ' - ' + <?php echo json_encode(translate('Betegállomány')); ?>;
                                } else if (event.event_type === 'pending') {
                                    icon = '⏳';
                                    bgColor = '#f1c40f';
                                    eventText = event.workers + ' - ' + <?php echo json_encode(translate('Függőben')); ?>;
                                }

                                // Tooltip szöveg összeállítása
                                let tooltipText = '';
                                if (event.event_type === 'work') {
                                    const startTime = new Date(event.start_date).toLocaleTimeString('hu-HU', { hour: '2-digit', minute: '2-digit' });
                                    const endTime = new Date(event.end_date).toLocaleTimeString('hu-HU', { hour: '2-digit', minute: '2-digit' });
                                    tooltipText = `${event.project_name}\n${event.workers}\n${new Date(event.start_date).toLocaleDateString('hu-HU')} ${startTime} - ${new Date(event.end_date).toLocaleDateString('hu-HU')} ${endTime}`;
                                } else {
                                    tooltipText = `${event.workers}\n${new Date(event.start_date).toLocaleDateString('hu-HU')} - ${new Date(event.end_date).toLocaleDateString('hu-HU')}`;
                                }

                                return `
                                    <div class="time-slot ${event.event_type}" style="
                                        background-color: ${bgColor};
                                        color: white;
                                        padding: 4px 8px;
                                        border-radius: 4px;
                                        font-size: 0.85rem;
                                        cursor: pointer;
                                        display: inline-flex;
                                        align-items: center;
                                        margin: 2px;
                                    " title="${tooltipText}">
                                        ${icon} ${eventText}
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `;
            }
            
            timelineDiv.innerHTML = timelineHTML;
        }

        function backToOptions() {
            // Modal szekciók kezelése
            document.getElementById('modalOptions').style.display = 'block';
            document.getElementById('eventForm').style.display = 'none';
            document.getElementById('dailySchedule').style.display = 'none';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            // Fejléc blur eltávolítása
            const header = document.querySelector('.main-header');
            if (header) header.classList.remove('blurred');
        }

        // Dátum validáció
        document.getElementById('endDate').addEventListener('change', function() {
            const startDate = document.getElementById('startDate').value;
            const endDate = this.value;
            
            if (startDate && endDate && startDate > endDate) {
                alert(<?php echo json_encode(translate('A befejező dátumnak későbbinek kell lennie, mint a kezdő dátum!')); ?>);
                this.value = startDate;
            }
        });

        document.getElementById('startDate').addEventListener('change', function() {
            const endDate = document.getElementById('endDate');
            endDate.min = this.value;
            if (endDate.value && endDate.value < this.value) {
                endDate.value = this.value;
            }
        });

        // Form elküldés kezelése
        document.getElementById('leaveForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Disable submit button to prevent double submission
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            
            const formData = new FormData(this);
            const eventType = formData.get('event_type');
            
            // Convert event_type to status_id (4 for vacation, 5 for sick leave)
            const statusId = eventType === 'vacation' ? 4 : 5;
            formData.delete('event_type');
            formData.append('status_type', statusId);
            
            try {
                const response = await fetch('save_calendar_event.php', {
                    method: 'POST',
                    body: formData
                });
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('A szerver nem JSON választ küldött vissza');
                }
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    showNotification(result.message, 'success');
                    
                    // Close modal
                    closeModal('eventModal');
                    
                    // Reset form
                    this.reset();
                    
                    // Immediately reload the page
                    window.location.reload();
                } else {
                    if (result.notification) {
                        showNotification(result.notification.message, result.notification.type);
                    } else {
                        showNotification(result.message || <?php echo json_encode(translate('Hiba történt a kérelem mentése során.')); ?>, 'error');
                    }
                    submitButton.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification(error.message || <?php echo json_encode(translate('Hiba történt a kérelem feldolgozása során.')); ?>, 'error');
                submitButton.disabled = false;
            }
        });

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const messageElement = document.getElementById('notification-message');
            const icon = notification.querySelector('i');
            
            // Ikon beállítása
            icon.className = `fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}`;
            
            // Üzenet beállítása
            messageElement.textContent = message;
            
            // Stílus beállítása
            notification.className = `notification ${type}`;
            
            // Megjelenítés
            notification.classList.add('show');
            
            // Automatikus elrejtés
            setTimeout(() => {
                notification.classList.add('hide');
                setTimeout(() => {
                    notification.classList.remove('show', 'hide');
                }, 500);
            }, 3000);
        }

        let currentRole = ''; // Globális változó a kiválasztott szerepkörhöz

        function toggleRoleFilter() {
            const dropdown = document.getElementById('roleFilterDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        function selectRole(role) {
            currentRole = role; // Mentjük a kiválasztott szerepkört
            
            const options = document.querySelectorAll('.role-option');
            const activeFilter = document.querySelector('.active-filter');
            
            options.forEach(option => {
                option.classList.remove('active');
                if (option.textContent === (role || <?php echo json_encode(translate('Összes szerepkör')); ?>)) {
                    option.classList.add('active');
                }
            });

            // Aktív szűrő megjelenítése/elrejtése
            if (role && role !== <?php echo json_encode(translate('Összes szerepkör')); ?>) {
                activeFilter.innerHTML = `${role}<span class="remove-filter" onclick="selectRole('')">&times;</span>`;
                activeFilter.classList.add('show');
            } else {
                activeFilter.classList.remove('show');
            }
            
            filterMembers();
            toggleRoleFilter();
        }

        function filterMembers() {
            const searchTerm = document.getElementById('memberSearch').value.toLowerCase();
            const memberCards = document.querySelectorAll('.team-member-option');
            
            memberCards.forEach(card => {
                const searchData = card.dataset.search.toLowerCase();
                const roles = card.dataset.roles.split(',').map(role => role.trim());
                
                // Szerepkör szűrés
                const roleMatch = !currentRole || roles.includes(currentRole);
                // Név szűrés
                const nameMatch = searchData.includes(searchTerm);
                
                card.style.display = (roleMatch && nameMatch) ? 'block' : 'none';
            });
        }

        // Keresési eseménykezelő
        const memberSearchInput = document.getElementById('memberSearch');
        if (memberSearchInput) {
            memberSearchInput.addEventListener('input', filterMembers);
        }

        // Dokumentum kattintás eseménykezelő a dropdown elrejtéséhez
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('roleFilterDropdown');
            const filterBtn = event.target.closest('.filter-btn');
            const roleOption = event.target.closest('.role-option');
            
            if (dropdown && !filterBtn && !roleOption && dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
            }
        });

        // Naptár inicializálása az oldal betöltésekor
        initCalendar();

        // Modal bezárása kattintásra a háttéren
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('eventModal');
            if (event.target == modal) {
                closeModal('eventModal');
            }
        });

        function toggleFilters() {
            const filterContainer = document.querySelector('.calendar-filters');
            filterContainer.classList.toggle('collapsed');
        }

        // Szűrő gombok kezelése
        document.querySelectorAll('.filter-button').forEach(button => {
            button.addEventListener('click', function() {
                // Aktív osztály eltávolítása az összes gombról
                document.querySelectorAll('.filter-button').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Aktív osztály hozzáadása a kiválasztott gombhoz
                this.classList.add('active');
                
                // Szűrő beállítása és naptár frissítése
                currentFilter = this.dataset.filter;
                initCalendar();
            });
        });
    </script>
</body>
</html>