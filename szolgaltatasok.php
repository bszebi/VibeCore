<?php require_once 'includes/config.php'; ?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Szolgáltatások - VibeCore</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
</head>
<body>
<?php include 'includes/header2.php'; ?> 

    <main>
        <!-- Hero Section -->
        <section class="services-hero">
            <div class="hero-content" data-aos="fade-up">
                <h1>Szolgáltatásaink</h1>
                <p>Professzionális eszközkezelési és projektmenedzsment megoldások rendezvénytechnikai cégek számára</p>
            </div>
        </section>

        <!-- Main Services Section -->
        <section class="features-section">
            <div class="section-header" data-aos="fade-up">
                <h2>Eszközkezelési és Projektmenedzsment Megoldások</h2>
                <p>Fedezze fel szolgáltatásainkat, amelyek segítenek rendezvénytechnikai eszközeinek és projektjeinek hatékony kezelésében</p>
            </div>
            
            <div class="features-container">
                <!-- Eszközkezelés -->
                <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="card-header">
                        <i class="fas fa-laptop-code"></i>
                        <h4>Eszközkezelés</h4>
                    </div>
                    <div class="card-body">
                        <p>Átfogó eszköznyilvántartási és követési rendszer rendezvénytechnikai eszközökhöz</p>
                        <ul class="features-list">
                            <li><i class="fas fa-check-circle"></i> QR kódos eszközazonosítás és nyomon követés</li>
                            <li><i class="fas fa-check-circle"></i> Részletes eszközinformációk és státuszkezelés</li>
                            <li><i class="fas fa-check-circle"></i> Karbantartási előzmények és ütemezés</li>
                            <li><i class="fas fa-check-circle"></i> Eszközfoglalási rendszer</li>
                            <li><i class="fas fa-check-circle"></i> Eszközök állapotának valós idejű követése</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Projektmenedzsment -->
                <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="card-header">
                        <i class="fas fa-tasks"></i>
                        <h4>Projektmenedzsment</h4>
                    </div>
                    <div class="card-body">
                        <p>Hatékony rendezvény- és projektkezelési rendszer</p>
                        <ul class="features-list">
                            <li><i class="fas fa-check-circle"></i> Rendezvények és projektek teljes körű kezelése</li>
                            <li><i class="fas fa-check-circle"></i> Eszközfoglalások és szállítás koordinálása</li>
                            <li><i class="fas fa-check-circle"></i> Személyzet beosztása és munkaidő követése</li>
                            <li><i class="fas fa-check-circle"></i> Helyszíni információk és követelmények kezelése</li>
                            <li><i class="fas fa-check-circle"></i> Projektdokumentáció és jelentések</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Karbantartás -->
                <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="card-header">
                        <i class="fas fa-tools"></i>
                        <h4>Karbantartáskezelés</h4>
                    </div>
                    <div class="card-body">
                        <p>Eszközkarbantartás és szervizelés nyomon követése</p>
                        <ul class="features-list">
                            <li><i class="fas fa-check-circle"></i> Karbantartási ütemterv készítése</li>
                            <li><i class="fas fa-check-circle"></i> Hibabejelentések és javítások követése</li>
                            <li><i class="fas fa-check-circle"></i> Szervizelési előzmények dokumentálása</li>
                            <li><i class="fas fa-check-circle"></i> Automatikus értesítések karbantartási igényekről</li>
                            <li><i class="fas fa-check-circle"></i> Eszközállapot statisztikák</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Additional Services -->
        <section class="additional-services">
            <div class="section-header" data-aos="fade-up">
                <h2>További Funkcióink</h2>
                <p>Integrált megoldások a hatékony működésért</p>
            </div>

            <div class="services-grid">
                <div class="service-item" data-aos="zoom-in" data-aos-delay="100">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Naptár és Időpontkezelés</h3>
                    <p>Rendezvények és karbantartások ütemezése</p>
                </div>

                <div class="service-item" data-aos="zoom-in" data-aos-delay="200">
                    <i class="fas fa-truck"></i>
                    <h3>Szállításkezelés</h3>
                    <p>Eszközszállítások tervezése és követése</p>
                </div>

                <div class="service-item" data-aos="zoom-in" data-aos-delay="300">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Leltárkezelés</h3>
                    <p>Eszközkészlet naprakész nyilvántartása</p>
                </div>

                <div class="service-item" data-aos="zoom-in" data-aos-delay="400">
                    <i class="fas fa-chart-line"></i>
                    <h3>Statisztikák és Jelentések</h3>
                    <p>Részletes kimutatások és elemzések</p>
                </div>
            </div>
        </section>

        <!-- Why Choose Us -->
        <section class="why-choose-us">
            <div class="section-header" data-aos="fade-up">
                <h2>Miért Válasszon Minket?</h2>
                <p>Tapasztalat és szakértelem egy helyen</p>
            </div>

            <div class="benefits-container">
                <div class="benefit" data-aos="fade-right">
                    <i class="fas fa-clock"></i>
                    <h4>15+ Év Tapasztalat</h4>
                    <p>Több mint 15 éves szakmai tapasztalat az IT szektorban</p>
                </div>

                <div class="benefit" data-aos="fade-right" data-aos-delay="100">
                    <i class="fas fa-users"></i>
                    <h4>Szakértő Csapat</h4>
                    <p>Magasan képzett szakemberek a szolgáltatásában</p>
                </div>

                <div class="benefit" data-aos="fade-right" data-aos-delay="200">
                    <i class="fas fa-handshake"></i>
                    <h4>Megbízhatóság</h4>
                    <p>99.9% szolgáltatási rendelkezésre állás</p>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer2.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000,
            once: true
        });
    </script>

    <style>
    .services-hero {
        background: linear-gradient(rgba(44, 62, 80, 0.9), rgba(44, 62, 80, 0.9)), url('assets/img/services-bg.jpg');
        background-size: cover;
        background-position: center;
        height: 400px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: white;
        margin-top: 60px;
    }

    .hero-content h1 {
        font-size: 3em;
        margin-bottom: 20px;
    }

    .hero-content p {
        font-size: 1.2em;
        max-width: 600px;
        margin: 0 auto;
    }

    .features-section {
        padding: 80px 20px;
        background-color: #f8f9fa;
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
        max-width: 700px;
        margin: 0 auto;
    }

    .features-container {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 30px;
        padding: 40px 0;
    }

    .feature-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .feature-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    }

    .card-header {
        background: #2c3e50;
        color: white;
        padding: 25px;
        text-align: center;
    }

    .card-header i {
        font-size: 2.5em;
        margin-bottom: 15px;
    }

    .card-body {
        padding: 30px;
    }

    .features-list {
        list-style: none;
        padding: 0;
        margin: 20px 0;
    }

    .features-list li {
        margin: 15px 0;
        display: flex;
        align-items: center;
    }

    .features-list li i {
        color: #3498db;
        margin-right: 10px;
    }

    .learn-more {
        display: inline-block;
        color: #2c3e50;
        text-decoration: none;
        font-weight: bold;
        margin-top: 20px;
        transition: color 0.3s ease;
    }

    .learn-more:hover {
        color: #3498db;
    }

    .learn-more i {
        margin-left: 5px;
        transition: transform 0.3s ease;
    }

    .learn-more:hover i {
        transform: translateX(5px);
    }

    .additional-services {
        padding: 80px 20px;
        background: white;
    }

    .services-grid {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 30px;
        padding: 40px 0;
    }

    .service-item {
        text-align: center;
        padding: 30px;
        border-radius: 10px;
        background: #f8f9fa;
        transition: transform 0.3s ease;
    }

    .service-item:hover {
        transform: translateY(-5px);
    }

    .service-item i {
        font-size: 2.5em;
        color: #2c3e50;
        margin-bottom: 20px;
    }

    .why-choose-us {
        padding: 80px 20px;
        background: #2c3e50;
        color: white;
    }

    .why-choose-us .section-header h2 {
        color: white;
    }

    .why-choose-us .section-header p {
        color: #ddd;
    }

    .benefits-container {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        padding: 40px 0;
    }

    .benefit {
        text-align: center;
        padding: 30px;
    }

    .benefit i {
        font-size: 3em;
        color: #3498db;
        margin-bottom: 20px;
    }

    @media (max-width: 768px) {
        .services-hero {
            height: 300px;
        }

        .hero-content h1 {
            font-size: 2em;
        }

        .features-container {
            grid-template-columns: 1fr;
        }

        .services-grid {
            grid-template-columns: 1fr;
        }

        .benefits-container {
            grid-template-columns: 1fr;
        }
    }
    </style>
</body>
</html>