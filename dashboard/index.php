<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
require_once '../includes/db.php';

// Jogosultság ellenőrzése
checkPageAccess();

$userId = $_SESSION['user_id'];

$db = Database::getInstance()->getConnection();

// Felhasználói adatok lekérése
try {
    $stmt = $db->prepare("SELECT * FROM user WHERE id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception(translate('Felhasználó nem található'));
    }
} catch (PDOException $e) {
    error_log(translate('Adatbázis hiba') . ": " . $e->getMessage());
    header('Location: ../auth/login.php');
    exit;
}

require_once '../includes/layout/header.php';
?>

<!-- Font Awesome CDN hozzáadása -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Információs gomb -->
<div class="info-button-container">
    <button id="infoButton" class="info-btn">
        <i class="fas fa-info-circle"></i> <?php echo translate('Információ'); ?>
    </button>
</div>

<!-- Tutorial Modal -->
<div id="tutorialModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><?php echo translate('Üdvözöljük a Tutorial felületen!'); ?></h2>
        <p><?php echo translate('Kérjük válassza ki a preferált oktatási formát:'); ?></p>
        
        <div class="tutorial-options">
            <div class="tutorial-box" id="written-tutorial">
                <i class="fas fa-book"></i>
                <h3><?php echo translate('Írásos Tutorial'); ?></h3>
                <p><?php echo translate('Részletes leírás lépésről lépésre'); ?></p>
            </div>
            
            <div class="tutorial-box" id="video-tutorial">
                <i class="fas fa-video"></i>
                <h3><?php echo translate('Video Tutorial'); ?></h3>
                <p><?php echo translate('Videós bemutató magyarázattal'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Written Tutorial Modal -->
<div id="writtenTutorialModal" class="modal">
    <div class="modal-content tutorial-content">
        <span class="close" id="writtenTutorialClose">&times;</span>
        <div class="tutorial-header">
            <h2><?php echo translate('Részletes Tutorial'); ?></h2>
            <div class="progress-bar">
                <div class="progress" id="tutorialProgress"></div>
            </div>
        </div>
        
        <div class="tutorial-steps">
            <!-- 1. lépés: Áttekintés -->
            <div class="tutorial-step" data-step="1">
                <h3><?php echo translate('1. A Vezérlőpult áttekintése'); ?></h3>
                <p><?php echo translate('A vezérlőpult a rendszer központi irányítófelülete, ahol minden fontos információt egy helyen láthatsz.'); ?></p>
                <div class="tutorial-image">
                    <i class="fas fa-desktop"></i>
                </div>
                <ul>
                    <li><?php echo translate('A felső sávban találod a fő navigációs menüt'); ?></li>
                    <li><?php echo translate('Jobb felső sarokban az információs gomb és a felhasználói menü'); ?></li>
                    <li><?php echo translate('Központi részen a fő statisztikai kártyák'); ?></li>
                </ul>
            </div>
            
            <!-- 2. lépés: Eszközök -->
            <div class="tutorial-step" data-step="2" style="display: none;">
                <h3><?php echo translate('2. Eszközök kezelése'); ?></h3>
                <p><?php echo translate('Az Eszközök kártya mutatja a rendszerben regisztrált összes berendezést.'); ?></p>
                <div class="tutorial-image">
                    <i class="fas fa-tools"></i>
                </div>
                <ul>
                    <li><?php echo translate('A számláló mutatja az összes eszköz számát'); ?></li>
                    <li><?php echo translate('A "Részletek" gombra kattintva az eszközök listájához jutsz'); ?></li>
                    <li><?php echo translate('Az eszközök listában kereshetsz, szűrhetsz és új eszközt vehetsz fel'); ?></li>
                    <li><?php echo translate('Minden eszközhöz tartozik részletes adatlap és karbantartási előzmények'); ?></li>
                </ul>
            </div>
            
            <!-- 3. lépés: Projektek -->
            <div class="tutorial-step" data-step="3" style="display: none;">
                <h3><?php echo translate('3. Projektek követése'); ?></h3>
                <p><?php echo translate('A Projektek kártya az aktív munkákat és feladatokat mutatja.'); ?></p>
                <div class="tutorial-image">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <ul>
                    <li><?php echo translate('Láthatod az összes folyamatban lévő projekt számát'); ?></li>
                    <li><?php echo translate('A projektek listában követheted a határidőket'); ?></li>
                    <li><?php echo translate('Új projektet indíthatsz és hozzárendelhetsz eszközöket'); ?></li>
                    <li><?php echo translate('A projektek státuszát folyamatosan frissítheted'); ?></li>
                </ul>
            </div>
            
            <!-- 4. lépés: Karbantartások -->
            <div class="tutorial-step" data-step="4" style="display: none;">
                <h3><?php echo translate('4. Karbantartások kezelése'); ?></h3>
                <p><?php echo translate('A Függő Karbantartások kártya a tervezett és esedékes feladatokat jelzi.'); ?></p>
                <div class="tutorial-image">
                    <i class="fas fa-wrench"></i>
                </div>
                <ul>
                    <li><?php echo translate('A számláló mutatja a függőben lévő karbantartások számát'); ?></li>
                    <li><?php echo translate('Ütemezhetsz rendszeres vagy egyszeri karbantartásokat'); ?></li>
                    <li><?php echo translate('Követheted a karbantartások státuszát és előrehaladását'); ?></li>
                    <li><?php echo translate('Értesítéseket kaphatsz a közelgő karbantartásokról'); ?></li>
                </ul>
            </div>
            
            <!-- 5. lépés: Események -->
            <div class="tutorial-step" data-step="5" style="display: none;">
                <h3><?php echo translate('5. Események követése'); ?></h3>
                <p><?php echo translate('Az események szekció két fontos információs panelt tartalmaz.'); ?></p>
                <div class="tutorial-image">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <ul>
                    <li><?php echo translate('Legutóbbi események: Minden rendszerben történt változás naplózva'); ?></li>
                    <li><?php echo translate('Közelgő események: A következő napok fontos teendői'); ?></li>
                    <li><?php echo translate('Az események automatikusan frissülnek'); ?></li>
                    <li><?php echo translate('Részletes szűrési és keresési lehetőségek'); ?></li>
                </ul>
            </div>
            
            <!-- 6. lépés: Felhasználói funkciók -->
            <div class="tutorial-step" data-step="6" style="display: none;">
                <h3><?php echo translate('6. Felhasználói beállítások'); ?></h3>
                <p><?php echo translate('A rendszer személyre szabható a hatékonyabb munkavégzés érdekében.'); ?></p>
                <div class="tutorial-image">
                    <i class="fas fa-user-cog"></i>
                </div>
                <ul>
                    <li><?php echo translate('Profilbeállítások módosítása'); ?></li>
                    <li><?php echo translate('Értesítési preferenciák kezelése'); ?></li>
                    <li><?php echo translate('Jogosultságok és hozzáférések'); ?></li>
                    <li><?php echo translate('Nyelvi beállítások változtatása'); ?></li>
                </ul>
            </div>
            
            <!-- 7. lépés: Záró üzenet -->
            <div class="tutorial-step" data-step="7" style="display: none;">
                <h3><?php echo translate('Köszönjük, hogy végignézte a tutorialt!'); ?></h3>
                <div class="tutorial-image">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="final-message">
                    <p><?php echo translate('Reméljük, minden információ érthető volt és segítségére lesz a rendszer használatában.'); ?></p>
                    <p><?php echo translate('Ha bármikor elakadna vagy szeretné újra átnézni a tudnivalókat:'); ?></p>
                    <ul>
                        <li><?php echo translate('Kattintson az Információ gombra a jobb felső sarokban'); ?></li>
                        <li><?php echo translate('A tutorial bármikor újra megtekinthető'); ?></li>
                        <li><?php echo translate('Választhat az írásos és videós verzió között'); ?></li>
                    </ul>
                    <div class="support-info">
                        <i class="fas fa-life-ring"></i>
                        <p><?php echo translate('További segítségért forduljon hozzánk bizalommal!'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="tutorial-navigation">
            <button id="prevStep" class="nav-btn" disabled><?php echo translate('Előző'); ?></button>
            <button id="nextStep" class="nav-btn"><?php echo translate('Következő'); ?></button>
        </div>
    </div>
</div>

<!-- Üdvözlő üzenet megjelenítése -->
<?php if (isset($_SESSION['welcome_message'])): ?>
    <div class="welcome-alert">
        <?php 
        echo $_SESSION['welcome_message'];
        unset($_SESSION['welcome_message']);
        ?>
    </div>
<?php endif; ?>
<link rel="icon" type="image/x-icon" href="../assets/cats/naptar.png">
<div class="dashboard-content">
    <h1><?php echo translate('Vezérlőpult'); ?></h1>
    
    <div class="dashboard-cards">
        <div class="card">
            <h2><?php echo translate('Eszközök'); ?></h2>
            <?php
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM stuffs WHERE company_id = :company_id");
                $stmt->execute([':company_id' => $user['company_id']]);
                $stuffCount = $stmt->fetchColumn();
            } catch (PDOException $e) {
                error_log(translate('Hiba az eszközök számolása közben') . ": " . $e->getMessage());
                $stuffCount = 0;
            }
            ?>
            <div class="number counter-animation" data-target="<?php echo $stuffCount; ?>">0</div>
            <a href="eszkozok.php" class="details-btn"><?php echo translate('Részletek'); ?></a>
        </div>

        <div class="card">
            <h2><?php echo translate('Aktív Projektek'); ?></h2>
            <?php
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM project WHERE company_id = :company_id");
                $stmt->execute([':company_id' => $user['company_id']]);
                $projectCount = $stmt->fetchColumn();
            } catch (PDOException $e) {
                error_log(translate('Hiba a projektek számolása közben') . ": " . $e->getMessage());
                $projectCount = 0;
            }
            ?>
            <div class="number counter-animation" data-target="<?php echo $projectCount; ?>">0</div>
            <a href="projektek.php" class="details-btn"><?php echo translate('Részletek'); ?></a>
        </div>

        <div class="card">
            <h2><?php echo translate('Függő Karbantartások'); ?></h2>
            <?php
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM maintenance 
                                    WHERE company_id = :company_id 
                                    AND maintenance_status_id NOT IN (
                                        SELECT id FROM maintenance_status 
                                        WHERE name IN ('Kesz', 'Törölve')
                                    )");
                $stmt->execute([':company_id' => $user['company_id']]);
                $maintenanceCount = $stmt->fetchColumn();
            } catch (PDOException $e) {
                error_log(translate('Hiba a karbantartások számolása közben') . ": " . $e->getMessage());
                $maintenanceCount = 0;
            }
            ?>
            <div class="number counter-animation" data-target="<?php echo $maintenanceCount; ?>">0</div>
            <a href="karbantartas.php" class="details-btn"><?php echo translate('Részletek'); ?></a>
        </div>
    </div>

    <div class="events-container">
        <div class="events-section">
            <div class="events-header">
                <h2><?php echo translate('Legutóbbi események'); ?></h2>
                <!-- Szűrő hozzáadása -->
                <div class="event-filter">
                    <label for="eventTypeFilter"><?php echo translate('Esemény típusa:'); ?></label>
                    <select id="eventTypeFilter" class="filter-select">
                        <option value="all"><?php echo translate('Összes'); ?></option>
                        <option value="maintenance"><?php echo translate('Karbantartás'); ?></option>
                        <option value="project"><?php echo translate('Projekt'); ?></option>
                        <option value="work"><?php echo translate('Munka'); ?></option>
                    </select>
                </div>
            </div>
            
            <table class="events-table">
                <thead>
                    <tr>
                        <th><?php echo translate('Dátum'); ?></th>
                        <th><?php echo translate('Esemény'); ?></th>
                        <th><?php echo translate('Felhasználó'); ?></th>
                        <th><?php echo translate('Projekt'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        // Debug információk naplózása
                        error_log("Debug - User data: " . print_r($user, true));
                        
                        // Oldalszám és limit beállítása
                        $eventsPerPage = 3;
                        $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                        $offset = ($currentPage - 1) * $eventsPerPage;

                        // Összes esemény számának lekérése a lapozáshoz
                        $countQuery = "
                            SELECT COUNT(*) as total FROM (
                                -- Karbantartás események
                                SELECT m.id
                                FROM maintenance m
                                WHERE m.company_id = :company_id 
                                
                                UNION ALL
                                
                                -- Projekt események
                                SELECT p.id
                                FROM project p
                                WHERE p.company_id = :company_id 
                                AND p.project_startdate IS NOT NULL
                                
                                UNION ALL
                                
                                -- Munka események
                                SELECT w.id
                                FROM work w
                                WHERE w.company_id = :company_id 
                            ) as count_table
                        ";
                        
                        $countStmt = $db->prepare($countQuery);
                        $countStmt->execute([':company_id' => $user['company_id']]);
                        $totalEvents = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                        $totalPages = ceil($totalEvents / $eventsPerPage);
                        
                        error_log("Pagination info - Total events: {$totalEvents}, Total pages: {$totalPages}, Current page: {$currentPage}");

                        // Ha nincs esemény, akkor megjelenítjük az üres üzenetet
                        if ($totalEvents == 0) {
                            echo "<tr><td colspan='4' style='text-align: center;'>" . translate('Nincsenek legutóbbi események') . "</td></tr>";
                        } else {
                            // Események lekérése lapozással
                            $query = "
                                SELECT * FROM (
                                    -- Karbantartás események
                                    SELECT 
                                        m.servis_currectenddate as event_date,
                                        'Karbantartás' as event_type,
                                        CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')) as user_name,
                                        '' as project_name
                                    FROM maintenance m
                                    LEFT JOIN user u ON m.user_id = u.id
                                    WHERE m.company_id = :company_id 
                                    
                                    UNION ALL
                                    
                                    -- Projekt események
                                    SELECT 
                                        COALESCE(p.project_enddate, p.project_startdate) as event_date,
                                        'Projekt' as event_type,
                                        CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')) as user_name,
                                        p.name as project_name
                                    FROM project p
                                    LEFT JOIN user u ON p.user_id = u.id
                                    WHERE p.company_id = :company_id 
                                    AND p.project_startdate IS NOT NULL
                                    
                                    UNION ALL
                                    
                                    -- Munka események
                                    SELECT 
                                        COALESCE(w.work_end_date, w.work_start_date) as event_date,
                                        'Munka' as event_type,
                                        CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')) as user_name,
                                        COALESCE(p.name, '') as project_name
                                    FROM work w
                                    LEFT JOIN project p ON w.project_id = p.id
                                    LEFT JOIN user_to_work utw ON w.id = utw.work_id
                                    LEFT JOIN user u ON utw.user_id = u.id
                                    WHERE w.company_id = :company_id 
                                ) as combined_events
                                WHERE event_date IS NOT NULL
                                ORDER BY event_date DESC
                                LIMIT :limit OFFSET :offset
                            ";

                            // Debug: SQL és paraméterek naplózása
                            error_log("SQL Query: " . str_replace(':company_id', $user['company_id'], 
                                    str_replace(':limit', $eventsPerPage, 
                                    str_replace(':offset', $offset, $query))));
                            
                            $stmt = $db->prepare($query);
                            $stmt->bindValue(':company_id', $user['company_id'], PDO::PARAM_INT);
                            $stmt->bindValue(':limit', $eventsPerPage, PDO::PARAM_INT);
                            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                            
                            if (!$stmt->execute()) {
                                $errorInfo = $stmt->errorInfo();
                                error_log("SQL Execute Error: " . print_r($errorInfo, true));
                                throw new PDOException("Execute failed: " . implode(", ", $errorInfo));
                            }
                            
                            $recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            error_log("Fetched events count: " . count($recentEvents));
                            error_log("Fetched events data: " . print_r($recentEvents, true));

                            if (empty($recentEvents)) {
                                echo "<tr><td colspan='4' style='text-align: center;'>" . translate('Nincsenek legutóbbi események') . "</td></tr>";
                            } else {
                                foreach ($recentEvents as $event) {
                                    $eventClass = '';
                                    if (strtolower($event['event_type']) === 'karbantartás') {
                                        $eventClass = 'maintenance-event';
                                    } elseif (strtolower($event['event_type']) === 'munka') {
                                        $eventClass = 'work-event';
                                    } else {
                                        $eventClass = 'project-event';
                                    }
                                    
                                    echo "<tr class='event-row {$eventClass}' data-event-type='{$event['event_type']}'>";
                                    echo "<td>" . htmlspecialchars(!empty($event['event_date']) ? date('Y.m.d', strtotime($event['event_date'])) : translate('Nincs megadva')) . "</td>";
                                    echo "<td>" . htmlspecialchars(translate($event['event_type'] ?? 'Ismeretlen')) . "</td>";
                                    echo "<td>" . htmlspecialchars(!empty($event['user_name']) && trim($event['user_name']) !== ' ' ? $event['user_name'] : translate('Nincs megadva')) . "</td>";
                                    echo "<td>" . htmlspecialchars(!empty($event['project_name']) ? $event['project_name'] : '') . "</td>";
                                    echo "</tr>";
                                }
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("SQL Error in events query: " . $e->getMessage());
                        error_log("SQL State: " . $e->getCode());
                        error_log("Error Line: " . $e->getLine());
                        error_log("Error Trace: " . $e->getTraceAsString());
                        
                        echo "<tr><td colspan='4' style='text-align: center;' class='error-message'>";
                        echo translate('Nincsenek legutóbbi események');
                        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
                            echo "<br><small>Error: " . htmlspecialchars($e->getMessage()) . "</small>";
                        }
                        echo "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="javascript:void(0);" data-page="<?php echo ($currentPage - 1); ?>" class="page-link">&laquo; <?php echo translate('Előző'); ?></a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="javascript:void(0);" data-page="<?php echo $i; ?>" class="page-link <?php echo $i === $currentPage ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($currentPage < $totalPages): ?>
                    <a href="javascript:void(0);" data-page="<?php echo ($currentPage + 1); ?>" class="page-link"><?php echo translate('Következő'); ?> &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="events-section upcoming-events">
            <div class="events-header">
                <h2><?php echo translate('Közelgő események'); ?></h2>
                <div class="event-filter">
                    <label for="upcomingEventTypeFilter"><?php echo translate('Esemény típusa:'); ?></label>
                    <select id="upcomingEventTypeFilter" class="filter-select">
                        <option value="all"><?php echo translate('Összes'); ?></option>
                        <option value="maintenance"><?php echo translate('Karbantartás'); ?></option>
                        <option value="work"><?php echo translate('Munka'); ?></option>
                    </select>
                </div>
            </div>
            
            <table class="events-table">
                <thead>
                    <tr>
                        <th><?php echo translate('Dátum'); ?></th>
                        <th><?php echo translate('Típus'); ?></th>
                        <th><?php echo translate('Név'); ?></th>
                        <th><?php echo translate('Hátralévő idő'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $debug_query = "SELECT * FROM work WHERE company_id = :company_id";
                        $debug_stmt = $db->prepare($debug_query);
                        $debug_stmt->execute([':company_id' => $user['company_id']]);
                        error_log(translate('Munka tábla rekordok száma') . ": " . $debug_stmt->rowCount());

                        $query = "
                            SELECT 
                                date,
                                type,
                                name,
                                days_left,
                                CASE 
                                    WHEN type = 'Munka' AND DATEDIFF(date, CURRENT_DATE) = 0 THEN 
                                        TIMESTAMPDIFF(MINUTE, CURRENT_TIMESTAMP, date)
                                    ELSE NULL 
                                END as minutes_left,
                                CASE
                                    WHEN type = 'Munka' AND DATEDIFF(date, CURRENT_DATE) < 0 THEN 'Folyamatban'
                                    WHEN type = 'Karbantartás' AND DATEDIFF(date, CURRENT_DATE) < 0 THEN 'Folyamatban'
                                    ELSE NULL
                                END as status
                            FROM (
                                -- Karbantartási események
                                SELECT 
                                    m.servis_planenddate as date,
                                    'Karbantartás' as type,
                                    CONCAT(sb.name, ' ', sm.name) as name,
                                    DATEDIFF(m.servis_planenddate, CURRENT_DATE) as days_left
                                FROM maintenance m
                                JOIN stuffs s ON m.stuffs_id = s.id
                                JOIN stuff_brand sb ON s.brand_id = sb.id
                                JOIN stuff_model sm ON s.model_id = sm.id
                                JOIN maintenance_status ms ON m.maintenance_status_id = ms.id
                                WHERE m.company_id = :company_id 
                                AND ms.name NOT IN ('Kesz', 'Törölve', 'Befejezve')
                                AND m.servis_planenddate >= CURRENT_TIMESTAMP - INTERVAL 30 DAY

                                UNION ALL

                                -- Munka események
                                SELECT 
                                    w.work_start_date as date,
                                    'Munka' as type,
                                    p.name as name,
                                    DATEDIFF(w.work_start_date, CURRENT_DATE) as days_left
                                FROM work w
                                JOIN project p ON w.project_id = p.id
                                JOIN user_to_work utw ON w.id = utw.work_id
                                JOIN user u ON utw.user_id = u.id
                                WHERE w.company_id = :company_id 
                                AND w.work_start_date > CURRENT_DATE
                                AND (
                                    w.work_end_date IS NULL 
                                    OR w.work_end_date > CURRENT_DATE
                                )
                            ) as combined_events
                            ORDER BY date ASC, type ASC
                            LIMIT 5
                        ";

                        error_log("Közelgő események lekérdezése: " . $query);
                        
                        $stmt = $db->prepare($query);
                        $stmt->execute([':company_id' => $user['company_id']]);
                        $upcomingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Direct debugging for work events
                        $directDebugQuery = "
                            SELECT 
                                w.id, 
                                w.work_start_date, 
                                w.work_end_date, 
                                p.name as project_name,
                                DATEDIFF(w.work_start_date, CURRENT_DATE) as days_until_start,
                                DATEDIFF(w.work_end_date, CURRENT_DATE) as days_until_end
                            FROM work w
                            JOIN project p ON w.project_id = p.id
                            WHERE w.company_id = :company_id 
                            ORDER BY w.work_start_date DESC
                            LIMIT 10
                        ";
                        $directDebugStmt = $db->prepare($directDebugQuery);
                        $directDebugStmt->execute([':company_id' => $user['company_id']]);
                        $directDebugEvents = $directDebugStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        error_log("DEBUG: All work events for company {$user['company_id']}:");
                        foreach ($directDebugEvents as $debugEvent) {
                            $startDate = new DateTime($debugEvent['work_start_date']);
                            $endDate = $debugEvent['work_end_date'] ? new DateTime($debugEvent['work_end_date']) : null;
                            $now = new DateTime();
                            
                            $isStartInPast = $startDate < $now;
                            $isEndInPast = $endDate ? $endDate < $now : false;
                            
                            error_log("Work ID: {$debugEvent['id']}, Project: {$debugEvent['project_name']}");
                            error_log("  Start: {$debugEvent['work_start_date']} (Days until start: {$debugEvent['days_until_start']}, In past: " . ($isStartInPast ? 'Yes' : 'No') . ")");
                            error_log("  End: " . ($debugEvent['work_end_date'] ? $debugEvent['work_end_date'] : 'NULL') . 
                                     ($endDate ? " (Days until end: {$debugEvent['days_until_end']}, In past: " . ($isEndInPast ? 'Yes' : 'No') . ")" : ""));
                            error_log("  Should be in upcoming: " . (!$isStartInPast && (!$endDate || !$isEndInPast) ? 'Yes' : 'No'));
                        }

                        error_log("Találatok száma: " . count($upcomingEvents));
                        
                        // Filter out completed work events in PHP
                        $filteredEvents = [];
                        foreach ($upcomingEvents as $event) {
                            if ($event['type'] === 'Munka') {
                                $startDate = new DateTime($event['date']);
                                $now = new DateTime();
                                
                                // Only include work events with start date in the future
                                if ($startDate > $now) {
                                    $filteredEvents[] = $event;
                                } else {
                                    error_log("Filtered out completed work event: {$event['name']} with start date {$event['date']}");
                                }
                            } else {
                                // Include all non-work events
                                $filteredEvents[] = $event;
                            }
                        }
                        
                        $upcomingEvents = $filteredEvents;
                        error_log("Filtered events count: " . count($upcomingEvents));

                        if (empty($upcomingEvents)) {
                            echo "<tr><td colspan='4' style='text-align: center;'>" . translate('Nincsenek közelgő események') . "</td></tr>";
                        } else {
                            foreach ($upcomingEvents as $event) {
                                $daysLeft = $event['days_left'];
                                $remainingTime = '';
                                
                                if (isset($event['status']) && $event['status'] === 'Folyamatban') {
                                    $remainingTime = translate('Folyamatban');
                                } else if ($daysLeft == 0) {
                                    $remainingTime = translate('Ma');
                                    // Csak munka típusú eseményeknél mutatjuk az időt
                                    if ($event['type'] === 'Munka' && isset($event['minutes_left']) && $event['minutes_left'] > 0) {
                                        $hours = floor($event['minutes_left'] / 60);
                                        $minutes = $event['minutes_left'] % 60;
                                        
                                        $timeString = '';
                                        if ($hours > 0) {
                                            $timeString .= $hours . ' ' . translate('óra');
                                        }
                                        if ($minutes > 0) {
                                            if ($hours > 0) {
                                                $timeString .= ' ' . translate('és') . ' ';
                                            }
                                            $timeString .= $minutes . ' ' . translate('perc');
                                        }
                                        if ($timeString !== '') {
                                            $remainingTime .= ' (' . $timeString . ')';
                                        }
                                    }
                                } elseif ($daysLeft == 1) {
                                    $remainingTime = translate('Holnap');
                                } else {
                                    $remainingTime = $daysLeft . ' ' . translate('nap múlva');
                                }

                                // Esemény típus alapján különböző háttérszín
                                $rowClass = $event['type'] === 'Munka' ? 'work-event' : 'maintenance-event';

                                echo "<tr class='event-row {$rowClass}' data-event-type='{$event['type']}'>";
                                if ($event['type'] === 'Karbantartás') {
                                    echo "<td>" . ($event['date'] ? htmlspecialchars(date('Y.m.d', strtotime($event['date']))) : translate('Nincs megadva')) . "</td>";
                                } else {
                                    echo "<td>" . ($event['date'] ? htmlspecialchars(date('Y.m.d H:i', strtotime($event['date']))) : translate('Nincs megadva')) . "</td>";
                                }
                                echo "<td>" . htmlspecialchars(translate($event['type'] ?? 'Ismeretlen')) . "</td>";
                                echo "<td>" . htmlspecialchars($event['name'] ?? translate('Nincs megadva')) . "</td>";
                                echo "<td>" . htmlspecialchars($remainingTime ?? translate('Ismeretlen')) . "</td>";
                                echo "</tr>";
                            }
                        }
                    } catch (PDOException $e) {
                        error_log(translate('Hiba a közelgő események lekérdezésekor') . ": " . $e->getMessage());
                        echo "<tr><td colspan='4' style='text-align: center;'>" . translate('Hiba történt az adatok lekérdezésekor') . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
body {
    margin: 0;
    padding: 0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background-color: #f5f6fa;
}
h1{
    text-align: center;
    padding: 0;
    margin: 10px 0;
    color: #2c3e50;
    font-size: 3rem;
    font-weight: 600;
    margin-bottom: 30px;
    position: relative;
    margin-top: 20px;
}

.dashboard-content {
    flex: 1;
    padding: 40px;
    width: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-top: 0;
}

.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-bottom: 30px;
    width: 100%;
    max-width: 1400px;
}

.card {
    background: white;
    border-radius: 8px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}

.card h2 {
    color: #37474f;
    font-size: 18px;
    margin-bottom: 20px;
    font-weight: normal;
}

.number {
    font-size: 48px;
    color: #3498db;
    margin-bottom: 20px;
    transition: color 0.3s ease;
    font-weight: 600;
    text-shadow: 0 2px 4px rgba(52, 152, 219, 0.2);
}

.number.highlight {
    color: #2980b9;
    transform: scale(1.1);
}

@keyframes countUp {
    0% {
        transform: translateY(20px);
        opacity: 0;
    }
    100% {
        transform: translateY(0);
        opacity: 1;
    }
}

.number {
    animation: countUp 0.5s ease-out forwards;
}

.details-btn {
    background-color: #3498db;
    color: #ffffff !important;
    border: none;
    padding: 8px 24px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
}

.details-btn:hover {
    background-color: #2980b9;
    text-decoration: none;
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.details-btn:visited {
    color: #ffffff !important;
}

.details-btn:active {
    color: #ffffff !important;
}

.details-btn:focus {
    color: #ffffff !important;
    outline: none;
}

.events-container {
    display: flex;
    gap: 30px;
    width: 100%;
    max-width: 1400px;
    margin-top: 30px;
    justify-content: space-between;
    padding: 0;
}

.events-section {
    background: white;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    flex: 0 0 calc(50% - 15px);
    max-width: calc(50% - 15px);
    overflow-x: auto;
}

/* Esemény szűrő stílusok */
.events-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.events-header h2 {
    margin: 0;
}

.event-filter {
    display: flex;
    align-items: center;
}

.event-filter label {
    margin-right: 10px;
    font-weight: 500;
    color: #37474f;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #f8f9fa;
    color: #37474f;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 120px;
}

.filter-select:hover {
    border-color: #3498db;
}

.filter-select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

/* Esemény típusok stílusai */
.maintenance-event {
    background-color: rgba(52, 152, 219, 0.05);
}

.project-event {
    background-color: rgba(46, 204, 113, 0.05);
}

.work-event {
    background-color: rgba(155, 89, 182, 0.05);
}

.events-section:first-child {
    margin-right: 0;
}

.events-section:last-child {
    margin-left: 0;
}

.events-section h2 {
    color: #37474f;
    font-size: 18px;
    margin-bottom: 20px;
    font-weight: normal;
}

.events-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.events-table th {
    background-color: #37474f;
    color: white;
    text-align: left;
    padding: 12px 20px;
    font-weight: normal;
    white-space: nowrap;
}

.events-table td {
    padding: 12px 20px;
    border-bottom: 1px solid #edf2f7;
    color: #37474f;
    white-space: nowrap;
}

.welcome-alert {
    background-color: #3498db;
    color: white;
    padding: 15px 30px;
    margin: 20px auto;
    border-radius: 4px;
    max-width: 1400px;
    text-align: center;
    animation: slideDown 0.5s ease-out, fadeOut 0.5s ease-out 3s forwards;
    position: relative;
    z-index: 1000;
}

@keyframes slideDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
        display: none;
    }
}

.upcoming-events {
    margin-top: 0;
}

.events-table tr:hover {
    background-color: #f8f9fa;
    transition: background-color 0.3s ease;
}

@media (max-width: 768px) {
    .events-container {
        flex-direction: column;
        gap: 20px;
        padding: 0 20px;
    }
    
    .events-section {
        flex: 0 0 100%;
        max-width: 100%;
    }
}

/* Info Button Styles */
.info-button-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
}

.info-btn {
    background-color: #3498db;
    color: #ffffff !important;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.info-btn:hover {
    background-color: #2980b9;
    transform: scale(1.05);
    color: #ffffff !important;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1001;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 30px;
    border-radius: 10px;
    width: 70%;
    max-width: 800px;
    position: relative;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-100px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.close {
    position: absolute;
    right: 20px;
    top: 10px;
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
}

.close:hover {
    color: #555;
}

.tutorial-options {
    display: flex;
    justify-content: space-around;
    margin-top: 30px;
    gap: 20px;
}

.tutorial-box {
    flex: 1;
    padding: 20px;
    border: 2px solid #3498db;
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.tutorial-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    background-color: #f8f9fa;
}

.tutorial-box i {
    font-size: 40px;
    color: #3498db;
    margin-bottom: 15px;
}

.tutorial-box h3 {
    margin: 10px 0;
    color: #2c3e50;
}

.tutorial-box p {
    color: #666;
    font-size: 14px;
}

.tutorial-content {
    max-width: 900px;
    margin: 5% auto;
}

.tutorial-header {
    margin-bottom: 30px;
}

.progress-bar {
    width: 100%;
    height: 4px;
    background-color: #eee;
    margin-top: 20px;
    border-radius: 2px;
}

.progress {
    width: 33.33%;
    height: 100%;
    background-color: #3498db;
    border-radius: 2px;
    transition: width 0.3s ease;
}

.tutorial-steps {
    margin: 30px 0;
    min-height: 300px;
}

.tutorial-step {
    animation: fadeIn 0.5s ease;
}

.tutorial-step h3 {
    color: #2c3e50;
    margin-bottom: 20px;
}

.tutorial-step ul {
    padding-left: 20px;
}

.tutorial-step li {
    margin: 10px 0;
    color: #555;
}

.tutorial-navigation {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
}

.nav-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    background-color: #3498db;
    color: #ffffff !important;
    cursor: pointer;
    transition: all 0.3s ease;
}

.nav-btn:disabled {
    background-color: #ccc;
    cursor: not-allowed;
    color: #ffffff !important;
}

.nav-btn:hover:not(:disabled) {
    background-color: #2980b9;
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* A meglévő stílusokhoz adjuk hozzá */
.final-message {
    text-align: center;
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 10px;
    margin: 20px 0;
}

.final-message p {
    font-size: 16px;
    color: #2c3e50;
    margin: 15px 0;
    line-height: 1.6;
}

.final-message ul {
    text-align: left;
    max-width: 500px;
    margin: 20px auto;
    list-style-type: none;
}

.final-message li {
    padding: 10px 0;
    color: #34495e;
    position: relative;
    padding-left: 30px;
}

.final-message li:before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #27ae60;
    font-weight: bold;
}

.support-info {
    margin-top: 30px;
    padding: 20px;
    background-color: #3498db;
    color: #ffffff;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.support-info i {
    font-size: 24px;
}

.support-info p {
    color: #ffffff;
    margin: 0;
}

/* Az utolsó lépésnél nagyobb ikon */
[data-step="7"] .tutorial-image i {
    font-size: 80px;
    color: #27ae60;
}

/* Lapozó stílusok */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 20px;
    gap: 5px;
}

.page-link {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    color: #3498db;
    text-decoration: none;
    transition: all 0.3s ease;
}

.page-link:hover {
    background-color: #f8f9fa;
    border-color: #3498db;
    color: #2980b9;
}

.page-link.active {
    background-color: #3498db;
    border-color: #3498db;
    color: white;
}

/* Mobil reszponzív lapozó */
@media (max-width: 768px) {
    .pagination {
        flex-wrap: wrap;
    }
    
    .page-link {
        padding: 6px 10px;
        font-size: 14px;
    }
}

/* Esemény típusok stílusai */
.maintenance-event {
    background-color: rgba(52, 152, 219, 0.05);
}

.project-event {
    background-color: rgba(46, 204, 113, 0.05);
}

.work-event {
    background-color: rgba(155, 89, 182, 0.05);
}

.events-table tr.work-event:hover,
.events-table tr.maintenance-event:hover,
.events-table tr.project-event:hover {
    background-color: rgba(0, 0, 0, 0.02);
    transition: background-color 0.3s ease;
}
</style>

<script>
// A script elejére adjuk hozzá az összes szükséges fordítást
const translations = {
    'next': '<?php echo translate("Következő"); ?>',
    'finish': '<?php echo translate("Befejezés"); ?>',
    'prev': '<?php echo translate("Előző"); ?>'
};

// Számláló animáció és egyéb funkciók inicializálása
document.addEventListener('DOMContentLoaded', () => {
    // Számláló elemek kiválasztása
    const counters = document.querySelectorAll('.counter-animation');
    
    // Számláló animáció
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'));
        const duration = 1000;
        const startTime = performance.now();
        
        function updateCounter(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const currentValue = Math.floor(easeOutQuart * target);
            
            // Számok formázása
            counter.textContent = formatNumber(currentValue);
            
            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = formatNumber(target);
            }
        }
        
        requestAnimationFrame(updateCounter);
    });

    // Kártyák kiemelése nagy számoknál
    counters.forEach(counter => {
        const value = parseInt(counter.getAttribute('data-target'));
        const card = counter.closest('.card');
        
        if (value >= 1000000000) {
            card.classList.add('highlight');
        } else if (value >= 1000000) {
            card.classList.add('highlight');
            card.querySelector('h2').innerHTML += ' 🎉';
        } else if (value >= 100000) {
            card.classList.add('highlight');
            card.querySelector('h2').innerHTML += ' 👏';
        }
    });

    // Esemény szűrő kezelése
    const eventTypeFilter = document.getElementById('eventTypeFilter');
    if (eventTypeFilter) {
        eventTypeFilter.addEventListener('change', function() {
            const selectedType = this.value;
            const eventRows = this.closest('.events-section').querySelectorAll('.event-row');
            
            eventRows.forEach(row => {
                if (selectedType === 'all') {
                    row.style.display = '';
                } else if (selectedType === 'maintenance' && row.classList.contains('maintenance-event')) {
                    row.style.display = '';
                } else if (selectedType === 'project' && row.classList.contains('project-event')) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Modal kezelése
    const modal = document.getElementById('tutorialModal');
    const btn = document.getElementById('infoButton');
    const span = document.getElementsByClassName('close')[0];
    const writtenTutorial = document.getElementById('written-tutorial');
    const videoTutorial = document.getElementById('video-tutorial');

    if (btn) {
        btn.addEventListener('click', function() {
            modal.style.display = "block";
        });
    }

    if (span) {
        span.addEventListener('click', function() {
            modal.style.display = "none";
        });
    }

    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    });

    if (writtenTutorial) {
        writtenTutorial.addEventListener('click', function() {
            modal.style.display = "none";
            document.getElementById('writtenTutorialModal').style.display = "block";
            updateTutorialStep();
        });
    }

    if (videoTutorial) {
        videoTutorial.addEventListener('click', function() {
            alert('Videós tutorial kiválasztva');
            // window.location.href = 'video-tutorial.php';
        });
    }

    // Írásos tutorial kezelése
    const writtenTutorialModal = document.getElementById('writtenTutorialModal');
    const writtenTutorialClose = document.getElementById('writtenTutorialClose');
    const prevStepBtn = document.getElementById('prevStep');
    const nextStepBtn = document.getElementById('nextStep');
    const progressBar = document.getElementById('tutorialProgress');
    const steps = document.querySelectorAll('.tutorial-step');
    let currentStep = 1;

    if (writtenTutorialClose) {
        writtenTutorialClose.addEventListener('click', function() {
            writtenTutorialModal.style.display = "none";
            resetTutorial();
        });
    }

    // Tutorial lépések kezelése
    function updateTutorialStep() {
        steps.forEach(step => step.style.display = 'none');
        document.querySelector(`[data-step="${currentStep}"]`).style.display = 'block';
        
        progressBar.style.width = `${(currentStep / 7) * 100}%`;
        
        prevStepBtn.disabled = currentStep === 1;
        
        if (currentStep === 7) {
            nextStepBtn.textContent = translations.finish;
            nextStepBtn.classList.add('finish-btn');
        } else {
            nextStepBtn.textContent = translations.next;
            nextStepBtn.classList.remove('finish-btn');
        }
    }

    function resetTutorial() {
        currentStep = 1;
        updateTutorialStep();
    }

    prevStepBtn.addEventListener('click', () => {
        if (currentStep > 1) {
            currentStep--;
            updateTutorialStep();
        }
    });

    nextStepBtn.addEventListener('click', () => {
        if (currentStep < steps.length) {
            currentStep++;
            updateTutorialStep();
        } else {
            writtenTutorialModal.style.display = "none";
            resetTutorial();
        }
    });

    // Kívülre kattintás kezelése az új modalnál
    window.addEventListener('click', function(event) {
        if (event.target === writtenTutorialModal) {
            writtenTutorialModal.style.display = "none";
            resetTutorial();
        }
    });

    // Esemény szűrők kezelése
    function handleEventFilter(filterId, eventClass) {
        const eventTypeFilter = document.getElementById(filterId);
        if (eventTypeFilter) {
            eventTypeFilter.addEventListener('change', function() {
                const selectedType = this.value;
                const eventRows = this.closest('.events-section').querySelectorAll('.event-row');
                
                eventRows.forEach(row => {
                    if (selectedType === 'all') {
                        row.style.display = '';
                    } else if (selectedType === 'maintenance' && row.classList.contains('maintenance-event')) {
                        row.style.display = '';
                    } else if ((selectedType === 'work' || selectedType === 'project') && row.classList.contains(eventClass)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    }

    // Legutóbbi események szűrő
    handleEventFilter('eventTypeFilter', 'project-event');
    
    // Közelgő események szűrő
    handleEventFilter('upcomingEventTypeFilter', 'work-event');
});

// Segédfüggvény a számok formázásához
function formatNumber(num) {
    if (num >= 1000000000) {
        return (num / 1000000000).toFixed(1) + 'B';
    }
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num;
}

// Pagination kezelése AJAX-szal
document.addEventListener('DOMContentLoaded', function() {
    const paginationLinks = document.querySelectorAll('.pagination .page-link');
    
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = this.getAttribute('data-page');
            loadPage(page);
        });
    });
    
    function loadPage(page) {
        // URL paraméterek frissítése anélkül, hogy újratöltjük az oldalt
        const url = new URL(window.location.href);
        url.searchParams.set('page', page);
        window.history.pushState({}, '', url);
        
        // AJAX kérés a tartalom betöltéséhez
        fetch(`index.php?page=${page}`)
            .then(response => response.text())
            .then(html => {
                // Csak a táblázat tartalmát frissítjük
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Táblázat tartalom frissítése
                const eventsTable = document.querySelector('.events-section:first-child .events-table tbody');
                const newTableBody = doc.querySelector('.events-table tbody');
                if (eventsTable && newTableBody) {
                    eventsTable.innerHTML = newTableBody.innerHTML;
                }
                
                // Lapozás frissítése
                const pagination = document.querySelector('.pagination');
                const newPagination = doc.querySelector('.pagination');
                if (pagination && newPagination) {
                    pagination.innerHTML = newPagination.innerHTML;
                    
                    // Új eseménykezelők hozzáadása
                    const newPaginationLinks = pagination.querySelectorAll('.page-link');
                    newPaginationLinks.forEach(link => {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            const newPage = this.getAttribute('data-page');
                            loadPage(newPage);
                        });
                    });
                }
                
                // Aktív oldal jelölése
                const allPageLinks = document.querySelectorAll('.page-link');
                allPageLinks.forEach(link => {
                    const linkPage = link.getAttribute('data-page');
                    if (linkPage === page) {
                        link.classList.add('active');
                    } else {
                        link.classList.remove('active');
                    }
                });
            })
            .catch(error => {
                console.error('Hiba történt az adatok betöltése közben:', error);
            });
    }
});
</script>

</body>
</html> 