<?php
/**
 * Database Configuration File
 * 
 * This file contains the database connection settings for the
 * Digital Service Billing Mobile App. It defines constants for
 * database credentials and provides a connection method.
 */

// Prevent direct script access
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

// Database Credentials
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'service_billing_app');
define('DB_USER', getenv('DB_USER') ?: 'app_user');
define('DB_PASS', getenv('DB_PASS') ?: 'secure_password');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', 'utf8mb4');

/**
 * Database Connection Class
 * 
 * Handles database connection management
 */
class Database {
    private static $instance = null;
    private $conn;
    
    /**
     * Constructor - Connect to the database
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET . ";port=" . DB_PORT;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the error instead of exposing details
            error_log('Database Connection Error: ' . $e->getMessage());
            die('Database connection failed. Please check the logs or contact support.');
        }
    }
    
    /**
     * Get singleton instance
     * 
     * @return PDO Database connection
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->conn;
    }
    
    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}
    
    /**
     * Close database connection
     */
    public function __destruct() {
        $this->conn = null;
    }
}

/**
 * Get database connection
 * 
 * Helper function to get the database connection
 * 
 * @return PDO Database connection
 */
function db_connect() {
    return Database::getInstance();
}