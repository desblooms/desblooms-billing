 
<?php
/**
 * Invoice API Endpoints
 * 
 * Handles all invoice-related operations including:
 * - Listing invoices (with filtering options)
 * - Getting invoice details
 * - Creating new invoices
 * - Updating invoice status
 * - Generating invoice PDFs
 * - Managing pending and outstanding invoices
 */

// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/invoice-functions.php';

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Generate a unique invoice number
 * 
 * @return string
 */
function generateInvoiceNumber() {
    global $conn;
    
    // Format: INV-YYYYMMDD-XXXX
    $prefix = 'INV-' . date('Ymd') . '-';
    
    // Get last invoice number with same prefix
    $query = "SELECT invoice_number FROM invoices WHERE invoice_number LIKE '$prefix%' ORDER BY id DESC LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $last_number = $row['invoice_number'];
        $last_sequence = intval(substr($last_number, -4));
        $new_sequence = $last_sequence + 1;
    } else {
        $new_sequence = 1;
    }
    
    // Format sequence with leading zeros
    $sequence = str_pad($new_sequence, 4, '0', STR_PAD_LEFT);
    
    return $prefix . $sequence;
}

/**
 * Send invoice notification to user
 * 
 * @param int $invoice_id
 * @return bool
 */
function sendInvoiceNotification($invoice_id) {
    global $conn;
    
    // Get invoice details
    $query = "SELECT i.*, u.name, u.email 
              FROM invoices i 
              JOIN users u ON i.user_id = u.id 
              WHERE i.id = $invoice_id";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        return false;
    }
    
    $invoice = mysqli_fetch_assoc($result);
    
    // Get user's preferred notification method
    $notification_query = "SELECT notification_email, notification_push 
                          FROM user_settings 
                          WHERE user_id = {$invoice['user_id']}";
    
    $notification_result = mysqli_query($conn, $notification_query);
    $notification_settings = mysqli_fetch_assoc($notification_result);
    
    $success = true;
    
    // Send email notification if enabled
    if ($notification_settings['notification_email']) {
        // Load email template
        $email_template = file_get_contents('../includes/email-templates/invoice-created.html');
        
        // Replace placeholders
        $email_template = str_replace('{NAME}', $invoice['name'], $email_template);
        $email_template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $email_template);
        $email_template = str_replace('{INVOICE_DATE}', date('F j, Y', strtotime($invoice['invoice_date'])), $email_template);
        $email_template = str_replace('{DUE_DATE}', date('F j, Y', strtotime($invoice['due_date'])), $email_template);
        $email_template = str_replace('{AMOUNT}', number_format($invoice['total_amount'], 2), $email_template);
        
        // Add invoice URL
        $base_url = "https://" . $_SERVER['HTTP_HOST'];
        $invoice_url = "$base_url/invoices.php?id=$invoice_id";
        $email_template = str_replace('{INVOICE_URL}', $invoice_url, $email_template);
        
        // Set email headers
        $to = $invoice['email'];
        $subject = "New Invoice #{$invoice['invoice_number']} from " . COMPANY_NAME;
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: ' . COMPANY_EMAIL . "\r\n";
        
        // Send email
        if (!mail($to, $subject, $email_template, $headers)) {
            // Log email sending failure
            error_log("Failed to send invoice notification email to: $to");
            $success = false;
        }
    }
    
    // Send push notification if enabled
    if ($notification_settings['notification_push']) {
        // This would be implemented based on the push notification service you're using
        // Example: Firebase Cloud Messaging
        
        // Get user's device tokens
        $device_query = "SELECT device_token FROM user_devices WHERE user_id = {$invoice['user_id']}";
        $device_result = mysqli_query($conn, $device_query);
        
        while ($device = mysqli_fetch_assoc($device_result)) {
            $token = $device['device_token'];
            
            // Send push notification
            // This is just a placeholder - actual implementation would depend on your push service
            $push_result = sendPushNotification(
                $token,
                "New Invoice #{$invoice['invoice_number']}",
                "You have a new invoice for " . number_format($invoice['total_amount'], 2) . " due on " . date('M j, Y', strtotime($invoice['due_date']))
            );
            
            if (!$push_result) {
                // Log push notification failure
                error_log("Failed to send push notification to device: $token");
                $success = false;
            }
        }
    }
    
    // Log notification in database
    $log_query = "INSERT INTO notification_logs (
                 user_id, notification_type, related_id, status, created_at
              ) VALUES (
                 {$invoice['user_id']}, 'invoice_created', $invoice_id, " . ($success ? "'success'" : "'failed'") . ", NOW()
              )";
    
    mysqli_query($conn, $log_query);
    
    return $success;
}

/**
 * Send outstanding invoice notification to user
 * 
 * @param int $invoice_id
 * @return bool
 */
function sendOutstandingInvoiceNotification($invoice_id) {
    global $conn;
    
    // Get invoice details
    $query = "SELECT i.*, u.name, u.email 
              FROM invoices i 
              JOIN users u ON i.user_id = u.id 
              WHERE i.id = $invoice_id";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        return false;
    }
    
    $invoice = mysqli_fetch_assoc($result);
    
    // Calculate days overdue
    $current_date = date('Y-m-d');
    $due_date = new DateTime($invoice['due_date']);
    $today = new DateTime($current_date);
    $days_overdue = $today->diff($due_date)->days;
    
    // Calculate late fees if applicable
    $late_fee = 0;
    if ($days_overdue > 0) {
        $settings_query = "SELECT late_fee_percentage, late_fee_fixed FROM system_settings LIMIT 1";
        $settings_result = mysqli_query($conn, $settings_query);
        $settings = mysqli_fetch_assoc($settings_result);
        
        $late_fee_percentage = floatval($settings['late_fee_percentage']);
        $late_fee_fixed = floatval($settings['late_fee_fixed']);
        
        $late_fee = $late_fee_fixed;
        if ($late_fee_percentage > 0) {
            $late_fee += ($invoice['total_amount'] * $late_fee_percentage / 100);
        }
    }
    
    $total_with_late_fee = $invoice['total_amount'] + $late_fee;
    
    // Get user's preferred notification method
    $notification_query = "SELECT notification_email, notification_push, notification_sms 
                          FROM user_settings 
                          WHERE user_id = {$invoice['user_id']}";
    
    $notification_result = mysqli_query($conn, $notification_query);
    $notification_settings = mysqli_fetch_assoc($notification_result);
    
    $success = true;
    
    // Send email notification if enabled
    if ($notification_settings['notification_email']) {
        // Load email template
        $email_template = file_get_contents('../includes/email-templates/invoice-outstanding.html');
        
        // Replace placeholders
        $email_template = str_replace('{NAME}', $invoice['name'], $email_template);
        $email_template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $email_template);
        $email_template = str_replace('{DUE_DATE}', date('F j, Y', strtotime($invoice['due_date'])), $email_template);
        $email_template = str_replace('{DAYS_OVERDUE}', $days_overdue, $email_template);
        $email_template = str_replace('{AMOUNT}', number_format($invoice['total_amount'], 2), $email_template);
        $email_template = str_replace('{LATE_FEE}', number_format($late_fee, 2), $email_template);
        $email_template = str_replace('{TOTAL_WITH_LATE_FEE}', number_format($total_with_late_fee, 2), $email_template);
        
        // Add invoice URL
        $base_url = "https://" . $_SERVER['HTTP_HOST'];
        $invoice_url = "$base_url/invoices.php?id=$invoice_id";
        $email_template = str_replace('{INVOICE_URL}', $invoice_url, $email_template);
        
        // Set email headers
        $to = $invoice['email'];
        $subject = "REMINDER: Outstanding Invoice #{$invoice['invoice_number']} from " . COMPANY_NAME;
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: ' . COMPANY_EMAIL . "\r\n";
        
        // Send email
        if (!mail($to, $subject, $email_template, $headers)) {
            // Log email sending failure
            error_log("Failed to send outstanding invoice notification email to: $to");
            $success = false;
        }
    }
    
    // Send SMS notification if enabled and configured
    if (isset($notification_settings['notification_sms']) && $notification_settings['notification_sms']) {
        // Get user's phone number
        $phone_query = "SELECT phone FROM users WHERE id = {$invoice['user_id']}";
        $phone_result = mysqli_query($conn, $phone_query);
        $phone_data = mysqli_fetch_assoc($phone_result);
        
        if ($phone_data && !empty($phone_data['phone'])) {
            $phone = $phone_data['phone'];
            
            // SMS message
            $message = "REMINDER: Invoice #{$invoice['invoice_number']} is overdue by $days_overdue days. " .
                      "Amount due: " . number_format($total_with_late_fee, 2) . ". " .
                      "Please pay now: " . "$base_url/invoices.php?id=$invoice_id";
            
            // Send SMS
            // This is just a placeholder - actual implementation would depend on your SMS service
            $sms_result = sendSMS($phone, $message);
            
            if (!$sms_result) {
                // Log SMS sending failure
                error_log("Failed to send SMS notification to phone: $phone");
                $success = false;
            }
        }
    }
    
    // Send push notification if enabled
    if ($notification_settings['notification_push']) {
        // Get user's device tokens
        $device_query = "SELECT device_token FROM user_devices WHERE user_id = {$invoice['user_id']}";
        $device_result = mysqli_query($conn, $device_query);
        
        while ($device = mysqli_fetch_assoc($device_result)) {
            $token = $device['device_token'];
            
            // Send push notification
            $push_result = sendPushNotification(
                $token,
                "Invoice #{$invoice['invoice_number']} is Overdue",
                "Your invoice is overdue by $days_overdue days. Total due: " . number_format($total_with_late_fee, 2) . "."
            );
            
            if (!$push_result) {
                // Log push notification failure
                error_log("Failed to send push notification to device: $token");
                $success = false;
            }
        }
    }
    
    // Log notification in database
    $log_query = "INSERT INTO notification_logs (
                 user_id, notification_type, related_id, status, created_at
              ) VALUES (
                 {$invoice['user_id']}, 'invoice_outstanding', $invoice_id, " . ($success ? "'success'" : "'failed'") . ", NOW()
              )";
    
    mysqli_query($conn, $log_query);
    
    return $success;
}

/**
 * Send push notification
 * This is a placeholder function - actual implementation would depend on your push notification service
 * 
 * @param string $token
 * @param string $title
 * @param string $message
 * @return bool
 */
function sendPushNotification($token, $title, $message) {
    // This is a placeholder for push notification implementation
    // You would typically use Firebase Cloud Messaging, OneSignal, or another service
    
    // Example using Firebase Cloud Messaging (FCM)
    /*
    $url = 'https://fcm.googleapis.com/fcm/send';
    
    $fields = [
        'to' => $token,
        'notification' => [
            'title' => $title,
            'body' => $message,
            'sound' => 'default'
        ],
        'data' => [
            'type' => 'invoice',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        ]
    ];
    
    $headers = [
        'Authorization: key=' . FCM_SERVER_KEY,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result !== false;
    */
    
    // For now, just return true (simulate successful sending)
    return true;
}

/**
 * Send SMS notification
 * This is a placeholder function - actual implementation would depend on your SMS service
 * 
 * @param string $phone
 * @param string $message
 * @return bool
 */
function sendSMS($phone, $message) {
    // This is a placeholder for SMS implementation
    // You would typically use Twilio, Nexmo, or another SMS service
    
    // Example using Twilio
    /*
    $account_sid = TWILIO_ACCOUNT_SID;
    $auth_token = TWILIO_AUTH_TOKEN;
    $twilio_number = TWILIO_PHONE_NUMBER;
    
    $client = new Client($account_sid, $auth_token);
    
    try {
        $client->messages->create(
            $phone,
            [
                'from' => $twilio_number,
                'body' => $message
            ]
        );
        return true;
    } catch (Exception $e) {
        error_log('Twilio SMS Error: ' . $e->getMessage());
        return false;
    }
    */
    
    // For now, just return true (simulate successful sending)
    return true;
}

// Verify JWT token and get user info
$user = verifyToken();
if (!$user) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access. Please login.'
    ]);
    http_response_code(401);
    exit;
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get endpoint path
$path = isset($_GET['path']) ? $_GET['path'] : '';
$paths = explode('/', trim($path, '/'));
$resource = $paths[0] ?? '';
$id = $paths[1] ?? null;
$action = $paths[2] ?? null;

// Process based on request method
switch ($method) {
    case 'GET':
        handleGetRequest($resource, $id, $action, $user);
        break;
    case 'POST':
        handlePostRequest($resource, $id, $action, $user);
        break;
    case 'PUT':
        handlePutRequest($resource, $id, $action, $user);
        break;
    case 'DELETE':
        handleDeleteRequest($resource, $id, $action, $user);
        break;
    default:
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Method not allowed'
        ]);
        break;
}

/**
 * Handle GET requests
 */
function handleGetRequest($resource, $id, $action, $user) {
    global $conn;
    
    // List all invoices or filter by status, date range, etc.
    if ($resource == 'list') {
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        $offset = ($page - 1) * $limit;
        
        // Build query based on user role
        if ($user['role'] == 'admin' || $user['role'] == 'staff') {
            $query = "SELECT i.*, u.name as customer_name, u.email as customer_email 
                      FROM invoices i 
                      LEFT JOIN users u ON i.user_id = u.id 
                      WHERE 1=1";
            
            if ($status) {
                $query .= " AND i.status = '$status'";
            }
        } else {
            $user_id = $user['id'];
            $query = "SELECT i.* FROM invoices i WHERE i.user_id = $user_id";
            
            if ($status) {
                $query .= " AND i.status = '$status'";
            }
        }
        
        // Add date range filters if provided
        if ($start_date) {
            $query .= " AND i.invoice_date >= '$start_date'";
        }
        if ($end_date) {
            $query .= " AND i.invoice_date <= '$end_date'";
        }
        
        // Get total count for pagination
        $count_query = str_replace("SELECT i.*", "SELECT COUNT(*) as total", $query);
        $count_result = mysqli_query($conn, $count_query);
        $total_records = mysqli_fetch_assoc($count_result)['total'];
        $total_pages = ceil($total_records / $limit);
        
        // Add order and limit
        $query .= " ORDER BY i.created_at DESC LIMIT $offset, $limit";
        
        $result = mysqli_query($conn, $query);
        if (!$result) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . mysqli_error($conn)
            ]);
            exit;
        }
        
        $invoices = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Format dates
            $row['invoice_date'] = date('Y-m-d', strtotime($row['invoice_date']));
            $row['due_date'] = date('Y-m-d', strtotime($row['due_date']));
            
            // Add invoice items if needed
            if (isset($_GET['include_items']) && $_GET['include_items'] == 'true') {
                $invoice_id = $row['id'];
                $items_query = "SELECT * FROM invoice_items WHERE invoice_id = $invoice_id";
                $items_result = mysqli_query($conn, $items_query);
                $items = [];
                while ($item = mysqli_fetch_assoc($items_result)) {
                    $items[] = $item;
                }
                $row['items'] = $items;
            }
            
            // Add payment history if needed
            if (isset($_GET['include_payments']) && $_GET['include_payments'] == 'true') {
                $invoice_id = $row['id'];
                $payments_query = "SELECT * FROM payments WHERE invoice_id = $invoice_id ORDER BY payment_date DESC";
                $payments_result = mysqli_query($conn, $payments_query);
                $payments = [];
                while ($payment = mysqli_fetch_assoc($payments_result)) {
                    $payments[] = $payment;
                }
                $row['payments'] = $payments;
            }
            
            $invoices[] = $row;
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'invoices' => $invoices,
                'pagination' => [
                    'total_records' => $total_records,
                    'total_pages' => $total_pages,
                    'current_page' => $page,
                    'limit' => $limit
                ]
            ]
        ]);
        exit;
    }
    
    // Get invoice details
    elseif ($resource == 'details' && $id) {
        $invoice_id = intval($id);
        
        // Check if user has access to this invoice
        if ($user['role'] != 'admin' && $user['role'] != 'staff') {
            $access_check = "SELECT id FROM invoices WHERE id = $invoice_id AND user_id = {$user['id']}";
            $access_result = mysqli_query($conn, $access_check);
            if (mysqli_num_rows($access_result) == 0) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'You do not have permission to access this invoice'
                ]);
                exit;
            }
        }
        
        // Get invoice details
        $query = "SELECT i.*, u.name as customer_name, u.email as customer_email, 
                 u.address as customer_address, u.phone as customer_phone
                 FROM invoices i 
                 LEFT JOIN users u ON i.user_id = u.id 
                 WHERE i.id = $invoice_id";
        
        $result = mysqli_query($conn, $query);
        if (!$result || mysqli_num_rows($result) == 0) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invoice not found'
            ]);
            exit;
        }
        
        $invoice = mysqli_fetch_assoc($result);
        
        // Get invoice items
        $items_query = "SELECT * FROM invoice_items WHERE invoice_id = $invoice_id";
        $items_result = mysqli_query($conn, $items_query);
        $items = [];
        while ($item = mysqli_fetch_assoc($items_result)) {
            $items[] = $item;
        }
        $invoice['items'] = $items;
        
        // Get payment history
        $payments_query = "SELECT * FROM payments WHERE invoice_id = $invoice_id ORDER BY payment_date DESC";
        $payments_result = mysqli_query($conn, $payments_query);
        $payments = [];
        while ($payment = mysqli_fetch_assoc($payments_result)) {
            $payments[] = $payment;
        }
        $invoice['payments'] = $payments;
        
        echo json_encode([
            'status' => 'success',
            'data' => $invoice
        ]);
        exit;
    }
    
    // Get pending invoices for a user
    elseif ($resource == 'pending') {
        $user_id = ($user['role'] == 'admin' && isset($_GET['user_id'])) ? intval($_GET['user_id']) : $user['id'];
        $current_date = date('Y-m-d');
        
        $query = "SELECT * FROM invoices 
                  WHERE user_id = $user_id 
                  AND status = 'pending' 
                  AND due_date >= '$current_date' 
                  ORDER BY due_date ASC";
                  
        $result = mysqli_query($conn, $query);
        if (!$result) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . mysqli_error($conn)
            ]);
            exit;
        }
        
        $invoices = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $invoices[] = $row;
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $invoices
        ]);
        exit;
    }
    
    // Get outstanding invoices for a user
    elseif ($resource == 'outstanding') {
        $user_id = ($user['role'] == 'admin' && isset($_GET['user_id'])) ? intval($_GET['user_id']) : $user['id'];
        $current_date = date('Y-m-d');
        
        $query = "SELECT * FROM invoices 
                  WHERE user_id = $user_id 
                  AND status = 'outstanding' 
                  AND due_date < '$current_date' 
                  ORDER BY due_date ASC";
                  
        $result = mysqli_query($conn, $query);
        if (!$result) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . mysqli_error($conn)
            ]);
            exit;
        }
        
        $invoices = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Calculate days overdue
            $due_date = new DateTime($row['due_date']);
            $today = new DateTime($current_date);
            $days_overdue = $today->diff($due_date)->days;
            $row['days_overdue'] = $days_overdue;
            
            // Calculate late fees if applicable
            if ($days_overdue > 0) {
                $settings_query = "SELECT late_fee_percentage, late_fee_fixed FROM system_settings LIMIT 1";
                $settings_result = mysqli_query($conn, $settings_query);
                $settings = mysqli_fetch_assoc($settings_result);
                
                $late_fee_percentage = floatval($settings['late_fee_percentage']);
                $late_fee_fixed = floatval($settings['late_fee_fixed']);
                
                $late_fee = $late_fee_fixed;
                if ($late_fee_percentage > 0) {
                    $late_fee += ($row['total_amount'] * $late_fee_percentage / 100);
                }
                
                $row['late_fee'] = $late_fee;
                $row['total_with_late_fee'] = $row['total_amount'] + $late_fee;
            }
            
            $invoices[] = $row;
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $invoices
        ]);
        exit;
    }
    
    // Generate PDF invoice
    elseif ($resource == 'pdf' && $id) {
        $invoice_id = intval($id);
        
        // Check if user has access to this invoice
        if ($user['role'] != 'admin' && $user['role'] != 'staff') {
            $access_check = "SELECT id FROM invoices WHERE id = $invoice_id AND user_id = {$user['id']}";
            $access_result = mysqli_query($conn, $access_check);
            if (mysqli_num_rows($access_result) == 0) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'You do not have permission to access this invoice'
                ]);
                exit;
            }
        }
        
        // Generate PDF invoice
        require_once '../includes/invoice-functions.php';
        $pdf_path = generateInvoicePDF($invoice_id);
        
        if ($pdf_path) {
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'pdf_url' => $pdf_path
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to generate PDF invoice'
            ]);
        }
        exit;
    }
    
    // Invalid resource
    else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Resource not found'
        ]);
        exit;
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($resource, $id, $action, $user) {
    global $conn;
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Create new invoice
    if ($resource == 'create') {
        // Check if user has permission to create invoices
        if ($user['role'] != 'admin' && $user['role'] != 'staff') {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'You do not have permission to create invoices'
            ]);
            exit;
        }
        
        // Validate required fields
        $required_fields = ['user_id', 'invoice_date', 'due_date', 'items'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => "Missing required field: $field"
                ]);
                exit;
            }
        }
        
        // Validate items
        if (!is_array($data['items']) || count($data['items']) == 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invoice must have at least one item'
            ]);
            exit;
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Generate invoice number
            $invoice_number = generateInvoiceNumber();
            
            // Calculate totals
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            
            // Apply tax if applicable
            $tax_amount = 0;
            if (isset($data['tax_percentage']) && $data['tax_percentage'] > 0) {
                $tax_amount = $subtotal * ($data['tax_percentage'] / 100);
            }
            
            // Apply discount if applicable
            $discount_amount = 0;
            if (isset($data['discount_amount']) && $data['discount_amount'] > 0) {
                $discount_amount = $data['discount_amount'];
            } elseif (isset($data['discount_percentage']) && $data['discount_percentage'] > 0) {
                $discount_amount = $subtotal * ($data['discount_percentage'] / 100);
            }
            
            // Calculate total
            $total_amount = $subtotal + $tax_amount - $discount_amount;
            
            // Prepare invoice data
            $user_id = intval($data['user_id']);
            $invoice_date = mysqli_real_escape_string($conn, $data['invoice_date']);
            $due_date = mysqli_real_escape_string($conn, $data['due_date']);
            $notes = isset($data['notes']) ? mysqli_real_escape_string($conn, $data['notes']) : '';
            $status = 'pending'; // Default status for new invoices
            
            // Insert invoice
            $query = "INSERT INTO invoices (
                      invoice_number, user_id, invoice_date, due_date, 
                      subtotal, tax_amount, discount_amount, total_amount, 
                      status, notes, created_at, updated_at
                  ) VALUES (
                      '$invoice_number', $user_id, '$invoice_date', '$due_date', 
                      $subtotal, $tax_amount, $discount_amount, $total_amount, 
                      '$status', '$notes', NOW(), NOW()
                  )";
            
            $result = mysqli_query($conn, $query);
            if (!$result) {
                throw new Exception("Failed to create invoice: " . mysqli_error($conn));
            }
            
            $invoice_id = mysqli_insert_id($conn);
            
            // Insert invoice items
            foreach ($data['items'] as $item) {
                $service_id = intval($item['service_id']);
                $description = mysqli_real_escape_string($conn, $item['description']);
                $quantity = intval($item['quantity']);
                $price = floatval($item['price']);
                $amount = $quantity * $price;
                
                $item_query = "INSERT INTO invoice_items (
                              invoice_id, service_id, description, quantity, price, amount
                          ) VALUES (
                              $invoice_id, $service_id, '$description', $quantity, $price, $amount
                          )";
                
                $item_result = mysqli_query($conn, $item_query);
                if (!$item_result) {
                    throw new Exception("Failed to create invoice item: " . mysqli_error($conn));
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Send invoice notification
            // This would be implemented in a separate function
            sendInvoiceNotification($invoice_id);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Invoice created successfully',
                'data' => [
                    'invoice_id' => $invoice_id,
                    'invoice_number' => $invoice_number
                ]
            ]);
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }
    
    // Process payment for an invoice
    elseif ($resource == 'payment' && $id) {
        $invoice_id = intval($id);
        
        // Validate required fields
        $required_fields = ['amount', 'payment_method'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => "Missing required field: $field"
                ]);
                exit;
            }
        }
        
        // Get invoice details
        $query = "SELECT * FROM invoices WHERE id = $invoice_id";
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) == 0) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invoice not found'
            ]);
            exit;
        }
        
        $invoice = mysqli_fetch_assoc($result);
        
        // Check if user has access to this invoice
        if ($user['role'] != 'admin' && $user['role'] != 'staff' && $invoice['user_id'] != $user['id']) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'You do not have permission to process payment for this invoice'
            ]);
            exit;
        }
        
        // Process payment
        require_once '../includes/payment-functions.php';
        $payment_result = processPayment($invoice_id, $data);
        
        if ($payment_result['success']) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Payment processed successfully',
                'data' => $payment_result['data']
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $payment_result['message']
            ]);
        }
        exit;
    }
    
    // Invalid resource
    else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Resource not found'
        ]);
        exit;
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($resource, $id, $action, $user) {
    global $conn;
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Update invoice
    if ($resource == 'update' && $id) {
        $invoice_id = intval($id);
        
        // Check if user has permission to update invoices
        if ($user['role'] != 'admin' && $user['role'] != 'staff') {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'You do not have permission to update invoices'
            ]);
            exit;
        }
        
        // Get current invoice
        $query = "SELECT * FROM invoices WHERE id = $invoice_id";
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) == 0) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invoice not found'
            ]);
            exit;
        }
        
        $invoice = mysqli_fetch_assoc($result);
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            $update_fields = [];
            
            // Update invoice fields if provided
            if (isset($data['invoice_date'])) {
                $invoice_date = mysqli_real_escape_string($conn, $data['invoice_date']);
                $update_fields[] = "invoice_date = '$invoice_date'";
            }
            
            if (isset($data['due_date'])) {
                $due_date = mysqli_real_escape_string($conn, $data['due_date']);
                $update_fields[] = "due_date = '$due_date'";
            }
            
            if (isset($data['notes'])) {
                $notes = mysqli_real_escape_string($conn, $data['notes']);
                $update_fields[] = "notes = '$notes'";
            }
            
            if (isset($data['status'])) {
                $allowed_statuses = ['pending', 'outstanding', 'paid', 'cancelled'];
                $status = mysqli_real_escape_string($conn, $data['status']);
                
                if (!in_array($status, $allowed_statuses)) {
                    throw new Exception("Invalid status: $status");
                }
                
                $update_fields[] = "status = '$status'";
            }
            
            // Update items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                // Delete existing items
                $delete_query = "DELETE FROM invoice_items WHERE invoice_id = $invoice_id";
                $delete_result = mysqli_query($conn, $delete_query);
                
                if (!$delete_result) {
                    throw new Exception("Failed to update invoice items: " . mysqli_error($conn));
                }
                
                // Calculate new totals
                $subtotal = 0;
                foreach ($data['items'] as $item) {
                    $service_id = intval($item['service_id']);
                    $description = mysqli_real_escape_string($conn, $item['description']);
                    $quantity = intval($item['quantity']);
                    $price = floatval($item['price']);
                    $amount = $quantity * $price;
                    $subtotal += $amount;
                    
                    $item_query = "INSERT INTO invoice_items (
                                  invoice_id, service_id, description, quantity, price, amount
                              ) VALUES (
                                  $invoice_id, $service_id, '$description', $quantity, $price, $amount
                              )";
                    
                    $item_result = mysqli_query($conn, $item_query);
                    if (!$item_result) {
                        throw new Exception("Failed to update invoice item: " . mysqli_error($conn));
                    }
                }
                
                // Apply tax if applicable
                $tax_amount = 0;
                if (isset($data['tax_percentage']) && $data['tax_percentage'] > 0) {
                    $tax_percentage = floatval($data['tax_percentage']);
                    $tax_amount = $subtotal * ($tax_percentage / 100);
                }
                
                // Apply discount if applicable
                $discount_amount = 0;
                if (isset($data['discount_amount']) && $data['discount_amount'] > 0) {
                    $discount_amount = floatval($data['discount_amount']);
                } elseif (isset($data['discount_percentage']) && $data['discount_percentage'] > 0) {
                    $discount_percentage = floatval($data['discount_percentage']);
                    $discount_amount = $subtotal * ($discount_percentage / 100);
                }
                
                // Calculate total
                $total_amount = $subtotal + $tax_amount - $discount_amount;
                
                $update_fields[] = "subtotal = $subtotal";
                $update_fields[] = "tax_amount = $tax_amount";
                $update_fields[] = "discount_amount = $discount_amount";
                $update_fields[] = "total_amount = $total_amount";
            }
            
            // Update invoice record if there are any fields to update
            if (!empty($update_fields)) {
                $update_fields[] = "updated_at = NOW()";
                $update_query = "UPDATE invoices SET " . implode(", ", $update_fields) . " WHERE id = $invoice_id";
                $update_result = mysqli_query($conn, $update_query);
                
                if (!$update_result) {
                    throw new Exception("Failed to update invoice: " . mysqli_error($conn));
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Invoice updated successfully'
            ]);
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }
    
    // Update invoice status
    elseif ($resource == 'status' && $id) {
        $invoice_id = intval($id);
        
        // Check if user has permission to update invoice status
        if ($user['role'] != 'admin' && $user['role'] != 'staff') {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'You do not have permission to update invoice status'
            ]);
            exit;
        }
        
        // Validate required fields
        if (!isset($data['status'])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => "Missing required field: status"
            ]);
            exit;
        }
        
        $allowed_statuses = ['pending', 'outstanding', 'paid', 'cancelled'];
        $status = mysqli_real_escape_string($conn, $data['status']);
        
        if (!in_array($status, $allowed_statuses)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => "Invalid status: $status"
            ]);
            exit;
        }
        
        // Update status
        $query = "UPDATE invoices SET status = '$status', updated_at = NOW() WHERE id = $invoice_id";
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            // Send notification if status changed to outstanding
            if ($status == 'outstanding') {
                // This would be implemented in a separate function
                sendOutstandingInvoiceNotification($invoice_id);
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Invoice status updated successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to update invoice status: ' . mysqli_error($conn)
            ]);
        }
        exit;
    }
    
    // Invalid resource
    else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Resource not found'
        ]);
        exit;
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($resource, $id, $action, $user) {
    global $conn;
    
    // Delete invoice
    if ($resource == 'delete' && $id) {
        $invoice_id = intval($id);
        
        // Check if user has permission to delete invoices
        if ($user['role'] != 'admin') {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'You do not have permission to delete invoices'
            ]);
            exit;
        }
        
        // Check if invoice exists
        $query = "SELECT * FROM invoices WHERE id = $invoice_id";
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) == 0) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invoice not found'
            ]);
            exit;
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Delete invoice items
            $delete_items_query = "DELETE FROM invoice_items WHERE invoice_id = $invoice_id";
            $delete_items_result = mysqli_query($conn, $delete_items_query);
            
            if (!$delete_items_result) {
                throw new Exception("Failed to delete invoice items: " . mysqli_error($conn));
            }
            
            // Delete payments
            $delete_payments_query = "DELETE FROM payments WHERE invoice_id = $invoice_id";
            $delete_payments_result = mysqli_query($conn, $delete_payments_query);
            
            if (!$delete_payments_result) {
                throw new Exception("Failed to delete invoice payments: " . mysqli_error($conn));
            }
            
            // Delete invoice
            $delete_invoice_query = "DELETE FROM invoices WHERE id = $invoice_id";
            $delete_invoice_result = mysqli_query($conn, $delete_invoice_query);
            
            if (!$delete_invoice_result) {
                throw new Exception("Failed to delete invoice: " . mysqli_error($conn));
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Invoice deleted successfully'
            ]);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    // Invalid resource
    else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Resource not found'
        ]);
        exit;
    }
}