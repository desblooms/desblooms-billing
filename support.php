 
<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Redirect to login page
    header('Location: login.php?redirect=support.php');
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Initialize variables
$success_message = '';
$error_message = '';

// Handle ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $subject = trim($_POST['subject']);
    $category = trim($_POST['category']);
    $message = trim($_POST['message']);
    $priority = trim($_POST['priority']);
    
    // Basic validation
    if (empty($subject) || empty($message) || empty($category)) {
        $error_message = 'Please fill all required fields.';
    } else {
        // Process file attachment if present
        $attachment_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($_FILES['attachment']['type'], $allowed_types)) {
                $error_message = 'Invalid file type. Allowed types: JPG, PNG, GIF, PDF, TXT.';
            } elseif ($_FILES['attachment']['size'] > $max_size) {
                $error_message = 'File too large. Maximum size is 5MB.';
            } else {
                $upload_dir = 'uploads/tickets/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $filename = time() . '_' . $_FILES['attachment']['name'];
                $attachment_path = $upload_dir . $filename;
                
                if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $attachment_path)) {
                    $error_message = 'Failed to upload file. Please try again.';
                    $attachment_path = null;
                }
            }
        }
        
        if (empty($error_message)) {
            // Generate ticket number
            $ticket_number = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(md5(time() . $user_id), 0, 6));
            
            // Insert ticket into database
            $ticket_id = createSupportTicket($user_id, $ticket_number, $subject, $category, $message, $priority, $attachment_path);
            
            if ($ticket_id) {
                // Send confirmation email to user
                $user_email = $user['email'];
                $user_name = $user['first_name'] . ' ' . $user['last_name'];
                sendTicketConfirmationEmail($user_email, $user_name, $ticket_number, $subject);
                
                // Notify admin about new ticket
                notifyAdminNewTicket($ticket_number, $subject, $user_name);
                
                $success_message = 'Your support ticket has been submitted successfully. Ticket Number: ' . $ticket_number;
                
                // Clear form data
                $subject = $message = $category = $priority = '';
            } else {
                $error_message = 'Failed to create ticket. Please try again.';
            }
        }
    }
}

// Get user's tickets
$tickets = getUserTickets($user_id);

// Include header template
$page_title = 'Support Center';
require_once 'templates/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Support Center</h1>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h2 class="text-lg font-semibold mb-4">Quick Links</h2>
                <ul class="space-y-2">
                    <li><a href="#faq" class="text-blue-600 hover:text-blue-800">Frequently Asked Questions</a></li>
                    <li><a href="#new-ticket" class="text-blue-600 hover:text-blue-800">Submit a New Ticket</a></li>
                    <li><a href="#my-tickets" class="text-blue-600 hover:text-blue-800">My Tickets</a></li>
                </ul>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-4">
                <h2 class="text-lg font-semibold mb-4">Contact Information</h2>
                <div class="space-y-3">
                    <p>
                        <span class="font-semibold">Email:</span><br>
                        <a href="mailto:support@example.com" class="text-blue-600 hover:text-blue-800">support@example.com</a>
                    </p>
                    <p>
                        <span class="font-semibold">Phone:</span><br>
                        <a href="tel:+1234567890" class="text-blue-600 hover:text-blue-800">+1 (234) 567-890</a>
                    </p>
                    <p>
                        <span class="font-semibold">Working Hours:</span><br>
                        Monday - Friday: 9:00 AM - 6:00 PM<br>
                        Saturday: 10:00 AM - 2:00 PM<br>
                        Sunday: Closed
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Main content -->
        <div class="lg:col-span-2">
            <!-- Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo $success_message; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>
            
            <!-- FAQ Section -->
            <div id="faq" class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Frequently Asked Questions</h2>
                
                <div class="space-y-4">
                    <div class="border-b pb-4">
                        <button class="flex justify-between items-center w-full text-left font-medium faq-toggle" data-target="faq-1">
                            <span>How do I pay my invoice?</span>
                            <svg class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="faq-1" class="mt-2 hidden">
                            <p class="text-gray-700">
                                You can pay your invoice by logging into your account, navigating to the Invoices section,
                                and selecting the invoice you want to pay. We accept various payment methods including
                                credit/debit cards, net banking, and digital wallets.
                            </p>
                        </div>
                    </div>
                    
                    <div class="border-b pb-4">
                        <button class="flex justify-between items-center w-full text-left font-medium faq-toggle" data-target="faq-2">
                            <span>What happens if I miss a payment?</span>
                            <svg class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="faq-2" class="mt-2 hidden">
                            <p class="text-gray-700">
                                If you miss a payment, your invoice will move to the Outstanding section. Depending on your
                                service agreement, late fees may apply. After a certain period of non-payment, services may
                                be temporarily suspended until payment is received.
                            </p>
                        </div>
                    </div>
                    
                    <div class="border-b pb-4">
                        <button class="flex justify-between items-center w-full text-left font-medium faq-toggle" data-target="faq-3">
                            <span>How do I download my invoice?</span>
                            <svg class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="faq-3" class="mt-2 hidden">
                            <p class="text-gray-700">
                                To download your invoice, go to the Invoices section, find the invoice you want to download,
                                and click on the Download PDF button. You can also share the invoice directly via email by
                                using the Share option next to each invoice.
                            </p>
                        </div>
                    </div>
                    
                    <div class="border-b pb-4">
                        <button class="flex justify-between items-center w-full text-left font-medium faq-toggle" data-target="faq-4">
                            <span>How do I update my payment information?</span>
                            <svg class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="faq-4" class="mt-2 hidden">
                            <p class="text-gray-700">
                                You can update your payment information by going to your Profile settings and selecting
                                the Payment Methods tab. From there, you can add new payment methods or update existing ones.
                            </p>
                        </div>
                    </div>
                    
                    <div class="pb-2">
                        <button class="flex justify-between items-center w-full text-left font-medium faq-toggle" data-target="faq-5">
                            <span>How do I subscribe to a service?</span>
                            <svg class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="faq-5" class="mt-2 hidden">
                            <p class="text-gray-700">
                                To subscribe to a service, browse our Services section, select the service you're interested in,
                                choose your preferred plan, and click on Subscribe. You'll be guided through the checkout process
                                where you can review the details and confirm your subscription.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Submit Ticket Form -->
            <div id="new-ticket" class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Submit a New Support Ticket</h2>
                
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                        <input type="text" id="subject" name="subject" value="<?php echo isset($subject) ? htmlspecialchars($subject) : ''; ?>" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <select id="category" name="category" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select a category</option>
                            <option value="billing" <?php echo (isset($category) && $category === 'billing') ? 'selected' : ''; ?>>Billing Issue</option>
                            <option value="technical" <?php echo (isset($category) && $category === 'technical') ? 'selected' : ''; ?>>Technical Support</option>
                            <option value="account" <?php echo (isset($category) && $category === 'account') ? 'selected' : ''; ?>>Account Management</option>
                            <option value="service" <?php echo (isset($category) && $category === 'service') ? 'selected' : ''; ?>>Service Inquiry</option>
                            <option value="other" <?php echo (isset($category) && $category === 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select id="priority" name="priority"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="low" <?php echo (isset($priority) && $priority === 'low') ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo (!isset($priority) || $priority === 'medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo (isset($priority) && $priority === 'high') ? 'selected' : ''; ?>>High</option>
                            <option value="critical" <?php echo (isset($priority) && $priority === 'critical') ? 'selected' : ''; ?>>Critical</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
                        <textarea id="message" name="message" rows="5" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>
                    </div>
                    
                    <div>
                        <label for="attachment" class="block text-sm font-medium text-gray-700 mb-1">Attachment (Optional)</label>
                        <input type="file" id="attachment" name="attachment"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Max file size: 5MB. Allowed file types: JPG, PNG, GIF, PDF, TXT</p>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="email_updates" name="email_updates" checked
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="email_updates" class="ml-2 block text-sm text-gray-700">
                            Send me email updates regarding this ticket
                        </label>
                    </div>
                    
                    <div>
                        <button type="submit" name="submit_ticket"
                                class="w-full md:w-auto px-6 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Submit Ticket
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- My Tickets -->
            <div id="my-tickets" class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">My Support Tickets</h2>
                
                <?php if (empty($tickets)): ?>
                    <div class="bg-gray-50 p-4 rounded-md text-center">
                        <p class="text-gray-600">You haven't submitted any support tickets yet.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ticket #
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Subject
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($ticket['subject']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_class = '';
                                            switch ($ticket['status']) {
                                                case 'open':
                                                    $status_class = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'in-progress':
                                                    $status_class = 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'on-hold':
                                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'closed':
                                                    $status_class = 'bg-gray-100 text-gray-800';
                                                    break;
                                                default:
                                                    $status_class = 'bg-gray-100 text-gray-800';
                                            }
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                                <?php echo ucfirst(htmlspecialchars($ticket['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <a href="ticket-details.php?ticket_id=<?php echo $ticket['id']; ?>" class="text-blue-600 hover:text-blue-800">
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
        </div>
    </div>
</div>

<!-- Live Chat Widget Container -->
<div id="live-chat-container" class="fixed bottom-4 right-4 z-50"></div>

<script>
    // Toggle FAQ answers
    document.querySelectorAll('.faq-toggle').forEach(button => {
        button.addEventListener('click', () => {
            const target = document.getElementById(button.dataset.target);
            const icon = button.querySelector('svg');
            
            if (target.classList.contains('hidden')) {
                target.classList.remove('hidden');
                icon.classList.add('rotate-180');
            } else {
                target.classList.add('hidden');
                icon.classList.remove('rotate-180');
            }
        });
    });
    
    // Initialize live chat (example with Tawk.to)
    // You would replace this with your actual chat widget code
    document.addEventListener('DOMContentLoaded', function() {
        // Example: Tawk.to widget initialization code
        /*
        var s1 = document.createElement("script");
        s1.async = true;
        s1.src = 'https://embed.tawk.to/YOUR_TAWK_ID/default';
        s1.charset = 'UTF-8';
        s1.setAttribute('crossorigin', '*');
        document.head.appendChild(s1);
        */
        
        // For this example, we'll just add a placeholder
        const chatContainer = document.getElementById('live-chat-container');
        const chatButton = document.createElement('button');
        chatButton.innerHTML = `
            <div class="bg-blue-600 text-white rounded-full p-3 shadow-lg hover:bg-blue-700 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
            </div>
        `;
        chatButton.onclick = function() {
            alert('Chat functionality would open here.');
        };
        chatContainer.appendChild(chatButton);
    });
</script>

<?php
// Include necessary functions for support tickets
// These functions should be defined in includes/functions.php or a separate file

/**
 * Create a new support ticket
 *
 * @param int $user_id User ID
 * @param string $ticket_number Generated ticket number
 * @param string $subject Ticket subject
 * @param string $category Ticket category
 * @param string $message Ticket message
 * @param string $priority Ticket priority
 * @param string|null $attachment_path Path to uploaded attachment
 * @return int|false The ticket ID if successful, false otherwise
 */
function createSupportTicket($user_id, $ticket_number, $subject, $category, $message, $priority, $attachment_path = null) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, ticket_number, subject, category, message, priority, attachment, status, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW())");
        
        $stmt->bind_param("issssss", $user_id, $ticket_number, $subject, $category, $message, $priority, $attachment_path);
        
        if ($stmt->execute()) {
            return $stmt->insert_id;
        } else {
            return false;
        }
    } catch (Exception $e) {
        // Log error
        error_log('Error creating support ticket: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all tickets for a user
 *
 * @param int $user_id User ID
 * @return array Array of tickets
 */
function getUserTickets($user_id) {
    global $conn;
    $tickets = [];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }
    } catch (Exception $e) {
        // Log error
        error_log('Error fetching user tickets: ' . $e->getMessage());
    }
    
    return $tickets;
}

/**
 * Send ticket confirmation email to user
 *
 * @param string $email User email
 * @param string $name User name
 * @param string $ticket_number Ticket number
 * @param string $subject Ticket subject
 * @return bool Whether the email was sent successfully
 */
function sendTicketConfirmationEmail($email, $name, $ticket_number, $subject) {
    // In a real application, this would use a proper email library or service
    $to = $email;
    $subject = "Support Ticket Confirmation: $ticket_number";
    
    $message = "
    <html>
    <head>
        <title>Support Ticket Confirmation</title>
    </head>
    <body>
        <h2>Support Ticket Confirmation</h2>
        <p>Dear $name,</p>
        <p>Thank you for submitting a support ticket. Our team will review your request and respond as soon as possible.</p>
        <p><strong>Ticket Number:</strong> $ticket_number</p>
        <p><strong>Subject:</strong> $subject</p>
        <p>You can view the status of your ticket by logging into your account and visiting the Support section.</p>
        <p>Thank you for your patience.</p>
        <p>Best regards,<br>Support Team</p>
    </body>
    </html>
    ";
    
    // Headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: support@example.com" . "\r\n";
    
    // For demonstration purposes, we'll just return true
    // In a real application, you would use mail() or a library like PHPMailer
    
    return true;
}

// Include footer template
require_once 'templates/footer.php';
