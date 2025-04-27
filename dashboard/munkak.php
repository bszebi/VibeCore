<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Kapcsolódási hiba: " . mysqli_connect_error());
}

// Handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'get_job' && isset($_GET['id'])) {
    $job_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    $job_sql = "SELECT w.*, 
        GROUP_CONCAT(DISTINCT utw.user_id) as selected_users,
        GROUP_CONCAT(DISTINCT wts.stuffs_id) as selected_stuffs,
        GROUP_CONCAT(DISTINCT CONCAT(u.lastname, ' ', u.firstname, ' (', COALESCE(st_user.name, 'Elérhető'), ')') SEPARATOR ', ') as worker_names,
        d.name as deliver_name, d.id as deliver_id,
        p.name as project_name, p.id as project_id,
        c.company_name,
        co.name as country_name,
        cou.name as county_name,
        ci.name as city_name,
        GROUP_CONCAT(
            CONCAT(st.name, ' -', 
                COALESCE(ss.name, ''), ' -',
                COALESCE(sb.name, ''), ' -',
                COALESCE(sm.name, ''),
                ' (QR: ', s.qr_code, ')'
            ) SEPARATOR '|||'
        ) as equipment_list
        FROM work w
        LEFT JOIN user_to_work utw ON w.id = utw.work_id
        LEFT JOIN user u ON utw.user_id = u.id
        LEFT JOIN status st_user ON u.current_status_id = st_user.id
        LEFT JOIN deliver d ON w.deliver_id = d.id
        LEFT JOIN project p ON w.project_id = p.id
        LEFT JOIN company c ON w.company_id = c.id
        LEFT JOIN countries co ON p.country_id = co.id
        LEFT JOIN counties cou ON p.county_id = cou.id
        LEFT JOIN cities ci ON p.city_id = ci.id
        LEFT JOIN work_to_stuffs wts ON w.id = wts.work_id
        LEFT JOIN stuffs s ON wts.stuffs_id = s.id
        LEFT JOIN stuff_type st ON s.type_id = st.id
        LEFT JOIN stuff_secondtype ss ON s.secondtype_id = ss.id
        LEFT JOIN stuff_brand sb ON s.brand_id = sb.id
        LEFT JOIN stuff_model sm ON s.model_id = sm.id
        WHERE w.id = '$job_id' AND w.company_id = " . $_SESSION['company_id'] . "
        GROUP BY w.id";
    
    $job_result = mysqli_query($conn, $job_sql);
    
    if ($job = mysqli_fetch_assoc($job_result)) {
        // Convert dates to the expected format
        $job['work_start_date'] = date('Y-m-d\TH:i', strtotime($job['work_start_date']));
        $job['work_end_date'] = date('Y-m-d\TH:i', strtotime($job['work_end_date']));
        
        header('Content-Type: application/json');
        echo json_encode($job);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Job not found']);
    }
    exit;
}

// Handle POST requests for delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['work_id'])) {
    header('Content-Type: application/json');
    
    $work_id = mysqli_real_escape_string($conn, $_POST['work_id']);
    
    // First, check if the work exists and belongs to the company
    $check_sql = "SELECT id FROM work WHERE id = '$work_id' AND company_id = " . $_SESSION['company_id'];
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) === 0) {
        echo json_encode(['success' => false, 'error' => 'Munka nem található vagy nincs jogosultsága a törléshez.']);
        exit;
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Delete related records from user_to_work
        $delete_user_work = "DELETE FROM user_to_work WHERE work_id = '$work_id'";
        mysqli_query($conn, $delete_user_work);
        
        // Delete related records from work_to_stuffs
        $delete_work_stuffs = "DELETE FROM work_to_stuffs WHERE work_id = '$work_id'";
        mysqli_query($conn, $delete_work_stuffs);
        
        // Finally, delete the work itself
        $delete_work = "DELETE FROM work WHERE id = '$work_id'";
        mysqli_query($conn, $delete_work);
        
        // Commit transaction
        mysqli_commit($conn);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'error' => 'Hiba történt a törlés során: ' . $e->getMessage()]);
    }
    exit;
}

// Jogosultság ellenőrzése
checkPageAccess();

// Frissítsük a felhasználók státuszát az aktív munkákhoz
$update_user_status = "
UPDATE user u
INNER JOIN user_to_work utw ON u.id = utw.user_id
INNER JOIN work w ON utw.work_id = w.id
SET u.current_status_id = CASE 
    WHEN NOW() BETWEEN w.work_start_date AND w.work_end_date THEN 
        (SELECT id FROM status WHERE name = 'Munkában')
    ELSE 
        (SELECT id FROM status WHERE name = 'Elérhető')
    END
WHERE w.company_id = " . $_SESSION['company_id'];

mysqli_query($conn, $update_user_status);

// SQL lekérdezés a work táblára
$jobs_sql = "SELECT w.*, 
             COUNT(DISTINCT utw.user_id) as user_count,
             COUNT(DISTINCT wts.stuffs_id) as stuff_count,
             GROUP_CONCAT(DISTINCT CONCAT(u.lastname, ' ', u.firstname, ' (', COALESCE(st_user.name, 'Elérhető'), ')') SEPARATOR ', ') as worker_names,
             d.name as deliver_name, p.name as project_name,
             p.picture as project_picture,
             pt.name as project_type,
             pt.id as project_type_id,
             c.company_name,
             co.name as country_name,
             cou.name as county_name,
             ci.name as city_name,
             CASE 
                WHEN w.work_end_date < NOW() THEN 'completed'
                WHEN w.work_start_date <= NOW() AND w.work_end_date >= NOW() THEN 'in_progress'
                ELSE 'upcoming'
             END as job_status,
             GROUP_CONCAT(
                 CONCAT(st.name, ' -', 
                       COALESCE(ss.name, ''), ' -',
                       COALESCE(sb.name, ''), ' -',
                       COALESCE(sm.name, ''),
                       ' (QR: ', s.qr_code, ')',
                       ' [', 
                       CASE 
                           WHEN DATE(w.work_start_date) = CURDATE() THEN 'Használatban'
                           ELSE COALESCE((SELECT name FROM stuff_status WHERE id = s.stuff_status_id), 'Raktáron')
                       END,
                       ']'
                 ) SEPARATOR '|||'
             ) as equipment_list
             FROM work w
             LEFT JOIN user_to_work utw ON w.id = utw.work_id
             LEFT JOIN user u ON utw.user_id = u.id
             LEFT JOIN status st_user ON u.current_status_id = st_user.id
             LEFT JOIN deliver d ON w.deliver_id = d.id
             LEFT JOIN project p ON w.project_id = p.id
             LEFT JOIN project_type pt ON p.type_id = pt.id
             LEFT JOIN company c ON w.company_id = c.id
             LEFT JOIN countries co ON p.country_id = co.id
             LEFT JOIN counties cou ON p.county_id = cou.id
             LEFT JOIN cities ci ON p.city_id = ci.id
             LEFT JOIN work_to_stuffs wts ON w.id = wts.work_id
             LEFT JOIN stuffs s ON wts.stuffs_id = s.id
             LEFT JOIN stuff_type st ON s.type_id = st.id
             LEFT JOIN stuff_secondtype ss ON s.secondtype_id = ss.id
             LEFT JOIN stuff_brand sb ON s.brand_id = sb.id
             LEFT JOIN stuff_model sm ON s.model_id = sm.id
             LEFT JOIN stuff_status ss2 ON s.stuff_status_id = ss2.id
             WHERE w.company_id = " . $_SESSION['company_id'] . "
             AND (
                 w.work_end_date >= NOW() OR 
                 (w.work_end_date < NOW() AND w.work_end_date >= DATE_SUB(NOW(), INTERVAL 7 DAY))
             )
             GROUP BY w.id
             ORDER BY 
                CASE job_status
                    WHEN 'in_progress' THEN 1
                    WHEN 'upcoming' THEN 2
                    WHEN 'completed' THEN 3
                END,
                CASE 
                    WHEN job_status = 'completed' THEN w.work_end_date
                    ELSE w.work_start_date
                END ASC";

$jobs_result = mysqli_query($conn, $jobs_sql);

// Add type colors array before the jobs grid
$typeColors = [
    'Fesztivál' => '#3498db',      // kék
    'Konferancia' => '#c2ae1b',    // piszkos sárga
    'Rendezvény' => '#9b59b6',     // lila
    'Előadás' => '#34495e',        // sötétszürke
    'Kiállitás' => '#e67e22',      // narancssárga
    'Jótékonysági' => '#fa93ce',   // halvány rózsaszín
    'Ünnepség' => '#16a085',       // türkiz
    'Egyéb' => '#95a5a6'           // szürke
];
?>

<!-- jQuery betöltése a Select2 előtt -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 CSS és JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
.content-container {
    margin-top: 60px; /* Ez biztosítja, hogy a navbar alatt kezdődjön */
}

.jobs-header {
    position: fixed;
    top: 60px; /* A navbar magassága */
    left: 0;
    right: 0;
    z-index: 100;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 0;
    padding: 1rem 2rem;
}

.jobs-header h1 {
    margin: 0;
    color: #2c3e50;
    font-size: 1.8rem;
}

.btn-new-job {
    background: #3498db;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: background-color 0.3s;
}

.btn-new-job:hover {
    background: #2980b9;
}

.jobs-grid {
    margin-top: 70px;
    display: grid;
    grid-template-columns: repeat(3, 350px);
    gap: 2rem;
    padding: 2rem;
    justify-content: center;
    background-color: #f8f9fa;
}

.job-card {
    width: 350px;
    min-height: 200px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
    position: relative; /* Added for status bar positioning */
}

.job-card.completed {
    opacity: 0.8;
    filter: blur(0.5px);
    background: rgba(255, 255, 255, 0.95);
}

.job-card.in-progress {
    border: 2px solid #3498db;
    box-shadow: 0 0 10px rgba(52, 152, 219, 0.3);
}

.job-card.completed:hover,
.job-card.in-progress:hover {
    transform: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.job-card.in-progress:hover {
    box-shadow: 0 0 15px rgba(52, 152, 219, 0.4);
}

/* Add new styles for completed jobs */
.job-card.completed::before {
    content: '<?php echo translate("Befejeződött"); ?>';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    background: rgba(72, 187, 120, 0.9);
    color: white;
    text-align: center;
    padding: 8px;
    font-weight: 500;
    z-index: 1;
}

.job-card.completed .job-actions button {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.job-card.completed .job-actions button:hover {
    transform: none;
    box-shadow: none;
}

.job-card.in-progress::before {
    content: '<?php echo translate("Folyamatban"); ?>';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    background: rgba(52, 152, 219, 0.9);
    color: white;
    text-align: center;
    padding: 8px;
    font-weight: 500;
    z-index: 1;
}

.job-card.in-progress .job-actions button.btn-edit,
.job-card.in-progress .job-actions button.btn-delete {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.job-card.in-progress .job-actions button.btn-edit:hover,
.job-card.in-progress .job-actions button.btn-delete:hover {
    transform: none;
    box-shadow: none;
}

.job-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.job-header {
    padding: 1rem;
    border-bottom: 1px solid #edf2f7;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.job-title {
    margin: 0;
    font-size: 1.25rem;
    color: #2c3e50;
    font-weight: 600;
}

.job-body {
    padding: 1rem;
    flex: 1;
}

.job-info {
    display: grid;
    gap: 0.75rem;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.info-item i {
    color: #718096;
    margin-top: 0.25rem;
}

.info-item span {
    flex: 1;
    color: #4a5568;
    line-height: 1.4;
}

.equipment-section {
    flex-direction: column;
}

.equipment-list {
    width: 100%;
    display: grid;
    gap: 0.5rem;
    max-height: 150px;
    overflow-y: auto;
}

.equipment-item {
    background: white;
    padding: 0.5rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    font-size: 0.85rem;
    color: #4a5568;
}

.job-footer {
    margin-top: auto;
    padding: 0.75rem;
    background: #f8fafc;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.job-stats {
    display: flex;
    gap: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #4a5568;
    font-size: 0.85rem;
}

.job-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

@media (max-width: 1200px) {
    .jobs-grid {
        grid-template-columns: repeat(2, 350px);
    }
}

@media (max-width: 800px) {
    .jobs-grid {
        grid-template-columns: 350px;
    }
}

@media (max-width: 400px) {
    .jobs-grid {
        grid-template-columns: 1fr;
    }
    
    .job-card {
        width: 100%;
    }
}

.btn-info {
    background: #ebf8ff;
    color: #3498db;
}

.btn-info:hover {
    background: #bee3f8;
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

/* Modal overlay styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    z-index: 10000;
    overflow-y: auto;
    padding: 20px;
}

.modal-content {
    background-color: #fff;
    margin: 20px auto;
    padding: 25px;
    border-radius: 12px;
    width: 90%;
    max-width: 800px;
    position: relative;
    z-index: 10001;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #edf2f7;
}

.modal-header h3 {
    margin: 0;
    color: #2d3748;
    font-size: 1.5rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #718096;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.modal-close:hover {
    color: #2d3748;
    background-color: #f7fafc;
}

.modal-body {
    padding: 20px 0;
}

/* Form controls within modal */
.modal .form-group {
    margin-bottom: 20px;
}

.modal .form-label {
    font-weight: 500;
    color: #4a5568;
    margin-bottom: 8px;
    display: block;
}

.modal .form-control {
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    padding: 10px;
    width: 100%;
    transition: all 0.2s ease;
}

.modal .form-control:focus {
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

/* Button styles */
.modal .btn {
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.modal .btn-primary {
    background-color: #4299e1;
    border: none;
    color: white;
}

.modal .btn-primary:hover {
    background-color: #3182ce;
}

.modal .btn-secondary {
    background-color: #edf2f7;
    border: none;
    color: #4a5568;
}

.modal .btn-secondary:hover {
    background-color: #e2e8f0;
}

.info-grid {
    display: grid;
    gap: 1rem;
}

.info-row {
    display: flex;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 0.5rem;
    background: #f8fafc;
}

.info-label {
    font-weight: 500;
    color: #4a5568;
    min-width: 120px;
}

.info-value {
    color: #2d3748;
}

.equipment-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.equipment-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: #f1f5f9;
    border-radius: 0.375rem;
    font-size: 0.9rem;
}

.equipment-item i {
    color: #3498db;
}

.confirm-dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1100;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.confirm-content {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    text-align: center;
    max-width: 400px;
    width: 90%;
    transform: scale(0.8);
    transition: transform 0.3s ease;
}

.confirm-icon {
    color: #e53e3e;
    font-size: 3rem;
    margin-bottom: 1rem;
}

.confirm-content h2 {
    color: #2d3748;
    margin-bottom: 1rem;
}

.confirm-content p {
    color: #4a5568;
    margin-bottom: 2rem;
}

.confirm-buttons {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.confirm-buttons button {
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-cancel {
    background: #e2e8f0;
    color: #4a5568;
}

.btn-cancel:hover {
    background: #cbd5e0;
}

.btn-confirm {
    background: #e53e3e;
    color: white;
}

.btn-confirm:hover {
    background: #c53030;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #2d3748;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    font-size: 0.9rem;
    transition: border-color 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.date-inputs {
    display: flex;
    gap: 1rem;
}

.date-input-group {
    flex: 1;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
    padding: 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    position: sticky;
    bottom: 0;
    z-index: 10;
}

.form-actions button {
    min-width: 120px;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.form-actions .btn-cancel {
    background: #e2e8f0;
    color: #4a5568;
    border: none;
}

.form-actions .btn-cancel:hover {
    background: #cbd5e0;
    transform: translateY(-1px);
}

.form-actions .btn-save {
    background: #3498db;
    color: white;
    border: none;
}

.form-actions .btn-save:hover {
    background: #2980b9;
    transform: translateY(-1px);
}

/* Accordion stílusok */
.accordion {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: background-color 0.2s;
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
    padding: 1rem;
    display: none;
    background: white;
}

.accordion-item.active .accordion-content {
    display: block;
}

.readonly-value {
    padding: 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    color: #4a5568;
}

/* Form action gombok */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
    padding: 1rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}

.form-actions button {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel {
    background: #e2e8f0;
    color: #4a5568;
    border: none;
}

.btn-cancel:hover {
    background: #cbd5e0;
}

.btn-save {
    background: #3498db;
    color: white;
    border: none;
}

.btn-save:hover {
    background: #2980b9;
}

/* Adjuk hozzá a notification stílusokat */
.notification {
    position: fixed;
    top: 80px; /* header + kis térköz */
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transform: translateX(120%);
    transition: transform 0.3s ease;
    z-index: 1000;
}

.notification.success {
    border-left: 4px solid #2ecc71;
}

.notification.error {
    border-left: 4px solid #e74c3c;
}

.notification.show {
    transform: translateX(0);
}

.notification.hide {
    transform: translateX(120%);
}

.notification i {
    font-size: 1.25rem;
}

.notification.success i {
    color: #2ecc71;
}

.notification.error i {
    color: #e74c3c;
}

.modal-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    border-radius: 0 0 0.75rem 0.75rem;
}

.btn {
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    min-width: 120px;
}

.btn-secondary {
    background: #f3f4f6;
    color: #4b5563;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-danger:hover {
    background: #b91c1c;
}

.job-details {
    margin: 20px 0;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 4px;
}

.job-details p {
    margin: 8px 0;
}

#delete-equipment-list {
    margin: 0;
    padding-left: 20px;
}

#delete-equipment-list li {
    margin: 5px 0;
}

.job-card {
    transition: all 0.3s ease;
}

.no-jobs {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
    font-size: 1.1rem;
}

/* Információs modal tartalom igazítása */
#jobInfoModal .info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    padding: 1rem;
}

#jobInfoModal .info-row {
    background: #f8fafc;
    padding: 1.25rem;
    border-radius: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

#jobInfoModal .dates-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

#jobInfoModal .date-column {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

#jobInfoModal .info-label {
    font-weight: 600;
    color: #4a5568;
    font-size: 1.1rem;
}

#jobInfoModal .info-value {
    color: #2d3748;
    font-size: 1.05rem;
}

#jobInfoModal .equipment-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 0.75rem;
}

#jobInfoModal .equipment-item {
    background: #fff;
    border: 1px solid #e2e8f0;
    padding: 0.75rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

#jobInfoModal .equipment-item i {
    color: #3498db;
    font-size: 1.1rem;
}

/* Szerkesztés modal tartalom igazítása */
#editJobModal .modal-content {
    max-width: 800px;
}

/* Törlés modal tartalom igazítása */
#deleteConfirmModal .modal-content {
    max-width: 600px;
}

/* Delete confirmation modal styles */
.delete-confirmation {
    padding: 1.5rem;
}

.delete-warning {
    text-align: center;
    margin-bottom: 2rem;
}

.delete-warning i {
    font-size: 3rem;
    color: #ef4444;
    margin-bottom: 1rem;
}

.delete-warning h4 {
    color: #1f2937;
    font-size: 1.25rem;
    margin: 0.5rem 0;
}

.warning-text {
    color: #6b7280;
    margin: 0.5rem 0;
}

.job-details {
    background: #f9fafb;
    border-radius: 0.75rem;
    padding: 1.5rem;
    margin: 1rem 0;
}

.detail-row {
    display: flex;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    flex: 0 0 35%;
    color: #4b5563;
    font-weight: 500;
}

.detail-value {
    flex: 0 0 65%;
    color: #1f2937;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    border-radius: 0 0 0.75rem 0.75rem;
}

.btn {
    padding: 0.75rem 2rem;
    border-radius: 0.5rem;
    font-weight: 500;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-secondary {
    background: #f3f4f6;
    color: #4b5563;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.date-range {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.9rem;
    color: #4a5568;
    margin-left: 0.5rem;
}

.date-range div {
    display: flex;
    align-items: center;
    gap: 0.5rem;
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

.project-type {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.875rem;
    color: white;
    font-weight: 500;
    text-shadow: 0 1px 1px rgba(0,0,0,0.1);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-top: 0.5rem;
}

.project-type:hover {
    filter: brightness(1.1);
    transition: filter 0.2s ease;
}

.filter-container {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.filter-header {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
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

/* Select2 testreszabása */
.select2-container {
    width: 100% !important;
}

.select2-container--default .select2-selection--multiple {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.5rem;
    min-height: 100px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #3498db;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 5px 10px;
    margin: 3px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: white;
    margin-right: 5px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #ff4444;
    background: none;
}

.select2-dropdown {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #3498db;
}

.select2-container--default .select2-search--inline .select2-search__field {
    margin-top: 7px;
}

.stuff-category {
    font-weight: bold;
    color: #2c3e50;
    padding: 8px;
    background: #f8fafc;
}

.stuff-option {
    display: flex;
    align-items: center;
    padding: 8px;
}

.stuff-option i {
    margin-right: 8px;
    color: #3498db;
}

.stuff-details {
    font-size: 0.9em;
    color: #666;
    margin-left: 5px;
}

/* Eszköz szűrők és kiválasztás stílusai */
.stuff-filters {
    margin-bottom: 1rem;
    background: #f8fafc;
    padding: 1rem;
    border-radius: 0.5rem;
}

.filter-label {
    font-weight: 500;
    color: #4a5568;
    margin-bottom: 0.5rem;
}

.filter-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.stuff-filter-btn {
    background: white;
    border: 1px solid #e2e8f0;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    color: #4a5568;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stuff-filter-btn:hover {
    background: #f1f5f9;
    transform: translateY(-1px);
}

.stuff-filter-btn.active {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

.stuff-filter-btn.active i {
    color: white;
}

.stuff-selection {
    background: white;
    padding: 1rem;
    border-radius: 0.5rem;
    border: 1px solid #e2e8f0;
}

.selection-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid #e2e8f0;
}

.selected-count {
    color: #4a5568;
    font-size: 0.875rem;
}

.btn-clear-selection {
    background: none;
    border: none;
    color: #ef4444;
    font-size: 0.875rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    transition: all 0.2s;
}

.btn-clear-selection:hover {
    background: #fee2e2;
}

/* Select2 további testreszabások */
.select2-container--default .select2-selection--multiple {
    border-color: #e2e8f0;
    padding: 0.5rem;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background: #3498db;
    color: white;
    border: none;
    padding: 0.25rem 0.75rem;
    margin: 0.25rem;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.select2-container--default .select2-search--inline .select2-search__field {
    margin-top: 0;
    padding: 0.375rem;
}

.select2-container--default .select2-results__option {
    padding: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}

.select2-container--default .select2-results__option:last-child {
    border-bottom: none;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background: #3498db;
}

.countdown {
    margin-top: 8px;
    padding: 5px 10px;
    background-color: #e9f7fe;
    border-radius: 4px;
    color: #3498db;
    font-size: 0.9em;
    display: flex;
    align-items: center;
    gap: 5px;
}

.countdown i {
    color: #3498db;
}

.jobs-section {
    margin-bottom: 2rem;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #2d3748;
    font-size: 1.5rem;
    margin: 2rem 0 1rem;
    padding: 0 2rem;
}

.section-title i {
    color: #3498db;
}

.section-title .subtitle {
    font-size: 0.875rem;
    color: #718096;
    font-weight: normal;
    margin-left: 1rem;
}

.completed-section {
    background-color: #f8fafc;
    padding: 2rem 0;
    margin-top: 3rem;
    border-top: 1px solid #e2e8f0;
}

.completed-section .section-title {
    color: #4a5568;
}

.completed-section .section-title i {
    color: #48bb78;
}

.job-card.completed {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid #e2e8f0;
}

.job-card.completed::before {
    content: '<?php echo translate("Befejeződött"); ?>';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    background: rgba(72, 187, 120, 0.9);
    color: white;
    text-align: center;
    padding: 8px;
    font-weight: 500;
    z-index: 1;
}

.job-card.completed .job-actions button {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.job-card.completed .job-actions button:hover {
    transform: none;
    box-shadow: none;
}

.job-card.completed .deletion-countdown {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: #fff5f5;
    border-radius: 0.375rem;
    color: #e53e3e;
    font-size: 0.875rem;
}

.job-card.completed .deletion-countdown i {
    color: #e53e3e;
}

.job-card.in-progress {
    border: 2px solid #3498db;
    box-shadow: 0 0 10px rgba(52, 152, 219, 0.3);
}

.job-card.in-progress::before {
    content: '<?php echo translate("Folyamatban"); ?>';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    background: rgba(52, 152, 219, 0.9);
    color: white;
    text-align: center;
    padding: 8px;
    font-weight: 500;
    z-index: 1;
}

.job-card.in-progress .job-actions button.btn-edit,
.job-card.in-progress .job-actions button.btn-delete {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.job-card.in-progress .job-actions button.btn-edit:hover,
.job-card.in-progress .job-actions button.btn-delete:hover {
    transform: none;
    box-shadow: none;
}

.job-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.job-header {
    padding: 1rem;
    border-bottom: 1px solid #edf2f7;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.job-title {
    margin: 0;
    font-size: 1.25rem;
    color: #2c3e50;
    font-weight: 600;
}

.job-body {
    padding: 1rem;
    flex: 1;
}

.job-info {
    display: grid;
    gap: 0.75rem;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.info-item i {
    color: #718096;
    margin-top: 0.25rem;
}

.info-item span {
    flex: 1;
    color: #4a5568;
    line-height: 1.4;
}

.equipment-section {
    flex-direction: column;
}

.equipment-list {
    width: 100%;
    display: grid;
    gap: 0.5rem;
    max-height: 150px;
    overflow-y: auto;
}

.equipment-item {
    background: white;
    padding: 0.5rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    font-size: 0.85rem;
    color: #4a5568;
}

.job-footer {
    margin-top: auto;
    padding: 0.75rem;
    background: #f8fafc;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.job-stats {
    display: flex;
    gap: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #4a5568;
    font-size: 0.85rem;
}

.job-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

@media (max-width: 1200px) {
    .jobs-grid {
        grid-template-columns: repeat(2, 350px);
    }
}

@media (max-width: 800px) {
    .jobs-grid {
        grid-template-columns: 350px;
    }
}

@media (max-width: 400px) {
    .jobs-grid {
        grid-template-columns: 1fr;
    }
    
    .job-card {
        width: 100%;
    }
}

.btn-info {
    background: #ebf8ff;
    color: #3498db;
}

.btn-info:hover {
    background: #bee3f8;
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

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    z-index: 10000;
    overflow-y: auto;
    padding: 20px;
}

.modal-content {
    background-color: #fff;
    margin: 20px auto;
    padding: 25px;
    border-radius: 12px;
    width: 90%;
    max-width: 800px;
    position: relative;
    z-index: 10001;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #edf2f7;
}

.modal-header h3 {
    margin: 0;
    color: #2d3748;
    font-size: 1.5rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #718096;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.modal-close:hover {
    color: #2d3748;
    background-color: #f7fafc;
}

.modal-body {
    padding: 20px 0;
}

/* Form controls within modal */
.modal .form-group {
    margin-bottom: 20px;
}

.modal .form-label {
    font-weight: 500;
    color: #4a5568;
    margin-bottom: 8px;
    display: block;
}

.modal .form-control {
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    padding: 10px;
    width: 100%;
    transition: all 0.2s ease;
}

.modal .form-control:focus {
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

/* Button styles */
.modal .btn {
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.modal .btn-primary {
    background-color: #4299e1;
    border: none;
    color: white;
}

.modal .btn-primary:hover {
    background-color: #3182ce;
}

.modal .btn-secondary {
    background-color: #edf2f7;
    border: none;
    color: #4a5568;
}

.modal .btn-secondary:hover {
    background-color: #e2e8f0;
}

.info-grid {
    display: grid;
    gap: 1rem;
}

.info-row {
    display: flex;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 0.5rem;
    background: #f8fafc;
}

.info-label {
    font-weight: 500;
    color: #4a5568;
    min-width: 120px;
}

.info-value {
    color: #2d3748;
}

.equipment-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.equipment-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: #f1f5f9;
    border-radius: 0.375rem;
    font-size: 0.9rem;
}

.equipment-item i {
    color: #3498db;
}

.confirm-dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1100;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.confirm-content {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    text-align: center;
    max-width: 400px;
    width: 90%;
    transform: scale(0.8);
    transition: transform 0.3s ease;
}

.confirm-icon {
    color: #e53e3e;
    font-size: 3rem;
    margin-bottom: 1rem;
}

.confirm-content h2 {
    color: #2d3748;
    margin-bottom: 1rem;
}

.confirm-content p {
    color: #4a5568;
    margin-bottom: 2rem;
}

.confirm-buttons {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.confirm-buttons button {
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-cancel {
    background: #e2e8f0;
    color: #4a5568;
}

.btn-cancel:hover {
    background: #cbd5e0;
}

.btn-confirm {
    background: #e53e3e;
    color: white;
}

.btn-confirm:hover {
    background: #c53030;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #2d3748;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    font-size: 0.9rem;
    transition: border-color 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.date-inputs {
    display: flex;
    gap: 1rem;
}

.date-input-group {
    flex: 1;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
    padding: 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    position: sticky;
    bottom: 0;
    z-index: 10;
}

.form-actions button {
    min-width: 120px;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.form-actions .btn-cancel {
    background: #e2e8f0;
    color: #4a5568;
    border: none;
}

.form-actions .btn-cancel:hover {
    background: #cbd5e0;
    transform: translateY(-1px);
}

.form-actions .btn-save {
    background: #3498db;
    color: white;
    border: none;
}

.form-actions .btn-save:hover {
    background: #2980b9;
    transform: translateY(-1px);
}

/* Accordion stílusok */
.accordion {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: background-color 0.2s;
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
    padding: 1rem;
    display: none;
    background: white;
}

.accordion-item.active .accordion-content {
    display: block;
}

.readonly-value {
    padding: 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    color: #4a5568;
}

/* Form action gombok */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
    padding: 1rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}

.form-actions button {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel {
    background: #e2e8f0;
    color: #4a5568;
    border: none;
}

.btn-cancel:hover {
    background: #cbd5e0;
}

.btn-save {
    background: #3498db;
    color: white;
    border: none;
}

.btn-save:hover {
    background: #2980b9;
}

/* Adjuk hozzá a notification stílusokat */
.notification {
    position: fixed;
    top: 80px; /* header + kis térköz */
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transform: translateX(120%);
    transition: transform 0.3s ease;
    z-index: 1000;
}

.notification.success {
    border-left: 4px solid #2ecc71;
}

.notification.error {
    border-left: 4px solid #e74c3c;
}

.notification.show {
    transform: translateX(0);
}

.notification.hide {
    transform: translateX(120%);
}

.notification i {
    font-size: 1.25rem;
}

.notification.success i {
    color: #2ecc71;
}

.notification.error i {
    color: #e74c3c;
}

.modal-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    border-radius: 0 0 0.75rem 0.75rem;
}

.btn {
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    min-width: 120px;
}

.btn-secondary {
    background: #f3f4f6;
    color: #4b5563;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-danger:hover {
    background: #b91c1c;
}

.job-details {
    margin: 20px 0;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 4px;
}

.job-details p {
    margin: 8px 0;
}

#delete-equipment-list {
    margin: 0;
    padding-left: 20px;
}

#delete-equipment-list li {
    margin: 5px 0;
}

.job-card {
    transition: all 0.3s ease;
}

.no-jobs {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
    font-size: 1.1rem;
}

/* Információs modal tartalom igazítása */
#jobInfoModal .info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    padding: 1rem;
}

#jobInfoModal .info-row {
    background: #f8fafc;
    padding: 1.25rem;
    border-radius: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

#jobInfoModal .dates-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

#jobInfoModal .date-column {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

#jobInfoModal .info-label {
    font-weight: 600;
    color: #4a5568;
    font-size: 1.1rem;
}

#jobInfoModal .info-value {
    color: #2d3748;
    font-size: 1.05rem;
}

#jobInfoModal .equipment-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 0.75rem;
}

#jobInfoModal .equipment-item {
    background: #fff;
    border: 1px solid #e2e8f0;
    padding: 0.75rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

#jobInfoModal .equipment-item i {
    color: #3498db;
    font-size: 1.1rem;
}

/* Szerkesztés modal tartalom igazítása */
#editJobModal .modal-content {
    max-width: 800px;
}

/* Törlés modal tartalom igazítása */
#deleteConfirmModal .modal-content {
    max-width: 600px;
}

/* Delete confirmation modal styles */
.delete-confirmation {
    padding: 1.5rem;
}

.delete-warning {
    text-align: center;
    margin-bottom: 2rem;
}

.delete-warning i {
    font-size: 3rem;
    color: #ef4444;
    margin-bottom: 1rem;
}

.delete-warning h4 {
    color: #1f2937;
    font-size: 1.25rem;
    margin: 0.5rem 0;
}

.warning-text {
    color: #6b7280;
    margin: 0.5rem 0;
}

.job-details {
    background: #f9fafb;
    border-radius: 0.75rem;
    padding: 1.5rem;
    margin: 1rem 0;
}

.detail-row {
    display: flex;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    flex: 0 0 35%;
    color: #4b5563;
    font-weight: 500;
}

.detail-value {
    flex: 0 0 65%;
    color: #1f2937;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    border-radius: 0 0 0.75rem 0.75rem;
}

.btn {
    padding: 0.75rem 2rem;
    border-radius: 0.5rem;
    font-weight: 500;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-secondary {
    background: #f3f4f6;
    color: #4b5563;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.date-range {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.9rem;
    color: #4a5568;
    margin-left: 0.5rem;
}

.date-range div {
    display: flex;
    align-items: center;
    gap: 0.5rem;
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

.project-type {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.875rem;
    color: white;
    font-weight: 500;
    text-shadow: 0 1px 1px rgba(0,0,0,0.1);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-top: 0.5rem;
}

.project-type:hover {
    filter: brightness(1.1);
    transition: filter 0.2s ease;
}

.filter-container {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.filter-header {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
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

/* Select2 testreszabása */
.select2-container {
    width: 100% !important;
}

.select2-container--default .select2-selection--multiple {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.5rem;
    min-height: 100px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #3498db;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 5px 10px;
    margin: 3px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: white;
    margin-right: 5px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #ff4444;
    background: none;
}

.select2-dropdown {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #3498db;
}

.select2-container--default .select2-search--inline .select2-search__field {
    margin-top: 7px;
}

.stuff-category {
    font-weight: bold;
    color: #2c3e50;
    padding: 8px;
    background: #f8fafc;
}

.stuff-option {
    display: flex;
    align-items: center;
    padding: 8px;
}

.stuff-option i {
    margin-right: 8px;
    color: #3498db;
}

.stuff-details {
    font-size: 0.9em;
    color: #666;
    margin-left: 5px;
}

/* Eszköz szűrők és kiválasztás stílusai */
.stuff-filters {
    margin-bottom: 1rem;
    background: #f8fafc;
    padding: 1rem;
    border-radius: 0.5rem;
}

.filter-label {
    font-weight: 500;
    color: #4a5568;
    margin-bottom: 0.5rem;
}

.filter-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.stuff-filter-btn {
    background: white;
    border: 1px solid #e2e8f0;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    color: #4a5568;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stuff-filter-btn:hover {
    background: #f1f5f9;
    transform: translateY(-1px);
}

.stuff-filter-btn.active {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

.stuff-filter-btn.active i {
    color: white;
}

.stuff-selection {
    background: white;
    padding: 1rem;
    border-radius: 0.5rem;
    border: 1px solid #e2e8f0;
}

.selection-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid #e2e8f0;
}

.selected-count {
    color: #4a5568;
    font-size: 0.875rem;
}

.btn-clear-selection {
    background: none;
    border: none;
    color: #ef4444;
    font-size: 0.875rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    transition: all 0.2s;
}

.btn-clear-selection:hover {
    background: #fee2e2;
}

/* Select2 további testreszabások */
.select2-container--default .select2-selection--multiple {
    border-color: #e2e8f0;
    padding: 0.5rem;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background: #3498db;
    color: white;
    border: none;
    padding: 0.25rem 0.75rem;
    margin: 0.25rem;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.select2-container--default .select2-search--inline .select2-search__field {
    margin-top: 0;
    padding: 0.375rem;
}

.select2-container--default .select2-results__option {
    padding: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}

.select2-container--default .select2-results__option:last-child {
    border-bottom: none;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background: #3498db;
}

.countdown {
    margin-top: 8px;
    padding: 5px 10px;
    background-color: #e9f7fe;
    border-radius: 4px;
    color: #3498db;
    font-size: 0.9em;
    display: flex;
    align-items: center;
    gap: 5px;
}

.countdown i {
    color: #3498db;
}
</style>

<?php require_once '../includes/layout/header.php'; ?>

<div class="content-container">
    <div class="jobs-header">
        <h1><?php echo translate('Munkák'); ?></h1>
        <a href="uj_munka.php" class="btn-new-job">
            <i class="fas fa-plus"></i>
            <?php echo translate('Új munka'); ?>
        </a>
    </div>

    <div class="filter-container">
        <div class="filter-header">
            <button class="filter-btn active" data-type="all">
                <i class="fas fa-th-large"></i>
                <?php echo translate('Összes'); ?>
            </button>
            <?php
            // Project típusok lekérése
            $type_sql = "SELECT DISTINCT pt.name, pt.id 
                        FROM project_type pt 
                        INNER JOIN project p ON p.type_id = pt.id 
                        INNER JOIN work w ON w.project_id = p.id 
                        WHERE w.company_id = " . $_SESSION['company_id'];
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
                echo '<button class="filter-btn" data-type="' . htmlspecialchars($type['id']) . '">';
                echo '<i class="fas ' . $icon . '"></i> ';
                echo htmlspecialchars($type['name']);
                echo '</button>';
            }
            ?>
        </div>
    </div>

    <?php
    // Munkák szétválogatása státusz szerint
    $active_jobs = [];
    $completed_jobs = [];
    
    if (mysqli_num_rows($jobs_result) > 0) {
        while ($job = mysqli_fetch_assoc($jobs_result)) {
            if ($job['job_status'] === 'completed') {
                $completed_jobs[] = $job;
            } else {
                $active_jobs[] = $job;
            }
        }
    }
    ?>

    <!-- Aktív munkák konténere -->
    <div class="jobs-section">
        <h2 class="section-title">
            <i class="fas fa-clock"></i>
            <?php echo translate('Aktív és közelgő munkák'); ?>
        </h2>
        <div class="jobs-grid">
            <?php
            if (!empty($active_jobs)) {
                foreach ($active_jobs as $job) {
                    $now = new DateTime();
                    $start_date = new DateTime($job['work_start_date']);
                    $end_date = new DateTime($job['work_end_date']);
                    $is_in_progress = $job['job_status'] === 'in_progress';
                    $card_class = $is_in_progress ? 'job-card in-progress' : 'job-card';
                    ?>
                    <div class="<?php echo $card_class; ?>" 
                         data-job-id="<?php echo $job['id']; ?>"
                         data-start-date="<?php echo $job['work_start_date']; ?>"
                         data-end-date="<?php echo $job['work_end_date']; ?>">
                        <!-- Itt következik a kártya tartalma (ugyanaz, mint eddig) -->
                        <div class="project-image">
                            <?php if ($job['project_picture']): ?>
                                <img src="../<?php echo htmlspecialchars($job['project_picture']); ?>" alt="<?php echo htmlspecialchars($job['project_name']); ?>">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="job-header">
                            <h2 class="job-title">
                                <?php echo htmlspecialchars($job['project_name'] ?? 'Ismeretlen projekt'); ?>
                            </h2>
                            <span class="project-type" data-type-id="<?php echo htmlspecialchars($job['project_type_id'] ?? ''); ?>" style="background-color: <?php echo $typeColors[$job['project_type'] ?? ''] ?? '#95a5a6'; ?>">
                                <?php echo htmlspecialchars($job['project_type'] ?? 'Nincs típus'); ?>
                            </span>
                        </div>
                        <div class="job-body">
                            <div class="job-info">
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php 
                                        $location = array_filter([
                                            $job['country_name'] ?? 'Ismeretlen ország',
                                            $job['county_name'] ?? 'Ismeretlen megye',
                                            $job['city_name'] ?? 'Ismeretlen város'
                                        ]);
                                        echo htmlspecialchars(implode(', ', $location));
                                    ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-truck"></i>
                                    <span class="deliver-name">
                                        <?php echo htmlspecialchars($job['deliver_name'] ?? 'Nincs megadva'); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-calendar"></i>
                                    <div class="date-range" data-start="<?php echo $job['work_start_date']; ?>" data-end="<?php echo $job['work_end_date']; ?>">
                                        <div><?php echo translate('Kezdés'); ?>: <?php echo date('Y.m.d H:i', strtotime($job['work_start_date'])); ?></div>
                                        <div><?php echo translate('Befejezés'); ?>: <?php echo date('Y.m.d H:i', strtotime($job['work_end_date'])); ?></div>
                                        <?php if ($job['job_status'] === 'upcoming'): ?>
                                            <div class="countdown" data-start="<?php echo $job['work_start_date']; ?>">
                                                <i class="fas fa-hourglass-half"></i>
                                                <?php echo translate('Hátralévő idő számítása...'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="job-footer">
                            <div class="job-stats">
                                <div class="stat-item">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo htmlspecialchars($job['user_count']) . ' ' . translate('fő'); ?></span>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-tools"></i>
                                    <span class="equipment-count"><?php echo htmlspecialchars($job['stuff_count']) . ' ' . translate('eszköz'); ?></span>
                                </div>
                            </div>
                            <div class="job-actions">
                                <button class="btn-action btn-info" onclick="showJobInfo(<?php echo htmlspecialchars(json_encode($job)); ?>)" title="<?php echo translate('Információ'); ?>">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                                <?php if (!$is_in_progress): ?>
                                    <button class="btn-action btn-edit" onclick="editJob(<?php echo $job['id']; ?>)" title="<?php echo translate('Szerkesztés'); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-action btn-delete" onclick="deleteJob(<?php echo $job['id']; ?>)" title="<?php echo translate('Törlés'); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<div class="no-jobs">' . translate('Nincsenek aktív vagy közelgő munkák.') . '</div>';
            }
            ?>
        </div>
    </div>

    <!-- Befejeződött munkák konténere -->
    <?php if (!empty($completed_jobs)): ?>
    <div class="jobs-section completed-section">
        <h2 class="section-title">
            <i class="fas fa-check-circle"></i>
            <?php echo translate('Befejeződött munkák'); ?>
            <span class="subtitle"><?php echo translate('(automatikusan törlődnek 7 nap után)'); ?></span>
        </h2>
        <div class="jobs-grid">
            <?php foreach ($completed_jobs as $job): ?>
                <div class="job-card completed" data-job-id="<?php echo $job['id']; ?>" data-start-date="<?php echo $job['work_start_date']; ?>" data-end-date="<?php echo $job['work_end_date']; ?>">
                    <!-- A kártya tartalma ugyanaz, mint fent -->
                    <div class="project-image">
                        <?php if ($job['project_picture']): ?>
                            <img src="../<?php echo htmlspecialchars($job['project_picture']); ?>" alt="<?php echo htmlspecialchars($job['project_name']); ?>">
                        <?php else: ?>
                            <div class="no-image">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="job-header">
                        <h2 class="job-title">
                            <?php echo htmlspecialchars($job['project_name'] ?? 'Ismeretlen projekt'); ?>
                        </h2>
                        <span class="project-type" data-type-id="<?php echo htmlspecialchars($job['project_type_id'] ?? ''); ?>" style="background-color: <?php echo $typeColors[$job['project_type'] ?? ''] ?? '#95a5a6'; ?>">
                            <?php echo htmlspecialchars($job['project_type'] ?? 'Nincs típus'); ?>
                        </span>
                    </div>
                    <div class="job-body">
                        <div class="job-info">
                            <div class="info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php 
                                    $location = array_filter([
                                        $job['country_name'] ?? 'Ismeretlen ország',
                                        $job['county_name'] ?? 'Ismeretlen megye',
                                        $job['city_name'] ?? 'Ismeretlen város'
                                    ]);
                                    echo htmlspecialchars(implode(', ', $location));
                                ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-truck"></i>
                                <span class="deliver-name">
                                    <?php echo htmlspecialchars($job['deliver_name'] ?? 'Nincs megadva'); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-calendar"></i>
                                <div class="date-range">
                                    <div><?php echo translate('Kezdés'); ?>: <?php echo date('Y.m.d H:i', strtotime($job['work_start_date'])); ?></div>
                                    <div><?php echo translate('Befejezés'); ?>: <?php echo date('Y.m.d H:i', strtotime($job['work_end_date'])); ?></div>
                                    <?php
                                    $end_date = new DateTime($job['work_end_date']);
                                    $deletion_date = clone $end_date;
                                    $deletion_date->modify('+7 days');
                                    $now = new DateTime();
                                    $days_until_deletion = $deletion_date->diff($now)->days;
                                    ?>
                                    <div class="deletion-countdown">
                                        <i class="fas fa-clock"></i>
                                        <?php echo translate('Törlésig hátralévő idő'); ?>: <?php echo $days_until_deletion; ?> <?php echo translate('nap'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="job-footer">
                        <div class="job-stats">
                            <div class="stat-item">
                                <i class="fas fa-users"></i>
                                <span><?php echo htmlspecialchars($job['user_count']) . ' ' . translate('fő'); ?></span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-tools"></i>
                                <span class="equipment-count"><?php echo htmlspecialchars($job['stuff_count']) . ' ' . translate('eszköz'); ?></span>
                            </div>
                        </div>
                        <div class="job-actions">
                            <button class="btn-action btn-info" onclick="showJobInfo(<?php echo htmlspecialchars(json_encode($job)); ?>)" title="<?php echo translate('Információ'); ?>">
                                <i class="fas fa-info-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div id="jobInfoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo translate('Munka részletei'); ?></h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="info-grid" id="jobInfoContent">
                <!-- A JavaScript tölti fel a tartalmat -->
            </div>
        </div>
    </div>
</div>

<div id="editJobModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo translate('Munka szerkesztése'); ?></h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="accordion">
                <form id="editJobForm" onsubmit="saveJobChanges(event)">
                    <input type="hidden" id="edit_job_id">
                    <input type="hidden" id="edit_project_id">
                    
                    <!-- Projekt (csak megjelenítés) -->
                    <div class="form-group">
                        <label><?php echo translate('Projekt'); ?></label>
                        <div class="readonly-value" id="edit_project_name"></div>
                    </div>

                    <!-- Dolgozó -->
                    <div class="accordion-item">
                        <div class="accordion-header" onclick="toggleAccordion(this)">
                            <span><?php echo translate('Dolgozó'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accordion-content">
                            <div class="form-group">
                                <select id="edit_user" class="form-control" required>
                                    <?php
                                    $users_sql = "SELECT id, firstname, lastname FROM user WHERE company_id = " . $_SESSION['company_id'];
                                    $users_result = mysqli_query($conn, $users_sql);
                                    while ($user = mysqli_fetch_assoc($users_result)) {
                                        echo '<option value="' . $user['id'] . '">' . htmlspecialchars($user['lastname'] . ' ' . $user['firstname']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Szállítási mód -->
                    <div class="accordion-item">
                        <div class="accordion-header" onclick="toggleAccordion(this)">
                            <span><?php echo translate('Szállítási mód'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accordion-content">
                            <div class="form-group">
                                <select id="edit_deliver" class="form-control" required>
                                    <?php
                                    $delivers_sql = "SELECT id, name FROM deliver";
                                    $delivers_result = mysqli_query($conn, $delivers_sql);
                                    while ($deliver = mysqli_fetch_assoc($delivers_result)) {
                                        echo '<option value="' . $deliver['id'] . '">' . htmlspecialchars($deliver['name']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Időpontok -->
                    <div class="accordion-item">
                        <div class="accordion-header" onclick="toggleAccordion(this)">
                            <span><?php echo translate('Időpontok'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accordion-content">
                            <div class="form-group">
                                <div class="date-inputs">
                                    <div class="date-input-group">
                                        <label for="edit_start_date"><?php echo translate('Kezdés'); ?></label>
                                        <input type="datetime-local" id="edit_start_date" class="form-control" required>
                                    </div>
                                    <div class="date-input-group">
                                        <label for="edit_end_date"><?php echo translate('Befejezés'); ?></label>
                                        <input type="datetime-local" id="edit_end_date" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Eszközök -->
                    <div class="accordion-item">
                        <div class="accordion-header" onclick="toggleAccordion(this)">
                            <span><?php echo translate('Eszközök'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accordion-content">
                            <div class="form-group">
                                <!-- Eszköz típus szűrők -->
                                <div class="stuff-filters">
                                    <div class="filter-label"><?php echo translate('Gyors szűrők'); ?>:</div>
                                    <div class="filter-buttons">
                                        <?php
                                        // Eszköz típusok lekérése
                                        $types_sql = "SELECT DISTINCT st.id, st.name 
                                                    FROM stuff_type st 
                                                    JOIN stuffs s ON s.type_id = st.id 
                                                    WHERE s.company_id = " . $_SESSION['company_id'];
                                        $types_result = mysqli_query($conn, $types_sql);
                                        while ($type = mysqli_fetch_assoc($types_result)) {
                                            echo '<button type="button" class="stuff-filter-btn" data-type="' . $type['id'] . '">';
                                            echo '<i class="fas fa-filter"></i> ' . htmlspecialchars($type['name']);
                                            echo '</button>';
                                        }
                                        ?>
                                        <button type="button" class="stuff-filter-btn active" data-type="all">
                                            <i class="fas fa-times"></i> <?php echo translate('Összes'); ?>
                                        </button>
                                    </div>
                                </div>

                                <!-- Eszközök kiválasztása -->
                                <div class="stuff-selection">
                                    <select id="edit_stuffs" class="form-control" multiple>
                                        <?php
                                        // Eszközök lekérdezése típus szerint csoportosítva
                                        $stuffs_sql = "SELECT 
                                            s.id,
                                            st.name as type_name,
                                            CONCAT(
                                                COALESCE(ss.name, ''), ' - ',
                                                COALESCE(sb.name, ''), ' - ',
                                                COALESCE(sm.name, ''),
                                                ' (QR: ', s.qr_code, ')'
                                            ) as stuff_details,
                                            ss2.name as status_name
                                        FROM stuffs s
                                        LEFT JOIN stuff_type st ON s.type_id = st.id
                                        LEFT JOIN stuff_secondtype ss ON s.secondtype_id = ss.id
                                        LEFT JOIN stuff_brand sb ON s.brand_id = sb.id
                                        LEFT JOIN stuff_model sm ON s.model_id = sm.id
                                        LEFT JOIN stuff_status ss2 ON s.stuff_status_id = ss2.id
                                        WHERE s.company_id = " . $_SESSION['company_id'] . "
                                        ORDER BY st.name, ss.name, sb.name, sm.name";
                                        
                                        $stuffs_result = mysqli_query($conn, $stuffs_sql);
                                        $current_type = '';
                                        
                                        while ($stuff = mysqli_fetch_assoc($stuffs_result)) {
                                            if ($current_type != $stuff['type_name']) {
                                                if ($current_type != '') echo '</optgroup>';
                                                echo '<optgroup label="' . htmlspecialchars($stuff['type_name']) . '">';
                                                $current_type = $stuff['type_name'];
                                            }
                                            
                                            $status_class = $stuff['status_name'] == 'Használatban' ? 'text-danger' : 'text-success';
                                            echo '<option value="' . $stuff['id'] . '" data-status="' . htmlspecialchars($stuff['status_name']) . '">' 
                                                . htmlspecialchars($stuff['type_name'] . ' - ' . $stuff['stuff_details'])
                                                . ' [' . htmlspecialchars($stuff['status_name']) . ']'
                                                . '</option>';
                                        }
                                        if ($current_type != '') echo '</optgroup>';
                                        ?>
                                    </select>
                                    <div class="selection-info">
                                        <span class="selected-count"><?php echo translate('0 eszköz kiválasztva'); ?></span>
                                        <button type="button" class="btn-clear-selection">
                                            <i class="fas fa-times"></i> <?php echo translate('Kiválasztás törlése'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="closeEditModal()">
                            <i class="fas fa-times"></i>
                            <?php echo translate('Mégse'); ?>
                        </button>
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i>
                            <?php echo translate('Mentés'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="deleteConfirmModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo translate('Munka törlése'); ?></h3>
            <button class="modal-close" onclick="closeDeleteConfirmModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="delete-confirmation">
                <div class="delete-warning">
                    <i class="fas fa-exclamation-circle"></i>
                    <h4><?php echo translate('Biztosan törölni szeretné ezt a munkát?'); ?></h4>
                    <p class="warning-text"><?php echo translate('A törlés nem vonható vissza!'); ?></p>
                </div>
                <div class="job-details">
                    <div class="detail-row">
                        <div class="detail-label"><?php echo translate('Projekt'); ?>:</div>
                        <div class="detail-value" id="delete-project-name"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><?php echo translate('Helyszín'); ?>:</div>
                        <div class="detail-value" id="delete-deliver-name"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><?php echo translate('Kezdés dátuma'); ?>:</div>
                        <div class="detail-value" id="delete-start-date"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><?php echo translate('Befejezés dátuma'); ?>:</div>
                        <div class="detail-value" id="delete-end-date"></div>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteConfirmModal()">Mégse</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" style="background-color: #dc2626; color: white;">Törlés</button>
            </div>
        </div>
    </div>
</div>

<script>
function showJobInfo(jobData) {
    const modal = document.getElementById('jobInfoModal');
    const content = document.getElementById('jobInfoContent');
    
    // Formázzuk a dátumokat
    const startDate = new Date(jobData.work_start_date).toLocaleString('hu-HU');
    const endDate = new Date(jobData.work_end_date).toLocaleString('hu-HU');
    
    // Helyszín összeállítása
    const location = [
        jobData.country_name || 'Ismeretlen ország',
        jobData.county_name || 'Ismeretlen megye',
        jobData.city_name || 'Ismeretlen város'
    ].filter(Boolean).join(', ');
    
    // Eszközök listájának feldolgozása
    let equipmentHtml = '';
    if (jobData.equipment_list) {
        const equipment = jobData.equipment_list.split('|||');
        equipmentHtml = equipment.map(item => `
            <div class="equipment-item">
                <i class="fas fa-tools"></i>
                ${item}
            </div>
        `).join('');
    } else {
        equipmentHtml = '<div class="equipment-item">Nincs hozzárendelt eszköz</div>';
    }
    
    content.innerHTML = `
        <div class="info-row">
            <div class="info-label"><?php echo translate('Projekt'); ?>:</div>
            <div class="info-value">${jobData.project_name || 'Nincs megadva'}</div>
        </div>
        <div class="info-row">
            <div class="info-label"><?php echo translate('Helyszín'); ?>:</div>
            <div class="info-value">${location}</div>
        </div>
        <div class="info-row">
            <div class="info-label"><?php echo translate('Cég'); ?>:</div>
            <div class="info-value">${jobData.company_name || 'Nincs megadva'}</div>
        </div>
        <div class="info-row">
            <div class="info-label"><?php echo translate('Dolgozó(k)'); ?>:</div>
            <div class="info-value">${jobData.worker_names || 'Nincs dolgozó hozzárendelve'}</div>
        </div>
        <div class="info-row">
            <div class="info-label"><?php echo translate('Szállítási mód'); ?>:</div>
            <div class="info-value">${jobData.deliver_name || 'Nincs megadva'}</div>
        </div>
        <div class="info-row dates-row">
            <div class="date-column">
            <div class="info-label"><?php echo translate('Kezdés'); ?>:</div>
            <div class="info-value">${startDate}</div>
        </div>
            <div class="date-column">
            <div class="info-label"><?php echo translate('Befejezés'); ?>:</div>
            <div class="info-value">${endDate}</div>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label"><?php echo translate('Eszközök'); ?>:</div>
            <div class="info-value equipment-list">
                ${equipmentHtml}
            </div>
        </div>
    `;
    
    modal.style.display = 'block';
}

function closeModal() {
    const modal = document.getElementById('jobInfoModal');
    modal.style.display = 'none';
}

// Kattintás eseménykezelő a modális ablak kívüli területre
window.onclick = function(event) {
    const modal = document.getElementById('jobInfoModal');
    if (event.target == modal) {
        closeModal();
    }
}

function editJob(id) {
    fetch(`munkak.php?action=get_job&id=${id}`)
        .then(response => response.json())
        .then(job => {
            document.getElementById('edit_job_id').value = job.id;
            document.getElementById('edit_project_name').textContent = job.project_name;
            document.getElementById('edit_user').value = job.user_id;
            document.getElementById('edit_deliver').value = job.deliver_id;
            
            // Eszközök kiválasztása
            const stuffsSelect = document.getElementById('edit_stuffs');
            const selectedStuffs = job.selected_stuffs ? job.selected_stuffs.split(',') : [];
            Array.from(stuffsSelect.options).forEach(option => {
                option.selected = selectedStuffs.includes(option.value);
            });
            
            // Dátumok beállítása
            const startDate = new Date(job.work_start_date).toISOString().slice(0, 16);
            const endDate = new Date(job.work_end_date).toISOString().slice(0, 16);
            document.getElementById('edit_start_date').value = startDate;
            document.getElementById('edit_end_date').value = endDate;
            
            document.getElementById('editJobModal').style.display = 'block';
            
            const firstAccordion = document.querySelector('.accordion-item');
            if (firstAccordion) {
                firstAccordion.classList.add('active');
            }
            
            // Select2 inicializálása
            initializeSelect2();
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Hiba történt az adatok betöltése során!', 'error');
        });
}

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

function closeEditModal() {
    document.getElementById('editJobModal').style.display = 'none';
}

async function saveJobChanges(event) {
    event.preventDefault();
    
    const stuffsSelect = document.getElementById('edit_stuffs');
    const selectedStuffs = Array.from(stuffsSelect.selectedOptions).map(option => option.value);
    
    const formData = {
        action: 'update',
        id: document.getElementById('edit_job_id').value,
        user_id: document.getElementById('edit_user').value,
        deliver_id: document.getElementById('edit_deliver').value,
        work_start_date: document.getElementById('edit_start_date').value,
        work_end_date: document.getElementById('edit_end_date').value,
        stuffs_ids: selectedStuffs
    };

    try {
        const response = await fetch('munkak.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();
        
        if (data.success) {
            closeEditModal();
            showNotification('<?php echo translate('A munka sikeresen módosítva!'); ?>', 'success');
            
            // Frissítsük a kártya tartalmát AJAX-szal
            const jobResponse = await fetch(`munkak.php?action=get_job&id=${formData.id}`);
            const updatedJob = await jobResponse.json();
            
            // Keressük meg és frissítsük a megfelelő kártyát
            const jobCard = document.querySelector(`[data-job-id="${formData.id}"]`);
            if (jobCard) {
                // Frissítsük a szállítási módot
                jobCard.querySelector('.deliver-name').textContent = 
                    updatedJob.deliver_name || 'Nincs megadva';
                
                // Frissítsük a dátumokat
                const startDate = new Date(updatedJob.work_start_date);
                const endDate = new Date(updatedJob.work_end_date);
                
                // Formázzuk a dátumokat a megfelelő formátumban (év.hónap.nap óra:perc)
                const formatDate = (date) => {
                    return date.toLocaleString('hu-HU', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                };
                
                jobCard.querySelector('.date-range').innerHTML = `
                    <div><?php echo translate('Kezdés'); ?>: ${formatDate(startDate)}</div>
                    <div><?php echo translate('Befejezés'); ?>: ${formatDate(endDate)}</div>
                `;
                
                // Frissítsük az eszközök számát és listáját
                const equipmentCount = updatedJob.selected_stuffs ? 
                    updatedJob.selected_stuffs.split(',').length : 0;
                jobCard.querySelector('.equipment-count').textContent = 
                    `${equipmentCount} eszköz`;
                
                // Frissítsük a dolgozó nevét is, ha van ilyen mező a kártyán
                const userNameElement = jobCard.querySelector('.user-name');
                if (userNameElement) {
                    userNameElement.textContent = `${updatedJob.lastname} ${updatedJob.firstname}`;
                }
            }
        } else {
            showNotification('<?php echo translate('Hiba történt a mentés során!'); ?>', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Hiba történt a mentés során!', 'error');
    }
}

function deleteJob(jobId) {
    // Adatok lekérése a munkáról
    fetch(`munkak.php?action=get_job&id=${jobId}`)
    .then(response => response.json())
        .then(jobData => {
            // Modal megnyitása és adatok megjelenítése
            const modal = document.getElementById('deleteConfirmModal');
            document.getElementById('delete-project-name').textContent = jobData.project_name || 'Ismeretlen projekt';
            
            // Helyszín összeállítása (ország, megye, város)
            const location = [
                jobData.country_name || 'Ismeretlen ország',
                jobData.county_name || 'Ismeretlen megye',
                jobData.city_name || 'Ismeretlen város'
            ].filter(Boolean).join(', ');
            
            document.getElementById('delete-deliver-name').textContent = location;
            document.getElementById('delete-start-date').textContent = new Date(jobData.work_start_date).toLocaleString('hu-HU') || 'Nincs megadva';
            document.getElementById('delete-end-date').textContent = new Date(jobData.work_end_date).toLocaleString('hu-HU') || 'Nincs megadva';

            // Modal megjelenítése
            modal.style.display = 'block';

            // Törlés gomb eseménykezelő
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            confirmDeleteBtn.onclick = () => {
                // Azonnal elrejtjük a törlés megerősítő modált
                closeDeleteConfirmModal();
                
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('work_id', jobId);

                fetch('munkak.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Megkeressük a törlendő kártyát
                        const jobCard = document.querySelector(`[data-job-id="${jobId}"]`);
                    if (jobCard) {
                            // Animáljuk a kártya eltűnését
                            jobCard.style.transition = 'all 0.3s ease';
                        jobCard.style.opacity = '0';
                        jobCard.style.transform = 'scale(0.8)';
                            
                            // Várunk az animáció végéig, majd eltávolítjuk a DOM-ból
                            setTimeout(() => {
                                jobCard.style.height = '0';
                                jobCard.style.margin = '0';
                                jobCard.style.padding = '0';
                        
                        setTimeout(() => {
                            jobCard.remove();
                                    // Sikeres törlés üzenet megjelenítése
                                    showNotification('<?php echo translate('A munka sikeresen törölve!'); ?>', 'success');
                                    
                                    // Ellenőrizzük, hogy van-e még munka
                            const remainingJobs = document.querySelectorAll('.job-card');
                            if (remainingJobs.length === 0) {
                                const jobsGrid = document.querySelector('.jobs-grid');
                                jobsGrid.innerHTML = '<div class="no-jobs">Nincsenek még munkák.</div>';
                            }
                                }, 300);
                        }, 300);
                    }
                } else {
                        showNotification('Hiba történt a törlés során: ' + (result.error || 'Ismeretlen hiba'), 'error');
                    }
                })
                .catch(error => {
                    showNotification('Hiba történt a törlés során: ' + error.message, 'error');
                });
            };
    })
    .catch(error => {
            alert('<?php echo translate('Hiba történt az adatok lekérése során:'); ?> ' + error.message);
        });
}

function closeDeleteConfirmModal() {
    const modal = document.getElementById('deleteConfirmModal');
    modal.style.display = 'none';
}

// Bezárás a modalon kívüli kattintásra
window.onclick = function(event) {
    const deleteModal = document.getElementById('deleteConfirmModal');
    if (event.target == deleteModal) {
        deleteModal.style.display = 'none';
    }
}

// Értesítés megjelenítése funkció
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
        notification.classList.add('hide');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 500);
    }, 3000);
}

// Adjuk hozzá az új stílusokat
const style = document.createElement('style');
style.textContent = `
    .job-card {
        transition: all 0.3s ease;
    }
    .no-jobs {
        text-align: center;
        padding: 2rem;
        color: #6b7280;
        font-size: 1.1rem;
    }
`;
document.head.appendChild(style);

document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const jobCards = document.querySelectorAll('.job-card');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons
            filterBtns.forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');

            const type = this.dataset.type;

            jobCards.forEach(card => {
                if (type === 'all') {
                    card.style.display = 'flex';
                } else {
                    const projectTypeElement = card.querySelector('.project-type');
                    const projectTypeId = projectTypeElement ? projectTypeElement.getAttribute('data-type-id') : null;
                    
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

// Select2 inicializálása és testreszabása
function initializeSelect2() {
    $('#edit_stuffs').select2({
        placeholder: '<?php echo translate('Kezdjen el gépelni az eszköz kereséséhez...'); ?>',
        allowClear: true,
        language: {
            noResults: function() {
                return "<?php echo translate('Nincs találat'); ?>";
            },
            searching: function() {
                return "<?php echo translate('Keresés...'); ?>";
            }
        },
        templateResult: formatStuff,
        templateSelection: formatStuffSelection
    }).on('change', function() {
        updateSelectionInfo();
    });

    // Szűrő gombok kezelése
    $('.stuff-filter-btn').click(function() {
        $('.stuff-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        const typeId = $(this).data('type');
        filterStuffsByType(typeId);
    });

    // Kiválasztás törlése gomb
    $('.btn-clear-selection').click(function() {
        $('#edit_stuffs').val(null).trigger('change');
    });

    updateSelectionInfo();
}

// Eszközök szűrése típus szerint
function filterStuffsByType(typeId) {
    const select = $('#edit_stuffs');
    const options = select.find('option');
    
    if (typeId === 'all') {
        options.prop('disabled', false);
        select.select2('destroy').select2();
        return;
    }

    // Get the type name from the button text
    const typeName = $(`.stuff-filter-btn[data-type="${typeId}"]`).text().trim();
    
    options.each(function() {
        const optionText = $(this).text();
        const belongsToType = optionText.startsWith(typeName);
        $(this).prop('disabled', !belongsToType);
    });

    select.select2('destroy').select2();
}

// Kiválasztott eszközök számának frissítése
function updateSelectionInfo() {
    const selectedCount = $('#edit_stuffs').select2('data').length;
    $('.selected-count').text(`${selectedCount} <?php echo translate('eszköz kiválasztva'); ?>`);
}

// Eszköz formázása a legördülő listában
function formatStuff(stuff) {
    if (!stuff.id) return stuff.text;
    
    const status = $(stuff.element).data('status');
    const statusClass = status === 'Használatban' ? 'text-danger' : 'text-success';
    
    return $(`
        <div class="stuff-option">
            <i class="fas fa-tools"></i>
            <div>
                <div class="stuff-name">${stuff.text}</div>
                <div class="stuff-details ${statusClass}">
                    <i class="fas fa-circle"></i> ${status}
                </div>
            </div>
        </div>
    `);
}

// Kiválasztott eszköz formázása
function formatStuffSelection(stuff) {
    if (!stuff.id) return stuff.text;
    
    const status = $(stuff.element).data('status');
    return $(`
        <span>
            <i class="fas fa-tools"></i>
            ${stuff.text}
        </span>
    `);
}

// Visszaszámlálás frissítése
function updateCountdowns() {
    const countdowns = document.querySelectorAll('.countdown');
    countdowns.forEach(countdown => {
        const startDate = new Date(countdown.dataset.start);
        const now = new Date();
        const diff = startDate - now;

        if (diff > 0) {
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            let timeText = '';
            if (days > 0) {
                timeText += days + ' <?php echo translate('nap'); ?> ';
            }
            timeText += `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            
            countdown.innerHTML = `<i class="fas fa-hourglass-half"></i> <?php echo translate('Hátralévő idő'); ?>: ${timeText}`;
        } else {
            countdown.remove();
            location.reload(); // Frissítjük az oldalt, ha lejárt a visszaszámlálás
        }
    });
}

// Visszaszámlálás indítása
setInterval(updateCountdowns, 1000);

document.addEventListener('DOMContentLoaded', function() {
    updateCountdowns(); // Első futtatás azonnal
    // ... existing code ...
});

// Function to update tool statuses when a work is completed
function updateToolStatusesForCompletedWork(workId) {
    return fetch('update_work_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            work_id: workId,
            action: 'update_tools'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message);
        }
        console.log(`Tool statuses updated for work ${workId}`);
    })
    .catch(error => {
        console.error('Error updating tool statuses:', error);
        throw error;
    });
}

// Check for completed works and update tool statuses
function checkCompletedWorks() {
    const completedJobs = document.querySelectorAll('.job-card.completed');
    completedJobs.forEach(job => {
        const workId = job.dataset.jobId;
        if (workId) {
            updateToolStatusesForCompletedWork(workId);
        }
    });
}

// Function to update job status to "Munkában" when work starts
function updateJobStatusToInProgress(workId) {
    fetch('update_work_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            work_id: workId,
            action: 'start'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Job status updated to Munkában successfully');
            // Reload the page to show updated status
            location.reload();
        } else {
            console.error('Error updating job status:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function updateJobStatusToAvailable(workId) {
    fetch('update_work_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            work_id: workId,
            action: 'end'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Job status updated to Elérhető successfully');
            // Reload the page to show updated status
            location.reload();
        } else {
            console.error('Error updating job status:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Check for jobs that have started
function checkStartedJobs() {
    const now = new Date();
    const jobs = document.querySelectorAll('.job-card:not(.completed):not(.in-progress)');
    
    jobs.forEach(job => {
        const startDate = new Date(job.querySelector('.date-range').dataset.start);
        if (now >= startDate) {
            const workId = job.dataset.jobId;
            if (workId) {
                updateJobStatusToInProgress(workId);
            }
        }
    });
}

// Check for jobs that have ended
function checkEndedJobs() {
    const now = new Date();
    const jobs = document.querySelectorAll('.job-card.in-progress');
    
    jobs.forEach(job => {
        const endDate = new Date(job.querySelector('.date-range').dataset.end);
        if (now >= endDate) {
            const workId = job.dataset.jobId;
            if (workId) {
                updateJobStatusToAvailable(workId);
            }
        }
    });
}

// Call the functions when the page loads
document.addEventListener('DOMContentLoaded', function() {
    checkCompletedWorks();
    checkStartedJobs();
    checkEndedJobs();
    
    // Check for started and ended jobs every minute
    setInterval(function() {
        checkStartedJobs();
        checkEndedJobs();
    }, 60000);
});

// Job status check function
function checkJobStatuses() {
    // Get the last check time from localStorage or set it to 0 if not exists
    const lastCheckTime = parseInt(localStorage.getItem('lastJobStatusCheck') || '0');
    const currentTime = new Date().getTime();
    
    // Only proceed if at least 5 minutes have passed since the last check
    if (currentTime - lastCheckTime < 300000) { // 5 minutes in milliseconds
        return;
    }
    
    // Update the last check time
    localStorage.setItem('lastJobStatusCheck', currentTime.toString());
    
    // Get the list of jobs that have already been processed
    const processedJobs = JSON.parse(localStorage.getItem('processedJobs') || '[]');
    
    const jobCards = document.querySelectorAll('.job-card');
    const now = new Date();
    
    jobCards.forEach(card => {
        const startDate = new Date(card.dataset.startDate);
        const endDate = new Date(card.dataset.endDate);
        const jobId = card.dataset.jobId;
        
        // Skip if this job has already been processed in this session
        if (processedJobs.includes(jobId)) {
            return;
        }
        
        // Check if job has started
        if (now >= startDate && now < endDate) {
            fetch('update_work_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    work_id: jobId,
                    action: 'start'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`Job ${jobId} status updated to Munkában`);
                    // Add to processed jobs
                    processedJobs.push(jobId);
                    localStorage.setItem('processedJobs', JSON.stringify(processedJobs));
                    
                    // Update the UI without reloading
                    updateJobStatusUI(jobId, 'Munkában');
                } else {
                    console.error('Error updating job status:', data.message);
                }
            })
            .catch(error => console.error('Error updating job status:', error));
        }
        // Check if job has ended
        else if (now >= endDate) {
            fetch('update_work_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    work_id: jobId,
                    action: 'end'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`Job ${jobId} status updated to Elérhető`);
                    // Add to processed jobs
                    processedJobs.push(jobId);
                    localStorage.setItem('processedJobs', JSON.stringify(processedJobs));
                    
                    // Update the UI without reloading
                    updateJobStatusUI(jobId, 'Elérhető');
                } else {
                    console.error('Error updating job status:', data.message);
                }
            })
            .catch(error => console.error('Error updating job status:', error));
        }
    });
}

// Function to update the UI without reloading the page
function updateJobStatusUI(jobId, status) {
    // Find the job card
    const jobCard = document.querySelector(`.job-card[data-job-id="${jobId}"]`);
    if (!jobCard) return;
    
    // Update the status badge
    const statusBadge = jobCard.querySelector('.status-badge');
    if (statusBadge) {
        statusBadge.textContent = status;
        
        // Update badge color based on status
        if (status === 'Munkában') {
            statusBadge.classList.remove('status-available');
            statusBadge.classList.add('status-in-progress');
        } else if (status === 'Elérhető') {
            statusBadge.classList.remove('status-in-progress');
            statusBadge.classList.add('status-available');
        }
    }
    
    // Update the worker status in the job info modal if it's open
    const jobInfoModal = document.getElementById('jobInfoModal');
    if (jobInfoModal && jobInfoModal.classList.contains('show')) {
        const currentJobId = jobInfoModal.getAttribute('data-job-id');
        if (currentJobId === jobId.toString()) {
            // Update the worker status in the modal
            const workerStatusElement = document.querySelector('#jobInfoModal .worker-status');
            if (workerStatusElement) {
                workerStatusElement.textContent = status;
            }
        }
    }
}

// Start periodic job status check
setInterval(checkJobStatuses, 60000); // Check every minute
checkJobStatuses(); // Initial check
</script>

<?php require_once '../includes/layout/footer.php'; ?> 