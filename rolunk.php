<?php require_once 'includes/config.php'; ?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rólunk - VibeCore</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <style>
        .about-hero {
            background: linear-gradient(rgba(44, 62, 80, 0.9), rgba(44, 62, 80, 0.9)), url('assets/img/about-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            text-align: center;
            padding: 100px 20px;
            margin-top: 80px;
        }

        .about-hero h1 {
            font-size: 3em;
            margin-bottom: 20px;
        }

        .about-hero p {
            font-size: 1.2em;
            max-width: 800px;
            margin: 0 auto;
        }

        .about-stats {
            background: white;
            padding: 50px 20px;
        }

        .stats-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
        }

        .stat-card {
            text-align: center;
            padding: 30px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5em;
            color: #3498db;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #2c3e50;
            font-size: 1.1em;
        }

        .team-section {
            padding: 80px 20px;
            background: #f8f9fa;
        }

        .team-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title h2 {
            font-size: 2.5em;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .section-title p {
            color: #666;
            font-size: 1.1em;
            max-width: 600px;
            margin: 0 auto;
        }

        .team-grid {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 50px;
            flex-wrap: wrap;
        }

        .team-card {
            flex: 0 0 300px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .team-card:hover {
            transform: translateY(-5px);
        }

        .team-image {
            width: 100%;
            height: 380px;
            object-fit: cover;
        }

        .team-info {
            padding: 20px;
            text-align: center;
        }

        .team-name {
            font-size: 1.3em;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .team-role {
            color: #3498db;
            margin-bottom: 15px;
        }

        .values-section {
            padding: 80px 20px;
            background: white;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .value-card {
            padding: 30px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .value-card:hover {
            transform: translateY(-5px);
        }

        .value-icon {
            font-size: 2.5em;
            color: #3498db;
            margin-bottom: 20px;
        }

        .value-title {
            font-size: 1.3em;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .value-description {
            color: #666;
            line-height: 1.6;
        }

        @media (max-width: 1024px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }

            .team-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .values-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .team-grid {
                grid-template-columns: 1fr;
            }

            .values-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: 1fr;
            }

            .about-hero h1 {
                font-size: 2em;
            }
        }

        /* Küldetés és Jövőkép szekció */
        .mission-vision {
            padding: 80px 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .mission-vision-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
        }

        .mission-card, .vision-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .mission-card:hover, .vision-card:hover {
            transform: translateY(-10px);
        }

        .mission-card::before, .vision-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
        }

        .mission-card::before {
            background: #3498db;
        }

        .vision-card::before {
            background: #2ecc71;
        }

        .card-icon {
            font-size: 2.5em;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .card-title {
            font-size: 1.8em;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .card-text {
            color: #666;
            line-height: 1.8;
            font-size: 1.1em;
        }

        /* Partnerek szekció */
        .partners-section {
            padding: 80px 20px;
            background: white;
        }

        .partners-grid {
            max-width: 1200px;
            margin: 40px auto 0;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
        }

        .partner-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .partner-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .partner-logo {
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin-bottom: 15px;
            filter: grayscale(100%);
            transition: filter 0.3s ease;
        }

        .partner-card:hover .partner-logo {
            filter: grayscale(0%);
        }

        .partner-name {
            font-size: 1.2em;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .partner-description {
            color: #666;
            font-size: 0.9em;
            line-height: 1.6;
        }

        /* Reszponzív beállítások kiegészítése */
        @media (max-width: 1024px) {
            .mission-vision-container {
                grid-template-columns: 1fr;
            }

            .partners-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .partners-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header2.php'; ?>

    <section class="about-hero" data-aos="fade-down">
        <h1>Rólunk</h1>
        <p>Innovatív megoldások a rendezvénytechnikai eszközök és projektek hatékony kezeléséhez</p>
    </section>

    <section class="about-stats">
        <div class="stats-container">
            <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-number">1000+</div>
                <div class="stat-label">Kezelt eszköz</div>
            </div>
            <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-number">100+</div>
                <div class="stat-label">Aktív projekt</div>
            </div>
            <div class="stat-card" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-number">50+</div>
                <div class="stat-label">Elégedett partner</div>
            </div>
            <div class="stat-card" data-aos="fade-up" data-aos-delay="400">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Eszközkövetés</div>
            </div>
        </div>
    </section>

    <section class="values-section">
        <div class="section-title">
            <h2>Értékeink</h2>
            <p>Alapelveink, amelyek mentén szolgáltatásunkat nyújtjuk</p>
        </div>
        <div class="values-grid">
            <div class="value-card" data-aos="flip-up" data-aos-delay="100">
                <div class="value-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h3 class="value-title">Hatékonyság</h3>
                <p class="value-description">Rendszerünk egyszerűsíti és automatizálja az eszközkezelési és projektmenedzsment folyamatokat.</p>
            </div>
            <div class="value-card" data-aos="flip-up" data-aos-delay="200">
                <div class="value-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="value-title">Megbízhatóság</h3>
                <p class="value-description">Pontos és naprakész információk az eszközökről és projektekről, bármikor és bárhonnan elérhetően.</p>
            </div>
            <div class="value-card" data-aos="flip-up" data-aos-delay="300">
                <div class="value-icon">
                    <i class="fas fa-sync"></i>
                </div>
                <h3 class="value-title">Folyamatos fejlődés</h3>
                <p class="value-description">Rendszerünket folyamatosan fejlesztjük partnereink visszajelzései alapján.</p>
            </div>
        </div>
    </section>

    <section class="team-section">
        <div class="team-container">
            <div class="section-title">
                <h2>Csapatunk</h2>
                <p>Ismerje meg szakértő munkatársainkat</p>
            </div>
            <div class="team-grid">
                <div class="team-card" data-aos="zoom-in" data-aos-delay="100">
                    <img src="assets/img/Balga Sebastián.jpg" alt="Csapattag" class="team-image">
                    <div class="team-info">
                        <h3 class="team-name">Balga Sebastián</h3>
                        <div class="team-role">Fejlesztő</div>
                    </div>
                </div>
                <div class="team-card" data-aos="zoom-in" data-aos-delay="200">
                    <img src="assets/img/Hesz Milán Mihály.jpg" alt="Csapattag" class="team-image">
                    <div class="team-info">
                        <h3 class="team-name">Hesz Milán Mihály</h3>
                        <div class="team-role">Fejlesztő</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Küldetés és Jövőkép szekció -->
    <section class="mission-vision">
        <div class="section-title">
            <h2>Küldetésünk és Jövőképünk</h2>
            <p>Célunk a rendezvénytechnikai iparág digitális fejlesztése</p>
        </div>
        <div class="mission-vision-container">
            <div class="mission-card" data-aos="fade-right" data-aos-delay="100">
                <div class="card-icon">
                    <i class="fas fa-bullseye"></i>
                </div>
                <h3 class="card-title">Küldetésünk</h3>
                <p class="card-text">
                    Küldetésünk, hogy modern és felhasználóbarát eszközkezelési rendszerünkkel segítsük a rendezvénytechnikai cégek 
                    mindennapi munkáját. Célunk az adminisztráció egyszerűsítése és a hatékonyság növelése.
                </p>
            </div>
            <div class="vision-card" data-aos="fade-left" data-aos-delay="200">
                <div class="card-icon">
                    <i class="fas fa-binoculars"></i>
                </div>
                <h3 class="card-title">Jövőképünk</h3>
                <p class="card-text">
                    Szeretnénk a rendezvénytechnikai iparág vezető eszközkezelési és projektmenedzsment platformjává válni, 
                    amely valódi értéket teremt partnereink számára és hozzájárul sikerükhöz.
                </p>
            </div>
        </div>
    </section>

    <!-- Partnerek szekció -->
    <section class="partners-section">
        <div class="section-title">
            <h2>Technológiáink</h2>
            <p>Modern technológiák a megbízható működésért</p>
        </div>
        <div class="partners-grid">
            <div class="partner-card" data-aos="fade-up" data-aos-delay="100">
                <i class="fas fa-qrcode fa-3x"></i>
                <h3 class="partner-name">QR Kód Rendszer</h3>
                <p class="partner-description">Gyors és pontos eszközazonosítás</p>
            </div>
            <div class="partner-card" data-aos="fade-up" data-aos-delay="200">
                <i class="fas fa-mobile-alt fa-3x"></i>
                <h3 class="partner-name">Mobilbarát Felület</h3>
                <p class="partner-description">Bárhonnan elérhető rendszer</p>
            </div>
            <div class="partner-card" data-aos="fade-up" data-aos-delay="300">
                <i class="fas fa-database fa-3x"></i>
                <h3 class="partner-name">Biztonságos Adattárolás</h3>
                <p class="partner-description">Védett és redundáns rendszer</p>
            </div>
            <div class="partner-card" data-aos="fade-up" data-aos-delay="400">
                <i class="fas fa-clock fa-3x"></i>
                <h3 class="partner-name">Valós Idejű Követés</h3>
                <p class="partner-description">Azonnali státuszfrissítések</p>
            </div>
        </div>
    </section>

    <?php include 'includes/footer2.php'; ?>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });
    </script>
</body>
</html>