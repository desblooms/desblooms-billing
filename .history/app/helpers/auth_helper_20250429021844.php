<?php
/**
 * Authentication Helper
 * 
 * This file contains utility functions for authentication-related operations
 * such as password hashing, session management, and permission checking.
 */

/**
 * Hash a password using a secure algorithm
 * 
 * @param string $password The plain text password to hash
 * @return string The hashed password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a password against a hash
 * 
 * @param string $password The plain text password to verify
 * @param string $hash The hashed password to check against
 * @return bool True if the password matches the hash, false otherwise
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Create a secure session for a user
 * 
 * @param array $user The user data to store in the session
 * @return void
 */
function create_user_session($user) {
    // Remove sensitive data before storing in session
    unset($user['password']);
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);
    
    // Set last activity time for session timeout
    $_SESSION['last_activity'] = time();
}

/**
 * Destroy the current user session (logout)
 * 
 * @return void
 */
function destroy_user_session() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Unset all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Check if a user is logged in
 * 
 * @return bool True if the user is logged in, false otherwise
 */
function is_logged_in() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if logged_in session variable exists and is true
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if the current session has timed out
 * 
 * @param int $timeout The timeout period in seconds (default: 30 minutes)
 * @return bool True if the session has timed out, false otherwise
 */
function is_session_expired($timeout = 1800) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['last_activity'])) {
        return true;
    }
    
    // Check if the session has expired
    if (time() - $_SESSION['last_activity'] > $timeout) {
        destroy_user_session();
        return true;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return false;
}

/**
 * Check if the current user has a specific role
 * 
 * @param string|array $roles Role or array of roles to check
 * @return bool True if the user has at least one of the specified roles, false otherwise
 */
function has_role($roles) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // If not logged in, return false
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    // If a single role is passed, convert to array
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    // Check if the user has any of the specified roles
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Generate a secure CSRF token and store it in the session
 * 
 * @return string The CSRF token
 */
function generate_csrf_token() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Generate a random token if it doesn't exist
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token against the one stored in the session
 * 
 * @param string $token The token to verify
 * @return bool True if the token is valid, false otherwise
 */
function verify_csrf_token($token) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if the token exists and matches
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    
    return true;
}

/**
 * Get the ID of the currently logged-in user
 * 
 * @return int|null The user ID, or null if not logged in
 */
function get_current_user_id() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get the role of the currently logged-in user
 * 
 * @return string|null The user role, or null if not logged in
 */
function get_current_user_role() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
}

/**
 * Redirect to login page if the user is not logged in
 * 
 * @param string $redirect_url The URL to redirect to after login (default: current URL)
 * @return void
 */
function require_login($redirect_url = null) {
    if (!is_logged_in()) {
        // Get current URL if redirect_url is not provided
        if ($redirect_url === null) {
            $redirect_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        }
        
        // Store the redirect URL in the session
        $_SESSION['redirect_after_login'] = $redirect_url;
        
        // Redirect to login page
        header('Location: /auth/login');
        exit;
    }
    
    // Check if session has expired
    if (is_session_expired()) {
        header('Location: /auth/login?message=session_expired');
        exit;
    }
}

/**
 * Redirect to access denied page if the user doesn't have the required role
 * 
 * @param string|array $required_roles Role or array of roles required to access the page
 * @return void
 */
function require_role($required_roles) {
    require_login();
    
    if (!has_role($required_roles)) {
        header('Location: /access-denied');
        exit;
    }
}