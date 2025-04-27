<?php
// Session is already started in config.php
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="assets/img/monitor.png">
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <title>VibeCore - Kezdőlap</title>
</head>
<body>
    <?php include 'includes/accept_cookies.php'; ?>
    <?php include 'includes/header2.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>VibeCore</h1>
            <h2>Professzionális Eszközkezelési Rendszer</h2>
            <p>Hatékony megoldások rendezvénytechnikai cégek számára</p>
            <div class="hero-buttons">
                <a href="auth/register.php" class="btn btn-primary">Próbálja ki ingyen</a>
                <a href="szolgaltatasok.php" class="btn btn-secondary">Tudjon meg többet</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="section-title">
            <h2>Miért válassza a VibeCore rendszert?</h2>
            <p>Egyszerűsítse vállalkozása működését modern eszközkezelési megoldásunkkal</p>
        </div>
        <div class="features-grid">
            <div class="feature-card" data-aos="fade-up">
                <div class="feature-icon">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h3>QR Kód Alapú Követés</h3>
                <p>Egyedi QR kódokkal azonosíthatja és követheti eszközeit, így mindig naprakész információkkal rendelkezik azok állapotáról és helyzetéről.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3>Projektmenedzsment</h3>
                <p>Kezelje hatékonyan rendezvényeit és projektjeit, tervezze meg az eszközök felhasználását és kövesse nyomon a folyamatokat.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <h3>Karbantartás Követés</h3>
                <p>Tartsa nyilván eszközei karbantartási igényeit, ütemezze a szervizeléseket és dokumentálja az elvégzett munkákat.</p>
            </div>
            <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Részletes Riportok</h3>
                <p>Készítsen átfogó kimutatásokat eszközei kihasználtságáról, a projektek állapotáról és a karbantartási munkákról.</p>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="benefits">
        <div class="section-title">
            <h2>Előnyök</h2>
            <p>A VibeCore rendszer használatával jelentős előnyökre tehet szert</p>
        </div>
        <div class="benefits-container">
            <div class="benefit-row" data-aos="fade-right" data-aos-duration="1000" data-aos-offset="300">
                <div class="benefit-text">
                    <h3>Hatékonyabb Működés</h3>
                    <div class="benefit-list">
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-robot"></i>
                            </div>
                            <div class="benefit-detail">
                                <h4>Automatizált eszközkövetés</h4>
                                <p>Automatikus nyilvántartás és követés minden eszközéről</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="benefit-detail">
                                <h4>Gyors eszközazonosítás</h4>
                                <p>Azonnali és pontos eszközazonosítás QR kódokkal</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="benefit-detail">
                                <h4>Egyszerűsített adminisztráció</h4>
                                <p>Papírmentes, gyors és hatékony adminisztrációs folyamatok</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="benefit-detail">
                                <h4>Időmegtakarítás</h4>
                                <p>Jelentős időmegtakarítás az automatizált folyamatoknak köszönhetően</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="benefit-image">
                    <img src="assets/img/hatékonyság.png" alt="Hatékonyság" class="floating-image">
                </div>
            </div>
            
            <div class="benefit-row" data-aos="fade-left" data-aos-duration="1000" data-aos-offset="300">
                <div class="benefit-text">
                    <h3>Teljes Kontroll</h3>
                    <div class="benefit-list">
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-satellite"></i>
                            </div>
                            <div class="benefit-detail">
                                <h4>Valós idejű eszközkövetés</h4>
                                <p>Folyamatos, valós idejű információk eszközei helyzetéről</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                            <div class="benefit-detail">
                                <h4>Átlátható projektmenedzsment</h4>
                                <p>Könnyen kezelhető, átlátható projekttervezés és követés</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="benefit-detail">
                                <h4>Naprakész leltár</h4>
                                <p>Mindig aktuális készletinformációk és leltárjelentések</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="benefit-detail">
                                <h4>Részletes statisztikák</h4>
                                <p>Átfogó elemzések és kimutatások az üzleti döntésekhez</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="benefit-image">
                    <img src="assets/img/kontroll.png" alt="Kontroll" class="floating-image">
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="cta-content">
            <h2>Készen áll a hatékonyabb működésre?</h2>
            <p>Regisztráljon most és próbálja ki rendszerünket 14 napig ingyenesen!</p>
            <a href="auth/register.php" class="btn btn-primary">Ingyenes próba</a>
        </div>
    </section>

    <?php include 'includes/footer2.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        // AOS inicializálása
        AOS.init({
            duration: 1000,
            once: true
        });
    </script>

    <style>
    /* Általános stílusok */
    body {
        font-family: 'Arial', sans-serif;
    }

    .section-header {
        text-align: center;
        margin-bottom: 50px;
    }

    .section-header h2 {
        color: #2c3e50;
        font-size: 2.5em;
        margin-bottom: 20px;
    }

    .section-header p {
        color: #666;
        font-size: 1.2em;
    }

    /* Hero Section */
    .hero {
        background: linear-gradient(135deg, #2c3e50, #2c3e50);
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        color: white;
        text-align: center;
        padding: 180px 20px;
        position: relative;
        overflow: hidden;
        margin-top: 60px;
    }

    .hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTQ4MCIgaGVpZ2h0PSI2NTAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+ICAgIDxwYXRoIGQ9Ik03MzEuMjA3IDY0OS44MDJDOTM1LjQ4NCA2NDkuODAyIDExMDIuMjcgNTA1LjM2MSAxMTAyLjI3IDMyNi4wODFDMTEwMi4yNyAxNDYuODAxIDkzNS40ODQgMi4zNTk4NiA3MzEuMjA3IDIuMzU5ODZDNTI2LjkzIDIuMzU5ODYgMzYwLjE0NCAxNDYuODAxIDM2MC4xNDQgMzI2LjA4MUMzNjAuMTQ0IDUwNS4zNjEgNTI2LjkzIDY0OS44MDIgNzMxLjIwNyA2NDkuODAyWiIgZmlsbD0id2hpdGUiIGZpbGwtb3BhY2l0eT0iMC4wMiIvPjwvc3ZnPg==') no-repeat center center;
        opacity: 0.1;
    }

    .hero-content {
        max-width: 900px;
        margin: 0 auto;
        position: relative;
        z-index: 1;
    }

    .hero h1 {
        font-size: 4.5em;
        margin-bottom: 20px;
        font-weight: 700;
        letter-spacing: 2px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        animation: fadeInDown 1s ease-out;
    }

    .hero h2 {
        font-size: 2.5em;
        margin-bottom: 30px;
        font-weight: 300;
        color: rgba(255, 255, 255, 0.95);
        animation: fadeInUp 1s ease-out 0.3s;
        animation-fill-mode: both;
    }

    .hero p {
        font-size: 1.3em;
        margin-bottom: 40px;
        color: rgba(255, 255, 255, 0.9);
        line-height: 1.6;
        animation: fadeInUp 1s ease-out 0.6s;
        animation-fill-mode: both;
    }

    .hero-buttons {
        display: flex;
        gap: 20px;
        justify-content: center;
        animation: fadeInUp 1s ease-out 0.9s;
        animation-fill-mode: both;
    }

    .hero .btn {
        padding: 15px 35px;
        font-size: 1.1em;
        border-radius: 30px;
        text-decoration: none;
        transition: all 0.3s ease;
        font-weight: 500;
        letter-spacing: 0.5px;
    }

    .hero .btn-primary {
        background: #3498db;
        color: white;
        border: 2px solid transparent;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
    }

    .hero .btn-primary:hover {
        background: #2980b9;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(52, 152, 219, 0.6);
    }

    .hero .btn-secondary {
        background: transparent;
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.9);
    }

    .hero .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateY(-2px);
    }

    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 768px) {
        .hero {
            padding: 120px 20px;
        }

        .hero h1 {
            font-size: 3em;
        }

        .hero h2 {
            font-size: 1.8em;
        }

        .hero p {
            font-size: 1.1em;
        }

        .hero-buttons {
            flex-direction: column;
            gap: 15px;
            align-items: center;
        }

        .hero .btn {
            width: 80%;
            text-align: center;
        }
    }

    /* Features Section */
    .features {
        padding: 80px 0;
        background: white;
    }

    .features-grid {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 30px;
        padding: 20px;
    }

    .feature-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(44, 62, 80, 0.08);
        overflow: hidden;
        transition: all 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(44, 62, 80, 0.15);
    }

    .feature-icon {
        background: #2c3e50;
        color: white;
        padding: 40px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .feature-card:hover .feature-icon {
        background: #3498db;
    }

    .feature-icon i {
        font-size: 2.5em;
    }

    .feature-content {
        padding: 25px;
        text-align: center;
    }

    .feature-card h3 {
        color: #2c3e50;
        font-size: 1.5em;
        margin: 20px 0;
        text-align: center;
    }

    .feature-card p {
        color: #666;
        line-height: 1.6;
        margin: 0;
        padding: 0 25px 25px;
        text-align: center;
    }

    @media (max-width: 768px) {
        .features-grid {
            grid-template-columns: 1fr;
            padding: 20px;
            gap: 20px;
        }

        .feature-icon {
            padding: 30px;
        }

        .feature-content {
            padding: 20px;
        }

        .feature-card p {
            padding: 0 15px 20px;
        }
    }

    /* Benefits Section */
    .benefits {
        padding: 80px 0;
        background: white;
        overflow: hidden;
        width: 100%;
    }

    .benefits-container {
        max-width: 1800px;
        margin: 0 auto;
        padding: 0 220px;
        position: relative;
        overflow-x: visible;
    }

    .benefit-row {
        display: flex;
        align-items: flex-start;
        gap: 60px;
        margin-bottom: 80px;
        background: white;
        border-radius: 15px;
        padding: 40px;
        box-shadow: 0 5px 20px rgba(44, 62, 80, 0.08);
        width: 85%;
        position: relative;
        transition: all 0.3s ease;
    }

    .benefit-row:hover {
        box-shadow: 0 8px 30px rgba(44, 62, 80, 0.12);
        transform: translateY(-2px);
    }

    /* First benefit row (Hatékonyabb Működés) */
    .benefit-row:first-child {
        margin-left: -200px;
        margin-right: auto;
    }

    /* Second benefit row (Teljes Kontroll) */
    .benefit-row:last-child {
        margin-left: auto;
        margin-right: -200px;
        flex-direction: row-reverse;
    }

    @media (max-width: 1800px) {
        .benefits-container {
            padding: 0 180px;
        }
    }

    @media (max-width: 1600px) {
        .benefits-container {
            padding: 0 140px;
        }
        .benefit-row:first-child {
            margin-left: -100px;
        }
        .benefit-row:last-child {
            margin-right: -100px;
        }
    }

    @media (max-width: 1400px) {
        .benefits-container {
            padding: 0 100px;
        }
    }

    @media (max-width: 1200px) {
        .benefits-container {
            padding: 0 40px;
        }
        .benefit-row:first-child {
            margin-left: 0;
        }
        .benefit-row:last-child {
            margin-right: 0;
        }
    }

    @media (max-width: 992px) {
        .benefits-container {
            padding: 0 20px;
        }
        .benefit-row,
        .benefit-row:first-child,
        .benefit-row:last-child {
            flex-direction: column;
            gap: 30px;
            padding: 30px;
            width: 100%;
            margin: 0 auto 40px auto;
        }
    }

    .benefit-text {
        flex: 1;
    }

    .benefit-text h3 {
        color: #2c3e50;
        font-size: 2em;
        margin-bottom: 30px;
        font-weight: 600;
        position: relative;
    }

    .benefit-text h3:after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        width: 60px;
        height: 4px;
        background: #3498db;
        border-radius: 2px;
    }

    .benefit-list {
        display: grid;
        gap: 20px;
    }

    .benefit-item {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 10px;
        transition: transform 0.3s ease;
    }

    .benefit-item:hover {
        transform: translateY(-3px);
    }

    .benefit-icon {
        background: #ebf5ff;
        color: #3498db;
        width: 45px;
        height: 45px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3em;
        flex-shrink: 0;
    }

    .benefit-detail {
        flex: 1;
    }

    .benefit-detail h4 {
        color: #2c3e50;
        font-size: 1.1em;
        margin-bottom: 5px;
        font-weight: 600;
    }

    .benefit-detail p {
        color: #666;
        font-size: 0.95em;
        line-height: 1.5;
        margin: 0;
    }

    .benefit-image {
        flex: 0 0 400px;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .floating-image {
        max-width: 100%;
        height: auto;
        border-radius: 15px;
        transition: transform 0.3s ease;
    }

    .floating-image:hover {
        transform: translateY(-5px);
    }

    /* CTA Section */
    .cta {
        padding: 80px 20px;
        background: #2c3e50;
        color: white;
    }

    .cta-content {
        max-width: 800px;
        margin: 0 auto;
        text-align: center;
    }

    .cta-content h2 {
        font-size: 2.5em;
        margin-bottom: 20px;
    }

    .cta-content p {
        font-size: 1.2em;
        margin-bottom: 30px;
        color: #ecf0f1;
    }

    .cta .btn-primary {
        background: linear-gradient(45deg, #3498db, #2980b9);
        color: white;
        padding: 18px 45px;
        font-size: 1.3em;
        border-radius: 30px;
        text-decoration: none;
        position: relative;
        display: inline-block;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 0 25px rgba(52, 152, 219, 0.7);
        animation: glowing 1.5s infinite, shimmer 2s infinite;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    @keyframes glowing {
        0% {
            box-shadow: 0 0 25px rgba(52, 152, 219, 0.7),
                        0 0 40px rgba(41, 128, 185, 0.3);
        }
        50% {
            box-shadow: 0 0 35px rgba(52, 152, 219, 0.9),
                        0 0 50px rgba(41, 128, 185, 0.5);
        }
        100% {
            box-shadow: 0 0 25px rgba(52, 152, 219, 0.7),
                        0 0 40px rgba(41, 128, 185, 0.3);
        }
    }

    @keyframes shimmer {
        0% {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }
        25% {
            background: linear-gradient(45deg, #2980b9, #3498db);
        }
        50% {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }
        75% {
            background: linear-gradient(45deg, #2980b9, #3498db);
        }
        100% {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }
    }

    .cta .btn-primary:before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: rgba(255, 255, 255, 0.2);
        transform: rotate(45deg);
        animation: shine 2s infinite;
    }

    @keyframes shine {
        0% {
            transform: rotate(45deg) translateX(-100%);
            opacity: 0.6;
        }
        50% {
            opacity: 0.9;
        }
        100% {
            transform: rotate(45deg) translateX(100%);
            opacity: 0.6;
        }
    }

    .cta .btn-primary:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 0 50px rgba(52, 152, 219, 0.9),
                    0 0 30px rgba(41, 128, 185, 0.6);
        background: linear-gradient(45deg, #2980b9, #3498db);
    }

    /* Reszponzív design */
    @media (max-width: 768px) {
        .hero {
            padding: 100px 20px;
        }

        .hero h1 {
            font-size: 2em;
        }

        .hero h2 {
            font-size: 1.5em;
        }

        .features-grid {
            grid-template-columns: 1fr;
        }

        .benefits-container {
            flex-direction: column;
        }

        .benefit-item {
            flex-direction: column;
            text-align: center;
            align-items: center;
        }

        .benefit-icon {
            margin-bottom: 10px;
        }

        .floating-image {
            max-width: 100%;
        }
    }

    @media (max-width: 992px) {
        .benefit-row,
        .benefit-row:first-child,
        .benefit-row:last-child {
            flex-direction: column;
            gap: 30px;
            padding: 30px;
            width: 100%;
            margin: 0 auto 40px auto;
        }
        
        .benefit-text h3 {
            text-align: center;
            font-size: 1.8em;
        }

        .benefit-text h3:after {
            left: 50%;
            transform: translateX(-50%);
        }

        .benefit-image {
            flex: 0 0 auto;
            width: 100%;
            order: -1;
        }

        .floating-image {
            max-width: 80%;
        }

        .benefit-item {
            padding: 12px;
        }
    }

    .section-title {
        text-align: center;
        margin-bottom: 50px;
        padding: 0 20px;
    }

    .section-title h2 {
        color: #2c3e50;
        font-size: 2.5em;
        margin-bottom: 20px;
        font-weight: 600;
    }

    .section-title p {
        color: #666;
        font-size: 1.2em;
        max-width: 800px;
        margin: 0 auto;
        line-height: 1.6;
    }
    </style>
</body>
</html>