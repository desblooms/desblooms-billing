 
<?php
/**
 * Pending Invoices Page
 * 
 * Displays all pending invoices (generated but not yet due) for the logged-in user
 * Includes early payment options and due date information
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and functions
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/invoice-functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Redirect to login page with return URL
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// Fetch pending invoices for the current user
$pendingInvoices = getPendingInvoices($userId);

// Process early payment if requested
if (isset($_POST['pay_early']) && isset($_POST['invoice_id'])) {
    $invoiceId = clean($_POST['invoice_id']);
    // Redirect to checkout page with invoice ID
    header('Location: checkout.php?invoice_id=' . $invoiceId . '&early_payment=1');
    exit;
}

// Set page title
$pageTitle = "Pending Invoices";

// Include header
include 'templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6">Pending Invoices</h1>
    
    <?php if (empty($pendingInvoices)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <p class="text-gray-600">You don't have any pending invoices at the moment.</p>
            <a href="services.php" class="inline-block mt-4 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">Browse Services</a>
        </div>
    <?php else: ?>
        <div class="grid gap-6">
            <?php foreach ($pendingInvoices as $invoice): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-6">
                        <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                </h3>
                                <p class="text-gray-600 mt-1">
                                    <?php echo htmlspecialchars($invoice['service_name']); ?>
                                </p>
                            </div>
                            <div class="mt-4 md:mt-0">
                                <span class="inline-block px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                                    Pending
                                </span>
                            </div>
                        </div>
                        
                        <div class="mt-6 border-t border-gray-200 pt-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500">Amount</p>
                                    <p class="font-semibold text-gray-800">
                                        <?php echo formatCurrency($invoice['total_amount']); ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Issue Date</p>
                                    <p class="font-semibold text-gray-800">
                                        <?php echo formatDate($invoice['issue_date']); ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Due Date</p>
                                    <p class="font-semibold text-gray-800">
                                        <?php echo formatDate($invoice['due_date']); ?>
                                    </p>
                                    <?php
                                        $daysUntilDue = getDaysUntilDue($invoice['due_date']);
                                        $badgeClass = $daysUntilDue < 3 ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800';
                                    ?>
                                    <span class="inline-block mt-1 px-2 py-0.5 <?php echo $badgeClass; ?> rounded-full text-xs">
                                        <?php echo $daysUntilDue > 0 ? $daysUntilDue . ' days left' : 'Due today'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex flex-col sm:flex-row gap-3">
                            <a href="invoices.php?id=<?php echo $invoice['id']; ?>" class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                View Details
                            </a>
                            <form method="post" class="flex-1">
                                <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                                <button type="submit" name="pay_early" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Pay Now
                                </button>
                            </form>
                            <a href="invoices.php?id=<?php echo $invoice['id']; ?>&download=1" class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Download PDF
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination (if needed) -->
        <?php if (isset($totalPages) && $totalPages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="inline-flex rounded-md shadow">
                    <!-- Pagination code here -->
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Include footer
include 'templates/footer.php';
?>