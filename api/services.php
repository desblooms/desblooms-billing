 
<?php
/**
 * Service API Endpoints
 * Handles all service-related API operations for the Digital Service Billing Mobile App
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Set response header to JSON
header('Content-Type: application/json');

// Validate JWT token and authenticate user
$auth = new Auth();
$user = $auth->validateToken();

if (!$user) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access. Please login to continue.'
    ]);
    exit;
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Handle based on HTTP method
switch ($method) {
    case 'GET':
        handleGetRequest();
        break;
    
    case 'POST':
        handlePostRequest();
        break;
    
    case 'PUT':
        handlePutRequest();
        break;
    
    case 'DELETE':
        handleDeleteRequest();
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
 * Handle GET requests for services
 * - Get all services
 * - Get service by ID
 * - Get services by category
 * - Search services
 */
function handleGetRequest() {
    global $conn, $user;
    
    // Get query parameters
    $serviceId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    // Calculate offset for pagination
    $offset = ($page - 1) * $limit;
    
    try {
        // Get service by ID
        if ($serviceId) {
            $stmt = $conn->prepare("
                SELECT s.*, c.name as category_name 
                FROM services s
                LEFT JOIN categories c ON s.category_id = c.id
                WHERE s.id = ? AND s.is_active = 1
            ");
            $stmt->bind_param("i", $serviceId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Service not found'
                ]);
                exit;
            }
            
            $service = $result->fetch_assoc();
            
            // Get service features
            $featStmt = $conn->prepare("
                SELECT * FROM service_features 
                WHERE service_id = ?
            ");
            $featStmt->bind_param("i", $serviceId);
            $featStmt->execute();
            $featResult = $featStmt->get_result();
            
            $features = [];
            while ($feature = $featResult->fetch_assoc()) {
                $features[] = $feature;
            }
            
            $service['features'] = $features;
            
            echo json_encode([
                'status' => 'success',
                'data' => $service
            ]);
        }
        // Get services by category
        else if ($categoryId) {
            $stmt = $conn->prepare("
                SELECT s.*, c.name as category_name 
                FROM services s
                LEFT JOIN categories c ON s.category_id = c.id
                WHERE s.category_id = ? AND s.is_active = 1
                ORDER BY s.name ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("iii", $categoryId, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $services = [];
            while ($service = $result->fetch_assoc()) {
                $services[] = $service;
            }
            
            // Get total count for pagination
            $countStmt = $conn->prepare("
                SELECT COUNT(*) as total 
                FROM services 
                WHERE category_id = ? AND is_active = 1
            ");
            $countStmt->bind_param("i", $categoryId);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $total = $countResult->fetch_assoc()['total'];
            
            echo json_encode([
                'status' => 'success',
                'data' => $services,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        }
        // Search services
        else if ($search) {
            $searchTerm = "%{$search}%";
            $stmt = $conn->prepare("
                SELECT s.*, c.name as category_name 
                FROM services s
                LEFT JOIN categories c ON s.category_id = c.id
                WHERE (s.name LIKE ? OR s.description LIKE ?) AND s.is_active = 1
                ORDER BY s.name ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("ssii", $searchTerm, $searchTerm, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $services = [];
            while ($service = $result->fetch_assoc()) {
                $services[] = $service;
            }
            
            // Get total count for pagination
            $countStmt = $conn->prepare("
                SELECT COUNT(*) as total 
                FROM services 
                WHERE (name LIKE ? OR description LIKE ?) AND is_active = 1
            ");
            $countStmt->bind_param("ss", $searchTerm, $searchTerm);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $total = $countResult->fetch_assoc()['total'];
            
            echo json_encode([
                'status' => 'success',
                'data' => $services,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        }
        // Get all services (paginated)
        else {
            $stmt = $conn->prepare("
                SELECT s.*, c.name as category_name 
                FROM services s
                LEFT JOIN categories c ON s.category_id = c.id
                WHERE s.is_active = 1
                ORDER BY s.name ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $services = [];
            while ($service = $result->fetch_assoc()) {
                $services[] = $service;
            }
            
            // Get total count for pagination
            $countStmt = $conn->query("SELECT COUNT(*) as total FROM services WHERE is_active = 1");
            $total = $countStmt->fetch_assoc()['total'];
            
            echo json_encode([
                'status' => 'success',
                'data' => $services,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to fetch services: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle POST requests for services
 * - Create a new service (admin only)
 * - Add service to cart
 */
function handlePostRequest() {
    global $conn, $user;
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if it's an add to cart request
    if (isset($data['action']) && $data['action'] === 'add_to_cart') {
        addServiceToCart($data);
        return;
    }
    
    // Only admin can create services
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Permission denied. Only admin can create services.'
        ]);
        return;
    }
    
    // Validate required fields
    $requiredFields = ['name', 'description', 'price', 'category_id'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => "Missing required field: {$field}"
            ]);
            return;
        }
    }
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Insert service
        $stmt = $conn->prepare("
            INSERT INTO services (
                name, description, price, category_id, billing_cycle, 
                is_recurring, is_active, created_at, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        // Set default values if not provided
        $billingCycle = isset($data['billing_cycle']) ? $data['billing_cycle'] : 'monthly';
        $isRecurring = isset($data['is_recurring']) ? (int)$data['is_recurring'] : 0;
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        
        $stmt->bind_param("ssdssiis", 
            $data['name'], 
            $data['description'], 
            $data['price'], 
            $data['category_id'],
            $billingCycle,
            $isRecurring,
            $isActive,
            $user['id']
        );
        
        $stmt->execute();
        $serviceId = $conn->insert_id;
        
        // Add features if provided
        if (isset($data['features']) && is_array($data['features'])) {
            $featStmt = $conn->prepare("
                INSERT INTO service_features (
                    service_id, name, description
                ) VALUES (?, ?, ?)
            ");
            
            foreach ($data['features'] as $feature) {
                $featStmt->bind_param("iss", 
                    $serviceId, 
                    $feature['name'], 
                    $feature['description'] ?? ''
                );
                $featStmt->execute();
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        echo json_encode([
            'status' => 'success',
            'message' => 'Service created successfully',
            'data' => [
                'id' => $serviceId,
                'name' => $data['name']
            ]
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to create service: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle PUT requests for services
 * - Update an existing service (admin only)
 */
function handlePutRequest() {
    global $conn, $user;
    
    // Only admin can update services
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Permission denied. Only admin can update services.'
        ]);
        return;
    }
    
    // Get request body and service ID
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || empty($data['id'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Service ID is required'
        ]);
        return;
    }
    
    $serviceId = (int)$data['id'];
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Check if service exists
        $checkStmt = $conn->prepare("SELECT id FROM services WHERE id = ?");
        $checkStmt->bind_param("i", $serviceId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Service not found'
            ]);
            $conn->rollback();
            return;
        }
        
        // Build update query dynamically based on provided fields
        $updateFields = [];
        $bindParams = [];
        $paramTypes = '';
        
        $fieldMapping = [
            'name' => ['type' => 's', 'field' => 'name'],
            'description' => ['type' => 's', 'field' => 'description'],
            'price' => ['type' => 'd', 'field' => 'price'],
            'category_id' => ['type' => 'i', 'field' => 'category_id'],
            'billing_cycle' => ['type' => 's', 'field' => 'billing_cycle'],
            'is_recurring' => ['type' => 'i', 'field' => 'is_recurring'],
            'is_active' => ['type' => 'i', 'field' => 'is_active']
        ];
        
        foreach ($fieldMapping as $key => $mapping) {
            if (isset($data[$key])) {
                $updateFields[] = "{$mapping['field']} = ?";
                $bindParams[] = $data[$key];
                $paramTypes .= $mapping['type'];
            }
        }
        
        // Add updated_at and updated_by
        $updateFields[] = "updated_at = NOW()";
        $updateFields[] = "updated_by = ?";
        $bindParams[] = $user['id'];
        $paramTypes .= 's';
        
        // Add service ID at the end of params
        $bindParams[] = $serviceId;
        $paramTypes .= 'i';
        
        if (empty($updateFields)) {
            echo json_encode([
                'status' => 'warning',
                'message' => 'No fields to update'
            ]);
            $conn->rollback();
            return;
        }
        
        // Update service
        $updateQuery = "UPDATE services SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        
        // Dynamically bind parameters
        $updateParams = array_merge([$paramTypes], $bindParams);
        call_user_func_array([$updateStmt, 'bind_param'], refValues($updateParams));
        
        $updateStmt->execute();
        
        // Update features if provided
        if (isset($data['features']) && is_array($data['features'])) {
            // Delete existing features
            $deleteStmt = $conn->prepare("DELETE FROM service_features WHERE service_id = ?");
            $deleteStmt->bind_param("i", $serviceId);
            $deleteStmt->execute();
            
            // Add new features
            $featStmt = $conn->prepare("
                INSERT INTO service_features (
                    service_id, name, description
                ) VALUES (?, ?, ?)
            ");
            
            foreach ($data['features'] as $feature) {
                $featStmt->bind_param("iss", 
                    $serviceId, 
                    $feature['name'], 
                    $feature['description'] ?? ''
                );
                $featStmt->execute();
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        echo json_encode([
            'status' => 'success',
            'message' => 'Service updated successfully',
            'data' => [
                'id' => $serviceId
            ]
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update service: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle DELETE requests for services
 * - Delete a service (admin only)
 */
function handleDeleteRequest() {
    global $conn, $user;
    
    // Only admin can delete services
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Permission denied. Only admin can delete services.'
        ]);
        return;
    }
    
    // Get service ID from query params
    $serviceId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$serviceId) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Service ID is required'
        ]);
        return;
    }
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Check if service exists
        $checkStmt = $conn->prepare("SELECT id FROM services WHERE id = ?");
        $checkStmt->bind_param("i", $serviceId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Service not found'
            ]);
            $conn->rollback();
            return;
        }
        
        // Check if service is in use (subscribed by any user)
        $inUseStmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM user_services 
            WHERE service_id = ? AND (status = 'active' OR status = 'pending')
        ");
        $inUseStmt->bind_param("i", $serviceId);
        $inUseStmt->execute();
        $inUseResult = $inUseStmt->get_result();
        $inUse = $inUseResult->fetch_assoc()['total'] > 0;
        
        if ($inUse) {
            // Soft delete if service is in use
            $stmt = $conn->prepare("
                UPDATE services 
                SET is_active = 0, updated_at = NOW(), updated_by = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("si", $user['id'], $serviceId);
            $stmt->execute();
            
            $message = 'Service marked as inactive because it is currently in use by customers';
        } else {
            // Hard delete features first (foreign key constraint)
            $featStmt = $conn->prepare("DELETE FROM service_features WHERE service_id = ?");
            $featStmt->bind_param("i", $serviceId);
            $featStmt->execute();
            
            // Then delete the service
            $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
            $stmt->bind_param("i", $serviceId);
            $stmt->execute();
            
            $message = 'Service deleted successfully';
        }
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        echo json_encode([
            'status' => 'success',
            'message' => $message
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to delete service: ' . $e->getMessage()
        ]);
    }
}

/**
 * Add a service to the user's cart
 */
function addServiceToCart($data) {
    global $conn, $user;
    
    // Validate required fields
    if (!isset($data['service_id']) || empty($data['service_id'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Service ID is required'
        ]);
        return;
    }
    
    $serviceId = (int)$data['service_id'];
    $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
    
    // Validate quantity
    if ($quantity < 1) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Quantity must be at least 1'
        ]);
        return;
    }
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Check if service exists and is active
        $checkStmt = $conn->prepare("
            SELECT id, name, price, is_recurring, billing_cycle 
            FROM services 
            WHERE id = ? AND is_active = 1
        ");
        $checkStmt->bind_param("i", $serviceId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Service not found or is inactive'
            ]);
            $conn->rollback();
            return;
        }
        
        $service = $checkResult->fetch_assoc();
        
        // Check if service is already in cart
        $cartStmt = $conn->prepare("
            SELECT id, quantity FROM cart 
            WHERE user_id = ? AND service_id = ? AND status = 'active'
        ");
        $cartStmt->bind_param("si", $user['id'], $serviceId);
        $cartStmt->execute();
        $cartResult = $cartStmt->get_result();
        
        if ($cartResult->num_rows > 0) {
            // Update existing cart item
            $cartItem = $cartResult->fetch_assoc();
            $newQuantity = $cartItem['quantity'] + $quantity;
            
            $updateStmt = $conn->prepare("
                UPDATE cart 
                SET quantity = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $updateStmt->bind_param("ii", $newQuantity, $cartItem['id']);
            $updateStmt->execute();
            
            $message = 'Service quantity updated in cart';
        } else {
            // Add new cart item
            $insertStmt = $conn->prepare("
                INSERT INTO cart (
                    user_id, service_id, quantity, unit_price, 
                    is_recurring, billing_cycle, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $insertStmt->bind_param("siidis", 
                $user['id'], 
                $serviceId, 
                $quantity, 
                $service['price'],
                $service['is_recurring'],
                $service['billing_cycle']
            );
            $insertStmt->execute();
            
            $message = 'Service added to cart';
        }
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'service_id' => $serviceId,
                'service_name' => $service['name'],
                'quantity' => $quantity
            ]
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to add service to cart: ' . $e->getMessage()
        ]);
    }
}

/**
 * Helper function for bind_param with references
 */
function refValues($arr) {
    if (strnatcmp(phpversion(), '5.3') >= 0) {
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }
    return $arr;
}