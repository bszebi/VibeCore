<?php
session_start();

// Frissítés kezelése - ezt tegyük a header.php include előtt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    require_once '../includes/db.php';
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("
            UPDATE eszkozok 
            SET tipus = ?, marka_id = ?, modell_id = ?, ev = ?, allapot_id = ?, qr_kod = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['tipus'],
            $_POST['marka_id'],
            $_POST['modell_id'],
            $_POST['ev'],
            $_POST['allapot_id'],
            $_POST['qr_kod'],
            $_GET['id']
        ]);
        
        $_SESSION['success_message'] = "Az eszköz sikeresen frissítve!";
        $_SESSION['show_toast'] = true;
        header("Location: eszkozok.php");
        exit;
    } catch (PDOException $e) {
        $error = "Hiba történt a mentés során: " . $e->getMessage();
    }
}

require_once '../includes/layout/header.php';

$db = Database::getInstance()->getConnection();
$error = '';
$success = '';

// Eszköz adatainak lekérése
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $db->prepare("
            SELECT e.*, m.nev as marka_nev, mo.nev as modell_nev, a.nev as allapot_nev
            FROM eszkozok e
            LEFT JOIN markak m ON e.marka_id = m.id
            LEFT JOIN modellek mo ON e.modell_id = mo.id
            LEFT JOIN eszkoz_allapotok a ON e.allapot_id = a.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $eszkoz = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$eszkoz) {
            header("Location: eszkozok.php");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Hiba történt az adatok lekérése során.";
    }
}

// Márkák, modellek és állapotok lekérése
$markak = $db->query("SELECT * FROM markak")->fetchAll(PDO::FETCH_ASSOC);
$modellek = $db->query("SELECT * FROM modellek")->fetchAll(PDO::FETCH_ASSOC);
$allapotok = $db->query("SELECT * FROM eszkoz_allapotok")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <h1 class="page-title">Eszköz szerkesztése</h1>
    
    <div class="card">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="form-grid">
            <div class="form-group">
                <label for="tipus">Típus</label>
                <input type="text" id="tipus" name="tipus" class="form-control" 
                       value="<?php echo htmlspecialchars($eszkoz['tipus']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="marka_id">Márka</label>
                <select id="marka_id" name="marka_id" class="form-control" required>
                    <?php foreach ($markak as $marka): ?>
                        <option value="<?php echo $marka['id']; ?>" 
                            <?php echo $marka['id'] == $eszkoz['marka_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($marka['nev']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="modell_id">Modell</label>
                <select id="modell_id" name="modell_id" class="form-control" required>
                    <?php foreach ($modellek as $modell): ?>
                        <option value="<?php echo $modell['id']; ?>"
                            <?php echo $modell['id'] == $eszkoz['modell_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($modell['nev']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="ev">Év</label>
                <input type="number" id="ev" name="ev" class="form-control" 
                       value="<?php echo htmlspecialchars($eszkoz['ev']); ?>"
                       min="1900" max="<?php echo date('Y'); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="allapot_id">Állapot</label>
                <select id="allapot_id" name="allapot_id" class="form-control" required>
                    <?php foreach ($allapotok as $allapot): ?>
                        <option value="<?php echo $allapot['id']; ?>"
                            <?php echo $allapot['id'] == $eszkoz['allapot_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($allapot['nev']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="qr_kod">QR kód</label>
                <div class="qr-code-container">
                    <input type="text" id="qr_kod" name="qr_kod" class="form-control" 
                           value="<?php echo htmlspecialchars($eszkoz['qr_kod']); ?>" required>
                    <button type="button" id="generateQR" class="btn btn-secondary">
                        Új QR kód
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" name="update" class="btn btn-primary">Mentés</button>
                <a href="eszkozok.php" class="btn btn-secondary">Vissza</a>
            </div>
        </form>
    </div>
</div>

<style>
.qr-code-container {
    display: flex;
    gap: 10px;
    align-items: center;
}

.qr-code-container .form-control {
    flex: 1;
}

#generateQR {
    white-space: nowrap;
    padding: 8px 16px;
    background-color: #3498db;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s ease;
}

#generateQR:hover {
    background-color: #2980b9;
}

.confirm-dialog {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.confirm-dialog-content {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    width: 90%;
    max-width: 400px;
}

.confirm-dialog-title {
    font-size: 18px;
    margin-bottom: 15px;
    color: #2c3e50;
}

.confirm-dialog-buttons {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
}

.confirm-btn {
    padding: 8px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.confirm-btn-yes {
    background-color: #3498db;
    color: white;
}

.confirm-btn-no {
    background-color: #e0e0e0;
    color: #333;
}

.confirm-btn:hover {
    opacity: 0.9;
}
</style>

<div id="confirmDialog" class="confirm-dialog">
    <div class="confirm-dialog-content">
        <div class="confirm-dialog-title">QR kód generálása</div>
        <p>Biztosan szeretne új QR kódot generálni? A régi QR kód többé nem lesz használható.</p>
        <div class="confirm-dialog-buttons">
            <button class="confirm-btn confirm-btn-yes" id="confirmYes">Igen</button>
            <button class="confirm-btn confirm-btn-no" id="confirmNo">Mégsem</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const generateQRBtn = document.getElementById('generateQR');
    const qrInput = document.getElementById('qr_kod');
    const confirmDialog = document.getElementById('confirmDialog');
    const confirmYes = document.getElementById('confirmYes');
    const confirmNo = document.getElementById('confirmNo');

    // QR kód generálás gomb eseménykezelő
    generateQRBtn.addEventListener('click', function() {
        confirmDialog.style.display = 'block';
    });

    // Igen gomb eseménykezelő
    confirmYes.addEventListener('click', function() {
        const newQRCode = generateRandomQRCode();
        qrInput.value = newQRCode;
        confirmDialog.style.display = 'none';
        
        // Vizuális visszajelzés
        qrInput.style.backgroundColor = '#e8f5e9';
        setTimeout(() => {
            qrInput.style.backgroundColor = '';
        }, 1000);
    });

    // Mégsem gomb eseménykezelő
    confirmNo.addEventListener('click', function() {
        confirmDialog.style.display = 'none';
    });

    // Kívül kattintás eseménykezelő
    window.addEventListener('click', function(event) {
        if (event.target === confirmDialog) {
            confirmDialog.style.display = 'none';
        }
    });

    // ESC billentyű eseménykezelő
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && confirmDialog.style.display === 'block') {
            confirmDialog.style.display = 'none';
        }
    });

    // QR kód generáló függvény
    function generateRandomQRCode() {
        const timestamp = Date.now().toString();
        const random = Math.random().toString(36).substring(2, 8);
        return `QR${timestamp}${random}`.toUpperCase();
    }
});
</script>

<?php require_once '../includes/layout/footer.php'; ?> 