<?php
require_once '../includes/config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';
require_once '../includes/language_handler.php';

// Initialize $translations array
$translations = [];

// Get translations based on current language
$currentLang = $_SESSION['language'] ?? 'hu'; // Alapértelmezett magyar nyelv
try {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT translation_key, translation_value FROM translations WHERE language_code = :lang");
    $stmt->execute([':lang' => $currentLang]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $translations[$row['translation_key']] = $row['translation_value'];
    }
} catch (PDOException $e) {
    // Handle error gracefully
    error_log('Translation fetch error: ' . $e->getMessage());
}

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Felhasználói adatok lekérése
try {
    $db = DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT *, profile_pic FROM user WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['profile_pic'] = $user['profile_pic']; // Frissítjük a session-ben tárolt profilképet
} catch (PDOException $e) {
    $error = 'Adatbázis hiba: ' . $e->getMessage();
}

// Header betöltése
require_once '../includes/layout/header.php';
?>

<!-- Csak a tartalom, HTML struktúra nélkül -->
<div class="profile-container">
    <h1 class="main-title" data-translate="settings"><?php echo translate('settings'); ?></h1>
    <div class="settings-layout">
        <!-- Bal oldali menü -->
        <div class="settings-menu">
            <div class="menu-items">
                <div class="menu-item active" data-target="password">
                    <span data-translate="change_password"><?php echo translate('change_password'); ?></span>
                </div>
                <div class="menu-item" data-target="appearance">
                    <span data-translate="change_language"><?php echo translate('change_language'); ?></span>
                </div>
                <div class="menu-item" data-target="darkmode">
                    <span data-translate="dark_mode"><?php echo translate('dark_mode'); ?></span>
                </div>
            </div>
            <div class="menu-item danger" data-target="delete">
                <span data-translate="delete_account"><?php echo translate('delete_account'); ?></span>
            </div>
        </div>

        <!-- Jobb oldali tartalom -->
        <div class="settings-content">
            <!-- Jelszó módosítás szekció -->
            <div class="content-section active" id="password-section">
                <h2 data-translate="change_password"><?php echo translate('change_password'); ?></h2>
                <div class="form-group">
                    <label data-translate="current_password"><?php echo translate('current_password'); ?></label>
                    <div class="password-input-container">
                        <input type="password" id="current_password" name="current_password">
                        <img src="../assets/img/hide.png" class="toggle-password" onclick="togglePasswordVisibility('current_password', this)" alt="<?php echo translate('toggle_password'); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label data-translate="new_password"><?php echo translate('new_password'); ?></label>
                    <div class="password-input-container">
                        <input type="password" id="new_password" name="new_password">
                        <img src="../assets/img/hide.png" class="toggle-password" onclick="togglePasswordVisibility('new_password', this)" alt="<?php echo translate('toggle_password'); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label data-translate="confirm_new_password"><?php echo translate('confirm_new_password'); ?></label>
                    <div class="password-input-container">
                        <input type="password" id="new_password_confirmation" name="new_password_confirmation">
                        <img src="../assets/img/hide.png" class="toggle-password" onclick="togglePasswordVisibility('new_password_confirmation', this)" alt="<?php echo translate('toggle_password'); ?>">
                    </div>
                </div>
                <button type="button" class="btn-update" onclick="updatePassword()" data-translate="save_password"><?php echo translate('save_password'); ?></button>
            </div>

            <!-- Nyelv választás szekció -->
            <div class="content-section" id="appearance-section">
                <h2 data-translate="language_settings"><?php echo translate('language_settings'); ?></h2>
                <div class="language-select">
                    <select id="language" class="form-control">
                        <option value="hu" <?php echo ($currentLang === 'hu') ? 'selected' : ''; ?>><?php echo translate('hungarian'); ?></option>
                        <option value="en" <?php echo ($currentLang === 'en') ? 'selected' : ''; ?>><?php echo translate('english'); ?></option>
                        <option value="de" <?php echo ($currentLang === 'de') ? 'selected' : ''; ?>><?php echo translate('german'); ?></option>
                        <option value="sk" <?php echo ($currentLang === 'sk') ? 'selected' : ''; ?>><?php echo translate('slovak'); ?></option>
                    </select>
                </div>
                <button type="button" class="btn-update" onclick="saveLanguage()" data-translate="save_language"><?php echo translate('save_language'); ?></button>
            </div>

            <!-- Sötét mód szekció -->
            <div class="content-section" id="darkmode-section">
                <h2 data-translate="appearance"><?php echo translate('appearance'); ?></h2>
                <div class="mode-status" id="modeStatus" data-translate="light_mode"><?php echo translate('light_mode'); ?></div>
                <div class="toggle-switch">
                    <label class="switch-label">
                        <input type="checkbox" class="checkbox" id="darkMode">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <!-- Fiók törlés szekció -->
            <div class="content-section" id="delete-section">
                <h2 data-translate="confirm_account_deletion"><?php echo translate('confirm_account_deletion'); ?></h2>
                <div class="delete-account-container">
                    <div class="warning-box">
                        <img src="../assets/cats/sure.png" alt="<?php echo translate('warning'); ?>" class="warning-icon-img">
                        <h3 data-translate="warning"><?php echo translate('warning'); ?></h3>
                        <p data-translate="deletion_permanent"><?php echo translate('deletion_permanent'); ?></p>
                        <ul class="warning-list">
                            <li data-translate="all_data_deleted"><?php echo translate('all_data_deleted'); ?></li>
                            <li data-translate="project_access_lost"><?php echo translate('project_access_lost'); ?></li>
                            <li data-translate="settings_lost"><?php echo translate('settings_lost'); ?></li>
                        </ul>
                    </div>
                    <div class="confirmation-box">
                        <label class="confirm-checkbox">
                            <input type="checkbox" id="deleteConfirm">
                            <span data-translate="understand_permanent"><?php echo translate('understand_permanent'); ?></span>
                        </label>
                        <button class="delete-btn" onclick="confirmDelete()" disabled>
                            <i class="fas fa-trash-alt"></i>
                            <span data-translate="delete_account"><?php echo translate('delete_account'); ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Törlés megerősítő modal -->
<div id="deleteAccountModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <img src="../assets/img/warning.png" alt="Figyelmeztetés" class="warning-icon-img">
            <h3><?php echo translate('warning'); ?></h3>
        </div>
        <p class="modal-text"><?php echo translate('delete_permanent'); ?><br><?php echo translate('all_data_deleted'); ?><br><?php echo translate('settings_lost'); ?><br><?php echo translate('understand_permanent'); ?>.</p>
        <div class="modal-buttons">
            <button class="deactivate-btn" onclick="deleteAccount()"><?php echo translate('delete_account'); ?></button>
            <button class="cancel-btn" onclick="closeDeleteModal()"><?php echo translate('cancel'); ?></button>
        </div>
    </div>
</div>

<!-- Köszönő képernyő -->
<div id="thankYouScreen" class="thank-you-overlay" style="display: none;">
    <div class="thank-you-content">
        <img src="../assets/cats/thanks.png" alt="Köszönjük" class="thank-you-image">
        <h2><?php echo translate('thank_you'); ?></h2>
        <p><?php echo translate('team_message'); ?></p>
        <div class="loading-spinner"></div>
    </div>
</div>

<style>
    body {
        margin: 0;
        padding: 0;
        background: #f5f5f5;
        transform: translateX(0);
        transition: transform 1s ease-in-out;
    }

    .profile-container {
        max-width: 1800px;  /* Növelt szélesség */
        margin: 2rem auto;
        margin-top: 5rem; /* Hely a fixed headernek */
        padding: 2rem 1.5rem; /* Növelt felső padding */
        background: white;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    .settings-layout {
        display: grid;
        grid-template-columns: 300px minmax(800px, 1fr); /* Fix minimum szélesség a jobb oldalnak */
        gap: 2rem;
        min-height: 600px; /* Fix minimum magasság */
    }

    .settings-menu {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between; /* Ez biztosítja, hogy a Fiók törlése alul legyen */
        min-height: 400px; /* Minimum magasság a megfelelő térközért */
    }

    .menu-items {
        display: flex;
        flex-direction: column;
    }

    .menu-item {
        padding: 1rem 1.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }

    .menu-item:hover {
        background: #f8f9fa;
        border-left-color: #3498db;
    }

    .menu-item.active {
        background: #f8f9fa;
        border-left-color: #3498db;
    }

    .menu-item.danger {
        color: #dc3545;
        margin-top: auto; /* Ez biztosítja, hogy alul legyen */
        border-top: 1px solid #eee; /* Elválasztó vonal */
    }

    .menu-item.danger:hover {
        background: #fff5f5;
        border-left-color: #dc3545;
    }

    .settings-content {
        background: white;
        border-radius: 8px;
        padding: 2rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        min-height: 600px; /* Fix minimum magasság */
    }

    .content-section {
        display: none;
        min-height: 500px; /* Fix minimum magasság minden szekciónak */
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .content-section.active {
        display: block;
        opacity: 1;
    }

    .settings-section {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        width: 100%;  /* Teljes szélesség */
        max-width: 1800px;  /* Maximum szélesség */
        margin: 0 auto;  /* Középre igazítás */
    }

    .settings-box {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        width: 100%;  /* Teljes szélesség */
    }

    .settings-box h3 {
        margin: 0 0 20px 0;
        color: #333;
        font-size: 18px;
    }

    .btn-update {
        background: #3498db;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        width: 100%;
        margin-top: 15px;
    }

    .btn-update:hover {
        background: #2980b9;
    }

    .checkbox-group {
        margin-bottom: 15px;
    }

    .checkbox-group label {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
    }

    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
    }

    .form-group,
    .language-select,
    .theme-switch-wrapper,
    .danger-zone {
        max-width: 800px; /* Maximum szélesség a form elemeknek */
        margin: 0 auto;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: #666;
    }

    .form-group input,
    .language-select select {
        width: 100%;
        padding: 0.8rem;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .home-link {
        position: fixed;
        top: 20px;
        left: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        color: #333;
        padding: 10px;
        transition: transform 0.2s;
        z-index: 1000;
        background: none;
        box-shadow: none;
    }

    .home-link:hover {
        transform: translateY(-2px);
    }

    .home-link img {
        width: 40px;
        height: 40px;
    }

    .home-link span {
        font-size: 16px;
        font-weight: 500;
    }

    .theme-switch-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .theme-switch {
        position: relative;
        width: 100px;
        height: 50px;
        --light: #d8dbe0;
        --dark: #28292c;
        --link: rgb(27, 129, 112);
        --link-hover: rgb(24, 94, 82);
        margin: 20px 0;
    }

    .switch-label {
        position: absolute;
        width: 100%;
        height: 50px;
        background-color: var(--light);
        border-radius: 25px;
        cursor: pointer;
        border: 3px solid var(--light);
    }

    .checkbox {
        position: absolute;
        display: none;
    }

    .slider {
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 25px;
        -webkit-transition: 0.3s;
        transition: 0.3s;
        background-color: var(--light);
    }

    .checkbox:checked ~ .slider {
        background-color: var(--dark);
    }

    .slider::before {
        content: "";
        position: absolute;
        top: 8px;
        left: 8px;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background-color: var(--dark);
        -webkit-transition: 0.3s;
        transition: 0.3s;
    }

    .checkbox:checked ~ .slider::before {
        -webkit-transform: translateX(55px);
        -ms-transform: translateX(55px);
        transform: translateX(55px);
        background-color: var(--light);
        -webkit-box-shadow: inset -5px -4px 0px 0px var(--light);
        box-shadow: inset -5px -4px 0px 0px var(--light);
    }

    .language-select select {
        width: 100%;
        padding: 10px;
        border-radius: 4px;
        border: 1px solid #ddd;
        margin-bottom: 10px;
    }

    .danger-zone {
        border: 1px solid #ff4444;
    }

    .danger-zone h3 {
        color: #ff4444;
    }

    .warning-text {
        color: #ff4444;
        margin-bottom: 15px;
    }

    .btn-delete {
        background: #ff4444;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        width: 100%;
    }

    .btn-delete:hover {
        background: #cc0000;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        z-index: 1000;
    }

    .modal-content {
        background-color: white;
        margin: 15% auto;
        padding: 20px;
        border-radius: 8px;
        width: 80%;
        max-width: 500px;
    }

    .modal-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .btn-cancel {
        background: #6c757d;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        flex: 1;
    }

    .btn-cancel:hover {
        background: #5a6268;
    }

    /* Címek egységes mérete */
    .content-section h2 {
        margin-bottom: 2rem;
        font-size: 24px;
        color: #333;
    }

    .main-title {
        text-align: center;
        font-size: 32px;
        color: #333;
        margin-bottom: 2rem;
        font-weight: 600;
        padding-bottom: 1rem;
        border-bottom: 2px solid #eee;
    }

    .toggle-switch {
        position: relative;
        width: 100px;
        height: 50px;
        --light: #d8dbe0;
        --dark: #28292c;
        --link: rgb(27, 129, 112);
        --link-hover: rgb(24, 94, 82);
        margin: 20px 0;
    }

    .switch-label {
        position: absolute;
        width: 100%;
        height: 50px;
        background-color: var(--light);
        border-radius: 25px;
        cursor: pointer;
        border: 3px solid var(--light);
    }

    .checkbox {
        position: absolute;
        display: none;
    }

    .slider {
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 25px;
        -webkit-transition: 0.3s;
        transition: 0.3s;
        background-color: var(--light);
    }

    .checkbox:checked ~ .slider {
        background-color: var(--dark);
    }

    .slider::before {
        content: "";
        position: absolute;
        top: 8px;
        left: 8px;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background-color: var(--dark);
        -webkit-transition: 0.3s;
        transition: 0.3s;
    }

    .checkbox:checked ~ .slider::before {
        -webkit-transform: translateX(55px);
        -ms-transform: translateX(55px);
        transform: translateX(55px);
        background-color: var(--light);
        -webkit-box-shadow: inset -5px -4px 0px 0px var(--light);
        box-shadow: inset -5px -4px 0px 0px var(--light);
    }

    .mode-status {
        text-align: left;
        margin-bottom: 10px;
        font-size: 16px;
        color: #333;
        margin-left: 5px;
    }

    .delete-account-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
    }

    .warning-box {
        background: #fff5f5;
        border: 1px solid #ffcdd2;
        border-radius: 8px;
        padding: 2rem;
        text-align: center;
        margin-bottom: 2rem;
    }

    .warning-box i {
        color: #dc3545;
        font-size: 48px;
        margin-bottom: 15px;
    }

    .warning-box h3 {
        color: #dc3545;
        margin: 10px 0;
    }

    .warning-box p {
        color: #555;
        font-size: 16px;
        margin-bottom: 20px;
    }

    .warning-list {
        text-align: left;
        padding-left: 20px;
        margin: 15px 0;
    }

    .warning-list li {
        color: #666;
        margin-bottom: 8px;
        line-height: 1.5;
    }

    .confirmation-box {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .confirm-checkbox {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        cursor: pointer;
    }

    .confirm-checkbox input {
        width: 18px;
        height: 18px;
    }

    .confirm-checkbox span {
        color: #555;
        font-size: 14px;
    }

    .delete-btn {
        width: 100%;
        padding: 12px;
        background-color: #dc3545;
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: background-color 0.3s;
    }

    .delete-btn:hover {
        background-color: #c82333;
    }

    .delete-btn:disabled {
        background-color: #e9ecef;
        cursor: not-allowed;
        color: #6c757d;
    }

    .delete-btn i {
        font-size: 18px;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 12px;
        width: 90%;
        max-width: 400px;
        text-align: center;
    }

    .modal-header {
        margin-bottom: 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .modal-header .warning-icon-img {
        width: 40px;
        height: 40px;
        margin-bottom: 15px;
    }

    .modal-header h3 {
        color: #dc3545;
        margin: 0;
    }

    .modal-text {
        color: #666;
        margin-bottom: 25px;
        line-height: 1.5;
    }

    .modal-buttons {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .deactivate-btn {
        background: #dc3545;
        color: white;
        border: none;
        padding: 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 16px;
        transition: background 0.3s;
    }

    .deactivate-btn:hover {
        background: #c82333;
    }

    .cancel-btn {
        background: #f8f9fa;
        color: #333;
        border: none;
        padding: 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 16px;
        transition: background 0.3s;
    }

    .cancel-btn:hover {
        background: #e2e6ea;
    }

    /* Frissítsük a header profilkép méretét */
    .profile-icon {
        width: 45px !important;
        height: 45px !important;
    }

    .thank-you-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: #2c3e50;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: opacity 1s ease-out;
    }

    .thank-you-content {
        text-align: center;
        padding: 2rem;
    }

    .thank-you-image {
        width: 200px;
        height: auto;
        margin-bottom: 2rem;
    }

    .thank-you-content h2 {
        color: #fff;
        font-size: 2rem;
        margin-bottom: 1rem;
    }

    .thank-you-content p {
        color: #ecf0f1;
        font-size: 1.2rem;
        margin-bottom: 2rem;
    }

    .loading-spinner {
        width: 200px;
        height: 4px;
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 2px;
        margin: 20px auto;
        position: relative;
        overflow: hidden;
    }

    .loading-spinner::after {
        content: '';
        position: absolute;
        width: 40%;
        height: 100%;
        background-color: #3498db;
        border-radius: 2px;
        animation: loading 1s infinite ease-in-out;
    }

    @keyframes loading {
        0% {
            left: -40%;
        }
        100% {
            left: 100%;
        }
    }

    .warning-icon-img {
        width: 100px;
        height: 100px;
        margin-bottom: 15px;
    }

    .warning-box {
        background: #fff5f5;
        border: 1px solid #ffcdd2;
        border-radius: 8px;
        padding: 2rem;
        text-align: center;
        margin-bottom: 2rem;
    }

    .warning-box h3 {
        color: #dc3545;
        margin: 10px 0;
    }

    .password-input-container {
        position: relative;
        width: 100%;
    }

    .password-input-container input {
        width: 100%;
        padding-right: 40px;
    }

    .toggle-password {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        width: 20px;
        height: 20px;
        object-fit: contain;
    }

    .toggle-password:hover {
        opacity: 0.8;
    }
</style>

<script>
    function translate(key) {
        return document.querySelector('html').getAttribute('data-translations-' + key) || key;
    }

    // Jelszó módosítás kezelése
    function updatePassword() {
        const currentPassword = document.getElementById('current_password').value;
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('new_password_confirmation').value;

        if (!currentPassword || !newPassword || !confirmPassword) {
            alert(translate('fill_all_fields'));
            return;
        }

        if (newPassword !== confirmPassword) {
            alert(translate('passwords_not_match'));
            return;
        }

        if (newPassword.length < 8) {
            alert(translate('password_min_length'));
            return;
        }

        // AJAX hívás a jelszó módosításához
        fetch('../includes/update_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Sikeres jelszóváltoztatás
                alert(translate('password_changed'));
                // Mezők ürítése
                document.getElementById('current_password').value = '';
                document.getElementById('new_password').value = '';
                document.getElementById('new_password_confirmation').value = '';
            } else {
                // Hiba esetén
                alert(data.error || translate('password_error'));
            }
        })
        .catch(error => {
            console.error('Hiba:', error);
            alert(translate('jelszo_hiba'));
        });
    }

    // Értesítési beállítások mentése
    function saveNotificationSettings() {
        const emailNotifications = document.getElementById('email_notifications').checked;
        const browserNotifications = document.getElementById('browser_notifications').checked;

        // Itt implementáld az értesítési beállítások mentésének AJAX hívását
    }

    // Dark mode kezelése
    const darkModeToggle = document.getElementById('darkMode');
    const modeStatus = document.getElementById('modeStatus');
    
    darkModeToggle.addEventListener('change', function() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', this.checked);
        modeStatus.textContent = this.checked ? translate('dark_mode_enabled') : translate('light_mode_enabled');
    });

    // Nyelv váltás
    function saveLanguage() {
        const language = document.getElementById('language').value;
        
        // AJAX kérés a nyelv mentéséhez
        fetch('../includes/save_language.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'language=' + encodeURIComponent(language)
        })
        .then(response => {
            // Ellenőrizzük, hogy a válasz JSON-e
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new TypeError('A szerver nem JSON választ küldött!');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Sikeres mentés
                alert('<?php echo translate("A nyelvi beállítás sikeresen mentve!"); ?>');
                
                // Frissítjük a fordításokat
                fetch('../includes/get_translations.php?lang=' + encodeURIComponent(language))
                .then(response => response.json())
                .then(translations => {
                    // Frissítjük a fordításokat a DOM-ban
                    document.querySelectorAll('[data-translate]').forEach(element => {
                        const key = element.getAttribute('data-translate');
                        if (translations[key]) {
                            element.textContent = translations[key];
                        }
                    });
                    
                    // Frissítjük a nyelvi beállítást a session-ben
                    document.cookie = 'language=' + language + '; path=/';
                    
                    // Oldal újratöltése a változtatások érvényesítéséhez
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Translation fetch error:', error);
                    // Ha nem sikerül a fordítások frissítése, akkor is újratöltjük az oldalt
                    window.location.reload();
                });
            } else {
                // Hiba esetén
                alert('<?php echo translate("Hiba történt a nyelvi beállítás mentése közben:"); ?> ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?php echo translate("Hiba történt a kérés során."); ?>');
        });
    }

    // Fiók törlés
    function confirmDelete() {
        document.getElementById('deleteAccountModal').style.display = 'flex';
    }

    function closeDeleteModal() {
        document.getElementById('deleteAccountModal').style.display = 'none';
    }

    function deleteAccount() {
        const userId = <?php echo json_encode($_SESSION['user_id']); ?>;
        
        fetch('../includes/delete_account.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                user_id: userId
            })
        })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Szerver válasz:', text);
                throw new Error('Érvénytelen szerver válasz');
            }
        })
        .then(data => {
            if (data.success) {
                // Elrejtjük a törlés modalt
                document.getElementById('deleteAccountModal').style.display = 'none';
                
                // Megjelenítjük a köszönjük képernyőt
                const thankYouScreen = document.getElementById('thankYouScreen');
                thankYouScreen.style.display = 'flex';
                thankYouScreen.style.opacity = '1';
                
                // 2.5 másodperc után kezdjük a slide animációt
                setTimeout(() => {
                    // Beállítjuk és elindítjuk a slide animációt
                    document.body.style.transform = 'translateX(0)';
                    document.body.style.transition = 'transform 2s ease-in-out'; // 2 másodperces animáció
                    document.body.style.transform = 'translateX(-100%)';
                    
                    // 2 másodperc múlva (a slide közben) kezdjük el halványítani a loading screen-t
                    setTimeout(() => {
                        thankYouScreen.style.transition = 'opacity 0.5s ease-out';
                        thankYouScreen.style.opacity = '0';
                        
                        // Az átmenet végén átirányítunk
                        setTimeout(() => {
                            sessionStorage.setItem('skipLoadingScreen', 'true');
                            window.location.href = '../home.php';
                        }, 500);
                    }, 1500);
                }, 2500);
            } else {
                throw new Error(data.error || 'Ismeretlen hiba történt');
            }
        })
        .catch(error => {
            console.error('Hiba:', error);
            alert('Hiba történt a fiók törlésekor: ' + error.message);
        });
    }

    // Ha a felhasználó a modalon kívülre kattint, bezárjuk azt
    window.onclick = function(event) {
        const modal = document.getElementById('deleteAccountModal');
        if (event.target == modal) {
            closeDeleteModal();
        }
    }

    // Dropdown menü kezelése
    function toggleUserMenu() {
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('active');
    }
    
    // Kattintás esemény figyelése a dokumentumon
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('userDropdown');
        const profileIcon = document.querySelector('.profile-icon');
        
        // Ellenőrizzük, hogy létezik-e a dropdown és a profileIcon
        if (dropdown && profileIcon) {
            // Ha a kattintás nem a profilikon területén belül történt és a dropdown nyitva van
            if (!profileIcon.contains(event.target) && dropdown.classList.contains('active')) {
                dropdown.classList.remove('active');
            }
        }
    });

    // Menü kezelése
    document.querySelectorAll('.menu-item').forEach(item => {
        item.addEventListener('click', () => {
            // Aktív menüpont kezelése
            document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');

            // Tartalom megjelenítése
            const target = item.dataset.target;
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(`${target}-section`).classList.add('active');
        });
    });

    document.getElementById('deleteConfirm').addEventListener('change', function() {
        document.querySelector('.delete-btn').disabled = !this.checked;
    });

    // Jelszó láthatóság kapcsolása
    function togglePasswordVisibility(inputId, icon) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.src = '../assets/img/view.png';
        } else {
            input.type = 'password';
            icon.src = '../assets/img/hide.png';
        }
    }
</script>

<html lang="<?php echo htmlspecialchars($currentLang); ?>"
    <?php if (!empty($translations)): ?>
        <?php foreach ($translations as $key => $value): ?>
            data-translations-<?php echo htmlspecialchars($key); ?>="<?php echo htmlspecialchars($value); ?>"
        <?php endforeach; ?>
    <?php endif; ?>>

<?php
// Footer betöltése, ha van
// require_once '../includes/layout/footer.php';
?> 