 
<?php
/**
 * Admin Users Management
 * 
 * This file handles all user management functionality for administrators
 * including viewing, editing, blocking, and deleting users.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !isAdmin()) {
    // Redirect to login page with error message
    $_SESSION['error'] = "You must be logged in as an administrator to access this page.";
    header("Location: ../login.php");
    exit();
}

// Initialize variables
$users = [];
$error = '';
$success = '';
$currentPage = 1;
$totalPages = 1;
$searchTerm = '';
$filterRole = '';
$itemsPerPage = 10;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        // Handle different action types
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'block':
                    if (isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
                        $userId = (int)$_POST['user_id'];
                        // Don't allow blocking self
                        if ($userId === (int)$_SESSION['user_id']) {
                            $error = "You cannot block yourself.";
                        } else {
                            $status = blockUser($userId);
                            if ($status) {
                                $success = "User has been blocked successfully.";
                            } else {
                                $error = "Failed to block user. Please try again.";
                            }
                        }
                    }
                    break;
                    
                case 'unblock':
                    if (isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
                        $userId = (int)$_POST['user_id'];
                        $status = unblockUser($userId);
                        if ($status) {
                            $success = "User has been unblocked successfully.";
                        } else {
                            $error = "Failed to unblock user. Please try again.";
                        }
                    }
                    break;
                    
                case 'delete':
                    if (isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
                        $userId = (int)$_POST['user_id'];
                        // Don't allow deleting self
                        if ($userId === (int)$_SESSION['user_id']) {
                            $error = "You cannot delete yourself.";
                        } else {
                            $status = deleteUser($userId);
                            if ($status) {
                                $success = "User has been deleted successfully.";
                            } else {
                                $error = "Failed to delete user. Please try again.";
                            }
                        }
                    }
                    break;
                    
                case 'change_role':
                    if (isset($_POST['user_id']) && is_numeric($_POST['user_id']) && isset($_POST['role'])) {
                        $userId = (int)$_POST['user_id'];
                        $role = sanitizeInput($_POST['role']);
                        
                        // Don't allow changing own role
                        if ($userId === (int)$_SESSION['user_id']) {
                            $error = "You cannot change your own role.";
                        } else {
                            // Validate role value
                            $validRoles = ['admin', 'staff', 'customer'];
                            if (in_array($role, $validRoles)) {
                                $status = changeUserRole($userId, $role);
                                if ($status) {
                                    $success = "User's role has been changed to {$role} successfully.";
                                } else {
                                    $error = "Failed to change user's role. Please try again.";
                                }
                            } else {
                                $error = "Invalid role specified.";
                            }
                        }
                    }
                    break;
                    
                case 'edit_user':
                    if (isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
                        $userId = (int)$_POST['user_id'];
                        $firstName = sanitizeInput($_POST['first_name'] ?? '');
                        $lastName = sanitizeInput($_POST['last_name'] ?? '');
                        $email = sanitizeInput($_POST['email'] ?? '');
                        $phone = sanitizeInput($_POST['phone'] ?? '');
                        
                        // Validate email
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $error = "Invalid email address.";
                        } else {
                            // Check if email already exists for another user
                            if (emailExistsForOtherUser($email, $userId)) {
                                $error = "Email address is already in use by another user.";
                            } else {
                                $status = updateUser($userId, $firstName, $lastName, $email, $phone);
                                if ($status) {
                                    $success = "User details have been updated successfully.";
                                } else {
                                    $error = "Failed to update user details. Please try again.";
                                }
                            }
                        }
                    }
                    break;
                
                case 'add_user':
                    $firstName = sanitizeInput($_POST['first_name'] ?? '');
                    $lastName = sanitizeInput($_POST['last_name'] ?? '');
                    $email = sanitizeInput($_POST['email'] ?? '');
                    $phone = sanitizeInput($_POST['phone'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $role = sanitizeInput($_POST['role'] ?? 'customer');
                    
                    // Validate inputs
                    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
                        $error = "All fields marked with * are required.";
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = "Invalid email address.";
                    } elseif (strlen($password) < 8) {
                        $error = "Password must be at least 8 characters long.";
                    } elseif (emailExists($email)) {
                        $error = "Email address is already in use.";
                    } else {
                        // Validate role value
                        $validRoles = ['admin', 'staff', 'customer'];
                        if (in_array($role, $validRoles)) {
                            $userId = createUser($firstName, $lastName, $email, $phone, $password, $role);
                            if ($userId) {
                                $success = "New user has been created successfully.";
                            } else {
                                $error = "Failed to create new user. Please try again.";
                            }
                        } else {
                            $error = "Invalid role specified.";
                        }
                    }
                    break;
            }
        }
    }
}

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Pagination, search, and filter handling
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $currentPage = max(1, (int)$_GET['page']);
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = sanitizeInput($_GET['search']);
}

if (isset($_GET['role']) && !empty($_GET['role'])) {
    $filterRole = sanitizeInput($_GET['role']);
}

// Get total number of users and calculate pagination
$totalUsers = countUsers($searchTerm, $filterRole);
$totalPages = ceil($totalUsers / $itemsPerPage);
$currentPage = min($currentPage, max(1, $totalPages));
$offset = ($currentPage - 1) * $itemsPerPage;

// Get users for current page
$users = getUsers($offset, $itemsPerPage, $searchTerm, $filterRole);

// Functions for user management

/**
 * Count total number of users with filter
 */
function countUsers($search = '', $role = '') {
    global $conn;
    
    $sql = "SELECT COUNT(*) as total FROM users WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $searchPattern = "%{$search}%";
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
    }
    
    if (!empty($role)) {
        $sql .= " AND role = ?";
        $params[] = $role;
    }
    
    try {
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['total'];
    } catch (PDOException $e) {
        error_log("Database Error (countUsers): " . $e->getMessage());
        return 0;
    }
}

/**
 * Get users with pagination and filters
 */
function getUsers($offset = 0, $limit = 10, $search = '', $role = '') {
    global $conn;
    
    $sql = "SELECT * FROM users WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $searchPattern = "%{$search}%";
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
    }
    
    if (!empty($role)) {
        $sql .= " AND role = ?";
        $params[] = $role;
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ?, ?";
    $params[] = (int)$offset;
    $params[] = (int)$limit;
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database Error (getUsers): " . $e->getMessage());
        return [];
    }
}

/**
 * Block a user
 */
function blockUser($userId) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("UPDATE users SET status = 'blocked', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Database Error (blockUser): " . $e->getMessage());
        return false;
    }
}

/**
 * Unblock a user
 */
function unblockUser($userId) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Database Error (unblockUser): " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a user
 */
function deleteUser($userId) {
    global $conn;
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Delete related records
        // Note: You need to adapt this based on your database schema and constraints
        // Example: Delete user invoices, payments, etc.
        
        // Finally delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Commit transaction
        $conn->commit();
        
        return true;
    } catch (PDOException $e) {
        // Rollback on error
        $conn->rollBack();
        error_log("Database Error (deleteUser): " . $e->getMessage());
        return false;
    }
}

/**
 * Change user role
 */
function changeUserRole($userId, $role) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$role, $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Database Error (changeUserRole): " . $e->getMessage());
        return false;
    }
}

/**
 * Update user details
 */
function updateUser($userId, $firstName, $lastName, $email, $phone) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$firstName, $lastName, $email, $phone, $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Database Error (updateUser): " . $e->getMessage());
        return false;
    }
}

/**
 * Create new user
 */
function createUser($firstName, $lastName, $email, $phone, $password, $role) {
    global $conn;
    
    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())");
        $stmt->execute([$firstName, $lastName, $email, $phone, $hashedPassword, $role]);
        
        return $conn->lastInsertId();
    } catch (PDOException $e) {
        error_log("Database Error (createUser): " . $e->getMessage());
        return false;
    }
}

/**
 * Check if email exists for user creation
 */
function emailExists($email) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Database Error (emailExists): " . $e->getMessage());
        return true; // Return true on error to prevent duplicate email
    }
}

/**
 * Check if email exists for another user (for updates)
 */
function emailExistsForOtherUser($email, $userId) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Database Error (emailExistsForOtherUser): " . $e->getMessage());
        return true; // Return true on error to prevent duplicate email
    }
}

/**
 * Get user details by ID
 */
function getUserById($userId) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database Error (getUserById): " . $e->getMessage());
        return false;
    }
}

// Include header
$pageTitle = "User Management";
require_once '../templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex flex-col md:flex-row items-center justify-between mb-6">
        <h1 class="text-2xl font-bold mb-2 md:mb-0">User Management</h1>
        <button id="addUserBtn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-user-plus mr-2"></i> Add New User
        </button>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $error; ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $success; ?></span>
        </div>
    <?php endif; ?>
    
    <!-- Search and Filter -->
    <div class="bg-white shadow-md rounded-lg p-4 mb-6">
        <form method="GET" action="" class="md:flex md:space-x-4 space-y-4 md:space-y-0">
            <div class="flex-1">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($searchTerm); ?>" 
                    placeholder="Search by name or email" 
                    class="w-full border border-gray-300 px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            
            <div class="md:w-1/4">
                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                <select name="role" id="role" 
                    class="w-full border border-gray-300 px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="staff" <?php echo $filterRole === 'staff' ? 'selected' : ''; ?>>Staff</option>
                    <option value="customer" <?php echo $filterRole === 'customer' ? 'selected' : ''; ?>>Customer</option>
                </select>
            </div>
            
            <div class="md:w-1/6 flex items-end">
                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-search mr-2"></i> Search
                </button>
            </div>
            
            <?php if (!empty($searchTerm) || !empty($filterRole)): ?>
                <div class="md:w-1/6 flex items-end">
                    <a href="users.php" class="w-full bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-center">
                        <i class="fas fa-times mr-2"></i> Clear
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Users Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            User
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Contact
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Role
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Registered
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                No users found matching your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                <span class="text-gray-700 font-medium">
                                                    <?php echo substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                ID: <?php echo $user['id']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'No phone'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        echo $user['role'] === 'admin' 
                                            ? 'bg-purple-100 text-purple-800' 
                                            : ($user['role'] === 'staff' 
                                                ? 'bg-blue-100 text-blue-800' 
                                                : 'bg-green-100 text-green-800'); 
                                        ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php 
                                        echo $user['status'] === 'active' 
                                            ? 'bg-green-100 text-green-800' 
                                            : 'bg-red-100 text-red-800'; 
                                        ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button class="text-blue-600 hover:text-blue-900 edit-user-btn" 
                                                data-user-id="<?php echo $user['id']; ?>"
                                                title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <?php if ($user['status'] === 'active'): ?>
                                                <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to block this user?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="block">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="text-yellow-600 hover:text-yellow-900" title="Block User">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to unblock this user?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="unblock">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="text-green-600 hover:text-green-900" title="Unblock User">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <button class="text-indigo-600 hover:text-indigo-900 change-role-btn" 
                                                    data-user-id="<?php echo $user['id']; ?>"
                                                    data-current-role="<?php echo $user['role']; ?>"
                                                    title="Change Role">
                                                <i class="fas fa-user-tag"></i>
                                            </button>
                                            
                                            <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-900" title="Delete User">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="flex justify-center my-6">
            <div class="inline-flex rounded-md shadow">
                <nav class="flex" aria-label="Pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?php echo $currentPage - 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($filterRole) ? '&role=' . urlencode($filterRole) : ''; ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Previous</span>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $startPage + 4);
                    if ($endPage - $startPage < 4) {
                        $startPage = max(1, $endPage - 4);
                    }
                    ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($filterRole) ? '&role=' . urlencode($filterRole) : ''; ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $currentPage ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?php echo $currentPage + 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($filterRole) ? '&role=' . urlencode($filterRole) : ''; ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Next</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Summary -->
    <div class="text-gray-600 text-sm text-center">
        Showing <?php echo count($users); ?> of <?php echo $totalUsers; ?> users
        <?php if (!empty($searchTerm) || !empty($filterRole)): ?>
            with applied filters
        <?php endif; ?>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Add New User</h3>
            <button id="closeAddUserModal" class="text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" id="addUserForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="add_user">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                    <input type="text" name="first_name" id="first_name" required
                        class="w-full border border-gray-300 px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                    <input type="text" name="last_name" id="last_name" required
                        class="w-full border border-gray-300 px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>
            
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                <input type="email" name="email" id="email" required
                    class="w-full border border-gray-300 px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <input type="text" name="phone" id="phone"
                    class="w-full border border-gray-300 px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                <input type="password" name="password" id="password" required minlength="8"
                    class="w-full border border-gray-300 px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">Password must be at least 8 characters long.</p>
            </div>
            
            <div class="mb-4">
                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                <select name="role" id="role" required
                    class="w-full border border-gray-300 px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="customer">Customer</option>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" id="cancelAddUser" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Add User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Edit User</h3>
            <button id="closeEditUserModal" class="text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" id="editUserForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id" value="">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="edit_first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                    <input type="text" name="first_name" id="edit_first_name" required
                        class="w-full border border-gray-300 px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="edit_last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <input type="text" name="last_name" id="edit_last_name" required
                        class="w-full border border-gray-300 px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>
            
            <div class="mb-4">
                <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <input type="email" name="email" id="edit_email" required
                    class="w-full border border-gray-300 px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label for="edit_phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <input type="text" name="phone" id="edit_phone"
                    class="w-full border border-gray-300 px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" id="cancelEditUser" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Update User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Change Role Modal -->
<div id="changeRoleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Change User Role</h3>
            <button id="closeChangeRoleModal" class="text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" id="changeRoleForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="change_role">
            <input type="hidden" name="user_id" id="role_user_id" value="">
            
            <div class="mb-4">
                <label for="role_select" class="block text-sm font-medium text-gray-700 mb-1">Select New Role</label>
                <select name="role" id="role_select" required
                    class="w-full border border-gray-300 px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="customer">Customer</option>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" id="cancelChangeRole" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Change Role
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Add User Modal Functionality
const addUserBtn = document.getElementById('addUserBtn');
const addUserModal = document.getElementById('addUserModal');
const closeAddUserModal = document.getElementById('closeAddUserModal');
const cancelAddUser = document.getElementById('cancelAddUser');
const addUserForm = document.getElementById('addUserForm');

function showAddUserModal() {
    addUserModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideAddUserModal() {
    addUserModal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    addUserForm.reset();
}

addUserBtn.addEventListener('click', showAddUserModal);
closeAddUserModal.addEventListener('click', hideAddUserModal);
cancelAddUser.addEventListener('click', hideAddUserModal);

// Edit User Modal Functionality
const editUserBtns = document.querySelectorAll('.edit-user-btn');
const editUserModal = document.getElementById('editUserModal');
const closeEditUserModal = document.getElementById('closeEditUserModal');
const cancelEditUser = document.getElementById('cancelEditUser');
const editUserForm = document.getElementById('editUserForm');
const editUserId = document.getElementById('edit_user_id');
const editFirstName = document.getElementById('edit_first_name');
const editLastName = document.getElementById('edit_last_name');
const editEmail = document.getElementById('edit_email');
const editPhone = document.getElementById('edit_phone');

function showEditUserModal(userId) {
    // Fetch user data using AJAX
    fetch(`../api/users.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                editUserId.value = user.id;
                editFirstName.value = user.first_name;
                editLastName.value = user.last_name;
                editEmail.value = user.email;
                editPhone.value = user.phone || '';
                
                editUserModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            } else {
                alert('Failed to load user data. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error fetching user data:', error);
            alert('An error occurred while loading user data. Please try again.');
        });
}

function hideEditUserModal() {
    editUserModal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

editUserBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        const userId = btn.getAttribute('data-user-id');
        showEditUserModal(userId);
    });
});

closeEditUserModal.addEventListener('click', hideEditUserModal);
cancelEditUser.addEventListener('click', hideEditUserModal);

// Change Role Modal Functionality
const changeRoleBtns = document.querySelectorAll('.change-role-btn');
const changeRoleModal = document.getElementById('changeRoleModal');
const closeChangeRoleModal = document.getElementById('closeChangeRoleModal');
const cancelChangeRole = document.getElementById('cancelChangeRole');
const changeRoleForm = document.getElementById('changeRoleForm');
const roleUserId = document.getElementById('role_user_id');
const roleSelect = document.getElementById('role_select');

function showChangeRoleModal(userId, currentRole) {
    roleUserId.value = userId;
    roleSelect.value = currentRole;
    
    changeRoleModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideChangeRoleModal() {
    changeRoleModal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

changeRoleBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        const userId = btn.getAttribute('data-user-id');
        const currentRole = btn.getAttribute('data-current-role');
        showChangeRoleModal(userId, currentRole);
    });
});

closeChangeRoleModal.addEventListener('click', hideChangeRoleModal);
cancelChangeRole.addEventListener('click', hideChangeRoleModal);

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === addUserModal) {
        hideAddUserModal();
    } else if (event.target === editUserModal) {
        hideEditUserModal();
    } else if (event.target === changeRoleModal) {
        hideChangeRoleModal();
    }
});
</script>

<?php require_once '../templates/footer.php'; ?>