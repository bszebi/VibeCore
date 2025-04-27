<?php
// Include error handler
require_once 'error_handler.php';

// Prevent PHP errors from being displayed directly
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted output
ob_start();

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';

// Clear any previous output
ob_clean();

header('Content-Type: application/json');

// Function to send JSON response and exit
function sendJsonResponse($success, $message) {
    ensureJsonResponse([
        'success' => $success,
        'message' => $message
    ]);
}

// Check if user is logged in and has company owner role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Cég tulajdonos') {
    sendJsonResponse(false, 'Nincs jogosultságod a művelet végrehajtásához.');
}

// Validate input
if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['role'])) {
    sendJsonResponse(false, 'Minden mező kitöltése kötelező.');
}

$name = trim($_POST['name']);
$email = trim($_POST['email']);
$role = trim($_POST['role']);

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(false, 'Érvénytelen email cím.');
}

try {
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Get company ID and subscription info
        $stmt = $db->prepare("
            SELECT 
                c.id as company_id,
                sp.description as plan_description,
                (SELECT COUNT(*) FROM user WHERE company_id = c.id) as current_user_count
            FROM user u
            JOIN company c ON u.company_id = c.id
            LEFT JOIN subscriptions s ON s.company_id = c.id AND s.subscription_status_id = 1
            LEFT JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
            WHERE u.id = :user_id
        ");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            throw new Exception('Nem található a cég azonosítója.');
        }

        // Check user limit
        preg_match('/(\d+)\s+felhasználó/', $result['plan_description'], $matches);
        $user_limit = isset($matches[1]) ? (int)$matches[1] : 0;
        $current_user_count = (int)$result['current_user_count'];

        if ($current_user_count >= $user_limit) {
            sendJsonResponse(false, 'A csapat létszáma elérte a maximális ' . $user_limit . ' főt. A limit növeléséhez váltson magasabb csomagra vagy módosítsa jelenlegi előfizetését.');
        }

        $company_id = $result['company_id'];
        
        // Get company and owner information
        $stmt = $db->prepare("
            SELECT 
                c.company_name,
                CONCAT(u.lastname, ' ', u.firstname) as owner_name,
                u.email as sender_email
            FROM company c 
            JOIN user u ON u.id = :user_id 
            WHERE c.id = :company_id
        ");
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':company_id' => $company_id
        ]);
        $companyInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$companyInfo) {
            throw new Exception('Nem található a cég vagy tulajdonos információja.');
        }
        
        // Get role ID from role name
        $stmt = $db->prepare("SELECT id FROM roles WHERE role_name = :role");
        $stmt->execute([':role' => $role]);
        $role_id = $stmt->fetchColumn();
        
        if (!$role_id) {
            throw new Exception('Érvénytelen szerepkör.');
        }
        
        // Generate invitation token
        $token = bin2hex(random_bytes(32));
        $created_date = date('Y-m-d H:i:s');
        $expires_at = date('Y-m-d H:i:s', strtotime('+48 hours'));
        
        // Insert invitation into database
        $stmt = $db->prepare("
            INSERT INTO invitations (
                email,
                company_id,
                role_id,
                invitation_token,
                expiration_date,
                created_by_user_id,
                created_date
            ) VALUES (
                :email,
                :company_id,
                :role_id,
                :invitation_token,
                :expiration_date,
                :created_by_user_id,
                :created_date
            )
        ");
        
        $params = [
            ':email' => $email,
            ':company_id' => $company_id,
            ':role_id' => $role_id,
            ':invitation_token' => $token,
            ':expiration_date' => $expires_at,
            ':created_by_user_id' => $_SESSION['user_id'],
            ':created_date' => $created_date
        ];
        
        if (!$stmt->execute($params)) {
            $error = $stmt->errorInfo();
            throw new Exception('Adatbázis hiba: ' . $error[2]);
        }

        try {
            // Send email
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'kurinczjozsef@gmail.com';
            $mail->Password = 'yxsyntnvrwvezode';
            $mail->SMTPSecure = 'ssl';
            $mail->Port = 465;

            $mail->setFrom('kurinczjozsef@gmail.com', 'Céges meghívó');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';

            // Lejárati dátum formázása
            $expiration_date = date('Y. m. d. H:i', strtotime($expires_at));

            $mail->Subject = 'Meghívó a céges rendszerbe';
            $mail->Body = "
                <!DOCTYPE html>
                <html lang='hu'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Meghívó - Csatlakozás a vállalathoz</title>
                    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'>
                    <style>
                        body {
                            font-family: 'Inter', sans-serif;
                            background-color: #f8f9fa;
                            margin: 0;
                            padding: 20px;
                            color: #2c3e50;
                            line-height: 1.6;
                        }

                        .email-container {
                            max-width: 600px;
                            width: 100%;
                            background: #ffffff;
                            border-radius: 16px;
                            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
                            overflow: hidden;
                            margin: 0 auto;
                        }

                        .header {
                            background: #3498db;
                            padding: 40px 30px;
                            text-align: center;
                            position: relative;
                        }

                        .header::after {
                            content: '';
                            position: absolute;
                            bottom: 0;
                            left: 0;
                            right: 0;
                            height: 40px;
                            background: linear-gradient(180deg, transparent, rgba(255, 255, 255, 0.1));
                        }

                        .header h1 {
                            color: white;
                            font-size: 28px;
                            margin: 0;
                            font-weight: 600;
                            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                        }

                        .content {
                            padding: 40px 30px;
                            color: #2c3e50;
                        }

                        .welcome-text {
                            font-size: 18px;
                            margin-bottom: 30px;
                            color: #2c3e50;
                            line-height: 1.6;
                        }

                        .company-info {
                            background: #f8f9fa;
                            border-radius: 12px;
                            padding: 20px;
                            margin: 20px 0;
                            border: 1px solid #e9ecef;
                        }

                        .info-item {
                            margin: 12px 0;
                            color: #2c3e50;
                        }

                        .button-container {
                            text-align: center;
                            margin: 30px 0;
                        }

                        .button {
                            display: inline-block;
                            background: #3498db;
                            color: white;
                            text-decoration: none;
                            padding: 16px 32px;
                            border-radius: 8px;
                            font-size: 16px;
                            font-weight: 600;
                        }

                        .expiration {
                            text-align: center;
                            color: #6c757d;
                            font-size: 14px;
                            margin: 20px 0;
                            padding: 15px;
                            background: #f8f9fa;
                            border-radius: 8px;
                        }

                        .footer {
                            background: #f8f9fa;
                            padding: 20px;
                            text-align: center;
                            font-size: 14px;
                            color: #6c757d;
                            border-top: 1px solid #e9ecef;
                        }

                        .divider {
                            height: 1px;
                            background: #e9ecef;
                            margin: 30px 0;
                        }

                        .support-info {
                            background: #fff;
                            padding: 20px;
                            border-radius: 8px;
                            margin: 20px 0;
                            border: 1px solid #e9ecef;
                        }

                        .support-info h3 {
                            margin-top: 0;
                            color: #3498db;
                            font-size: 18px;
                            margin-bottom: 15px;
                        }

                        .support-info p {
                            margin: 10px 0;
                            color: #2c3e50;
                        }

                        .support-info ul {
                            list-style: none;
                            padding: 0;
                            margin: 15px 0;
                        }

                        .support-info li {
                            margin: 10px 0;
                            color: #2c3e50;
                        }

                        .support-info a {
                            color: #3498db;
                            text-decoration: none;
                        }

                        .support-info a:hover {
                            text-decoration: underline;
                        }

                        @media (max-width: 600px) {
                            body {
                                padding: 10px;
                            }
                            .header {
                                padding: 30px 20px;
                            }
                            .content {
                                padding: 30px 20px;
                            }
                            .button {
                                width: 100%;
                                box-sizing: border-box;
                            }
                        }
                    </style>
                </head>
                <body></body>
                    <div class='email-container'>
                        <div class='header'>
                            <h1>Meghívó a vállalathoz</h1>
                        </div>
                        
                        <div class='content'>
                            <p class='welcome-text'>
                                Kedves <strong>{$name}</strong>!
                                <br><br>
                                Meghívást kapott <strong>{$companyInfo['owner_name']}</strong>-tól/től a(z) <strong>{$companyInfo['company_name']}</strong> céges rendszerébe <strong>{$role}</strong> szerepkörrel.
                            </p>

                            <div class='company-info'>
                                <div class='info-item'>
                                    <span>Vállalat: <strong>{$companyInfo['company_name']}</strong></span>
                                </div>
                                <div class='info-item'>
                                    <span>Szerepkör: <strong>{$role}</strong></span>
                                </div>
                                <div class='info-item'>
                                    <span>Email: <strong>{$email}</strong></span>
                                </div>
                            </div>

                            <p class='welcome-text'>
                                A regisztrációt követően hozzáférést kap vállalatunk belső rendszeréhez. A felületen keresztül könnyedén kezelheti a mindennapi munkafolyamatokat, nyomon követheti az aktuális projekteket. A rendszer minden szükséges eszközt biztosít a hatékony munkavégzéshez.
                            </p>

                            <div class='expiration'>
                                <strong>Fontos:</strong> A regisztrációs link <strong>48 órán belül</strong> érvényes!
                                <br>
                                Lejárat időpontja: <strong>{$expiration_date}</strong>
                            </div>

                            <div class='button-container'>
                                <a href='http://localhost/Vizsga_oldal/auth/worker_register.php?email={$email}&token={$token}' class='button'>
                                    Regisztráció megkezdése
                                </a>
                            </div>

                            <div class='support-info'>
                                <h3 style='margin-top: 0; color: #3498db;'>Segítségre van szüksége?</h3>
                                <p>Ha problémába ütközik a regisztráció során vagy kérdése van, az alábbi módokon kérhet segítséget:</p>
                                <ul>
                                    <li>Írjon emailt a meghívónak: <a href='mailto:{$companyInfo['sender_email']}'>{$companyInfo['sender_email']}</a></li>
                                    <li>Vagy jelezze a problémát a rendszergazdának: <a href='mailto:support@example.com'>support@example.com</a></li>
                                </ul>
                            </div>

                            <div class='divider'></div>

                            <p style='text-align: center; color: #6c757d; font-size: 14px;'>
                                Ha nem Ön a címzett vagy nem kíván regisztrálni, kérjük hagyja figyelmen kívül ezt az emailt.
                            </p>
                        </div>

                        <div class='footer'>
                            <p>© VibeCore - " . date('Y') . "</p>
                            <p>Ez egy automatikus üzenet, kérjük ne válaszoljon rá.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            $mail->send();
            
            // If we got here, both database insert and email sending were successful
            $db->commit();
            
            // Clear any unwanted output before sending response
            ob_clean();
            sendJsonResponse(true, 'Meghívó sikeresen elküldve.');
            
        } catch (Exception $e) {
            // Email sending failed
            $db->rollBack();
            throw new Exception('Hiba az email küldése során: ' . $e->getMessage());
        }
        
    } catch (Exception $e) {
        // Database operations failed
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log the full error for debugging
    error_log('Invitation error: ' . $e->getMessage());
    
    // Clear any unwanted output before sending response
    ob_clean();
    sendJsonResponse(false, 'Hiba történt a meghívó küldése során: ' . $e->getMessage());
} 