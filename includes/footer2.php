<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>Kapcsolat</h3>
            <p>Email: info@vibecore.hu</p>
            <p>Telefon: +36 1 234 5678</p>
            <p>Cím: 1234 Budapest, Példa utca 123.</p>
        </div>
        <div class="footer-section">
            <h3>Hasznos linkek</h3>
            <p><a href="/Vizsga_oldal/hasznos-linkek/gyik.php">GYIK</a></p>
            <p><a href="/Vizsga_oldal/hasznos-linkek/adatvedelem.php">Adatvédelmi irányelvek</a></p>
            <p><a href="/Vizsga_oldal/hasznos-linkek/aszf.php">Általános szerződési feltételek</a></p>
        </div>
        <div class="footer-section">
            <h3>Kövessen minket</h3>
            <div class="social-links">
                <a href="https://www.facebook.com/heszmilan?locale=hu_HU"><img src="/Vizsga_oldal/assets/img/facebook.png" alt="Facebook"></a>
                <a href="https://x.com/Milee_exe"><img src="/Vizsga_oldal/assets/img/twitter.png" alt="X"></a>
                <a href="https://www.instagram.com/milan_hesz/"><img src="/Vizsga_oldal/assets/img/instagram.png" alt="Instagram"></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2025 VibeCore. Minden jog fenntartva.</p>
    </div>
</footer>

<style>
footer {
    background-color: #2c3e50;
    color: white;
    padding: 50px 0 20px;
    margin-top: 50px;
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    padding: 0 20px;
}

.footer-section h3 {
    font-size: 1.2em;
    margin-bottom: 20px;
}

.footer-section p {
    margin-bottom: 10px;
}

.footer-section a {
    color: white;
    text-decoration: none;
    transition: color 0.3s;
}

.footer-section a:hover {
    color: #3498db;
}

.social-links {
    display: flex;
    gap: 15px;
}

.social-links img {
    width: 30px;
    height: 30px;
    transition: transform 0.3s;
}

.social-links img:hover {
    transform: scale(1.1);
}

.footer-bottom {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

@media (max-width: 768px) {
    .footer-content {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .social-links {
        justify-content: center;
    }
}
</style> 