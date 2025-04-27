<?php require_once '../includes/config.php'; ?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ÁSZF - VibeCore</title>
    <link rel="stylesheet" href="../assets/css/home.css">
    <style>
        .terms-hero {
            background: linear-gradient(rgba(44, 62, 80, 0.9), rgba(44, 62, 80, 0.9)), url('../assets/img/terms-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            text-align: center;
            padding: 100px 20px;
            margin-top: 80px;
        }

        .terms-hero h1 {
            font-size: 3em;
            margin-bottom: 20px;
        }

        .terms-content {
            max-width: 1000px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .terms-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .terms-section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.8em;
        }

        .terms-section p {
            color: #666;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .terms-section ol {
            color: #666;
            line-height: 1.8;
            margin-bottom: 15px;
            padding-left: 20px;
        }

        @media (max-width: 768px) {
            .terms-hero h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <?php 
    $root_path = "..";
    include '../includes/header2.php'; 
    ?>

    <section class="terms-hero">
        <h1>Általános Szerződési Feltételek</h1>
        <p>Ismerje meg szolgáltatásaink használatának feltételeit</p>
    </section>

    <div class="terms-content">
        <div class="terms-section">
            <h2>1. Általános rendelkezések</h2>
            <p>Jelen Általános Szerződési Feltételek (továbbiakban: ÁSZF) tartalmazza a VibeCore szolgáltatásainak igénybevételére vonatkozó feltételeket. A szolgáltatás használatával Ön elfogadja ezeket a feltételeket.</p>
        </div>

        <div class="terms-section">
            <h2>2. Szolgáltatások</h2>
            <ol>
                <li>A szolgáltatás leírása: A VibeCore egy online platform, amely lehetővé teszi a felhasználók számára, hogy különböző szolgáltatásokat igénybe vegyenek.</li>
                <li>Szolgáltatási csomagok: Különböző csomagok állnak rendelkezésre, amelyek különböző szolgáltatásokat és árakat tartalmaznak.</li>
                <li>Árak és fizetési feltételek: Az árak a kiválasztott csomagtól függnek, és a fizetés a szolgáltatás igénybevételekor történik.</li>
            </ol>
        </div>

        <div class="terms-section">
            <h2>3. Felelősség</h2>
            <p>A VibeCore felelős a szolgáltatások minőségéért és a felhasználói adatok biztonságáért. A szolgáltató nem vállal felelősséget a felhasználók által okozott károkért, beleértve a szolgáltatás helytelen használatát vagy a jogszabályok megsértését. A felhasználók felelősek a saját adataik pontosságáért és a szolgáltatás használatával kapcsolatos jogszabályi kötelezettségeik betartásáért.</p>
        </div>

        <div class="terms-section">
            <h2>4. Felhasználói jogok és kötelezettségek</h2>
            <p>A felhasználóknak joguk van a szolgáltatás használatára, feltéve, hogy betartják az ÁSZF feltételeit. A felhasználók kötelesek a szolgáltatás használatával kapcsolatos jogszabályi kötelezettségeiket betartani, és nem jogosultak a szolgáltatás visszafejtésére vagy más felhasználók adatainak jogosulatlan hozzáférésére.</p>
        </div>

        <div class="terms-section">
            <h2>5. Adatvédelem</h2>
            <p>A VibeCore elkötelezett a felhasználói adatok védelme iránt. Az adatkezelés során betartjuk a GDPR előírásait, és biztosítjuk, hogy az adatok biztonságosan, átláthatóan és jogszabályosan legyenek kezelve.</p>
        </div>

        <div class="terms-section">
            <h2>6. Szerződés módosítása</h2>
            <p>A VibeCore fenntartja a jogot az ÁSZF módosítására. A módosításokról a felhasználókat értesítjük, és a módosítások érvénybe lépése után a szolgáltatás használatával a felhasználók elfogadják az új feltételeket.</p>
        </div>

        <div class="terms-section">
            <h2>7. Jogviták</h2>
            <p>A jogviták esetén a magyar bíróságok illetékesek. A VibeCore és a felhasználók kötelezettséget vállalnak a jogviták békés megoldására törekedni.</p>
        </div>
    </div>

    <?php include '../includes/footer2.php'; ?>
</body>
</html> 