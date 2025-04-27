<?php require_once '../includes/config.php'; ?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GYIK - VibeCore</title>
    <link rel="stylesheet" href="../assets/css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .faq-hero {
            background: linear-gradient(rgba(44, 62, 80, 0.9), rgba(44, 62, 80, 0.9)), url('../assets/img/faq-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            text-align: center;
            padding: 100px 20px;
            margin-top: 80px;
        }

        .faq-hero h1 {
            font-size: 3em;
            margin-bottom: 20px;
        }

        .faq-hero p {
            font-size: 1.2em;
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-section {
            max-width: 1000px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .faq-item {
            background: white;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .faq-question {
            padding: 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
            color: #2c3e50;
            transition: all 0.3s ease;
        }

        .faq-question:hover {
            background: #f8f9fa;
        }

        .faq-question i {
            transition: transform 0.3s ease;
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            line-height: 1.6;
            color: #666;
        }

        .faq-item.active .faq-question {
            background: #f8f9fa;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        .faq-item.active .faq-answer {
            padding: 20px;
            max-height: 1000px;
        }

        @media (max-width: 768px) {
            .faq-hero h1 {
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

    <section class="faq-hero">
        <h1>Gyakran Ismételt Kérdések</h1>
        <p>Válaszok a leggyakrabban felmerülő kérdésekre</p>
    </section>

    <section class="faq-section">
        <div class="faq-item">
            <div class="faq-question">
                <span>Hogyan kezdhetem el használni a szolgáltatást?</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Regisztráljon weboldalunkon, válassza ki az Önnek megfelelő csomagot, és azonnal elkezdheti használni szolgáltatásainkat.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                <span>Milyen fizetési módokat fogadnak el?</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Elfogadunk bankkártyás fizetést, banki átutalást, valamint Apple Pay és Google Pay fizetési módokat. A QR kód banki alkalmazással való beolvasása is folyamatban van.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                <span>Mennyi időre szól az előfizetés?</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Előfizetéseink havi vagy éves időtartamra köthetők, rugalmas megújítási lehetőségekkel.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                <span>Van lehetőség az előfizetés módosítására?</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Igen, előfizetését bármikor módosíthatja, válthat magasabb vagy alacsonyabb csomagra igényei szerint.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                <span>Hogyan tudom lemondani az előfizetésemet?</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Az előfizetés lemondásához jelentkezzen be a fiókjába, és a "Fiók beállítások" menüpontban találja a lemondási opciót. A lemondás után az aktuális előfizetési időszak végéig továbbra is használhatja a szolgáltatásokat.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                <span>Mi történik, ha lejár az előfizetésem?</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Ha lejár az előfizetése, a rendszer automatikusan értesíti Önt. A lejárás után 7 napig továbbra is bejelentkezhet, de a szolgáltatások használata korlátozott lesz. A 7. nap után a fiók inaktívvá válik, amíg nem újítsa meg az előfizetést.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                <span>Hogyan tudom visszaállítani a jelszavam?</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                A jelszó visszaállításához kattintson a "Bejelentkezés" oldalon az "Elfelejtett jelszó" linkre. Adja meg az email címét, és küldünk egy linket, ahol új jelszót állíthat be. A link 24 órán belül érvényes.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                <span>Milyen adatokat tároltok a rendszerben?</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                A rendszerben tároljuk a felhasználói adatokat (név, email, telefonszám), a cég adatait, valamint a szolgáltatás használatával kapcsolatos adatokat. Minden adatot a GDPR előírásainak megfelelően kezelünk, és nem adjuk át harmadik félnek.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                <span>Hogyan tudom módosítani a profilom adatait?</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                A profil adatainak módosításához jelentkezzen be a fiókjába, és kattintson a "Profil beállítások" menüpontra. Itt módosíthatja a személyes adatait, jelszavát, és egyéb beállításokat.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                <span>Milyen böngészőkben működik a szolgáltatás?</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                A szolgáltatás a legtöbb modern böngészőben működik, beleértve a Chrome, Firefox, Safari és Edge legújabb verzióit. Javasoljuk a böngészők legfrissebb verziójának használatát a legjobb felhasználói élmény érdekében.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                <span>Van mobilalkalmazás is?</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Jelenleg nincs mobilalkalmazásunk, de a fejlesztése folyamatban van. Hamarosan elérhető lesz az App Store-ban és a Google Play áruházban.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                <span>Hogyan tudok kapcsolatba lépni az ügyfélszolgálattal?</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Az ügyfélszolgálattal többféleképpen is kapcsolatba léphet: emailben a support@vibecore.hu címen, telefonon a +36 1 234 5678 számon, vagy a "Kapcsolat" oldalon található űrlaton keresztül. Az ügyfélszolgálat hétköznap 9:00-17:00 között elérhető.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question">
                <span>Van ingyenes próbaidőszak?</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
                Igen, minden csomaghoz tartozik egy 14 napos ingyenes próbaidőszak. A próbaidőszak alatt minden funkciót kipróbálhat, és ha nem megfelelő, bármikor lemondhatja az előfizetést, anélkül hogy költsége terhelne.
            </div>
        </div>
    </section>

    <?php include '../includes/footer2.php'; ?>

    <script>
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const item = question.parentElement;
                const isActive = item.classList.contains('active');
                
                // Bezárjuk az összes nyitott elemet
                document.querySelectorAll('.faq-item').forEach(faqItem => {
                    faqItem.classList.remove('active');
                });

                // Ha nem volt aktív, kinyitjuk
                if (!isActive) {
                    item.classList.add('active');
                }
            });
        });
    </script>
</body>
</html> 