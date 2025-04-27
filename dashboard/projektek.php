<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Debug információ
if (!isset($_SESSION)) {
    session_start();
}
error_log('Session tartalom: ' . print_r($_SESSION, true));

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Kapcsolódási hiba: " . mysqli_connect_error());
}

// Ellenőrizzük és állítsuk be a company_id-t ha nincs
if (!isset($_SESSION['company_id']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $company_query = "SELECT company_id FROM user WHERE id = ?";
    $stmt = mysqli_prepare($conn, $company_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user_data = mysqli_fetch_assoc($result)) {
        $_SESSION['company_id'] = $user_data['company_id'];
    }
}

// Jogosultság ellenőrzése
checkPageAccess();

require_once '../includes/layout/header.php';

// Projektek lekérdezése JOIN-nal a típushoz és szűrés company_id alapján
$sql = "SELECT p.*, pt.name as type_name, 
        c.name as country_name, 
        co.name as county_name, 
        ci.name as city_name,
        d.name as district_name,
        c.has_districts,
        CASE 
            WHEN NOW() BETWEEN p.project_startdate AND p.project_enddate THEN 1
            WHEN NOW() < p.project_startdate THEN 2
            ELSE 3
        END as status_order,
        CASE 
            WHEN NOW() > p.project_enddate THEN DATE_ADD(p.project_enddate, INTERVAL 7 DAY)
            ELSE NULL
        END as deletion_date
        FROM project p
        LEFT JOIN project_type pt ON p.type_id = pt.id
        LEFT JOIN countries c ON p.country_id = c.id
        LEFT JOIN counties co ON p.county_id = co.id
        LEFT JOIN districts d ON p.district_id = d.id
        LEFT JOIN cities ci ON p.city_id = ci.id
        WHERE p.company_id = ?
        ORDER BY status_order ASC, 
                 CASE 
                    WHEN status_order = 1 THEN p.project_enddate
                    WHEN status_order = 2 THEN p.project_startdate
                    ELSE p.project_enddate
                 END ASC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['company_id']);
mysqli_stmt_execute($stmt);
$projects_result = mysqli_stmt_get_result($stmt);

// Adjuk hozzá ezt a tömböt a fájl elejére a többi PHP kód elé
$typeColors = [
    translate('Fesztivál') => '#3498db',      // kék
    translate('Konferancia') => '#c2ae1b',    // piszkos sárga
    translate('Rendezvény') => '#9b59b6',     // lila
    translate('Előadás') => '#34495e',        // sötétszürke
    translate('Kiállitás') => '#e67e22',      // narancssárga
    translate('Jótékonysági') => '#fa93ce',   // halvány rózsaszín
    translate('Ünnepség') => '#16a085',       // türkiz
    translate('Egyéb') => '#95a5a6'           // szürke
];
?>

<style>
.projects-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: fixed;
    top: 60px;  /* A navbar magasságához igazítva */
    left: 0;
    right: 0;
    padding: 1rem 2rem;
    z-index: 100;
}

.projects-header h1 {
    margin: 0;
    color: #2c3e50;
    font-size: 1.8rem;
}

.btn-new-project {
    background: #3498db;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    font-weight: 500;
}

.btn-new-project:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.projects-grid {
    margin-top: 80px;
    display: grid;
    grid-template-columns: repeat(3, 350px);  /* Csökkentett szélesség 350px-re */
    gap: 2rem;
    padding: 2rem;
    justify-content: center;
    margin-left: auto;
    margin-right: auto;
}

.project-card {
    width: 350px;  /* Csökkentett szélesség 350px-re */
    min-height: 250px;  /* Kicsit csökkentett minimum magasság */
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s, opacity 0.3s;
    display: flex;
    flex-direction: column;
    position: relative; /* Added for status bar positioning */
}

.project-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.project-image {
    width: 100%;
    height: 200px;
    overflow: hidden;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
    position: relative;
}

.project-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.project-image .no-image {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8fafc;
    color: #94a3b8;
}

.project-image .no-image i {
    font-size: 3rem;
}

.project-header {
    padding: 1rem;
    border-bottom: 1px solid #edf2f7;
}

.project-title {
    margin: 0;
    font-size: 1.25rem;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.project-type {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.875rem;
    color: white;          /* Fehér szöveg minden típusnál */
    font-weight: 500;      /* Kicsit vastagabb betű */
    text-shadow: 0 1px 1px rgba(0,0,0,0.1);  /* Jobb olvashatóság */
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);   /* Enyhe árnyék */
}

/* Hover effekt a típus címkékhez */
.project-type:hover {
    filter: brightness(1.1);
    transition: filter 0.2s ease;
}

.project-body {
    flex: 1;  /* Kitölti a rendelkezésre álló teret */
    padding: 1.5rem;
}

.project-info {
    display: grid;
    gap: 1rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #6b7280;
}

.info-item i {
    width: 20px;
    text-align: center;
    color: #3498db;
}

.project-footer {
    padding: 1rem 1.5rem;
    background: #f8f9fa;
    display: flex;
    justify-content: flex-end;
    align-items: center;
}

.project-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    padding: 0.5rem;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-edit {
    background: #e9ecef;
    color: #495057;
}

.btn-edit:hover {
    background: #dee2e6;
}

.btn-delete {
    background: #fee2e2;
    color: #dc2626;
}

.btn-delete:hover {
    background: #fecaca;
}

.btn-info {
    background: #e3f2fd;
    color: #1976d2;
}

.btn-info:hover {
    background: #bbdefb;
}

/* Reszponzív viselkedés módosítása */
@media (max-width: 1200px) {
    .projects-grid {
        grid-template-columns: repeat(2, 350px);
    }
}

@media (max-width: 800px) {
    .projects-grid {
        grid-template-columns: 350px;
    }
}

@media (max-width: 400px) {
    .projects-grid {
        grid-template-columns: 1fr;
    }
    
    .project-card {
        width: 100%;
    }
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    z-index: 1000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background-color: white;
    padding: 2rem;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
    position: relative;
}

.close-modal {
    position: absolute;
    right: 1rem;
    top: 1rem;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.modal-body {
    margin-top: 1.5rem;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 6px;
}

.info-row i {
    color: #3498db;
    width: 20px;
    text-align: center;
}

.location-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.location-details span:not(:empty)::before {
    content: '•';
    margin-right: 0.5rem;
    color: #3498db;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #2c3e50;
}

.form-control {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.date-inputs {
    display: flex;
    gap: 1rem;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
}

.btn-save {
    background: #3498db;
    color: white;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn-cancel {
    background: #e9ecef;
    color: #495057;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.accordion {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.accordion-item {
    border-bottom: 1px solid #e2e8f0;
}

.accordion-item:last-child {
    border-bottom: none;
}

.accordion-header {
    padding: 1rem;
    background: #f8fafc;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background-color 0.3s;
}

.accordion-header:hover {
    background: #f1f5f9;
}

.accordion-header i {
    transition: transform 0.3s;
}

.accordion-item.active .accordion-header i {
    transform: rotate(180deg);
}

.accordion-content {
    padding: 0;
    max-height: 0;
    overflow: hidden;
    transition: all 0.3s ease-out;
    background: white;
}

.accordion-item.active .accordion-content {
    padding: 1rem;
    max-height: 500px;
}

.modal-content {
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
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

.date-input-group {
    flex: 1;
}

.date-input-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #2c3e50;
    font-size: 0.9rem;
}

/* Módosítsuk az image-upload-container stílusát */
.accordion-content .image-upload-container {
    width: 100%;
    height: 250px;
    margin: 0;
    border-radius: 8px;
    overflow: hidden;
    background: #f8fafc;
    border: 3px dashed #e2e8f0;
}

.accordion-content .form-group {
    margin: 0;
    padding: 1rem;
}

.edit-image-container {
    position: relative;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.edit-image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
    color: white;
    border-radius: 8px;
}

.edit-image-overlay i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.edit-image-container:hover .edit-image-overlay {
    opacity: 1;
}

.mt-2 {
    margin-top: 0.5rem;
}

.date-input-group {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Add new styles for completed projects */
.project-card.completed::before,
.project-card.in-progress::before {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    text-align: center;
    padding: 8px;
    font-weight: 500;
    z-index: 1;
}

.project-card.completed::before {
    content: '<?php echo translate("Befejeződött"); ?>';
    background: rgba(0, 0, 0, 0.7);
    color: white;
}

.project-card.in-progress::before {
    content: '<?php echo translate("Folyamatban lévő"); ?>';
    background: rgba(52, 152, 219, 0.9);
    color: white;
}

.project-card.completed .project-actions button.btn-edit,
.project-card.completed .project-actions button.btn-delete,
.project-card.in-progress .project-actions button.btn-edit,
.project-card.in-progress .project-actions button.btn-delete {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.project-card.completed .project-actions button.btn-edit:hover,
.project-card.completed .project-actions button.btn-delete:hover,
.project-card.in-progress .project-actions button.btn-edit:hover,
.project-card.in-progress .project-actions button.btn-delete:hover {
    transform: none;
    box-shadow: none;
}

.project-card.completed {
    opacity: 0.8;
    filter: blur(0.5px);
    background: rgba(255, 255, 255, 0.95);
}

.project-card.in-progress {
    border: 2px solid #3498db;
    box-shadow: 0 0 10px rgba(52, 152, 219, 0.3);
}

.project-card.completed:hover,
.project-card.in-progress:hover {
    transform: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.project-card.in-progress:hover {
    box-shadow: 0 0 15px rgba(52, 152, 219, 0.4);
}

.project-card.completed .countdown,
.project-card:not(.completed):not(.in-progress) .countdown {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    text-align: center;
    padding: 4px;
    font-size: 0.8rem;
    z-index: 1;
}

.project-card.completed .project-footer,
.project-card:not(.completed):not(.in-progress) .project-footer {
    position: relative;
    z-index: 2;
    background: #f8f9fa;
    margin-bottom: 28px;
}

.project-card.completed .project-body {
    padding-bottom: 0; /* Remove extra padding */
}

/* Filter styles */
.filter-container {
    margin: 20px 0;
    padding: 0 20px;
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filter-header {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 15px;
}

.filter-btn {
    background: #f8f9fa;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    color: #495057;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-btn i {
    font-size: 1rem;
}

.filter-btn:hover {
    background: #e9ecef;
    transform: translateY(-1px);
}

.filter-btn.active {
    background: #3498db;
    color: white;
}

.filter-btn.active i {
    color: white;
}

.filter-btn {
    cursor: pointer !important;
}
</style>

<div id="notification" class="notification" style="display: none;"></div>

<div class="projects-header">
    <h1><?php echo translate('Projektek'); ?></h1>
    <a href="uj_projekt.php" class="btn-new-project">
        <i class="fas fa-plus"></i>
        <?php echo translate('Új projekt'); ?>
    </a>
</div>

<div class="filter-container">
    <div class="filter-header">
        <button type="button" class="filter-btn active" data-type="all">
            <i class="fas fa-th-large"></i>
            <?php echo translate('Összes'); ?>
        </button>
        <?php
        // Project típusok lekérése
        $type_sql = "SELECT DISTINCT pt.name, pt.id 
                    FROM project_type pt 
                    INNER JOIN project p ON p.type_id = pt.id 
                    WHERE p.company_id = " . $_SESSION['company_id'];
        $type_result = mysqli_query($conn, $type_sql);
        while ($type = mysqli_fetch_assoc($type_result)) {
            $icon = '';
            switch(strtolower($type['name'])) {
                case 'fesztivál':
                    $icon = 'fa-music';
                    break;
                case 'előadás':
                    $icon = 'fa-theater-masks';
                    break;
                default:
                    $icon = 'fa-calendar-alt';
            }
            echo '<button type="button" class="filter-btn" data-type="' . htmlspecialchars($type['id']) . '">';
            echo '<i class="fas ' . $icon . '"></i> ';
            echo htmlspecialchars($type['name']);
            echo '</button>';
        }
        ?>
    </div>
</div>

<div class="projects-grid">
    <?php
    if (mysqli_num_rows($projects_result) > 0) {
        while ($project = mysqli_fetch_assoc($projects_result)) {
            $start_date = new DateTime($project['project_startdate']);
            $end_date = new DateTime($project['project_enddate']);
            $now = new DateTime();
            $is_completed = $now > $end_date;
            $is_in_progress = $now >= $start_date && $now <= $end_date;
            $card_class = 'project-card';
            if ($is_completed) {
                $card_class .= ' completed';
                $deletion_date = clone $end_date;
                $deletion_date->modify('+7 days');
            } elseif ($is_in_progress) {
                $card_class .= ' in-progress';
            }
            ?>
            <div class="<?php echo $card_class; ?>" 
                 data-project-id="<?php echo $project['id']; ?>"
                 data-type-id="<?php echo $project['type_id']; ?>"
                 <?php if ($is_completed): ?>
                 data-deletion-date="<?php echo $deletion_date->format('Y-m-d H:i:s'); ?>"
                 <?php elseif (!$is_in_progress): ?>
                 data-start-date="<?php echo $start_date->format('Y-m-d H:i:s'); ?>"
                 <?php endif; ?>>
                <div class="project-image">
                    <?php if ($project['picture']): ?>
                        <img src="../<?php echo htmlspecialchars($project['picture']); ?>" alt="<?php echo htmlspecialchars($project['name']); ?>">
                    <?php else: ?>
                        <div class="no-image">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="project-header">
                    <h3 class="project-title"><?php echo htmlspecialchars($project['name']); ?></h3>
                    <div class="project-type" style="background-color: <?php echo $typeColors[translate($project['type_name'])]; ?>">
                        <?php echo translate($project['type_name']); ?>
                    </div>
                </div>
                <div class="project-body">
                    <div class="project-info">
                        <div class="info-item">
                            <i class="fas fa-calendar"></i>
                            <span class="date-info" 
                                  data-start="<?php echo $project['project_startdate']; ?>"
                                  data-end="<?php echo $project['project_enddate']; ?>">
                                <?php echo $start_date->format('Y.m.d'); ?>
                                -
                                <?php echo $end_date->format('Y.m.d'); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-clock"></i>
                            <span class="time-info">
                                <?php echo $start_date->format('H:i'); ?>
                                -
                                <?php echo $end_date->format('H:i'); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span class="location-info" 
                                  data-country="<?php echo htmlspecialchars($project['country_name'] ?? ''); ?>"
                                  data-county="<?php echo htmlspecialchars($project['county_name'] ?? ''); ?>"
                                  data-city="<?php echo htmlspecialchars($project['city_name'] ?? ''); ?>"
                                  data-district="<?php echo htmlspecialchars($project['district_name'] ?? ''); ?>"
                                  data-has-districts="<?php echo $project['has_districts'] ?? 0; ?>">
                                <?php echo htmlspecialchars($project['city_name'] ?? ''); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="project-footer">
                    <div class="project-actions">
                        <button class="btn-action btn-info" onclick="showProjectDetails(<?php echo $project['id']; ?>)">
                            <i class="fas fa-info-circle"></i>
                        </button>
                        <?php
                        // Check if the project is currently in progress
                        $now = new DateTime();
                        $start_date = new DateTime($project['project_startdate']);
                        $end_date = new DateTime($project['project_enddate']);
                        $is_in_progress = $now >= $start_date && $now <= $end_date;
                        
                        if (!$is_in_progress) {
                            // Only show edit and delete buttons if the project is not in progress
                            echo '<button class="btn-action btn-edit" onclick="editProject(' . $project['id'] . ')">';
                            echo '<i class="fas fa-edit"></i>';
                            echo '</button>';
                            
                            echo '<button class="btn-action btn-delete" onclick="deleteProject(' . $project['id'] . ')">';
                            echo '<i class="fas fa-trash"></i>';
                            echo '</button>';
                        } else {
                            // Show disabled buttons with tooltip for in-progress projects
                            echo '<button class="btn-action btn-edit" disabled title="' . translate('A projekt folyamatban van, nem szerkeszthető') . '" style="opacity: 0.5; cursor: not-allowed;">';
                            echo '<i class="fas fa-edit"></i>';
                            echo '</button>';
                            
                            echo '<button class="btn-action btn-delete" disabled title="' . translate('A projekt folyamatban van, nem törölhető') . '" style="opacity: 0.5; cursor: not-allowed;">';
                            echo '<i class="fas fa-trash"></i>';
                            echo '</button>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php
        }
    } else {
        echo '<div class="no-projects">' . translate('Nincsenek még projektek.') . '</div>';
    }
    ?>
</div>

<div id="projectModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2 id="modal-title"></h2>
        <div class="modal-body">
            <div class="info-row">
                <i class="fas fa-tag"></i>
                <span id="modal-type"></span>
            </div>
            <div class="info-row">
                <i class="fas fa-calendar"></i>
                <span id="modal-date"></span>
            </div>
            <div class="info-row">
                <i class="fas fa-clock"></i>
                <span id="modal-time"></span>
            </div>
            <div class="info-row">
                <i class="fas fa-map-marker-alt"></i>
                <div class="location-details">
                    <span id="modal-country"></span>
                    <span id="modal-county"></span>
                    <span id="modal-city"></span>
                    <span id="modal-location"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="editProjectModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close-modal" onclick="closeEditModal()">&times;</span>
        <h2><?php echo translate('Projekt szerkesztése'); ?></h2>
        <div class="modal-body">
            <form id="editProjectForm" onsubmit="saveProjectChanges(event)">
                <input type="hidden" id="edit-project-id">
                
                <div class="accordion">
                    <!-- Kép panel -->
                    <div class="accordion-item">
                        <div class="accordion-header" onclick="toggleAccordion(this)">
                            <span><?php echo translate('Projekt képe'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accordion-content">
                            <div class="form-group">
                                <div class="image-upload-container">
                                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                    <div class="image-upload-text"><?php echo translate('Kattintson vagy húzza ide a képet'); ?></div>
                                    <div id="edit_image_preview"></div>
                                    <input type="file" 
                                           id="edit_project_image" 
                                           name="project_image" 
                                           accept="image/*"
                                           onchange="previewImage(this);">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Név panel -->
                    <div class="accordion-item">
                        <div class="accordion-header" onclick="toggleAccordion(this)">
                            <span><?php echo translate('Projekt neve'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accordion-content">
                            <div class="form-group">
                                <input type="text" id="edit-name" class="form-control">
                            </div>
                        </div>
                    </div>

                    <!-- Dátum panel -->
                    <div class="accordion-item">
                        <div class="accordion-header" onclick="toggleAccordion(this)">
                            <span><?php echo translate('Projekt időpontja'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accordion-content">
                            <div class="form-group">
                                <div class="date-inputs">
                                    <div class="date-input-group">
                                        <label for="edit-start-date"><?php echo translate('Kezdő dátum:'); ?></label>
                                        <input type="date" 
                                               id="edit-start-date" 
                                               class="form-control"
                                               onchange="validateDates(this.value, 'start')"
                                               pattern="\d{4}-\d{2}-\d{2}">
                                        <label for="edit-start-time" class="mt-2"><?php echo translate('Kezdő időpont:'); ?></label>
                                        <input type="time" 
                                               id="edit-start-time" 
                                               class="form-control"
                                               onchange="validateTimes(this.value, 'start')">
                                    </div>
                                    <div class="date-input-group">
                                        <label for="edit-end-date"><?php echo translate('Záró dátum:'); ?></label>
                                        <input type="date" 
                                               id="edit-end-date" 
                                               class="form-control"
                                               onchange="validateDates(this.value, 'end')"
                                               pattern="\d{4}-\d{2}-\d{2}">
                                        <label for="edit-end-time" class="mt-2"><?php echo translate('Záró időpont:'); ?></label>
                                        <input type="time" 
                                               id="edit-end-time" 
                                               class="form-control"
                                               onchange="validateTimes(this.value, 'end')">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Helyszín panel -->
                    <div class="accordion-item">
                        <div class="accordion-header" onclick="toggleAccordion(this)">
                            <span><?php echo translate('Projekt helyszíne'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accordion-content">
                            <div class="form-group">
                                <label for="edit-country"><?php echo translate('Ország:'); ?></label>
                                <select id="edit-country" class="form-control" onchange="loadCounties()"></select>
                            </div>
                            <div class="form-group">
                                <label for="edit-county"><?php echo translate('Megye:'); ?></label>
                                <select id="edit-county" class="form-control" onchange="loadCities()"></select>
                            </div>
                            <div class="form-group">
                                <label for="edit-city"><?php echo translate('Város:'); ?></label>
                                <select id="edit-city" class="form-control"></select>
                            </div>
                            <div class="form-group">
                                <label for="edit-location"><?php echo translate('Helyszín neve:'); ?></label>
                                <input type="text" id="edit-location" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()"><?php echo translate('Mégse'); ?></button>
                    <button type="submit" class="btn-save"><?php echo translate('Mentés'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let originalValues = {
    name: null,
    start_date: null,
    end_date: null,
    country_id: null,
    county_id: null,
    city_id: null,
    location_name: null
};

function validateDates(date, type) {
    if (type === 'start') {
        const startDate = new Date(date);
        const endDateInput = document.getElementById('edit-end-date');
        
        // Mindig állítsuk be a következő napot záró dátumnak
        const nextDay = new Date(startDate);
        nextDay.setDate(startDate.getDate() + 1);
        
        // Dátum formázása YYYY-MM-DD formátumra
        const formattedDate = nextDay.toISOString().split('T')[0];
        endDateInput.value = formattedDate;
    }
}

function validateTimes(time, type) {
    // Implementálás
}

async function editProject(id) {
    const card = document.querySelector(`[data-project-id="${id}"]`);
    const dateInfo = card.querySelector('.date-info');
    const timeInfo = card.querySelector('.time-info');
    
    // Eredeti értékek mentése
    originalValues = {
        id: id,
        name: card.querySelector('.project-title').textContent,
        location_name: card.querySelector('.location-info').textContent,
        country_id: card.dataset.countryId,
        county_id: card.dataset.countyId,
        city_id: card.dataset.cityId,
        picture: card.querySelector('.project-image img')?.src,
        start_date: dateInfo.dataset.start.split(' ')[0],
        end_date: dateInfo.dataset.end.split(' ')[0],
        start_time: dateInfo.dataset.start.split(' ')[1],
        end_time: dateInfo.dataset.end.split(' ')[1]
    };

    // Form mezők feltöltése
    document.getElementById('edit-project-id').value = id;
    document.getElementById('edit-name').value = originalValues.name;
    document.getElementById('edit-start-date').value = originalValues.start_date;
    document.getElementById('edit-end-date').value = originalValues.end_date;
    document.getElementById('edit-start-time').value = originalValues.start_time;
    document.getElementById('edit-end-time').value = originalValues.end_time;

    // Kép előnézet beállítása
    const preview = document.getElementById('edit_image_preview');
    const uploadIcon = document.querySelector('#editProjectModal .upload-icon');
    const uploadText = document.querySelector('#editProjectModal .image-upload-text');
    const fileInput = document.getElementById('edit_project_image');
    
    // Töröljük a régi előnézetet
    preview.innerHTML = '';
    
    if (originalValues.picture) {
        const imgContainer = document.createElement('div');
        imgContainer.className = 'edit-image-container';
        
        const img = document.createElement('img');
        img.src = originalValues.picture;
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';
        img.style.borderRadius = '8px';
        
        const overlay = document.createElement('div');
        overlay.className = 'edit-image-overlay';
        overlay.innerHTML = '<i class="fas fa-camera"></i><span><?php echo translate('Kép módosítása'); ?></span>';
        
        imgContainer.appendChild(img);
        imgContainer.appendChild(overlay);
        preview.appendChild(imgContainer);
        
        // A teljes konténerre rakjuk a click eseményt
        imgContainer.addEventListener('click', () => fileInput.click());
        
        preview.style.display = 'block';
        uploadIcon.style.opacity = '0';
        uploadText.style.opacity = '0';
    } else {
        preview.style.display = 'none';
        uploadIcon.style.opacity = '1';
        uploadText.style.opacity = '1';
    }

    // Helyszín adatok betöltése
    await loadCountries();
    document.getElementById('edit-country').value = originalValues.country_id;
    
    await loadCounties();
    document.getElementById('edit-county').value = originalValues.county_id;
    
    await loadCities();
    document.getElementById('edit-city').value = originalValues.city_id;
    
    // Modal megjelenítése
    document.getElementById('editProjectModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editProjectModal').style.display = 'none';
}

function convertDateFormat(dateStr) {
    // Ha a dátum már YYYY-MM-DD formátumban van
    if (dateStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
        return dateStr;
    }
    // Ha a dátum YYYY.MM.DD formátumban van
    const parts = dateStr.split('.');
    if (parts.length === 3) {
        return `${parts[0]}-${parts[1].padStart(2, '0')}-${parts[2].padStart(2, '0')}`;
    }
    // Ha a dátum más formátumban van, próbáljuk meg átalakítani
    const date = new Date(dateStr);
    return date.toISOString().split('T')[0];
}

async function loadCountries() {
    const response = await fetch('../api/get_countries.php');
    const countries = await response.json();
    const select = document.getElementById('edit-country');
    select.innerHTML = '<option value="">' + <?php echo json_encode(translate('Válassz országot...')); ?> + '</option>';
    countries.forEach(country => {
        select.innerHTML += `<option value="${country.id}">${country.name}</option>`;
    });
}

async function loadCounties() {
    const countryId = document.getElementById('edit-country').value;
    if (!countryId) return;
    
    const response = await fetch(`../api/get_counties.php?country_id=${countryId}`);
    const counties = await response.json();
    const select = document.getElementById('edit-county');
    select.innerHTML = '<option value="">' + <?php echo json_encode(translate('Válassz megyét...')); ?> + '</option>';
    counties.forEach(county => {
        select.innerHTML += `<option value="${county.id}">${county.name}</option>`;
    });
}

async function loadCities() {
    const countyId = document.getElementById('edit-county').value;
    if (!countyId) return;
    
    const response = await fetch(`../api/get_cities.php?county_id=${countyId}`);
    const cities = await response.json();
    const select = document.getElementById('edit-city');
    select.innerHTML = '<option value="">' + <?php echo json_encode(translate('Válassz várost...')); ?> + '</option>';
    cities.forEach(city => {
        select.innerHTML += `<option value="${city.id}">${city.name}</option>`;
    });
}

function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    notification.className = 'notification ' + type;
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        ${message}
    `;
    notification.style.display = 'flex';
    
    // Kis késleltetés a display: flex után, hogy a transition működjön
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);

    // 5 másodperc után kezdjük el az eltűnési animációt
    setTimeout(() => {
        notification.classList.remove('show');
        notification.classList.add('hide');
        
        // Várjuk meg az animáció végét mielőtt elrejtjük az elemet
        setTimeout(() => {
            notification.style.display = 'none';
            notification.classList.remove('hide');
        }, 500);
    }, 5000);
}

async function saveProjectChanges(event) {
    event.preventDefault();
    const changedValues = {
        id: document.getElementById('edit-project-id').value
    };

    // Név ellenőrzése
    const newName = document.getElementById('edit-name').value;
    if (newName !== originalValues.name) {
        changedValues.name = newName;
    }

    // Dátumok és időpontok ellenőrzése
    const newStartDate = document.getElementById('edit-start-date').value;
    const newEndDate = document.getElementById('edit-end-date').value;
    const newStartTime = document.getElementById('edit-start-time').value;
    const newEndTime = document.getElementById('edit-end-time').value;
    
    // Teljes datetime stringek létrehozása és mindig küldjük el őket
    changedValues.project_startdate = `${newStartDate} ${newStartTime}:00`;
    changedValues.project_enddate = `${newEndDate} ${newEndTime}:00`;

    // Debug: Kiírjuk a küldendő adatokat
    console.log('Küldendő adatok:', changedValues);

    try {
        const response = await fetch('../api/update_project.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(changedValues)
        });

        // Debug: Kiírjuk a választ
        const responseData = await response.json();
        console.log('Szerver válasz:', responseData);

        if (response.ok) {
            // Kártya frissítése
            const card = document.querySelector(`[data-project-id="${changedValues.id}"]`);
            
            // Dátum és idő frissítése
            const dateInfo = card.querySelector('.date-info');
            const timeInfo = card.querySelector('.time-info');
            
            // Frissítjük a data attribútumokat
            dateInfo.dataset.start = changedValues.project_startdate;
            dateInfo.dataset.end = changedValues.project_enddate;
            
            // Frissítjük a megjelenített szöveget
            const startDate = new Date(changedValues.project_startdate);
            const endDate = new Date(changedValues.project_enddate);
            
            dateInfo.textContent = `${startDate.toLocaleDateString('hu-HU').replace(/\s/g, '')} - ${endDate.toLocaleDateString('hu-HU').replace(/\s/g, '')}`;
            timeInfo.textContent = `${startDate.toLocaleTimeString('hu-HU', {hour: '2-digit', minute:'2-digit'})} - ${endDate.toLocaleTimeString('hu-HU', {hour: '2-digit', minute:'2-digit'})}`;

            closeEditModal();
            showNotification(<?php echo json_encode(translate('A projekt sikeresen módosítva!')); ?>);
        } else {
            showNotification(responseData.error || <?php echo json_encode(translate('Hiba történt a mentés során!')); ?>, 'error');
        }
    } catch (error) {
        console.error('Hiba:', error);
        showNotification(<?php echo json_encode(translate('Hiba történt a mentés során!')); ?>, 'error');
    }
}

async function deleteProject(id) {
    if (!confirm(<?php echo json_encode(translate('Biztosan törölni szeretné ezt a projektet?')); ?>)) {
        return;
    }

    try {
        console.log('Attempting to delete project:', id);
        const formData = new FormData();
        formData.append('project_id', id);

        const response = await fetch('../api/delete_project.php', {
            method: 'POST',
            body: formData
        });

        console.log('Delete response status:', response.status);
        const data = await response.json();
        console.log('Delete response data:', data);

        if (data.success) {
            // Projekt kártya eltávolítása az oldalról
            const projectCard = document.querySelector(`[data-project-id="${id}"]`);
            if (projectCard) {
                projectCard.style.opacity = '0';
                setTimeout(() => {
                    projectCard.remove();
                    
                    // Ha ez volt az utolsó projekt, jelenítsük meg a "Nincsenek projektek" üzenetet
                    const remainingProjects = document.querySelectorAll('.project-card');
                    if (remainingProjects.length === 0) {
                        const projectsGrid = document.querySelector('.projects-grid');
                        projectsGrid.innerHTML = '<div class="no-projects">' + <?php echo json_encode(translate('Nincsenek még projektek.')); ?> + '</div>';
                    }
                }, 300);
            }
            
            showNotification(<?php echo json_encode(translate('A projekt sikeresen törölve lett!')); ?>, 'success');
        } else {
            console.error('Delete failed:', data.error);
            showNotification(data.error || <?php echo json_encode(translate('Hiba történt a projekt törlésekor!')); ?>, 'error');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showNotification(<?php echo json_encode(translate('Hiba történt a projekt törlésekor!')); ?>, 'error');
    }
}

function showProjectDetails(id) {
    const card = document.querySelector(`[data-project-id="${id}"]`);
    const title = card.querySelector('.project-title').textContent;
    const type = card.querySelector('.project-type').textContent;
    const dateInfo = card.querySelector('.date-info');
    const timeInfo = card.querySelector('.time-info');
    const locationInfo = card.querySelector('.location-info');
    
    // Helyszín adatok kinyerése
    const country = locationInfo.dataset.country;
    const county = locationInfo.dataset.county;
    const city = locationInfo.dataset.city;
    const district = locationInfo.dataset.district;
    const hasDistricts = locationInfo.dataset.hasDistricts === "1";

    // Modal feltöltése
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-type').textContent = type;
    document.getElementById('modal-date').textContent = dateInfo.textContent.trim();
    document.getElementById('modal-time').textContent = timeInfo.textContent.trim();
    
    // Helyszín adatok megjelenítése
    const locationDetails = document.querySelector('.location-details');
    locationDetails.innerHTML = '';

    // Ország és megye mindig megjelenik
    if (country) {
        locationDetails.innerHTML += `<span>${country}</span>`;
    }
    if (county) {
        locationDetails.innerHTML += `<span>${county}</span>`;
    }
    
    // Kerület csak akkor jelenik meg, ha van és az országban használnak kerületeket
    if (hasDistricts && district) {
        locationDetails.innerHTML += `<span>${district}</span>`;
    }
    
    // Város mindig megjelenik
    if (city) {
        locationDetails.innerHTML += `<span>${city}</span>`;
    }

    // Modal megjelenítése
    document.getElementById('projectModal').style.display = 'flex';
}

// Modal bezárása
document.querySelector('.close-modal').addEventListener('click', function() {
    document.getElementById('projectModal').style.display = 'none';
});

// Modal bezárása kattintásra a háttéren
window.addEventListener('click', function(event) {
    const modal = document.getElementById('projectModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
});

function toggleAccordion(header) {
    const item = header.parentElement;
    const wasActive = item.classList.contains('active');
    
    // Minden panel bezárása
    document.querySelectorAll('.accordion-item').forEach(panel => {
        panel.classList.remove('active');
    });
    
    // Ha nem volt aktív, akkor kinyitjuk
    if (!wasActive) {
        item.classList.add('active');
    }
}

function previewImage(input) {
    const container = input.closest('.image-upload-container');
    const preview = container.querySelector('#image_preview, #edit_image_preview');
    const uploadIcon = container.querySelector('.upload-icon');
    const uploadText = container.querySelector('.image-upload-text');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const imgContainer = document.createElement('div');
            imgContainer.className = 'edit-image-container';
            
            const img = new Image();
            img.src = e.target.result;
            
            img.onload = function() {
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '8px';
                
                const overlay = document.createElement('div');
                overlay.className = 'edit-image-overlay';
                overlay.innerHTML = '<i class="fas fa-camera"></i><span><?php echo translate('Kép módosítása'); ?></span>';
                
                imgContainer.appendChild(img);
                imgContainer.appendChild(overlay);
                
                // A teljes konténerre rakjuk a click eseményt
                imgContainer.addEventListener('click', () => input.click());
                
                preview.innerHTML = '';
                preview.appendChild(imgContainer);
                preview.style.display = 'block';
                
                uploadIcon.style.opacity = '0';
                uploadText.style.opacity = '0';
            };
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.innerHTML = '';
        preview.style.display = 'none';
        uploadIcon.style.opacity = '1';
        uploadText.style.opacity = '1';
    }
}

function updateCountdowns() {
    const completedProjects = document.querySelectorAll('.project-card.completed');
    const futureProjects = document.querySelectorAll('.project-card:not(.completed):not(.in-progress)');
    
    // Handle completed projects
    completedProjects.forEach(card => {
        const deletionDate = new Date(card.dataset.deletionDate);
        const now = new Date();
        const timeLeft = deletionDate - now;
        
        if (timeLeft <= 0) {
            deleteProject(card.dataset.projectId);
            return;
        }
        
        const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
        const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
        
        let countdownText = '';
        if (days > 0) {
            countdownText = `<?php echo translate('Törlés'); ?>: ${days} <?php echo translate('nap'); ?> ${hours} <?php echo translate('óra'); ?>`;
        } else if (hours > 0) {
            countdownText = `<?php echo translate('Törlés'); ?>: ${hours} <?php echo translate('óra'); ?> ${minutes} <?php echo translate('perc'); ?>`;
        } else {
            countdownText = `<?php echo translate('Törlés'); ?>: ${minutes} <?php echo translate('perc'); ?>`;
        }
        
        let countdownElement = card.querySelector('.countdown');
        if (!countdownElement) {
            countdownElement = document.createElement('div');
            countdownElement.className = 'countdown';
            card.appendChild(countdownElement);
        }
        countdownElement.textContent = countdownText;
    });

    // Handle future projects
    futureProjects.forEach(card => {
        const startDate = new Date(card.dataset.startDate);
        const now = new Date();
        const timeLeft = startDate - now;
        
        const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
        const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
        
        let countdownText = '';
        if (days > 0) {
            countdownText = `<?php echo translate('Kezdés'); ?>: ${days} <?php echo translate('nap'); ?> ${hours} <?php echo translate('óra'); ?>`;
        } else if (hours > 0) {
            countdownText = `<?php echo translate('Kezdés'); ?>: ${hours} <?php echo translate('óra'); ?> ${minutes} <?php echo translate('perc'); ?>`;
        } else {
            countdownText = `<?php echo translate('Kezdés'); ?>: ${minutes} <?php echo translate('perc'); ?>`;
        }
        
        let countdownElement = card.querySelector('.countdown');
        if (!countdownElement) {
            countdownElement = document.createElement('div');
            countdownElement.className = 'countdown';
            card.appendChild(countdownElement);
        }
        countdownElement.textContent = countdownText;
    });
}

// Update countdowns every minute instead of every hour
setInterval(updateCountdowns, 60000);
// Initial update
updateCountdowns();

// Project type filter functionality
console.log('Filter script loaded');
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const projectCards = document.querySelectorAll('.project-card');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            console.log('Filter gomb kattintva', this.dataset.type);
            // Remove active class from all buttons
            filterBtns.forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');

            const type = this.dataset.type;

            projectCards.forEach(card => {
                if (type === 'all') {
                    card.style.display = 'flex';
                } else {
                    const projectTypeId = card.getAttribute('data-type-id');
                    if (projectTypeId === type) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        });
    });
});
</script>

<?php require_once '../includes/layout/footer.php'; ?> 