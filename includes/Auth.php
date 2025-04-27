<?php
require_once __DIR__ . '/db.php';

class Auth {
    public static function check() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            header('Location: /home.php');
            exit;
        }
    }

    public static function user() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT u.*, c.company_name, GROUP_CONCAT(r.role_name) as role_names
                FROM user u
                LEFT JOIN company c ON u.company_id = c.id
                LEFT JOIN user_to_roles utr ON u.id = utr.user_id
                LEFT JOIN roles r ON utr.role_id = r.id
                WHERE u.id = :id
                GROUP BY u.id
            ");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Hiba a felhasználó adatainak lekérdezésekor: ' . $e->getMessage());
            return null;
        }
    }

    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = array();
        session_destroy();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-3600, '/');
        }
        
        header('Location: ../home.php');
        exit;
    }
}