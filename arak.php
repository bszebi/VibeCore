<?php require_once 'includes/config.php'; ?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Árak - VibeCore</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @keyframes floatAnimation {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        .pricing-section {
            padding: 80px 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .trial-card {
            max-width: 800px;
            margin: 0 auto 40px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 0;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            opacity: 0;
            animation: fadeInUp 1s ease forwards;
        }

        .trial-card:hover {
            transform: scale(1.02);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .trial-content {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 15px;
        }

        .trial-header {
            margin-bottom: 30px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            padding: 25px;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            margin: -40px -40px 30px -40px;
            color: white;
        }

        .trial-header h2 {
            color: white;
            font-size: 36px;
            margin: 0;
            font-weight: 700;
        }

        .trial-badge {
            display: inline-block;
            background: linear-gradient(135deg, #3498db, #2ecc71);
            color: white;
            padding: 8px 25px;
            border-radius: 30px;
            font-size: 18px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.2);
        }

        .trial-features {
            margin: 30px 0;
        }

        .trial-features ul {
            list-style: none;
            padding: 0;
            display: flex;
            justify-content: center;
            gap: 60px;
            margin: 0;
        }

        .trial-features li {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            color: #4a5568;
            font-size: 16px;
            text-align: center;
        }

        .trial-features i {
            color: #3498db;
            font-size: 24px;
            background: rgba(52, 152, 219, 0.1);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            margin-bottom: 5px;
        }

        .btn-trial {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 15px 50px;
            border-radius: 25px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.2);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 20px;
            width: 100%;
            max-width: 300px;
        }

        .btn-trial:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pricing-divider {
            text-align: center;
            margin: 40px 0;
            position: relative;
        }

        .pricing-divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e1e1e1;
        }

        .pricing-divider span {
            background: #f8f9fa;
            padding: 0 20px;
            color: #666;
            font-size: 18px;
            font-weight: 600;
            position: relative;
        }

        .pricing-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            gap: 30px;
            padding: 40px 20px;
            flex-wrap: wrap;
        }

        .pricing-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            opacity: 0;
            animation: fadeInUp 1s ease forwards;
            animation-delay: calc(var(--order) * 0.2s);
            width: 300px;
            min-height: 450px;
            display: flex;
            flex-direction: column;
            border: 1px solid #e9ecef;
        }

        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .pricing-card.featured {
            transform: scale(1.02);
            border: none;
            background: linear-gradient(white, white) padding-box,
                        linear-gradient(135deg, #3498db, #2ecc71) border-box;
            border: 2px solid transparent;
            position: relative;
        }

        .pricing-card.featured:hover {
            transform: scale(1.05);
        }

        .card-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .card-header h4 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .card-body {
            padding: 30px 20px;
            text-align: center;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .price {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .price small {
            font-size: 16px;
            color: #666;
            font-weight: normal;
        }

        .features-list {
            list-style: none;
            padding: 0;
            margin: 0 0 30px;
            flex-grow: 1;
        }

        .features-list li {
            padding: 12px 0;
            color: #666;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .features-list li:last-child {
            border-bottom: none;
        }

        .features-list li i {
            color: #3498db;
            font-size: 18px;
        }

        .btn-choose {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
            width: 100%;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.2);
        }

        .btn-choose:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        @media (max-width: 1200px) {
            .pricing-container {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .pricing-card {
                margin-bottom: 20px;
            }
        }

        @media (max-width: 768px) {
            .pricing-section {
                padding: 40px 20px;
            }
            
            .trial-card {
                margin: 20px;
            }

            .trial-content {
                padding: 20px;
            }

            .trial-header {
                margin: -20px -20px 20px -20px;
                padding: 20px;
            }

            .trial-header h2 {
                font-size: 28px;
            }

            .trial-features ul {
                flex-direction: column;
                gap: 30px;
            }
            
            .trial-features li {
                flex-direction: row;
                justify-content: flex-start;
                text-align: left;
            }
            
            .trial-badge {
                font-size: 16px;
                padding: 6px 20px;
            }
            
            .pricing-card {
                width: 100%;
                max-width: 350px;
            }
        }

        h1.text-center {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 50px;
            font-size: 42px;
            font-weight: 700;
            position: relative;
            padding-bottom: 15px;
        }

        h1.text-center::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, #3498db, #2ecc71);
            border-radius: 2px;
        }

        /* Subscription Toggle Styles */
        .subscription-toggle {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 40px;
            background: #f8f9fa;
            padding: 4px;
            border-radius: 100px;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .toggle-button {
            background: none;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            color: #666;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
            border-radius: 100px;
            font-weight: 500;
            min-width: 140px;
        }

        .toggle-button.active {
            background: #3498db;
            color: white;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.35);
        }

        .discount-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #e74c3c;
            color: white;
            padding: 4px 8px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .toggle-button:not(.active) {
            background: transparent;
        }

        .toggle-button:not(.active):hover {
            background: rgba(52, 152, 219, 0.1);
        }

        .price-amount {
            transition: all 0.3s ease;
        }

        .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 0.7em;
            margin-right: 8px;
        }
    </style>
</head>
<body>
<?php include 'includes/header2.php'; ?> 

    <main class="pricing-section">
        <h1 class="text-center">Csomagjaink</h1>
        
        <div class="trial-card">
            <div class="trial-content">
                <div class="trial-header">
                    <h2>Ingyenes próba verzió</h2>
                    <span class="trial-badge">14 nap</span>
                </div>
                <div class="trial-features">
                    <ul>
                        <li>
                            <i class="fas fa-users"></i>
                            2 felhasználó
                        </li>
                        <li>
                            <i class="fas fa-laptop"></i>
                            10 eszköz kezelése
                        </li>
                        <li>
                            <i class="fas fa-chart-bar"></i>
                            Alapvető jelentések
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            Email támogatás
                        </li>
                    </ul>
                </div>
                <button class="btn-trial" onclick="window.location.href='auth/register.php'">Próba verzió indítása</button>
            </div>
        </div>

        <div class="pricing-divider">
            <span>Fizetős csomagok</span>
        </div>

        <!-- Subscription Toggle -->
        <div class="subscription-toggle">
            <button class="toggle-button active" data-period="monthly">Havi előfizetés</button>
            <button class="toggle-button" data-period="yearly">
                Éves előfizetés
                <span class="discount-badge">15% kedvezmény</span>
            </button>
        </div>

        <div class="pricing-container">
            <div class="pricing-card">
                <div class="card-header">
                    <h4>Alap csomag</h4>
                </div>
                <div class="card-body">
                    <h1 class="price">
                        <span class="price-amount" data-monthly="29999" data-yearly="305990">29,990 Ft</span>
                        <small class="period">/hó</small>
                    </h1>
                    <ul class="features-list">
                        <li>5 felhasználó</li>
                        <li>100 eszköz kezelése</li>
                        <li>Alapvető jelentések</li>
                        <li>Email támogatás</li>
                    </ul>
                    <button class="btn-choose" onclick="window.location.href='megrendeles.php?csomag=alap&ar=' + (document.querySelector('.toggle-button[data-period=yearly]').classList.contains('active') ? '305990&period=ev' : '29999&period=ho')">Választom</button>
                </div>
            </div>
            
            <div class="pricing-card featured">
                <div class="card-header">
                    <h4>Közepes csomag</h4>
                </div>
                <div class="card-body">
                    <h1 class="price">
                        <span class="price-amount" data-monthly="55990" data-yearly="571098">55,990 Ft</span>
                        <small class="period">/hó</small>
                    </h1>
                    <ul class="features-list">
                        <li>10 felhasználó</li>
                        <li>250 eszköz kezelése</li>
                        <li>Részletes jelentések</li>
                        <li>Prioritásos támogatás</li>
                    </ul>
                    <button class="btn-choose" onclick="window.location.href='megrendeles.php?csomag=kozepes&ar=' + (document.querySelector('.toggle-button[data-period=yearly]').classList.contains('active') ? '571098&period=ev' : '55990&period=ho')">Választom</button>
                </div>
            </div>

            <div class="pricing-card">
                <div class="card-header">
                    <h4>Üzleti csomag</h4>
                </div>
                <div class="card-body">
                    <h1 class="price">
                        <span class="price-amount" data-monthly="80990" data-yearly="826098">80,990 Ft</span>
                        <small class="period">/hó</small>
                    </h1>
                    <ul class="features-list">
                        <li>20 felhasználó</li>
                        <li>500 eszköz kezelése</li>
                        <li>Részletes jelentések</li>
                        <li>Prioritásos támogatás</li>
                        <li>Telefonos segítségnyújtás</li>
                    </ul>
                    <button class="btn-choose" onclick="window.location.href='megrendeles.php?csomag=uzleti&ar=' + (document.querySelector('.toggle-button[data-period=yearly]').classList.contains('active') ? '826098&period=ev' : '80990&period=ho')">Választom</button>
                </div>
            </div>
            
            <div class="pricing-card">
                <div class="card-header">
                    <h4>Vállalati csomag</h4>
                </div>
                <div class="card-body">
                    <h1 class="price">Egyedi ár</h1>
                    <ul class="features-list">
                        <li>Korlátlan felhasználó</li>
                        <li>Korlátlan eszköz</li>
                        <li>Testreszabott megoldások</li>
                        <li>24/7 támogatás</li>
                    </ul>
                    <button class="btn-choose" onclick="window.location.href='megrendeles.php?csomag=vallalati&ar=egyedi'">Kapcsolatfelvétel</button>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButtons = document.querySelectorAll('.toggle-button');
            const priceAmounts = document.querySelectorAll('.price-amount');
            const chooseBtns = document.querySelectorAll('.btn-choose');
            const periodLabels = document.querySelectorAll('.period');

            function updatePrices(period) {
                priceAmounts.forEach(price => {
                    if (price.dataset[period]) {
                        const monthlyPrice = parseInt(price.dataset.monthly);
                        const yearlyPrice = parseInt(price.dataset.yearly);
                        
                        if (period === 'yearly') {
                            const originalYearlyPrice = monthlyPrice * 12;
                            price.innerHTML = `
                                <span class="original-price">${originalYearlyPrice.toLocaleString('hu-HU')} Ft</span>
                                ${yearlyPrice.toLocaleString('hu-HU')} Ft
                            `;
                        } else {
                            price.innerHTML = `${monthlyPrice.toLocaleString('hu-HU')} Ft`;
                        }
                    }
                });

                periodLabels.forEach(label => {
                    label.textContent = period === 'yearly' ? '/év' : '/hó';
                });

                chooseBtns.forEach(btn => {
                    if (btn.dataset[period]) {
                        const price = btn.dataset[period];
                        const packageName = btn.closest('.pricing-card').querySelector('h4').textContent.toLowerCase().replace(' csomag', '');
                        const periodText = period === 'yearly' ? 'ev' : 'ho';
                        btn.onclick = function() {
                            window.location.href = `megrendeles.php?csomag=${packageName}&ar=${price}&period=${periodText}`;
                        };
                    }
                });
            }

            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    toggleButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    updatePrices(this.dataset.period);
                });
            });
        });
    </script>

    <?php include 'includes/footer2.php'; ?> 
</body>
</html> 