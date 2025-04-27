<?php
function handleCookieConsent($db, $userId) {
    try {
        // Lekérjük a felhasználó adatait
        $stmt = $db->prepare("SELECT * FROM user WHERE id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Először ellenőrizzük, hogy van-e már cookie_id a felhasználónak
        if (empty($user['cookie_id'])) {
            // Ha nincs, létrehozunk egy új cookie rekordot
            $stmt = $db->prepare("INSERT INTO cookies (acceptedornot) VALUES (false)");
            $stmt->execute();
            $cookieId = $db->lastInsertId();
            
            // Frissítjük a felhasználó rekordját az új cookie_id-val
            $stmt = $db->prepare("UPDATE user SET cookie_id = :cookie_id WHERE id = :user_id");
            $stmt->execute([
                ':cookie_id' => $cookieId,
                ':user_id' => $userId
            ]);
            
            $cookieAccepted = ['acceptedornot' => false];
        } else {
            // Ha van cookie_id, lekérjük annak állapotát
            $stmt = $db->prepare("SELECT acceptedornot FROM cookies WHERE id = :cookie_id");
            $stmt->execute([':cookie_id' => $user['cookie_id']]);
            $cookieAccepted = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Ha még nem fogadta el a cookie-kat vagy nincs cookie_accepted
        if (!$cookieAccepted || !$cookieAccepted['acceptedornot']) {
            echo "
            <link rel='stylesheet' href='/Vizsga_oldal/assets/css/cookie_styles.css'>
            <div id='cookieModal' class='cookie-modal'>
                <div class='cookie-content'>
                    <img src='/vizsga_oldal/assets/img/cookie.png' alt='Cookie' class='cookie-icon'>
                    <h3>Az Ön adatainak védelme fontos számunkra</h3>
                    <p>Az oldal cookie-kat használ a felhasználói élmény javítása érdekében.</p>
                    <div class='cookie-buttons'>
                        <button id='cookieAcceptBtn' class='btn-primary'>Elfogadom</button>
                        <button id='cookieMoreBtn' class='btn-secondary'>További információk</button>
                    </div>
                </div>
            </div>

            <div id='privacyModal' class='privacy-modal'>
                <div class='privacy-content'>
                    <span class='close-privacy'>&times;</span>
                    <div class='privacy-text'>
                        <h2>Adatvédelmi Szabályzat</h2>
                        <h3>1. Bevezetés</h3>
                        <p>Ez az adatvédelmi szabályzat tájékoztatást nyújt arról, hogyan gyűjtjük és kezeljük az Ön személyes adatait a VibeCore rendszer használata során.</p>

                        <h3>2. Cookie-k használata</h3>
                        <p>Weboldalunk cookie-kat (sütiket) használ a felhasználói élmény javítása érdekében. A cookie-k olyan kis szöveges fájlok, amelyeket az Ön eszköze tárol.</p>

                        <h3>3. Milyen cookie-kat használunk?</h3>
                        <ul>
                            <li>Munkamenet sütik: A weboldal megfelelő működéséhez szükségesek</li>
                            <li>Funkcionális sütik: A felhasználói beállítások megjegyzéséhez</li>
                            <li>Analitikai sütik: A weboldal használatának elemzéséhez</li>
                        </ul>

                        <h3>4. Az Ön jogai</h3>
                        <ul>
                            <li>Hozzáférhet a tárolt személyes adataihoz</li>
                            <li>Kérheti adatai helyesbítését vagy törlését</li>
                            <li>Visszavonhatja a cookie-k használatához adott hozzájárulását</li>
                        </ul>
                    </div>
                </div>
            </div>

            <style>
                .cookie-modal {
                    position: fixed;
                    bottom: 20px;
                    left: 20px;
                    background: white;
                    padding: 20px;
                    border-radius: 10px;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                    z-index: 1000;
                    max-width: 400px;
                }

                .cookie-content {
                    text-align: center;
                }

                .cookie-icon {
                    width: 50px;
                    margin-bottom: 15px;
                }

                .cookie-buttons {
                    display: flex;
                    gap: 10px;
                    margin-top: 20px;
                    justify-content: center;
                }

                .btn-primary {
                    background: #3498db;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }

                .btn-primary:hover {
                    background: #2980b9;
                }

                .btn-secondary {
                    background: #95a5a6;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    cursor: pointer;
                    transition: background-color 0.3s ease;
                }

                .btn-secondary:hover {
                    background: #7f8c8d;
                }

                .cookie-modal.hiding {
                    transform: scale(0) rotate(-15deg);
                    opacity: 0;
                }

                .cookie-modal.hiding .cookie-content {
                    transform: scale(0.8);
                    opacity: 0;
                }

                .cookie-modal.hiding .cookie-icon {
                    transform: rotate(180deg);
                }
            </style>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const cookieModal = document.getElementById('cookieModal');
                const privacyModal = document.getElementById('privacyModal');
                const cookieMoreBtn = document.getElementById('cookieMoreBtn');
                const cookieAcceptBtn = document.getElementById('cookieAcceptBtn');
                const closePrivacy = document.querySelector('.close-privacy');

                cookieMoreBtn.addEventListener('click', function() {
                    privacyModal.style.display = 'block';
                });

                closePrivacy.addEventListener('click', function() {
                    privacyModal.style.display = 'none';
                });

                cookieAcceptBtn.addEventListener('click', function(e) {
                    // Hullám effekt hozzáadása
                    const ripple = document.createElement('div');
                    ripple.classList.add('ripple');
                    this.appendChild(ripple);
                    
                    // Cookie elfogadás animáció
                    cookieModal.classList.add('hiding');
                    
                    fetch('/Vizsga_oldal/includes/accept_cookies.php', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Megvárjuk az animáció végét, majd eltüntetjük
                            setTimeout(() => {
                                cookieModal.style.display = 'none';
                            }, 500);
                        } else {
                            console.error('Hiba történt:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Fetch hiba:', error);
                    });
                });
            });
            </script>
            ";
        }
    } catch (PDOException $e) {
        error_log("Cookie kezelési hiba: " . $e->getMessage());
    }
} 