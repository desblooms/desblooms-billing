 
<?php
/**
 * Digital Service Billing - Payment API Endpoints
 * 
 * This file handles all payment-related API endpoints including:
 * - Processing payments
 * - Getting payment history
 * - Managing wallet/credits
 * - Handling payment status updates
 */

// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/payment-functions.php';

// Set headers for API response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Check if it's a preflight request and handle it
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verify JWT token for all requests
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$auth_header) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Authorization header is required'
    ]);
    http_response_code(401);
    exit;
}

// Extract token from Bearer
$token = str_replace('Bearer ', '', $auth_header);
$verified = verifyJWTToken($token);

if (!$verified) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or expired token'
    ]);
    http_response_code(401);
    exit;
}

// Get user data from token
$user_id = $verified['user_id'];
$user_role = $verified['role'];

// Get request method and endpoint
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));
$endpoint = end($uri_parts);

// Handle the different endpoints
switch ($endpoint) {
    case 'process':
        handleProcessPayment($request_method);
        break;
    case 'history':
        handlePaymentHistory($request_method);
        break;
    case 'wallet':
        handleWalletOperations($request_method);
        break;
    case 'verify':
        handlePaymentVerification($request_method);
        break;
    case 'methods':
        handlePaymentMethods($request_method);
        break;
    case 'status':
        handlePaymentStatus($request_method);
        break;
    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid endpoint'
        ]);
        http_response_code(404);
        break;
}

/**
 * Process a new payment
 */
function handleProcessPayment($method) {
    global $conn, $user_id;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        return;
    }
    
    // Get the request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['invoice_id']) || !isset($data['payment_method']) || !isset($data['amount'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        return;
    }
    
    // Validate the invoice belongs to the user
    $invoice_id = sanitizeInput($data['invoice_id']);
    $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $invoice_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invoice not found or not authorized']);
        return;
    }
    
    $invoice = $result->fetch_assoc();
    
    // Check if invoice is already paid
    if ($invoice['status'] === 'paid') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invoice is already paid']);
        return;
    }
    
    // Process payment based on payment method
    $payment_method = sanitizeInput($data['payment_method']);
    $amount = floatval($data['amount']);
    $payment_response = [];
    
    switch ($payment_method) {
        case 'stripe':
            $payment_response = processStripePayment($data);
            break;
        case 'paypal':
            $payment_response = processPayPalPayment($data);
            break;
        case 'razorpay':
            $payment_response = processRazorpayPayment($data);
            break;
        case 'wallet':
            $payment_response = processWalletPayment($user_id, $amount, $invoice_id);
            break;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid payment method']);
            return;
    }
    
    // If payment is successful, update invoice status
    if ($payment_response['status'] === 'success') {
        // Record the payment in database
        $payment_id = recordPayment($invoice_id, $user_id, $amount, $payment_method, $payment_response['transaction_id']);
        
        // Update invoice status
        updateInvoiceStatus($invoice_id, 'paid');
        
        // Send confirmation email
        sendPaymentConfirmationEmail($user_id, $invoice_id, $amount, $payment_method);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Payment processed successfully',
            'data' => [
                'payment_id' => $payment_id,
                'invoice_id' => $invoice_id,
                'amount' => $amount,
                'transaction_id' => $payment_response['transaction_id'],
                'payment_date' => date('Y-m-d H:i:s')
            ]
        ]);
        return;
    } else {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $payment_response['message'] ?? 'Payment processing failed'
        ]);
        return;
    }
}

/**
 * Get payment history
 */
function handlePaymentHistory($method) {
    global $conn, $user_id, $user_role;
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        return;
    }
    
    // Pagination parameters
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;
    
    // Additional filters
    $filters = [];
    $filter_sql = "";
    $params = [];
    $types = "";
    
    // Add user_id filter if not admin
    if ($user_role !== 'admin') {
        $filters[] = "user_id = ?";
        $params[] = $user_id;
        $types .= "i";
    } else if (isset($_GET['user_id'])) {
        // Admin can filter by specific user
        $filters[] = "user_id = ?";
        $params[] = intval($_GET['user_id']);
        $types .= "i";
    }
    
    // Date range filter
    if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $filters[] = "payment_date BETWEEN ? AND ?";
        $params[] = sanitizeInput($_GET['start_date']);
        $params[] = sanitizeInput($_GET['end_date']);
        $types .= "ss";
    }
    
    // Payment method filter
    if (isset($_GET['payment_method'])) {
        $filters[] = "payment_method = ?";
        $params[] = sanitizeInput($_GET['payment_method']);
        $types .= "s";
    }
    
    // Build the filter SQL
    if (!empty($filters)) {
        $filter_sql = "WHERE " . implode(" AND ", $filters);
    }
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM payments $filter_sql";
    $stmt = $conn->prepare($count_sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $total_result = $stmt->get_result()->fetch_assoc();
    $total = $total_result['total'];
    
    // Get the payments
    $sql = "SELECT p.*, i.invoice_number, i.total as invoice_total, 
            u.first_name, u.last_name, u.email  
            FROM payments p 
            JOIN invoices i ON p.invoice_id = i.id 
            JOIN users u ON p.user_id = u.id 
            $filter_sql 
            ORDER BY p.payment_date DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    
    // Add pagination parameters
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        // Mask sensitive data if not admin or not the owner
        if ($user_role !== 'admin' && $row['user_id'] !== $user_id) {
            $row['email'] = maskEmail($row['email']);
        }
        
        $payments[] = [
            'id' => $row['id'],
            'invoice_id' => $row['invoice_id'],
            'invoice_number' => $row['invoice_number'],
            'amount' => $row['amount'],
            'payment_method' => $row['payment_method'],
            'transaction_id' => $row['transaction_id'],
            'payment_date' => $row['payment_date'],
            'user' => [
                'id' => $row['user_id'],
                'name' => $row['first_name'] . ' ' . $row['last_name'],
                'email' => $row['email']
            ]
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $payments,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Handle wallet operations (add funds, check balance)
 */
function handleWalletOperations($method) {
    global $conn, $user_id;
    
    switch ($method) {
        case 'GET':
            // Get wallet balance
            $stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Create wallet if it doesn't exist
                $stmt = $conn->prepare("INSERT INTO wallet (user_id, balance) VALUES (?, 0)");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $balance = 0;
            } else {
                $wallet = $result->fetch_assoc();
                $balance = $wallet['balance'];
            }
            
            // Get wallet transaction history
            $stmt = $conn->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY transaction_date DESC LIMIT 10");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $transactions_result = $stmt->get_result();
            
            $transactions = [];
            while ($row = $transactions_result->fetch_assoc()) {
                $transactions[] = [
                    'id' => $row['id'],
                    'amount' => $row['amount'],
                    'type' => $row['type'],
                    'description' => $row['description'],
                    'transaction_date' => $row['transaction_date']
                ];
            }
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'balance' => $balance,
                    'transactions' => $transactions
                ]
            ]);
            break;
            
        case 'POST':
            // Add funds to wallet
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['amount']) || !isset($data['payment_method'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
                return;
            }
            
            $amount = floatval($data['amount']);
            $payment_method = sanitizeInput($data['payment_method']);
            
            // Process the payment
            $payment_response = [];
            
            switch ($payment_method) {
                case 'stripe':
                    $payment_response = processStripePayment($data);
                    break;
                case 'paypal':
                    $payment_response = processPayPalPayment($data);
                    break;
                case 'razorpay':
                    $payment_response = processRazorpayPayment($data);
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid payment method']);
                    return;
            }
            
            // If payment is successful, add funds to wallet
            if ($payment_response['status'] === 'success') {
                // Update wallet balance
                $stmt = $conn->prepare("INSERT INTO wallet (user_id, balance) VALUES (?, ?) 
                                       ON DUPLICATE KEY UPDATE balance = balance + ?");
                $stmt->bind_param("idd", $user_id, $amount, $amount);
                $stmt->execute();
                
                // Record transaction
                $transaction_type = 'deposit';
                $description = "Added funds via $payment_method";
                $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, external_id) 
                                        VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("idsss", $user_id, $amount, $transaction_type, $description, $payment_response['transaction_id']);
                $stmt->execute();
                
                // Get updated balance
                $stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $wallet = $result->fetch_assoc();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Funds added successfully',
                    'data' => [
                        'new_balance' => $wallet['balance'],
                        'amount_added' => $amount,
                        'transaction_id' => $payment_response['transaction_id']
                    ]
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => $payment_response['message'] ?? 'Payment processing failed'
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            break;
    }
}

/**
 * Handle payment verification callbacks from payment gateways
 */
function handlePaymentVerification($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        return;
    }
    
    // Get the payment gateway from the request
    $payment_gateway = isset($_GET['gateway']) ? sanitizeInput($_GET['gateway']) : '';
    
    if (empty($payment_gateway)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Payment gateway not specified']);
        return;
    }
    
    // Process the verification based on the payment gateway
    switch ($payment_gateway) {
        case 'stripe':
            verifyStripePayment();
            break;
        case 'paypal':
            verifyPayPalPayment();
            break;
        case 'razorpay':
            verifyRazorpayPayment();
            break;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid payment gateway']);
            break;
    }
}

/**
 * Handle saved payment methods
 */
function handlePaymentMethods($method) {
    global $conn, $user_id;
    
    switch ($method) {
        case 'GET':
            // Get saved payment methods
            $stmt = $conn->prepare("SELECT id, payment_type, card_last4, card_brand, is_default, created_at 
                                   FROM payment_methods WHERE user_id = ? AND status = 'active'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $payment_methods = [];
            while ($row = $result->fetch_assoc()) {
                $payment_methods[] = [
                    'id' => $row['id'],
                    'payment_type' => $row['payment_type'],
                    'card_last4' => $row['card_last4'],
                    'card_brand' => $row['card_brand'],
                    'is_default' => (bool)$row['is_default'],
                    'created_at' => $row['created_at']
                ];
            }
            
            echo json_encode([
                'status' => 'success',
                'data' => $payment_methods
            ]);
            break;
            
        case 'POST':
            // Add a new payment method
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['payment_type']) || !isset($data['token'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
                return;
            }
            
            $payment_type = sanitizeInput($data['payment_type']);
            $token = sanitizeInput($data['token']);
            $make_default = isset($data['make_default']) && $data['make_default'] ? 1 : 0;
            
            // Process the payment method based on type
            switch ($payment_type) {
                case 'card':
                    $response = saveCardPaymentMethod($user_id, $token, $make_default);
                    break;
                case 'bank_account':
                    $response = saveBankAccountPaymentMethod($user_id, $token, $make_default);
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid payment method type']);
                    return;
            }
            
            if ($response['status'] === 'success') {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Payment method added successfully',
                    'data' => $response['data']
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => $response['message']
                ]);
            }
            break;
            
        case 'PUT':
            // Update payment method (make default)
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['payment_method_id'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Missing payment method ID']);
                return;
            }
            
            $payment_method_id = intval($data['payment_method_id']);
            
            // Verify ownership
            $stmt = $conn->prepare("SELECT id FROM payment_methods WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $payment_method_id, $user_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 0) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Payment method not found or not authorized']);
                return;
            }
            
            // Update all payment methods to not default
            $stmt = $conn->prepare("UPDATE payment_methods SET is_default = 0 WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Set the selected payment method as default
            $stmt = $conn->prepare("UPDATE payment_methods SET is_default = 1 WHERE id = ?");
            $stmt->bind_param("i", $payment_method_id);
            $stmt->execute();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Payment method set as default'
            ]);
            break;
            
        case 'DELETE':
            // Delete a payment method
            $payment_method_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            
            if ($payment_method_id === 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Missing payment method ID']);
                return;
            }
            
            // Verify ownership
            $stmt = $conn->prepare("SELECT id, external_id, payment_type FROM payment_methods WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $payment_method_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Payment method not found or not authorized']);
                return;
            }
            
            $payment_method = $result->fetch_assoc();
            
            // If it's the default payment method, check if there are other methods
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM payment_methods WHERE user_id = ? AND id != ?");
            $stmt->bind_param("ii", $user_id, $payment_method_id);
            $stmt->execute();
            $count_result = $stmt->get_result()->fetch_assoc();
            
            // Delete the payment method from the payment processor
            switch ($payment_method['payment_type']) {
                case 'card':
                    $delete_result = deleteCardPaymentMethod($payment_method['external_id']);
                    break;
                case 'bank_account':
                    $delete_result = deleteBankAccountPaymentMethod($payment_method['external_id']);
                    break;
                default:
                    $delete_result = ['status' => 'success'];
                    break;
            }
            
            if ($delete_result['status'] === 'success') {
                // Delete from our database
                $stmt = $conn->prepare("UPDATE payment_methods SET status = 'deleted' WHERE id = ?");
                $stmt->bind_param("i", $payment_method_id);
                $stmt->execute();
                
                // If it was the default and there are other methods, set another as default
                if ($count_result['count'] > 0) {
                    $stmt = $conn->prepare("UPDATE payment_methods SET is_default = 1 
                                           WHERE user_id = ? AND status = 'active' 
                                           ORDER BY created_at DESC LIMIT 1");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                }
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Payment method deleted successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => $delete_result['message'] ?? 'Failed to delete payment method'
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            break;
    }
}

/**
 * Check or update the status of a payment
 */
function handlePaymentStatus($method) {
    global $conn, $user_id, $user_role;
    
    switch ($method) {
        case 'GET':
            // Check payment status
            if (!isset($_GET['payment_id'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Missing payment ID']);
                return;
            }
            
            $payment_id = intval($_GET['payment_id']);
            
            // Build query based on role
            if ($user_role === 'admin') {
                $stmt = $conn->prepare("SELECT p.*, i.invoice_number, i.status as invoice_status, 
                                       u.first_name, u.last_name, u.email 
                                       FROM payments p 
                                       JOIN invoices i ON p.invoice_id = i.id 
                                       JOIN users u ON p.user_id = u.id 
                                       WHERE p.id = ?");
                $stmt->bind_param("i", $payment_id);
            } else {
                $stmt = $conn->prepare("SELECT p.*, i.invoice_number, i.status as invoice_status, 
                                       u.first_name, u.last_name, u.email 
                                       FROM payments p 
                                       JOIN invoices i ON p.invoice_id = i.id 
                                       JOIN users u ON p.user_id = u.id 
                                       WHERE p.id = ? AND p.user_id = ?");
                $stmt->bind_param("ii", $payment_id, $user_id);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Payment not found or not authorized']);
                return;
            }
            
            $payment = $result->fetch_assoc();
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'id' => $payment['id'],
                    'invoice_id' => $payment['invoice_id'],
                    'invoice_number' => $payment['invoice_number'],
                    'invoice_status' => $payment['invoice_status'],
                    'amount' => $payment['amount'],
                    'payment_method' => $payment['payment_method'],
                    'transaction_id' => $payment['transaction_id'],
                    'payment_date' => $payment['payment_date'],
                    'user' => [
                        'id' => $payment['user_id'],
                        'name' => $payment['first_name'] . ' ' . $payment['last_name'],
                        'email' => $payment['email']
                    ]
                ]
            ]);
            break;
            
        case 'PUT':
            // Only admin can update payment status
            if ($user_role !== 'admin') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['payment_id']) || !isset($data['status'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
                return;
            }
            
            $payment_id = intval($data['payment_id']);
            $status = sanitizeInput($data['status']);
            
            // Validate status
            $valid_statuses = ['completed', 'pending', 'failed', 'refunded'];
            if (!in_array($status, $valid_statuses)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
                return;
            }
            
            // Get the payment and invoice
            $stmt = $conn->prepare("SELECT p.*, i.id as invoice_id, i.status as invoice_status 
                                   FROM payments p 
                                   JOIN invoices i ON p.invoice_id = i.id 
                                   WHERE p.id = ?");
            $stmt->bind_param("i", $payment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Payment not found']);
                return;
            }
            
            $payment = $result->fetch_assoc();
            
            // Update payment status
            $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $payment_id);
            $stmt->execute();
            
            // Update invoice status if needed
            $invoice_status = '';
            if ($status === 'completed') {
                $invoice_status = 'paid';
            } else if ($status === 'failed') {
                $invoice_status = 'outstanding'; // or 'pending' based on your business logic
            } else if ($status === 'refunded') {
                $invoice_status = 'refunded';
            }
            
            if (!empty($invoice_status)) {
                updateInvoiceStatus($payment['invoice_id'], $invoice_status);
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Payment status updated successfully',
                'data' => [
                    'payment_id' => $payment_id,
                    'new_status' => $status,
                    'invoice_id' => $payment['invoice_id'],
                    'invoice_status' => $invoice_status
                ]
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            break;
    }
}

/**
 * Helper function to record a payment in the database
 */
function recordPayment($invoice_id, $user_id, $amount, $payment_method, $transaction_id) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO payments (invoice_id, user_id, amount, payment_method, transaction_id, status) 
                           VALUES (?, ?, ?, ?, ?, 'completed')");
    $stmt->bind_param("iidss", $invoice_id, $user_id, $amount, $payment_method, $transaction_id);
    $stmt->execute();
    
    return $conn->insert_id;
}

/**
 * Helper function to update invoice status
 */
function updateInvoiceStatus($invoice_id, $status) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $invoice_id);
    $stmt->execute();
    
    return $stmt->affected_rows > 0;
}

/**
 * Process Stripe payment
 */
function processStripePayment($data) {
    // Include Stripe SDK or use direct API calls
    // This is a simplified example - in production, you would use the Stripe SDK
    
    // Example Stripe payment implementation
    try {
        // API key should be stored in config file
        // \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        
        // For demonstration purpose, simulate successful payment
        // In real implementation, use:
        // $charge = \Stripe\Charge::create([
        //     'amount' => $data['amount'] * 100, // Stripe expects amount in cents
        //     'currency' => 'usd',
        //     'source' => $data['token'],
        //     'description' => 'Payment for invoice ' . $data['invoice_id'],
        //     'metadata' => [
        //         'invoice_id' => $data['invoice_id'],
        //         'user_id' => $data['user_id']
        //     ]
        // ]);
        
        // Simulated success response
        return [
            'status' => 'success',
            'transaction_id' => 'stripe_' . time() . '_' . rand(1000, 9999),
            'message' => 'Payment processed successfully'
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Process PayPal payment
 */
function processPayPalPayment($data) {
    // Include PayPal SDK or use direct API calls
    // This is a simplified example - in production, you would use the PayPal SDK
    
    // Example PayPal payment implementation
    try {
        // For demonstration purpose, simulate successful payment
        return [
            'status' => 'success',
            'transaction_id' => 'paypal_' . time() . '_' . rand(1000, 9999),
            'message' => 'Payment processed successfully'
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Process Razorpay payment
 */
function processRazorpayPayment($data) {
    // Include Razorpay SDK or use direct API calls
    // This is a simplified example - in production, you would use the Razorpay SDK
    
    // Example Razorpay payment implementation
    try {
        // For demonstration purpose, simulate successful payment
        return [
            'status' => 'success',
            'transaction_id' => 'rzp_' . time() . '_' . rand(1000, 9999),
            'message' => 'Payment processed successfully'
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Process wallet payment
 */
function processWalletPayment($user_id, $amount, $invoice_id) {
    global $conn;
    
    // Check if user has enough balance
    $stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return [
            'status' => 'error',
            'message' => 'Wallet not found'
        ];
    }
    
    $wallet = $result->fetch_assoc();
    
    if ($wallet['balance'] < $amount) {
        return [
            'status' => 'error',
            'message' => 'Insufficient wallet balance'
        ];
    }
    
    // Deduct amount from wallet
    $stmt = $conn->prepare("UPDATE wallet SET balance = balance - ? WHERE user_id = ?");
    $stmt->bind_param("di", $amount, $user_id);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        return [
            'status' => 'error',
            'message' => 'Failed to process wallet payment'
        ];
    }
    
    // Record wallet transaction
    $transaction_type = 'payment';
    $description = "Payment for invoice #" . $invoice_id;
    $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, reference_id) 
                            VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idssi", $user_id, $amount, $transaction_type, $description, $invoice_id);
    $stmt->execute();
    
    $transaction_id = $conn->insert_id;
    
    return [
        'status' => 'success',
        'transaction_id' => 'wallet_' . $transaction_id,
        'message' => 'Payment processed successfully'
    ];
}

/**
 * Verify Stripe payment callback
 */
function verifyStripePayment() {
    global $conn;
    
    // Get the payload and signature
    $payload = file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    
    // This is a simplified example - in production, use proper verification
    try {
        // $event = \Stripe\Webhook::constructEvent(
        //     $payload, $sig_header, STRIPE_WEBHOOK_SECRET
        // );
        
        // Simulate verified event
        $event = json_decode($payload, true);
        
        // Handle the event based on its type
        switch ($event['type']) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event['data']['object'];
                $invoice_id = $paymentIntent['metadata']['invoice_id'] ?? null;
                
                if ($invoice_id) {
                    updateInvoiceStatus($invoice_id, 'paid');
                }
                break;
                
            case 'payment_intent.payment_failed':
                $paymentIntent = $event['data']['object'];
                $invoice_id = $paymentIntent['metadata']['invoice_id'] ?? null;
                
                if ($invoice_id) {
                    // You might want to update to a different status based on your business logic
                    updateInvoiceStatus($invoice_id, 'outstanding');
                }
                break;
                
            // Add more event types as needed
                
            default:
                // Unexpected event type
                break;
        }
        
        http_response_code(200);
        echo json_encode(['status' => 'success']);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

/**
 * Verify PayPal payment callback
 */
function verifyPayPalPayment() {
    // Implement PayPal IPN verification
    // This is a simplified example - in production, implement proper IPN handling
    
    $raw_post_data = file_get_contents('php://input');
    $raw_post_array = explode('&', $raw_post_data);
    $myPost = [];
    
    foreach ($raw_post_array as $keyval) {
        $keyval = explode('=', $keyval);
        if (count($keyval) == 2) {
            $myPost[$keyval[0]] = urldecode($keyval[1]);
        }
    }
    
    // Create validate message
    $req = 'cmd=_notify-validate';
    
    foreach ($myPost as $key => $value) {
        $value = urlencode($value);
        $req .= "&$key=$value";
    }
    
    // Send back to PayPal for validation
    // In production, use proper PayPal endpoints
    $ch = curl_init('https://ipnpb.paypal.com/cgi-bin/webscr');
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Connection: Close']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    // For demonstration purposes
    if ($response == "VERIFIED") {
        // Payment verified, update status
        $invoice_id = $myPost['custom'] ?? null;
        $payment_status = $myPost['payment_status'] ?? '';
        
        if ($invoice_id && $payment_status == 'Completed') {
            updateInvoiceStatus($invoice_id, 'paid');
        }
        
        http_response_code(200);
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'IPN validation failed']);
    }
}

/**
 * Verify Razorpay payment callback
 */
function verifyRazorpayPayment() {
    global $conn;
    
    // Get the payload
    $payload = json_decode(file_get_contents('php://input'), true);
    
    // Verify signature
    // This is a simplified example - in production, use proper signature verification
    try {
        // In real implementation, verify using Razorpay SDK:
        // $razorpay = new Razorpay\Api\Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
        // $razorpay->utility->verifyPaymentSignature($payload);
        
        // For demonstration purpose, assume verification passed
        $payment_id = $payload['payload']['payment']['entity']['id'] ?? '';
        $invoice_id = $payload['payload']['payment']['entity']['notes']['invoice_id'] ?? null;
        $status = $payload['payload']['payment']['entity']['status'] ?? '';
        
        if ($invoice_id && $status == 'captured') {
            updateInvoiceStatus($invoice_id, 'paid');
        }
        
        http_response_code(200);
        echo json_encode(['status' => 'success']);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

/**
 * Save card payment method
 */
function saveCardPaymentMethod($user_id, $token, $make_default) {
    global $conn;
    
    // In real implementation, use payment gateway SDK to save the card
    // Example with Stripe:
    // \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    // $customer_id = getStripeCustomerId($user_id);
    // $payment_method = \Stripe\PaymentMethod::retrieve($token);
    // $payment_method->attach(['customer' => $customer_id]);
    
    // For demonstration purpose, simulate successful saved card
    $card_last4 = substr(str_replace(' ', '', $token), -4); // This would come from the payment gateway
    $card_brand = 'Visa'; // This would come from the payment gateway
    $external_id = 'pm_' . time() . rand(1000, 9999); // This would come from the payment gateway
    
    // If make_default, set all other methods to not default
    if ($make_default) {
        $stmt = $conn->prepare("UPDATE payment_methods SET is_default = 0 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    } else {
        // Check if there are any payment methods, if not, make this default
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM payment_methods WHERE user_id = ? AND status = 'active'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] == 0) {
            $make_default = 1;
        }
    }
    
    // Save to database
    $stmt = $conn->prepare("INSERT INTO payment_methods (user_id, payment_type, card_last4, card_brand, external_id, is_default, status) 
                           VALUES (?, 'card', ?, ?, ?, ?, 'active')");
    $stmt->bind_param("isssi", $user_id, $card_last4, $card_brand, $external_id, $make_default);
    $stmt->execute();
    
    $payment_method_id = $conn->insert_id;
    
    return [
        'status' => 'success',
        'data' => [
            'id' => $payment_method_id,
            'payment_type' => 'card',
            'card_last4' => $card_last4,
            'card_brand' => $card_brand,
            'is_default' => (bool)$make_default
        ]
    ];
}

/**
 * Save bank account payment method
 */
function saveBankAccountPaymentMethod($user_id, $token, $make_default) {
    global $conn;
    
    // Similar implementation as saveCardPaymentMethod
    // For demonstration purpose, simulate successful saved bank account
    $account_last4 = substr(str_replace(' ', '', $token), -4);
    $bank_name = 'Example Bank';
    $external_id = 'ba_' . time() . rand(1000, 9999);
    
    // If make_default, set all other methods to not default
    if ($make_default) {
        $stmt = $conn->prepare("UPDATE payment_methods SET is_default = 0 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    } else {
        // Check if there are any payment methods, if not, make this default
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM payment_methods WHERE user_id = ? AND status = 'active'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] == 0) {
            $make_default = 1;
        }
    }
    
    // Save to database
    $stmt = $conn->prepare("INSERT INTO payment_methods (user_id, payment_type, bank_last4, bank_name, external_id, is_default, status) 
                           VALUES (?, 'bank_account', ?, ?, ?, ?, 'active')");
    $stmt->bind_param("isssi", $user_id, $account_last4, $bank_name, $external_id, $make_default);
    $stmt->execute();
    
    $payment_method_id = $conn->insert_id;
    
    return [
        'status' => 'success',
        'data' => [
            'id' => $payment_method_id,
            'payment_type' => 'bank_account',
            'bank_last4' => $account_last4,
            'bank_name' => $bank_name,
            'is_default' => (bool)$make_default
        ]
    ];
}

/**
 * Delete card payment method
 */
function deleteCardPaymentMethod($external_id) {
    // In real implementation, use payment gateway SDK to delete the card
    // Example with Stripe:
    // \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    // $payment_method = \Stripe\PaymentMethod::retrieve($external_id);
    // $payment_method->detach();
    
    // For demonstration purpose, simulate successful deletion
    return [
        'status' => 'success'
    ];
}

/**
 * Delete bank account payment method
 */
function deleteBankAccountPaymentMethod($external_id) {
    // Similar implementation as deleteCardPaymentMethod
    // For demonstration purpose, simulate successful deletion
    return [
        'status' => 'success'
    ];
}

/**
 * Send payment confirmation email
 */
function sendPaymentConfirmationEmail($user_id, $invoice_id, $amount, $payment_method) {
    global $conn;
    
    // Get user email
    $stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // Get invoice details
    $stmt = $conn->prepare("SELECT invoice_number, due_date FROM invoices WHERE id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    
    // In a real implementation, you would use a proper email library
    // This is just a placeholder
    $to = $user['email'];
    $subject = 'Payment Confirmation - Invoice #' . $invoice['invoice_number'];
    
    $message = "Dear " . $user['first_name'] . " " . $user['last_name'] . ",\n\n";
    $message .= "Thank you for your payment of $" . number_format($amount, 2) . " for Invoice #" . $invoice['invoice_number'] . ".\n\n";
    $message .= "Payment Method: " . ucfirst($payment_method) . "\n";
    $message .= "Date: " . date('Y-m-d H:i:s') . "\n\n";
    $message .= "You can view and download your invoice from your account dashboard.\n\n";
    $message .= "Thank you for your business!\n\n";
    $message .= "Regards,\nDigital Service Billing Team";
    
    $headers = "From: noreply@yourcompany.com\r\n";
    
    // Don't actually send emails in this demo code
    // mail($to, $subject, $message, $headers);
    
    // Log the email instead
    error_log("Payment confirmation email would be sent to: $to");
}

/**
 * Helper function to mask email for privacy
 */
function maskEmail($email) {
    if (empty($email)) return '';
    
    $em   = explode("@", $email);
    $name = implode('@', array_slice($em, 0, count($em)-1));
    $len  = floor(strlen($name)/2);
    
    return substr($name,0, $len) . str_repeat('*', $len) . "@" . end($em);
}

/**
 * Helper function to sanitize input
 */
function sanitizeInput($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}