<?php
/**
 * Validation Helper
 * 
 * A collection of functions to validate user inputs throughout the application.
 * Helps maintain consistent validation rules and error messages.
 */

// Prevent direct access to this file
if (!defined('BASE_PATH')) {
    exit('Direct script access is not allowed');
}

/**
 * Validate an email address
 * 
 * @param string $email Email to validate
 * @return bool|string True if valid, error message if invalid
 */
function validate_email($email) {
    $email = trim($email);
    
    if (empty($email)) {
        return "Email address is required";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Please enter a valid email address";
    }
    
    return true;
}

/**
 * Validate a password
 * 
 * @param string $password Password to validate
 * @param bool $is_new Whether this is a new password (true) or existing (false)
 * @return bool|string True if valid, error message if invalid
 */
function validate_password($password, $is_new = true) {
    if ($is_new) {
        // For new passwords, enforce stronger rules
        if (strlen($password) < 8) {
            return "Password must be at least 8 characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return "Password must contain at least one number";
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return "Password must contain at least one special character";
        }
    } else {
        // For existing password checks (login), just check it's not empty
        if (empty($password)) {
            return "Password is required";
        }
    }
    
    return true;
}

/**
 * Validate a username
 * 
 * @param string $username Username to validate
 * @return bool|string True if valid, error message if invalid
 */
function validate_username($username) {
    $username = trim($username);
    
    if (empty($username)) {
        return "Username is required";
    }
    
    if (strlen($username) < 3 || strlen($username) > 50) {
        return "Username must be between 3 and 50 characters";
    }
    
    if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        return "Username can only contain letters, numbers, and underscores";
    }
    
    return true;
}

/**
 * Validate a name (first name, last name, etc.)
 * 
 * @param string $name Name to validate
 * @param string $field_name Field name for the error message
 * @return bool|string True if valid, error message if invalid
 */
function validate_name($name, $field_name = 'Name') {
    $name = trim($name);
    
    if (empty($name)) {
        return "$field_name is required";
    }
    
    if (strlen($name) > 100) {
        return "$field_name must be less than 100 characters";
    }
    
    if (!preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\s\'-]+$/', $name)) {
        return "$field_name can only contain letters, spaces, hyphens, and apostrophes";
    }
    
    return true;
}

/**
 * Validate a phone number
 * 
 * @param string $phone Phone number to validate
 * @return bool|string True if valid, error message if invalid
 */
function validate_phone($phone) {
    $phone = trim(preg_replace('/[^0-9+]/', '', $phone));
    
    if (empty($phone)) {
        return "Phone number is required";
    }
    
    if (strlen($phone) < 10 || strlen($phone) > 15) {
        return "Please enter a valid phone number";
    }
    
    return true;
}

/**
 * Validate a date
 * 
 * @param string $date Date to validate (YYYY-MM-DD format)
 * @param string $field_name Field name for the error message
 * @return bool|string True if valid, error message if invalid
 */
function validate_date($date, $field_name = 'Date') {
    $date = trim($date);
    
    if (empty($date)) {
        return "$field_name is required";
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return "$field_name must be in YYYY-MM-DD format";
    }
    
    $date_parts = explode('-', $date);
    if (!checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
        return "Please enter a valid $field_name";
    }
    
    return true;
}

/**
 * Validate a numeric value
 * 
 * @param mixed $value Value to validate
 * @param string $field_name Field name for the error message
 * @param float|null $min Minimum value (optional)
 * @param float|null $max Maximum value (optional)
 * @return bool|string True if valid, error message if invalid
 */
function validate_numeric($value, $field_name = 'Value', $min = null, $max = null) {
    $value = trim($value);
    
    if ($value === '') {
        return "$field_name is required";
    }
    
    if (!is_numeric($value)) {
        return "$field_name must be a number";
    }
    
    if ($min !== null && $value < $min) {
        return "$field_name must be at least $min";
    }
    
    if ($max !== null && $value > $max) {
        return "$field_name must be no more than $max";
    }
    
    return true;
}

/**
 * Validate a URL
 * 
 * @param string $url URL to validate
 * @param bool $required Whether the URL is required
 * @return bool|string True if valid, error message if invalid
 */
function validate_url($url, $required = true) {
    $url = trim($url);
    
    if (empty($url)) {
        return $required ? "URL is required" : true;
    }
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return "Please enter a valid URL";
    }
    
    return true;
}

/**
 * Sanitize input data
 * 
 * @param mixed $data Data to sanitize
 * @return mixed Sanitized data
 */
function sanitize_input($data) {
    if (is_array($data)) {
        // Recursively sanitize array
        return array_map('sanitize_input', $data);
    }
    
    // Trim whitespace
    $data = trim($data);
    
    // Strip HTML tags except allowed ones
    $data = strip_tags($data);
    
    // Convert special characters to HTML entities
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}

/**
 * Validate a form token (CSRF protection)
 * 
 * @param string $token The token to validate
 * @return bool True if valid, false if invalid
 */
function validate_form_token($token) {
    if (!isset($_SESSION['form_tokens']) || !is_array($_SESSION['form_tokens'])) {
        return false;
    }
    
    foreach ($_SESSION['form_tokens'] as $stored_token => $expiry) {
        // Remove expired tokens
        if (time() > $expiry) {
            unset($_SESSION['form_tokens'][$stored_token]);
            continue;
        }
        
        // Token matches
        if (hash_equals($stored_token, $token)) {
            // Remove used token
            unset($_SESSION['form_tokens'][$stored_token]);
            return true;
        }
    }
    
    return false;
}

/**
 * Generate a form token (CSRF protection)
 * 
 * @param int $expiry_time Token expiry time in seconds (default: 3600 = 1 hour)
 * @return string The generated token
 */
function generate_form_token($expiry_time = 3600) {
    $token = bin2hex(random_bytes(32));
    
    if (!isset($_SESSION['form_tokens']) || !is_array($_SESSION['form_tokens'])) {
        $_SESSION['form_tokens'] = [];
    }
    
    // Store token with expiry time
    $_SESSION['form_tokens'][$token] = time() + $expiry_time;
    
    return $token;
}

/**
 * Validate file upload
 * 
 * @param array $file File data from $_FILES
 * @param array $allowed_types Allowed MIME types
 * @param int $max_size Maximum file size in bytes
 * @return bool|string True if valid, error message if invalid
 */
function validate_file($file, $allowed_types = [], $max_size = 5242880) {
    // Check if file was uploaded properly
    if (!isset($file['error']) || is_array($file['error'])) {
        return "Invalid file upload";
    }
    
    // Check for upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "Exceeded file size limit";
        default:
            return "Unknown file upload error";
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return "File size exceeds limit of " . round($max_size / 1048576, 2) . " MB";
    }
    
    // Check file type if specified
    if (!empty($allowed_types)) {
        $file_info = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $file_info->file($file['tmp_name']);
        
        if (!in_array($mime_type, $allowed_types)) {
            return "File type not allowed";
        }
    }
    
    return true;
}

/**
 * Validate a service ID
 * 
 * @param int $service_id Service ID to validate
 * @return bool|string True if valid, error message if invalid
 */
function validate_service_id($service_id) {
    if (!is_numeric($service_id) || $service_id < 1) {
        return "Invalid service selected";
    }
    
    // Additional check to see if service exists in database can be added here
    
    return true;
}

/**
 * Validate an order quantity
 * 
 * @param int $quantity Quantity to validate
 * @param int $min Minimum quantity (default: 1)
 * @param int $max Maximum quantity (default: 100)
 * @return bool|string True if valid, error message if invalid
 */
function validate_quantity($quantity, $min = 1, $max = 100) {
    if (!is_numeric($quantity) || intval($quantity) != $quantity) {
        return "Quantity must be a whole number";
    }
    
    $quantity = intval($quantity);
    
    if ($quantity < $min) {
        return "Quantity must be at least $min";
    }
    
    if ($quantity > $max) {
        return "Quantity cannot exceed $max";
    }
    
    return true;
}

/**
 * Validate a payment amount
 * 
 * @param float $amount Amount to validate
 * @param float $min Minimum amount (default: 0.01)
 * @return bool|string True if valid, error message if invalid
 */
function validate_payment_amount($amount, $min = 0.01) {
    if (!is_numeric($amount)) {
        return "Payment amount must be a number";
    }
    
    $amount = floatval($amount);
    
    if ($amount < $min) {
        return "Payment amount must be at least " . number_format($min, 2);
    }
    
    return true;
}
?>