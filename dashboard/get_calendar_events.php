<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Check if user is admin (Cég tulajdonos or Manager)
$user_roles = explode(',', $_SESSION['user_role']);
$is_admin = false;
foreach ($user_roles as $role) {
    $role = trim($role);
    if ($role === 'Cég tulajdonos' || $role === 'Manager') {
        $is_admin = true;
        break;
    }
}

try {
    // Alapvető események lekérdezése (munkák, stb.)
    $base_sql = "SELECT 
        ce.id,
        ce.title,
        ce.description,
        ce.start_date as start,
        ce.end_date as end,
        ce.status_id,
        s.name as status_name,
        CONCAT(u.firstname, ' ', u.lastname) as user_name,
        ce.is_accepted
    FROM calendar_events ce
    LEFT JOIN status s ON ce.status_id = s.id
    LEFT JOIN user u ON ce.user_id = u.id
    WHERE ce.company_id = ?";

    // Ha admin, akkor csak az elfogadott szabadság/betegállomány kérelmeket látja,
    // valamint az összes többi típusú eseményt
    if ($is_admin) {
        $base_sql .= " AND (
            (ce.status_id NOT IN (4,5)) OR 
            (ce.status_id IN (4,5) AND ce.is_accepted = 1)
        )";
    } else {
        // Ha nem admin, akkor látja:
        // - a saját eseményeit (függetlenül azok státuszától)
        // - más felhasználók nem szabadság/betegállomány típusú eseményeit
        $base_sql .= " AND (
            ce.user_id = ? OR 
            (ce.user_id != ? AND ce.status_id NOT IN (4,5))
        )";
    }

    $stmt = $pdo->prepare($base_sql);
    
    if ($is_admin) {
        $stmt->execute([$_SESSION['company_id']]);
    } else {
        $stmt->execute([
            $_SESSION['company_id'],
            $_SESSION['user_id'],
            $_SESSION['user_id']
        ]);
    }
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Események formázása a naptár számára
    $formatted_events = array_map(function($event) {
        $color = '';
        switch($event['status_id']) {
            case 1: // Elérhető
                $color = '#28a745'; // zöld
                break;
            case 2: // Munkában
                $color = '#007bff'; // kék
                break;
            case 3: // Lefoglalt
                $color = '#ffc107'; // sárga
                break;
            case 4: // Szabadság
                if ($event['is_accepted'] === null) {
                    $color = '#6c757d'; // szürke (függőben)
                } else if ($event['is_accepted'] == 1) {
                    $color = '#17a2b8'; // világoskék (elfogadva)
                } else {
                    $color = '#dc3545'; // piros (elutasítva)
                }
                break;
            case 5: // Betegállomány
                if ($event['is_accepted'] === null) {
                    $color = '#6c757d'; // szürke (függőben)
                } else if ($event['is_accepted'] == 1) {
                    $color = '#e83e8c'; // rózsaszín (elfogadva)
                } else {
                    $color = '#dc3545'; // piros (elutasítva)
                }
                break;
            default:
                $color = '#6c757d'; // szürke
        }

        $title = $event['title'];
        if ($event['user_name']) {
            $title .= ' - ' . $event['user_name'];
        }
        if ($event['status_id'] == 4 || $event['status_id'] == 5) {
            if ($event['is_accepted'] === null) {
                $title .= ' (Függőben)';
            } else if ($event['is_accepted'] == 0) {
                $title .= ' (Elutasítva)';
            }
        }

        return [
            'id' => $event['id'],
            'title' => $title,
            'description' => $event['description'],
            'start' => $event['start'],
            'end' => $event['end'],
            'color' => $color,
            'status_id' => $event['status_id'],
            'status_name' => $event['status_name'],
            'is_accepted' => $event['is_accepted']
        ];
    }, $events);

    echo json_encode($formatted_events);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 