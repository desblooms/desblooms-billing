 
<?php
/**
 * Digital Service Billing Mobile App
 * Configuration File
 * 
 * This file contains all the configuration settings for the application
 * including database credentials, application paths, API keys, and global settings.
 */

// Prevent direct access to this file
if (!defined('APP_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access to this file is forbidden');
}

// Application environment
define('APP_ENV', 'development'); // Options: development, staging, production

// Debug mode (set to false in production)
define('APP_DEBUG', APP_ENV !== 'production');

// Define application paths
define('APP_ROOT', dirname(__DIR__));
define('APP_URL', 'https://yourdomain.com'); // Change this to your domain
define('APP_API_URL', APP_URL . '/api');
define('APP_ASSETS_URL', APP_URL . '/assets');
define('APP_UPLOADS_DIR', APP_ROOT . '/uploads');
define('APP_INVOICES_DIR', APP_ROOT . '/invoices');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'digital_billing_app');
define('DB_USER', 'dbuser');
define('DB_PASS', 'dbpassword');
define('DB_CHARSET', 'utf8mb4');

// Default timezone
date_default_timezone_set('UTC');

// Application settings
define('APP_NAME', 'Digital Service Billing');
define('APP_VERSION', '1.0.0');
define('APP_CURRENCY', 'USD');
define('APP_CURRENCY_SYMBOL', '$');
define('APP_DATE_FORMAT', 'Y-m-d');
define('APP_DATETIME_FORMAT', 'Y-m-d H:i:s');

// Security settings
define('HASH_COST', 12); // Password hashing cost
define('JWT_SECRET', 'change_this_to_a_long_random_string');
define('JWT_EXPIRY', 3600 * 24); // Token validity in seconds (24 hours)
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_NAME', 'digital_billing_session');

// Invoice settings
define('INVOICE_PREFIX', 'INV-');
define('INVOICE_DUE_DAYS', 14); // Default due date (days from issue)
define('INVOICE_LATE_FEE_PERCENT', 5); // Default late fee percentage
define('INVOICE_LATE_FEE_DAYS', 7); // Days after which late fee applies
define('SERVICE_SUSPENSION_DAYS', 30); // Days after which service is suspended

// Payment gateway credentials
// Stripe
define('STRIPE_ENABLE', true);
define('STRIPE_PUBLIC_KEY', 'pk_test_your_key_here');
define('STRIPE_SECRET_KEY', 'sk_test_your_key_here');
define('STRIPE_WEBHOOK_SECRET', 'whsec_your_key_here');

// PayPal
define('PAYPAL_ENABLE', true);
define('PAYPAL_CLIENT_ID', 'your_client_id_here');
define('PAYPAL_CLIENT_SECRET', 'your_secret_here');
define('PAYPAL_SANDBOX', APP_ENV !== 'production');

// Razorpay
define('RAZORPAY_ENABLE', true);
define('RAZORPAY_KEY_ID', 'your_key_id_here');
define('RAZORPAY_KEY_SECRET', 'your_key_secret_here');

// Email configuration
define('MAIL_DRIVER', 'smtp'); // Options: smtp, sendmail, mail
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'noreply@example.com');
define('MAIL_PASSWORD', 'your_password');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_ADDRESS', 'noreply@example.com');
define('MAIL_FROM_NAME', APP_NAME);

// SMS configuration (optional)
define('SMS_ENABLE', false);
define('SMS_PROVIDER', 'twilio'); // Options: twilio, nexmo, etc.
define('SMS_API_KEY', 'your_api_key');
define('SMS_API_SECRET', 'your_api_secret');
define('SMS_FROM', 'your_phone_number');

// Notification settings
define('NOTIFY_INVOICE_CREATED', true);
define('NOTIFY_PAYMENT_RECEIVED', true);
define('NOTIFY_PAYMENT_DUE', true);
define('NOTIFY_PAYMENT_OVERDUE', true);

// PWA settings
define('PWA_ENABLE', true);
define('PWA_NAME', APP_NAME);
define('PWA_SHORT_NAME', 'DSBilling');
define('PWA_THEME_COLOR', '#2563eb');
define('PWA_BACKGROUND_COLOR', '#ffffff');

// Support system
define('SUPPORT_EMAIL', 'support@example.com');
define('SUPPORT_PHONE', '+1234567890');
define('LIVE_CHAT_ENABLE', true);
define('LIVE_CHAT_PROVIDER', 'tawk'); // Options: tawk, crisp, custom
define('LIVE_CHAT_ID', 'your_tawk_id');

// Multi-language support (optional)
define('MULTI_LANGUAGE_ENABLE', false);
define('DEFAULT_LANGUAGE', 'en');
define('AVAILABLE_LANGUAGES', json_encode(['en', 'es', 'fr']));

// Admin account defaults
define('ADMIN_EMAIL', 'admin@example.com');
define('ADMIN_USERNAME', 'admin');
define('ADMIN_DEFAULT_PASSWORD', 'change_this_password');

// Pagination settings
define('ITEMS_PER_PAGE', 10);

// File upload settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_UPLOAD_EXTENSIONS', json_encode(['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']));

// System settings
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'We are currently performing maintenance. Please check back shortly.');

// Define user roles
define('ROLE_ADMIN', 'admin');
define('ROLE_STAFF', 'staff');
define('ROLE_CUSTOMER', 'customer');

// Error logging
define('ERROR_LOG_FILE', APP_ROOT . '/logs/error.log');
ini_set('error_log', ERROR_LOG_FILE);
error_reporting(APP_DEBUG ? E_ALL : E_ERROR | E_PARSE);
ini_set('display_errors', APP_DEBUG ? 1 : 0);

// Load environment-specific configuration if available
$env_config = APP_ROOT . '/includes/config.' . APP_ENV . '.php';
if (file_exists($env_config)) {
    require_once $env_config;
}

// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Set default headers for security
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
if (APP_ENV === 'production') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Helper function to get config values from database (optional)
function get_config($key, $default = null) {
    // Implement database lookup for dynamic configuration
    // This would typically query a settings table
    // For now, we'll just return the default
    return $default;
}