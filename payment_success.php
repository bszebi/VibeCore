<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Adatbázis kapcsolat létrehozása
$db = Database::getInstance();
$conn = $db->getConnection();

// Ellenőrizzük, hogy van-e már aktív session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ellenőrizzük, hogy be van-e jelentkezve a felhasználó
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Ellenőrizzük, hogy van-e subscription_id paraméter
if (!isset($_GET['subscription_id'])) {
    header('Location: home.php');
    exit;
}

// Előfizetés adatainak lekérése
$subscription_id = $_GET['subscription_id'];
$stmt = $conn->prepare("SELECT s.*, sp.name as plan_name, sp.description as plan_description, 
                              ph.amount as actual_price, c.company_name, 
                              pm.card_type, pm.last_four_digits,
                              s.start_date, s.end_date,
                              bi.name as billing_interval,
                              (SELECT modification_reason FROM subscription_modifications 
                               WHERE subscription_id = s.id 
                               ORDER BY modification_date DESC LIMIT 1) as modification_details
                       FROM subscriptions s 
                       JOIN subscription_plans sp ON s.subscription_plan_id = sp.id 
                       JOIN company c ON s.company_id = c.id 
                       JOIN payment_methods pm ON s.payment_method_id = pm.id
                       JOIN payment_history ph ON ph.subscription_id = s.id
                       JOIN billing_intervals bi ON sp.billing_interval_id = bi.id
                       WHERE s.id = ? AND s.user_id = ?
                       ORDER BY ph.payment_date DESC LIMIT 1");

$stmt->execute([$subscription_id, $_SESSION['user_id']]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription) {
    error_log("Nem található előfizetés: ID = $subscription_id, User ID = " . $_SESSION['user_id']);
    header('Location: home.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fizetés sikeres - VibeCore</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <style>
        .success-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            padding: 20px;
        }

        .success-container {
            margin-top: 75px;
            background: #fff;
            width: 100%;
            max-width: 600px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            text-align: center;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: #2ecc71;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }

        .success-icon i {
            color: white;
            font-size: 40px;
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
        }

        .subscription-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }

        .subscription-details h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #666;
            align-items: flex-start;
        }

        .detail-row strong {
            color: #2c3e50;
            text-align: right;
            max-width: 60%;
        }

        .package-details {
            background: #fff;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .package-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e1e1e1;
        }

        .package-content {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .package-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #2c3e50;
            font-size: 14px;
        }

        .package-item i {
            color: #2ecc71;
            font-size: 16px;
        }

        .modifications-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e1e1e1;
        }

        .modifications-title {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .modification-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
            padding: 5px 0;
        }

        .modification-item i {
            color: #3498db;
        }

        .buttons {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #2c3e50;
            border: 1px solid #e1e1e1;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .email-notice {
            margin-top: 20px;
            padding: 15px;
            background: #e8f4f8;
            border-radius: 10px;
            color: #2c3e50;
        }

        .email-notice i {
            color: #3498db;
            margin-right: 5px;
        }

        .payment-info {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            text-align: left;
        }

        .payment-info h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .card-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
        }

        .card-icon {
            width: 40px;
            height: 25px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <?php include 'includes/header2.php'; ?>

    <div class="success-page">
        <div class="success-container">
            <h1>Köszönjük a vásárlást!</h1>
            
            <div class="subscription-details">
                <h3>Előfizetés adatai</h3>
                <div class="detail-row">
                    <span>Cég:</span>
                    <strong><?php echo htmlspecialchars($subscription['company_name']); ?></strong>
                </div>
                
                <div class="package-details">
                    <div class="package-title">
                        <?php 
                        $planName = strtolower($subscription['plan_name']);
                        $displayName = str_replace('_eves', '', $planName);
                        // Ékezetes csomagnevek javítása
                        $csomagNevek = [
                            'alap' => 'Alap',
                            'kozepes' => 'Közepes',
                            'uzleti' => 'Üzleti'
                        ];
                        $csomagLabel = isset($csomagNevek[$displayName]) ? $csomagNevek[$displayName] : ucfirst($displayName);
                        echo $csomagLabel . ' csomag (' . $subscription['billing_interval'] . ')';
                        ?>
                    </div>
                    <div class="package-content">
                        <?php
                        // Az eredeti csomag tartalmának megjelenítése
                        $description = $subscription['plan_description'];
                        preg_match_all('/(\d+)\s+(felhasználó|eszköz)/', $description, $matches);
                        
                        if (!empty($matches[0])) {
                            foreach ($matches[0] as $match) {
                                echo '<div class="package-item"><i class="fas fa-check"></i>' . ucfirst($match) . '</div>';
                            }
                        }
                        
                        // Módosítások megjelenítése
                        if (!empty($subscription['modification_details'])) {
                            echo '<div class="modifications-section">';
                            echo '<div class="modifications-title">Testreszabott módosítások</div>';
                            
                            $modificationText = $subscription['modification_details'];
                            if (strpos($modificationText, 'Csomag testreszabása:') !== false) {
                                $parts = explode(':', $modificationText, 2);
                                $modifications = explode(',', trim($parts[1]));
                                
                                // Az eredeti értékek kinyerése
                                $originalUsers = 0;
                                $originalDevices = 0;
                                foreach ($matches[0] as $match) {
                                    if (strpos($match, 'felhasználó') !== false) {
                                        $originalUsers = (int)filter_var($match, FILTER_SANITIZE_NUMBER_INT);
                                    }
                                    if (strpos($match, 'eszköz') !== false) {
                                        $originalDevices = (int)filter_var($match, FILTER_SANITIZE_NUMBER_INT);
                                    }
                                }
                                
                                foreach ($modifications as $mod) {
                                    $mod = trim($mod);
                                    // Különbség kiszámítása
                                    if (strpos($mod, 'felhasználó') !== false) {
                                        $newUsers = (int)filter_var($mod, FILTER_SANITIZE_NUMBER_INT);
                                        $userDiff = $newUsers - $originalUsers;
                                        if ($userDiff > 0) {
                                            echo '<div class="modification-item"><i class="fas fa-plus-circle"></i>' . $mod . ' (+' . $userDiff . ' felhasználó)</div>';
                                        } else {
                                            echo '<div class="modification-item"><i class="fas fa-check-circle"></i>' . $mod . ' (nem történt változás)</div>';
                                        }
                                    }
                                    if (strpos($mod, 'eszköz') !== false) {
                                        $newDevices = (int)filter_var($mod, FILTER_SANITIZE_NUMBER_INT);
                                        $deviceDiff = $newDevices - $originalDevices;
                                        if ($deviceDiff > 0) {
                                            echo '<div class="modification-item"><i class="fas fa-plus-circle"></i>' . $mod . ' (+' . $deviceDiff . ' eszköz)</div>';
                                        } else {
                                            echo '<div class="modification-item"><i class="fas fa-check-circle"></i>' . $mod . ' (nem történt változás)</div>';
                                        }
                                    }
                                }
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <?php if (strpos($subscription['plan_name'], '_eves') !== false): ?>
                    <div class="detail-row">
                    <span>Kezdés:</span>
                    <strong><?php echo date('Y.m.d', strtotime($subscription['start_date'])); ?></strong>
                </div>
                <div class="detail-row">
                    <span>Lejárat:</span>
                    <strong><?php echo date('Y.m.d', strtotime($subscription['end_date'])); ?></strong>
                </div>
                    <div class="detail-row">
                    <span>Eredeti ár (éves):</span>
                    <strong style="text-decoration: line-through;"><?php echo number_format($subscription['actual_price'] / 0.85, 0, '.', ' '); ?> Ft</strong>
                </div>
                <div class="detail-row">
                    <span>Megtakarítás (15%):</span>
                    <strong style="color: #2ecc71;"><?php echo number_format($subscription['actual_price'] / 0.85 - $subscription['actual_price'], 0, '.', ' '); ?> Ft</strong>
                </div>
                <?php endif; ?>
                <hr>
                <div class="detail-row">
                    <span>Összeg:</span>
                    <strong><?php echo number_format($subscription['actual_price'], 0, '.', ' '); ?> Ft</strong>
                </div>
            </div>

            <div class="payment-info">
                <h4>Fizetési információk</h4>
                <div class="card-info">
                    <div class="card-icon">
                        <?php 
                        $cardType = strtolower($subscription['card_type']);
                        if ($cardType === 'visa') echo '💳';
                        elseif ($cardType === 'mastercard') echo '💳';
                        else echo '💳';
                        ?>
                    </div>
                    <span><?php echo htmlspecialchars($subscription['card_type']); ?> kártya (**** <?php echo htmlspecialchars($subscription['last_four_digits']); ?>)</span>
                </div>
            </div>

            <div class="email-notice">
                <i class="fas fa-envelope"></i>
                A részletes előfizetési adatokat elküldtük az email címére.
            </div>

            <div class="buttons">
                <a href="home.php" class="btn btn-primary">Vissza a főoldalra</a>
                <a href="bill.php?id=<?php echo $subscription_id; ?>" class="btn btn-secondary">Számla megtekintése</a>
            </div>
        </div>
    </div>

    <?php include 'includes/footer2.php'; ?>
</body>
</html> 