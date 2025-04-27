<?php
// Definiáld a gyökérkönyvtárat
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/language_handler.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth_check.php';  // Ez legyen az utolsó, mivel függhet a többitől

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Kapcsolódási hiba: " . mysqli_connect_error());
}

// Jogosultság ellenőrzése
checkPageAccess();

// Projektek lekérése az adott céghez
$company_id = $_SESSION['company_id'];
$project_types_sql = "SELECT DISTINCT pt.id, pt.name 
                     FROM project_type pt 
                     INNER JOIN project p ON p.type_id = pt.id 
                     WHERE p.company_id = '$company_id'
                     ORDER BY pt.name";
$project_types_result = mysqli_query($conn, $project_types_sql);

$projects_sql = "SELECT p.id, p.name, p.project_startdate, p.project_enddate, pt.name as type_name
                 FROM project p
                 LEFT JOIN project_type pt ON p.type_id = pt.id 
                 LEFT JOIN work w ON p.id = w.project_id
                 WHERE p.company_id = ? 
                 AND w.id IS NULL  /* Csak azok a projektek, amelyekhez nincs még munka */
                 AND p.project_startdate > NOW()  /* Csak azok a projektek, amelyek még nem kezdődtek el */
                 ORDER BY p.name";

$stmt = mysqli_prepare($conn, $projects_sql);
mysqli_stmt_bind_param($stmt, "i", $company_id);
mysqli_stmt_execute($stmt);
$projects_result = mysqli_stmt_get_result($stmt);

// A PHP részben, a users lekérése előtt adjuk hozzá a szerepkörök lekérését
$roles_sql = "SELECT DISTINCT r.id, r.role_name 
              FROM roles r
              INNER JOIN user_to_roles ur ON r.id = ur.role_id
              INNER JOIN user u ON ur.user_id = u.id
              WHERE u.company_id = '$company_id'
              ORDER BY r.role_name";
$roles_result = mysqli_query($conn, $roles_sql);

// Lekérjük a szükséges adatokat a legördülő listákhoz
$users_sql = "SELECT u.id, u.firstname, u.lastname, r.role_name as role_name,
              (SELECT COUNT(*) 
               FROM user_to_work utw 
               JOIN work w ON utw.work_id = w.id 
               WHERE utw.user_id = u.id 
               AND w.company_id = '$company_id'
               AND CURRENT_DATE BETWEEN w.work_start_date AND w.work_end_date) as has_active_work
              FROM user u
              LEFT JOIN user_to_roles ur ON u.id = ur.user_id
              LEFT JOIN roles r ON ur.role_id = r.id 
              WHERE u.company_id = '$company_id' 
              ORDER BY u.lastname, u.firstname";
$users_result = mysqli_query($conn, $users_sql);

$delivers_sql = "SELECT id, name FROM deliver ORDER BY name";
$delivers_result = mysqli_query($conn, $delivers_sql);

// Eszközök lekérése
$stuffs_sql = "SELECT s.id, s.qr_code, st.name as type_name, ss.name as subtype_name, 
               sb.name as brand_name, sm.name as model_name
               FROM stuffs s
               LEFT JOIN stuff_type st ON s.type_id = st.id
               LEFT JOIN stuff_secondtype ss ON s.secondtype_id = ss.id
               LEFT JOIN stuff_brand sb ON s.brand_id = sb.id
               LEFT JOIN stuff_model sm ON s.model_id = sm.id
               WHERE s.company_id = '$company_id'
               ORDER BY st.name, ss.name";
$stuffs_result = mysqli_query($conn, $stuffs_sql);

// A PHP részben adjunk hozzá egy tömböt az ikonokhoz
$deliver_icons = [
    translate('Autó') => 'fa-car',
    translate('Teherautó') => 'fa-truck',
    translate('Motor') => 'fa-motorcycle',
    translate('Bicikli') => 'fa-bicycle',
    translate('Gyalog') => 'fa-walking',
    translate('Vonat') => 'fa-train',
    translate('Repülő') => 'fa-plane',
    translate('Hajó') => 'fa-ship',
    translate('Futár') => 'fa-shipping-fast',
    translate('Posta') => 'fa-mail-bulk',
    translate('Expressz') => 'fa-truck-fast'
];

// Form feldolgozása
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'];
    $user_ids = array_filter(explode(',', $_POST['user_id'])); // Üres elemek kiszűrése
    $stuffs_ids = array_filter(explode(',', $_POST['stuffs_ids'])); // Üres elemek kiszűrése
    $deliver_id = $_POST['deliver_id']; // Egy szállítási mód
    
    // Dátumok konvertálása megfelelő formátumra
    $work_start_date = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $_POST['work_start_date'])));
    $work_end_date = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $_POST['work_end_date'])));
    
    // Ellenőrizzük, hogy van-e kiválasztva minden szükséges elem
    if (empty($project_id) || empty($user_ids) || empty($stuffs_ids) || empty($deliver_id) || empty($work_start_date) || empty($work_end_date)) {
        $_SESSION['error'] = "Minden mező kitöltése kötelező!";
        header('Location: uj_munka.php');
        exit;
    }

    $company_id = $_SESSION['company_id'];
    
    try {
        $pdo->beginTransaction();

        // Insert the new work entry
        $stmt = $pdo->prepare("INSERT INTO work (project_id, work_start_date, work_end_date, company_id, deliver_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$project_id, $work_start_date, $work_end_date, $company_id, $deliver_id]);
        $work_id = $pdo->lastInsertId();

        // Add all selected users to the work
        if (!empty($user_ids)) {
            $stmt = $pdo->prepare("INSERT INTO user_to_work (work_id, user_id) VALUES (?, ?)");
            foreach ($user_ids as $user_id) {
                $stmt->execute([$work_id, $user_id]);
            }
        }

        // Add equipment to the work
        if (!empty($stuffs_ids)) {
            $stmt = $pdo->prepare("INSERT INTO work_to_stuffs (work_id, stuffs_id) VALUES (?, ?)");
            foreach ($stuffs_ids as $stuff_id) {
                $stmt->execute([$work_id, $stuff_id]);
            }
        }

        // Create work history entry
        $stmt = $pdo->prepare("INSERT INTO work_history (original_work_id, user_id, deliver_id, project_id, work_start_date, work_end_date, company_id, archived_by) 
                              SELECT id, NULL, deliver_id, project_id, work_start_date, work_end_date, company_id, ? FROM work WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $work_id]);

        // Send notifications to all selected workers
        if (!empty($user_ids)) {
            $stmt = $pdo->prepare("INSERT INTO notifications (receiver_user_id, notification_text, notification_time) VALUES (?, ?, NOW())");
            $project_stmt = $pdo->prepare("SELECT name FROM project WHERE id = ?");
            $project_stmt->execute([$project_id]);
            $project_name = $project_stmt->fetchColumn();

            $message = "Új munkát kaptál: " . $project_name;
            foreach ($user_ids as $user_id) {
                $stmt->execute([$user_id, $message]);
            }
        }

        $pdo->commit();

        $_SESSION['success'] = "A munka sikeresen létrehozva!";
        header('Location: munkak.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Hiba történt a munka létrehozása során: " . $e->getMessage();
        header('Location: uj_munka.php');
        exit;
    }
}
?>

<style>
.form-container {
    margin-top: 20px;
    padding: 1.5rem;
    height: calc(100vh - 60px);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    overflow-y: auto;
}

.form-sections {
    display: flex;
    gap: 1.5rem;
    height: 65%;
    min-height: 400px; /* Fix minimum magasság */
}

.section-column {
    flex: 1;
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    min-height: 400px; /* Fix minimum magasság */
    height: 100%; /* Teljes magasság kitöltése */
}

.section-header {
    padding: 1rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.section-header h2 {
    margin: 0;
    font-size: 1.1rem;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-height: 32px; /* Fix magasság a header-nek */
}

.section-content {
    padding: 1rem;
    flex: 1;
    overflow: hidden;
    height: calc(100% - 60px);
}

.grid-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.5rem;
    padding: 0.5rem;
    min-height: 300px;
    height: calc(100% - 20px);
}

#usersGrid {
    gap: 0.35rem;
    grid-auto-rows: min-content;  /* Minimális magasság a soroknak */
    align-content: start;  /* Felülről kezdje a kártyák elrendezését */
}

.card {
    border: 2px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    text-align: center;
    font-size: 0.9rem;
    height: 100px; /* Fix magasság a kártyáknak */
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.card:hover {
    border-color: #3498db;
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card.selected {
    border-color: #3498db;
    background-color: #ebf8ff;
}

.bottom-section {
    display: flex;
    gap: 1.5rem;
    height: 25%;
    min-height: 20px; /* Fix minimum magasság */
    margin-top: 1rem;
    margin-bottom: 1rem;
}

.bottom-section .section-column {
    min-height: 350px; /* Fix minimum magasság */
    height: 100%; /* Teljes magasság kitöltése */
}

.date-container {
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 0.5rem;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    height: auto;
    min-height: 200px;
}

.date-inputs {
    display: flex;
    gap: 1rem;
    margin: 0;
}

.date-group {
    flex: 1;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.date-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #4a5568;
    font-size: 0.85rem;
    font-weight: 500;
}

.date-hint {
    font-size: 0.75rem;
    color: #718096;
    margin-top: 0.15rem;
}

.date-duration {
    text-align: center;
    padding: 0.5rem;
    background: #ebf8ff;
    border-radius: 0.5rem;
    color: #2b6cb0;
    font-weight: 500;
    font-size: 0.85rem;
    margin-top: 0.5rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.date-error {
    color: #e53e3e;
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.btn-group {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem;
    background: white;
    margin-top: 0.5rem;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.btn {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    border-radius: 0.375rem;
    min-width: 100px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    cursor: pointer;
}

.btn i {
    font-size: 0.875rem;
}

.btn-primary {
    background-color: #3182ce;
    color: white;
    box-shadow: 0 1px 3px rgba(49, 130, 206, 0.2);
}

.btn-primary:hover {
    background-color: #2c5282;
    transform: translateY(-1px) scale(1.02);
    box-shadow: 0 4px 6px rgba(49, 130, 206, 0.2);
}

.btn-primary:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(49, 130, 206, 0.2);
}

.btn-primary:disabled {
    background-color: #90cdf4;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-secondary {
    background-color: #edf2f7;
    color: #4a5568;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.btn-secondary:hover {
    background-color: #e2e8f0;
    color: #2d3748;
    transform: translateY(-1px) scale(1.02);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.btn-secondary:active {
    transform: translateY(0);
    background-color: #cbd5e0;
}

.status-badge {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 1rem;
    background: #e2e8f0;
    color: #4a5568;
    margin-left: auto;
    min-width: 140px; /* Fix szélesség a badge-nek */
    text-align: center; /* Középre igazítás */
    display: inline-block; /* Blokk elem */
}

.status-badge.completed {
    background: #c6f6d5;
    color: #2f855a;
}

.project-card {
    width: 180px;
    height: 100px;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    background: white;
    transition: all 0.2s;
}

.project-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.project-name {
    font-weight: 500;
    font-size: 0.9rem;
    color: #2d3748;
    margin-bottom: 0.25rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.project-dates {
    font-size: 0.75rem;
    color: #718096;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.project-date-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.project-date-item i {
    font-size: 0.7rem;
    color: #a0aec0;
}

.project-type {
    font-size: 0.75rem;
    color: #718096;
    background: #f7fafc;
    padding: 0.15rem 0.5rem;
    border-radius: 1rem;
    display: inline-block;
    margin-bottom: 0.25rem;
}

/* Módosítsuk a kiválasztott és hover állapotokat */
.project-card:hover {
    border-color: #3498db;
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.project-card.selected {
    border: 2px solid #3498db;
    background-color: #ebf8ff;
}

.user-card {
    width: 180px;
    height: 85px;  /* Csökkentjük a kártya magasságát */
    padding: 0.5rem;  /* Csökkentjük a belső térközt */
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    background: white;
    transition: all 0.2s;
}

.user-card i {
    font-size: 1.25rem;  /* Kicsit kisebb ikon */
    color: #718096;
    margin-bottom: 0.35rem;  /* Kisebb margó az ikon alatt */
}

.user-name {
    font-weight: 500;
    font-size: 0.9rem;
    color: #2d3748;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-bottom: 0.25rem;  /* Kisebb margó a név alatt */
}

.user-role {
    font-size: 0.75rem;
    color: #718096;
}

.stuff-card {
    width: 185px;
    height: 90px;
    padding: 0.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    transition: all 0.2s;
    font-size: 0.8rem;
}

.stuff-type {
    font-weight: 500;
    color: #2d3748;
    margin-bottom: 0.2rem;
    font-size: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stuff-details {
    color: #4a5568;
    font-size: 0.7rem;
    line-height: 1.2;
    margin-bottom: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stuff-qr {
    font-size: 0.7rem;
    color: #718096;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Módosítsuk a hover és selected állapotokat minden kártyatípusra */
.project-card:hover,
.user-card:hover,
.stuff-card:hover {
    border-color: #3498db;
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.project-card.selected,
.user-card.selected,
.stuff-card.selected {
    border: 2px solid #3498db;
    background-color: #ebf8ff;
}

.deliver-card {
    width: 100px;
    height: 70px;
    padding: 0.4rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    background: white;
    transition: all 0.2s;
}

.deliver-card i {
    font-size: 1.1rem;
    color: #4a5568;
}

.deliver-card .deliver-name {
    font-size: 0.7rem;
    color: #2d3748;
    text-align: center;
    line-height: 1.2;
}

.section-column:has(.deliver-card) .grid-cards {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem;
    flex-wrap: wrap;
    height: auto;
    margin-top: 4rem;
}

/* Ha szükséges a görgetősáv elrejtése: */
.section-column:has(.deliver-card) .grid-cards::-webkit-scrollbar {
    display: none;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: #a0aec0;
    text-align: center;
    width: 100%;
    height: 100%;
}

.empty-state i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

.type-filter {
    position: relative;
    margin-left: auto;
}

.btn-filter {
    background: none;
    border: none;
    color: #718096;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.375rem;
    transition: all 0.2s;
}

.btn-filter:hover {
    background: #EDF2F7;
    color: #2D3748;
}

.type-filter-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background: white;
    border: 1px solid #E2E8F0;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    min-width: 200px;
    margin-top: 0.5rem;
}

.type-filter-menu.show {
    display: block;
}

.filter-option {
    padding: 0.75rem 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #4A5568;
    transition: all 0.2s;
}

.filter-option:hover {
    background: #F7FAFC;
    color: #2D3748;
}

.filter-option i {
    font-size: 0.875rem;
    width: 1rem;
}

.filter-divider {
    height: 1px;
    background: #E2E8F0;
    margin: 0.5rem 0;
}

/* Pagination styles */
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    margin-top: 0.5rem;
}

.pagination-btn {
    padding: 0.25rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    background: white;
    color: #4a5568;
    cursor: pointer;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.pagination-btn:hover:not(:disabled) {
    background: #f7fafc;
    color: #2d3748;
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-info {
    font-size: 0.875rem;
    color: #4a5568;
}

/* Módosítsuk a grid-cards osztályt az eszközöknél */
.section-column:has(.stuff-card) .grid-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    padding: 0.5rem;
    height: calc(100% - 40px);
    width: 600px;
    margin: 0 auto;
    overflow: hidden;
}

.btn-select-all {
    background: none;
    border: none;
    color: #4A5568;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    margin-left: 0.5rem;
    transition: all 0.2s;
}

.btn-select-all:hover {
    background: #EDF2F7;
    color: #2D3748;
}

.btn-select-all i {
    font-size: 0.75rem;
}

.hidden-card {
    display: none !important;
}

.card.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    border-color: #cbd5e0;
    background-color: #f1f5f9;
}

.card.disabled:hover {
    transform: none;
    border-color: #cbd5e0;
    box-shadow: none;
}

.project-status {
    margin-top: 0.5rem;
    color: #64748b;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.project-status i {
    color: #e53e3e;
}

.accordion-content {
    padding: 1.5rem;
    min-height: 180px;
    background: white;
}

.accordion-item.active .accordion-content {
    display: block;
}

.form-group {
    margin-bottom: 2rem;
}

.form-control {
    width: 100%;
    padding: 0.875rem;
    height: 50px;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    font-size: 1rem;
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.date-inputs {
    display: flex;
    gap: 2rem;
    margin: 1rem 0;
}

.date-input-group {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.date-input-group label {
    font-size: 1rem;
    font-weight: 500;
    color: #2d3748;
}

.date-input-group input[type="date"] {
    height: 50px;
    padding: 0.875rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    font-size: 1rem;
}

select.form-control {
    height: 50px;
    padding: 0 0.875rem;
    background-color: white;
    cursor: pointer;
}

select.form-control:hover {
    border-color: #3498db;
}
</style>

<?php require_once '../includes/layout/header.php'; ?>

<div class="form-container">
    <!-- Adjuk hozzá a form elemet és a rejtett input mezőket -->
    <form id="workForm" method="POST">
        <input type="hidden" id="project_id" name="project_id" value="">
        <input type="hidden" id="user_id" name="user_id" value="">
        <input type="hidden" id="stuffs_ids" name="stuffs_ids" value="">
        <input type="hidden" id="deliver_id" name="deliver_id" value="">
        
        <div class="form-sections">
            <!-- Projekt választás -->
            <div class="section-column">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-project-diagram"></i>
                        <?php echo translate('Válasszon projektet...'); ?>
                        <span class="status-badge" id="projectBadge"><?php echo translate('Választás szükséges'); ?></span>
                        
                        <!-- Típus szűrő menü -->
                        <div class="type-filter">
                            <button type="button" class="btn-filter" onclick="toggleTypeFilter(event)">
                                <i class="fas fa-filter"></i>
                            </button>
                            <div class="type-filter-menu" id="typeFilterMenu">
                                <div class="filter-option" onclick="filterProjects('all')">
                                    <i class="fas fa-list"></i>
                                    <?php echo translate('Összes típus'); ?>
                                </div>
                                <div class="filter-divider"></div>
                                <?php while ($type = mysqli_fetch_assoc($project_types_result)): ?>
                                <div class="filter-option" onclick="filterProjects('<?php echo htmlspecialchars($type['name']); ?>')">
                                    <i class="fas fa-tag"></i>
                                    <?php echo translate(htmlspecialchars($type['name'])); ?>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </h2>
                </div>
                <div class="section-content">
                    <div class="grid-cards" id="projectsGrid">
                        <?php if (mysqli_num_rows($projects_result) > 0): ?>
                            <?php while ($project = mysqli_fetch_assoc($projects_result)): ?>
                                <div class="card project-card" 
                                     data-project-id="<?php echo $project['id']; ?>"
                                     data-project-type="<?php echo htmlspecialchars($project['type_name']); ?>"
                                     onclick="selectProject(this)">
                                    <div class="project-info">
                                        <div class="project-name"><?php echo htmlspecialchars($project['name']); ?></div>
                                        <div class="project-dates">
                                            <div class="project-date-item">
                                                <i class="fas fa-calendar-alt"></i>
                                                <span><?php echo date('Y.m.d H:i', strtotime($project['project_startdate'])); ?></span>
                                            </div>
                                            <div class="project-date-item">
                                                <i class="fas fa-calendar-check"></i>
                                                <span><?php echo date('Y.m.d H:i', strtotime($project['project_enddate'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <p><?php echo translate('Nincs elérhető projekt'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Dolgozó választás -->
            <div class="section-column">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-user"></i>
                        <?php echo translate('Válasszon dolgozót...'); ?>
                        <span class="status-badge" id="userBadge"><?php echo translate('Választás szükséges'); ?></span>
                        
                        <!-- Szerepkör szűrő menü -->
                        <div class="type-filter">
                            <button type="button" class="btn-filter" onclick="toggleRoleFilter(event)">
                                <i class="fas fa-filter"></i>
                            </button>
                            <div class="type-filter-menu" id="roleFilterMenu">
                                <div class="filter-option" onclick="filterUsers('all')">
                                    <i class="fas fa-users"></i>
                                    <?php echo translate('Összes szerepkör'); ?>
                                </div>
                                <div class="filter-divider"></div>
                                <?php 
                                mysqli_data_seek($roles_result, 0);
                                while ($role = mysqli_fetch_assoc($roles_result)): 
                                ?>
                                <div class="filter-option" onclick="filterUsers('<?php echo htmlspecialchars($role['role_name']); ?>')">
                                    <i class="fas fa-user-tag"></i>
                                    <?php echo translate(htmlspecialchars($role['role_name'])); ?>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </h2>
                </div>
                <div class="section-content">
                    <div class="grid-cards" id="usersGrid">
                        <?php 
                        if (mysqli_num_rows($users_result) > 0):
                            while ($user = mysqli_fetch_assoc($users_result)): 
                                $isDisabled = $user['has_active_work'] > 0;
                        ?>
                            <div class="card user-card <?php echo $isDisabled ? 'disabled' : ''; ?>" 
                                 data-user-id="<?php echo $user['id']; ?>" 
                                 data-user-role="<?php echo htmlspecialchars($user['role_name']); ?>"
                                 onclick="selectUser(this)"
                                 title="<?php echo $isDisabled ? translate('A dolgozó már egy aktív munkában van') : ''; ?>">
                                <i class="fas <?php echo $isDisabled ? 'fa-user-lock' : 'fa-user'; ?>"></i>
                                <div class="user-name" title="<?php echo htmlspecialchars($user['lastname'] . ' ' . $user['firstname']); ?>">
                                    <?php echo htmlspecialchars($user['lastname'] . ' ' . $user['firstname']); ?>
                                </div>
                                <div class="user-role">
                                    <?php echo htmlspecialchars($user['role_name']); ?>
                                </div>
                            </div>
                        <?php 
                            endwhile;
                        else: 
                        ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <p><?php echo translate('Nincs elérhető dolgozó'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Eszközök választás -->
            <div class="section-column">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-tools"></i>
                        <?php echo translate('Válasszon eszközöket...'); ?>
                        <!-- Eszköz szűrő menü -->
                        <div class="type-filter">
                            <button type="button" class="btn-filter" onclick="toggleStuffFilter(event)">
                                <i class="fas fa-filter"></i>
                            </button>
                            <div class="type-filter-menu" id="stuffFilterMenu">
                                <div class="filter-option" onclick="filterStuffs('all')">
                                    <i class="fas fa-list"></i>
                                    <?php echo translate('Összes típus'); ?>
                                </div>
                                <div class="filter-divider"></div>
                                <?php
                                // Eszköz típusok lekérése
                                $stuff_types_sql = "SELECT DISTINCT st.name 
                                                  FROM stuff_type st
                                                  INNER JOIN stuffs s ON s.type_id = st.id 
                                                  WHERE s.company_id = '$company_id'
                                                  ORDER BY st.name";
                                $stuff_types_result = mysqli_query($conn, $stuff_types_sql);
                                
                                while ($type = mysqli_fetch_assoc($stuff_types_result)): 
                                ?>
                                <div class="filter-option" onclick="filterStuffs('<?php echo htmlspecialchars($type['name']); ?>')">
                                    <i class="fas fa-tools"></i>
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <button type="button" class="btn-select-all" onclick="toggleAllStuffs()">
                            <i class="fas fa-check-double"></i>
                            <span><?php echo translate('Összes kijelölése'); ?></span>
                        </button>
                        <span class="status-badge" id="stuffBadge"><?php echo translate('Választás szükséges'); ?></span>
                    </h2>
                </div>
                <div class="section-content">
                    <div class="grid-cards">
                        <?php 
                        if (mysqli_num_rows($stuffs_result) > 0):
                            while ($stuff = mysqli_fetch_assoc($stuffs_result)): 
                            $backgroundColor = isset($type_colors[$stuff['type_name']]) ? $type_colors[$stuff['type_name']] : '#F7FAFC';
                        ?>
                            <div class="card stuff-card" 
                                 data-stuff-id="<?php echo $stuff['id']; ?>" 
                                 data-type="<?php echo htmlspecialchars($stuff['type_name']); ?>"
                                 onclick="toggleStuff(this)" 
                                 style="background-color: <?php echo $backgroundColor; ?>">
                                <div>
                                    <div class="stuff-type">
                                        <?php echo htmlspecialchars($stuff['type_name']); ?>
                                    </div>
                                    <div class="stuff-details">
                                        <?php 
                                        $details = array_filter([
                                            $stuff['subtype_name'],
                                            $stuff['brand_name'],
                                            $stuff['model_name']
                                        ]);
                                        echo htmlspecialchars(implode(' - ', $details)); 
                                        ?>
                                    </div>
                                </div>
                                <div class="stuff-qr">
                                    QR: <?php echo htmlspecialchars($stuff['qr_code']); ?>
                                </div>
                            </div>
                        <?php 
                            endwhile;
                        else: 
                        ?>
                            <div class="empty-state">
                                <i class="fas fa-tools"></i>
                                <p><?php echo translate('Nincs elérhető eszköz'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="pagination-container" id="stuffsPagination"></div>
                </div>
            </div>
        </div>

        <div class="bottom-section">
            <!-- Dátum szekció -->
            <div class="section-column">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo translate('Időpontok megadása'); ?>
                        <span class="status-badge" id="dateBadge"><?php echo translate('Kitöltés szükséges'); ?></span>
                    </h2>
                </div>
                <div class="section-content">
                    <div class="date-container">
                        <div class="date-inputs">
                            <div class="date-group">
                                <label for="work_start_date">
                                    <i class="fas fa-hourglass-start"></i>
                                    <?php echo translate('Kezdő időpont'); ?>
                                </label>
                                <input type="datetime-local" 
                                       name="work_start_date" 
                                       id="work_start_date" 
                                       class="form-control" 
                                       required>
                                <small class="date-hint"><?php echo translate('A munka kezdete'); ?></small>
                            </div>
                            <div class="date-group">
                                <label for="work_end_date">
                                    <i class="fas fa-hourglass-end"></i>
                                    <?php echo translate('Befejezés időpontja'); ?>
                                </label>
                                <input type="datetime-local" 
                                       name="work_end_date" 
                                       id="work_end_date" 
                                       class="form-control" 
                                       required>
                                <small class="date-hint"><?php echo translate('Várható befejezés'); ?></small>
                            </div>
                        </div>
                        <div class="date-duration" id="dateDuration">
                            <?php echo translate('Időtartam'); ?>: <span>-</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Szállítás -->
            <div class="section-column">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-truck"></i>
                        <?php echo translate('Szállítási mód'); ?>
                        <span class="status-badge" id="deliverBadge"><?php echo translate('Választás szükséges'); ?></span>
                    </h2>
                </div>
                <div class="section-content">
                    <div class="grid-cards">
                        <?php 
                        mysqli_data_seek($delivers_result, 0);
                        if (mysqli_num_rows($delivers_result) > 0):
                            while ($deliver = mysqli_fetch_assoc($delivers_result)): 
                            $icon = isset($deliver_icons[$deliver['name']]) ? $deliver_icons[$deliver['name']] : 'fa-truck';
                        ?>
                            <div class="card deliver-card" data-deliver-id="<?php echo $deliver['id']; ?>" onclick="selectDeliver(this)">
                                <i class="fas <?php echo $icon; ?>"></i>
                                <div class="deliver-name">
                                    <?php echo translate(htmlspecialchars($deliver['name'])); ?>
                                </div>
                            </div>
                        <?php 
                            endwhile;
                        else: 
                        ?>
                            <div class="empty-state">
                                <i class="fas fa-truck"></i>
                                <p><?php echo translate('Nincs elérhető szállítási mód'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="btn-group">
            <a href="munkak.php" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                <?php echo translate('Mégse'); ?>
            </a>
            <button type="submit" class="btn btn-primary" id="saveButton" disabled>
                <i class="fas fa-save"></i>
                <?php echo translate('Munka létrehozása'); ?>
            </button>
        </div>
    </form>
</div>

<script>
// Adjuk hozzá a sections tömböt a script elejére
const sections = ['project', 'user', 'stuff', 'date', 'deliver'];

function isSelectionComplete(section) {
    switch(section) {
        case 'project':
            return document.getElementById('project_id').value !== '';
        case 'user':
            // Legalább egy felhasználó ki van választva
            return document.querySelectorAll('.user-card.selected').length > 0;
        case 'stuff':
            // Legalább egy eszköz ki van választva
            return document.querySelectorAll('.stuff-card.selected').length > 0;
        case 'date':
            return document.getElementById('work_start_date').value !== '' && 
                   document.getElementById('work_end_date').value !== '';
        case 'deliver':
            // Legalább egy szállítási mód ki van választva
            return document.querySelectorAll('.deliver-card.selected').length > 0;
        default:
            return false;
    }
}

function updateSectionStatus(section) {
    const badge = document.getElementById(section + 'Badge');
    if (isSelectionComplete(section)) {
        badge.textContent = '<?php echo translate('Kiválasztva'); ?>';
        badge.style.backgroundColor = '#C6F6D5';
        badge.style.color = '#2F855A';
    } else {
        badge.textContent = '<?php echo translate('Választás szükséges'); ?>';
        badge.style.backgroundColor = '#E2E8F0';
        badge.style.color = '#4A5568';
    }
    updateSaveButton();
}

function updateSaveButton() {
    const saveButton = document.querySelector('button[type="submit"]');
    const allComplete = sections.every(section => isSelectionComplete(section));
    
    if (allComplete) {
        saveButton.removeAttribute('disabled');
        saveButton.classList.remove('btn-disabled');
    } else {
        saveButton.setAttribute('disabled', 'disabled');
        saveButton.classList.add('btn-disabled');
    }
}

// Módosítsuk a meglévő select függvényeket
function selectProject(element) {
    // Ha a projekt disabled, ne engedjük kiválasztani
    if (element.classList.contains('disabled')) {
        showNotification('<?php echo translate('Ehhez a projekthez már van munka rendelve!'); ?>', 'error');
        return;
    }

    document.querySelectorAll('.project-card').forEach(card => {
        card.classList.remove('selected');
    });
    element.classList.add('selected');
    const projectId = element.getAttribute('data-project-id');
    document.getElementById('project_id').value = projectId;
    
    // Projekt dátumainak kinyerése
    const startDateStr = element.querySelector('.project-date-item:first-child span').textContent.trim();
    const endDateStr = element.querySelector('.project-date-item:last-child span').textContent.trim();
    
    // Dátumok konvertálása
    const projectStart = new Date(startDateStr.replace('.', '-').replace('.', '-'));
    const projectEnd = new Date(endDateStr.replace('.', '-').replace('.', '-'));
    
    // Mai dátum
    const today = new Date();
    
    // Kezdő dátum (10 perccel a projekt kezdete előtt)
    const startDate = new Date(projectStart);
    startDate.setMinutes(startDate.getMinutes() - 10);
    
    // Ha a kezdő dátum korábbi, mint a mai nap, akkor a mai napot használjuk
    if (startDate < today) {
        startDate.setTime(today.getTime());
    }
    
    // Befejező dátum (1 nappal a projekt vége után)
    const endDate = new Date(projectEnd);
    endDate.setDate(endDate.getDate() + 1);
    
    // Dátumok formázása a datetime-local input formátumára (YYYY-MM-DDThh:mm)
    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    };
    
    document.getElementById('work_start_date').value = formatDate(startDate);
    document.getElementById('work_end_date').value = formatDate(endDate);
    
    // Időtartam frissítése
    updateDateDuration();
    updateSectionStatus('project');
}

function selectUser(element) {
    // Ha a felhasználó disabled, ne engedjük kiválasztani
    if (element.classList.contains('disabled')) {
        return;
    }
    
    const userId = element.getAttribute('data-user-id');
    const startDate = document.getElementById('work_start_date').value;
    const endDate = document.getElementById('work_end_date').value;
    
    // Ellenőrizzük, hogy van-e átfedő munka az adott időszakban
    fetch(`check_user_work.php?user_id=${userId}&start_date=${startDate}&end_date=${endDate}`)
        .then(response => response.json())
        .then(data => {
            if (data.has_overlapping_work) {
                showNotification('A kiválasztott dolgozó már van munkája ebben az időszakban!', 'error');
                return;
            }
            
            // Toggle selected class
            element.classList.toggle('selected');
            
            // Update hidden input with selected user IDs
            const selectedUsers = Array.from(document.querySelectorAll('.user-card.selected'))
                .map(card => card.getAttribute('data-user-id'));
            
            document.getElementById('user_id').value = selectedUsers.join(',');
            updateSectionStatus('user');
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Hiba történt a dolgozó ellenőrzése során!', 'error');
        });
}

// Dátum változás esetén ellenőrizzük a kiválasztott dolgozókat
document.getElementById('work_start_date').addEventListener('change', checkSelectedUsers);
document.getElementById('work_end_date').addEventListener('change', checkSelectedUsers);

function checkSelectedUsers() {
    const startDate = document.getElementById('work_start_date').value;
    const endDate = document.getElementById('work_end_date').value;
    
    if (!startDate || !endDate) return;
    
    const selectedUsers = document.querySelectorAll('.user-card.selected');
    selectedUsers.forEach(card => {
        const userId = card.getAttribute('data-user-id');
        
        fetch(`check_user_work.php?user_id=${userId}&start_date=${startDate}&end_date=${endDate}`)
            .then(response => response.json())
            .then(data => {
                if (data.has_overlapping_work) {
                    card.classList.remove('selected');
                    showNotification('A dolgozó kijelölése törölve, mert már van munkája az új időszakban!', 'error');
                    
                    // Frissítjük a kiválasztott felhasználók listáját
                    const remainingSelectedUsers = Array.from(document.querySelectorAll('.user-card.selected'))
                        .map(card => card.getAttribute('data-user-id'));
                    document.getElementById('user_id').value = remainingSelectedUsers.join(',');
                    updateSectionStatus('user');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    });
}

function toggleStuff(element) {
    // Toggle selected class
    element.classList.toggle('selected');
    
    // Update hidden input with selected stuff IDs
    const selectedStuffs = Array.from(document.querySelectorAll('.stuff-card.selected'))
        .map(card => card.getAttribute('data-stuff-id'));
    
    document.getElementById('stuffs_ids').value = selectedStuffs.join(',');
    updateSectionStatus('stuff');
}

function selectDeliver(element) {
    // Ha már ki van választva ez az elem, akkor ne csináljunk semmit
    if (element.classList.contains('selected')) {
        return;
    }
    
    // Először minden kiválasztást törlünk
    document.querySelectorAll('.deliver-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Kiválasztjuk az új elemet
    element.classList.add('selected');
    
    // Frissítjük a hidden input értékét az egyetlen kiválasztott elem ID-jával
    const deliverId = element.getAttribute('data-deliver-id');
    document.getElementById('deliver_id').value = deliverId;
    
    updateSectionStatus('deliver');
}

function updateDateDuration() {
    const startDate = document.getElementById('work_start_date').value;
    const endDate = document.getElementById('work_end_date').value;
    const durationElement = document.getElementById('dateDuration');

    if (startDate && endDate) {
        const start = new Date(startDate);
        const today = new Date();
        
        // Ha a kezdő dátum korábbi, mint a mai nap
        if (start < today) {
            durationElement.innerHTML = '<span style="color: #e53e3e;"><?php echo translate('A kezdő dátum nem lehet korábbi, mint a mai nap!'); ?></span>';
            return false;
        }

        // Különbség számítása
        const diffTime = start - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        // Hónapok és napok számítása
        const months = Math.floor(diffDays / 30);
        const days = diffDays % 30;
        
        let timeText = '';
        if (months > 0) {
            timeText += months + ' <?php echo translate('hónap'); ?> ';
        }
        if (days > 0 || months === 0) {
            timeText += days + ' <?php echo translate('nap'); ?>';
        }
        
        if (diffDays === 0) {
            durationElement.innerHTML = '<?php echo translate('A munka ma kezdődik'); ?>';
        } else {
            durationElement.innerHTML = '<?php echo translate('Hátralévő idő'); ?>: <span>' + timeText + '</span>';
        }
        
        return true;
    }
    
    durationElement.innerHTML = '<?php echo translate('Időtartam'); ?>: <span>-</span>';
    return false;
}

document.getElementById('work_start_date').addEventListener('change', updateDateDuration);
document.getElementById('work_end_date').addEventListener('change', updateDateDuration);

// Minimum dátum beállítása a mai napra
const today = new Date();
const todayString = today.toISOString().slice(0, 16);
document.getElementById('work_start_date').min = todayString;
document.getElementById('work_end_date').min = todayString;

// Egyetlen form submit eseménykezelő
document.addEventListener('DOMContentLoaded', () => {
    updateSaveButton();
    
    document.getElementById('workForm').addEventListener('submit', function(e) {
        e.preventDefault(); // Ideiglenesen megállítjuk a küldést
        
        // Debug információk
        console.log('<?php echo translate('Form elküldése előtti értékek'); ?>:');
        console.log('project_id:', document.getElementById('project_id').value);
        console.log('user_id:', document.getElementById('user_id').value);
        console.log('stuffs_ids:', document.getElementById('stuffs_ids').value);
        console.log('deliver_id:', document.getElementById('deliver_id').value);
        console.log('work_start_date:', document.getElementById('work_start_date').value);
        console.log('work_end_date:', document.getElementById('work_end_date').value);

        // Ellenőrizzük, hogy minden szükséges mező ki van-e töltve
        if (!sections.every(section => isSelectionComplete(section))) {
            alert('<?php echo translate('Kérjük, töltsön ki minden szükséges mezőt!'); ?>');
            return;
        }

        // Ellenőrizzük a dátumokat
        if (!updateDateDuration()) {
            alert('<?php echo translate('Kérjük, ellenőrizze az időpontokat!'); ?>');
            return;
        }

        // Ha minden rendben, ténylegesen elküldjük a formot
        this.submit();
    });
});

// A script szekcióhoz adjuk hozzá
function toggleTypeFilter(event) {
    event.stopPropagation();
    event.preventDefault();
    const menu = document.getElementById('typeFilterMenu');
    menu.classList.toggle('show');
}

// Kattintás eseménykezelő a dokumentumra a menü bezárásához
document.addEventListener('click', function(event) {
    const typeMenu = document.getElementById('typeFilterMenu');
    const roleMenu = document.getElementById('roleFilterMenu');
    const btnFilter = event.target.closest('.btn-filter');
    
    if (!btnFilter) {
        if (typeMenu.classList.contains('show')) {
            typeMenu.classList.remove('show');
        }
        if (roleMenu.classList.contains('show')) {
            roleMenu.classList.remove('show');
        }
    }
});

function filterProjects(type) {
    event.preventDefault();
    const projectCards = document.querySelectorAll('.project-card');
    const menu = document.getElementById('typeFilterMenu');
    menu.classList.remove('show');

    projectCards.forEach(card => {
        if (type === 'all' || card.dataset.projectType === type) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });

    // Üres állapot kezelése
    const visibleCards = Array.from(projectCards).filter(card => 
        card.style.display !== 'none'
    ).length;

    const projectsGrid = document.getElementById('projectsGrid');
    const existingEmptyState = projectsGrid.querySelector('.empty-state');

    if (visibleCards === 0) {
        if (!existingEmptyState) {
            const emptyState = document.createElement('div');
            emptyState.className = 'empty-state';
            emptyState.innerHTML = `
                <i class="fas fa-filter"></i>
                <p><?php echo translate('Nincs projekt a kiválasztott típussal'); ?></p>
            `;
            projectsGrid.appendChild(emptyState);
        } else {
            existingEmptyState.style.display = '';
        }
    } else if (existingEmptyState) {
        existingEmptyState.style.display = 'none';
    }
}

function toggleRoleFilter(event) {
    event.stopPropagation();
    event.preventDefault();
    const menu = document.getElementById('roleFilterMenu');
    menu.classList.toggle('show');
}

function filterUsers(role) {
    event.preventDefault();
    const userCards = document.querySelectorAll('.user-card');
    const menu = document.getElementById('roleFilterMenu');
    menu.classList.remove('show');

    userCards.forEach(card => {
        if (role === 'all' || card.dataset.userRole === role) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });

    // Üres állapot kezelése
    const visibleCards = Array.from(userCards).filter(card => 
        card.style.display !== 'none'
    ).length;

    const usersGrid = document.getElementById('usersGrid');
    const existingEmptyState = usersGrid.querySelector('.empty-state');

    if (visibleCards === 0) {
        if (!existingEmptyState) {
            const emptyState = document.createElement('div');
            emptyState.className = 'empty-state';
            emptyState.innerHTML = `
                <i class="fas fa-filter"></i>
                <p><?php echo translate('Nincs dolgozó a kiválasztott szerepkörrel'); ?></p>
            `;
            usersGrid.appendChild(emptyState);
        } else {
            existingEmptyState.style.display = '';
        }
    } else if (existingEmptyState) {
        existingEmptyState.style.display = 'none';
    }
}

function toggleStuffFilter(event) {
    event.stopPropagation();
    event.preventDefault();
    const menu = document.getElementById('stuffFilterMenu');
    menu.classList.toggle('show');
}

let currentPage = 1;
const itemsPerPage = 9;
let filteredStuffs = [];

function updateStuffsPagination() {
    const stuffCards = filteredStuffs.length > 0 ? filteredStuffs : Array.from(document.querySelectorAll('.stuff-card'));
    const totalPages = Math.ceil(stuffCards.length / itemsPerPage);
    
    // Minden kártyát elrejtünk először
    stuffCards.forEach(card => card.classList.add('hidden-card'));
    
    // Csak az aktuális oldalhoz tartozó kártyákat jelenítjük meg
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, stuffCards.length);
    
    for (let i = startIndex; i < endIndex; i++) {
        stuffCards[i].classList.remove('hidden-card');
    }
    
    // Pagination információk frissítése
    const paginationContainer = document.getElementById('stuffsPagination');
    paginationContainer.innerHTML = `
        <button class="pagination-btn" onclick="changePage(-1)" ${currentPage === 1 ? 'disabled' : ''}>
            <i class="fas fa-chevron-left"></i>
        </button>
        <span class="pagination-info">
            ${startIndex + 1}-${endIndex} / ${stuffCards.length}
        </span>
        <button class="pagination-btn" onclick="changePage(1)" ${currentPage === totalPages ? 'disabled' : ''}>
            <i class="fas fa-chevron-right"></i>
        </button>
    `;
}

function changePage(direction) {
    const stuffCards = filteredStuffs.length > 0 ? filteredStuffs : Array.from(document.querySelectorAll('.stuff-card'));
    const totalPages = Math.ceil(stuffCards.length / itemsPerPage);
    
    const newPage = currentPage + direction;
    if (newPage >= 1 && newPage <= totalPages) {
        currentPage = newPage;
        updateStuffsPagination();
    }
}

function filterStuffs(type) {
    event.preventDefault();
    const stuffCards = Array.from(document.querySelectorAll('.stuff-card'));
    const menu = document.getElementById('stuffFilterMenu');
    menu.classList.remove('show');

    // Először minden kártyát elrejtünk
    stuffCards.forEach(card => card.classList.add('hidden-card'));

    filteredStuffs = stuffCards.filter(card => {
        const cardType = card.dataset.type;
        return type === 'all' || cardType === type;
    });

    // Reset to first page when filtering
    currentPage = 1;
    
    if (filteredStuffs.length === 0) {
        const stuffsGrid = document.querySelector('.section-content .grid-cards');
        const existingEmptyState = stuffsGrid.querySelector('.empty-state');
        
        if (!existingEmptyState) {
            const emptyState = document.createElement('div');
            emptyState.className = 'empty-state';
            emptyState.innerHTML = `
                <i class="fas fa-filter"></i>
                <p><?php echo translate('Nincs eszköz a kiválasztott típussal'); ?></p>
            `;
            stuffsGrid.appendChild(emptyState);
        } else {
            existingEmptyState.style.display = '';
        }
    } else {
        // Csak az első oldalon lévő elemeket jelenítjük meg
        const startIndex = 0;
        const endIndex = Math.min(itemsPerPage, filteredStuffs.length);
        
        for (let i = startIndex; i < endIndex; i++) {
            filteredStuffs[i].classList.remove('hidden-card');
        }
    }
    
    updateStuffsPagination();
    
    // Frissítjük a "Összes kijelölése" gomb szövegét
    const button = document.querySelector('.btn-select-all span');
    button.textContent = '<?php echo translate('Összes kijelölése'); ?>';
}

function toggleAllStuffs() {
    const allFilteredCards = filteredStuffs.length > 0 ? 
        filteredStuffs : 
        Array.from(document.querySelectorAll('.stuff-card'));
    
    // Ellenőrizzük, hogy van-e olyan kártya, ami nincs kiválasztva
    const hasUnselected = allFilteredCards.some(card => !card.classList.contains('selected'));
    
    allFilteredCards.forEach(card => {
        if (hasUnselected) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    });
    
    // Frissítjük a hidden input értékét
    const selectedStuffs = Array.from(document.querySelectorAll('.stuff-card.selected'))
        .map(card => card.getAttribute('data-stuff-id'));
    
    document.getElementById('stuffs_ids').value = selectedStuffs.join(',');
    updateSectionStatus('stuff');
    
    // Frissítjük a gomb szövegét
    const button = document.querySelector('.btn-select-all span');
    button.textContent = hasUnselected ? '<?php echo translate('Kijelölés törlése'); ?>' : '<?php echo translate('Összes kijelölése'); ?>';
}

// Értesítés megjelenítése funkció (ha még nem létezik)
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 500);
    }, 3000);
}

// Adjuk hozzá a DOMContentLoaded eseménykezelőhöz:
document.addEventListener('DOMContentLoaded', () => {
    // ... existing code ...
    updateStuffsPagination();
});
</script>

<?php require_once '../includes/layout/footer.php'; ?> 