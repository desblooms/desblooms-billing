 
<?php
/**
 * Invoice Management Page
 * 
 * This page handles the display and management of invoices for users
 * Features: View all invoices, filter by status, download PDF, view details
 */

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/invoice-functions.php';

// Check if user is logged in, redirect to login if not
check_auth();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle actions (download, view details, etc.)
$action = isset($_GET['action']) ? $_GET['action'] : '';
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action == 'download' && $invoice_id > 0) {
    // Generate and download invoice PDF
    generate_invoice_pdf($invoice_id, $user_id, true);
    exit;
} elseif ($action == 'share' && $invoice_id > 0) {
    // Generate shareable link for invoice
    $share_link = generate_invoice_share_link($invoice_id, $user_id);
    // Redirect back with share link in session
    $_SESSION['share_link'] = $share_link;
    header('Location: invoices.php?status=shared&id=' . $invoice_id);
    exit;
}

// Handle filtering
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Get invoices based on filters
$invoices = get_user_invoices($user_id, $status_filter, $date_from, $date_to);

// Get stats for overview cards
$invoice_stats = get_invoice_stats($user_id);

// Page title
$page_title = "My Invoices";

// Include header
include 'templates/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <!-- Top Navigation Bar -->
    <?php include 'templates/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="p-4 sm:ml-64">
        <div class="p-2 md:p-4 mt-14">
            <h1 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">My Invoices</h1>
            
            <!-- Overview Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <!-- Total Invoices Card -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-md p-3 bg-blue-100">
                            <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Total Invoices</h2>
                            <p class="text-lg font-semibold text-gray-900"><?php echo $invoice_stats['total']; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Invoices Card -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-md p-3 bg-yellow-100">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Pending</h2>
                            <p class="text-lg font-semibold text-gray-900"><?php echo $invoice_stats['pending']; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Outstanding Invoices Card -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-md p-3 bg-red-100">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Outstanding</h2>
                            <p class="text-lg font-semibold text-gray-900"><?php echo $invoice_stats['outstanding']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-800">Filter Invoices</h2>
                </div>
                <div class="p-4">
                    <form action="" method="GET" class="space-y-4 md:space-y-0 md:flex md:items-end md:space-x-4">
                        <div class="flex-1">
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="status" name="status" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Invoices</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="outstanding" <?php echo $status_filter == 'outstanding' ? 'selected' : ''; ?>>Outstanding</option>
                                <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>" 
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex-1">
                            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>" 
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <button type="submit" class="w-full md:w-auto bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Share Link Alert -->
            <?php if (isset($_SESSION['share_link'])): ?>
            <div id="share-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm">Share link generated: <span class="font-medium break-all"><?php echo $_SESSION['share_link']; ?></span></p>
                        <button onclick="copyShareLink('<?php echo $_SESSION['share_link']; ?>')" class="mt-2 text-sm text-blue-600 hover:text-blue-800">Copy to clipboard</button>
                    </div>
                    <div class="ml-auto pl-3">
                        <div class="-mx-1.5 -my-1.5">
                            <button onclick="document.getElementById('share-alert').style.display = 'none'" class="inline-flex rounded-md p-1.5 text-green-500 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['share_link']); endif; ?>
            
            <!-- Invoices Table/List -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-800">My Invoices</h2>
                </div>
                
                <?php if (empty($invoices)): ?>
                <div class="p-6 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No invoices found</h3>
                    <p class="mt-1 text-sm text-gray-500">No invoices match your current filter criteria.</p>
                </div>
                <?php else: ?>
                
                <!-- Mobile View (Cards) -->
                <div class="md:hidden">
                    <?php foreach ($invoices as $invoice): ?>
                    <div class="border-b border-gray-200 p-4">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="text-sm font-semibold">#<?php echo $invoice['invoice_number']; ?></span>
                                <p class="text-xs text-gray-500"><?php echo format_date($invoice['invoice_date']); ?></p>
                            </div>
                            <div>
                                <?php echo get_status_badge($invoice['status']); ?>
                            </div>
                        </div>
                        <div class="mb-2">
                            <p class="text-sm text-gray-700"><?php echo $invoice['service_name']; ?></p>
                            <p class="text-base font-bold"><?php echo format_currency($invoice['total_amount']); ?></p>
                        </div>
                        <div class="text-xs text-gray-500 mb-3">
                            <span>Due: <?php echo format_date($invoice['due_date']); ?></span>
                        </div>
                        <div class="flex space-x-2">
                            <a href="invoice-details.php?id=<?php echo $invoice['id']; ?>" class="flex-1 text-center bg-blue-600 text-white text-xs py-2 px-3 rounded hover:bg-blue-700">View Details</a>
                            <a href="invoices.php?action=download&id=<?php echo $invoice['id']; ?>" class="flex-1 text-center bg-gray-200 text-gray-800 text-xs py-2 px-3 rounded hover:bg-gray-300">Download</a>
                            <button onclick="showInvoiceActions(<?php echo $invoice['id']; ?>)" class="flex-shrink-0 bg-gray-200 text-gray-800 text-xs py-2 px-3 rounded hover:bg-gray-300">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                </svg>
                            </button>
                        </div>
                        <!-- Mobile Actions Dropdown -->
                        <div id="invoice-actions-<?php echo $invoice['id']; ?>" class="hidden mt-2 bg-gray-100 rounded p-2">
                            <a href="invoices.php?action=share&id=<?php echo $invoice['id']; ?>" class="block text-sm text-gray-700 py-1">Share Invoice</a>
                            <?php if ($invoice['status'] !== 'paid'): ?>
                            <a href="checkout.php?invoice_id=<?php echo $invoice['id']; ?>" class="block text-sm text-gray-700 py-1">Pay Now</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Desktop View (Table) -->
                <div class="hidden md:block">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">#<?php echo $invoice['invoice_number']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $invoice['service_name']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500"><?php echo format_date($invoice['invoice_date']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500"><?php echo format_date($invoice['due_date']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo format_currency($invoice['total_amount']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo get_status_badge($invoice['status']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="relative">
                                        <button onclick="toggleDropdown(<?php echo $invoice['id']; ?>)" class="text-gray-600 hover:text-gray-900 focus:outline-none">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                            </svg>
                                        </button>
                                        <div id="dropdown-<?php echo $invoice['id']; ?>" class="hidden absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                                            <div class="py-1" role="menu" aria-orientation="vertical">
                                                <a href="invoice-details.php?id=<?php echo $invoice['id']; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">View Details</a>
                                                <a href="invoices.php?action=download&id=<?php echo $invoice['id']; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Download PDF</a>
                                                <a href="invoices.php?action=share&id=<?php echo $invoice['id']; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Share Invoice</a>
                                                <?php if ($invoice['status'] !== 'paid'): ?>
                                                <a href="checkout.php?invoice_id=<?php echo $invoice['id']; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Pay Now</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for dropdowns and share link functionality -->
<script>
// Toggle dropdowns for desktop view
function toggleDropdown(id) {
    const dropdown = document.getElementById(`dropdown-${id}`);
    
    // Close all other dropdowns first
    document.querySelectorAll('[id^="dropdown-"]').forEach(el => {
        if (el.id !== `dropdown-${id}`) {
            el.classList.add('hidden');
        }
    });
    
    dropdown.classList.toggle('hidden');
    
    // Close when clicking outside
    document.addEventListener('click', function closeDropdown(e) {
        if (!e.target.closest(`button[onclick="toggleDropdown(${id})"]`) && 
            !e.target.closest(`#dropdown-${id}`)) {
            dropdown.classList.add('hidden');
            document.removeEventListener('click', closeDropdown);
        }
    });
}

// Show/hide mobile invoice actions
function showInvoiceActions(id) {
    const actionsMenu = document.getElementById(`invoice-actions-${id}`);
    
    // Close all other action menus first
    document.querySelectorAll('[id^="invoice-actions-"]').forEach(el => {
        if (el.id !== `invoice-actions-${id}`) {
            el.classList.add('hidden');
        }
    });
    
    actionsMenu.classList.toggle('hidden');
    
    // Close when clicking outside
    document.addEventListener('click', function closeActions(e) {
        if (!e.target.closest(`button[onclick="showInvoiceActions(${id})"]`) && 
            !e.target.closest(`#invoice-actions-${id}`)) {
            actionsMenu.classList.add('hidden');
            document.removeEventListener('click', closeActions);
        }
    });
}

// Copy share link to clipboard
function copyShareLink(link) {
    navigator.clipboard.writeText(link).then(() => {
        // Show success message
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = "Copied!";
        setTimeout(() => {
            btn.textContent = originalText;
        }, 2000);
    }).catch(err => {
        console.error('Could not copy text: ', err);
    });
}
</script>

<?php
// Include footer
include 'templates/footer.php';
?>