<?php require_once '../includes/config.php'; ?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adatvédelmi Irányelvek - VibeCore</title>
    <link rel="stylesheet" href="../assets/css/home.css">
    <style>
        .privacy-hero {
            background: linear-gradient(rgba(44, 62, 80, 0.9), rgba(44, 62, 80, 0.9)), url('../assets/img/privacy-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            text-align: center;
            padding: 100px 20px;
            margin-top: 80px;
        }

        .privacy-hero h1 {
            font-size: 3em;
            margin-bottom: 20px;
        }

        .privacy-content {
            max-width: 1000px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .privacy-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .privacy-section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.8em;
        }

        .privacy-section p {
            color: #666;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .privacy-section ul {
            color: #666;
            line-height: 1.8;
            margin-bottom: 15px;
            padding-left: 20px;
        }

        @media (max-width: 768px) {
            .privacy-hero h1 {
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

    <section class="privacy-hero">
        <h1>Adatvédelmi Irányelvek</h1>
        <p>Ismerje meg, hogyan kezeljük és védjük az Ön adatait</p>
    </section>

    <div class="privacy-content">
        <div class="privacy-section">
            <h2>Adatkezelési alapelvek</h2>
            <p>A VibeCore elkötelezett felhasználói személyes adatainak védelme iránt. Az adatkezelés során betartjuk a GDPR előírásait, és biztosítjuk, hogy az adatok biztonságosan, átláthatóan és jogszabályosan legyenek kezelve.</p>
        </div>

        <div class="privacy-section">
            <h2>Milyen adatokat gyűjtünk?</h2>
            <ul>
                <li>Név és elérhetőségi adatok</li>
                <li>Számlázási információk</li>
                <li>Szolgáltatás használatával kapcsolatos adatok</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2>Az adatok felhasználása</h2>
            <p>Az összegyűjtött adatokat kizárólag a szolgáltatásaink nyújtásához és fejlesztéséhez használjuk. Az adatokat nem adjuk át harmadik félnek, kivéve, ha a jogszabályok ezt megkövetelik, vagy Ön kifejezetten hozzájárul.</p>
        </div>
    </div>

    <?php include '../includes/footer2.php'; ?>
</body>
</html> 