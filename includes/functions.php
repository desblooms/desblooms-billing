 
<?php
/**
 * Digital Service Billing Mobile App
 * Helper Functions
 */

// Prevent direct script access
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Format price with currency symbol
 * 
 * @param float $amount The amount to format
 * @param string $currency Currency code (default: USD)
 * @return string Formatted price
 */
function formatPrice($amount, $currency = 'USD') {
    $currencies = [
        'USD' => ['symbol' => '$', 'position' => 'before'],
        'EUR' => ['symbol' => '€', 'position' => 'after'],
        'GBP' => ['symbol' => '£', 'position' => 'before'],
        'INR' => ['symbol' => '₹', 'position' => 'before'],
        // Add more currencies as needed
    ];
    
    // Fallback to USD if currency not found
    if (!isset($currencies[$currency])) {
        $currency = 'USD';
    }
    
    $formatted = number_format($amount, 2, '.', ',');
    
    if ($currencies[$currency]['position'] === 'before') {
        return $currencies[$currency]['symbol'] . $formatted;
    } else {
        return $formatted . $currencies[$currency]['symbol'];
    }
}

/**
 * Generate a unique invoice number
 * 
 * @param string $prefix Optional prefix for invoice number
 * @return string Unique invoice number
 */
function generateInvoiceNumber($prefix = 'INV') {
    $timestamp = time();
    $random = rand(1000, 9999);
    return $prefix . '-' . date('Ymd', $timestamp) . '-' . $random;
}

/**
 * Sanitize user input
 * 
 * @param string $data Data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    global $conn; // Database connection
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    
    if (isset($conn)) {
        $data = mysqli_real_escape_string($conn, $data);
    }
    
    return $data;
}

/**
 * Validate email address
 * 
 * @param string $email Email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate a secure password hash
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password against hash
 * 
 * @param string $password Plain text password
 * @param string $hash Stored hash
 * @return bool True if password matches hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate a random token
 * 
 * @param int $length Length of token
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check user permission based on role
 * 
 * @param string $permission Permission to check
 * @param string $role User role (default: current user role)
 * @return bool True if permitted, false otherwise
 */
function hasPermission($permission, $role = null) {
    if ($role === null) {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }
        $role = $_SESSION['user_role'];
    }
    
    $permissions = [
        'admin' => [
            'manage_services',
            'manage_users',
            'manage_invoices',
            'view_reports',
            'system_settings',
            'view_dashboard',
            'create_manual_invoice',
            'delete_invoice',
            'manage_payments',
            'manage_tax_rules',
        ],
        'staff' => [
            'view_dashboard',
            'manage_services',
            'view_invoices',
            'create_manual_invoice',
            'manage_payments',
        ],
        'customer' => [
            'view_services',
            'purchase_services',
            'view_own_invoices',
            'make_payments',
            'update_profile',
        ],
    ];
    
    // If role doesn't exist, deny permission
    if (!isset($permissions[$role])) {
        return false;
    }
    
    return in_array($permission, $permissions[$role]);
}

/**
 * Redirect to a specific page
 * 
 * @param string $page Page to redirect to
 * @param array $params URL parameters
 * @return void
 */
function redirect($page, $params = []) {
    $url = $page;
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    header("Location: $url");
    exit;
}

/**
 * Set flash message in session
 * 
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message text
 * @return void
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
        'time' => time(),
    ];
}

/**
 * Get and clear flash message from session
 * 
 * @return array|null Flash message or null if none exists
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        // Only return message if it's less than 5 minutes old
        if ($message['time'] > (time() - 300)) {
            return $message;
        }
    }
    
    return null;
}

/**
 * Generate pagination links
 * 
 * @param int $current_page Current page number
 * @param int $total_pages Total number of pages
 * @param string $url_pattern URL pattern with %d placeholder for page number
 * @return string HTML for pagination links
 */
function generatePagination($current_page, $total_pages, $url_pattern) {
    if ($total_pages <= 1) {
        return '';
    }
    
    $pagination = '<div class="pagination">';
    
    // Previous page link
    if ($current_page > 1) {
        $pagination .= '<a href="' . sprintf($url_pattern, $current_page - 1) . '" class="prev">&laquo; Previous</a>';
    } else {
        $pagination .= '<span class="prev disabled">&laquo; Previous</span>';
    }
    
    // Page number links
    $range = 2;
    for ($i = max(1, $current_page - $range); $i <= min($total_pages, $current_page + $range); $i++) {
        if ($i == $current_page) {
            $pagination .= '<span class="current">' . $i . '</span>';
        } else {
            $pagination .= '<a href="' . sprintf($url_pattern, $i) . '">' . $i . '</a>';
        }
    }
    
    // Next page link
    if ($current_page < $total_pages) {
        $pagination .= '<a href="' . sprintf($url_pattern, $current_page + 1) . '" class="next">Next &raquo;</a>';
    } else {
        $pagination .= '<span class="next disabled">Next &raquo;</span>';
    }
    
    $pagination .= '</div>';
    
    return $pagination;
}

/**
 * Calculate due days for invoice
 * 
 * @param string $due_date Due date (Y-m-d format)
 * @return int Number of days until due (negative if overdue)
 */
function calculateDueDays($due_date) {
    $due = strtotime($due_date);
    $now = time();
    
    $diff = $due - $now;
    return floor($diff / (60 * 60 * 24));
}

/**
 * Get invoice status class for styling
 * 
 * @param string $status Invoice status
 * @return string CSS class name
 */
function getInvoiceStatusClass($status) {
    $classes = [
        'pending' => 'text-yellow-500',
        'outstanding' => 'text-red-500',
        'paid' => 'text-green-500',
        'canceled' => 'text-gray-500',
        'partially_paid' => 'text-blue-500',
    ];
    
    return isset($classes[$status]) ? $classes[$status] : '';
}

/**
 * Calculate tax amount for a given subtotal
 * 
 * @param float $subtotal Subtotal amount
 * @param float $tax_rate Tax rate percentage
 * @return float Tax amount
 */
function calculateTax($subtotal, $tax_rate) {
    return round($subtotal * ($tax_rate / 100), 2);
}

/**
 * Calculate discount amount for a given subtotal
 * 
 * @param float $subtotal Subtotal amount
 * @param float $discount_value Discount value
 * @param string $discount_type Discount type (percentage or fixed)
 * @return float Discount amount
 */
function calculateDiscount($subtotal, $discount_value, $discount_type = 'percentage') {
    if ($discount_type === 'percentage') {
        return round($subtotal * ($discount_value / 100), 2);
    } else {
        return min($discount_value, $subtotal); // Fixed amount, capped at subtotal
    }
}

/**
 * Format date in user's preferred format
 * 
 * @param string $date Date string
 * @param string $format Date format (default: Y-m-d)
 * @return string Formatted date
 */
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) {
        return '';
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    
    // Use user's preferred format if available
    if (isset($_SESSION['user_date_format']) && !empty($_SESSION['user_date_format'])) {
        $format = $_SESSION['user_date_format'];
    }
    
    return date($format, $timestamp);
}

/**
 * Get time elapsed string (e.g., "2 days ago")
 * 
 * @param string $datetime Date and time string
 * @return string Time elapsed text
 */
function timeElapsed($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = [
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!empty($string)) {
        return key($string) === 's' ? 'just now' : current($string) . ' ago';
    }
    
    return 'just now';
}

/**
 * Truncate text to a specified length
 * 
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix for truncated text
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * Generate a secure random password
 * 
 * @param int $length Password length
 * @return string Generated password
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
    $password = '';
    
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    
    return $password;
}

/**
 * Log system activity
 * 
 * @param string $action Action performed
 * @param int $user_id User ID
 * @param string $details Additional details
 * @return bool True on success, false on failure
 */
function logActivity($action, $user_id = null, $details = '') {
    global $conn;
    
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('issss', $user_id, $action, $details, $ip_address, $user_agent);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    return false;
}

/**
 * Get current page URL
 * 
 * @param bool $query_string Include query string
 * @return string Current URL
 */
function getCurrentUrl($query_string = true) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    if (!$query_string && strpos($url, '?') !== false) {
        $url = substr($url, 0, strpos($url, '?'));
    }
    
    return $url;
}

/**
 * Generate a JWT token for API authentication
 * 
 * @param array $payload Token payload data
 * @param string $secret Secret key
 * @param int $expiry Expiry time in seconds
 * @return string Generated JWT token
 */
function generateJWT($payload, $secret, $expiry = 3600) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    
    $payload['exp'] = time() + $expiry;
    $payload['iat'] = time();
    $payload_json = json_encode($payload);
    
    $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload_json));
    
    $signature = hash_hmac('sha256', $base64_header . '.' . $base64_payload, $secret, true);
    $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64_header . '.' . $base64_payload . '.' . $base64_signature;
}

/**
 * Verify a JWT token
 * 
 * @param string $token JWT token
 * @param string $secret Secret key
 * @return array|false Payload if valid, false otherwise
 */
function verifyJWT($token, $secret) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    
    list($base64_header, $base64_payload, $base64_signature) = $parts;
    
    $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64_signature));
    $expected_signature = hash_hmac('sha256', $base64_header . '.' . $base64_payload, $secret, true);
    
    if (!hash_equals($expected_signature, $signature)) {
        return false;
    }
    
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64_payload)), true);
    
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false; // Token expired
    }
    
    return $payload;
}

/**
 * Send an email using PHPMailer
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body Email body
 * @param array $options Additional options (cc, bcc, attachments)
 * @return bool True on success, false on failure
 */
function sendEmail($to, $subject, $body, $options = []) {
    // Requires PHPMailer to be installed and configured
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        if (isset($options['cc'])) {
            foreach ((array)$options['cc'] as $cc) {
                $mail->addCC($cc);
            }
        }
        
        if (isset($options['bcc'])) {
            foreach ((array)$options['bcc'] as $bcc) {
                $mail->addBCC($bcc);
            }
        }
        
        // Attachments
        if (isset($options['attachments'])) {
            foreach ((array)$options['attachments'] as $attachment) {
                $mail->addAttachment($attachment);
            }
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email Error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Get the status of a service by ID
 * 
 * @param int $service_id Service ID
 * @return string Status (active, inactive, discontinued)
 */
function getServiceStatus($service_id) {
    global $conn;
    
    $sql = "SELECT status FROM services WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param('i', $service_id);
        $stmt->execute();
        $stmt->bind_result($status);
        $stmt->fetch();
        $stmt->close();
        
        return $status ?? 'unknown';
    }
    
    return 'unknown';
}

/**
 * Check if coupon code is valid
 * 
 * @param string $code Coupon code
 * @param float $subtotal Order subtotal
 * @param int $user_id User ID
 * @return array|false Coupon data if valid, false otherwise
 */
function validateCoupon($code, $subtotal = 0, $user_id = 0) {
    global $conn;
    
    $sql = "SELECT * FROM coupons WHERE code = ? AND active = 1 
            AND (expiry_date IS NULL OR expiry_date >= CURDATE())
            AND (min_order_value IS NULL OR min_order_value <= ?)
            AND (max_uses IS NULL OR uses < max_uses)";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param('sd', $code, $subtotal);
        $stmt->execute();
        $result = $stmt->get_result();
        $coupon = $result->fetch_assoc();
        $stmt->close();
        
        if (!$coupon) {
            return false;
        }
        
        // Check if user has already used this coupon
        if ($user_id && $coupon['one_per_customer']) {
            $sql = "SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $coupon['id'], $user_id);
            $stmt->execute();
            $stmt->bind_result($usage_count);
            $stmt->fetch();
            $stmt->close();
            
            if ($usage_count > 0) {
                return false; // User has already used this coupon
            }
        }
        
        return $coupon;
    }
    
    return false;
}

/**
 * Generate CSV file from data
 * 
 * @param array $data Array of data rows
 * @param array $headers Column headers
 * @param string $filename Output filename
 * @return bool True on success, false on failure
 */
function generateCSV($data, $headers, $filename) {
    // Set headers for file download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    if ($output === false) {
        return false;
    }
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    return true;
}

/**
 * Clean and normalize a URL slug
 * 
 * @param string $text Text to convert to slug
 * @return string Slug
 */
function createSlug($text) {
    // Replace non letter or digit with -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // Transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // Trim
    $text = trim($text, '-');
    // Remove duplicate -
    $text = preg_replace('~-+~', '-', $text);
    // Lowercase
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

/**
 * Output JSON response
 * 
 * @param mixed $data Response data
 * @param int $status HTTP status code
 * @return void
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

?>