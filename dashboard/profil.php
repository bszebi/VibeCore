<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/language_handler.php';
require_once '../includes/db.php';

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Felhasználói adatok lekérése
try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT 
            u.*,
            c.company_name,
            c.company_address,
            c.company_email,
            c.company_telephone,
            GROUP_CONCAT(r.role_name) as role_names,
            sp.name as subscription_plan_name,
            sp.price as subscription_price,
            bi.name as billing_interval,
            s.trial_end_date
        FROM user u
        LEFT JOIN company c ON u.company_id = c.id
        LEFT JOIN user_to_roles utr ON u.id = utr.user_id
        LEFT JOIN roles r ON utr.role_id = r.id
        LEFT JOIN subscriptions s ON u.id = s.user_id AND s.subscription_status_id = 1
        LEFT JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
        LEFT JOIN billing_intervals bi ON sp.billing_interval_id = bi.id
        WHERE u.id = :user_id
        GROUP BY u.id, c.company_name, c.company_address, c.company_email, c.company_telephone, sp.name, sp.price, bi.name
    ");
    
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Ha nincs felhasználó, állítsuk be az alapértelmezett értékeket
        $user = [
            'profile_pic' => 'user.png',
            'firstname' => '',
            'lastname' => '',
            'email' => '',
            'telephone' => '',
            'company_name' => '',
            'company_address' => '',
            'company_email' => '',
            'company_telephone' => '',
            'role_names' => '',
            'subscription_plan_name' => '',
            'subscription_price' => '',
            'billing_interval' => '',
            'trial_end_date' => null
        ];
    }

    // Profil frissítése ha van POST kérés
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
        try {
            // Ellenőrizzük, hogy léteznek-e a POST adatok
            $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : $user['email'];
            $telephone = isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : $user['telephone'];
            
            // Adatok frissítése
            $update_stmt = $db->prepare("
                UPDATE user 
                SET email = :email, 
                    telephone = :telephone
                WHERE id = :user_id
            ");
            
            if ($update_stmt->execute([
                ':email' => $email,
                ':telephone' => $telephone,
                ':user_id' => $_SESSION['user_id']
            ])) {
                $success = 'A profil sikeresen frissítve!';
                // Frissítjük a user változót az új adatokkal
                $user['email'] = $email;
                $user['telephone'] = $telephone;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // AJAX profilkép kezelése
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        try {
            if ($_POST['action'] === 'update_profile_pic' || $_POST['action'] === 'update_company_logo') {
                // Ellenőrizzük a PHP beállításokat és a feltöltési korlátokat
                if (ini_get('file_uploads') != 1) {
                    throw new Exception('A fájl feltöltés nincs engedélyezve a szerveren.');
                }

                $fileKey = $_POST['action'] === 'update_profile_pic' ? 'profile_pic' : 'company_logo';
                if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                    $uploadErrors = array(
                        UPLOAD_ERR_INI_SIZE => 'A fájl mérete meghaladja a PHP.ini-ben beállított maximális méretet.',
                        UPLOAD_ERR_FORM_SIZE => 'A fájl mérete meghaladja a form-ban megadott maximális méretet.',
                        UPLOAD_ERR_PARTIAL => 'A fájl csak részlegesen lett feltöltve.',
                        UPLOAD_ERR_NO_FILE => 'Nem lett fájl kiválasztva.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Hiányzik az ideiglenes mappa.',
                        UPLOAD_ERR_CANT_WRITE => 'Nem sikerült a fájl írása a lemezre.',
                        UPLOAD_ERR_EXTENSION => 'Egy PHP kiterjesztés leállította a fájl feltöltését.'
                    );
                    $errorMessage = isset($uploadErrors[$_FILES[$fileKey]['error']]) 
                        ? $uploadErrors[$_FILES[$fileKey]['error']] 
                        : 'Ismeretlen hiba történt a feltöltés során.';
                    throw new Exception($errorMessage);
                }

                // Ha vállalati logó feltöltésről van szó, ellenőrizzük a jogosultságokat
                if ($_POST['action'] === 'update_company_logo') {
                    $user_roles = explode(',', $_SESSION['user_role']);
                    $can_edit_logo = false;
                    foreach ($user_roles as $role) {
                        $translatedOwnerRole = translate('Cég tulajdonos');
                        $translatedManagerRole = translate('Manager');
                        if (trim($role) === $translatedOwnerRole || trim($role) === $translatedManagerRole) {
                            $can_edit_logo = true;
                            break;
                        }
                    }
                    
                    if (!$can_edit_logo) {
                        throw new Exception('Nincs jogosultsága a vállalati logó módosításához!');
                    }
                }

                // Ellenőrizzük a fájl méretét (pl. max 5MB)
                $maxFileSize = 5 * 1024 * 1024; // 5MB
                if ($_FILES[$fileKey]['size'] > $maxFileSize) {
                    throw new Exception('A fájl mérete nem lehet nagyobb mint 5MB.');
                }

                $upload_dir = $_POST['action'] === 'update_profile_pic' ? '../uploads/profiles/' : '../uploads/company_logos/';
                
                // Ellenőrizzük és létrehozzuk a mappákat, ha nem léteznek
                if (!file_exists('../uploads')) {
                    if (!@mkdir('../uploads', 0777, true)) {
                        throw new Exception('Nem sikerült létrehozni az uploads mappát.');
                    }
                }
                if (!file_exists($upload_dir)) {
                    if (!@mkdir($upload_dir, 0777, true)) {
                        throw new Exception('Nem sikerült létrehozni a célmappát.');
                    }
                }
                
                // Ellenőrizzük az írási jogosultságot
                if (!is_writable($upload_dir)) {
                    throw new Exception('A feltöltési mappa nem írható. Kérem, ellenőrizze a jogosultságokat.');
                }

                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $_FILES[$fileKey]['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($mime_type, $allowed_types)) {
                    throw new Exception('Csak JPG, PNG és GIF képeket lehet feltölteni!');
                }

                $file_extension = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
                $prefix = $_POST['action'] === 'update_profile_pic' ? 'profile_' : 'company_';
                $new_filename = $prefix . time() . '_' . uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $new_filename;

                if ($_POST['action'] === 'update_profile_pic') {
                    // Régi profilkép törlése
                    $stmt = $db->prepare("SELECT profile_pic FROM user WHERE id = :user_id");
                    $stmt->execute([':user_id' => $_SESSION['user_id']]);
                    $old_pic = $stmt->fetchColumn();
                    
                    if ($old_pic !== 'user.png' && file_exists($upload_dir . $old_pic)) {
                        @unlink($upload_dir . $old_pic);
                    }

                    if (!@move_uploaded_file($_FILES[$fileKey]['tmp_name'], $target_path)) {
                        throw new Exception('Nem sikerült a fájl mentése.');
                    }

                    $stmt = $db->prepare("UPDATE user SET profile_pic = :profile_pic WHERE id = :user_id");
                    $stmt->execute([
                        ':profile_pic' => $new_filename,
                        ':user_id' => $_SESSION['user_id']
                    ]);

                    $_SESSION['profile_pic'] = $new_filename;
                } else {
                    // Vállalati logó kezelése
                    $stmt = $db->prepare("SELECT company_id FROM user WHERE id = :user_id");
                    $stmt->execute([':user_id' => $_SESSION['user_id']]);
                    $company_id = $stmt->fetchColumn();

                    if (!$company_id) {
                        throw new Exception('Nincs hozzárendelt vállalat!');
                    }

                    // Régi logó törlése
                    $stmt = $db->prepare("SELECT profile_picture FROM company WHERE id = :company_id");
                    $stmt->execute([':company_id' => $company_id]);
                    $old_logo = $stmt->fetchColumn();
                    
                    if ($old_logo !== 'default_company.png' && file_exists($upload_dir . $old_logo)) {
                        @unlink($upload_dir . $old_logo);
                    }

                    if (!@move_uploaded_file($_FILES[$fileKey]['tmp_name'], $target_path)) {
                        throw new Exception('Nem sikerült a fájl mentése.');
                    }

                    $stmt = $db->prepare("UPDATE company SET profile_picture = :profile_picture WHERE id = :company_id");
                    $stmt->execute([
                        ':profile_picture' => $new_filename,
                        ':company_id' => $company_id
                    ]);
                }

                echo json_encode([
                    'success' => true,
                    'profile_pic_path' => $target_path,
                    'message' => $_POST['action'] === 'update_profile_pic' ? 
                        'A profilkép sikeresen frissítve!' : 
                        'A vállalati logó sikeresen frissítve!'
                ]);
                exit;

            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }

    // AJAX kérés kezelése az adatok frissítéséhez
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['field'])) {
        header('Content-Type: application/json');
        try {
            $field = $_POST['field'];
            $value = $_POST['value'];
            
            // Ellenőrizzük, hogy melyik mezőt kell frissíteni
            if ($field === 'email') {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Érvénytelen email cím!');
                }
            }
            
            // Frissítjük az adatbázist
            $stmt = $db->prepare("UPDATE user SET $field = :value WHERE id = :user_id");
            $stmt->execute([
                ':value' => $value,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // Státuszok lekérése
    $statusStmt = $db->prepare("
        SELECT s.id, s.name 
        FROM status s 
        ORDER BY s.id
    ");
    $statusStmt->execute();
    $statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    // Felhasználó aktuális státuszának lekérése
    $currentStatusStmt = $db->prepare("
        SELECT s.id, s.name 
        FROM status s 
        JOIN user u ON u.current_status_id = s.id 
        WHERE u.id = :user_id
    ");
    $currentStatusStmt->execute([':user_id' => $_SESSION['user_id']]);
    $currentStatus = $currentStatusStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Adatbázis hiba: ' . $e->getMessage();
}

// AJAX státusz frissítés kezelése
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    try {
        if (!isset($_POST['status_id'])) {
            throw new Exception('Hiányzó status_id paraméter');
        }
        
        $newStatusId = (int)$_POST['status_id'];
        $currentTimestamp = date('Y-m-d H:i:s');
        
        // Tranzakció kezdése
        $db->beginTransaction();
        
        // 1. Lekérjük az aktuális nyitott státusz bejegyzést
        $getCurrentStatusStmt = $db->prepare("
            SELECT sh.id, sh.status_id, sh.status_startdate
            FROM status_history sh
            WHERE sh.user_id = :user_id 
            AND sh.status_enddate IS NULL
            ORDER BY sh.status_startdate DESC 
            LIMIT 1
        ");
        $getCurrentStatusStmt->execute([':user_id' => $_SESSION['user_id']]);
        $currentStatus = $getCurrentStatusStmt->fetch(PDO::FETCH_ASSOC);

        // 2. Lezárjuk az előző státuszt
        if ($currentStatus) {
            $updateOldStmt = $db->prepare("
                UPDATE status_history 
                SET status_enddate = :end_date 
                WHERE id = :history_id
            ");
            $updateOldStmt->execute([
                ':end_date' => $currentTimestamp,
                ':history_id' => $currentStatus['id']
            ]);
        }
        
        // 3. Új státusz bejegyzés létrehozása
        $insertHistoryStmt = $db->prepare("
            INSERT INTO status_history (
                user_id, 
                status_id, 
                status_startdate,
                status_enddate
            ) VALUES (
                :user_id, 
                :status_id, 
                :start_date,
                NULL
            )
        ");
        
        $insertHistoryStmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':status_id' => $newStatusId,
            ':start_date' => $currentTimestamp
        ]);
        
        // 4. Felhasználó aktuális státuszának frissítése
        $updateUserStmt = $db->prepare("
            UPDATE user 
            SET current_status_id = :status_id 
            WHERE id = :user_id
        ");
        
        $updateUserStmt->execute([
            ':status_id' => $newStatusId,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        $db->commit();
        
        // Lekérjük az új státusz nevét a visszajelzéshez
        $getStatusNameStmt = $db->prepare("SELECT name FROM status WHERE id = :status_id");
        $getStatusNameStmt->execute([':status_id' => $newStatusId]);
        $statusName = $getStatusNameStmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'message' => 'Státusz sikeresen módosítva: ' . $statusName,
            'debug' => [
                'currentStatus' => $currentStatus,
                'newStatusId' => $newStatusId,
                'timestamp' => $currentTimestamp
            ]
        ]);
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Státusz frissítési hiba: ' . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Hiba történt a státusz módosítása során!',
            'debug' => [
                'errorMessage' => $e->getMessage(),
                'errorTrace' => $e->getTraceAsString()
            ]
        ]);
        exit;
    }
}

// A fájl elején adjuk hozzá ezt a függvényt
function safeEcho($value) {
    return htmlspecialchars($value ?? '');
}

require_once '../includes/layout/header.php';
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language'] ?? 'hu'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="assets/img/resume.png">
    <title><?php echo translate('profil'); ?> - VibeCore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-container {
            max-width: 90%;  /* A képernyő 90%-a */
            margin: 1rem auto;  /* Kisebb margó */
            padding: 1.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .profile-picture-container {
            position: relative;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            cursor: pointer;
        }

        .profile-picture {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-picture-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .profile-picture-overlay span {
            color: white;
            text-align: center;
            padding: 10px;
            font-size: 14px;
        }

        .profile-picture-container:hover .profile-picture-overlay {
            opacity: 1;
        }

        .profile-picture-label {
            display: block;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .profile-info h1 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }

        .role-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background: #007bff;
            color: white;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .profile-section {
            margin-bottom: 1.5rem;  /* Kisebb margó */
            padding: 0 1rem;
        }

        .profile-section h2 {
            color: #444;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
            width: 100%;
        }

        .form-group label {
            margin-bottom: 0.3rem;
        }

        .form-group input {
            padding: 0.5rem;
        }

        .company-info {
            background: transparent;
            padding: 1.5rem;
            border-radius: 8px;
            width: 100%;
        }

        .company-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .company-logo-container {
            width: 200px;
            height: 150px;
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            cursor: pointer;
            overflow: hidden;
        }

        .company-logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .company-details {
            width: 100%;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .company-details p {
            margin: 10px 0;
            padding: 8px;
            line-height: 1.4;
        }

        .company-details p:last-child {
            border-bottom: none;
        }

        .company-details strong {
            margin-right: 5px;
            min-width: 140px;
            display: inline-block;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .input-group input {
            flex-grow: 1;
            background-color: #f5f5f5;
        }

        .input-group input:disabled {
            background-color: #f5f5f5;
            color: #333;
            cursor: not-allowed;
        }

        .edit-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
        }

        .edit-btn img {
            width: 20px;
            height: 20px;
        }

        .save-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            display: none;
        }

        .cancel-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            display: none;
        }

        /* Kompaktabb form elemek */
        .form-group input {
            padding: 0.5rem;
        }

        .form-group label {
            margin-bottom: 0.3rem;
        }

        /* Két oszlopos elrendezés */
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 1fr;  /* Két egyenlő oszlop */
            gap: 2rem;
            margin-top: 1rem;
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
            padding: 10px;  /* Csökkentett padding */
            transition: transform 0.2s;
            z-index: 1000;
            background: none;  /* Eltávolítottuk a fehér hátteret */
            box-shadow: none;  /* Eltávolítottuk az árnyékot */
        }

        .home-link:hover {
            transform: translateY(-2px);
        }

        .home-link img {
            width: 40px;    /* Növeltük a képméretet */
            height: 40px;   /* Növeltük a képméretet */
        }

        .home-link span {
            font-size: 16px;  /* Növeltük a betűméretet */
            font-weight: 500; /* Kicsit vastagabb betű */
        }

        .settings-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
        }

        .settings-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
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

        /* Frissítsük a header profilkép méretét */
        .profile-icon {
            width: 45px !important;  /* !important hogy felülírja az esetleges más stílusokat */
            height: 45px !important;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 4px;
            color: white;
            z-index: 1000;
            animation: slideIn 0.5s ease-out;
        }

        .notification.success {
            background-color: #4CAF50;
        }

        .notification.error {
            background-color: #f44336;
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

        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }

        .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 5px rgba(76,175,80,0.2);
        }

        .logo-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            color: white;
        }

        .company-logo-container:hover .logo-overlay {
            opacity: 1;
        }

        .logo-overlay i {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .logo-overlay span {
            font-size: 14px;
            text-align: center;
            padding: 0 10px;
        }

        .profile-header p {
            margin: 5px 0;
            color: #666;
        }
        
        .subscription-badge {
            display: inline-block;
            padding: 4px 12px;
            background-color: #4CAF50;
            color: white !important;
            border-radius: 15px;
            font-size: 0.9em;
            margin-top: 8px !important;
        }

        /* Role badge styles */
        .role-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 5px 0;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            color: white !important;
            border-radius: 15px;
            font-size: 0.9em;
        }

        .role-badge.owner {
            background-color: #e74c3c;  /* Red for owner */
        }

        .role-badge.manager {
            background-color: #3498db;  /* Blue for manager */
        }

        .role-badge.technician {
            background-color: #2ecc71;  /* Green for technicians */
        }

        .role-badge.maintenance {
            background-color: #f39c12;  /* Orange for maintenance */
        }

        .role-badge.stagehand {
            background-color: #9b59b6;  /* Purple for stagehand */
        }

        .role-badge.default {
            background-color: #95a5a6;  /* Gray for other roles */
        }
    </style>
</head>
<body>
   

    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-picture-container">
                <?php
                $profile_pic = $user['profile_pic'] ?? 'user.png';
                $profile_pic_path = '../uploads/profiles/' . $profile_pic;
                $is_default = $profile_pic === 'user.png' || !file_exists($profile_pic_path);
                
                if ($is_default) {
                    $profile_pic_path = '../assets/img/user.png';
                }
                ?>
                <label for="profile_pic_input" class="profile-picture-label">
                    <img src="<?php echo htmlspecialchars($profile_pic_path); ?>" alt="<?php echo translate('profile_pic'); ?>" class="profile-picture">
                    <div class="profile-picture-overlay">
                        <span><?php echo $is_default ? translate('upload_profile_pic') : translate('change_profile_pic'); ?></span>
                    </div>
                </label>
                <input type="file" id="profile_pic_input" name="profile_pic" accept="image/*" style="display: none;">
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['lastname'] . ' ' . $user['firstname']); ?></h1>
                <?php
                if (!empty($user['role_names'])) {
                    echo '<div class="role-badges">';
                    $roles = explode(',', $user['role_names']);
                    foreach ($roles as $role) {
                        $role = trim($role);
                        $translatedRole = translate(trim($role));
                        
                        // Determine badge class based on role
                        $badgeClass = 'default';
                        switch ($role) {
                            case 'Cég tulajdonos':
                                $badgeClass = 'owner';
                                break;
                            case 'Manager':
                                $badgeClass = 'manager';
                                break;
                            case 'Hangtechnikus':
                            case 'Fénytechnikus':
                            case 'Vizuáltechnikus':
                            case 'Szinpadtechnikus':
                            case 'Szinpadfedés felelős':
                                $badgeClass = 'technician';
                                break;
                            case 'Karbantartó':
                                $badgeClass = 'maintenance';
                                break;
                            case 'Stagehand':
                                $badgeClass = 'stagehand';
                                break;
                        }
                        
                        echo '<span class="role-badge ' . $badgeClass . '">' . htmlspecialchars($translatedRole) . '</span>';
                    }
                    echo '</div>';
                }
                ?>
                <?php
                // Display subscription plan information
                if (!empty($user['subscription_plan_name'])) {
                    if ($user['subscription_plan_name'] === 'free-trial') {
                        if (!empty($user['trial_end_date'])) {
                            $trialEndDate = new DateTime($user['trial_end_date']);
                            $subscriptionText = 'Próbaidőszak - ' . $trialEndDate->format('Y.m.d');
                        } else {
                            $subscriptionText = 'Próbaidőszak';
                        }
                    } else {
                        // Convert database plan names to display names
                        $displayName = match($user['subscription_plan_name']) {
                            'alap', 'alap_eves' => 'Alap',
                            'kozepes', 'kozepes_eves' => 'Közepes',
                            'uzleti', 'uzleti_eves' => 'Üzleti',
                            default => $user['subscription_plan_name']
                        };
                        
                        $subscriptionText = $displayName;
                        if (!empty($user['next_billing_date'])) {
                            $nextBillingDate = new DateTime($user['next_billing_date']);
                            $subscriptionText .= ' - ' . $nextBillingDate->format('Y.m.d');
                        }
                    }
                    echo '<p class="subscription-badge">' . $subscriptionText . '</p>';
                }
                ?>
            </div>
        </div>

        <div class="profile-content">
            <div class="profile-section">
                <h2><?php echo translate('personal_data'); ?></h2>
                <div class="form-group">
                    <label><?php echo translate('lastname'); ?>:</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['lastname']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label><?php echo translate('firstname'); ?>:</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['firstname']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label><?php echo translate('email'); ?>:</label>
                    <div class="input-group">
                        <input type="email" name="email" id="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                               disabled required>
                        <button type="button" class="edit-btn" onclick="toggleEdit('email')">
                            <img src="../assets/img/edit.png" alt="<?php echo translate('edit'); ?>">
                        </button>
                        <button type="button" class="save-btn" id="email-save" onclick="saveField('email')"><?php echo translate('save'); ?></button>
                        <button type="button" class="cancel-btn" id="email-cancel" onclick="cancelEdit('email')"><?php echo translate('cancel_edit'); ?></button>
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo translate('telephone'); ?>:</label>
                    <div class="input-group">
                        <input type="tel" name="telephone" id="telephone" 
                               value="<?php echo htmlspecialchars($user['telephone']); ?>" 
                               disabled>
                        <button type="button" class="edit-btn" onclick="toggleEdit('telephone')">
                            <img src="../assets/img/edit.png" alt="<?php echo translate('edit'); ?>">
                        </button>
                        <button type="button" class="save-btn" id="telephone-save" onclick="saveField('telephone')"><?php echo translate('save'); ?></button>
                        <button type="button" class="cancel-btn" id="telephone-cancel" onclick="cancelEdit('telephone')"><?php echo translate('cancel_edit'); ?></button>
                    </div>
                </div>
                <div class="form-group">
                        <label><?php echo translate('status'); ?>:</label>
                    <div class="input-group">
                        <select id="status" name="status" class="form-control">
                            <?php foreach ($statuses as $status): ?>
                                <?php 
                                // A státusz nevének lefordítása
                                $statusTranslationKey = strtolower($status['name']);
                                $translatedStatus = translate($statusTranslationKey);
                                ?>
                                <option value="<?php echo $status['id']; ?>" 
                                    <?php echo ($currentStatus && $currentStatus['id'] == $status['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($translatedStatus); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="profile-section">
                <h2><?php echo translate('company_data'); ?></h2>
                <div class="company-info">
                    <?php
                    // Lekérjük a vállalat logóját
                    $companyLogo = 'default_company.png';
                    $logoPath = '../assets/img/company.png';
                    
                    if (isset($user['company_id']) && !empty($user['company_id'])) {
                        $companyStmt = $db->prepare("SELECT profile_picture FROM company WHERE id = :company_id");
                        $companyStmt->execute([':company_id' => $user['company_id']]);
                        $companyLogo = $companyStmt->fetchColumn();
                        
                        if ($companyLogo) {
                            $tempPath = '../uploads/company_logos/' . $companyLogo;
                            if (file_exists($tempPath)) {
                                $logoPath = $tempPath;
                            }
                        }
                    }

                    // Ellenőrizzük a felhasználó szerepkörét
                    $user_roles = explode(',', $user['role_names'] ?? '');
                    $can_edit_logo = false;
                    foreach ($user_roles as $role) {
                        $translatedOwnerRole = translate('Cég tulajdonos');
                        $translatedManagerRole = translate('Manager');
                        if (trim($role) === $translatedOwnerRole || trim($role) === $translatedManagerRole) {
                            $can_edit_logo = true;
                            break;
                        }
                    }
                    ?>
                    <div class="company-content">
                        <div class="company-logo-container" <?php echo $can_edit_logo ? 'onclick="document.getElementById(\'company_logo_input\').click()"' : ''; ?>>
                            <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Vállalati logó" id="company_logo_preview">
                            <?php if ($can_edit_logo): ?>
                                <input type="file" id="company_logo_input" style="display: none" accept="image/*">
                                <div class="logo-overlay">
                                    <i class="fas fa-camera"></i>
                                    <span><?php echo translate('Logó módosítása'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="company-details">
                            <p><strong><?php echo translate('company_name'); ?>:</strong> <?php echo htmlspecialchars($user['company_name'] ?? translate('Nincs megadva')); ?></p>
                            <p><strong><?php echo translate('company_address'); ?>:</strong> <?php echo htmlspecialchars($user['company_address'] ?? translate('Nincs megadva')); ?></p>
                            <p><strong><?php echo translate('company_email'); ?>:</strong> <?php echo htmlspecialchars($user['company_email'] ?? translate('Nincs megadva')); ?></p>
                            <p><strong><?php echo translate('company_telephone'); ?>:</strong> <?php echo htmlspecialchars($user['company_telephone'] ?? translate('Nincs megadva')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let originalValues = {};

        function toggleEdit(fieldId) {
            const input = document.getElementById(fieldId);
            const saveBtn = document.getElementById(fieldId + '-save');
            const cancelBtn = document.getElementById(fieldId + '-cancel');
            
            input.disabled = !input.disabled;
            saveBtn.style.display = input.disabled ? 'none' : 'inline-block';
            cancelBtn.style.display = input.disabled ? 'none' : 'inline-block';
            
            if (!input.disabled) {
                input.focus();
                // Mentsük el az eredeti értéket
                input.dataset.originalValue = input.value;
            }
        }

        function saveField(fieldId) {
            const input = document.getElementById(fieldId);
            const value = input.value;
            
            // AJAX kérés az adatok mentéséhez
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `field=${fieldId}&value=${encodeURIComponent(value)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toggleEdit(fieldId);
                    showNotification('Sikeres mentés!', 'success');
                } else {
                    showNotification(data.error || 'Hiba történt a mentés során!', 'error');
                    input.value = input.dataset.originalValue;
                }
            })
            .catch(error => {
                console.error('Hiba:', error);
                showNotification('Hiba történt a mentés során!', 'error');
                input.value = input.dataset.originalValue;
            });
        }

        function cancelEdit(fieldId) {
            const input = document.getElementById(fieldId);
            // Visszaállítjuk az eredeti értéket
            input.value = input.dataset.originalValue;
            toggleEdit(fieldId);
        }

        function showNotification(message, type) {
            const header = document.querySelector('.main-header');
            const headerHeight = header ? header.offsetHeight : 0;
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            // Pozicionálás a header alá
            notification.style.cssText = `
                position: fixed;
                top: ${headerHeight + 20}px;
                right: 20px;
                background-color: ${type === 'success' ? '#28a745' : '#dc3545'};
                color: white;
                padding: 12px 24px;
                border-radius: 4px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                z-index: 999;
                animation: slideInRight 0.5s ease-out;
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Profilkép kezelése
        document.getElementById('profile_pic_input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('A fájl mérete nem lehet nagyobb mint 5MB.');
                    return;
                }

                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Csak JPG, PNG és GIF képeket lehet feltölteni!');
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'update_profile_pic');
                formData.append('profile_pic', file);

                this.disabled = true;

                fetch('profil.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Frissítjük az összes profilképet az oldalon
                        const profilePics = document.querySelectorAll('img[src*="uploads/profiles/"], img[src*="assets/img/user.png"]');
                        profilePics.forEach(img => {
                            img.src = data.profile_pic_path;
                        });
                        
                        showToast('A profilkép sikeresen frissítve!');
                    } else {
                        throw new Error(data.message || 'Hiba történt a profilkép feltöltése során.');
                    }
                })
                .catch(error => {
                    alert(error.message);
                })
                .finally(() => {
                    this.disabled = false;
                    this.value = '';
                });
            }
        });

        // Toast üzenet megjelenítése függvény
        function showToast(message) {
            const header = document.querySelector('.main-header');
            const headerHeight = header ? header.offsetHeight : 0;
            
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.textContent = message;
            
            toast.style.cssText = `
                position: fixed;
                top: ${headerHeight + 20}px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 12px 24px;
                border-radius: 4px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                z-index: 999;
                animation: slideInRight 0.5s, fadeOut 0.5s 2.5s;
                white-space: nowrap;
                max-width: 90%;
                overflow: hidden;
                text-overflow: ellipsis;
            `;
            
            // Animációk hozzáadása, ha még nem léteznek
            if (!document.querySelector('#toast-animations')) {
                const style = document.createElement('style');
                style.id = 'toast-animations';
                style.textContent = `
                    @keyframes slideInRight {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes fadeOut {
                        from { opacity: 1; }
                        to { opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Jelszó módosítás kezelése
        function updatePassword() {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('new_password_confirmation').value;

            if (newPassword !== confirmPassword) {
                alert('Az új jelszavak nem egyeznek!');
                return;
            }

            // Itt implementáld a jelszó módosítás AJAX hívását
        }

        // Értesítési beállítások mentése
        function saveNotificationSettings() {
            const emailNotifications = document.getElementById('email_notifications').checked;
            const browserNotifications = document.getElementById('browser_notifications').checked;

            // Itt implementáld az értesítési beállítások mentésének AJAX hívását
        }

        document.getElementById('status').addEventListener('change', function() {
            const statusId = this.value;
            const statusText = this.options[this.selectedIndex].text;
            
            // AJAX kérés a státusz frissítéséhez
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=update_status&status_id=${statusId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`Státusz sikeresen módosítva: ${statusText}`, 'success');
                } else {
                    showNotification(data.error || 'Hiba történt a státusz módosítása során!', 'error');
                    // Visszaállítjuk az eredeti értéket
                    this.value = currentStatus;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Hiba történt a státusz módosítása során!', 'error');
                // Visszaállítjuk az eredeti értéket
                this.value = currentStatus;
            });
        });

        // Vállalati logó kezelése
        document.getElementById('company_logo_input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    showNotification('A fájl mérete nem lehet nagyobb mint 5MB.', 'error');
                    return;
                }

                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    showNotification('Csak JPG, PNG és GIF képeket lehet feltölteni!', 'error');
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'update_company_logo');
                formData.append('company_logo', file);

                fetch('profil.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Frissítjük a logó képét
                        document.getElementById('company_logo_preview').src = data.profile_pic_path;
                        showNotification(data.message, 'success');
                    } else {
                        throw new Error(data.message || 'Hiba történt a logó feltöltése során.');
                    }
                })
                .catch(error => {
                    showNotification(error.message, 'error');
                });
            }
        });
    </script>
</body>
</html>