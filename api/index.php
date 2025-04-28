 
<?php
/**
 * API Router for Digital Service Billing App
 * 
 * This file routes API requests to the appropriate endpoint handlers
 */

// Set headers for API responses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Get the request path
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/api/'; // Adjust based on your server configuration
$path = substr($request_uri, strpos($request_uri, $base_path) + strlen($base_path));
$path = explode('?', $path)[0]; // Remove query string if present
$path_parts = explode('/', trim($path, '/'));

// Extract the endpoint and any identifier
$endpoint = $path_parts[0] ?? '';
$id = $path_parts[1] ?? null;
$action = $path_parts[2] ?? null;

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request body for POST, PUT requests
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = [];
}

// Merge with $_POST for form submissions
$input = array_merge($_POST, $input);

// JWT Authentication verification - exclude auth endpoints
if ($endpoint !== 'auth' || ($endpoint === 'auth' && $action !== 'login' && $action !== 'register')) {
    // Get JWT token from Authorization header
    $headers = getallheaders();
    $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($auth_header) || strpos($auth_header, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: No token provided']);
        exit;
    }
    
    $token = substr($auth_header, 7); // Remove "Bearer " prefix
    
    // Verify JWT token
    try {
        $user = verifyJWTToken($token);
        if (!$user) {
            throw new Exception('Invalid token');
        }
        // Add user info to the input for route handlers
        $input['user_id'] = $user['id'];
        $input['user_role'] = $user['role'];
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: ' . $e->getMessage()]);
        exit;
    }
    
    // Role-based access control
    if (!checkAccess($endpoint, $action, $input['user_role'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: Insufficient permissions']);
        exit;
    }
}

// Route the request to the appropriate endpoint file
switch ($endpoint) {
    case 'auth':
        require_once 'auth.php';
        break;
    case 'services':
        require_once 'services.php';
        break;
    case 'invoices':
        require_once 'invoices.php';
        break;
    case 'payments':
        require_once 'payments.php';
        break;
    case 'users':
        require_once 'users.php';
        break;
    case 'categories':
        require_once 'categories.php';
        break;
    case 'dashboard':
        require_once 'dashboard.php';
        break;
    case 'support':
        require_once 'support.php';
        break;
    case 'settings':
        require_once 'settings.php';
        break;
    default:
        // Handle invalid endpoint
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        exit;
}

/**
 * Check if user has permission to access a resource
 * 
 * @param string $endpoint The API endpoint
 * @param string $action The requested action
 * @param string $role The user's role
 * @return bool Whether the user has access
 */
function checkAccess($endpoint, $action, $role) {
    // Define role-based access permissions
    $permissions = [
        'admin' => [
            // Admin can access everything
            '*' => true,
        ],
        'staff' => [
            'services' => ['get', 'list'],
            'invoices' => ['get', 'list', 'create', 'update'],
            'payments' => ['get', 'list', 'process'],
            'users' => ['get', 'list'],
            'categories' => ['get', 'list'],
            'dashboard' => ['stats'],
            'support' => ['get', 'list', 'reply'],
        ],
        'customer' => [
            'services' => ['get', 'list'],
            'invoices' => ['get', 'list', 'pay'],
            'payments' => ['get', 'list', 'make'],
            'users' => ['get' => ['self']], // Customer can only access their own user data
            'support' => ['get', 'list', 'create'],
        ],
    ];
    
    // Admin has access to everything
    if ($role === 'admin') {
        return true;
    }
    
    // Check if role exists
    if (!isset($permissions[$role])) {
        return false;
    }
    
    // Check if endpoint is allowed for this role
    if (!isset($permissions[$role][$endpoint]) && !isset($permissions[$role]['*'])) {
        return false;
    }
    
    // If action is not specified, return true if endpoint is allowed
    if (!$action) {
        return true;
    }
    
    // Check if action is allowed for this endpoint and role
    if (isset($permissions[$role][$endpoint])) {
        // Check if all actions are allowed
        if ($permissions[$role][$endpoint] === true || in_array('*', $permissions[$role][$endpoint])) {
            return true;
        }
        
        // Check if specific action is allowed
        return in_array($action, $permissions[$role][$endpoint]);
    }
    
    return false;
}

/**
 * Send a JSON response
 * 
 * @param mixed $data The data to send
 * @param int $status_code HTTP status code
 */
function sendResponse($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}