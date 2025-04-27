<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cookie_handler.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../translation.php';

// Session ellenőrzése és inicializálása
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

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    header('Location: /Vizsga_oldal/auth/login.php');
    exit;
}

// Initialize database connection
try {
    $db = DatabaseConnection::getInstance()->getConnection();
} catch (Exception $e) {
    error_log("Database connection error in header.php: " . $e->getMessage());
    // Continue without database connection - we'll handle errors where needed
    $db = null;
}

// Get current page path
$current_page = $_SERVER['REQUEST_URI'];

// Get user roles
$user_roles = !empty($_SESSION['user_role']) ? explode(',', $_SESSION['user_role']) : [];
$is_admin = false;
$is_worker = false;

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

foreach ($user_roles as $role) {
    $role = trim($role);
    if ($role === 'Cég tulajdonos' || $role === 'Manager') {
        $is_admin = true;
        break;
    }
}

// Külön ellenőrizzük a worker szerepköröket
foreach ($user_roles as $role) {
    $role = trim($role);
    if (in_array($role, $worker_roles)) {
        $is_worker = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($_SESSION['language'] ?? 'hu'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo translate('VibeCore'); ?></title>
    <link rel="stylesheet" href="/Vizsga_oldal/assets/css/style.css">
    <link rel="stylesheet" href="/Vizsga_oldal/assets/css/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/Vizsga_oldal/includes/darkmode/darkmode.css">
    <script src="/Vizsga_oldal/includes/darkmode/darkmode.js"></script>
    <style>
        body {
            margin: 0;
            padding-top: 65px;
            min-height: 100vh;
            background-color: #f5f6fa;
        }

        .main-header {
            background-color: #2c3e50;
            padding: 0.6rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            width: 100%;
            box-sizing: border-box;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1002;
            height: 65px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: white;
            transition: opacity 0.3s;
            cursor: pointer;
            z-index: 1003;
        }

        .logo-section:hover {
            opacity: 0.9;
        }

        .logo-section img {
            width: 22px;
            height: 22px;
        }

        .logo-section span {
            font-size: 1rem;
            font-weight: bold;
            color: white;
            margin-left: 0.5rem;
        }

        .main-nav {
            display: flex;
            align-items: center;
        }

        .main-nav ul {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 2.5rem;
            align-items: center;
            height: 100%;
        }

        .main-nav li {
            display: flex;
            align-items: center;
            height: 100%;
            position: relative;
            padding: 0.5rem 0;
        }

        .main-nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            font-size: 1.05rem;
            padding: 0.5rem 1rem;
            display: inline-block;
            line-height: 1.5;
        }

        .main-nav li > a:hover {
            color: #3498db;
        }

        .main-nav a.active {
            color: #3498db;
            position: relative;
        }

        .main-nav a.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #3498db;
            display: block; /* Ensure the underline is always visible */
        }

        /* Add underline for dropdown parent when active */
        .dropdown > a.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #3498db;
            display: block; /* Ensure the underline is always visible */
        }

        /* Ensure dropdown items don't have the underline */
        .dropdown-content a::after {
            display: none;
        }

        .dropdown-content a {
            color: #4b5563 !important;
            padding: 0.75rem 1.5rem !important;
            text-decoration: none;
            display: flex !important;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            border-radius: 0 !important;
        }

        .dropdown-content a:hover {
            background-color: #f3f4f6;
            color: #3498db !important;
        }

        .dropdown-content a i {
            width: 20px;
            text-align: center;
            color: #6b7280;
            margin-right: 0.5rem;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .user-menu span {
            font-size: 1.05rem;
        }

        .profile-dropdown {
            position: relative;
            z-index: 1001;
        }

        .profile-dropdown .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1010;
            margin-top: 0.5rem;
            min-width: 200px;
            animation: fadeIn 0.2s ease;
        }

        .profile-dropdown .dropdown-menu.show {
            display: block;
        }

        .profile-dropdown .dropdown-menu a {
            color: #37474f !important;
            padding: 10px 16px !important;
            text-decoration: none !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            font-size: 0.95rem !important;
            white-space: nowrap !important;
        }

        .profile-dropdown .dropdown-menu a:hover {
            background-color: #f5f6fa !important;
        }

        .profile-dropdown .dropdown-menu i {
            width: 20px !important;
            text-align: center !important;
            font-size: 16px !important;
        }

        .profile-dropdown .dropdown-menu hr {
            margin: 8px 0 !important;
            border: none !important;
            border-top: 1px solid #edf2f7 !important;
        }

        .profile-trigger {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            cursor: pointer;
            overflow: hidden;
            background-color: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1002;
        }

        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown {
            position: relative;
            display: inline-block;
            padding: 0.5rem 0;
        }

        .dropdown > a {
            padding: 0.5rem 1rem;
            height: 100%;
            display: flex;
            align-items: center;
            position: relative;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            min-width: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            margin-top: 0.2rem;
            padding: 0.5rem 0;
            transition: opacity 1.5s ease-out;
            opacity: 0;
            transition-delay: 0.5s;
        }

        /* Módosított hover viselkedés */
        .dropdown:hover .dropdown-content {
            display: block;
            opacity: 1;
            transition-delay: 0s;
        }

        /* Add padding to create a hover-safe area */
        .dropdown::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 50px;
            background: transparent;
        }

        /* Add padding to create a hover-safe area for the dropdown content */
        .dropdown-content::before {
            content: '';
            position: absolute;
            top: -20px;
            left: 0;
            width: 100%;
            height: 20px;
            background: transparent;
        }

        .dropdown-content a {
            color: #4b5563 !important;
            padding: 0.75rem 1.5rem !important;
            text-decoration: none;
            display: flex !important;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            border-radius: 0 !important;
            transition: all 0.2s;
            position: relative;
        }

        .dropdown-content a::after {
            content: '';
            position: absolute;
            top: -10px;
            left: 0;
            width: 100%;
            height: 10px;
            background: transparent;
        }

        .container {
            margin: 0 4rem;
            width: calc(100% - 8rem);
            max-width: 1600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Hamburger Menu Styles */
        .hamburger-menu {
            display: none;
            cursor: pointer;
            padding: 10px;
            z-index: 1003;
        }

        .hamburger-menu .bar {
            width: 25px;
            height: 3px;
            background-color: white;
            margin: 5px 0;
            transition: 0.4s;
            border-radius: 3px;
        }

        .mobile-menu {
            display: none;
            position: fixed;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100vh;
            background-color: #2c3e50;
            transition: 0.3s;
            overflow-y: auto;
            z-index: 1001;
            padding-top: 65px;
            display: flex;
            flex-direction: column;
        }

        .mobile-menu.active {
            left: 0;
        }

        .mobile-menu-items {
            padding: 0;
            background-color: #2c3e50;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .mobile-menu-content {
            flex: 1;
        }

        .mobile-menu-items a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 15px 20px;
            font-size: 1.1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .mobile-menu-items a:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .mobile-dropdown {
            display: block;
            width: 100%;
        }

        .mobile-dropdown-content {
            display: none;
            background-color: rgba(0,0,0,0.2);
            padding-left: 20px;
        }

        .mobile-dropdown.active .mobile-dropdown-content {
            display: block;
        }

        .mobile-user-menu {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 20px;
        }

        .mobile-profile-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: rgba(255,255,255,0.1);
            border-radius: 8px;
        }

        .mobile-profile-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 15px;
        }

        .mobile-profile-info span {
            color: white;
            font-size: 1.1rem;
        }

        /* Hamburger Animation */
        .hamburger-menu.active .bar:nth-child(1) {
            transform: rotate(-45deg) translate(-5px, 6px);
        }

        .hamburger-menu.active .bar:nth-child(2) {
            opacity: 0;
        }

        .hamburger-menu.active .bar:nth-child(3) {
            transform: rotate(45deg) translate(-5px, -6px);
        }

        /* Media Queries */
        @media (max-width: 768px) {
            .main-header {
                height: 55px;
                padding: 0.4rem 1rem;
            }

            .main-nav {
                display: none;
            }

            .hamburger-menu {
                display: block;
            }

            .mobile-menu {
                display: block;
                padding-top: 55px;
            }

            .user-menu {
                display: none !important;
            }

            .logo-section span {
                font-size: 0.9rem;
                color: white;
            }

            .logo-section img {
                width: 20px;
                height: 20px;
            }

            body {
                padding-top: 55px;
            }
        }

        /* Módosított mobile-user-menu stílusok */
        .mobile-profile-trigger {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background-color: rgba(255,255,255,0.1);
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .mobile-profile-trigger img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 15px;
        }

        .mobile-profile-trigger span {
            color: white;
            font-size: 1.1rem;
            flex-grow: 1;
        }

        .mobile-profile-trigger i {
            color: white;
            transition: transform 0.3s;
        }

        .mobile-profile-menu {
            display: none;
            background-color: rgba(0,0,0,0.2);
            border-radius: 8px;
            overflow: hidden;
        }

        .mobile-profile-menu.active {
            display: block;
        }

        .mobile-profile-menu a {
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .mobile-profile-menu a:last-child {
            border-bottom: none;
        }

        .mobile-profile-menu a i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }

        .mobile-profile-menu a:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .mobile-logout {
            width: 100%;
            padding: 0;
            margin-top: auto;
            position: sticky;
            bottom: 0;
            background-color: #2c3e50;
        }

        .mobile-logout a {
            color: #ff4d4d;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 20px;
            font-size: 1.1rem;
            width: 100%;
            border-top: 1px solid rgba(255,255,255,0.1);          
        }

        .mobile-logout a:hover {
            background-color: rgba(0,0,0,0.2);
        }

        .mobile-logout i {
            margin-right: 15px;
            color: #ff4d4d;
        }
    </style>
</head>
<body>
    <header class="main-header">
        <a href="/Vizsga_oldal/dashboard/index.php" class="logo-section">
            <img src="/Vizsga_oldal/admin/VIBCORE BLACK2 másolata.png" alt="Logo">
            <span>VibeCore</span>
        </a>

        <!-- Hamburger Menu Button -->
        <div class="hamburger-menu">
            <div class="bar"></div>
            <div class="bar"></div>
            <div class="bar"></div>
        </div>

        <nav class="main-nav">
            <ul>
                <li>
                    <a href="/Vizsga_oldal/dashboard/index.php" class="<?php echo strpos($current_page, '/dashboard/index.php') !== false ? 'active' : ''; ?>" data-translate="Kezdőlap">
                        <?php echo translate('Kezdőlap'); ?>
                    </a>
                </li>
                <?php if ($is_admin || $is_worker): ?>
                <li class="dropdown">
                    <a href="#" class="<?php echo (strpos($current_page, 'eszkozok.php') !== false  || 
                                                strpos($current_page, 'karbantartas.php') !== false) ? 'active' : ''; ?>" data-translate="Eszközök">
                        <?php echo translate('Eszközök'); ?>
                    </a>
                    <div class="dropdown-content">
                        <a href="/Vizsga_oldal/dashboard/eszkozok.php" data-translate="Raktár">
                            <i class="fas fa-warehouse"></i>
                            <?php echo translate('Raktár'); ?>
                        </a>
                        <a href="/Vizsga_oldal/dashboard/karbantartas.php" data-translate="Karbantartás">
                            <i class="fas fa-tools"></i>
                            <?php echo translate('Karbantartás'); ?>
                        </a>
                        <a href="/Vizsga_oldal/dashboard/beallas.php" data-translate="Beállás">
                            <i class="fas fa-cogs"></i>
                            <?php echo translate('Beállás'); ?>
                        </a>
                        <a href="/Vizsga_oldal/dashboard/eszkozbejelentes.php" data-translate="Eszköz bejelentés">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo translate('Eszköz bejelentés'); ?>
                        </a>
                        <a href="/Vizsga_oldal/dashboard/eszkozok-qr.php" data-translate="QR Kód">
                            <i class="fas fa-qrcode"></i>
                            <?php echo translate('QR Kód'); ?>
                        </a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="#" class="<?php echo (strpos($current_page, 'projektek.php') !== false || 
                                                strpos($current_page, 'munkak.php') !== false) ? 'active' : ''; ?>" data-translate="Munka">
                        <?php echo translate('Munka'); ?>
                    </a>
                    <div class="dropdown-content">
                        <a href="/Vizsga_oldal/dashboard/projektek.php" data-translate="Projektek">
                            <i class="fas fa-project-diagram"></i>
                            <?php echo translate('Projektek'); ?>
                        </a>
                        <a href="/Vizsga_oldal/dashboard/munkak.php" data-translate="Munkák">
                            <i class="fas fa-tasks"></i>
                            <?php echo translate('Munkák'); ?>
                        </a>
                        <?php if ($is_admin): ?>
                        <a href="/Vizsga_oldal/dashboard/uj_projekt.php" data-translate="Új projekt">
                            <i class="fas fa-plus-circle"></i>
                            <?php echo translate('Új projekt'); ?>
                        </a>
                        <a href="/Vizsga_oldal/dashboard/uj_munka.php" data-translate="Új munka">
                            <i class="fas fa-plus"></i>
                            <?php echo translate('Új munka'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </li>
                <li>
                    <a href="/Vizsga_oldal/dashboard/csapat.php" 
                       class="<?php echo strpos($current_page, '/csapat/') !== false ? 'active' : ''; ?>" data-translate="Csapat">
                        <?php echo translate('Csapat'); ?>
                    </a>
                </li>
                <li>
                    <a href="/Vizsga_oldal/dashboard/naptar.php" 
                       class="<?php echo strpos($current_page, '/naptar/') !== false ? 'active' : ''; ?>" data-translate="Naptár">
                        <?php echo translate('Naptár'); ?>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Mobile Menu -->
        <div class="mobile-menu">
            <div class="mobile-menu-items">
                <div class="mobile-menu-content">
                    <!-- Profil szekció -->
                    <div class="mobile-user-menu">
                        <?php
                        // Base path definition
                        $base_path = '/Vizsga_oldal/';
                        
                        // Profilkép lekérése az adatbázisból
                        $profile_pic = 'user.png';
                        if ($db && isset($_SESSION['user_id'])) {
                            try {
                                $stmt = $db->prepare("SELECT profile_pic FROM user WHERE id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($result && $result['profile_pic']) {
                                    $profile_pic = $result['profile_pic'];
                                }
                            } catch (PDOException $e) {
                                error_log("Error fetching profile picture: " . $e->getMessage());
                            }
                        }
                        
                        $profile_pic_path = $base_path . 'uploads/profiles/' . $profile_pic;
                        $default_pic_path = $base_path . 'assets/img/user.png';
                        $server_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
                        $full_path = $server_root . $profile_pic_path;
                        $display_path = file_exists($full_path) ? $profile_pic_path : $default_pic_path;
                        ?>
                        <div class="mobile-profile-trigger">
                            <img src="<?php echo htmlspecialchars($display_path); ?>" alt="Profilkép">
                            <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="mobile-profile-menu">
                            <a href="/Vizsga_oldal/dashboard/profil.php">
                                <i class="fas fa-user"></i> <?php echo translate('Profil'); ?>
                            </a>
                            <a href="/Vizsga_oldal/dashboard/naptar.php">
                                <i class="fas fa-calendar"></i> <?php echo translate('Naptár'); ?>
                            </a>
                            <a href="/Vizsga_oldal/dashboard/notes/notes.php">
                                <i class="fas fa-sticky-note"></i> <?php echo translate('Jegyzetek'); ?>
                            </a>
                            <a href="/Vizsga_oldal/dashboard/ertesitesek.php">
                                <i class="fas fa-bell"></i> <?php echo translate('Értesítések'); ?>
                            </a>
                            <a href="/Vizsga_oldal/dashboard/beallitasok.php">
                                <i class="fas fa-cog"></i> <?php echo translate('Beállítások'); ?>
                            </a>
                            <a href="/Vizsga_oldal/dashboard/subscription.php">
                                <i class="fas fa-box"></i> <?php echo translate('Csomag módosítás'); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Többi menüpont -->
                    <a href="/Vizsga_oldal/dashboard/index.php" class="<?php echo strpos($current_page, '/dashboard/index.php') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> <?php echo translate('Kezdőlap'); ?>
                    </a>
                    
                    <?php if ($is_admin || $is_worker): ?>
                    <div class="mobile-dropdown">
                        <a href="#" class="mobile-dropdown-trigger">
                            <i class="fas fa-tools"></i> <?php echo translate('Eszközök'); ?>
                            <i class="fas fa-chevron-down" style="float: right;"></i>
                        </a>
                        <div class="mobile-dropdown-content">
                            <a href="/Vizsga_oldal/dashboard/eszkozok.php">
                                <i class="fas fa-warehouse"></i> <?php echo translate('Raktár'); ?>
                            </a>
                            <a href="/Vizsga_oldal/dashboard/karbantartas.php">
                                <i class="fas fa-tools"></i> <?php echo translate('Karbantartás'); ?>
                            </a>
                            <a href="/Vizsga_oldal/dashboard/beallas.php">
                                <i class="fas fa-cogs"></i> <?php echo translate('Beállás'); ?>
                            </a>
                            <a href="/Vizsga_oldal/dashboard/eszkozbejelentes.php">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo translate('Eszköz bejelentés'); ?>
                            </a>
                            <a href="/Vizsga_oldal/dashboard/eszkozok-qr.php">
                                <i class="fas fa-qrcode"></i> <?php echo translate('QR Kód'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="mobile-dropdown">
                        <a href="#" class="mobile-dropdown-trigger">
                            <i class="fas fa-briefcase"></i> <?php echo translate('Munka'); ?>
                            <i class="fas fa-chevron-down" style="float: right;"></i>
                        </a>
                        <div class="mobile-dropdown-content">
                            <a href="/Vizsga_oldal/dashboard/projektek.php">
                                <i class="fas fa-project-diagram"></i> <?php echo translate('Projektek'); ?>
                            </a>
                            <a href="/Vizsga_oldal/dashboard/munkak.php">
                                <i class="fas fa-tasks"></i> <?php echo translate('Munkák'); ?>
                            </a>
                            <?php if ($is_admin): ?>
                            <a href="/Vizsga_oldal/dashboard/uj_projekt.php">
                                <i class="fas fa-plus-circle"></i> <?php echo translate('Új projekt'); ?>
                            </a>
                            <a href="/Vizsga_oldal/dashboard/uj_munka.php">
                                <i class="fas fa-plus"></i> <?php echo translate('Új munka'); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <a href="/Vizsga_oldal/dashboard/csapat.php">
                        <i class="fas fa-users"></i> <?php echo translate('Csapat'); ?>
                    </a>
                    <a href="/Vizsga_oldal/dashboard/naptar.php">
                        <i class="fas fa-calendar"></i> <?php echo translate('Naptár'); ?>
                    </a>
                </div>

                <!-- Kijelentkezés gomb -->
                <div class="mobile-logout">
                    <a href="/Vizsga_oldal/auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i> <?php echo translate('Kijelentkezés'); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="user-menu">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span data-translate="Üdvözöljük"><?php echo translate('Üdvözöljük'); ?>, <?php 
                    // Get user's first name from database
                    if ($db) {
                        try {
                            $stmt = $db->prepare("SELECT firstname FROM user WHERE id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                            echo htmlspecialchars($userData['firstname']); 
                        } catch (PDOException $e) {
                            error_log("Error fetching user data in header.php: " . $e->getMessage());
                            echo "User";
                        }
                    } else {
                        echo "User";
                    }
                ?>!</span>
                <div class="profile-dropdown">
                    <div class="profile-trigger">
                        <?php
                        // Javított útvonal kezelés
                        $current_page = $_SERVER['PHP_SELF'];
                        $base_path = '/Vizsga_oldal/';
                        
                        // Profilkép lekérése az adatbázisból
                        $profile_pic = 'user.png'; // Alapértelmezett érték
                        if ($db && isset($_SESSION['user_id'])) {
                            try {
                                $stmt = $db->prepare("SELECT profile_pic FROM user WHERE id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($result && $result['profile_pic']) {
                                    $profile_pic = $result['profile_pic'];
                                }
                            } catch (PDOException $e) {
                                error_log("Error fetching profile picture: " . $e->getMessage());
                            }
                        }
                        
                        // Útvonalak beállítása
                        $profile_pic_path = $base_path . 'uploads/profiles/' . $profile_pic;
                        $default_pic_path = $base_path . 'assets/img/user.png';
                        
                        // Teljes szerver útvonal a file_exists ellenőrzéshez
                        $server_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
                        $full_path = $server_root . $profile_pic_path;
                        
                        // Debug információk logolása
                        error_log("Profile pic from DB: " . $profile_pic);
                        error_log("Full path being checked: " . $full_path);
                        error_log("File exists check result: " . (file_exists($full_path) ? 'true' : 'false'));
                        
                        // Ellenőrzés és megfelelő útvonal kiválasztása
                        $display_path = file_exists($full_path) ? $profile_pic_path : $default_pic_path;
                        ?>
                        <img src="<?php echo htmlspecialchars($display_path); ?>" alt="Profilkép" class="profile-image">
                    </div>
                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="/Vizsga_oldal/dashboard/profil.php" data-translate="Profil">
                            <i class="fas fa-user"></i> <?php echo translate('Profil'); ?>
                        </a>
                        <a href="/Vizsga_oldal/dashboard/naptar.php" data-translate="Naptár">
                            <i class="fas fa-calendar"></i> <?php echo translate('Naptár'); ?>
                        </a>
                        <a href="/Vizsga_oldal/dashboard/notes/notes.php" data-translate="Jegyzetek">
                            <i class="fas fa-sticky-note"></i> <?php echo translate('Jegyzetek'); ?>
                        </a>
                        <a href="/Vizsga_oldal/dashboard/ertesitesek.php" data-translate="Értesítések">
                            <i class="fas fa-bell"></i> <?php echo translate('Értesítések'); ?>
                        </a>
                        <a href="/Vizsga_oldal/dashboard/beallitasok.php" data-translate="Beállítások">
                            <i class="fas fa-cog"></i> <?php echo translate('Beállítások'); ?>
                        </a>
                        <a href="/Vizsga_oldal/dashboard/subscription.php" data-translate="Csomag módosítás">
                            <i class="fas fa-box"></i> <?php echo translate('Csomag módosítás'); ?>
                        </a>
                        <hr>
                        <a href="/Vizsga_oldal/auth/logout.php" data-translate="Kijelentkezés">
                            <i class="fas fa-sign-out-alt"></i> <?php echo translate('Kijelentkezés'); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="content">

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.querySelector('.hamburger-menu');
        const mobileMenu = document.querySelector('.mobile-menu');
        const dropdownTriggers = document.querySelectorAll('.mobile-dropdown-trigger');
        const profileTrigger = document.querySelector('.mobile-profile-trigger');
        const profileMenu = document.querySelector('.mobile-profile-menu');

        // Desktop profil legördülő menü kezelése
        const profileDropdownTrigger = document.querySelector('.profile-trigger');
        const profileDropdownMenu = document.querySelector('.dropdown-menu');
        
        if (profileDropdownTrigger && profileDropdownMenu) {
            profileDropdownTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                profileDropdownMenu.classList.toggle('show');
            });

            // Kattintás figyelése a dokumentumon a menü bezárásához
            document.addEventListener('click', function(e) {
                if (!profileDropdownTrigger.contains(e.target)) {
                    profileDropdownMenu.classList.remove('show');
                }
            });
        }

        // Hamburger menu toggle
        hamburger.addEventListener('click', function() {
            this.classList.toggle('active');
            mobileMenu.classList.toggle('active');
        });

        // Profile menu toggle
        if (profileTrigger && profileMenu) {
            profileTrigger.addEventListener('click', function() {
                profileMenu.classList.toggle('active');
                const chevron = this.querySelector('.fa-chevron-down');
                chevron.style.transform = profileMenu.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0)';
            });
        }

        // Dropdown toggles
        dropdownTriggers.forEach(trigger => {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = this.closest('.mobile-dropdown');
                dropdown.classList.toggle('active');
                
                // Rotate chevron
                const chevron = this.querySelector('.fa-chevron-down');
                chevron.style.transform = dropdown.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0)';
            });
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!mobileMenu.contains(e.target) && !hamburger.contains(e.target) && mobileMenu.classList.contains('active')) {
                mobileMenu.classList.remove('active');
                hamburger.classList.remove('active');
            }
        });
    });
    </script>

    <?php
    // Cookie kezelés meghívása a header végén
    if (isset($_SESSION['user_id']) && $db) {
        try {
            handleCookieConsent($db, $_SESSION['user_id']);
        } catch (Exception $e) {
            error_log("Error handling cookie consent: " . $e->getMessage());
        }
    }

    // Get subscription info
    try {
        if ($db) {
            $stmt = $db->prepare("
                SELECT 
                    sp.description as plan_description,
                    (SELECT COUNT(*) FROM stuffs WHERE company_id = ?) as current_device_count
                FROM subscriptions s
                JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
                WHERE s.company_id = ? 
                AND s.subscription_status_id = 1
                ORDER BY s.start_date DESC 
                LIMIT 1
            ");
            
            // Get user's company_id
            $stmt_company = $db->prepare("SELECT company_id FROM user WHERE id = ?");
            $stmt_company->execute([$_SESSION['user_id']]);
            $company_id = $stmt_company->fetchColumn();
            
            if ($company_id) {
                $stmt->execute([$company_id, $company_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    // Extract device limit from plan description
                    preg_match('/(\d+)\s+eszköz/', $result['plan_description'], $matches);
                    $device_limit = isset($matches[1]) ? (int)$matches[1] : 0;
                    $current_device_count = (int)$result['current_device_count'];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting subscription info: " . $e->getMessage());
    }
    ?>
</body>
</html>