<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Ellenőrizzük, hogy van-e email és token paraméter
if (!isset($_GET['email']) || !isset($_GET['token'])) {
    header('Location: ../index.php');
    exit;
}

$email = $_GET['email'];
$token = $_GET['token'];

try {
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Ellenőrizzük a meghívó érvényességét
    $stmt = $db->prepare("
        SELECT i.*, r.role_name, c.company_name, c.company_address, c.company_email
        FROM invitations i
        JOIN roles r ON i.role_id = r.id
        JOIN company c ON i.company_id = c.id
        WHERE i.email = :email 
        AND i.invitation_token = :token 
        AND i.expiration_date > NOW()
        AND i.is_used = FALSE
        LIMIT 1
    ");
    
    $stmt->execute([
        ':email' => $email,
        ':token' => $token
    ]);
    
    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invitation) {
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <title>Meghívó érvénytelen</title>
            <link rel="stylesheet" href="../assets/css/style.css">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container" style="text-align:center;">
                <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#e74c3c;margin-bottom:1rem;"></i>
                <h1>Meghívó érvénytelen vagy már felhasználták</h1>
                <div class="error" style="margin: 1.5rem auto; max-width: 400px;">
                    A meghívó link lejárt, érvénytelen vagy már felhasználták.<br>
                    Kérjük, kérj új meghívót a céged adminisztrátorától!
                </div>
                <div class="text-center">
                    <a href="../home.php" class="btn" style="max-width:200px;display:inline-block;color:#fff;">Vissza a főoldalra</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
} catch (PDOException $e) {
    die('Adatbázis hiba: ' . $e->getMessage());
}

// Ha van POST kérés, feldolgozzuk a regisztrációt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    
    $errors = [];
    
    // Validáció
    if (empty($firstname)) $errors[] = 'A keresztnév megadása kötelező.';
    if (empty($lastname)) $errors[] = 'A vezetéknév megadása kötelező.';
    if (empty($password)) $errors[] = 'A jelszó megadása kötelező.';
    if (empty($telephone)) $errors[] = 'A telefonszám megadása kötelező.';
    if ($password !== $password_confirm) $errors[] = 'A jelszavak nem egyeznek.';
    if (strlen($password) < 8) $errors[] = 'A jelszónak legalább 8 karakter hosszúnak kell lennie.';
    if (!preg_match('/[A-Z]/', $password)) $errors[] = 'A jelszónak tartalmaznia kell legalább egy nagybetűt.';
    if (!preg_match('/[a-z]/', $password)) $errors[] = 'A jelszónak tartalmaznia kell legalább egy kisbetűt.';
    if (!preg_match('/[0-9]/', $password)) $errors[] = 'A jelszónak tartalmaznia kell legalább egy számot.';
    
    // Profilkép feltöltés kezelése
    $profile_image = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Csak JPG, PNG és GIF képek engedélyezettek.';
        } else {
            $newFilename = uniqid() . '.' . $ext;
            $uploadPath = '../uploads/profiles/';
            
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath . $newFilename)) {
                $profile_image = $newFilename;
            } else {
                $errors[] = 'Hiba történt a kép feltöltése során.';
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Debug: Jelszó hash létrehozása előtt
            error_log('Password before hash: ' . $password);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            error_log('Password after hash: ' . $hashed_password);
            
            // Felhasználó létrehozása
            $stmt = $db->prepare("
                INSERT INTO user (
                    email, 
                    password, 
                    firstname, 
                    lastname, 
                    telephone,
                    profile_pic,
                    company_id,
                    current_status_id,
                    connect_date,
                    created_date
                ) VALUES (
                    :email,
                    :password,
                    :firstname,
                    :lastname,
                    :telephone,
                    :profile_pic,
                    :company_id,
                    1,
                    NOW(),
                    NOW()
                )
            ");
            
            $stmt->execute([
                ':email' => $email,
                ':password' => $hashed_password,
                ':firstname' => $firstname,
                ':lastname' => $lastname,
                ':telephone' => $telephone,
                ':profile_pic' => $profile_image ?? 'user.png',
                ':company_id' => $invitation['company_id']
            ]);
            
            $user_id = $db->lastInsertId();
            
            // Szerepkör hozzárendelése
            $stmt = $db->prepare("
                INSERT INTO user_to_roles (user_id, role_id)
                VALUES (:user_id, :role_id)
            ");
            
            $stmt->execute([
                ':user_id' => $user_id,
                ':role_id' => $invitation['role_id']
            ]);
            
            // Meghívó megjelölése használtként
            $stmt = $db->prepare("
                UPDATE invitations 
                SET is_used = TRUE 
                WHERE id = :invitation_id
            ");
            
            $stmt->execute([':invitation_id' => $invitation['id']]);
            
            $db->commit();
            
            // Átirányítás a bejelentkezési oldalra
            header('Location: login.php?registration=success');
            exit;
            
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = 'Hiba történt a regisztráció során: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Munkavállalói Regisztráció</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .main-title {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
            width: 100%;
            max-width: 1200px;
        }

        .main-title h1 {
            font-size: 2.5em;
            margin: 0;
            font-weight: 600;
        }

        .main-title p {
            font-size: 1.1em;
            color: #7f8c8d;
            margin: 10px 0 0 0;
        }

        .page-content {
            display: flex;
            gap: 0;
            max-width: 1200px;
            width: 100%;
            position: relative;
            justify-content: center;
            align-items: flex-start;
        }
        
        .register-container {
            flex: 0 1 700px;
            max-width: 700px;
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            position: relative;
            left: 0;
        }

        .company-info-wrapper {
            width: 400px;
            position: absolute;
            right: -200px;
            top: 0;
        }

        .company-info {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }

        .company-info h3 {
            color: #2c3e50;
            font-size: 1.3em;
            margin: 0 0 25px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .company-info h3 i {
            color: #3498db;
            font-size: 1.1em;
        }

        .company-info p {
            margin: 15px 0;
            color: #2c3e50;
            font-size: 1em;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            line-height: 1.4;
            flex-wrap: wrap;
        }

        .company-info i {
            width: 20px;
            color: #3498db;
            margin-top: 3px;
        }

        .company-info .highlight {
            color: #3498db;
            font-weight: 600;
            width: 100%;
            margin-top: 5px;
            word-break: break-word;
        }
        
        .profile-section {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .profile-upload {
            display: inline-block;
            text-align: center;
            width: 150px;
            height: 150px;
            position: relative;
            cursor: pointer;
            border-radius: 50%;
            overflow: hidden;
        }
        
        .profile-upload .upload-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: #f8f9fa;
            border: 2px dashed #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        
        .profile-upload:hover .upload-placeholder {
            border-color: #6c757d;
            background-color: #f8f9fa;
        }

        .profile-upload .hover-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(108, 117, 125, 0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 50%;
        }

        .profile-upload:hover .hover-overlay {
            opacity: 1;
        }

        .hover-overlay i {
            font-size: 2em;
            color: white;
            margin-bottom: 8px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .hover-overlay span {
            color: white;
            font-size: 0.9em;
            text-align: center;
            padding: 0 10px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .profile-upload i {
            font-size: 3em;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .profile-upload img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .profile-upload .upload-text {
            font-size: 0.85em;
            color: #7f8c8d;
            margin-top: 10px;
            display: block;
        }
        
        .role-info {
            text-align: center;
            margin: 15px 0;
            padding: 12px;
            background: #f1f9ff;
            border-radius: 8px;
            color: #2c3e50;
        }
        
        .role-info i {
            color: #3498db;
            margin-right: 8px;
        }
        
        .role-info .role-text {
            font-weight: 600;
            color: #3498db;
        }
        
        .form-section {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-group.email-group {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .form-group.email-group input {
            background: #f8f9fa;
            cursor: not-allowed;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .form-group {
            margin-bottom: 12px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.9em;
        }
        
        .form-group input:not([type="file"]) {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .btn-register {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-size: 1em;
            font-weight: 600;
            transition: background-color 0.3s ease;
            margin-top: 15px;
        }
        
        .btn-register:hover {
            background: #2980b9;
        }
        
        .error-message {
            background: #fff5f5;
            color: #e74c3c;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 4px solid #e74c3c;
            font-size: 0.9em;
        }
        
        .error-message p {
            margin: 3px 0;
        }
        
        .form-group.phone-group {
            display: flex;
            gap: 0;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            height: 40px;
        }

        .country-select {
            min-width: 90px;
            padding: 0 30px 0 10px;
            border: none;
            border-right: 1px solid #e9ecef;
            font-size: 0.95rem;
            background-color: white;
            cursor: pointer;
            transition: border-color 0.3s ease;
            color: #2c3e50;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%232c3e50' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }

        .country-select:focus {
            outline: none;
        }

        .phone-input-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
        }

        .phone-input {
            width: 100%;
            height: 100%;
            padding: 0 15px;
            border: none;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
            color: #2c3e50;
        }

        .phone-input:focus {
            outline: none;
        }

        .form-group.phone-group:focus-within {
            border-color: #3498db;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            width: 24px;
            height: 24px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }

        .password-toggle:hover {
            opacity: 1;
        }

        .password-toggle.hide {
            background-image: url('../assets/img/hide.png');
        }

        .password-toggle.view {
            background-image: url('../assets/img/view.png');
        }

        .form-group .password-field-wrapper {
            position: relative;
            width: 100%;
        }

        .form-group .password-field-wrapper input {
            padding-right: 45px;
            width: 100%;
            box-sizing: border-box;
        }
        
        @media (max-width: 1200px) {
            .page-content {
                flex-direction: column;
                align-items: center;
                gap: 30px;
            }
            
            .company-info-wrapper {
                width: 100%;
                max-width: 700px;
                position: static;
                margin-bottom: 0;
            }
            
            .register-container {
                width: 100%;
                left: 0;
            }

            .company-info p {
                font-size: 0.95em;
            }
        }
    </style>
</head>
<body>
    <div class="main-title">
        <h1>Regisztráció</h1>
        <p>Üdvözöljük a regisztrációs folyamatban</p>
    </div>

    <div class="page-content">
        <div class="register-container">
            <div class="profile-section">
                <div class="profile-upload" onclick="document.getElementById('profile_image').click()">
                    <div class="upload-placeholder" id="uploadPlaceholder">
                        <i class="fas fa-user-circle"></i>
                        <span class="upload-text">Profilkép feltöltése</span>
                    </div>
                    <input type="file" id="profile_image" name="profile_image" style="display: none" accept="image/*">
                    <div id="preview"></div>
                </div>
            </div>

            <div class="role-info">
                <i class="fas fa-user-tag"></i>
                Az Ön szerepköre: <span class="role-text"><?php echo htmlspecialchars($invitation['role_name']); ?></span>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <?php foreach ($errors as $error): ?>
                        <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="lastname"><i class="fas fa-user"></i> Vezetéknév</label>
                        <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($lastname ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="firstname"><i class="fas fa-user"></i> Keresztnév</label>
                        <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($firstname ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-group email-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email cím</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($email); ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label for="telephone"><i class="fas fa-phone"></i> Telefonszám</label>
                    <div class="phone-group">
                        <select class="country-select" id="country_code" name="country_code">
                            <option value="36" selected>HUN</option>
                            <option value="43">AUT</option>
                            <option value="385">CRO</option>
                            <option value="44">ENG</option>
                            <option value="33">FRA</option>
                            <option value="49">GER</option>
                            <option value="39">ITA</option>
                            <option value="48">POL</option>
                            <option value="40">ROM</option>
                            <option value="381">SRB</option>
                            <option value="421">SVK</option>
                            <option value="34">ESP</option>
                            <option value="1">USA</option>
                        </select>
                        <div class="phone-input-wrapper">
                            <input type="tel" id="telephone" name="telephone" class="phone-input" 
                                   value="<?php echo htmlspecialchars($telephone ?? ''); ?>" 
                                   placeholder="20 420 6942"
                                   pattern="[0-9]*"
                                   inputmode="numeric"
                                   required>
                        </div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Jelszó</label>
                        <div class="password-field-wrapper">
                            <input type="password" id="password" name="password" required onkeyup="checkPasswordStrength()">
                            <span class="password-toggle hide" onclick="togglePasswordVisibility('password')" title="Jelszó mutatása/elrejtése"></span>
                        </div>
                        <div style="width: 100%; height: 5px; background-color: #eee; border-radius: 3px; margin-top: 5px; overflow: hidden;">
                            <div id="password-strength-meter" style="height: 100%; width: 0%; transition: all 0.3s ease;"></div>
                        </div>
                        <div id="password-strength-text" style="font-size: 0.8rem; margin-top: 5px;"></div>
                        
                        <ul class="password-requirements" style="font-size: 0.8rem; color: #6c757d; margin-top: 8px; padding-left: 20px;">
                            <li id="length-check">Legalább 8 karakter</li>
                            <li id="uppercase-check">Legalább egy nagybetű</li>
                            <li id="lowercase-check">Legalább egy kisbetű</li>
                            <li id="number-check">Legalább egy szám</li>
                        </ul>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm"><i class="fas fa-lock"></i> Jelszó megerősítése</label>
                        <div class="password-field-wrapper">
                            <input type="password" id="password_confirm" name="password_confirm" required onkeyup="checkPasswordMatch()">
                            <span class="password-toggle hide" onclick="togglePasswordVisibility('password_confirm')" title="Jelszó mutatása/elrejtése"></span>
                        </div>
                        <div id="password-match-text" style="font-size: 0.8rem; margin-top: 5px;"></div>
                    </div>
                </div>
                
                <button type="submit" class="btn-register">
                    <i class="fas fa-user-plus"></i> Regisztráció
                </button>
            </form>
        </div>

        <div class="company-info-wrapper">
            <div class="company-info">
                <h3><i class="fas fa-building"></i> Vállalati információk</h3>
                <p><i class="fas fa-building"></i> Vállalat neve <span class="highlight"><?php echo htmlspecialchars($invitation['company_name']); ?></span></p>
                <p><i class="fas fa-map-marker-alt"></i> Cím <span class="highlight"><?php echo htmlspecialchars($invitation['company_address']); ?></span></p>
                <p><i class="fas fa-envelope"></i> Email <span class="highlight"><?php echo htmlspecialchars($invitation['company_email']); ?></span></p>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('preview');
                    const placeholder = document.getElementById('uploadPlaceholder');
                    if (placeholder) placeholder.style.display = 'none';
                    
                    preview.innerHTML = `
                        <img src="${e.target.result}" 
                             alt="Profilkép előnézet">
                        <div class="hover-overlay">
                            <i class="fas fa-camera"></i>
                            <span>Profilkép módosítása</span>
                        </div>
                    `;
                }
                reader.readAsDataURL(file);
            }
        });

        // Telefonszám input kezelése
        document.getElementById('telephone').addEventListener('input', function(e) {
            // Csak számokat engedünk meg
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Maximum hossz ellenőrzése országonként
            const maxLengths = {
                '43': 11,  // AUT
                '385': 9,  // CRO
                '44': 11,  // ENG
                '33': 9,   // FRA
                '49': 11,  // GER
                '36': 9,   // HUN
                '39': 10,  // ITA
                '48': 9,   // POL
                '40': 9,   // ROM
                '381': 9,  // SRB
                '421': 9,  // SVK
                '34': 9,   // ESP
                '1': 10    // USA
            };
            
            const countryCode = document.getElementById('country_code').value;
            const maxLength = maxLengths[countryCode] || 9;
            
            if (this.value.length > maxLength) {
                this.value = this.value.slice(0, maxLength);
            }
        });

        // Országkód változás kezelése
        document.getElementById('country_code').addEventListener('change', function(e) {
            const phoneInput = document.getElementById('telephone');
            phoneInput.value = ''; // Töröljük a jelenlegi értéket
            
            // Placeholder beállítása az országkód alapján
            const placeholders = {
                '43': '664 1234567',    // AUT
                '385': '91 234 5678',  // CRO
                '44': '7700 123456',    // ENG
                '33': '6 12 34 56 78',  // FRA
                '49': '151 1234567',    // GER
                '36': '20 420 6942',     // HUN
                '39': '312 345 6789',   // ITA
                '48': '512 345 678',    // POL
                '40': '712 345 678',    // ROM
                '381': '63 1234567',   // SRB
                '421': '903 123 456',  // SVK
                '34': '612 345 678',    // ESP
                '1': '+1 (555) 123-4567'    // USA
            };
            
            phoneInput.placeholder = placeholders[this.value] || '+36 20 4206942';
        });

        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const toggle = input.parentElement.querySelector('.password-toggle');
            
            if (input.type === 'password') {
                input.type = 'text';
                toggle.classList.remove('hide');
                toggle.classList.add('view');
            } else {
                input.type = 'password';
                toggle.classList.remove('view');
                toggle.classList.add('hide');
            }
        }

        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const meter = document.getElementById('password-strength-meter');
            const strengthText = document.getElementById('password-strength-text');
            
            // Check individual requirements
            document.getElementById('length-check').style.color = password.length >= 8 ? '#28a745' : '#6c757d';
            document.getElementById('uppercase-check').style.color = /[A-Z]/.test(password) ? '#28a745' : '#6c757d';
            document.getElementById('lowercase-check').style.color = /[a-z]/.test(password) ? '#28a745' : '#6c757d';
            document.getElementById('number-check').style.color = /[0-9]/.test(password) ? '#28a745' : '#6c757d';
            
            // Calculate strength
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[a-z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            
            // Update meter and text
            meter.style.width = strength + '%';
            
            if (strength === 0) {
                meter.style.backgroundColor = '#eee';
                strengthText.textContent = '';
            } else if (strength <= 50) {
                meter.style.backgroundColor = '#dc3545';
                strengthText.textContent = 'Gyenge';
                strengthText.style.color = '#dc3545';
            } else if (strength <= 75) {
                meter.style.backgroundColor = '#ffc107';
                strengthText.textContent = 'Közepes';
                strengthText.style.color = '#ffc107';
            } else {
                meter.style.backgroundColor = '#28a745';
                strengthText.textContent = 'Erős';
                strengthText.style.color = '#28a745';
            }
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirm').value;
            const matchText = document.getElementById('password-match-text');
            
            // Csak akkor ellenőrizzük, ha van első jelszó és elkezdték írni a másodikat
            if (password && confirmPassword) {
                if (password === confirmPassword) {
                    matchText.textContent = 'A jelszavak egyeznek';
                    matchText.style.color = '#28a745';
                } else {
                    matchText.textContent = 'A jelszavak nem egyeznek';
                    matchText.style.color = '#dc3545';
                }
            } else {
                matchText.textContent = '';
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('password_confirm');
            
            if (passwordField) {
                passwordField.addEventListener('input', checkPasswordStrength);
            }
            
            if (confirmPasswordField) {
                confirmPasswordField.addEventListener('input', checkPasswordMatch);
            }
        });
    </script>
</body>
</html> 