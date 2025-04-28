 
 
<?php
/**
 * Authentication API Endpoints
 * 
 * Handles user authentication, registration, password recovery, and token validation
 * for the Digital Service Billing Mobile App
 */

// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Set response header to JSON
header('Content-Type: application/json');

// Get request method
$request_method = $_SERVER['REQUEST_METHOD'];

// Get endpoint from URL parameter
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

// Initialize response array
$response = [
    'status' => 'error',
    'message' => 'Invalid request',
    'data' => null
];

// Process request based on endpoint and method
switch ($endpoint) {
    case 'login':
        if ($request_method === 'POST') {
            // Handle login request
            handleLogin();
        } else {
            $response['message'] = 'Method not allowed';
        }
        break;
        
    case 'register':
        if ($request_method === 'POST') {
            // Handle registration request
            handleRegistration();
        } else {
            $response['message'] = 'Method not allowed';
        }
        break;
        
    case 'forgot-password':
        if ($request_method === 'POST') {
            // Handle forgot password request
            handleForgotPassword();
        } else {
            $response['message'] = 'Method not allowed';
        }
        break;
        
    case 'reset-password':
        if ($request_method === 'POST') {
            // Handle reset password request
            handleResetPassword();
        } else {
            $response['message'] = 'Method not allowed';
        }
        break;
        
    case 'verify-token':
        if ($request_method === 'POST') {
            // Handle token verification
            handleVerifyToken();
        } else {
            $response['message'] = 'Method not allowed';
        }
        break;
        
    case 'logout':
        if ($request_method === 'POST') {
            // Handle logout request
            handleLogout();
        } else {
            $response['message'] = 'Method not allowed';
        }
        break;
        
    case 'update-profile':
        if ($request_method === 'POST') {
            // Handle profile update
            handleUpdateProfile();
        } else {
            $response['message'] = 'Method not allowed';
        }
        break;
        
    case 'change-password':
        if ($request_method === 'POST') {
            // Handle password change
            handleChangePassword();
        } else {
            $response['message'] = 'Method not allowed';
        }
        break;
        
    default:
        $response['message'] = 'Endpoint not found';
        break;
}

// Return JSON response
echo json_encode($response);
exit;

/**
 * Handle user login
 */
function handleLogin() {
    global $conn, $response;
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if required fields are provided
    if (!isset($data['email']) || !isset($data['password'])) {
        $response['message'] = 'Email and password are required';
        return;
    }
    
    $email = sanitizeInput($data['email']);
    $password = $data['password'];
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format';
        return;
    }
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, password, role, first_name, last_name, status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $response['message'] = 'Invalid email or password';
        return;
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        $response['message'] = 'Invalid email or password';
        return;
    }
    
    // Check if user account is active
    if ($user['status'] !== 'active') {
        $response['message'] = 'Account is not active. Please contact support.';
        return;
    }
    
    // Generate JWT token
    $token_data = [
        'user_id' => $user['id'],
        'email' => $email,
        'role' => $user['role'],
        'created' => time()
    ];
    
    $token = generateJWTToken($token_data);
    
    // Update last login time
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    
    // Prepare response data
    $response['status'] = 'success';
    $response['message'] = 'Login successful';
    $response['data'] = [
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'email' => $email,
            'role' => $user['role'],
            'name' => $user['first_name'] . ' ' . $user['last_name']
        ]
    ];
}

/**
 * Handle user registration
 */
function handleRegistration() {
    global $conn, $response;
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if required fields are provided
    if (!isset($data['email']) || !isset($data['password']) || !isset($data['first_name']) || !isset($data['last_name'])) {
        $response['message'] = 'All fields are required';
        return;
    }
    
    $email = sanitizeInput($data['email']);
    $password = $data['password'];
    $first_name = sanitizeInput($data['first_name']);
    $last_name = sanitizeInput($data['last_name']);
    $phone = isset($data['phone']) ? sanitizeInput($data['phone']) : '';
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format';
        return;
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        $response['message'] = 'Password must be at least 8 characters long';
        return;
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $response['message'] = 'Email already exists';
        return;
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    // Set default role to 'customer'
    $role = 'customer';
    $status = 'active';
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, phone, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssss", $email, $hashed_password, $first_name, $last_name, $phone, $role, $status);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        
        // Generate JWT token
        $token_data = [
            'user_id' => $user_id,
            'email' => $email,
            'role' => $role,
            'created' => time()
        ];
        
        $token = generateJWTToken($token_data);
        
        // Prepare response data
        $response['status'] = 'success';
        $response['message'] = 'Registration successful';
        $response['data'] = [
            'token' => $token,
            'user' => [
                'id' => $user_id,
                'email' => $email,
                'role' => $role,
                'name' => $first_name . ' ' . $last_name
            ]
        ];
        
        // Send welcome email (optional)
        // sendWelcomeEmail($email, $first_name);
    } else {
        $response['message'] = 'Registration failed. Please try again.';
    }
}

/**
 * Handle forgot password request
 */
function handleForgotPassword() {
    global $conn, $response;
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if email is provided
    if (!isset($data['email'])) {
        $response['message'] = 'Email is required';
        return;
    }
    
    $email = sanitizeInput($data['email']);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format';
        return;
    }
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Don't reveal that the email doesn't exist for security reasons
        $response['status'] = 'success';
        $response['message'] = 'If your email is registered, you will receive password reset instructions';
        return;
    }
    
    $user = $result->fetch_assoc();
    
    // Generate reset token
    $reset_token = bin2hex(random_bytes(32));
    $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Store reset token in database
    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
    $stmt->bind_param("ssi", $reset_token, $token_expiry, $user['id']);
    
    if ($stmt->execute()) {
        // Send reset email
        $reset_link = SITE_URL . '/reset-password.php?token=' . $reset_token;
        
        // Implement the email sending functionality
        // sendPasswordResetEmail($email, $user['first_name'], $reset_link);
        
        // For testing purposes, include the reset link in the response
        $response['status'] = 'success';
        $response['message'] = 'Password reset instructions have been sent to your email';
        $response['data'] = [
            'reset_link' => $reset_link // Remove this in production
        ];
    } else {
        $response['message'] = 'Failed to process password reset request. Please try again.';
    }
}

/**
 * Handle reset password
 */
function handleResetPassword() {
    global $conn, $response;
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if required fields are provided
    if (!isset($data['token']) || !isset($data['password']) || !isset($data['confirm_password'])) {
        $response['message'] = 'All fields are required';
        return;
    }
    
    $token = sanitizeInput($data['token']);
    $password = $data['password'];
    $confirm_password = $data['confirm_password'];
    
    // Validate password match
    if ($password !== $confirm_password) {
        $response['message'] = 'Passwords do not match';
        return;
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        $response['message'] = 'Password must be at least 8 characters long';
        return;
    }
    
    // Check if token is valid and not expired
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $response['message'] = 'Invalid or expired reset token';
        return;
    }
    
    $user = $result->fetch_assoc();
    
    // Hash new password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    // Update password and clear reset token
    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user['id']);
    
    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Password has been reset successfully. You can now login with your new password.';
    } else {
        $response['message'] = 'Failed to reset password. Please try again.';
    }
}

/**
 * Handle JWT token verification
 */
function handleVerifyToken() {
    global $response;
    
    // Get token from Authorization header or POST data
    $token = getAuthToken();
    
    if (!$token) {
        $response['message'] = 'Token is required';
        return;
    }
    
    // Verify token
    $is_valid = verifyJWTToken($token);
    
    if ($is_valid) {
        $payload = getJWTPayload($token);
        
        $response['status'] = 'success';
        $response['message'] = 'Token is valid';
        $response['data'] = [
            'user_id' => $payload['user_id'],
            'email' => $payload['email'],
            'role' => $payload['role']
        ];
    } else {
        $response['message'] = 'Invalid or expired token';
    }
}

/**
 * Handle user logout
 */
function handleLogout() {
    global $response;
    
    // Note: Since we're using JWT, the actual logout happens on the client side
    // by removing the token from storage. This endpoint can be used for logging
    // or future token blacklisting.
    
    $response['status'] = 'success';
    $response['message'] = 'Logout successful';
}

/**
 * Handle profile update
 */
function handleUpdateProfile() {
    global $conn, $response;
    
    // Get token and validate user is authenticated
    $token = getAuthToken();
    
    if (!$token || !verifyJWTToken($token)) {
        $response['message'] = 'Unauthorized';
        http_response_code(401);
        return;
    }
    
    // Get user ID from token
    $payload = getJWTPayload($token);
    $user_id = $payload['user_id'];
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if required fields are provided
    if (!isset($data['first_name']) || !isset($data['last_name'])) {
        $response['message'] = 'First name and last name are required';
        return;
    }
    
    $first_name = sanitizeInput($data['first_name']);
    $last_name = sanitizeInput($data['last_name']);
    $phone = isset($data['phone']) ? sanitizeInput($data['phone']) : '';
    
    // Update user profile
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("sssi", $first_name, $last_name, $phone, $user_id);
    
    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Profile updated successfully';
        $response['data'] = [
            'user' => [
                'id' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone
            ]
        ];
    } else {
        $response['message'] = 'Failed to update profile. Please try again.';
    }
}

/**
 * Handle password change
 */
function handleChangePassword() {
    global $conn, $response;
    
    // Get token and validate user is authenticated
    $token = getAuthToken();
    
    if (!$token || !verifyJWTToken($token)) {
        $response['message'] = 'Unauthorized';
        http_response_code(401);
        return;
    }
    
    // Get user ID from token
    $payload = getJWTPayload($token);
    $user_id = $payload['user_id'];
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if required fields are provided
    if (!isset($data['current_password']) || !isset($data['new_password']) || !isset($data['confirm_password'])) {
        $response['message'] = 'All fields are required';
        return;
    }
    
    $current_password = $data['current_password'];
    $new_password = $data['new_password'];
    $confirm_password = $data['confirm_password'];
    
    // Validate new password match
    if ($new_password !== $confirm_password) {
        $response['message'] = 'New passwords do not match';
        return;
    }
    
    // Validate new password strength
    if (strlen($new_password) < 8) {
        $response['message'] = 'New password must be at least 8 characters long';
        return;
    }
    
    // Get current password from database
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $response['message'] = 'User not found';
        return;
    }
    
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $response['message'] = 'Current password is incorrect';
        return;
    }
    
    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    
    // Update password
    $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Password changed successfully';
    } else {
        $response['message'] = 'Failed to change password. Please try again.';
    }
}

/**
 * Get authentication token from header or POST data
 */
function getAuthToken() {
    // Check Authorization header
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        // Bearer token format: "Bearer [token]"
        $auth_header = $headers['Authorization'];
        if (strpos($auth_header, 'Bearer ') === 0) {
            return substr($auth_header, 7);
        }
    }
    
    // Check POST data
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['token'])) {
        return $data['token'];
    }
    
    // Check GET parameter (not recommended for production)
    if (isset($_GET['token'])) {
        return $_GET['token'];
    }
    
    return null;
}

/**
 * Sanitize user input
 */
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}