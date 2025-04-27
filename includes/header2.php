<?php 
// Ha nincs beállítva a $root_path, akkor az aktuális mappát használjuk
if (!isset($root_path)) {
    $root_path = ".";
}
?>
<header class="header">
    <div class="header-content">
        <div class="logo">
            <a href="/Vizsga_oldal/home.php">
                <img src="/Vizsga_oldal/admin/VIBCORE BLACK2 másolata.png" alt="Logo">
                <span class="logo-text">VibeCore</span>
            </a>
        </div>

        <!-- Hamburger menü -->
        <div class="hamburger-menu">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <div class="nav-menu">
            <a href="/Vizsga_oldal/home.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'home.php' ? 'active' : ''; ?>">Kezdőlap</a>
            <a href="/Vizsga_oldal/szolgaltatasok.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'szolgaltatasok.php' ? 'active' : ''; ?>">Szolgáltatások</a>
            <a href="/Vizsga_oldal/arak.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'arak.php' ? 'active' : ''; ?>">Árak</a>
            <a href="/Vizsga_oldal/rolunk.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'rolunk.php' ? 'active' : ''; ?>">Rólunk</a>
            <a href="/Vizsga_oldal/kapcsolat.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'kapcsolat.php' ? 'active' : ''; ?>">Kapcsolat</a>
        </div>

        <div class="auth-buttons">
            <a href="/Vizsga_oldal/auth/login.php" class="login">Bejelentkezés</a>
            <a href="/Vizsga_oldal/auth/register.php" class="register">Regisztráció</a>
        </div>

        <!-- Mobil menü -->
        <div class="mobile-menu">
            <div class="mobile-nav">
                <a href="/Vizsga_oldal/home.php">Kezdőlap</a>
                <a href="/Vizsga_oldal/szolgaltatasok.php">Szolgáltatások</a>
                <a href="/Vizsga_oldal/arak.php">Árak</a>
                <a href="/Vizsga_oldal/rolunk.php">Rólunk</a>
                <a href="/Vizsga_oldal/kapcsolat.php">Kapcsolat</a>
            </div>
            <div class="mobile-auth">
                <a href="/Vizsga_oldal/auth/login.php" class="login">Bejelentkezés</a>
                <a href="/Vizsga_oldal/auth/register.php" class="register">Regisztráció</a>
            </div>
        </div>
    </div>
</header>

<style>
/* Hamburger menü */
.hamburger-menu {
    display: none;
    flex-direction: column;
    gap: 6px;
    cursor: pointer;
    padding: 5px;
    z-index: 1000;
}

.hamburger-menu span {
    display: block;
    width: 25px;
    height: 3px;
    background-color: #2c3e50;
    transition: all 0.3s ease;
}

/* Mobil menü */
.mobile-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    background: white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    padding: 20px;
}

.mobile-menu a {
    display: block;
    padding: 12px 15px;
    color: #2c3e50;
    text-decoration: none;
    text-align: center;
    transition: background-color 0.3s;
}

.mobile-menu a:hover {
    background-color: #f8f9fa;
}

.mobile-auth {
    display: none;
    flex-direction: column;
    gap: 10px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.mobile-nav {
    display: none;
    flex-direction: column;
}

/* Mobil auth gombok stílusa */
.mobile-auth .login,
.mobile-auth .register {
    padding: 12px 20px;
    font-size: 16px;
    color: #2c3e50;
    border: 2px solid #2c3e50;
    border-radius: 5px;
    margin: 0 20px;
    width: auto;
    max-width: 250px;
    margin: 0 auto;
}

.mobile-auth .register {
    background-color: #2c3e50;
    color: white !important;
}

.mobile-auth .login:hover,
.mobile-auth .register:hover {
    opacity: 0.9;
    background-color: #34495e;
    color: white !important;
}

/* Első breakpoint - auth gombok menübe */
@media (max-width: 1295px) {
    .hamburger-menu {
        display: flex;
    }

    .auth-buttons {
        display: none;
    }

    .mobile-menu.active {
        display: block;
    }

    .mobile-auth {
        display: flex;
        width: 100%;
        max-width: 300px;
        margin: 15px auto 0;
    }
}

/* Második breakpoint - minden menübe */
@media (max-width: 768px) {
    .nav-menu {
        display: none;
    }

    .mobile-nav {
        display: flex;
    }
}

/* Hamburger menü animáció */
.hamburger-menu.active span:nth-child(1) {
    transform: rotate(45deg) translate(7px, 7px);
}

.hamburger-menu.active span:nth-child(2) {
    opacity: 0;
}

.hamburger-menu.active span:nth-child(3) {
    transform: rotate(-45deg) translate(7px, -7px);
}

/* Reszponzív auth gombok */
@media (max-width: 1400px) {
    .login, .register {
        padding: 8px 15px;
        font-size: 14px;
    }
}

@media (max-width: 1350px) {
    .login, .register {
        padding: 6px 12px;
        font-size: 13px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.querySelector('.hamburger-menu');
    const mobileMenu = document.querySelector('.mobile-menu');

    hamburger.addEventListener('click', function() {
        hamburger.classList.toggle('active');
        mobileMenu.classList.toggle('active');
    });

    // Bezárja a menüt kívül kattintáskor
    document.addEventListener('click', function(e) {
        if (!hamburger.contains(e.target) && !mobileMenu.contains(e.target)) {
            hamburger.classList.remove('active');
            mobileMenu.classList.remove('active');
        }
    });

    // Bezárja a menüt linkre kattintáskor
    const mobileLinks = mobileMenu.querySelectorAll('a');
    mobileLinks.forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('active');
            mobileMenu.classList.remove('active');
        });
    });
});
</script> 