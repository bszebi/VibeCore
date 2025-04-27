<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
require_once '../includes/db.php';

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve és van-e company_id-ja
if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    header('Location: ../error.php?msg=unauthorized');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Ellenőrizzük a felhasználó szerepköreit
    $user_roles = explode(',', $_SESSION['user_role']);
    $is_admin = false;
    foreach ($user_roles as $role) {
        if (trim($role) === 'Cég tulajdonos' || trim($role) === 'Manager') {
            $is_admin = true;
            break;
        }
    }
    
    // Lekérjük a csapattagokat és az előfizetési limitet az új logikával
    $stmt = $db->prepare("
        SELECT 
            u.*,
            GROUP_CONCAT(DISTINCT r.role_name) as roles,
            MIN(s.name) as status_name,
            DATE_FORMAT(u.connect_date, '%Y. %m. %d.') as formatted_date,
            CASE 
                WHEN sm.modification_reason IS NOT NULL THEN 
                    CASE
                        WHEN sm.modification_reason REGEXP '^[0-9]+ felhasználó' THEN 
                            CAST(SUBSTRING_INDEX(sm.modification_reason, ' felhasználó', 1) AS UNSIGNED)
                        WHEN sm.modification_reason REGEXP '^[0-9]+ felhasználó, [0-9]+ eszköz' THEN 
                            CAST(SUBSTRING_INDEX(sm.modification_reason, ' felhasználó', 1) AS UNSIGNED)
                        ELSE 
                            CASE 
                                WHEN sp.name IN ('alap', 'alap_eves') THEN 5
                                WHEN sp.name IN ('kozepes', 'kozepes_eves') THEN 10
                                WHEN sp.name IN ('uzleti', 'uzleti_eves') THEN 20
                                WHEN sp.name = 'free-trial' THEN 2
                                ELSE 5
                            END
                    END
                ELSE 
                    CASE 
                        WHEN sp.name IN ('alap', 'alap_eves') THEN 5
                        WHEN sp.name IN ('kozepes', 'kozepes_eves') THEN 10
                        WHEN sp.name IN ('uzleti', 'uzleti_eves') THEN 20
                        WHEN sp.name = 'free-trial' THEN 2
                        ELSE 5
                    END
            END as user_limit,
            (SELECT COUNT(*) FROM user WHERE company_id = u.company_id) as current_user_count,
            MIN(sp.name) as plan_name,
            MIN(sm.modification_reason) as modification_reason,
            MIN(sm.modification_date) as modification_date,
            MIN(sub.subscription_status_id) as subscription_status_id
        FROM user u
        LEFT JOIN user_to_roles utr ON u.id = utr.user_id
        LEFT JOIN roles r ON utr.role_id = r.id
        LEFT JOIN status s ON u.current_status_id = s.id
        LEFT JOIN subscriptions sub ON sub.company_id = u.company_id AND sub.subscription_status_id = 1
        LEFT JOIN subscription_plans sp ON sub.subscription_plan_id = sp.id
        LEFT JOIN (
            SELECT 
                subscription_id, 
                modification_reason,
                modification_date
            FROM subscription_modifications
            WHERE modification_reason LIKE '% felhasználó%'
            ORDER BY modification_date DESC
            LIMIT 1
        ) sm ON sm.subscription_id = sub.id
        WHERE u.company_id = (
            SELECT company_id 
            FROM user 
            WHERE id = :user_id
        )
        GROUP BY u.id
        ORDER BY 
            CASE 
                WHEN EXISTS (
                    SELECT 1 
                    FROM user_to_roles ur2 
                    JOIN roles r2 ON ur2.role_id = r2.id 
                    WHERE ur2.user_id = u.id AND r2.role_name = 'Cég tulajdonos'
                ) THEN 0 
                ELSE 1 
            END,
            u.firstname
    ");
    
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Felhasználói limit kiszámítása az új logikával
    $user_limit = isset($team_members[0]['user_limit']) ? (int)$team_members[0]['user_limit'] : 0;
    $current_user_count = $team_members[0]['current_user_count'] ?? 0;
    $can_invite = $current_user_count < $user_limit;
    
    // Debug információk logolása
    error_log('User Limit Info:');
    error_log('Plan Name: ' . ($team_members[0]['plan_name'] ?? 'N/A'));
    error_log('User Limit: ' . $user_limit);
    error_log('Current User Count: ' . $current_user_count);
    error_log('Last Modification: ' . ($team_members[0]['modification_reason'] ?? 'No modification'));
    error_log('Modification Date: ' . ($team_members[0]['modification_date'] ?? 'N/A'));
    
} catch (PDOException $e) {
    $error = 'Adatbázis hiba: ' . $e->getMessage();
    error_log('Database Error in csapat.php: ' . $e->getMessage());
}

require_once '../includes/layout/header.php';
?>

<style>
    h1.main-title {
        text-align: center;
        padding: 0;
        margin: 10px 0;
        color: #2c3e50;
        font-size: 3rem;
        font-weight: 600;
        margin-bottom: 160px;
        position: relative;
        margin-top: -100px;
    }

    .team-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 2rem;
        margin-right: 250px;
        margin-top: 80px;
        margin-bottom: 20px;
        clear: both;
        position: relative;
    }

    .team-grid {
        display: grid;
        grid-template-columns: repeat(5, 300px);
        gap: 3.5rem;
        justify-content: start;
        margin-top: 0.5rem;
        margin-left: 2rem;
    }

    .team-container h1 {
        display: none;
    }

    .team-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        min-height: 380px;
        width: 100%;
        border: 2px solid transparent;
        transition: border-color 0.3s ease;
        position: relative;
    }

    .team-card.current-user {
        border-color: #3498db;
        box-shadow: 0 2px 15px rgba(52, 152, 219, 0.2);
    }

    .member-image-container {
        width: 120px;
        height: 120px;
        position: relative;
        margin-bottom: 1.5rem;
    }

    .member-image {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .status-indicator {
        position: absolute;
        bottom: 5px;
        right: 5px;
        width: 15px;
        height: 15px;
        border-radius: 50%;
        border: 2px solid white;
        background-color: #ccc;
    }

    /* Státusz színek */
    .status-indicator.elérhető { background-color: #2ecc71; }
    .status-indicator.munkában { background-color: #3498db; }
    .status-indicator.lefoglalt { background-color: #f1c40f; }
    .status-indicator.szabadság { background-color: #e67e22; }
    .status-indicator.betegállomány { background-color: #e74c3c; }

    .member-name {
        font-size: 1.2rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }

    .role-badge {
        background: #3498db;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
        margin: 0.5rem 0;
    }

    .status-badge {
        background: #f1f1f1;
        color: #666;
        padding: 0.4rem 0.8rem;
        border-radius: 15px;
        font-size: 0.85rem;
        margin: 0.5rem 0 1rem 0;
    }

    .member-contact {
        width: 100%;
        margin: 1rem 0;
    }

    .member-contact p {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        margin: 0.5rem 0;
        color: #666;
        font-size: 0.9rem;
    }

    .member-contact i, .member-contact img {
        color: #3498db;
        font-size: 1rem;
        width: 16px;
        height: 16px;
        object-fit: contain;
    }

    .member-join-date {
        color: #888;
        font-size: 0.8rem;
        margin-top: auto;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    /* Responsive design - Desktop first approach */
    @media (max-width: 1600px) {
        .team-grid {
            grid-template-columns: repeat(4, 300px);
        }
    }

    @media (max-width: 1300px) {
        .team-grid {
            grid-template-columns: repeat(3, 300px);
        }
    }

    @media (max-width: 1000px) {
        .team-grid {
            grid-template-columns: repeat(2, 300px);
        }
        .team-container {
            margin-right: 200px;
        }
    }

    @media (max-width: 768px) {
        h1.main-title {
            font-size: 2rem;
            margin-bottom: 80px;
            margin-top: 20px;
            padding: 0 20px;
        }

        .team-container {
            margin: 1rem;
            padding: 0 1rem;
            margin-right: 0;
        }

        .subscription-info {
            position: relative;
            top: auto;
            left: auto;
            margin: 20px auto;
            max-width: 90%;
            text-align: center;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .team-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-left: 0;
            margin-top: 20px;
        }

        .team-actions {
            position: relative;
            top: 0;
            right: 0;
            justify-content: center;
            margin: 20px 0;
            padding: 0;
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 1rem;
        }

        .right-side-buttons {
            float: none;
            margin: 0;
        }

        .filter-container {
            width: auto;
            margin: 0;
        }

        .team-card {
            min-height: 350px;
        }
        .member-image-container {
            width: 100px;
            height: 100px;
        }
    }

    @media (max-width: 576px) {
        h1.main-title {
            font-size: 1.8rem;
            margin-bottom: 60px;
            margin-top: 10px;
        }

        .team-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .subscription-info {
            margin: 15px auto;
            padding: 12px;
            font-size: 0.9rem;
        }

        .subscription-info::before {
            content: attr(data-count);
            display: block;
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
        }

        .subscription-info span {
            display: none;
        }

        .team-actions {
            flex-direction: row;
            align-items: center;
            gap: 0.8rem;
            justify-content: center;
        }

        .action-button {
            width: auto;
            padding: 12px;
            justify-content: center;
        }

        .action-button span {
            display: none;
        }

        .action-button i {
            margin: 0;
            font-size: 1.2rem;
        }

        .team-card {
            min-height: 320px;
        }
        .member-image-container {
            width: 90px;
            height: 90px;
        }
        .member-name {
            font-size: 1.1rem;
        }
        .role-badge, .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        .invitation-modal {
            width: 95%;
            padding: 1.5rem;
        }
        .modal-content {
            width: 95%;
            margin: 10% auto;
        }
        .tooltip .tooltiptext {
            width: 200px;
        }
    }

    .team-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        padding: 1rem 2rem;
        position: absolute;
        top: 100px;
        right: 20px;
    }

    .action-button {
        background: #3498db;
        border: none;
        padding: 0.8rem 1.5rem;
        border-radius: 8px;
        color: #fff;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .action-button:hover {
        background: #2980b9;
        transform: translateY(-2px);
    }

    .card-menu {
        position: absolute;
        top: 10px;
        right: 10px;
        cursor: pointer;
        padding: 5px;
        z-index: 10;
    }

    .menu-dots {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background-color 0.3s;
    }

    .menu-dots:hover {
        background-color: rgba(0,0,0,0.1);
    }

    .menu-dots i {
        color: #666;
        font-size: 16px;
    }

    .card-menu-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        min-width: 150px;
        display: none;
        z-index: 100;
    }

    .card-menu-dropdown.show {
        display: block;
    }

    .menu-item {
        padding: 8px 15px;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #333;
        transition: background-color 0.3s;
        cursor: pointer;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
    }

    .menu-item:hover {
        background-color: #f5f6fa;
    }

    .menu-item.delete {
        color: #e74c3c;
    }

    .menu-item i {
        font-size: 14px;
    }

    .context-menu {
        position: fixed;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        min-width: 150px;
        display: none;
        z-index: 1000;
    }

    .context-menu.show {
        display: block;
    }

    .context-menu .menu-item {
        padding: 8px 15px;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #333;
        transition: background-color 0.3s;
        cursor: pointer;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
    }

    .context-menu .menu-item:hover {
        background-color: #f5f6fa;
    }

    .context-menu .menu-item.delete {
        color: #e74c3c;
    }

    .context-menu .menu-item i {
        font-size: 14px;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        z-index: 1000;
    }

    .modal-content {
        background-color: white;
        margin: 15% auto;
        padding: 20px;
        border-radius: 8px;
        width: 80%;
        max-width: 500px;
        position: relative;
        transition: all 0.3s ease;
    }

    .close {
        position: absolute;
        right: 20px;
        top: 10px;
        font-size: 28px;
        cursor: pointer;
    }

    .form-group {
        margin: 20px 0;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #2c3e50;
    }

    .form-group select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .btn-save {
        background: #3498db;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.3s;
    }

    .btn-save:hover {
        background: #2980b9;
    }

    #confirmationContainer {
        text-align: center;
        padding: 20px;
    }

    .highlight-role, .highlight-name {
        font-weight: bold;
        color: #3498db;
    }

    .confirmation-buttons {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 30px;
    }

    .btn-confirm, .btn-cancel {
        padding: 10px 30px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-confirm {
        background: #2ecc71;
        color: white;
    }

    .btn-confirm:hover {
        background: #27ae60;
        transform: translateY(-2px);
    }

    .btn-cancel {
        background: #e74c3c;
        color: white;
    }

    .btn-cancel:hover {
        background: #c0392b;
        transform: translateY(-2px);
    }

    .success-message {
        position: fixed;
        top: 100px;
        right: 20px;
        background: #2ecc71;
        color: white;
        padding: 1rem 2rem;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1002;
        display: none;
        animation: slideIn 0.3s ease-out;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .success-message i {
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .success-message span {
        font-size: 1rem;
        line-height: 1.2;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .warning-text {
        color: #e74c3c;
        font-size: 0.9rem;
        margin: 1rem 0;
    }

    .btn-delete {
        background: #e74c3c;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-delete:hover {
        background: #c0392b;
    }

    .filter-container {
        position: relative;
        margin: 20px 0;
        
    }

    .filter {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(0, 0, 0, 0.192);
        cursor: pointer;
        box-shadow: 0px 10px 10px rgba(0, 0, 0, 0.021);
        transition: all 0.3s;
        background: white;
    }

    .filter svg {
        height: 16px;
        fill: rgb(77, 77, 77);
        transition: all 0.3s;
    }

    .filter:hover {
        box-shadow: 0px 10px 10px rgba(0, 0, 0, 0.11);
        background-color: rgb(59, 59, 59);
    }

    .filter:hover svg {
        fill: white;
    }

    .filter-menu {
        display: none;
        position: absolute;
        top: 60px;
        left: 0;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        min-width: 200px;
    }

    .filter-menu.show {
        display: block;
    }

    .filter-option {
        padding: 12px 20px;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .filter-option:hover {
        background: #f5f6fa;
    }

    .filter-option i {
        width: 20px;
        text-align: center;
    }

    .filter-header {
        background: #f8f9fa;
        padding: 15px 20px;
        margin: 20px 0 10px 0;
        border-radius: 8px;
        font-weight: 600;
        color: #2c3e50;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .team-row {
        margin-bottom: 20px;
        display: grid;
        grid-template-columns: repeat(5, 300px);
        gap: 3.5rem;
        justify-content: start;
        margin-left: 2rem;
    }

    .team-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .team-actions {
        display: flex;
        gap: 1rem;
    }

    .action-button {
        background: #3498db;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        transition: background-color 0.3s;
    }

    .action-button:hover {
        background: #2980b9;
    }

    .filter-container {
        position: relative;
        margin-top: 0px;
    }

    .right-side-buttons {
        position: fixed;
        right: 20px;
        top: 200px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        z-index: 99;
        width: 200px;
    }

    .action-button, .filter {
        transition: transform 0.2s ease;
    }

    .action-button:hover, .filter:hover {
        transform: translateX(-5px);
    }

    .filter-container {
        position: relative;
        width: 200px;
        display: flex;
        justify-content: flex-end;
    }

    .filter {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(0, 0, 0, 0.192);
        cursor: pointer;
        box-shadow: 0px 10px 10px rgba(0, 0, 0, 0.021);
        transition: all 0.3s;
        background: white;
        position: relative;
    }

    .filter-menu {
        position: absolute;
        top: 60px;
        right: 0;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        min-width: 200px;
        display: none;
        z-index: 1000;
    }

    .filter-menu.show {
        display: block;
    }

    /* Invitation Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        backdrop-filter: blur(5px);
    }

    .invitation-modal {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        z-index: 1001;
        width: 90%;
        max-width: 500px;
    }

    .invitation-modal h2 {
        color: #2c3e50;
        margin-bottom: 1.5rem;
        text-align: center;
    }

    .invitation-form {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-group label {
        color: #2c3e50;
        font-weight: 500;
    }

    .form-group input,
    .form-group select {
        padding: 0.8rem;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 1rem;
        transition: border-color 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus {
        border-color: #3498db;
        outline: none;
    }

    .form-group input.error {
        border-color: #e74c3c;
    }

    .error-message {
        color: #e74c3c;
        font-size: 0.85rem;
        margin-top: 0.3rem;
        display: none;
    }

    .modal-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .modal-button {
        padding: 0.8rem 1.5rem;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .modal-button.cancel {
        background: #f1f1f1;
        color: #666;
        border: none;
    }

    .modal-button.send {
        background: #3498db;
        color: white;
        border: none;
    }

    .modal-button:hover {
        transform: translateY(-2px);
    }

    .modal-button.cancel:hover {
        background: #e0e0e0;
    }

    .modal-button.send:hover {
        background: #2980b9;
    }

    .animation-container {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1002;
        width: 300px;
        height: 300px;
        display: none;
    }

    .animation-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1001;
        backdrop-filter: blur(5px);
    }

    /* Új stílusok a limit kijelzőhöz és a letiltott gombhoz */
    .subscription-info {
        position: fixed;
        top: 70px;
        left: 20px;
        background: #f8f9fa;
        padding: 15px 20px;
        border-radius: 8px;
        font-weight: 600;
        color: #2c3e50;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        z-index: 1000;
        max-width: 300px;
        border: 1px solid #e9ecef;
        text-align: left;
    }

    .action-button.disabled {
        background-color: #ccc !important;
        cursor: not-allowed;
        opacity: 0.7;
    }

    .tooltip {
        position: relative;
        display: inline-block;
    }

    .tooltip .tooltiptext {
        visibility: hidden;
        width: 250px;
        background-color: #333;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 10px;
        position: absolute;
        z-index: 1000;
        top: 150%;
        left: 50%;
        transform: translateX(-50%);
        opacity: 0;
        transition: opacity 0.3s;
        white-space: normal;
        line-height: 1.4;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

    .tooltip .tooltiptext::before {
        content: "";
        position: absolute;
        bottom: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: transparent transparent #333 transparent;
    }

    .tooltip:hover .tooltiptext {
        visibility: visible;
        opacity: 1;
    }
</style>

<h1 class="main-title"><?php echo translate('Csapatunk'); ?></h1>

<!-- Felhasználói limit kijelzése -->
<?php if ($is_admin): ?>
<div class="subscription-info" data-count="<?php echo $current_user_count; ?>/<?php echo $user_limit; ?>">
    <?php echo translate('A felvehető felhasználók'); ?>: <?php echo $current_user_count; ?>/<?php echo $user_limit; ?>
</div>
<?php endif; ?>

<?php if ($is_admin): ?>
<div class="team-actions">
    <?php if ($can_invite): ?>
    <button onclick="openInvitationModal()" class="action-button">
        <i class="fas fa-user-plus"></i>
        <span><?php echo translate('Új tag hozzáadása'); ?></span>
    </button>
    <?php else: ?>
    <div class="tooltip">
        <button class="action-button disabled" disabled>
            <i class="fas fa-user-plus"></i>
            <span><?php echo translate('Új tag hozzáadása'); ?></span>
        </button>
        <span class="tooltiptext">
            <?php echo translate('A csapat létszáma elérte a maximális ' . $user_limit . ' főt. A limit növeléséhez váltson magasabb csomagra vagy módosítsa jelenlegi előfizetését.'); ?>
        </span>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="team-container">
    <div class="right-side-buttons">
        <div class="filter-container">
            <button title="<?php echo translate('Szűrő'); ?>" class="filter" onclick="toggleFilterMenu()">
                <svg viewBox="0 0 512 512" height="1em">
                    <path d="M0 416c0 17.7 14.3 32 32 32l54.7 0c12.3 28.3 40.5 48 73.3 48s61-19.7 73.3-48L480 448c17.7 0 32-14.3 32-32s-14.3-32-32-32l-246.7 0c-12.3-28.3-40.5-48-73.3-48s-61 19.7-73.3 48L32 384c-17.7 0-32 14.3-32 32zm128 0a32 32 0 1 1 64 0 32 32 0 1 1 -64 0zM320 256a32 32 0 1 1 64 0 32 32 0 1 1 -64 0zm32-80c-32.8 0-61 19.7-73.3 48L32 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l246.7 0c12.3 28.3 40.5 48 73.3 48s61-19.7 73.3-48l54.7 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-54.7 0c-12.3-28.3-40.5-48-73.3-48zM192 128a32 32 0 1 1 0-64 32 32 0 1 1 0 64zm73.3-64C253 35.7 224.8 16 192 16s-61 19.7-73.3 48L32 64C14.3 64 0 78.3 0 96s14.3 32 32 32l86.7 0c12.3 28.3 40.5 48 73.3 48s61-19.7 73.3-48L480 128c17.7 0 32-14.3 32-32s-14.3-32-32-32L265.3 64z"></path>
                </svg>
            </button>
            <div class="filter-menu" id="filterMenu">
                <div class="filter-option" onclick="applyFilter('default')">
                    <i class="fas fa-undo"></i> <?php echo translate('Alapértelmezett'); ?>
                </div>
                <div class="filter-option" onclick="applyFilter('role')">
                    <i class="fas fa-user-tag"></i> <?php echo translate('Szerepkör szerint'); ?>
                </div>
                <div class="filter-option" onclick="applyFilter('status')">
                    <i class="fas fa-circle"></i> <?php echo translate('Státusz szerint'); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="team-grid">
        <?php foreach ($team_members as $member): ?>
            <div class="team-card <?php echo ($member['id'] === $_SESSION['user_id']) ? 'current-user' : ''; ?>" 
                 data-user-id="<?php echo $member['id']; ?>">
                <?php if ($is_admin && $member['id'] !== $_SESSION['user_id']): ?>
                <div class="card-menu">
                    <div class="menu-dots" onclick="toggleCardMenu(this)">
                        <i class="fas fa-ellipsis-v"></i>
                    </div>
                    <div class="card-menu-dropdown">
                        <button class="menu-item" onclick="openEditRoleModal(<?php echo $member['id']; ?>)">
                            <i class="fas fa-edit"></i>
                            <?php echo translate('Szerkesztés'); ?>
                        </button>
                        <button class="menu-item delete" onclick="confirmRemoveUser(<?php echo $member['id']; ?>)">
                            <i class="fas fa-trash-alt"></i>
                            <?php echo translate('Eltávolítás'); ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="member-image-container">
                    <img src="<?php 
                        $profile_pic = $member['profile_pic'] ?? 'user.png';
                        echo file_exists('../uploads/profiles/' . $profile_pic) 
                            ? '../uploads/profiles/' . $profile_pic 
                            : '../assets/img/user.png';
                    ?>" alt="<?php echo htmlspecialchars($member['lastname'] . ' ' . $member['firstname']); ?>" 
                    class="member-image">
                    <div class="status-indicator <?php echo strtolower($member['status_name']); ?>" 
                         title="<?php echo htmlspecialchars($member['status_name']); ?>">
                    </div>
                    <span class="status-text" style="display: none;">
                        <?php echo htmlspecialchars(translate($member['status_name'])); ?>
                    </span>
                </div>
                
                <h3 class="member-name">
                    <?php echo htmlspecialchars($member['lastname'] . ' ' . $member['firstname']); ?>
                </h3>
                
                <?php if (!empty($member['roles'])): ?>
                    <div class="role-badge">
                        <?php 
                        $roles = explode(',', $member['roles']);
                        echo htmlspecialchars(translate(trim($roles[0])));
                        ?>
                    </div>
                <?php endif; ?>

                <div class="status-badge">
                    <?php echo htmlspecialchars(translate($member['status_name'])); ?>
                </div>
                
                <div class="member-contact">
                    <p><i class="fas fa-envelope"></i><?php echo htmlspecialchars($member['email']); ?></p>
                    <?php if (!empty($member['telephone'])): ?>
                        <p>
                            <img src="../assets/img/phone.png" alt="Telefon">
                            <?php echo htmlspecialchars($member['telephone']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="member-join-date">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo translate('Csatlakozott:'); ?> <?php echo $member['formatted_date']; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div> 

<!-- Context menu HTML módosítása -->
<div id="contextMenu" class="context-menu">
    <button class="menu-item" onclick="openEditRoleModal(currentUserId)">
        <i class="fas fa-edit"></i>
        Szerkesztés
    </button>
    <button class="menu-item delete" onclick="confirmRemoveUser(currentUserId)">
        <i class="fas fa-trash-alt"></i>
        Eltávolítás
    </button>
</div>

<!-- Módosított modál HTML -->
<div id="editRoleModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <div id="roleSelectContainer">
            <h2><?php echo translate('Szerepkör módosítása'); ?></h2>
            <form id="editRoleForm">
                <input type="hidden" id="editUserId" name="user_id">
                <div class="form-group">
                    <label for="role"><?php echo translate('Szerepkör:'); ?></label>
                    <select id="roleSelect" name="role" required>
                        <?php
                        $stmt = $db->query("SELECT role_name FROM roles WHERE role_name != 'Cég tulajdonos' ORDER BY role_name");
                        while ($role = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo '<option value="' . htmlspecialchars($role['role_name']) . '">' . 
                                 htmlspecialchars(translate($role['role_name'])) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="btn-save"><?php echo translate('Mentés'); ?></button>
            </form>
        </div>
        <div id="confirmationContainer" style="display: none;">
            <h2><?php echo translate('Megerősítés'); ?></h2>
            <p><?php echo translate('Biztos, hogy'); ?> <span id="selectedRole" class="highlight-role"></span> <?php echo translate('szerepkört szeretne adni'); ?> <span id="selectedUserName" class="highlight-name"></span> <?php echo translate('részére'); ?>?</p>
            <div class="confirmation-buttons">
                <button id="confirmYes" class="btn-confirm"><?php echo translate('Igen'); ?></button>
                <button id="confirmNo" class="btn-cancel"><?php echo translate('Mégsem'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Adjunk hozzá egy új modált az eltávolítás megerősítéséhez -->
<div id="removeConfirmModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeRemoveModal()">&times;</span>
        <div id="removeConfirmContainer">
            <h2><?php echo translate('Felhasználó eltávolítása'); ?></h2>
            <p><?php echo translate('Biztosan el szeretné távolítani'); ?> <span id="removeUserName" class="highlight-name"></span> <?php echo translate('felhasználót a cégből'); ?>?</p>
            <p class="warning-text"><?php echo translate('Ez a művelet nem vonható vissza, és a felhasználó elveszíti hozzáférését a cég adataihoz!'); ?></p>
            <div class="confirmation-buttons">
                <button id="removeConfirmYes" class="btn-delete"><?php echo translate('Igen, eltávolítom'); ?></button>
                <button onclick="closeRemoveModal()" class="btn-cancel"><?php echo translate('Mégsem'); ?></button>
            </div>
        </div>
        <div id="finalConfirmContainer" style="display: none;">
            <h2><?php echo translate('Végső megerősítés'); ?></h2>
            <p><?php echo translate('Tényleg biztosan el szeretné távolítani'); ?> <span id="finalRemoveUserName" class="highlight-name"></span> <?php echo translate('felhasználót'); ?>?</p>
            <div class="confirmation-buttons">
                <button id="finalConfirmYes" class="btn-delete"><?php echo translate('Igen, véglegesen eltávolítom'); ?></button>
                <button onclick="closeRemoveModal()" class="btn-cancel"><?php echo translate('Mégsem'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Add this before the closing body tag -->
<div class="modal-overlay" id="invitationModalOverlay"></div>
<div class="invitation-modal" id="invitationModal">
    <h2><?php echo translate('Új csapattag meghívása'); ?></h2>
    <form class="invitation-form" id="invitationForm">
        <div class="form-group">
            <label for="inviteName"><?php echo translate('Név'); ?></label>
            <input type="text" id="inviteName" name="name" required>
        </div>
        <div class="form-group">
            <label for="inviteEmail"><?php echo translate('Email cím'); ?></label>
            <input type="email" id="inviteEmail" name="email" required>
            <div class="error-message" id="emailError"><?php echo translate('Ez az email cím már foglalt!'); ?></div>
        </div>
        <div class="form-group">
            <label for="inviteRole"><?php echo translate('Szerepkör'); ?></label>
            <select id="inviteRole" name="role" required>
                <option value=""><?php echo translate('Válassz szerepkört'); ?></option>
                <?php
                $stmt = $db->query("SELECT role_name FROM roles WHERE role_name != 'Cég tulajdonos' ORDER BY role_name");
                while ($role = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo '<option value="' . htmlspecialchars($role['role_name']) . '">' . 
                         htmlspecialchars(translate($role['role_name'])) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label for="inviteExpiration"><?php echo translate('Meghívó érvényességi ideje'); ?></label>
            <select id="inviteExpiration" name="expiration" required>
                <option value="24"><?php echo translate('24 óra'); ?></option>
                <option value="48" selected><?php echo translate('48 óra'); ?></option>
                <option value="72"><?php echo translate('72 óra'); ?></option>
                <option value="168"><?php echo translate('1 hét'); ?></option>
            </select>
        </div>
        <div class="modal-buttons">
            <button type="button" class="modal-button cancel" onclick="closeInvitationModal()"><?php echo translate('Mégse'); ?></button>
            <button type="submit" class="modal-button send"><?php echo translate('Meghívás küldése'); ?></button>
        </div>
    </form>
</div>
<div class="success-message" id="successMessage">
    <i class="fas fa-check-circle"></i>
    <span><?php echo translate('Meghívó sikeresen elküldve!'); ?></span>
</div>

<!-- Add animation container and overlay -->
<div class="animation-overlay" id="animationOverlay"></div>
<div class="animation-container" id="animationContainer"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
<script>
// Globális változó a kiválasztott felhasználó ID-jának tárolásához
let currentUserId = null;

function toggleCardMenu(element) {
    // Minden más menü bezárása
    document.querySelectorAll('.card-menu-dropdown.show').forEach(menu => {
        if (!menu.parentElement.contains(element)) {
            menu.classList.remove('show');
        }
    });

    // A kiválasztott menü toggle
    const dropdown = element.nextElementSibling;
    dropdown.classList.toggle('show');

    // Kattintás esemény a dokumentumra a menü bezárásához
    document.addEventListener('click', function closeMenu(e) {
        if (!element.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
            document.removeEventListener('click', closeMenu);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Elrejtjük a success message-et oldal betöltéskor
    const successMessage = document.getElementById('successMessage');
    successMessage.style.display = 'none';

    const contextMenu = document.getElementById('contextMenu');
    
    // Jobb klikk esemény kezelése a kártyákon
    document.querySelectorAll('.team-card').forEach(card => {
        card.addEventListener('contextmenu', function(e) {
            // Csak akkor jelenjen meg, ha a felhasználó tulajdonos és nem a saját kártyája
            if (!card.querySelector('.card-menu')) return;
            
            e.preventDefault(); // Alapértelmezett context menu letiltása
            
            // Felhasználó ID mentése
            currentUserId = card.getAttribute('data-user-id');
            
            // Minden más menü bezárása
            document.querySelectorAll('.card-menu-dropdown.show').forEach(menu => {
                menu.classList.remove('show');
            });
            
            // Context menu pozicionálása
            contextMenu.style.top = `${e.pageY}px`;
            contextMenu.style.left = `${e.pageX}px`;
            contextMenu.classList.add('show');
        });
    });
    
    // Kattintás esemény a dokumentumra a context menu bezárásához
    document.addEventListener('click', function(e) {
        if (!contextMenu.contains(e.target)) {
            contextMenu.classList.remove('show');
        }
    });
    
    // Görgetés esemény a context menu bezárásához
    document.addEventListener('scroll', function() {
        contextMenu.classList.remove('show');
    });
});

const modal = document.getElementById('editRoleModal');
const closeBtn = document.getElementsByClassName('close')[0];

function openEditRoleModal(userId) {
    document.getElementById('editUserId').value = userId;
    
    // Megkeressük a kártyát és lekérjük az aktuális szerepkört
    const card = document.querySelector(`.team-card[data-user-id="${userId}"]`);
    const currentRole = card.querySelector('.role-badge').textContent.trim();
    
    // Beállítjuk az aktuális szerepkört a select mezőben
    const roleSelect = document.getElementById('roleSelect');
    for(let option of roleSelect.options) {
        if(option.value === currentRole) {
            option.selected = true;
            break;
        }
    }
    
    modal.style.display = 'block';
}

closeBtn.onclick = function() {
    const roleSelectContainer = document.getElementById('roleSelectContainer');
    const confirmationContainer = document.getElementById('confirmationContainer');
    
    confirmationContainer.style.display = 'none';
    roleSelectContainer.style.display = 'block';
    modal.style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == modal) {
        const roleSelectContainer = document.getElementById('roleSelectContainer');
        const confirmationContainer = document.getElementById('confirmationContainer');
        
        confirmationContainer.style.display = 'none';
        roleSelectContainer.style.display = 'block';
        modal.style.display = 'none';
    }
}

document.getElementById('editRoleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const roleSelect = document.getElementById('roleSelect');
    const newRole = roleSelect.value;
    const roleSelectContainer = document.getElementById('roleSelectContainer');
    const confirmationContainer = document.getElementById('confirmationContainer');
    const selectedRoleSpan = document.getElementById('selectedRole');
    const selectedUserNameSpan = document.getElementById('selectedUserName');
    
    // Lekérjük a felhasználó nevét
    const userId = document.getElementById('editUserId').value;
    const userCard = document.querySelector(`.team-card[data-user-id="${userId}"]`);
    const userName = userCard.querySelector('.member-name').textContent.trim();
    
    // Beállítjuk a szerepkört és a nevet
    selectedRoleSpan.textContent = newRole;
    selectedUserNameSpan.textContent = userName;
    
    // Elrejtjük a szerepkör választót és megjelenítjük a megerősítést
    roleSelectContainer.style.display = 'none';
    confirmationContainer.style.display = 'block';
});

// Megerősítés kezelése
document.getElementById('confirmYes').addEventListener('click', function() {
    const userId = document.getElementById('editUserId').value;
    const newRole = document.getElementById('roleSelect').value;
    
    updateRole(userId, newRole);
});

document.getElementById('confirmNo').addEventListener('click', function() {
    const roleSelectContainer = document.getElementById('roleSelectContainer');
    const confirmationContainer = document.getElementById('confirmationContainer');
    
    // Visszaállítjuk az eredeti nézetet
    confirmationContainer.style.display = 'none';
    roleSelectContainer.style.display = 'block';
});

function showSuccessMessage(message) {
    const successMessage = document.getElementById('successMessage');
    successMessage.querySelector('span').textContent = message;
    successMessage.style.display = 'flex';
    
    setTimeout(() => {
        successMessage.style.display = 'none';
        if (message.includes(<?php echo json_encode(translate('eltávolítva')); ?>)) {
            location.reload();
        }
    }, 3100);
}

function updateRole(userId, newRole) {
    fetch('../includes/update_role.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}&role=${newRole}`
    })
    .then(response => {
        // Check if response is OK
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Check content type
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Expected JSON but got:', text);
                throw new TypeError("Expected JSON response but got " + contentType);
            });
        }
        
        return response.json();
    })
    .then(data => {
        if (data.success) {
            modal.style.display = 'none';
            
            const userCard = document.querySelector(`.team-card[data-user-id="${userId}"]`);
            const roleBadge = userCard.querySelector('.role-badge');
            roleBadge.textContent = newRole;
            
            showSuccessMessage(<?php echo json_encode(translate('Szerepkör sikeresen módosítva!')); ?>);
        } else {
            // Display more detailed error message if available
            const errorMessage = data.message || data.debug?.message || <?php echo json_encode(translate('Hiba történt a szerepkör módosítása során.')); ?>;
            alert(errorMessage);
            console.error('Server error details:', data.debug || {});
        }
    })
    .catch(error => {
        console.error('Hiba:', error);
        alert(<?php echo json_encode(translate('Hiba történt a szerepkör módosítása során.')); ?>);
    });
}

const removeModal = document.getElementById('removeConfirmModal');
let userToRemove = null;

function closeRemoveModal() {
    removeModal.style.display = 'none';
    document.getElementById('removeConfirmContainer').style.display = 'block';
    document.getElementById('finalConfirmContainer').style.display = 'none';
}

function confirmRemoveUser(userId) {
    userToRemove = userId;
    const userCard = document.querySelector(`.team-card[data-user-id="${userId}"]`);
    const userName = userCard.querySelector('.member-name').textContent.trim();
    
    document.getElementById('removeUserName').textContent = userName;
    document.getElementById('finalRemoveUserName').textContent = userName;
    
    removeModal.style.display = 'block';
}

document.getElementById('removeConfirmYes').addEventListener('click', function() {
    document.getElementById('removeConfirmContainer').style.display = 'none';
    document.getElementById('finalConfirmContainer').style.display = 'block';
});

document.getElementById('finalConfirmYes').addEventListener('click', function() {
    removeUser(userToRemove);
});

function removeUser(userId) {
    fetch('../includes/remove_user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}`,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Server response:', text);
            throw new Error('A szerver nem megfelelő formátumú választ küldött');
        }
        
        if (data.success) {
            closeRemoveModal();
            showSuccessMessage(<?php echo json_encode(translate('Felhasználó sikeresen eltávolítva a cégből')); ?>);
            // Frissítjük az oldalt a változások megjelenítéséhez
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(data.message || <?php echo json_encode(translate('Hiba történt a felhasználó eltávolítása során.')); ?>);
        }
    })
    .catch(error => {
        console.error('Hiba:', error);
        alert(error.message || <?php echo json_encode(translate('Hiba történt a felhasználó eltávolítása során. Kérjük, próbálja újra!')); ?>);
    });
}

function toggleFilterMenu() {
    const filterMenu = document.getElementById('filterMenu');
    filterMenu.classList.toggle('show');
    
    // Kattintás esemény a dokumentumon a menü bezárásához
    document.addEventListener('click', function closeMenu(e) {
        if (!e.target.closest('.filter-container')) {
            filterMenu.classList.remove('show');
            document.removeEventListener('click', closeMenu);
        }
    });
}

function applyFilter(filterType) {
    const container = document.querySelector('.team-grid');
    const cards = Array.from(document.querySelectorAll('.team-card'));
    container.innerHTML = '';

    if (filterType === 'default') {
        // Alapértelmezett nézet visszaállítása
        container.style.display = 'grid';
        container.style.gridTemplateColumns = 'repeat(5, 300px)';
        container.style.gap = '3.5rem';
        container.style.justifyContent = 'start';
        container.style.marginLeft = '2rem';
        cards.forEach(card => container.appendChild(card));
    } else if (filterType === 'role' || filterType === 'status') {
        container.style.display = 'block';
        const groups = new Set();
        
        cards.forEach(card => {
            const value = filterType === 'role' 
                ? card.querySelector('.role-badge').textContent.trim()
                : card.querySelector('.status-text').textContent.trim();
            groups.add(value);
        });

        groups.forEach(group => {
            const header = document.createElement('div');
            header.className = 'filter-header';
            
            if (filterType === 'role') {
                header.innerHTML = `<i class="fas fa-user-tag"></i> ${group}`;
            } else {
                let dotColor;
                switch(group.toLowerCase()) {
                    case '<?php echo mb_strtolower(translate('elérhető'), 'UTF-8'); ?>':
                        dotColor = '#2ecc71';
                        break;
                    case '<?php echo mb_strtolower(translate('munkában'), 'UTF-8'); ?>':
                        dotColor = '#3498db';
                        break;
                    case '<?php echo mb_strtolower(translate('szabadság'), 'UTF-8'); ?>':
                        dotColor = '#f1c40f';
                        break;
                    case '<?php echo mb_strtolower(translate('betegállomány'), 'UTF-8'); ?>':
                        dotColor = '#e74c3c';
                        break;
                    default:
                        dotColor = '#95a5a6';
                }
                header.innerHTML = `<i class="fas fa-circle" style="color: ${dotColor}"></i> ${group}`;
            }
            
            container.appendChild(header);

            const row = document.createElement('div');
            row.className = 'team-row';
            
            const filteredCards = cards.filter(card => {
                const cardValue = filterType === 'role'
                    ? card.querySelector('.role-badge').textContent.trim()
                    : card.querySelector('.status-text').textContent.trim();
                return cardValue === group;
            });
            
            filteredCards.forEach(card => row.appendChild(card));
            container.appendChild(row);
        });
    }

    // Bezárjuk a szűrő menüt
    document.getElementById('filterMenu').classList.remove('show');
}

function openInvitationModal() {
    document.getElementById('invitationModalOverlay').style.display = 'block';
    document.getElementById('invitationModal').style.display = 'block';
    
    // Új: Alapértelmezett lejárati idő beállítása (48 óra)
    document.getElementById('inviteExpiration').value = '48';
}

function closeInvitationModal() {
    document.getElementById('invitationModalOverlay').style.display = 'none';
    document.getElementById('invitationModal').style.display = 'none';
}

// Email ellenőrzés
let emailTimeout;
document.getElementById('inviteEmail').addEventListener('input', function(e) {
    const emailInput = e.target;
    const errorDiv = document.getElementById('emailError');
    
    // Töröljük a korábbi időzítőt
    clearTimeout(emailTimeout);
    
    // Alaphelyzetbe állítjuk a stílusokat
    emailInput.classList.remove('error');
    errorDiv.style.display = 'none';
    
    // Ha üres a mező, nem ellenőrzünk
    if (!emailInput.value) return;
    
    // Várunk 500ms-ot a gépelés befejezése után
    emailTimeout = setTimeout(async () => {
        try {
            const response = await fetch('../includes/api/check_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `email=${encodeURIComponent(emailInput.value)}`
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Expected JSON but got:', text);
                throw new TypeError("Expected JSON response but got " + contentType);
            }
            
            const data = await response.json();
            
            if (data.success && data.exists) {
                emailInput.classList.add('error');
                errorDiv.style.display = 'block';
                emailInput.setCustomValidity(<?php echo json_encode(translate('Ez az email cím már foglalt!')); ?>);
            } else {
                emailInput.classList.remove('error');
                errorDiv.style.display = 'none';
                emailInput.setCustomValidity('');
            }
        } catch (error) {
            console.error('Error checking email:', error);
            // Don't show error to user, just log it
            emailInput.classList.remove('error');
            errorDiv.style.display = 'none';
            emailInput.setCustomValidity('');
        }
    }, 500);
});

document.getElementById('invitationForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Ellenőrizzük még egyszer az email címet közvetlenül küldés előtt
    const emailInput = document.getElementById('inviteEmail');
    try {
        const response = await fetch('../includes/api/check_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `email=${encodeURIComponent(emailInput.value)}`
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Expected JSON but got:', text);
            throw new TypeError("Expected JSON response but got " + contentType);
        }
        
        const data = await response.json();
        
        if (data.success && data.exists) {
            emailInput.classList.add('error');
            document.getElementById('emailError').style.display = 'block';
            emailInput.setCustomValidity(<?php echo json_encode(translate('Ez az email cím már foglalt!')); ?>);
            return;
        }
    } catch (error) {
        console.error('Error checking email:', error);
        return;
    }
    
    const formData = new FormData(this);
    
    // Close modal first
    closeInvitationModal();
    
    // Show animation and overlay
    const animationContainer = document.getElementById('animationContainer');
    const animationOverlay = document.getElementById('animationOverlay');
    animationContainer.style.display = 'block';
    animationOverlay.style.display = 'block';
    
    // Load and play the animation
    const animation = lottie.loadAnimation({
        container: animationContainer,
        renderer: 'svg',
        loop: false,
        autoplay: true,
        path: '../assets/animation/Animation - 1741364641471.json'
    });

    // Listen for animation complete
    animation.addEventListener('complete', () => {
        // Hide animation and overlay
        animationContainer.style.display = 'none';
        animationOverlay.style.display = 'none';
        animation.destroy();
        
        // Show success message
        const successMessage = document.getElementById('successMessage');
        successMessage.style.display = 'flex';
        
        // Hide success message after 3.1 seconds
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 3100);
    });
    
    try {
        const response = await fetch('../includes/api/send_invitation.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Expected JSON but got:', text);
            throw new TypeError("Expected JSON response but got " + contentType);
        }

        const data = await response.json();

        if (data.success) {
            // Reset form
            this.reset();
        } else {
            // Hide animation immediately on error
            animationContainer.style.display = 'none';
            animationOverlay.style.display = 'none';
            animation.destroy();
            alert(data.message || <?php echo json_encode(translate('Hiba történt a meghívó küldése során.')); ?>);
        }
    } catch (error) {
        // Hide animation immediately on error
        animationContainer.style.display = 'none';
        animationOverlay.style.display = 'none';
        animation.destroy();
        console.error('Error:', error);
        alert(<?php echo json_encode(translate('Hiba történt a meghívó küldése során.')); ?>);
    }
});

// Close modal when clicking outside
document.getElementById('invitationModalOverlay').addEventListener('click', closeInvitationModal);
</script>