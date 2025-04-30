<?php
/**
 * Customer Controller
 * 
 * Handles all customer-related operations including profile management,
 * support tickets, and customer-specific views
 */
class CustomerController
{
    private $customerModel;
    private $orderModel;
    private $invoiceModel;
    private $serviceModel;
    private $authService;
    private $notificationService;
    
    /**
     * Constructor - initialize models and services
     */
    public function __construct()
    {
        $this->customerModel = new Customer();
        $this->orderModel = new Order();
        $this->invoiceModel = new Invoice();
        $this->serviceModel = new Service();
        $this->authService = new AuthService();
        $this->notificationService = new NotificationService();
    }
    
    /**
     * Display customer dashboard
     */
    public function dashboard()
    {
        // Verify user is authenticated and has customer role
        if (!$this->authService->isAuthenticated() || !$this->authService->hasRole('customer')) {
            redirect('auth/login');
        }
        
        $userId = $_SESSION['user_id'];
        $customerData = $this->customerModel->getByUserId($userId);
        
        if (!$customerData) {
            setFlashMessage('error', 'Customer profile not found.');
            redirect('auth/login');
        }
        
        // Get recent orders, invoices, and active services
        $recentOrders = $this->orderModel->getRecentByCustomerId($customerData['id'], 5);
        $pendingInvoices = $this->invoiceModel->getPendingByCustomerId($customerData['id']);
        $activeServices = $this->serviceModel->getActiveByCustomerId($customerData['id']);
        
        // Load customer dashboard view
        view('dashboard/customer', [
            'customer' => $customerData,
            'recentOrders' => $recentOrders,
            'pendingInvoices' => $pendingInvoices,
            'activeServices' => $activeServices
        ]);
    }
    
    /**
     * Display and update customer profile
     */
    public function profile()
    {
        // Verify user is authenticated and has customer role
        if (!$this->authService->isAuthenticated() || !$this->authService->hasRole('customer')) {
            redirect('auth/login');
        }
        
        $userId = $_SESSION['user_id'];
        $customerData = $this->customerModel->getByUserId($userId);
        
        if (!$customerData) {
            setFlashMessage('error', 'Customer profile not found.');
            redirect('dashboard');
        }
        
        // Handle profile update if form submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate form data
            $validation = validateProfileData($_POST);
            
            if ($validation['valid']) {
                // Update profile
                $updateData = [
                    'name' => sanitizeInput($_POST['name']),
                    'email' => sanitizeInput($_POST['email']),
                    'phone' => sanitizeInput($_POST['phone']),
                    'address' => sanitizeInput($_POST['address']),
                    'city' => sanitizeInput($_POST['city']),
                    'state' => sanitizeInput($_POST['state']),
                    'zip_code' => sanitizeInput($_POST['zip_code']),
                    'country' => sanitizeInput($_POST['country'])
                ];
                
                $updated = $this->customerModel->update($customerData['id'], $updateData);
                
                if ($updated) {
                    // If email changed, update user email as well
                    if ($updateData['email'] !== $customerData['email']) {
                        $this->authService->updateEmail($userId, $updateData['email']);
                    }
                    
                    setFlashMessage('success', 'Profile updated successfully.');
                    redirect('customer/profile');
                } else {
                    setFlashMessage('error', 'Failed to update profile. Please try again.');
                }
            } else {
                // Show validation errors
                setFormErrors($validation['errors']);
            }
        }
        
        // Load profile view
        view('customers/profile', [
            'customer' => $customerData
        ]);
    }
    
    /**
     * Display customer support page and handle ticket submission
     */
    public function support()
    {
        // Verify user is authenticated and has customer role
        if (!$this->authService->isAuthenticated() || !$this->authService->hasRole('customer')) {
            redirect('auth/login');
        }
        
        $userId = $_SESSION['user_id'];
        $customerData = $this->customerModel->getByUserId($userId);
        
        if (!$customerData) {
            setFlashMessage('error', 'Customer profile not found.');
            redirect('dashboard');
        }
        
        // Handle ticket submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate form data
            $validation = validateTicketData($_POST);
            
            if ($validation['valid']) {
                // Create support ticket
                $ticketData = [
                    'customer_id' => $customerData['id'],
                    'subject' => sanitizeInput($_POST['subject']),
                    'message' => sanitizeInput($_POST['message']),
                    'priority' => sanitizeInput($_POST['priority']),
                    'status' => 'open',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $ticketId = $this->customerModel->createSupportTicket($ticketData);
                
                if ($ticketId) {
                    // Notify admin about new ticket
                    $this->notificationService->notifyAdmins('new_support_ticket', [
                        'ticket_id' => $ticketId,
                        'customer_name' => $customerData['name'],
                        'subject' => $ticketData['subject']
                    ]);
                    
                    setFlashMessage('success', 'Support ticket submitted successfully.');
                    redirect('customer/tickets');
                } else {
                    setFlashMessage('error', 'Failed to submit support ticket. Please try again.');
                }
            } else {
                // Show validation errors
                setFormErrors($validation['errors']);
            }
        }
        
        // Load support view
        view('customers/support', [
            'customer' => $customerData
        ]);
    }
    
    /**
     * Display customer support tickets
     */
    public function tickets()
    {
        // Verify user is authenticated and has customer role
        if (!$this->authService->isAuthenticated() || !$this->authService->hasRole('customer')) {
            redirect('auth/login');
        }
        
        $userId = $_SESSION['user_id'];
        $customerData = $this->customerModel->getByUserId($userId);
        
        if (!$customerData) {
            setFlashMessage('error', 'Customer profile not found.');
            redirect('dashboard');
        }
        
        // Get customer's support tickets
        $tickets = $this->customerModel->getSupportTickets($customerData['id']);
        
        // Load tickets view
        view('customers/tickets', [
            'customer' => $customerData,
            'tickets' => $tickets
        ]);
    }
    
    /**
     * View ticket details and handle replies
     */
    public function ticketDetails($ticketId)
    {
        // Verify user is authenticated and has customer role
        if (!$this->authService->isAuthenticated() || !$this->authService->hasRole('customer')) {
            redirect('auth/login');
        }
        
        $userId = $_SESSION['user_id'];
        $customerData = $this->customerModel->getByUserId($userId);
        
        if (!$customerData) {
            setFlashMessage('error', 'Customer profile not found.');
            redirect('dashboard');
        }
        
        // Get ticket details
        $ticket = $this->customerModel->getTicketById($ticketId);
        
        // Verify ticket belongs to this customer
        if (!$ticket || $ticket['customer_id'] !== $customerData['id']) {
            setFlashMessage('error', 'Ticket not found or access denied.');
            redirect('customer/tickets');
        }
        
        // Handle ticket reply
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate form data
            $validation = validateTicketReplyData($_POST);
            
            if ($validation['valid']) {
                // Add reply to ticket
                $replyData = [
                    'ticket_id' => $ticketId,
                    'user_id' => $userId,
                    'message' => sanitizeInput($_POST['message']),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $replyAdded = $this->customerModel->addTicketReply($replyData);
                
                if ($replyAdded) {
                    // Update ticket status to customer-reply
                    $this->customerModel->updateTicketStatus($ticketId, 'customer-reply');
                    
                    // Notify admin about ticket reply
                    $this->notificationService->notifyAdmins('ticket_reply', [
                        'ticket_id' => $ticketId,
                        'customer_name' => $customerData['name'],
                        'subject' => $ticket['subject']
                    ]);
                    
                    setFlashMessage('success', 'Reply submitted successfully.');
                    redirect('customer/ticket/' . $ticketId);
                } else {
                    setFlashMessage('error', 'Failed to submit reply. Please try again.');
                }
            } else {
                // Show validation errors
                setFormErrors($validation['errors']);
            }
        }
        
        // Get ticket replies
        $replies = $this->customerModel->getTicketReplies($ticketId);
        
        // Load ticket details view
        view('customers/ticket-details', [
            'customer' => $customerData,
            'ticket' => $ticket,
            'replies' => $replies
        ]);
    }
    
    /**
     * Close a support ticket
     */
    public function closeTicket($ticketId)
    {
        // Verify user is authenticated and has customer role
        if (!$this->authService->isAuthenticated() || !$this->authService->hasRole('customer')) {
            redirect('auth/login');
        }
        
        $userId = $_SESSION['user_id'];
        $customerData = $this->customerModel->getByUserId($userId);
        
        if (!$customerData) {
            setFlashMessage('error', 'Customer profile not found.');
            redirect('dashboard');
        }
        
        // Get ticket details
        $ticket = $this->customerModel->getTicketById($ticketId);
        
        // Verify ticket belongs to this customer
        if (!$ticket || $ticket['customer_id'] !== $customerData['id']) {
            setFlashMessage('error', 'Ticket not found or access denied.');
            redirect('customer/tickets');
        }
        
        // Close the ticket
        $closed = $this->customerModel->updateTicketStatus($ticketId, 'closed');
        
        if ($closed) {
            setFlashMessage('success', 'Ticket closed successfully.');
        } else {
            setFlashMessage('error', 'Failed to close ticket. Please try again.');
        }
        
        redirect('customer/tickets');
    }
}