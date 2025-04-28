 
<?php
/**
 * Admin Invoices Management
 * 
 * This file handles all invoice management functionality for administrators
 * including viewing, filtering, creating manual invoices, and updating statuses.
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
require_once '../includes/invoice-functions.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !isAdmin()) {
    // Redirect to login page with error message
    $_SESSION['error'] = "You must be logged in as an administrator to access this page.";
    header("Location: index.php");
    exit();
}

// Set page title
$pageTitle = "Invoice Management";

// Initialize variables
$invoices = [];
$totalInvoices = 0;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20; // Number of invoices per page
$start = ($currentPage - 1) * $perPage;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create manual invoice
    if (isset($_POST['create_invoice'])) {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $items = isset($_POST['items']) ? $_POST['items'] : [];
        $dueDate = isset($_POST['due_date']) ? sanitize($_POST['due_date']) : '';
        $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
        
        // Validate required fields
        if ($userId <= 0) {
            $error = "Please select a valid user";
        } elseif (empty($items)) {
            $error = "Please add at least one item to the invoice";
        } elseif (empty($dueDate)) {
            $error = "Please set a due date for the invoice";
        } else {
            // Create invoice
            $invoiceId = createManualInvoice($userId, $items, $dueDate, $notes);
            
            if ($invoiceId) {
                $message = "Invoice #$invoiceId created successfully";
                // Optionally send notification to user
                sendInvoiceNotification($invoiceId, $userId);
            } else {
                $error = "Failed to create invoice. Please try again.";
            }
        }
    }
    
    // Update invoice status
    if (isset($_POST['update_status'])) {
        $invoiceId = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
        $newStatus = isset($_POST['new_status']) ? sanitize($_POST['new_status']) : '';
        
        if ($invoiceId > 0 && !empty($newStatus)) {
            $updated = updateInvoiceStatus($invoiceId, $newStatus);
            
            if ($updated) {
                $message = "Invoice #$invoiceId status updated to " . ucfirst($newStatus);
                
                // If marked as paid, record the payment
                if ($newStatus === 'paid' && isset($_POST['payment_method'])) {
                    $paymentMethod = sanitize($_POST['payment_method']);
                    $paymentRef = isset($_POST['payment_ref']) ? sanitize($_POST['payment_ref']) : '';
                    
                    recordManualPayment($invoiceId, $paymentMethod, $paymentRef);
                }
            } else {
                $error = "Failed to update invoice status";
            }
        } else {
            $error = "Invalid invoice or status";
        }
    }
    
    // Delete invoice
    if (isset($_POST['delete_invoice'])) {
        $invoiceId = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
        
        if ($invoiceId > 0) {
            // Soft delete instead of hard delete
            $deleted = softDeleteInvoice($invoiceId);
            
            if ($deleted) {
                $message = "Invoice #$invoiceId has been deleted";
            } else {
                $error = "Failed to delete invoice";
            }
        } else {
            $error = "Invalid invoice ID";
        }
    }
    
    // Send reminder
    if (isset($_POST['send_reminder'])) {
        $invoiceId = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
        
        if ($invoiceId > 0) {
            $sent = sendInvoiceReminder($invoiceId);
            
            if ($sent) {
                $message = "Payment reminder sent for Invoice #$invoiceId";
            } else {
                $error = "Failed to send reminder";
            }
        } else {
            $error = "Invalid invoice ID";
        }
    }
}

// Build the SQL query for invoices with filters
$sql = "SELECT i.*, u.full_name, u.email 
        FROM invoices i 
        JOIN users u ON i.user_id = u.id 
        WHERE 1=1";

$countSql = "SELECT COUNT(*) as total FROM invoices i JOIN users u ON i.user_id = u.id WHERE 1=1";

// Apply search filter
if (!empty($search)) {
    $searchCondition = " AND (i.invoice_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $sql .= $searchCondition;
    $countSql .= $searchCondition;
}

// Apply status filter
if ($statusFilter !== 'all') {
    $statusCondition = " AND i.status = ?";
    $sql .= $statusCondition;
    $countSql .= $statusCondition;
}

// Apply date range filter
if (!empty($dateFrom) && !empty($dateTo)) {
    $dateCondition = " AND (i.created_at BETWEEN ? AND ?)";
    $sql .= $dateCondition;
    $countSql .= $dateCondition;
}

// Add order and pagination
$sql .= " ORDER BY i.created_at DESC LIMIT ?, ?";

// Prepare and execute the count query
$countStmt = $pdo->prepare($countSql);

// Prepare parameters for count query
$params = [];
if (!empty($search)) {
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($statusFilter !== 'all') {
    $params[] = $statusFilter;
}

if (!empty($dateFrom) && !empty($dateTo)) {
    $params[] = $dateFrom . ' 00:00:00';
    $params[] = $dateTo . ' 23:59:59';
}

// Execute count query
$countStmt->execute($params);
$totalRow = $countStmt->fetch(PDO::FETCH_ASSOC);
$totalInvoices = $totalRow['total'];

// Calculate total pages
$totalPages = ceil($totalInvoices / $perPage);

// Prepare and execute the main query
$stmt = $pdo->prepare($sql);

// Add pagination parameters
$params[] = $start;
$params[] = $perPage;

// Execute main query
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for the create invoice form
$usersStmt = $pdo->query("SELECT id, full_name, email FROM users ORDER BY full_name");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all services for the create invoice form
$servicesStmt = $pdo->query("SELECT id, name, price FROM services WHERE active = 1 ORDER BY name");
$services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

// Include header
include_once '../templates/header.php';
?>

<div class="flex">
    <!-- Include sidebar -->
    <?php include_once '../templates/sidebar.php'; ?>
    
    <main class="flex-1 p-4 md:p-6 bg-gray-100 min-h-screen">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Invoice Management</h1>
            
            <?php if (!empty($message)): ?>
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <!-- Invoice Management Tabs -->
            <div class="mb-6 border-b border-gray-200">
                <div class="flex flex-wrap -mb-px">
                    <a href="#" class="mr-2 inline-block py-2 px-4 text-sm font-medium text-center text-blue-600 border-b-2 border-blue-600 rounded-t-lg active">
                        All Invoices
                    </a>
                    <a href="#create-invoice" class="mr-2 inline-block py-2 px-4 text-sm font-medium text-center text-gray-500 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300">
                        Create Invoice
                    </a>
                    <a href="#invoice-settings" class="mr-2 inline-block py-2 px-4 text-sm font-medium text-center text-gray-500 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300">
                        Invoice Settings
                    </a>
                </div>
            </div>
            
            <!-- Filters and Search -->
            <div class="bg-white rounded-lg shadow mb-6 p-4">
                <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Invoice #, customer name, email" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="outstanding" <?php echo $statusFilter === 'outstanding' ? 'selected' : ''; ?>>Outstanding</option>
                            <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="canceled" <?php echo $statusFilter === 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="date_range" class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md">
                            Filter Results
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Invoices List -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Invoice #
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Due Date
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">
                                    No invoices found. Try adjusting your filters.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            #<?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($invoice['full_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($invoice['email']); ?></div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo formatCurrency($invoice['total_amount']); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo formatDate($invoice['created_at']); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo formatDate($invoice['due_date']); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <?php
                                        $statusClass = '';
                                        switch($invoice['status']) {
                                            case 'pending':
                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'outstanding':
                                                $statusClass = 'bg-red-100 text-red-800';
                                                break;
                                            case 'paid':
                                                $statusClass = 'bg-green-100 text-green-800';
                                                break;
                                            case 'canceled':
                                                $statusClass = 'bg-gray-100 text-gray-800';
                                                break;
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo ucfirst(htmlspecialchars($invoice['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <a href="view-invoice.php?id=<?php echo $invoice['id']; ?>" class="text-blue-600 hover:text-blue-900" title="View Invoice">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </a>
                                            <button type="button" class="text-indigo-600 hover:text-indigo-900" title="Edit Invoice" 
                                                onclick="openEditModal(<?php echo $invoice['id']; ?>, '<?php echo $invoice['status']; ?>')">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <a href="../invoices/<?php echo $invoice['invoice_number']; ?>.pdf" target="_blank" class="text-green-600 hover:text-green-900" title="Download PDF">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                                </svg>
                                            </a>
                                            <?php if ($invoice['status'] === 'pending' || $invoice['status'] === 'outstanding'): ?>
                                            <button type="button" class="text-yellow-600 hover:text-yellow-900" title="Send Reminder" 
                                                onclick="openReminderModal(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number']); ?>')">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                                </svg>
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="text-red-600 hover:text-red-900" title="Delete Invoice" 
                                                onclick="openDeleteModal(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number']); ?>')">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between items-center">
                            <?php if ($currentPage > 1): ?>
                            <a href="?page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                            <?php else: ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-300 bg-white cursor-not-allowed">
                                Previous
                            </span>
                            <?php endif; ?>
                            
                            <div class="hidden md:flex">
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium"><?php echo $start + 1; ?></span> to <span class="font-medium"><?php echo min($start + $perPage, $totalInvoices); ?></span> of <span class="font-medium"><?php echo $totalInvoices; ?></span> results
                                </p>
                            </div>
                            
                            <?php if ($currentPage < $totalPages): ?>
                            <a href="?page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                            <?php else: ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-300 bg-white cursor-not-allowed">
                                Next
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Create Invoice Form -->
            <div id="create-invoice" class="mt-8 bg-white rounded-lg shadow p-6 hidden">
                <h2 class="text-xl font-bold mb-4">Create New Invoice</h2>
                
                <form action="" method="POST" id="createInvoiceForm">
                    <input type="hidden" name="create_invoice" value="1">
                    
                    <div class="mb-4">
                        <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                        <select id="user_id" name="user_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select Customer</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Items</label>
                        <div id="invoice-items">
                            <div class="invoice-item grid grid-cols-12 gap-2 mb-2">
                                <div class="col-span-5">
                                    <select name="items[0][service_id]" class="service-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                        <option value="">Select Service</option>
                                        <?php foreach ($services as $service): ?>
                                        <option value="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>"><?php echo htmlspecialchars($service['name']); ?> (<?php echo formatCurrency($service['price']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <input type="number" name="items[0][quantity]" placeholder="Qty" min="1" value="1" class="quantity-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                </div>
                                <div class="col-span-2">
                                    <input type="text" name="items[0][price]" placeholder="Price" class="price-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" readonly>
                                </div>
                                <div class="col-span-2">
                                    <input type="text" name="items[0][total]" placeholder="Total" class="total-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" readonly>
                                </div>
                                <div class="col-span-1 flex items-center justify-center">
                                    <button type="button" class="remove-item text-red-600 hover:text-red-900" title="Remove Item" disabled>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-2">
                            <button type="button" id="add-item" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="-ml-0.5 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Item
                            </button>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="due_date" class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                            <input type="date" id="due_date" name="due_date" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="subtotal" class="block text-sm font-medium text-gray-700 mb-1">Subtotal</label>
                            <input type="text" id="subtotal" name="subtotal" readonly class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <label for="tax_rate" class="block text-sm font-medium text-gray-700 mb-1">Tax Rate (%)</label>
                            <input type="number" id="tax_rate" name="tax_rate" min="0" max="100" step="0.01" value="0" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="tax_amount" class="block text-sm font-medium text-gray-700 mb-1">Tax Amount</label>
                            <input type="text" id="tax_amount" name="tax_amount" readonly class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm">
                        </div>
                        <div>
                            <label for="total_amount" class="block text-sm font-medium text-gray-700 mb-1">Total Amount</label>
                            <input type="text" id="total_amount" name="total_amount" readonly class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm font-bold">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes/Terms</label>
                        <textarea id="notes" name="notes" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Create Invoice
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Invoice Settings -->
            <div id="invoice-settings" class="mt-8 bg-white rounded-lg shadow p-6 hidden">
                <h2 class="text-xl font-bold mb-4">Invoice Settings</h2>
                
                <form action="../api/settings.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="hidden" name="update_invoice_settings" value="1">
                    
                    <div>
                        <h3 class="text-lg font-medium mb-3">General Settings</h3>
                        
                        <div class="mb-4">
                            <label for="company_name" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                            <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars(getSetting('company_name')); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="company_address" class="block text-sm font-medium text-gray-700 mb-1">Company Address</label>
                            <textarea id="company_address" name="company_address" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars(getSetting('company_address')); ?></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label for="company_phone" class="block text-sm font-medium text-gray-700 mb-1">Company Phone</label>
                            <input type="text" id="company_phone" name="company_phone" value="<?php echo htmlspecialchars(getSetting('company_phone')); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="company_email" class="block text-sm font-medium text-gray-700 mb-1">Company Email</label>
                            <input type="email" id="company_email" name="company_email" value="<?php echo htmlspecialchars(getSetting('company_email')); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="company_website" class="block text-sm font-medium text-gray-700 mb-1">Company Website</label>
                            <input type="url" id="company_website" name="company_website" value="<?php echo htmlspecialchars(getSetting('company_website')); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="invoice_prefix" class="block text-sm font-medium text-gray-700 mb-1">Invoice Number Prefix</label>
                            <input type="text" id="invoice_prefix" name="invoice_prefix" value="<?php echo htmlspecialchars(getSetting('invoice_prefix', 'INV')); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium mb-3">Payment & Due Date Settings</h3>
                        
                        <div class="mb-4">
                            <label for="currency" class="block text-sm font-medium text-gray-700 mb-1">Default Currency</label>
                            <select id="currency" name="currency" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="USD" <?php echo getSetting('currency') === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                <option value="EUR" <?php echo getSetting('currency') === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                <option value="GBP" <?php echo getSetting('currency') === 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                                <option value="INR" <?php echo getSetting('currency') === 'INR' ? 'selected' : ''; ?>>INR (₹)</option>
                                <!-- Add more currencies as needed -->
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="default_tax_rate" class="block text-sm font-medium text-gray-700 mb-1">Default Tax Rate (%)</label>
                            <input type="number" id="default_tax_rate" name="default_tax_rate" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars(getSetting('default_tax_rate', '0')); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="payment_due_days" class="block text-sm font-medium text-gray-700 mb-1">Default Payment Due Days</label>
                            <input type="number" id="payment_due_days" name="payment_due_days" min="1" max="90" value="<?php echo htmlspecialchars(getSetting('payment_due_days', '14')); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="default_payment_methods" class="block text-sm font-medium text-gray-700 mb-1">Enabled Payment Methods</label>
                            <div class="mt-1 space-y-2">
                                <?php
                                $enabledMethods = explode(',', getSetting('default_payment_methods', 'credit_card,bank_transfer'));
                                $paymentMethods = [
                                    'credit_card' => 'Credit Card',
                                    'paypal' => 'PayPal',
                                    'bank_transfer' => 'Bank Transfer',
                                    'cash' => 'Cash',
                                    'wallet' => 'Wallet'
                                ];
                                
                                foreach ($paymentMethods as $value => $label) {
                                    $checked = in_array($value, $enabledMethods) ? 'checked' : '';
                                    echo '<div class="flex items-start">';
                                    echo '<div class="flex items-center h-5">';
                                    echo '<input id="payment_method_' . $value . '" name="payment_methods[]" type="checkbox" value="' . $value . '" ' . $checked . ' class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">';
                                    echo '</div>';
                                    echo '<div class="ml-3 text-sm">';
                                    echo '<label for="payment_method_' . $value . '" class="font-medium text-gray-700">' . $label . '</label>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="default_invoice_notes" class="block text-sm font-medium text-gray-700 mb-1">Default Invoice Notes/Terms</label>
                            <textarea id="default_invoice_notes" name="default_invoice_notes" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars(getSetting('default_invoice_notes')); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<!-- Modals -->
<!-- Edit Invoice Status Modal -->
<div id="edit-invoice-modal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">Update Invoice Status</h3>
            <button type="button" class="text-gray-400 hover:text-gray-500" onclick="closeModal('edit-invoice-modal')">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        <form action="" method="POST">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" id="edit_invoice_id" name="invoice_id" value="">
            
            <div class="mb-4">
                <label for="new_status" class="block text-sm font-medium text-gray-700 mb-1">New Status</label>
                <select id="new_status" name="new_status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="togglePaymentFields()">
                    <option value="pending">Pending</option>
                    <option value="outstanding">Outstanding</option>
                    <option value="paid">Paid</option>
                    <option value="canceled">Canceled</option>
                </select>
            </div>
            
            <div id="payment_fields" class="hidden">
                <div class="mb-4">
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                    <select id="payment_method" name="payment_method" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="credit_card">Credit Card</option>
                        <option value="paypal">PayPal</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="wallet">Wallet</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="payment_ref" class="block text-sm font-medium text-gray-700 mb-1">Payment Reference</label>
                    <input type="text" id="payment_ref" name="payment_ref" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Transaction ID, Reference Number, etc.">
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="button" class="mr-3 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md" onclick="closeModal('edit-invoice-modal')">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                    Update Status
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Invoice Modal -->
<div id="delete-invoice-modal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">Delete Invoice</h3>
            <button type="button" class="text-gray-400 hover:text-gray-500" onclick="closeModal('delete-invoice-modal')">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        <p class="mb-4 text-gray-700">Are you sure you want to delete invoice <span id="delete_invoice_number" class="font-semibold"></span>? This action cannot be undone.</p>
        
        <form action="" method="POST">
            <input type="hidden" name="delete_invoice" value="1">
            <input type="hidden" id="delete_invoice_id" name="invoice_id" value="">
            
            <div class="flex justify-end">
                <button type="button" class="mr-3 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md" onclick="closeModal('delete-invoice-modal')">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md">
                    Delete Invoice
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Send Reminder Modal -->
<div id="reminder-modal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">Send Payment Reminder</h3>
            <button type="button" class="text-gray-400 hover:text-gray-500" onclick="closeModal('reminder-modal')">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        <p class="mb-4 text-gray-700">Send a payment reminder email for invoice <span id="reminder_invoice_number" class="font-semibold"></span>?</p>
        
        <form action="" method="POST">
            <input type="hidden" name="send_reminder" value="1">
            <input type="hidden" id="reminder_invoice_id" name="invoice_id" value="">
            
            <div class="flex justify-end">
                <button type="button" class="mr-3 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md" onclick="closeModal('reminder-modal')">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                    Send Reminder
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Tab navigation
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.flex-wrap a');
    const tabContents = {
        'All Invoices': document.querySelector('.bg-white.rounded-lg.shadow.overflow-hidden'),
        'Create Invoice': document.getElementById('create-invoice'),
        'Invoice Settings': document.getElementById('invoice-settings')
    };
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs
            tabs.forEach(t => {
                t.classList.remove('text-blue-600', 'border-blue-600');
                t.classList.add('text-gray-500', 'border-transparent');
            });
            
            // Add active class to clicked tab
            this.classList.remove('text-gray-500', 'border-transparent');
            this.classList.add('text-blue-600', 'border-blue-600');
            
            // Hide all content
            Object.values(tabContents).forEach(content => {
                content.classList.add('hidden');
            });
            
            // Show selected content
            const tabName = this.textContent.trim();
            if (tabContents[tabName]) {
                tabContents[tabName].classList.remove('hidden');
            }
        });
    });
    
    // If URL has hash, activate corresponding tab
    if (window.location.hash) {
        const targetTab = document.querySelector(`a[href="${window.location.hash}"]`);
        if (targetTab) {
            targetTab.click();
        }
    }
    
    // Invoice item handling for create invoice form
    const addItemBtn = document.getElementById('add-item');
    const invoiceItems = document.getElementById('invoice-items');
    let itemIndex = 0;
    
    // Update price when service is selected
    function updateItemPrices() {
        document.querySelectorAll('.service-select').forEach(select => {
            select.addEventListener('change', function() {
                const row = this.closest('.invoice-item');
                const priceInput = row.querySelector('.price-input');
                const quantityInput = row.querySelector('.quantity-input');
                const totalInput = row.querySelector('.total-input');
                
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const price = parseFloat(selectedOption.dataset.price);
                    priceInput.value = price.toFixed(2);
                    
                    // Calculate total
                    const quantity = parseInt(quantityInput.value);
                    totalInput.value = (price * quantity).toFixed(2);
                    
                    // Update invoice totals
                    calculateInvoiceTotals();
                } else {
                    priceInput.value = '';
                    totalInput.value = '';
                }
            });
        });
        
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                const row = this.closest('.invoice-item');
                const priceInput = row.querySelector('.price-input');
                const totalInput = row.querySelector('.total-input');
                
                if (priceInput.value) {
                    const price = parseFloat(priceInput.value);
                    const quantity = parseInt(this.value);
                    totalInput.value = (price * quantity).toFixed(2);
                    
                    // Update invoice totals
                    calculateInvoiceTotals();
                }
            });
        });
    }
    
    // Calculate invoice totals
    function calculateInvoiceTotals() {
        let subtotal = 0;
        
        document.querySelectorAll('.total-input').forEach(input => {
            if (input.value) {
                subtotal += parseFloat(input.value);
            }
        });
        
        const subtotalInput = document.getElementById('subtotal');
        const taxRateInput = document.getElementById('tax_rate');
        const taxAmountInput = document.getElementById('tax_amount');
        const totalAmountInput = document.getElementById('total_amount');
        
        subtotalInput.value = subtotal.toFixed(2);
        
        const taxRate = parseFloat(taxRateInput.value) || 0;
        const taxAmount = subtotal * (taxRate / 100);
        taxAmountInput.value = taxAmount.toFixed(2);
        
        const totalAmount = subtotal + taxAmount;
        totalAmountInput.value = totalAmount.toFixed(2);
    }
    
    // Tax rate change event
    document.getElementById('tax_rate').addEventListener('change', calculateInvoiceTotals);
    
    // Add new item
    if (addItemBtn) {
        addItemBtn.addEventListener('click', function() {
            itemIndex++;
            
            const newItem = document.createElement('div');
            newItem.className = 'invoice-item grid grid-cols-12 gap-2 mb-2';
            newItem.innerHTML = `
                <div class="col-span-5">
                    <select name="items[${itemIndex}][service_id]" class="service-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">Select Service</option>
                        <?php foreach ($services as $service): ?>
                        <option value="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>"><?php echo htmlspecialchars($service['name']); ?> (<?php echo formatCurrency($service['price']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-span-2">
                    <input type="number" name="items[${itemIndex}][quantity]" placeholder="Qty" min="1" value="1" class="quantity-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                </div>
                <div class="col-span-2">
                    <input type="text" name="items[${itemIndex}][price]" placeholder="Price" class="price-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" readonly>
                </div>
                <div class="col-span-2">
                    <input type="text" name="items[${itemIndex}][total]" placeholder="Total" class="total-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" readonly>
                </div>
                <div class="col-span-1 flex items-center justify-center">
                    <button type="button" class="remove-item text-red-600 hover:text-red-900" title="Remove Item">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            `;
            
            invoiceItems.appendChild(newItem);
            
            // Enable all remove buttons if there are multiple items
            if (invoiceItems.querySelectorAll('.invoice-item').length > 1) {
                document.querySelectorAll('.remove-item').forEach(btn => {
                    btn.removeAttribute('disabled');
                });
            }
            
            // Add event listeners to the new item
            updateItemPrices();
            
            // Add remove event listener
            newItem.querySelector('.remove-item').addEventListener('click', function() {
                this.closest('.invoice-item').remove();
                
                // Disable remove button if only one item left
                if (invoiceItems.querySelectorAll('.invoice-item').length === 1) {
                    document.querySelector('.remove-item').setAttribute('disabled', 'disabled');
                }
                
                // Update invoice totals
                calculateInvoiceTotals();
            });
        });
    }
    
    // Initialize item price updates
    updateItemPrices();
});

// Modal functions
function openEditModal(invoiceId, currentStatus) {
    document.getElementById('edit_invoice_id').value = invoiceId;
    document.getElementById('new_status').value = currentStatus;
    togglePaymentFields();
    
    document.getElementById('edit-invoice-modal').classList.remove('hidden');
}

function openDeleteModal(invoiceId, invoiceNumber) {
    document.getElementById('delete_invoice_id').value = invoiceId;
    document.getElementById('delete_invoice_number').textContent = invoiceNumber;
    
    document.getElementById('delete-invoice-modal').classList.remove('hidden');
}

function openReminderModal(invoiceId, invoiceNumber) {
    document.getElementById('reminder_invoice_id').value = invoiceId;
    document.getElementById('reminder_invoice_number').textContent = invoiceNumber;
    
    document.getElementById('reminder-modal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function togglePaymentFields() {
    const status = document.getElementById('new_status').value;
    const paymentFields = document.getElementById('payment_fields');
    
    if (status === 'paid') {
        paymentFields.classList.remove('hidden');
    } else {
        paymentFields.classList.add('hidden');
    }
}
</script>

<?php include_once '../templates/footer.php'; ?>