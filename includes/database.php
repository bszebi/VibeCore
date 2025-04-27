<?php
require_once __DIR__ . '/config.php';

// Using a different class name to avoid conflicts
if (!class_exists('DatabaseConnection')) {
    class DatabaseConnection {
        private $connection;
        private static $instance = null;

        private function __construct() {
            try {
                global $db_host, $db_user, $db_password, $db_name;
                
                // Log connection attempt
                error_log("Attempting database connection to: $db_host, database: $db_name, user: $db_user");
                
                $this->connection = new PDO(
                    "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4",
                    $db_user,
                    $db_password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                    ]
                );
                
                // Log successful connection
                error_log("Database connection established successfully");
            } catch(PDOException $e) {
                // Log the detailed error
                error_log("Database connection error: " . $e->getMessage());
                error_log("Error code: " . $e->getCode());
                error_log("Error trace: " . $e->getTraceAsString());
                
                // Throw a new exception with a user-friendly message
                throw new PDOException("Database connection failed: " . $e->getMessage());
            }
        }

        public static function getInstance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function getConnection() {
            if (!$this->connection) {
                error_log("Database connection is null when getConnection() is called");
                throw new PDOException("Database connection is not available");
            }
            return $this->connection;
        }
    }
}
?> 