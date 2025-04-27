<?php require_once 'includes/config.php';

// Ár lekérése a GET paraméterből
$ar = isset($_GET['ar']) ? (int)$_GET['ar'] : 0;
$kozlemeny = 'TS-' . date('Ymd') . rand(1000,9999);

// IBAN szám beállítása
$ibanNumber = "HU92117734180088534000000000";

// OTP SmartBank deeplink URL generálása egyszerűbb formátumban
$otpDeepLink = "otpmobilebank://transfer?to=" . urlencode($ibanNumber) . 
               "&amount=" . number_format($ar, 2, ".", "") . 
               "&currency=HUF";

// QR kód generálása az OTP deeplink URL-lel
$initialQrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($otpDeepLink);

// AJAX kérés kezelése
if (isset($_POST['generateQR'])) {
    $kozlemeny = $_POST['kozlemeny'];
    
    // OTP SmartBank deeplink URL generálása egyszerűbb formátumban
    $otpDeepLink = "otpmobilebank://transfer?to=" . urlencode($ibanNumber) . 
                   "&amount=" . number_format($ar, 2, ".", "") . 
                   "&currency=HUF";
    
    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($otpDeepLink);
    echo json_encode(['qrCodeUrl' => $qrCodeUrl, 'kozlemeny' => $kozlemeny]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banki átutalás - VibeCore</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <style>
        .transfer-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            padding: 20px;
        }

        .transfer-container {
            background: #fff;
            width: 650px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            margin-top: 84px;
            height: 785px;
            position: relative;
        }

        .transfer-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .transfer-header h2 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .transfer-details {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 85px;
        }

        .transfer-content {
            display: flex;
            gap: 30px;
            margin-bottom: 20px;
        }

        .transfer-info {
            flex: 1;
        }

        .transfer-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .transfer-item:last-child {
            border-bottom: none;
        }

        .transfer-label {
            color: #666;
            font-weight: 500;
        }

        .transfer-value {
            color: #2c3e50;
            font-weight: 600;
            font-family: monospace;
            font-size: 1.1em;
        }

        .transfer-note {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.9em;
        }

        .auto-redirect {
            position: absolute;
            bottom: 12px;
            left: 0;
            right: 0;
            text-align: center;
            color: #666;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        #countdown {
            font-weight: bold;
            color: #3498db;
        }

        .back-button {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            position: absolute;
            bottom: 55px;
            left: 50%;
            transform: translateX(-50%);
            transition: background 0.3s ease;
            font-size: 14px;
            z-index: 1;
        }

        .back-button:hover {
            background: #2980b9;
        }

        .qr-code-section {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .qr-code-section img {
            width: 200px;
            height: 200px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            padding: 10px;
            border-radius: 10px;
            background: white;
        }

        .qr-code-section p {
            color: #666;
            font-size: 14px;
            margin: 0 0 10px 0;
            max-width: 200px;
            line-height: 1.4;
            margin: 0 auto;
        }

        .qr-code-ref {
            display: block;
            margin-top: 10px;
            color: #888;
            font-size: 12px;
        }

        /* Reszponzív igazítások */
        @media (max-width: 768px) {
            .transfer-content {
                flex-direction: column;
            }
            
            .qr-code-section {
                margin-top: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header2.php'; ?>

    <section class="transfer-page">
        <div class="transfer-container">
            <div class="transfer-header">
                <h2>Banki átutalás információk</h2>
                <p>Kérjük, használja az alábbi adatokat az átutaláshoz</p>
            </div>

            <div class="transfer-details">
                <div class="transfer-content">
                    <div class="transfer-info">
                        <div class="transfer-item">
                            <span class="transfer-label">Kedvezményezett neve:</span>
                            <span class="transfer-value">VibeCore Kft.</span>
                        </div>
                        <div class="transfer-item">
                            <span class="transfer-label">Bankszámlaszám (IBAN):</span>
                            <span class="transfer-value"><br><?php echo $ibanNumber; ?></span>
                        </div>
                        <div class="transfer-item">
                            <span class="transfer-label">Bank neve:</span>
                            <span class="transfer-value">OTP Bank</span>
                        </div>
                        <div class="transfer-item">
                            <span class="transfer-label">Összeg:</span>
                            <span class="transfer-value"><?php echo $ar; ?> Ft</span>
                        </div>
                    </div>
                    
                    <div class="qr-code-section">
                        <img id="qrCodeImage" src="<?php echo $initialQrCodeUrl; ?>" alt="QR kód a banki átutaláshoz">
                        <p>Olvassa be a QR kódot az OTP SmartBank alkalmazással a gyors átutaláshoz</p>
                    </div>
                </div>
                
                <div class="transfer-note">
                    <strong>Fontos:</strong> Kérjük, a közlemény rovatban pontosan tüntesse fel a fenti azonosítót, 
                    hogy megrendelését azonosítani tudjuk. Az átutalás beérkezése után aktiváljuk szolgáltatását.
                </div>
            </div>

            <div style="text-align: center;">
                <a href="home.php" class="back-button">Vissza a főoldalra</a>
            </div>
        </div>
    </section>

    <script>
        function generateQRCode() {
            const kozlemeny = 'TS-' + new Date().toISOString().slice(0,8).replace(/-/g,'') + Math.floor(Math.random() * 9000 + 1000);
            
            fetch('bank_transfer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'generateQR=1&kozlemeny=' + encodeURIComponent(kozlemeny)
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('qrCodeImage').src = data.qrCodeUrl;
                document.querySelector('.transfer-value.kozlemeny').textContent = data.kozlemeny;
            })
            .catch(error => console.error('Hiba:', error));
        }

        // Oldal betöltésekor azonnal generáljuk a QR kódot
        document.addEventListener('DOMContentLoaded', generateQRCode);

        // QR kód frissítése 5 percenként
        setInterval(generateQRCode, 300000);
    </script>

    <?php include 'includes/footer2.php'; ?>
</body>
</html>