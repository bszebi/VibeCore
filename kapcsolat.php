<?php require_once 'includes/config.php'; ?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kapcsolat - VibeCore</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        .contact-page {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 80px 0;
        }

        .contact-hero {
            text-align: center;
            margin-bottom: 60px;
            opacity: 0;
            animation: fadeInDown 1s ease forwards;
        }

        .contact-hero h1 {
            font-size: 42px;
            color: white;
            margin-bottom: 15px;
            font-weight: 700;
            position: relative;
            padding-bottom: 15px;
        }

        .contact-hero h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 2px;
        }

        .contact-hero p {
            font-size: 18px;
            color: white;
            margin: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 40px;
            margin-top: 40px;
        }

        .contact-info {
            opacity: 0;
            animation: fadeInLeft 1s ease forwards;
            animation-delay: 0.3s;
        }

        .contact-info h2 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 30px;
        }

        .info-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .info-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .info-card .icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .info-card h3 {
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .info-card p {
            color: #666;
            margin: 5px 0;
        }

        .contact-form-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            opacity: 0;
            animation: fadeInRight 1s ease forwards;
            animation-delay: 0.6s;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h2 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .form-header p {
            color: #666;
        }

        .contact-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
            outline: none;
        }

        .form-group textarea {
            height: 150px;
            resize: vertical;
        }

        .submit-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .submit-btn::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: -100%;
            background: linear-gradient(90deg, rgba(255,255,255,0.2), transparent);
            transition: all 0.5s ease;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #2980b9, #3498db);
            transform: translateY(-2px);
        }

        .submit-btn:hover::after {
            left: 100%;
        }

        @media (max-width: 992px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }

            .info-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .contact-page {
                padding: 60px 0;
            }

            .info-cards {
                grid-template-columns: 1fr;
            }

            .contact-form .form-row {
                grid-template-columns: 1fr;
            }

            .contact-form-container {
                padding: 30px 20px;
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .animation-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1002;
            width: 300px;
            height: 300px;
            display: none;
        }

        .animation-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1001;
            backdrop-filter: blur(5px);
        }

        .success-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1000;
            display: none;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.5s ease-out;
        }

        .success-notification i {
            font-size: 20px;
            color: #28a745;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header2.php'; ?>

    <!-- Add success notification -->
    <div class="success-notification" id="successNotification">
        <i class="fas fa-check-circle"></i>
        <span>Az üzenet sikeresen elküldve!</span>
    </div>

    <main class="contact-page">
        <section class="contact-hero">
            <h1>Kapcsolat</h1>
            <p>Keressen minket bizalommal</p>
        </section>

        <section class="contact-content">
            <div class="container">
                <div class="contact-grid">
                    <div class="contact-info">
                        <h2>Elérhetőségeink</h2>
                        <div class="info-cards">
                            <div class="info-card">
                                <div class="icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <h3>Címünk</h3>
                                <p>1234 Budapest, Példa utca 123.</p>
                            </div>
                            <div class="info-card">
                                <div class="icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <h3>Telefonszám</h3>
                                <p>+36 1 234 5678</p>
                            </div>
                            <div class="info-card">
                                <div class="icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <h3>Email</h3>
                                <p>info@vibecore.hu</p>
                            </div>
                            <div class="info-card">
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3>Nyitvatartás</h3>
                                <p>H-P: 9:00 - 17:00</p>
                                <p>Szo-V: Zárva</p>
                            </div>
                        </div>
                    </div>

                    <div class="contact-form-container">
                        <div class="form-header">
                            <h2>Küldjön üzenetet</h2>
                            <p>Töltse ki az alábbi űrlapot és kollégáink hamarosan felkeresik</p>
                        </div>
                        <form class="contact-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">Név</label>
                                    <input type="text" id="name" name="name" placeholder="Az Ön neve" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" placeholder="Email cím" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="subject">Tárgy</label>
                                <input type="text" id="subject" name="subject" placeholder="Az üzenet tárgya" required>
                            </div>
                            <div class="form-group">
                                <label for="message">Üzenet</label>
                                <textarea id="message" name="message" placeholder="Írja le üzenetét..." required></textarea>
                            </div>
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-paper-plane"></i> Üzenet küldése
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer2.php'; ?> 

    <!-- Add animation container and overlay -->
    <div class="animation-overlay" id="animationOverlay"></div>
    <div class="animation-container" id="animationContainer"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
    <script>
        document.querySelector('.contact-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Show animation and overlay
            const animationContainer = document.getElementById('animationContainer');
            const animationOverlay = document.getElementById('animationOverlay');
            animationContainer.style.display = 'block';
            animationOverlay.style.display = 'block';
            
            // Load and play the animation
            const animation = lottie.loadAnimation({
                container: animationContainer,
                renderer: 'svg',
                loop: false,
                autoplay: true,
                path: 'assets/animation/Animation - 1741364641471.json'
            });

            try {
                const response = await fetch('process_contact.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                // Listen for animation complete
                animation.addEventListener('complete', () => {
                    // Hide animation and overlay
                    animationContainer.style.display = 'none';
                    animationOverlay.style.display = 'none';
                    animation.destroy();
                    
                    if (data.success) {
                        // Show success notification
                        const successNotification = document.getElementById('successNotification');
                        successNotification.style.display = 'flex';
                        
                        // Reset form
                        this.reset();
                        
                        // Hide notification after 3 seconds
                        setTimeout(() => {
                            successNotification.style.animation = 'slideOut 0.5s ease-out';
                            setTimeout(() => {
                                successNotification.style.display = 'none';
                                successNotification.style.animation = '';
                            }, 500);
                        }, 3000);
                    } else {
                        // Show error message
                        alert(data.message);
                    }
                });
            } catch (error) {
                // Hide animation immediately on error
                animationContainer.style.display = 'none';
                animationOverlay.style.display = 'none';
                animation.destroy();
                console.error('Error:', error);
                alert('Hiba történt az üzenet küldése során. Kérjük próbálja újra később.');
            }
        });
    </script>
</body>
</html>