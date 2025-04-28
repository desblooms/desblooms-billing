 
<?php
/**
 * Outstanding Invoices Page
 * 
 * Displays and manages overdue/unpaid invoices
 * Features:
 * - List of overdue invoices
 * - Payment options
 * - Late payment penalties display
 * - Urgency indicators
 */

// Start session and include required files
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/invoice-functions.php';
require_once 'includes/payment-functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Redirect to login page with return URL
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get current user ID
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Process payment if form submitted
$payment_message = '';
$payment_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_invoice'])) {
    $invoice_id = filter_input(INPUT_POST, 'invoice_id', FILTER_SANITIZE_NUMBER_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    $payment_amount = filter_input(INPUT_POST, 'payment_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    
    // Validate payment
    if (!$invoice_id || !$payment_method || !$payment_amount) {
        $payment_status = 'error';
        $payment_message = 'All payment fields are required';
    } else {
        // Process the payment (this would connect to payment gateway in production)
        $result = processPayment($invoice_id, $user_id, $payment_method, $payment_amount);
        
        if ($result['success']) {
            $payment_status = 'success';
            $payment_message = 'Payment processed successfully';
            
            // Update invoice status
            updateInvoicePaymentStatus($invoice_id, $payment_amount);
        } else {
            $payment_status = 'error';
            $payment_message = $result['message'];
        }
    }
}

// Get all outstanding invoices for the user
$outstanding_invoices = getOutstandingInvoices($user_id);

// Calculate total outstanding amount
$total_outstanding = 0;
foreach ($outstanding_invoices as $invoice) {
    $total_outstanding += ($invoice['total_amount'] - $invoice['paid_amount']);
}

// Page title
$page_title = "Outstanding Invoices";

// Include header
include 'templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex flex-wrap items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-red-600 md:text-3xl">Outstanding Invoices</h1>
        <div class="text-sm breadcrumbs">
            <ul class="flex flex-wrap">
                <li><a href="index.php" class="text-gray-600 hover:text-primary">Home</a></li>
                <li><span class="mx-2">/</span></li>
                <li class="text-red-600 font-medium">Outstanding</li>
            </ul>
        </div>
    </div>

    <?php if ($payment_message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $payment_status === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
        <?php echo $payment_message; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="p-4 bg-red-50 border-b border-red-100">
            <div class="flex flex-wrap items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">Overdue Payments</h2>
                <div class="flex items-center">
                    <span class="text-sm font-medium text-gray-600 mr-2">Total Outstanding:</span>
                    <span class="text-xl font-bold text-red-600"><?php echo formatCurrency($total_outstanding); ?></span>
                </div>
            </div>
        </div>

        <?php if (empty($outstanding_invoices)): ?>
        <div class="p-6 text-center">
            <div class="mb-4">
                <svg class="w-16 h-16 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-600">No Outstanding Invoices</h3>
            <p class="mt-2 text-gray-500">You don't have any overdue payments at this time.</p>
            <a href="services.php" class="mt-4 inline-block px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">Browse Services</a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Late</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($outstanding_invoices as $invoice): ?>
                    <?php
                        // Calculate days overdue
                        $due_date = new DateTime($invoice['due_date']);
                        $today = new DateTime('now');
                        $days_late = $today->diff($due_date)->days;
                        
                        // Calculate remaining amount
                        $remaining = $invoice['total_amount'] - $invoice['paid_amount'];
                        
                        // Determine urgency class based on days late
                        $urgency_class = '';
                        if ($days_late > 30) {
                            $urgency_class = 'bg-red-50';
                        } elseif ($days_late > 15) {
                            $urgency_class = 'bg-orange-50';
                        } elseif ($days_late > 7) {
                            $urgency_class = 'bg-yellow-50';
                        }
                    ?>
                    <tr class="<?php echo $urgency_class; ?>">
                        <td class="px-4 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="text-sm font-medium text-gray-900">
                                    <a href="invoice-details.php?id=<?php echo $invoice['id']; ?>" class="hover:text-primary">
                                        #<?php echo $invoice['invoice_number']; ?>
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($invoice['service_name']); ?></div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo formatDate($invoice['due_date']); ?></div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?php echo formatCurrency($invoice['total_amount']); ?></div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-500"><?php echo formatCurrency($invoice['paid_amount']); ?></div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <div class="text-sm font-bold text-red-600"><?php echo formatCurrency($remaining); ?></div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php 
                                if ($days_late > 30) echo 'bg-red-100 text-red-800';
                                elseif ($days_late > 15) echo 'bg-orange-100 text-orange-800';
                                elseif ($days_late > 7) echo 'bg-yellow-100 text-yellow-800';
                                else echo 'bg-gray-100 text-gray-800';
                                ?>">
                                <?php echo $days_late; ?> days
                            </span>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                            <button type="button" 
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                                onclick="showPaymentModal('<?php echo $invoice['id']; ?>', '<?php echo $invoice['invoice_number']; ?>', <?php echo $remaining; ?>)">
                                Pay Now
                            </button>
                            
                            <a href="invoice-details.php?id=<?php echo $invoice['id']; ?>" 
                                class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="p-4 bg-gray-50 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Important Information</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-4 border border-gray-200 rounded-lg">
                    <h3 class="text-md font-semibold text-gray-700 mb-2">
                        <svg class="w-5 h-5 inline-block mr-1 text-yellow-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        Late Payment Penalties
                    </h3>
                    <p class="text-sm text-gray-600">
                        Please note that overdue invoices may incur the following penalties:
                    </p>
                    <ul class="list-disc ml-5 mt-2 text-sm text-gray-600">
                        <li>1-7 days late: 2% of remaining balance</li>
                        <li>8-15 days late: 5% of remaining balance</li>
                        <li>16-30 days late: 10% of remaining balance</li>
                        <li>Over 30 days late: Service suspension may apply</li>
                    </ul>
                </div>
                <div class="p-4 border border-gray-200 rounded-lg">
                    <h3 class="text-md font-semibold text-gray-700 mb-2">
                        <svg class="w-5 h-5 inline-block mr-1 text-blue-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        Need Help?
                    </h3>
                    <p class="text-sm text-gray-600 mb-2">
                        If you're experiencing difficulties with payment or need a payment plan, please contact our support team.
                    </p>
                    <a href="support.php" class="text-sm text-primary hover:text-primary-dark font-medium">
                        Contact Support →
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-2" id="modal-title">Make Payment</h3>
            <div class="mt-2 px-7 py-3">
                <form id="paymentForm" method="POST" action="">
                    <input type="hidden" id="invoice_id" name="invoice_id" value="">
                    
                    <div class="mb-4 text-left">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="invoice_number">
                            Invoice
                        </label>
                        <input type="text" id="invoice_number" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" disabled>
                    </div>
                    
                    <div class="mb-4 text-left">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="payment_amount">
                            Amount Due
                        </label>
                        <input type="text" id="display_amount" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" disabled>
                        <input type="hidden" id="payment_amount" name="payment_amount" value="">
                    </div>
                    
                    <div class="mb-4 text-left">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="payment_method">
                            Payment Method
                        </label>
                        <select id="payment_method" name="payment_method" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="credit_card">Credit Card</option>
                            <option value="paypal">PayPal</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <?php if (hasWallet($user_id)): ?>
                            <option value="wallet">Wallet Balance</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="items-center justify-center mt-4 flex space-x-4">
                        <button type="button" 
                            class="px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300"
                            onclick="closePaymentModal()">
                            Cancel
                        </button>
                        <button type="submit" 
                            name="pay_invoice"
                            class="px-4 py-2 bg-primary text-white text-base font-medium rounded-md shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary">
                            Pay Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Payment Modal Functions
    function showPaymentModal(invoiceId, invoiceNumber, amount) {
        document.getElementById('invoice_id').value = invoiceId;
        document.getElementById('invoice_number').value = '#' + invoiceNumber;
        document.getElementById('payment_amount').value = amount;
        document.getElementById('display_amount').value = formatCurrency(amount);
        document.getElementById('paymentModal').classList.remove('hidden');
    }
    
    function closePaymentModal() {
        document.getElementById('paymentModal').classList.add('hidden');
    }
    
    function formatCurrency(amount) {
        return '$' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById('paymentModal');
        if (event.target === modal) {
            closePaymentModal();
        }
    }
</script>

<?php
// Include footer
include 'templates/footer.php';
?>