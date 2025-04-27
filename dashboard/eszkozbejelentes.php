<?php
// Output pufferelés indítása
ob_start();

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/layout/header.php';

// Ellenőrizzük a bejelentkezést
if (!isset($_SESSION['user_id'])) {
    header('Location: /Vizsga_oldal/auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Form feldolgozása
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stuff_id = $_POST['stuff_id'] ?? null;
    $work_id = $_POST['work_id'] ?? null;
    $status_id = $_POST['status_id'] ?? null;
    $description = $_POST['description'] ?? null;
    $user_id = $_SESSION['user_id'];

    if ($stuff_id && $work_id && $status_id) {
        try {
            // Ellenőrizzük, hogy az eszköz már be volt-e jelentve erre a munkára
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM stuff_history WHERE stuffs_id = ? AND work_id = ?");
            $check_stmt->execute([$stuff_id, $work_id]);
            $already_reported = $check_stmt->fetchColumn();

            if ($already_reported > 0) {
                $_SESSION['error_message'] = "Ez az eszköz már be lett jelentve erre a munkára!";
                header('Location: eszkozbejelentes.php');
                exit;
            }

            // Eszköz státuszának frissítése
            $stmt = $db->prepare("UPDATE stuffs SET stuff_status_id = ? WHERE id = ?");
            $stmt->execute([$status_id, $stuff_id]);

            // Eszköz történet rögzítése
            $stmt = $db->prepare("INSERT INTO stuff_history (stuffs_id, work_id, user_id, stuff_status_id, description) 
                                VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$stuff_id, $work_id, $user_id, $status_id, $description]);

            $_SESSION['success_message'] = "Az eszköz állapota sikeresen frissítve!";
            header('Location: eszkozbejelentes.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Hiba történt: " . $e->getMessage();
        }
    }
}

// Aktív munkák lekérése az adott felhasználóhoz
$stmt = $db->prepare("
    SELECT DISTINCT w.id, p.name as project_name, w.work_start_date, w.work_end_date
    FROM work w
    JOIN project p ON w.project_id = p.id
    JOIN user_to_work utw ON w.id = utw.work_id
    WHERE (utw.user_id = ? OR w.id IN (
        SELECT work_id FROM stuff_history WHERE user_id = ?
    ))
    AND w.work_end_date < NOW()
    ORDER BY w.work_start_date DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$works = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Státuszok lekérése (translation_key nélkül)
$stmt = $db->prepare("
    SELECT id, name 
    FROM stuff_status 
    WHERE name IN ('" . translate('Hibás') . "', '" . translate('Törött') . "')
");
$stmt->execute();
$statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2><?php echo translate('Eszköz állapot bejelentése'); ?></h2>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
            echo translate($_SESSION['success_message']);
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo translate($_SESSION['error_message']);
            unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="mt-4">
        <div class="form-group">
            <label for="work_id"><?php echo translate('Munka kiválasztása'); ?>:</label>
            <select class="form-control" id="work_id" name="work_id" required>
                <option value=""><?php echo translate('Válassz munkát...'); ?></option>
                <?php foreach ($works as $work): ?>
                    <option value="<?php echo $work['id']; ?>">
                        <?php echo htmlspecialchars($work['project_name'] . ' (' . 
                            date('Y-m-d', strtotime($work['work_start_date'])) . ' - ' . 
                            date('Y-m-d', strtotime($work['work_end_date'])) . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="stuff_id"><?php echo translate('Eszköz'); ?>:</label>
            <select class="form-control" id="stuff_id" name="stuff_id" required disabled>
                <option value=""><?php echo translate('Először válassz munkát...'); ?></option>
            </select>
        </div>

        <div class="form-group">
            <label for="status_id"><?php echo translate('Új állapot'); ?>:</label>
            <select class="form-control" id="status_id" name="status_id" required>
                <option value=""><?php echo translate('Válassz állapotot...'); ?></option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo $status['id']; ?>">
                        <?php echo translate($status['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="description"><?php echo translate('Megjegyzés'); ?>:</label>
            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
        </div>

        <button type="submit" class="btn btn-primary"><?php echo translate('Bejelentés'); ?></button>
    </form>
</div>

<script>
document.getElementById('work_id').addEventListener('change', function() {
    const workId = this.value;
    const stuffSelect = document.getElementById('stuff_id');
    
    if (workId) {
        // AJAX kérés a munkához tartozó eszközök lekéréséhez
        fetch(`get_work_stuffs.php?work_id=${workId}`)
            .then(response => response.json())
            .then(data => {
                stuffSelect.innerHTML = `<option value="">${<?php echo json_encode(translate('Válassz eszközt...')); ?>}</option>`;
                
                if (data.success && data.data.length > 0) {
                    data.data.forEach(stuff => {
                        const qrCode = stuff.qr_code ? ` (QR: ${stuff.qr_code})` : '';
                        stuffSelect.innerHTML += `<option value="${stuff.id}">${stuff.name}${qrCode}</option>`;
                    });
                    stuffSelect.disabled = false;
                } else {
                    stuffSelect.innerHTML = `<option value="">${<?php echo json_encode(translate('Nincs bepakolt eszköz ehhez a munkához')); ?>}</option>`;
                    stuffSelect.disabled = true;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                stuffSelect.innerHTML = `<option value="">${<?php echo json_encode(translate('Hiba történt az eszközök betöltésekor')); ?>}</option>`;
                stuffSelect.disabled = true;
            });
    } else {
        stuffSelect.innerHTML = `<option value="">${<?php echo json_encode(translate('Először válassz munkát...')); ?>}</option>`;
        stuffSelect.disabled = true;
    }
});

// Állapot változás figyelése
document.getElementById('status_id').addEventListener('change', function() {
    const description = document.getElementById('description');
    if (this.options[this.selectedIndex].text === <?php echo json_encode(translate('Hibás')); ?> || 
        this.options[this.selectedIndex].text === <?php echo json_encode(translate('Törött')); ?>) {
        description.required = true;
        description.parentElement.classList.add('required');
    } else {
        description.required = false;
        description.parentElement.classList.remove('required');
    }
});
</script>

<style>
.container.mt-4 {
    max-width: 90% !important;  /* A képernyő 90%-át használja */
    min-width: 600px !important;  /* Minimum szélesség */
    margin: 0 auto;
    padding: 20px;
    width: 100%;
}

.form-group {
    margin-bottom: 1rem;
}

.form-control {
    width: 100%;
    padding: 0.375rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
}

.btn-primary {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
    padding: 0.375rem 0.75rem;
    border-radius: 0.25rem;
    cursor: pointer;
}

.btn-primary:hover {
    background-color: #0069d9;
    border-color: #0062cc;
}

.alert {
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.required label::after {
    content: " *";
    color: red;
}
</style>

<?php require_once '../includes/layout/footer.php';
// Output puffer kiürítése és küldése
ob_end_flush(); ?> 