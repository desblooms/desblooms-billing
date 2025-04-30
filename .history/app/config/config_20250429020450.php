<?php
/**
 * Main Configuration File
 * 
 * This file contains all the main configuration settings for the Digital Service Billing Mobile App.
 * It defines constants and settings that are used throughout the application.
 */

// Prevent direct access to this file
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

// Application Settings
define('APP_NAME', 'Digital Service Billing');
define('APP_VERSION', '1.0.0');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');
define('APP_TIMEZONE', 'UTC');
define('APP_CHARSET', 'UTF-8');
define('APP_LANG', 'en');

// Environment Settings
define('ENVIRONMENT', $_ENV['APP_ENV'] ?? 'development'); // Options: development, testing, production
define('DISPLAY_ERRORS', ENVIRONMENT === 'development');
define('ERROR_REPORTING_LEVEL', ENVIRONMENT === 'development' ? E_ALL : E_ERROR | E_PARSE);

// Path Settings
define('BASEPATH', dirname(__DIR__)); // App directory path
define('PUBLIC_PATH', dirname(__DIR__, 2) . '/public'); // Public directory path
define('VIEW_PATH', BASEPATH . '/views'); // Views directory path
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads'); // File uploads path
define('LOG_PATH', dirname(__DIR__, 2) . '/logs'); // Log files path

// Session Settings
define('SESSION_NAME', 'digital_service_billing_session');
define('SESSION_LIFETIME', 7200); // 2 hours in seconds
define('SESSION_PATH', '/');
define('SESSION_SECURE', ENVIRONMENT === 'production');
define('SESSION_HTTP_ONLY', true);

// Database Settings (pulled from .env through environment variables)
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'service_billing');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_CHARSET', 'utf8mb4');

// Email Settings
define('MAIL_HOST', $_ENV['MAIL_HOST'] ?? 'smtp.example.com');
define('MAIL_PORT', $_ENV['MAIL_PORT'] ?? 587);
define('MAIL_USERNAME', $_ENV['MAIL_USERNAME'] ?? '');
define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD'] ?? '');
define('MAIL_ENCRYPTION', $_ENV['MAIL_ENCRYPTION'] ?? 'tls');
define('MAIL_FROM_ADDRESS', $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com');
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? APP_NAME);

// Payment Gateway Settings
define('PAYMENT_GATEWAY_MODE', $_ENV['PAYMENT_GATEWAY_MODE'] ?? 'sandbox'); // Options: sandbox, live
define('PAYMENT_GATEWAY_API_KEY', $_ENV['PAYMENT_GATEWAY_API_KEY'] ?? '');
define('PAYMENT_GATEWAY_SECRET', $_ENV['PAYMENT_GATEWAY_SECRET'] ?? '');

// Security Settings
define('HASH_COST', 12); // Cost factor for password hashing
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LIFETIME', 7200); // 2 hours in seconds

// Feature Flags
define('ENABLE_CUSTOMER_SUPPORT', true);
define('ENABLE_RECURRING_BILLING', true);
define('ENABLE_CUSTOM_SERVICE_REQUESTS', true);
define('ENABLE_MULTIPLE_PAYMENT_GATEWAYS', true);

// Pagination Settings
define('ITEMS_PER_PAGE', 15);

// File Upload Settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Error Handling Configuration
if (DISPLAY_ERRORS) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}
error_reporting(ERROR_REPORTING_LEVEL);

// Log Settings
define('LOG_ERRORS', true);
define('LOG_LEVEL', ENVIRONMENT === 'production' ? 'ERROR' : 'DEBUG');

// Load environment-specific configs if necessary
$env_config_file = BASEPATH . '/config/environments/' . ENVIRONMENT . '.php';
if (file_exists($env_config_file)) {
    require_once $env_config_file;
}