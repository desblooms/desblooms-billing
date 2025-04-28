<?php
/**
 * Authentication Functions
 * 
 * This file contains all authentication related functions for the Digital Service Billing App
 * Including: registration, login, password recovery, session management, and user role verification
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'db.php';
require_once 'functions.php';

/**
 * User Registration
 * 
 * @param string $fullname User's full name
 * @param string $email User's email
 * @param string $password User's password
 * @param string $role User's role (default: customer)
 * @return array Status and message
 */
function registerUser($fullname, $email, $password, $role = 'customer') {
    global $conn;
    
    // Validate inputs
    if (empty($fullname) || empty($email) || empty($password)) {
        return ['status' => 'error', 'message' => 'All fields are required'];
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'error', 'message' => 'Invalid email format'];
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['status' => 'error', 'message' => 'Email already registered'];
    }
    
    // Password validation (at least 8 characters with letters and numbers)
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return ['status' => 'error', 'message' => 'Password must be at least 8 characters and contain both letters and numbers'];
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Allowed roles
    $allowed_roles = ['admin', 'customer', 'staff'];
    if (!in_array($role, $allowed_roles)) {
        $role = 'customer'; // Default role
    }
    
    // Generate verification token
    $verification_token = bin2hex(random_bytes(32));
    $verified = 0; // Default to unverified
    
    // Current time
    $created_at = date('Y-m-d H:i:s');
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, role, verification_token, verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $fullname, $email, $hashed_password, $role, $verification_token, $verified, $created_at);
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        
        // Send verification email
        sendVerificationEmail($email, $verification_token);
        
        return [
            'status' => 'success', 
            'message' => 'Registration successful! Please check your email to verify your account.',
            'user_id' => $user_id
        ];
    } else {
        return ['status' => 'error', 'message' => 'Registration failed: ' . $stmt->error];
    }
}

/**
 * Send verification email
 * 
 * @param string $email User's email
 * @param string $token Verification token
 * @return bool Success or failure
 */
function sendVerificationEmail($email, $token) {
    $verify_url = getBaseUrl() . "verify.php?email=" . urlencode($email) . "&token=" . $token;
    
    $subject = "Verify Your Email Address";
    $message = file_get_contents('email-templates/verification.html');
    $message = str_replace('{{VERIFY_URL}}', $verify_url, $message);
    $message = str_replace('{{EMAIL}}', $email, $message);
    
    // Send email using your email function from functions.php
    return sendEmail($email, $subject, $message);
}

/**
 * Verify user's email
 * 
 * @param string $email User's email
 * @param string $token Verification token
 * @return array Status and message
 */
function verifyEmail($email, $token) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND verification_token = ?");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Update user as verified
        $stmt = $conn->prepare("UPDATE users SET verified = 1, verification_token = '' WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        
        if ($stmt->execute()) {
            return ['status' => 'success', 'message' => 'Email verified successfully. You can now login.'];
        } else {
            return ['status' => 'error', 'message' => 'Verification failed: ' . $stmt->error];
        }
    } else {
        return ['status' => 'error', 'message' => 'Invalid verification link or already verified'];
    }
}

/**
 * User Login
 * 
 * @param string $email User's email
 * @param string $password User's password
 * @param bool $remember_me Remember login
 * @return array Status and user data
 */
function loginUser($email, $password, $remember_me = false) {
    global $conn;
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        return ['status' => 'error', 'message' => 'Email and password are required'];
    }
    
    // Get user by email
    $stmt = $conn->prepare("SELECT id, fullname, email, password, role, verified, status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['status' => 'error', 'message' => 'Invalid email or password'];
    }
    
    $user = $result->fetch_assoc();
    
    // Check if account is verified
    if ($user['verified'] != 1) {
        return ['status' => 'error', 'message' => 'Please verify your email before logging in'];
    }
    
    // Check if account is active
    if ($user['status'] != 'active') {
        return ['status' => 'error', 'message' => 'Your account is not active. Please contact support.'];
    }
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Remove password from array
        unset($user['password']);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_fullname'] = $user['fullname'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        // Update last login time
        $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        
        // Set remember me cookie if requested
        if ($remember_me) {
            setRememberMeCookie($user['id']);
        }
        
        // Track login for security (optional)
        logUserLogin($user['id']);
        
        // Return user data
        return [
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user
        ];
    } else {
        return ['status' => 'error', 'message' => 'Invalid email or password'];
    }
}

/**
 * Set remember me cookie
 * 
 * @param int $user_id User ID
 * @return bool Success or failure
 */
function setRememberMeCookie($user_id) {
    global $conn;
    
    // Generate a unique token
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    
    // Hash the validator for storage
    $hashed_validator = password_hash($validator, PASSWORD_DEFAULT);
    
    // Set expiry date (30 days)
    $expires = date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60);
    
    // Delete any existing tokens for this user
    $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Store new token in database
    $stmt = $conn->prepare("INSERT INTO auth_tokens (user_id, selector, token, expires) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $selector, $hashed_validator, $expires);
    
    if ($stmt->execute()) {
        // Set cookie with selector and validator
        $cookie_value = $selector . ':' . $validator;
        setcookie('remember_me', $cookie_value, time() + 30 * 24 * 60 * 60, '/', '', true, true);
        return true;
    }
    
    return false;
}

/**
 * Check and process remember me cookie
 * 
 * @return bool Success or failure
 */
function checkRememberMeCookie() {
    global $conn;
    
    if (isset($_COOKIE['remember_me']) && !isLoggedIn()) {
        list($selector, $validator) = explode(':', $_COOKIE['remember_me']);
        
        $stmt = $conn->prepare("SELECT auth_tokens.token, users.* FROM auth_tokens 
                               JOIN users ON auth_tokens.user_id = users.id 
                               WHERE auth_tokens.selector = ? AND auth_tokens.expires > NOW()");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            if (password_verify($validator, $row['token'])) {
                // Set session variables
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_email'] = $row['email'];
                $_SESSION['user_fullname'] = $row['fullname'];
                $_SESSION['user_role'] = $row['role'];
                $_SESSION['logged_in'] = true;
                
                // Update last login time
                $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $row['id']);
                $stmt->execute();
                
                // Refresh the remember me cookie
                setRememberMeCookie($row['id']);
                
                return true;
            }
        }
        
        // Invalid cookie, remove it
        setcookie('remember_me', '', time() - 3600, '/');
    }
    
    return false;
}

/**
 * Log user login for security purposes
 * 
 * @param int $user_id User ID
 * @return bool Success or failure
 */
function logUserLogin($user_id) {
    global $conn;
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, login_time) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $ip_address, $user_agent);
    
    return $stmt->execute();
}

/**
 * Forgot Password - Send reset link
 * 
 * @param string $email User's email
 * @return array Status and message
 */
function forgotPassword($email) {
    global $conn;
    
    if (empty($email)) {
        return ['status' => 'error', 'message' => 'Email is required'];
    }
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ? AND verified = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // For security reasons, still show success even if email doesn't exist
        return ['status' => 'success', 'message' => 'If your email exists in our system, you will receive a password reset link'];
    }
    
    $user = $result->fetch_assoc();
    
    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 1 * 60 * 60); // 1 hour expiry
    
    // Store token in database
    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires) VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE token = VALUES(token), expires = VALUES(expires)");
    $stmt->bind_param("iss", $user['id'], $token, $expires);
    
    if ($stmt->execute()) {
        // Send reset email
        $reset_url = getBaseUrl() . "reset-password.php?email=" . urlencode($email) . "&token=" . $token;
        
        $subject = "Reset Your Password";
        $message = file_get_contents('email-templates/password-reset.html');
        $message = str_replace('{{RESET_URL}}', $reset_url, $message);
        $message = str_replace('{{NAME}}', $user['fullname'], $message);
        
        sendEmail($email, $subject, $message);
        
        return ['status' => 'success', 'message' => 'If your email exists in our system, you will receive a password reset link'];
    } else {
        return ['status' => 'error', 'message' => 'Failed to process request. Please try again later.'];
    }
}

/**
 * Verify reset token
 * 
 * @param string $email User's email
 * @param string $token Reset token
 * @return array Status and user ID
 */
function verifyResetToken($email, $token) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT password_resets.user_id 
                           FROM password_resets 
                           JOIN users ON password_resets.user_id = users.id 
                           WHERE users.email = ? AND password_resets.token = ? AND password_resets.expires > NOW()");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return ['status' => 'success', 'user_id' => $row['user_id']];
    } else {
        return ['status' => 'error', 'message' => 'Invalid or expired reset link'];
    }
}

/**
 * Reset password
 * 
 * @param int $user_id User ID
 * @param string $password New password
 * @return array Status and message
 */
function resetPassword($user_id, $password) {
    global $conn;
    
    // Password validation
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return ['status' => 'error', 'message' => 'Password must be at least 8 characters and contain both letters and numbers'];
    }
    
    // Hash new password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($stmt->execute()) {
        // Delete used reset token
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        return ['status' => 'success', 'message' => 'Password has been reset successfully. You can now login with your new password.'];
    } else {
        return ['status' => 'error', 'message' => 'Failed to reset password. Please try again.'];
    }
}

/**
 * User Logout
 * 
 * @return void
 */
function logoutUser() {
    // Clear remember me cookie if exists
    if (isset($_COOKIE['remember_me'])) {
        // Remove token from database
        if (isset($_SESSION['user_id'])) {
            global $conn;
            $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
        }
        
        // Delete cookie
        setcookie('remember_me', '', time() - 3600, '/');
    }
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Start a new session
    session_start();
}

/**
 * Check if user is logged in
 * 
 * @return bool User logged in status
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Get current user ID
 * 
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get current user role
 * 
 * @return string|null User role or null if not logged in
 */
function getCurrentUserRole() {
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
}

/**
 * Check if current user has specific role
 * 
 * @param string|array $roles Role or array of roles to check
 * @return bool Has role status
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $current_role = getCurrentUserRole();
    
    if (is_array($roles)) {
        return in_array($current_role, $roles);
    } else {
        return $current_role === $roles;
    }
}

/**
 * Require authentication to access page
 * Redirects to login page if not logged in
 * 
 * @return void
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Store current URL for redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login page
        header('Location: ' . getBaseUrl() . 'login.php');
        exit;
    }
}

/**
 * Require specific role to access page
 * Redirects to appropriate page if not authorized
 * 
 * @param string|array $roles Role or array of roles allowed
 * @return void
 */
function requireRole($roles) {
    requireLogin();
    
    if (!hasRole($roles)) {
        // Redirect based on user's role
        $current_role = getCurrentUserRole();
        
        if ($current_role === 'admin') {
            header('Location: ' . getBaseUrl() . 'admin/');
        } elseif ($current_role === 'staff') {
            header('Location: ' . getBaseUrl() . 'staff/');
        } else {
            header('Location: ' . getBaseUrl() . 'index.php');
        }
        exit;
    }
}

/**
 * Get user data by ID
 * 
 * @param int $user_id User ID
 * @return array|false User data or false if not found
 */
function getUserById($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, fullname, email, role, phone, address, profile_image, created_at, last_login, status 
                           FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return false;
    }
}

/**
 * Update user profile
 * 
 * @param int $user_id User ID
 * @param array $data Profile data to update
 * @return array Status and message
 */
function updateUserProfile($user_id, $data) {
    global $conn;
    
    $allowed_fields = ['fullname', 'phone', 'address', 'profile_image'];
    $updates = [];
    $types = '';
    $values = [];
    
    foreach ($data as $field => $value) {
        if (in_array($field, $allowed_fields)) {
            $updates[] = "$field = ?";
            $types .= 's';
            $values[] = $value;
        }
    }
    
    if (empty($updates)) {
        return ['status' => 'error', 'message' => 'No valid fields to update'];
    }
    
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $types .= 'i';
    $values[] = $user_id;
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        return ['status' => 'success', 'message' => 'Profile updated successfully'];
    } else {
        return ['status' => 'error', 'message' => 'Failed to update profile: ' . $stmt->error];
    }
}

/**
 * Change user password
 * 
 * @param int $user_id User ID
 * @param string $current_password Current password
 * @param string $new_password New password
 * @return array Status and message
 */
function changePassword($user_id, $current_password, $new_password) {
    global $conn;
    
    // Get current password from database
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['status' => 'error', 'message' => 'User not found'];
    }
    
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        return ['status' => 'error', 'message' => 'Current password is incorrect'];
    }
    
    // Password validation
    if (strlen($new_password) < 8 || !preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        return ['status' => 'error', 'message' => 'New password must be at least 8 characters and contain both letters and numbers'];
    }
    
    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($stmt->execute()) {
        return ['status' => 'success', 'message' => 'Password changed successfully'];
    } else {
        return ['status' => 'error', 'message' => 'Failed to change password: ' . $stmt->error];
    }
}

/**
 * Generate JWT token for API authentication
 * 
 * @param int $user_id User ID
 * @param string $role User role
 * @return string JWT token
 */
function generateJwtToken($user_id, $role) {
    // Load your JWT library or implement JWT generation
    // This is a simplified example without a proper JWT library
    
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $user_id,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + 3600 // 1 hour expiry
    ]);
    
    $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    // In a real application, use a secure secret key stored in environment variables
    $secret_key = getSecretKey(); 
    
    $signature = hash_hmac('sha256', $base64_header . "." . $base64_payload, $secret_key, true);
    $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    $jwt = $base64_header . "." . $base64_payload . "." . $base64_signature;
    
    return $jwt;
}

/**
 * Verify JWT token
 * 
 * @param string $token JWT token
 * @return array|false Payload data or false if invalid
 */
function verifyJwtToken($token) {
    // Implement proper JWT validation with a JWT library
    // This is a simplified example
    
    $token_parts = explode('.', $token);
    
    if (count($token_parts) !== 3) {
        return false;
    }
    
    list($base64_header, $base64_payload, $base64_signature) = $token_parts;
    
    // Get the secret key
    $secret_key = getSecretKey();
    
    // Verify signature
    $signature = hash_hmac('sha256', $base64_header . "." . $base64_payload, $secret_key, true);
    $expected_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    if (!hash_equals($expected_signature, $base64_signature)) {
        return false;
    }
    
    // Decode payload
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64_payload)), true);
    
    // Check if token has expired
    if (!isset($payload['exp']) || $payload['exp'] < time()) {
        return false;
    }
    
    return $payload;
}

/**
 * Get JWT token from Authorization header
 * 
 * @return string|false Token or false if not found
 */
function getAuthorizationToken() {
    $headers = apache_request_headers();
    
    if (isset($headers['Authorization'])) {
        $auth_header = $headers['Authorization'];
        
        if (strpos($auth_header, 'Bearer ') === 0) {
            return substr($auth_header, 7);
        }
    }
    
    return false;
}

/**
 * API authentication middleware
 * 
 * @param array|null $allowed_roles Roles allowed to access endpoint
 * @return array User data if authenticated
 */
function apiAuthMiddleware($allowed_roles = null) {
    $token = getAuthorizationToken();
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized - No token provided']);
        exit;
    }
    
    $payload = verifyJwtToken($token);
    
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized - Invalid token']);
        exit;
    }
    
    // Check role if specified
    if ($allowed_roles !== null) {
        $user_role = $payload['role'];
        $has_permission = false;
        
        if (is_array($allowed_roles)) {
            $has_permission = in_array($user_role, $allowed_roles);
        } else {
            $has_permission = ($user_role === $allowed_roles);
        }
        
        if (!$has_permission) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden - Insufficient permissions']);
            exit;
        }
    }
    
    return [
        'user_id' => $payload['user_id'],
        'role' => $payload['role']
    ];
}

/**
 * Get site's secret key from config
 * 
 * @return string Secret key
 */
function getSecretKey() {
    // In a real application, get this from environment variables or config file
    return defined('JWT_SECRET_KEY') ? JWT_SECRET_KEY : 'your-secret-key-should-be-long-and-secure';
}

/**
 * Get base URL of the application
 * 
 * @return string Base URL
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $path = $path === '\\' || $path === '/' ? '' : $path;
    
    return "$protocol://$host$path/";
}
?>