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
if (!isset($_GET['id'])) {
    header('Location: home.php');
    exit;
}

// Számla adatainak lekérése
$subscription_id = $_GET['id'];
$stmt = $conn->prepare("SELECT s.*, sp.name as plan_name, sp.description as plan_description, 
                              ph.amount as actual_price, ph.payment_date,
                              c.company_name, c.company_address, c.company_email, c.company_telephone,
                              u.firstname, u.lastname, u.email,
                              pm.card_type, pm.last_four_digits,
                              bi.name as billing_interval,
                              sp.price as base_price,
                              ph.transaction_id,
                              (SELECT modification_reason FROM subscription_modifications 
                               WHERE subscription_id = s.id 
                               ORDER BY modification_date DESC LIMIT 1) as modification_reason
                       FROM subscriptions s 
                       JOIN subscription_plans sp ON s.subscription_plan_id = sp.id 
                       JOIN company c ON s.company_id = c.id 
                       JOIN user u ON s.user_id = u.id
                       JOIN payment_methods pm ON s.payment_method_id = pm.id
                       JOIN payment_history ph ON ph.subscription_id = s.id
                       JOIN billing_intervals bi ON sp.billing_interval_id = bi.id
                       WHERE s.id = ? AND s.user_id = ?
                       ORDER BY ph.payment_date DESC LIMIT 1");

$stmt->execute([$subscription_id, $_SESSION['user_id']]);
$bill_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bill_data) {
    header('Location: home.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Számla</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <style>
        .invoice-page {
            min-height: 100vh;
            background-color: #f8f9fa;
            padding: 40px 20px;
        }

        .invoice-container {
            position: relative;
            background: white;
            max-width: 800px;
            margin: 75px auto 20px;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            background-image: url('admin/VIBECORE.png');
            background-size: 80%;
            background-position: center 70%;
            background-repeat: no-repeat;
            overflow: hidden;
        }

        .invoice-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            z-index: 1;
        }

        .invoice-header,
        .customer-details,
        .subscription-details,
        .payment-info,
        .print-button {
            position: relative;
            z-index: 2;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }

        .company-info {
            flex: 1;
        }

        .invoice-details {
            text-align: right;
        }

        .invoice-title {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .customer-details {
            margin-bottom: 40px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .subscription-details {
            margin-bottom: 40px;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .detail-table th,
        .detail-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .detail-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        /* Fix oszlopszélességek beállítása */
        .detail-table th:nth-child(1),
        .detail-table td:nth-child(1) {
            width: 25%;
        }

        .detail-table th:nth-child(2),
        .detail-table td:nth-child(2) {
            width: 15%;
        }

        .detail-table th:nth-child(3),
        .detail-table td:nth-child(3) {
            width: 15%;
            text-align: right;
        }

        .detail-table th:nth-child(4),
        .detail-table td:nth-child(4) {
            width: 30%;
        }

        .detail-table th:nth-child(5),
        .detail-table td:nth-child(5) {
            width: 15%;
            text-align: right;
        }

        /* Módosítások oszlop tartalmának igazítása */
        .detail-table td:nth-child(4) {
            padding-right: 20px;
        }

        .price-summary {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #eee;
            text-align: right;
        }

        .total-price {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin-top: 10px;
        }

        .payment-info {
            margin-top: 40px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .payment-info h3 {
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .modifications {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .print-button {
            display: inline-block;
            padding: 12px 25px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .print-button:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        @media print {
            body > *:not(.invoice-page) {
                display: none !important;
            }

            .invoice-page {
                padding: 0;
                margin: 0;
                background: none;
                page-break-after: avoid;
            }

            .invoice-container {
                box-shadow: none;
                margin: 0;
                padding: 20px;
                background-image: url('admin/VIBECORE.png') !important;
                background-size: 80% !important;
                background-position: center 70% !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                page-break-inside: avoid;
            }

            .invoice-container::before {
                background: rgba(255, 255, 255, 0.97) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .print-button {
                display: none;
            }

            header, footer {
                display: none !important;
            }

            /* Hide URL at the bottom */
            @page {
                margin-bottom: 0;
            }
            
            /* Remove URL display */
            @page :first {
                size: auto;
                margin: 0mm;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header2.php'; ?>

    <div class="invoice-page">
        <div class="invoice-container">
            <div class="invoice-header">
                <div class="company-info">
                    <h2>VibeCore</h2>
                    <p>Budapest, Példa utca 1.</p>
                    <p>info@vibecore.hu</p>
                    <p>+36 30 123 4567</p>
                </div>
                <div class="invoice-details">
                    <h1 class="invoice-title">SZÁMLA</h1>
                    <p>Számla sorszám: INV-<?php echo str_pad($subscription_id, 6, '0', STR_PAD_LEFT); ?></p>
                    <p>Tranzakció azonosító: <?php echo $bill_data['transaction_id']; ?></p>
                </div>
            </div>

            <div class="customer-details">
                <h3>Vevő adatai</h3>
                <p><strong>Cégnév:</strong> <?php echo htmlspecialchars($bill_data['company_name']); ?></p>
                <p><strong>Cím:</strong> <?php echo htmlspecialchars($bill_data['company_address']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($bill_data['company_email']); ?></p>
                <p><strong>Telefon:</strong> <?php echo htmlspecialchars($bill_data['company_telephone']); ?></p>
            </div>

            <div class="subscription-details">
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>Szolgáltatás</th>
                            <th>Időszak</th>
                            <th>Alapár</th>
                            <th>Módosítások</th>
                            <th>Végösszeg</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <?php 
                                $planName = strtolower($bill_data['plan_name']);
                                $displayName = str_replace('_eves', '', $planName);
                                echo ucfirst($displayName) . ' csomag';
                                ?>
                            </td>
                            <td><?php echo $bill_data['billing_interval']; ?></td>
                            <td><?php echo number_format($bill_data['base_price'], 0, '.', ' '); ?> Ft</td>
                            <td>
                                <?php
                                // Az eredeti csomag tartalmának kinyerése
                                $description = $bill_data['plan_description'];
                                preg_match_all('/(\d+)\s+(felhasználó|eszköz)/', $description, $matches);
                                if (!empty($matches[0])) {
                                    echo "<div style='margin-bottom: 10px;'><strong>Eredeti csomag:</strong></div>";
                                    foreach ($matches[0] as $match) {
                                        echo ucfirst($match) . "<br>";
                                    }
                                }
                                // Módosítások kiírása (payment_success.php logika)
                                if (!empty($bill_data['modification_reason']) && strpos($bill_data['modification_reason'], 'Csomag testreszabása:') !== false) {
                                    $modificationText = $bill_data['modification_reason'];
                                    echo "<div style='margin-top: 10px;'><strong>Testreszabott módosítások</strong></div>";
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
                                        if (strpos($mod, 'felhasználó') !== false) {
                                            $newUsers = (int)filter_var($mod, FILTER_SANITIZE_NUMBER_INT);
                                            $userDiff = $newUsers - $originalUsers;
                                            if ($userDiff > 0) {
                                                echo '<div style="color:#3498db; margin-bottom:2px;"><i class="fas fa-plus-circle"></i> ' . htmlspecialchars($mod) . ' (+' . $userDiff . ' felhasználó)</div>';
                                            } else {
                                                echo '<div style="color:#2ecc71; margin-bottom:2px;"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($mod) . ' (nem történt változás)</div>';
                                            }
                                        }
                                        if (strpos($mod, 'eszköz') !== false) {
                                            $newDevices = (int)filter_var($mod, FILTER_SANITIZE_NUMBER_INT);
                                            $deviceDiff = $newDevices - $originalDevices;
                                            if ($deviceDiff > 0) {
                                                echo '<div style="color:#3498db; margin-bottom:2px;"><i class="fas fa-plus-circle"></i> ' . htmlspecialchars($mod) . ' (+' . $deviceDiff . ' eszköz)</div>';
                                            } else {
                                                echo '<div style="color:#2ecc71; margin-bottom:2px;"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($mod) . ' (nem történt változás)</div>';
                                            }
                                        }
                                    }
                                } else if (!empty($bill_data['modification_reason'])) {
                                    // Ha van modification_reason, de nem a megszokott formátum, akkor is jelenjen meg
                                    echo nl2br(htmlspecialchars($bill_data['modification_reason']));
                                } else {
                                    echo "Nincs módosítás";
                                }
                                ?>
                            </td>
                            <td><?php echo number_format($bill_data['actual_price'], 0, '.', ' '); ?> Ft</td>
                        </tr>
                    </tbody>
                </table>

                <div class="price-summary">
                    <?php
                    // Ellenőrizzük, hogy történt-e tényleges módosítás a felhasználók vagy eszközök számában
                    $has_real_modifications = false;
                    if (!empty($bill_data['modification_reason'])) {
                        $modificationText = $bill_data['modification_reason'];
                        if (strpos($modificationText, 'Csomag testreszabása:') !== false) {
                            $parts = explode(':', $modificationText, 2);
                            $modifications = explode(',', trim($parts[1]));
                            
                            foreach ($modifications as $mod) {
                                $mod = trim($mod);
                                if (strpos($mod, 'felhasználó') !== false) {
                                    $newUsers = (int)filter_var($mod, FILTER_SANITIZE_NUMBER_INT);
                                    if ($newUsers != $originalUsers) {
                                        $has_real_modifications = true;
                                        break;
                                    }
                                }
                                if (strpos($mod, 'eszköz') !== false) {
                                    $newDevices = (int)filter_var($mod, FILTER_SANITIZE_NUMBER_INT);
                                    if ($newDevices != $originalDevices) {
                                        $has_real_modifications = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    $modification_fee = $bill_data['actual_price'] - $bill_data['base_price'];
                    if ($has_real_modifications && $modification_fee > 0) {
                        echo "<p>Részösszeg: " . number_format($bill_data['base_price'], 0, '.', ' ') . " Ft</p>";
                        echo "<p>Módosítások díja: " . number_format($modification_fee, 0, '.', ' ') . " Ft</p>";
                    }

                    // Éves előfizetés esetén megjelenítjük a kedvezményt
                    if (strpos($bill_data['plan_name'], '_eves') !== false) {
                        echo "<p>Eredeti ár (éves): <span style='text-decoration: line-through;'>" . 
                             number_format($bill_data['actual_price'] / 0.85, 0, '.', ' ') . " Ft</span></p>";
                        echo "<p>Megtakarítás (15%): <span style='color: #2ecc71;'>" . 
                             number_format($bill_data['actual_price'] / 0.85 - $bill_data['actual_price'], 0, '.', ' ') . " Ft</span></p>";
                    }
                    ?>
                    <div class="total-price">
                        Végösszeg: <?php echo number_format($bill_data['actual_price'], 0, '.', ' '); ?> Ft
                    </div>
                </div>
            </div>

            <div class="payment-info">
                <h3>Fizetési információk</h3>
                <p><strong>Fizetés módja:</strong> <?php echo htmlspecialchars($bill_data['card_type']); ?> kártya (**** <?php echo htmlspecialchars($bill_data['last_four_digits']); ?>)</p>
                <p><strong>Fizetés dátuma:</strong> <?php echo date('Y.m.d H:i', strtotime($bill_data['payment_date'])); ?></p>
                <p><strong>Előfizetési időszak:</strong> <?php echo date('Y.m.d', strtotime($bill_data['start_date'])); ?> - <?php echo date('Y.m.d', strtotime($bill_data['end_date'])); ?></p>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <button onclick="window.print()" class="print-button">Számla nyomtatása</button>
            </div>
        </div>
    </div>

    <?php include 'includes/footer2.php'; ?>
</body>
</html> 