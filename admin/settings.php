<?php
/**
 * Admin Settings Page
 * 
 * This file handles all system settings for the Digital Service Billing Mobile App.
 * Administrators can configure various aspects of the system including:
 * - General settings (company info, logo, etc.)
 * - Invoice settings (numbering format, terms, etc.)
 * - Payment settings (payment gateways, currencies)
 * - Tax settings (tax rates, rules)
 * - Notification settings (email, SMS, push)
 * - System settings (maintenance mode, debug)
 */

// Initialize the session
session_start();

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php?redirect=admin/settings.php");
    exit;
}

// Handle form submissions
$successMessage = '';
$errorMessage = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Determine which settings form was submitted
    if (isset($_POST['general_settings_submit'])) {
        // Handle general settings update
        $companyName = sanitizeInput($_POST['company_name']);
        $companyEmail = sanitizeInput($_POST['company_email']);
        $companyPhone = sanitizeInput($_POST['company_phone']);
        $companyAddress = sanitizeInput($_POST['company_address']);
        $siteName = sanitizeInput($_POST['site_name']);
        $siteTagline = sanitizeInput($_POST['site_tagline']);
        
        // Handle logo upload if present
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['company_logo']['type'], $allowedTypes)) {
                $uploadDir = '../uploads/';
                $filename = 'company_logo_' . time() . '.' . pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
                $uploadFile = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $uploadFile)) {
                    // Update logo setting in database
                    updateSetting('company_logo', $filename);
                } else {
                    $errorMessage = "Error uploading logo file.";
                }
            } else {
                $errorMessage = "Invalid file type. Please upload JPG, PNG or GIF.";
            }
        }
        
        // Update other general settings
        updateSetting('company_name', $companyName);
        updateSetting('company_email', $companyEmail);
        updateSetting('company_phone', $companyPhone);
        updateSetting('company_address', $companyAddress);
        updateSetting('site_name', $siteName);
        updateSetting('site_tagline', $siteTagline);
        
        $successMessage = "General settings updated successfully!";
    } 
    else if (isset($_POST['invoice_settings_submit'])) {
        // Handle invoice settings update
        $invoicePrefix = sanitizeInput($_POST['invoice_prefix']);
        $invoiceStartNumber = (int)sanitizeInput($_POST['invoice_start_number']);
        $invoiceDueDays = (int)sanitizeInput($_POST['invoice_due_days']);
        $invoiceTerms = sanitizeInput($_POST['invoice_terms']);
        $invoiceFooter = sanitizeInput($_POST['invoice_footer']);
        
        // Update invoice settings
        updateSetting('invoice_prefix', $invoicePrefix);
        updateSetting('invoice_start_number', $invoiceStartNumber);
        updateSetting('invoice_due_days', $invoiceDueDays);
        updateSetting('invoice_terms', $invoiceTerms);
        updateSetting('invoice_footer', $invoiceFooter);
        
        $successMessage = "Invoice settings updated successfully!";
    }
    else if (isset($_POST['payment_settings_submit'])) {
        // Handle payment gateway settings
        $defaultCurrency = sanitizeInput($_POST['default_currency']);
        $enableStripe = isset($_POST['enable_stripe']) ? 1 : 0;
        $stripePublicKey = sanitizeInput($_POST['stripe_public_key']);
        $stripeSecretKey = sanitizeInput($_POST['stripe_secret_key']);
        $enablePaypal = isset($_POST['enable_paypal']) ? 1 : 0;
        $paypalClientId = sanitizeInput($_POST['paypal_client_id']);
        $paypalSecret = sanitizeInput($_POST['paypal_secret']);
        $enableRazorpay = isset($_POST['enable_razorpay']) ? 1 : 0;
        $razorpayKeyId = sanitizeInput($_POST['razorpay_key_id']);
        $razorpayKeySecret = sanitizeInput($_POST['razorpay_key_secret']);
        $enableWallet = isset($_POST['enable_wallet']) ? 1 : 0;
        
        // Update payment settings
        updateSetting('default_currency', $defaultCurrency);
        updateSetting('enable_stripe', $enableStripe);
        updateSetting('stripe_public_key', $stripePublicKey);
        updateSetting('stripe_secret_key', $stripeSecretKey);
        updateSetting('enable_paypal', $enablePaypal);
        updateSetting('paypal_client_id', $paypalClientId);
        updateSetting('paypal_secret', $paypalSecret);
        updateSetting('enable_razorpay', $enableRazorpay);
        updateSetting('razorpay_key_id', $razorpayKeyId);
        updateSetting('razorpay_key_secret', $razorpayKeySecret);
        updateSetting('enable_wallet', $enableWallet);
        
        $successMessage = "Payment settings updated successfully!";
    }
    else if (isset($_POST['tax_settings_submit'])) {
        // Handle tax settings
        $enableTax = isset($_POST['enable_tax']) ? 1 : 0;
        $taxName = sanitizeInput($_POST['tax_name']);
        $taxRate = floatval(sanitizeInput($_POST['tax_rate']));
        $taxType = sanitizeInput($_POST['tax_type']); // 'percentage' or 'fixed'
        $taxNumber = sanitizeInput($_POST['tax_number']);
        
        // Update tax settings
        updateSetting('enable_tax', $enableTax);
        updateSetting('tax_name', $taxName);
        updateSetting('tax_rate', $taxRate);
        updateSetting('tax_type', $taxType);
        updateSetting('tax_number', $taxNumber);
        
        $successMessage = "Tax settings updated successfully!";
    }
    else if (isset($_POST['notification_settings_submit'])) {
        // Handle notification settings
        $enableEmailNotif = isset($_POST['enable_email_notifications']) ? 1 : 0;
        $enableSmsNotif = isset($_POST['enable_sms_notifications']) ? 1 : 0;
        $enablePushNotif = isset($_POST['enable_push_notifications']) ? 1 : 0;
        $smtpHost = sanitizeInput($_POST['smtp_host']);
        $smtpPort = (int)sanitizeInput($_POST['smtp_port']);
        $smtpUsername = sanitizeInput($_POST['smtp_username']);
        $smtpPassword = sanitizeInput($_POST['smtp_password']);
        $smtpEncryption = sanitizeInput($_POST['smtp_encryption']);
        $smsApiKey = sanitizeInput($_POST['sms_api_key']);
        $smsApiSecret = sanitizeInput($_POST['sms_api_secret']);
        $smsFrom = sanitizeInput($_POST['sms_from']);
        
        // Update notification settings
        updateSetting('enable_email_notifications', $enableEmailNotif);
        updateSetting('enable_sms_notifications', $enableSmsNotif);
        updateSetting('enable_push_notifications', $enablePushNotif);
        updateSetting('smtp_host', $smtpHost);
        updateSetting('smtp_port', $smtpPort);
        updateSetting('smtp_username', $smtpUsername);
        updateSetting('smtp_password', $smtpPassword);
        updateSetting('smtp_encryption', $smtpEncryption);
        updateSetting('sms_api_key', $smsApiKey);
        updateSetting('sms_api_secret', $smsApiSecret);
        updateSetting('sms_from', $smsFrom);
        
        $successMessage = "Notification settings updated successfully!";
    }
    else if (isset($_POST['system_settings_submit'])) {
        // Handle system settings
        $maintenanceMode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $debugMode = isset($_POST['debug_mode']) ? 1 : 0;
        $defaultLanguage = sanitizeInput($_POST['default_language']);
        $dateFormat = sanitizeInput($_POST['date_format']);
        $timeFormat = sanitizeInput($_POST['time_format']);
        $timezone = sanitizeInput($_POST['timezone']);
        $enableDarkMode = isset($_POST['enable_dark_mode']) ? 1 : 0;
        $sessionTimeout = (int)sanitizeInput($_POST['session_timeout']);
        
        // Update system settings
        updateSetting('maintenance_mode', $maintenanceMode);
        updateSetting('debug_mode', $debugMode);
        updateSetting('default_language', $defaultLanguage);
        updateSetting('date_format', $dateFormat);
        updateSetting('time_format', $timeFormat);
        updateSetting('timezone', $timezone);
        updateSetting('enable_dark_mode', $enableDarkMode);
        updateSetting('session_timeout', $sessionTimeout);
        
        $successMessage = "System settings updated successfully!";
    }
    else if (isset($_POST['outstanding_settings_submit'])) {
        // Handle outstanding invoice settings
        $latePaymentPenalty = floatval(sanitizeInput($_POST['late_payment_penalty']));
        $penaltyType = sanitizeInput($_POST['penalty_type']); // 'percentage' or 'fixed'
        $gracePeriodDays = (int)sanitizeInput($_POST['grace_period_days']);
        $suspendAfterDays = (int)sanitizeInput($_POST['suspend_after_days']);
        $enablePartialPayments = isset($_POST['enable_partial_payments']) ? 1 : 0;
        $sendReminders = isset($_POST['send_reminders']) ? 1 : 0;
        $reminderDays = sanitizeInput($_POST['reminder_days']); // Comma-separated values
        
        // Update outstanding invoice settings
        updateSetting('late_payment_penalty', $latePaymentPenalty);
        updateSetting('penalty_type', $penaltyType);
        updateSetting('grace_period_days', $gracePeriodDays);
        updateSetting('suspend_after_days', $suspendAfterDays);
        updateSetting('enable_partial_payments', $enablePartialPayments);
        updateSetting('send_reminders', $sendReminders);
        updateSetting('reminder_days', $reminderDays);
        
        $successMessage = "Outstanding invoice settings updated successfully!";
    }
}

// Function to update settings in the database
function updateSetting($key, $value) {
    global $conn;
    
    // Check if setting already exists
    $stmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing setting
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
    } else {
        // Insert new setting
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->bind_param("ss", $key, $value);
    }
    
    $stmt->execute();
    $stmt->close();
}

// Function to get setting value
function getSetting($key, $default = '') {
    global $conn;
    
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    } else {
        return $default;
    }
}

// Get current settings for the form
$companyName = getSetting('company_name', 'Your Company Name');
$companyEmail = getSetting('company_email', 'contact@example.com');
$companyPhone = getSetting('company_phone', '+1234567890');
$companyAddress = getSetting('company_address', '123 Main St, City, Country');
$siteName = getSetting('site_name', 'Digital Service Billing');
$siteTagline = getSetting('site_tagline', 'Manage Your Digital Services');
$companyLogo = getSetting('company_logo', 'default_logo.png');

$invoicePrefix = getSetting('invoice_prefix', 'INV-');
$invoiceStartNumber = getSetting('invoice_start_number', '1001');
$invoiceDueDays = getSetting('invoice_due_days', '15');
$invoiceTerms = getSetting('invoice_terms', 'Payment is due within 15 days from the date of invoice issuance.');
$invoiceFooter = getSetting('invoice_footer', 'Thank you for your business.');

$defaultCurrency = getSetting('default_currency', 'USD');
$enableStripe = getSetting('enable_stripe', '0');
$stripePublicKey = getSetting('stripe_public_key', '');
$stripeSecretKey = getSetting('stripe_secret_key', '');
$enablePaypal = getSetting('enable_paypal', '0');
$paypalClientId = getSetting('paypal_client_id', '');
$paypalSecret = getSetting('paypal_secret', '');
$enableRazorpay = getSetting('enable_razorpay', '0');
$razorpayKeyId = getSetting('razorpay_key_id', '');
$razorpayKeySecret = getSetting('razorpay_key_secret', '');
$enableWallet = getSetting('enable_wallet', '0');

$enableTax = getSetting('enable_tax', '0');
$taxName = getSetting('tax_name', 'VAT');
$taxRate = getSetting('tax_rate', '10');
$taxType = getSetting('tax_type', 'percentage');
$taxNumber = getSetting('tax_number', '');

$enableEmailNotif = getSetting('enable_email_notifications', '1');
$enableSmsNotif = getSetting('enable_sms_notifications', '0');
$enablePushNotif = getSetting('enable_push_notifications', '0');
$smtpHost = getSetting('smtp_host', '');
$smtpPort = getSetting('smtp_port', '587');
$smtpUsername = getSetting('smtp_username', '');
$smtpPassword = getSetting('smtp_password', '');
$smtpEncryption = getSetting('smtp_encryption', 'tls');
$smsApiKey = getSetting('sms_api_key', '');
$smsApiSecret = getSetting('sms_api_secret', '');
$smsFrom = getSetting('sms_from', '');

$maintenanceMode = getSetting('maintenance_mode', '0');
$debugMode = getSetting('debug_mode', '0');
$defaultLanguage = getSetting('default_language', 'en');
$dateFormat = getSetting('date_format', 'Y-m-d');
$timeFormat = getSetting('time_format', 'H:i:s');
$timezone = getSetting('timezone', 'UTC');
$enableDarkMode = getSetting('enable_dark_mode', '0');
$sessionTimeout = getSetting('session_timeout', '30');

$latePaymentPenalty = getSetting('late_payment_penalty', '5');
$penaltyType = getSetting('penalty_type', 'percentage');
$gracePeriodDays = getSetting('grace_period_days', '3');
$suspendAfterDays = getSetting('suspend_after_days', '30');
$enablePartialPayments = getSetting('enable_partial_payments', '0');
$sendReminders = getSetting('send_reminders', '1');
$reminderDays = getSetting('reminder_days', '3,7,14');

// Include header
include '../templates/header.php';
?>

<div class="flex min-h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include '../templates/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="flex-1 p-4 md:p-8">
        <div class="bg-white shadow-md rounded-lg p-4 md:p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">System Settings</h1>
            
            <?php if (!empty($successMessage)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p><?php echo $successMessage; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p><?php echo $errorMessage; ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Settings Navigation Tabs -->
            <div class="flex flex-wrap mb-4 border-b border-gray-200">
                <button class="tab-button px-4 py-2 font-medium text-sm leading-5 rounded-t-lg focus:outline-none active" data-tab="general">
                    General
                </button>
                <button class="tab-button px-4 py-2 font-medium text-sm leading-5 rounded-t-lg focus:outline-none" data-tab="invoice">
                    Invoice
                </button>
                <button class="tab-button px-4 py-2 font-medium text-sm leading-5 rounded-t-lg focus:outline-none" data-tab="payment">
                    Payment
                </button>
                <button class="tab-button px-4 py-2 font-medium text-sm leading-5 rounded-t-lg focus:outline-none" data-tab="tax">
                    Tax
                </button>
                <button class="tab-button px-4 py-2 font-medium text-sm leading-5 rounded-t-lg focus:outline-none" data-tab="notification">
                    Notification
                </button>
                <button class="tab-button px-4 py-2 font-medium text-sm leading-5 rounded-t-lg focus:outline-none" data-tab="outstanding">
                    Outstanding
                </button>
                <button class="tab-button px-4 py-2 font-medium text-sm leading-5 rounded-t-lg focus:outline-none" data-tab="system">
                    System
                </button>
            </div>
            
            <!-- General Settings Form -->
            <div id="general-tab" class="tab-content">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <h2 class="text-xl font-semibold mb-4">Company Information</h2>
                        </div>
                        
                        <div>
                            <label for="company_name" class="block text-sm font-medium text-gray-700 mb-2">Company Name</label>
                            <input type="text" name="company_name" id="company_name" value="<?php echo htmlspecialchars($companyName); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="company_email" class="block text-sm font-medium text-gray-700 mb-2">Company Email</label>
                            <input type="email" name="company_email" id="company_email" value="<?php echo htmlspecialchars($companyEmail); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="company_phone" class="block text-sm font-medium text-gray-700 mb-2">Company Phone</label>
                            <input type="text" name="company_phone" id="company_phone" value="<?php echo htmlspecialchars($companyPhone); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="company_address" class="block text-sm font-medium text-gray-700 mb-2">Company Address</label>
                            <textarea name="company_address" id="company_address" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($companyAddress); ?></textarea>
                        </div>
                        
                        <div class="col-span-2">
                            <h2 class="text-xl font-semibold mb-4">Site Information</h2>
                        </div>
                        
                        <div>
                            <label for="site_name" class="block text-sm font-medium text-gray-700 mb-2">Site Name</label>
                            <input type="text" name="site_name" id="site_name" value="<?php echo htmlspecialchars($siteName); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="site_tagline" class="block text-sm font-medium text-gray-700 mb-2">Site Tagline</label>
                            <input type="text" name="site_tagline" id="site_tagline" value="<?php echo htmlspecialchars($siteTagline); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="col-span-2">
                            <label for="company_logo" class="block text-sm font-medium text-gray-700 mb-2">Company Logo</label>
                            <div class="flex items-center">
                                <?php if (!empty($companyLogo)): ?>
                                    <div class="mr-4">
                                        <img src="../uploads/<?php echo htmlspecialchars($companyLogo); ?>" alt="Company Logo" class="h-16 w-auto">
                                    </div>
                                <?php endif; ?>
                                <div class="flex-1">
                                    <input type="file" name="company_logo" id="company_logo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <p class="mt-1 text-sm text-gray-500">Recommended size: 200x50 pixels. JPG, PNG or GIF.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" name="payment_settings_submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Save Payment Settings
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Outstanding Invoice Settings Form -->
            <div id="outstanding-tab" class="tab-content hidden">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <h2 class="text-xl font-semibold mb-4">Outstanding Invoice Settings</h2>
                        </div>
                        
                        <div>
                            <label for="late_payment_penalty" class="block text-sm font-medium text-gray-700 mb-2">Late Payment Penalty</label>
                            <div class="flex">
                                <input type="number" name="late_payment_penalty" id="late_payment_penalty" value="<?php echo htmlspecialchars($latePaymentPenalty); ?>" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <select name="penalty_type" id="penalty_type" class="ml-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="percentage" <?php echo $penaltyType == 'percentage' ? 'selected' : ''; ?>>%</option>
                                    <option value="fixed" <?php echo $penaltyType == 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label for="grace_period_days" class="block text-sm font-medium text-gray-700 mb-2">Grace Period (Days)</label>
                            <input type="number" name="grace_period_days" id="grace_period_days" value="<?php echo htmlspecialchars($gracePeriodDays); ?>" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <p class="mt-1 text-sm text-gray-500">Days after due date before applying late penalty</p>
                        </div>
                        
                        <div>
                            <label for="suspend_after_days" class="block text-sm font-medium text-gray-700 mb-2">Suspend Services After (Days)</label>
                            <input type="number" name="suspend_after_days" id="suspend_after_days" value="<?php echo htmlspecialchars($suspendAfterDays); ?>" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <p class="mt-1 text-sm text-gray-500">Days after due date before services are suspended</p>
                        </div>
                        
                        <div class="col-span-2">
                            <div class="flex items-center mb-4">
                                <input type="checkbox" name="enable_partial_payments" id="enable_partial_payments" value="1" <?php echo $enablePartialPayments ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="enable_partial_payments" class="ml-2 block text-sm text-gray-700">Enable Partial Payments</label>
                            </div>
                        </div>
                        
                        <div class="col-span-2">
                            <div class="flex items-center mb-4">
                                <input type="checkbox" name="send_reminders" id="send_reminders" value="1" <?php echo $sendReminders ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="send_reminders" class="ml-2 block text-sm text-gray-700">Send Payment Reminders</label>
                            </div>
                        </div>
                        
                        <div class="col-span-2">
                            <label for="reminder_days" class="block text-sm font-medium text-gray-700 mb-2">Reminder Days</label>
                            <input type="text" name="reminder_days" id="reminder_days" value="<?php echo htmlspecialchars($reminderDays); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <p class="mt-1 text-sm text-gray-500">Comma-separated values for days to send reminders (e.g., 3,7,14)</p>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" name="outstanding_settings_submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Save Outstanding Settings
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- System Settings Form -->
            <div id="system-tab" class="tab-content hidden">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <h2 class="text-xl font-semibold mb-4">System Settings</h2>
                        </div>
                        
                        <div class="col-span-2">
                            <div class="flex items-center mb-4">
                                <input type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1" <?php echo $maintenanceMode ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="maintenance_mode" class="ml-2 block text-sm text-gray-700">Maintenance Mode</label>
                            </div>
                            
                            <div class="flex items-center mb-4">
                                <input type="checkbox" name="debug_mode" id="debug_mode" value="1" <?php echo $debugMode ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="debug_mode" class="ml-2 block text-sm text-gray-700">Debug Mode</label>
                            </div>
                            
                            <div class="flex items-center mb-4">
                                <input type="checkbox" name="enable_dark_mode" id="enable_dark_mode" value="1" <?php echo $enableDarkMode ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="enable_dark_mode" class="ml-2 block text-sm text-gray-700">Enable Dark Mode by Default</label>
                            </div>
                        </div>
                        
                        <div>
                            <label for="default_language" class="block text-sm font-medium text-gray-700 mb-2">Default Language</label>
                            <select name="default_language" id="default_language" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="en" <?php echo $defaultLanguage == 'en' ? 'selected' : ''; ?>>English</option>
                                <option value="fr" <?php echo $defaultLanguage == 'fr' ? 'selected' : ''; ?>>French</option>
                                <option value="es" <?php echo $defaultLanguage == 'es' ? 'selected' : ''; ?>>Spanish</option>
                                <option value="de" <?php echo $defaultLanguage == 'de' ? 'selected' : ''; ?>>German</option>
                                <option value="it" <?php echo $defaultLanguage == 'it' ? 'selected' : ''; ?>>Italian</option>
                                <option value="pt" <?php echo $defaultLanguage == 'pt' ? 'selected' : ''; ?>>Portuguese</option>
                                <option value="ru" <?php echo $defaultLanguage == 'ru' ? 'selected' : ''; ?>>Russian</option>
                                <option value="zh" <?php echo $defaultLanguage == 'zh' ? 'selected' : ''; ?>>Chinese</option>
                                <option value="ar" <?php echo $defaultLanguage == 'ar' ? 'selected' : ''; ?>>Arabic</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">Default Timezone</label>
                            <select name="timezone" id="timezone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="UTC" <?php echo $timezone == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                <option value="America/New_York" <?php echo $timezone == 'America/New_York' ? 'selected' : ''; ?>>America/New York</option>
                                <option value="America/Los_Angeles" <?php echo $timezone == 'America/Los_Angeles' ? 'selected' : ''; ?>>America/Los Angeles</option>
                                <option value="Europe/London" <?php echo $timezone == 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                                <option value="Europe/Paris" <?php echo $timezone == 'Europe/Paris' ? 'selected' : ''; ?>>Europe/Paris</option>
                                <option value="Asia/Tokyo" <?php echo $timezone == 'Asia/Tokyo' ? 'selected' : ''; ?>>Asia/Tokyo</option>
                                <option value="Asia/Dubai" <?php echo $timezone == 'Asia/Dubai' ? 'selected' : ''; ?>>Asia/Dubai</option>
                                <option value="Australia/Sydney" <?php echo $timezone == 'Australia/Sydney' ? 'selected' : ''; ?>>Australia/Sydney</option>
                                <!-- Add more timezone options as needed -->
                            </select>
                        </div>
                        
                        <div>
                            <label for="date_format" class="block text-sm font-medium text-gray-700 mb-2">Date Format</label>
                            <select name="date_format" id="date_format" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="Y-m-d" <?php echo $dateFormat == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (e.g., 2025-04-28)</option>
                                <option value="d-m-Y" <?php echo $dateFormat == 'd-m-Y' ? 'selected' : ''; ?>>DD-MM-YYYY (e.g., 28-04-2025)</option>
                                <option value="m/d/Y" <?php echo $dateFormat == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (e.g., 04/28/2025)</option>
                                <option value="d/m/Y" <?php echo $dateFormat == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (e.g., 28/04/2025)</option>
                                <option value="F j, Y" <?php echo $dateFormat == 'F j, Y' ? 'selected' : ''; ?>>Month Day, Year (e.g., April 28, 2025)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="time_format" class="block text-sm font-medium text-gray-700 mb-2">Time Format</label>
                            <select name="time_format" id="time_format" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="H:i:s" <?php echo $timeFormat == 'H:i:s' ? 'selected' : ''; ?>>24 Hour (e.g., 14:30:00)</option>
                                <option value="h:i:s A" <?php echo $timeFormat == 'h:i:s A' ? 'selected' : ''; ?>>12 Hour (e.g., 02:30:00 PM)</option>
                                <option value="H:i" <?php echo $timeFormat == 'H:i' ? 'selected' : ''; ?>>24 Hour, No Seconds (e.g., 14:30)</option>
                                <option value="h:i A" <?php echo $timeFormat == 'h:i A' ? 'selected' : ''; ?>>12 Hour, No Seconds (e.g., 02:30 PM)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="session_timeout" class="block text-sm font-medium text-gray-700 mb-2">Session Timeout (minutes)</label>
                            <input type="number" name="session_timeout" id="session_timeout" value="<?php echo htmlspecialchars($sessionTimeout); ?>" min="5" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <p class="mt-1 text-sm text-gray-500">Minutes before user is automatically logged out due to inactivity</p>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" name="system_settings_submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Save System Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Tab functionality
    document.addEventListener('DOMContentLoaded', function() {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        
        // Function to switch tabs
        function switchTab(tabId) {
            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tab buttons
            tabButtons.forEach(button => {
                button.classList.remove('active', 'bg-indigo-100', 'text-indigo-700', 'border-b-2', 'border-indigo-500');
                button.classList.add('text-gray-500');
            });
            
            // Show the selected tab content
            const selectedTab = document.getElementById(tabId + '-tab');
            if (selectedTab) {
                selectedTab.classList.remove('hidden');
            }
            
            // Set active class to the clicked tab button
            const activeButton = document.querySelector(`.tab-button[data-tab="${tabId}"]`);
            if (activeButton) {
                activeButton.classList.add('active', 'bg-indigo-100', 'text-indigo-700', 'border-b-2', 'border-indigo-500');
                activeButton.classList.remove('text-gray-500');
            }
            
            // Save the active tab to localStorage
            localStorage.setItem('activeSettingsTab', tabId);
        }
        
        // Add click event to tab buttons
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                switchTab(tabId);
            });
        });
        
        // Check if there's a saved active tab in localStorage
        const savedTab = localStorage.getItem('activeSettingsTab');
        if (savedTab) {
            switchTab(savedTab);
        } else {
            // Use the first tab as default
            switchTab('general');
        }
        
        // Toggle password fields visibility
        const togglePasswordButtons = document.querySelectorAll('.toggle-password');
        togglePasswordButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordField = document.getElementById(targetId);
                
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    this.textContent = 'Hide';
                } else {
                    passwordField.type = 'password';
                    this.textContent = 'Show';
                }
            });
        });
        
        // Toggle form sections based on checkbox state
        function toggleSectionVisibility() {
            // Email notifications
            const emailSection = document.querySelector('#smtp-settings');
            const emailCheckbox = document.querySelector('#enable_email_notifications');
            if (emailSection && emailCheckbox) {
                emailSection.style.display = emailCheckbox.checked ? 'block' : 'none';
            }
            
            // SMS notifications
            const smsSection = document.querySelector('#sms-settings');
            const smsCheckbox = document.querySelector('#enable_sms_notifications');
            if (smsSection && smsCheckbox) {
                smsSection.style.display = smsCheckbox.checked ? 'block' : 'none';
            }
            
            // Payment gateways
            const stripeSection = document.querySelector('#stripe-settings');
            const stripeCheckbox = document.querySelector('#enable_stripe');
            if (stripeSection && stripeCheckbox) {
                stripeSection.style.display = stripeCheckbox.checked ? 'block' : 'none';
            }
            
            const paypalSection = document.querySelector('#paypal-settings');
            const paypalCheckbox = document.querySelector('#enable_paypal');
            if (paypalSection && paypalCheckbox) {
                paypalSection.style.display = paypalCheckbox.checked ? 'block' : 'none';
            }
            
            const razorpaySection = document.querySelector('#razorpay-settings');
            const razorpayCheckbox = document.querySelector('#enable_razorpay');
            if (razorpaySection && razorpayCheckbox) {
                razorpaySection.style.display = razorpayCheckbox.checked ? 'block' : 'none';
            }
            
            // Tax settings
            const taxDetailsSection = document.querySelector('#tax-details');
            const taxCheckbox = document.querySelector('#enable_tax');
            if (taxDetailsSection && taxCheckbox) {
                taxDetailsSection.style.display = taxCheckbox.checked ? 'block' : 'none';
            }
            
            // Reminder settings
            const reminderDetailsSection = document.querySelector('#reminder-details');
            const reminderCheckbox = document.querySelector('#send_reminders');
            if (reminderDetailsSection && reminderCheckbox) {
                reminderDetailsSection.style.display = reminderCheckbox.checked ? 'block' : 'none';
            }
        }
        
        // Initialize toggle sections
        toggleSectionVisibility();
        
        // Add event listeners to checkboxes that toggle sections
        const toggleCheckboxes = document.querySelectorAll('.toggle-section');
        toggleCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', toggleSectionVisibility);
        });
        
        // Test email and SMS functionality
        const testEmailButton = document.querySelector('#test-email');
        if (testEmailButton) {
            testEmailButton.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Show loading state
                this.disabled = true;
                this.textContent = 'Sending...';
                
                // Send test email using AJAX
                fetch('ajax/test-email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        smtp_host: document.querySelector('#smtp_host').value,
                        smtp_port: document.querySelector('#smtp_port').value,
                        smtp_username: document.querySelector('#smtp_username').value,
                        smtp_password: document.querySelector('#smtp_password').value,
                        smtp_encryption: document.querySelector('#smtp_encryption').value,
                        test_email: document.querySelector('#test_email_address').value
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    // Reset button state
                    this.disabled = false;
                    this.textContent = 'Test Email';
                    
                    // Show result
                    alert(data.success ? 'Test email sent successfully!' : 'Failed to send test email: ' + data.message);
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.disabled = false;
                    this.textContent = 'Test Email';
                    alert('Error sending test email: ' + error.message);
                });
            });
        }
        
        const testSmsButton = document.querySelector('#test-sms');
        if (testSmsButton) {
            testSmsButton.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Show loading state
                this.disabled = true;
                this.textContent = 'Sending...';
                
                // Send test SMS using AJAX
                fetch('ajax/test-sms.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        sms_api_key: document.querySelector('#sms_api_key').value,
                        sms_api_secret: document.querySelector('#sms_api_secret').value,
                        sms_from: document.querySelector('#sms_from').value,
                        test_phone: document.querySelector('#test_phone_number').value
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    // Reset button state
                    this.disabled = false;
                    this.textContent = 'Test SMS';
                    
                    // Show result
                    alert(data.success ? 'Test SMS sent successfully!' : 'Failed to send test SMS: ' + data.message);
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.disabled = false;
                    this.textContent = 'Test SMS';
                    alert('Error sending test SMS: ' + error.message);
                });
            });
        }
    });
</script>

<?php
// Include footer
include '../templates/footer.php';
?>
                            
            
            <!-- Tax Settings Form -->
            <div id="tax-tab" class="tab-content hidden">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <h2 class="text-xl font-semibold mb-4">Tax Settings</h2>
                            <div class="flex items-center mb-4">
                                <input type="checkbox" name="enable_tax" id="enable_tax" value="1" <?php echo $enableTax ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="enable_tax" class="ml-2 block text-sm text-gray-700">Enable Taxes on Invoices</label>
                            </div>
                        </div>
                        
                        <div>
                            <label for="tax_name" class="block text-sm font-medium text-gray-700 mb-2">Tax Name</label>
                            <input type="text" name="tax_name" id="tax_name" value="<?php echo htmlspecialchars($taxName); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <p class="mt-1 text-sm text-gray-500">Example: VAT, GST, Sales Tax, etc.</p>
                        </div>
                        
                        <div>
                            <label for="tax_rate" class="block text-sm font-medium text-gray-700 mb-2">Tax Rate</label>
                            <div class="flex">
                                <input type="number" name="tax_rate" id="tax_rate" value="<?php echo htmlspecialchars($taxRate); ?>" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <select name="tax_type" id="tax_type" class="ml-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="percentage" <?php echo $taxType == 'percentage' ? 'selected' : ''; ?>>%</option>
                                    <option value="fixed" <?php echo $taxType == 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-span-2">
                            <label for="tax_number" class="block text-sm font-medium text-gray-700 mb-2">Tax Registration Number</label>
                            <input type="text" name="tax_number" id="tax_number" value="<?php echo htmlspecialchars($taxNumber); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <p class="mt-1 text-sm text-gray-500">This will be displayed on invoices.</p>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" name="tax_settings_submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Save Tax Settings
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Notification Settings Form -->
            <div id="notification-tab" class="tab-content hidden">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <h2 class="text-xl font-semibold mb-4">Notification Settings</h2>
                        </div>
                        
                        <div class="col-span-2">
                            <div class="flex items-center mb-2">
                                <input type="checkbox" name="enable_email_notifications" id="enable_email_notifications" value="1" <?php echo $enableEmailNotif ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="enable_email_notifications" class="ml-2 block text-sm text-gray-700">Enable Email Notifications</label>
                            </div>
                            
                            <div class="flex items-center mb-2">
                                <input type="checkbox" name="enable_sms_notifications" id="enable_sms_notifications" value="1" <?php echo $enableSmsNotif ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="enable_sms_notifications" class="ml-2 block text-sm text-gray-700">Enable SMS Notifications</label>
                            </div>
                            
                            <div class="flex items-center mb-4">
                                <input type="checkbox" name="enable_push_notifications" id="enable_push_notifications" value="1" <?php echo $enablePushNotif ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="enable_push_notifications" class="ml-2 block text-sm text-gray-700">Enable Push Notifications</label>
                            </div>
                        </div>
                        
                        <div class="col-span-2">
                            <h3 class="text-lg font-medium mb-4">SMTP Settings</h3>
                        </div>
                        
                        <div>
                            <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-2">SMTP Host</label>
                            <input type="text" name="smtp_host" id="smtp_host" value="<?php echo htmlspecialchars($smtpHost); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-2">SMTP Port</label>
                            <input type="number" name="smtp_port" id="smtp_port" value="<?php echo htmlspecialchars($smtpPort); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="smtp_username" class="block text-sm font-medium text-gray-700 mb-2">SMTP Username</label>
                            <input type="text" name="smtp_username" id="smtp_username" value="<?php echo htmlspecialchars($smtpUsername); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="smtp_password" class="block text-sm font-medium text-gray-700 mb-2">SMTP Password</label>
                            <input type="password" name="smtp_password" id="smtp_password" value="<?php echo htmlspecialchars($smtpPassword); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="smtp_encryption" class="block text-sm font-medium text-gray-700 mb-2">SMTP Encryption</label>
                            <select name="smtp_encryption" id="smtp_encryption" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="tls" <?php echo $smtpEncryption == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo $smtpEncryption == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo $smtpEncryption == 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                        
                        <div class="col-span-2">
                            <h3 class="text-lg font-medium mb-4">SMS API Settings</h3>
                        </div>
                        
                        <div>
                            <label for="sms_api_key" class="block text-sm font-medium text-gray-700 mb-2">SMS API Key</label>
                            <input type="text" name="sms_api_key" id="sms_api_key" value="<?php echo htmlspecialchars($smsApiKey); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="sms_api_secret" class="block text-sm font-medium text-gray-700 mb-2">SMS API Secret</label>
                            <input type="password" name="sms_api_secret" id="sms_api_secret" value="<?php echo htmlspecialchars($smsApiSecret); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="sms_from" class="block text-sm font-medium text-gray-700 mb-2">SMS From Name/Number</label>
                            <input type="text" name="sms_from" id="sms_from" value="<?php echo htmlspecialchars($smsFrom); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" name="notification_settings_submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Save Notification Settings
                        </button>
                    </div>
                </form>
            </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" name="general_settings_submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Save General Settings
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Invoice Settings Form -->
            <div id="invoice-tab" class="tab-content hidden">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <h2 class="text-xl font-semibold mb-4">Invoice Settings</h2>
                        </div>
                        
                        <div>
                            <label for="invoice_prefix" class="block text-sm font-medium text-gray-700 mb-2">Invoice Prefix</label>
                            <input type="text" name="invoice_prefix" id="invoice_prefix" value="<?php echo htmlspecialchars($invoicePrefix); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="invoice_start_number" class="block text-sm font-medium text-gray-700 mb-2">Starting Invoice Number</label>
                            <input type="number" name="invoice_start_number" id="invoice_start_number" value="<?php echo htmlspecialchars($invoiceStartNumber); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="invoice_due_days" class="block text-sm font-medium text-gray-700 mb-2">Default Due Days</label>
                            <input type="number" name="invoice_due_days" id="invoice_due_days" value="<?php echo htmlspecialchars($invoiceDueDays); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="col-span-2">
                            <label for="invoice_terms" class="block text-sm font-medium text-gray-700 mb-2">Invoice Terms</label>
                            <textarea name="invoice_terms" id="invoice_terms" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($invoiceTerms); ?></textarea>
                        </div>
                        
                        <div class="col-span-2">
                            <label for="invoice_footer" class="block text-sm font-medium text-gray-700 mb-2">Invoice Footer</label>
                            <textarea name="invoice_footer" id="invoice_footer" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($invoiceFooter); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" name="invoice_settings_submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Save Invoice Settings
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Payment Settings Form -->
            <div id="payment-tab" class="tab-content hidden">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <h2 class="text-xl font-semibold mb-4">Payment Settings</h2>
                        </div>
                        
                        <div>
                            <label for="default_currency" class="block text-sm font-medium text-gray-700 mb-2">Default Currency</label>
                            <select name="default_currency" id="default_currency" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="USD" <?php echo $defaultCurrency == 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                <option value="EUR" <?php echo $defaultCurrency == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                <option value="GBP" <?php echo $defaultCurrency == 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                                <option value="CAD" <?php echo $defaultCurrency == 'CAD' ? 'selected' : ''; ?>>CAD - Canadian Dollar</option>
                                <option value="AUD" <?php echo $defaultCurrency == 'AUD' ? 'selected' : ''; ?>>AUD - Australian Dollar</option>
                                <option value="JPY" <?php echo $defaultCurrency == 'JPY' ? 'selected' : ''; ?>>JPY - Japanese Yen</option>
                                <option value="INR" <?php echo $defaultCurrency == 'INR' ? 'selected' : ''; ?>>INR - Indian Rupee</option>
                                <option value="CNY" <?php echo $defaultCurrency == 'CNY' ? 'selected' : ''; ?>>CNY - Chinese Yuan</option>
                            </select>
                        </div>
                        
                        <div class="col-span-2">
                            <div class="flex items-center">
                                <input type="checkbox" name="enable_wallet" id="enable_wallet" value="1" <?php echo $enableWallet ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="enable_wallet" class="ml-2 block text-sm text-gray-700">Enable Wallet/Prepaid Credits System</label>
                            </div>
                        </div>
                        
                        <div class="col-span-2">
                            <h3 class="text-lg font-medium mb-4">Stripe Integration</h3>
                            <div class="flex items-center mb-4">
                                <input type="checkbox" name="enable_stripe" id="enable_stripe" value="1" <?php echo $enableStripe ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="enable_stripe" class="ml-2 block text-sm text-gray-700">Enable Stripe Payment Gateway</label>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="stripe_public_key" class="block text-sm font-medium text-gray-700 mb-2">Stripe Public Key</label>
                                    <input type="text" name="stripe_public_key" id="stripe_public_key" value="<?php echo htmlspecialchars($stripePublicKey); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                
                                <div>
                                    <label for="stripe_secret_key" class="block text-sm font-medium text-gray-700 mb-2">Stripe Secret Key</label>
                                    <input type="password" name="stripe_secret_key" id="stripe_secret_key" value="<?php echo htmlspecialchars($stripeSecretKey); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-span-2">
                            <h3 class="text-lg font-medium mb-4">PayPal Integration</h3>
                            <div class="flex items-center mb-4">
                                <input type="checkbox" name="enable_paypal" id="enable_paypal" value="1" <?php echo $enablePaypal ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="enable_paypal" class="ml-2 block text-sm text-gray-700">Enable PayPal Payment Gateway</label>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="paypal_client_id" class="block text-sm font-medium text-gray-700 mb-2">PayPal Client ID</label>
                                    <input type="text" name="paypal_client_id" id="paypal_client_id" value="<?php echo htmlspecialchars($paypalClientId); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                
                                <div>
                                    <label for="paypal_secret" class="block text-sm font-medium text-gray-700 mb-2">PayPal Secret</label>
                                    <input type="password" name="paypal_secret" id="paypal_secret" value="<?php echo htmlspecialchars($paypalSecret); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-span-2">
                            <h3 class="text-lg font-medium mb-4">Razorpay Integration</h3>
                            <div class="flex items-center mb-4">
                                <input type="checkbox" name="enable_razorpay" id="enable_razorpay" value="1" <?php echo $enableRazorpay ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="enable_razorpay" class="ml-2 block text-sm text-gray-700">Enable Razorpay Payment Gateway</label>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="razorpay_key_id" class="block text-sm font-medium text-gray-700 mb-2">Razorpay Key ID</label>
                                    <input type="text" name="razorpay_key_id" id="razorpay_key_id" value="<?php echo htmlspecialchars($razorpayKeyId); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                
                                <div>
                                    <label for="razorpay_key_secret" class="block text-sm font-medium text-gray-700 mb-2">Razorpay Key Secret</label>
                                    <input type="password" name="razorpay_key_secret" id="razorpay_key_secret" value="<?php echo htmlspecialchars($razorpayKeySecret); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                            </div>
                        </div>