<?php
function checkPageAccess() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Session timeout ellenőrzése (30 perc)
    $timeout = 1800; // 30 perc másodpercben
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        header('Location: /Vizsga_oldal/auth/login.php?timeout=1');
        exit;
    }

    // Frissítjük az utolsó aktivitás időbélyegét
    $_SESSION['last_activity'] = time();

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: /Vizsga_oldal/auth/login.php');
        exit;
    }

    // Ellenőrizzük és állítsuk be a company_id-t ha nincs
    if (!isset($_SESSION['company_id'])) {
        try {
            require_once __DIR__ . '/database.php';
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT company_id FROM user WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data && isset($user_data['company_id'])) {
                $_SESSION['company_id'] = $user_data['company_id'];
            } else {
                // Ha nincs company_id, töröljük a session-t és átirányítjuk
                session_destroy();
                header('Location: /Vizsga_oldal/auth/login.php?error=no_company');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Database error in checkPageAccess: " . $e->getMessage());
            session_destroy();
            header('Location: /Vizsga_oldal/auth/login.php?error=db_error');
            exit;
        }
    }

    // Get current page path
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // Define worker roles
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
    
    // Define access rules
    $access_rules = [
        'worker_register.php' => ['Cég tulajdonos'],
        'csapat.php' => ['Cég tulajdonos', 'Manager', 'Vizuáltechnikus', 'Villanyszerelő', 'Szinpadtechnikus', 
                        'Szinpadfedés felelős', 'Stagehand', 'Karbantartó', 'Hangtechnikus', 'Fénytechnikus'],
        'eszkozok.php' => ['Cég tulajdonos', 'Manager', 'Vizuáltechnikus', 'Villanyszerelő', 'Szinpadtechnikus', 
                        'Szinpadfedés felelős', 'Stagehand', 'Karbantartó', 'Hangtechnikus', 'Fénytechnikus'],
        'karbantartas.php' => ['Cég tulajdonos', 'Manager', 'Vizuáltechnikus', 'Villanyszerelő', 'Szinpadtechnikus', 
                        'Szinpadfedés felelős', 'Stagehand', 'Karbantartó', 'Hangtechnikus', 'Fénytechnikus'],
        'naptar.php' => ['Cég tulajdonos', 'Manager', 'Vizuáltechnikus', 'Villanyszerelő', 'Szinpadtechnikus', 
                        'Szinpadfedés felelős', 'Stagehand', 'Karbantartó', 'Hangtechnikus', 'Fénytechnikus'],
        'ertesitesek.php' => ['Cég tulajdonos', 'Manager', 'Vizuáltechnikus', 'Villanyszerelő', 'Szinpadtechnikus', 
                        'Szinpadfedés felelős', 'Stagehand', 'Karbantartó', 'Hangtechnikus', 'Fénytechnikus'],
        'error.php' => ['*'] // Available to everyone
    ];

    // If page is in access rules
    if (isset($access_rules[$current_page])) {
        $allowed_roles = $access_rules[$current_page];
        
        // If not * and user's role is not in allowed roles
        if ($allowed_roles !== ['*']) {
            // Convert user roles from comma-separated string to array if not empty
            $user_roles = !empty($_SESSION['user_role']) ? explode(',', $_SESSION['user_role']) : [];
            
            // Check if there's an intersection between user roles and allowed roles
            $has_access = false;
            foreach ($user_roles as $role) {
                if (in_array(trim($role), $allowed_roles)) {
                    $has_access = true;
                    break;
                }
            }
            
            if (!$has_access) {
                header('Location: /Vizsga_oldal/dashboard/error.php?msg=unauthorized');
                exit;
            }
        }
    }
} 