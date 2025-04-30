<?php
/**
 * BillingController
 * Handles all billing-related operations including invoices, payments, and subscriptions
 */
namespace App\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Service;
use App\Services\BillingService;
use App\Services\PaymentGatewayService;
use App\Services\NotificationService;
use App\Helpers\ValidationHelper;
use App\Helpers\DateHelper;

class BillingController {
    private $billingService;
    private $paymentGatewayService;
    private $notificationService;
    private $validationHelper;

    /**
     * Constructor
     */
    public function __construct() {
        $this->billingService = new BillingService();
        $this->paymentGatewayService = new PaymentGatewayService();
        $this->notificationService = new NotificationService();
        $this->validationHelper = new ValidationHelper();
    }

    /**
     * Display invoices list
     * 
     * @return void
     */
    public function invoices() {
        // Check authentication
        if (!isAuthenticated()) {
            redirect('/auth/login');
        }

        $userId = getCurrentUserId();
        $userRole = getCurrentUserRole();
        $filterOptions = [];
        
        // Handle query parameters for filtering
        if (isset($_GET['status'])) {
            $filterOptions['status'] = $this->validationHelper->sanitize($_GET['status']);
        }
        
        if (isset($_GET['date_from'])) {
            $filterOptions['date_from'] = $this->validationHelper->sanitize($_GET['date_from']);
        }
        
        if (isset($_GET['date_to'])) {
            $filterOptions['date_to'] = $this->validationHelper->sanitize($_GET['date_to']);
        }

        // Get invoices based on user role
        if ($userRole === 'admin') {
            $invoices = $this->billingService->getAllInvoices($filterOptions);
        } elseif ($userRole === 'business') {
            $invoices = $this->billingService->getBusinessInvoices($userId, $filterOptions);
        } else {
            $invoices = $this->billingService->getCustomerInvoices($userId, $filterOptions);
        }

        // Load view
        require_once APP_ROOT . '/views/billing/invoices.php';
    }

    /**
     * Display invoice details
     * 
     * @param int $invoiceId
     * @return void
     */
    public function viewInvoice($invoiceId) {
        // Check authentication
        if (!isAuthenticated()) {
            redirect('/auth/login');
        }

        // Sanitize input
        $invoiceId = (int)$invoiceId;
        
        // Get invoice details
        $invoice = $this->billingService->getInvoiceById($invoiceId);
        
        // Check if invoice exists
        if (!$invoice) {
            setFlashMessage('error', 'Invoice not found');
            redirect('/billing/invoices');
        }
        
        // Check authorization based on user role
        $userId = getCurrentUserId();
        $userRole = getCurrentUserRole();
        
        if ($userRole === 'customer' && $invoice->customer_id !== $userId) {
            setFlashMessage('error', 'Unauthorized access');
            redirect('/billing/invoices');
        } elseif ($userRole === 'business' && $invoice->business_id !== $userId) {
            setFlashMessage('error', 'Unauthorized access');
            redirect('/billing/invoices');
        }
        
        // Get related data
        $payments = $this->billingService->getInvoicePayments($invoiceId);
        $order = $this->billingService->getInvoiceOrder($invoice->order_id);
        
        // Load view
        require_once APP_ROOT . '/views/billing/invoice-details.php';
    }

    /**
     * Generate a new invoice
     * 
     * @param int $orderId
     * @return void
     */
    public function generateInvoice($orderId = null) {
        // Check authentication and authorization
        if (!isAuthenticated() || !isAuthorized(['admin', 'business'])) {
            setFlashMessage('error', 'Unauthorized access');
            redirect('/auth/login');
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate input
            $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : null;
            $customerId = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
            $dueDate = isset($_POST['due_date']) ? $this->validationHelper->sanitize($_POST['due_date']) : null;
            
            if (!$orderId || !$customerId || !$dueDate) {
                setFlashMessage('error', 'Missing required fields');
                redirect('/billing/generate-invoice');
            }
            
            // Generate invoice
            $result = $this->billingService->createInvoice([
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'due_date' => $dueDate,
                'status' => 'pending',
                'created_by' => getCurrentUserId()
            ]);
            
            if ($result) {
                // Send notification
                $this->notificationService->sendInvoiceNotification($result);
                
                setFlashMessage('success', 'Invoice generated successfully');
                redirect('/billing/invoices');
            } else {
                setFlashMessage('error', 'Failed to generate invoice');
                redirect('/billing/generate-invoice');
            }
        }

        // Get data for the form
        $orderModel = new Order();
        
        if ($orderId) {
            $order = $orderModel->findById($orderId);
            $orders = [$order];
        } else {
            // Get orders without invoices
            $orders = $orderModel->findOrdersWithoutInvoices();
        }
        
        // Load view
        require_once APP_ROOT . '/views/billing/generate-invoice.php';
    }

    /**
     * Process payment for an invoice
     * 
     * @param int $invoiceId
     * @return void
     */
    public function processPayment($invoiceId) {
        // Check authentication
        if (!isAuthenticated()) {
            redirect('/auth/login');
        }

        // Sanitize input
        $invoiceId = (int)$invoiceId;
        
        // Get invoice details
        $invoice = $this->billingService->getInvoiceById($invoiceId);
        
        // Check if invoice exists and is pending
        if (!$invoice || $invoice->status !== 'pending') {
            setFlashMessage('error', 'Invalid invoice or already paid');
            redirect('/billing/invoices');
        }
        
        // Check authorization
        $userId = getCurrentUserId();
        $userRole = getCurrentUserRole();
        
        if ($userRole === 'customer' && $invoice->customer_id !== $userId) {
            setFlashMessage('error', 'Unauthorized access');
            redirect('/billing/invoices');
        }
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate input
            $paymentMethod = isset($_POST['payment_method']) ? $this->validationHelper->sanitize($_POST['payment_method']) : null;
            $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
            
            if (!$paymentMethod || $amount <= 0) {
                setFlashMessage('error', 'Invalid payment details');
                redirect('/billing/payment/' . $invoiceId);
            }
            
            // Process payment through gateway
            $paymentResult = $this->paymentGatewayService->processPayment([
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'method' => $paymentMethod,
                'customer_id' => $invoice->customer_id,
                'payment_details' => json_encode($_POST)
            ]);
            
            if ($paymentResult['success']) {
                // Record payment
                $paymentId = $this->billingService->recordPayment([
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'transaction_id' => $paymentResult['transaction_id'],
                    'status' => 'completed',
                    'payment_date' => date('Y-m-d H:i:s')
                ]);
                
                // Update invoice status
                $this->billingService->updateInvoiceStatus($invoiceId, 'paid');
                
                // Send receipt notification
                $this->notificationService->sendPaymentConfirmation($paymentId);
                
                setFlashMessage('success', 'Payment processed successfully');
                redirect('/billing/payment-receipt/' . $paymentId);
            } else {
                setFlashMessage('error', 'Payment failed: ' . $paymentResult['message']);
                redirect('/billing/payment/' . $invoiceId);
            }
        }
        
        // Get available payment methods
        $paymentMethods = $this->paymentGatewayService->getAvailablePaymentMethods();
        
        // Load view
        require_once APP_ROOT . '/views/billing/payments.php';
    }

    /**
     * Display payment receipt
     * 
     * @param int $paymentId
     * @return void
     */
    public function paymentReceipt($paymentId) {
        // Check authentication
        if (!isAuthenticated()) {
            redirect('/auth/login');
        }

        // Sanitize input
        $paymentId = (int)$paymentId;
        
        // Get payment details
        $payment = $this->billingService->getPaymentById($paymentId);
        
        // Check if payment exists
        if (!$payment) {
            setFlashMessage('error', 'Payment not found');
            redirect('/billing/invoices');
        }
        
        // Check authorization
        $userId = getCurrentUserId();
        $userRole = getCurrentUserRole();
        
        // Get invoice to check customer ID
        $invoice = $this->billingService->getInvoiceById($payment->invoice_id);
        
        if ($userRole === 'customer' && $invoice->customer_id !== $userId) {
            setFlashMessage('error', 'Unauthorized access');
            redirect('/billing/invoices');
        } elseif ($userRole === 'business' && $invoice->business_id !== $userId) {
            setFlashMessage('error', 'Unauthorized access');
            redirect('/billing/invoices');
        }
        
        // Load view
        require_once APP_ROOT . '/views/billing/payment-receipt.php';
    }

    /**
     * Manage subscription settings
     * 
     * @return void
     */
    public function subscription() {
        // Check authentication
        if (!isAuthenticated()) {
            redirect('/auth/login');
        }

        $userId = getCurrentUserId();
        $userRole = getCurrentUserRole();
        
        // Only customers can manage subscriptions
        if ($userRole !== 'customer') {
            setFlashMessage('error', 'Unauthorized access');
            redirect('/dashboard');
        }
        
        // Get customer subscriptions
        $subscriptions = $this->billingService->getCustomerSubscriptions($userId);
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = isset($_POST['action']) ? $this->validationHelper->sanitize($_POST['action']) : '';
            $subscriptionId = isset($_POST['subscription_id']) ? (int)$_POST['subscription_id'] : 0;
            
            switch ($action) {
                case 'cancel':
                    $result = $this->billingService->cancelSubscription($subscriptionId, $userId);
                    if ($result) {
                        setFlashMessage('success', 'Subscription cancelled successfully');
                    } else {
                        setFlashMessage('error', 'Failed to cancel subscription');
                    }
                    break;
                    
                case 'update':
                    $paymentMethod = isset($_POST['payment_method']) ? $this->validationHelper->sanitize($_POST['payment_method']) : '';
                    $result = $this->billingService->updateSubscriptionPaymentMethod($subscriptionId, $userId, $paymentMethod);
                    if ($result) {
                        setFlashMessage('success', 'Payment method updated successfully');
                    } else {
                        setFlashMessage('error', 'Failed to update payment method');
                    }
                    break;
            }
            
            redirect('/billing/subscription');
        }
        
        // Get available payment methods
        $paymentMethods = $this->paymentGatewayService->getAvailablePaymentMethods();
        
        // Load view
        require_once APP_ROOT . '/views/billing/subscription.php';
    }

    /**
     * Create new subscription
     * 
     * @param int $serviceId
     * @return void
     */
    public function createSubscription($serviceId = null) {
        // Check authentication
        if (!isAuthenticated()) {
            redirect('/auth/login');
        }

        $userId = getCurrentUserId();
        $userRole = getCurrentUserRole();
        
        // Only customers can create subscriptions
        if ($userRole !== 'customer') {
            setFlashMessage('error', 'Unauthorized access');
            redirect('/dashboard');
        }
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate input
            $serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : null;
            $planId = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : null;
            $paymentMethod = isset($_POST['payment_method']) ? $this->validationHelper->sanitize($_POST['payment_method']) : null;
            
            if (!$serviceId || !$planId || !$paymentMethod) {
                setFlashMessage('error', 'Missing required fields');
                redirect('/billing/create-subscription');
            }
            
            // Create subscription
            $subscriptionData = [
                'customer_id' => $userId,
                'service_id' => $serviceId,
                'plan_id' => $planId,
                'payment_method' => $paymentMethod,
                'status' => 'active',
                'start_date' => date('Y-m-d'),
                'billing_cycle' => isset($_POST['billing_cycle']) ? $this->validationHelper->sanitize($_POST['billing_cycle']) : 'monthly'
            ];
            
            $result = $this->billingService->createSubscription($subscriptionData);
            
            if ($result) {
                // Process initial payment
                $initialPaymentResult = $this->processSubscriptionPayment($result);
                
                if ($initialPaymentResult) {
                    setFlashMessage('success', 'Subscription created successfully');
                    redirect('/billing/subscription');
                } else {
                    // Rollback subscription if initial payment fails
                    $this->billingService->cancelSubscription($result, $userId);
                    setFlashMessage('error', 'Failed to process initial payment');
                    redirect('/billing/create-subscription');
                }
            } else {
                setFlashMessage('error', 'Failed to create subscription');
                redirect('/billing/create-subscription');
            }
        }
        
        // Get services and plans
        $serviceModel = new Service();
        $services = $serviceModel->findSubscriptionEligibleServices();
        
        // If service ID is provided, get plans for that service
        $plans = [];
        if ($serviceId) {
            $plans = $serviceModel->findServicePlans($serviceId);
        }
        
        // Get available payment methods
        $paymentMethods = $this->paymentGatewayService->getAvailablePaymentMethods();
        
        // Load view
        require_once APP_ROOT . '/views/billing/create-subscription.php';
    }

    /**
     * Export invoice as PDF
     * 
     * @param int $invoiceId
     * @return void
     */
    public function exportInvoice($invoiceId) {
        // Check authentication
        if (!isAuthenticated()) {
            redirect('/auth/login');
        }

        // Sanitize input
        $invoiceId = (int)$invoiceId;
        
        // Get invoice details
        $invoice = $this->billingService->getInvoiceById($invoiceId);
        
        // Check if invoice exists
        if (!$invoice) {
            setFlashMessage('error', 'Invoice not found');
            redirect('/billing/invoices');
        }
        
        // Check authorization
        $userId = getCurrentUserId();
        $userRole = getCurrentUserRole();
        
        if ($userRole === 'customer' && $invoice->customer_id !== $userId) {
            setFlashMessage('error', 'Unauthorized access');
            redirect('/billing/invoices');
        } elseif ($userRole === 'business' && $invoice->business_id !== $userId) {
            setFlashMessage('error', 'Unauthorized access');
            redirect('/billing/invoices');
        }
        
        // Generate PDF
        $pdf = $this->billingService->generateInvoicePdf($invoiceId);
        
        // Set headers for download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="invoice-'.$invoiceId.'.pdf"');
        header('Cache-Control: max-age=0');
        
        // Output PDF
        echo $pdf;
        exit;
    }
    
    /**
     * Process subscription payment
     * 
     * @param int $subscriptionId
     * @return bool Success status
     */
    private function processSubscriptionPayment($subscriptionId) {
        $subscription = $this->billingService->getSubscriptionById($subscriptionId);
        
        if (!$subscription) {
            return false;
        }
        
        // Get plan details
        $serviceModel = new Service();
        $plan = $serviceModel->findPlanById($subscription->plan_id);
        
        if (!$plan) {
            return false;
        }
        
        // Process payment through gateway
        $paymentResult = $this->paymentGatewayService->processSubscriptionPayment([
            'subscription_id' => $subscriptionId,
            'amount' => $plan->price,
            'method' => $subscription->payment_method,
            'customer_id' => $subscription->customer_id,
            'payment_details' => json_encode([
                'plan_id' => $plan->id,
                'service_id' => $subscription->service_id,
                'billing_cycle' => $subscription->billing_cycle
            ])
        ]);
        
        if ($paymentResult['success']) {
            // Create invoice
            $invoiceId = $this->billingService->createSubscriptionInvoice($subscriptionId);
            
            if (!$invoiceId) {
                return false;
            }
            
            // Record payment
            $paymentId = $this->billingService->recordPayment([
                'invoice_id' => $invoiceId,
                'amount' => $plan->price,
                'payment_method' => $subscription->payment_method,
                'transaction_id' => $paymentResult['transaction_id'],
                'status' => 'completed',
                'payment_date' => date('Y-m-d H:i:s'),
                'subscription_id' => $subscriptionId
            ]);
            
            // Update invoice status
            $this->billingService->updateInvoiceStatus($invoiceId, 'paid');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Display payment history
     * 
     * @return void
     */
    public function paymentHistory() {
        // Check authentication
        if (!isAuthenticated()) {
            redirect('/auth/login');
        }

        $userId = getCurrentUserId();
        $userRole = getCurrentUserRole();
        $filterOptions = [];
        
        // Handle query parameters for filtering
        if (isset($_GET['date_from'])) {
            $filterOptions['date_from'] = $this->validationHelper->sanitize($_GET['date_from']);
        }
        
        if (isset($_GET['date_to'])) {
            $filterOptions['date_to'] = $this->validationHelper->sanitize($_GET['date_to']);
        }
        
        if (isset($_GET['payment_method'])) {
            $filterOptions['payment_method'] = $this->validationHelper->sanitize($_GET['payment_method']);
        }

        // Get payments based on user role
        if ($userRole === 'admin') {
            $payments = $this->billingService->getAllPayments($filterOptions);
        } elseif ($userRole === 'business') {
            $payments = $this->billingService->getBusinessPayments($userId, $filterOptions);
        } else {
            $payments = $this->billingService->getCustomerPayments($userId, $filterOptions);
        }
        
        // Get available payment methods for filtering
        $paymentMethods = $this->paymentGatewayService->getAvailablePaymentMethods();
        
        // Load view
        require_once APP_ROOT . '/views/billing/payment-history.php';
    }
    
    /**
     * Send payment reminder for an invoice
     * 
     * @param int $invoiceId
     * @return void
     */
    public function sendPaymentReminder($invoiceId) {
        // Check authentication and authorization
        if (!isAuthenticated() || !isAuthorized(['admin', 'business'])) {
            setFlashMessage('error', 'Unauthorized access');
            redirect('/auth/login');
        }

        // Sanitize input
        $invoiceId = (int)$invoiceId;
        
        // Get invoice details
        $invoice = $this->billingService->getInvoiceById($invoiceId);
        
        // Check if invoice exists and is pending
        if (!$invoice || $invoice->status !== 'pending') {
            setFlashMessage('error', 'Invoice not found or already paid');
            redirect('/billing/invoices');
        }
        
        // Send reminder notification
        $result = $this->notificationService->sendPaymentReminder($invoiceId);
        
        if ($result) {
            // Update reminder sent count and date
            $this->billingService->updateInvoiceReminderCount($invoiceId);
            
            setFlashMessage('success', 'Payment reminder sent successfully');
        } else {
            setFlashMessage('error', 'Failed to send payment reminder');
        }
        
        redirect('/billing/view-invoice/' . $invoiceId);
    }
}