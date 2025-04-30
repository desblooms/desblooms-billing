<?php
/**
 * OrderController - Handles all order-related operations
 * 
 * Part of Digital Service Billing Mobile App
 * @author Claude
 */

namespace App\Controllers;

use App\Models\Order;
use App\Models\Service;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\BillingService;
use App\Services\NotificationService;

class OrderController
{
    private $orderModel;
    private $serviceModel;
    private $customerModel;
    private $paymentModel;
    private $billingService;
    private $notificationService;

    /**
     * Constructor - initialize models and services
     */
    public function __construct()
    {
        $this->orderModel = new Order();
        $this->serviceModel = new Service();
        $this->customerModel = new Customer();
        $this->paymentModel = new Payment();
        $this->billingService = new BillingService();
        $this->notificationService = new NotificationService();
    }

    /**
     * Display order creation form with available services
     */
    public function create()
    {
        // Verify user is authenticated
        if (!isAuthenticated()) {
            redirect('auth/login');
        }

        $customerId = getCurrentUserId();
        $services = $this->serviceModel->getAllActive();
        
        // Load view with data
        view('orders/create', [
            'services' => $services,
            'customerInfo' => $this->customerModel->getById($customerId)
        ]);
    }

    /**
     * Process order form submission
     */
    public function store()
    {
        // Verify user is authenticated
        if (!isAuthenticated()) {
            redirect('auth/login');
        }

        // Validate form input
        $validation = validateInput([
            'service_id' => 'required|numeric',
            'quantity' => 'required|numeric|min:1',
            'start_date' => 'required|date',
            'billing_cycle' => 'required|in:monthly,quarterly,annual'
        ]);

        if (!$validation['valid']) {
            // Return to form with validation errors
            setFlash('error', 'Please correct the errors in your form.');
            return view('orders/create', [
                'errors' => $validation['errors'],
                'old' => $_POST
            ]);
        }

        // Get service details to calculate price
        $serviceId = sanitize($_POST['service_id']);
        $quantity = sanitize($_POST['quantity']);
        $startDate = sanitize($_POST['start_date']);
        $billingCycle = sanitize($_POST['billing_cycle']);
        $customerId = getCurrentUserId();
        
        $service = $this->serviceModel->getById($serviceId);
        if (!$service) {
            setFlash('error', 'Selected service not found.');
            redirect('orders/create');
        }

        // Calculate total price based on quantity and billing cycle
        $price = $this->calculatePrice($service['base_price'], $quantity, $billingCycle);

        // Create order record
        $orderData = [
            'customer_id' => $customerId,
            'service_id' => $serviceId,
            'quantity' => $quantity,
            'price' => $price,
            'status' => 'pending',
            'start_date' => $startDate,
            'billing_cycle' => $billingCycle,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $orderId = $this->orderModel->create($orderData);
        
        if (!$orderId) {
            setFlash('error', 'Failed to create your order. Please try again.');
            redirect('orders/create');
        }

        // Generate invoice through billing service
        $invoiceId = $this->billingService->generateInvoice($orderId);
        
        // Notify customer about new order
        $this->notificationService->sendOrderConfirmation($orderId);

        setFlash('success', 'Your order has been placed successfully!');
        redirect('orders/details/' . $orderId);
    }

    /**
     * Display order details
     * 
     * @param int $id Order ID
     */
    public function details($id)
    {
        // Verify user is authenticated
        if (!isAuthenticated()) {
            redirect('auth/login');
        }

        $id = (int)$id;
        $order = $this->orderModel->getById($id);
        
        // Check if order exists and belongs to current user or user is admin
        if (!$order || (!isAdmin() && $order['customer_id'] != getCurrentUserId())) {
            setFlash('error', 'Order not found or you do not have permission to view it.');
            redirect('orders/history');
        }

        // Get related service and payment information
        $service = $this->serviceModel->getById($order['service_id']);
        $payments = $this->paymentModel->getByOrderId($id);
        
        view('orders/details', [
            'order' => $order,
            'service' => $service,
            'payments' => $payments
        ]);
    }

    /**
     * Display order history for current user
     */
    public function history()
    {
        // Verify user is authenticated
        if (!isAuthenticated()) {
            redirect('auth/login');
        }

        $customerId = getCurrentUserId();
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        // Get orders with pagination
        $orders = $this->orderModel->getByCustomerId($customerId, $limit, $offset);
        $totalOrders = $this->orderModel->countByCustomerId($customerId);
        $totalPages = ceil($totalOrders / $limit);
        
        view('orders/history', [
            'orders' => $orders,
            'currentPage' => $page,
            'totalPages' => $totalPages
        ]);
    }

    /**
     * Cancel an order (only allowed if order is still pending)
     * 
     * @param int $id Order ID
     */
    public function cancel($id)
    {
        // Verify user is authenticated
        if (!isAuthenticated()) {
            redirect('auth/login');
        }

        $id = (int)$id;
        $order = $this->orderModel->getById($id);
        
        // Check if order exists and belongs to current user
        if (!$order || $order['customer_id'] != getCurrentUserId()) {
            setFlash('error', 'Order not found or you do not have permission to cancel it.');
            redirect('orders/history');
        }

        // Check if order is still cancelable (pending status)
        if ($order['status'] != 'pending') {
            setFlash('error', 'This order cannot be canceled because it is already ' . $order['status'] . '.');
            redirect('orders/details/' . $id);
        }

        // Update order status
        $updated = $this->orderModel->update($id, ['status' => 'canceled']);
        
        if ($updated) {
            // Notify relevant parties about cancellation
            $this->notificationService->sendOrderCancellationNotice($id);
            setFlash('success', 'Your order has been canceled successfully.');
        } else {
            setFlash('error', 'Failed to cancel your order. Please try again.');
        }
        
        redirect('orders/details/' . $id);
    }

    /**
     * Admin method: Update order status
     * 
     * @param int $id Order ID
     */
    public function updateStatus($id)
    {
        // Verify user is authenticated and has admin privileges
        if (!isAuthenticated() || !isAdmin()) {
            redirect('auth/login');
        }

        // Validate status input
        $validation = validateInput([
            'status' => 'required|in:pending,processing,completed,canceled,failed'
        ]);

        if (!$validation['valid']) {
            setFlash('error', 'Invalid status provided.');
            redirect('orders/details/' . $id);
        }

        $id = (int)$id;
        $newStatus = sanitize($_POST['status']);
        
        // Update order status
        $updated = $this->orderModel->update($id, [
            'status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($updated) {
            // Notify customer about status change
            $this->notificationService->sendOrderStatusUpdate($id, $newStatus);
            setFlash('success', 'Order status updated successfully.');
        } else {
            setFlash('error', 'Failed to update order status. Please try again.');
        }
        
        redirect('orders/details/' . $id);
    }

    /**
     * Calculate price based on service base price, quantity and billing cycle
     * 
     * @param float $basePrice Service base price
     * @param int $quantity Quantity of service
     * @param string $billingCycle Billing cycle (monthly, quarterly, annual)
     * @return float Final price
     */
    private function calculatePrice($basePrice, $quantity, $billingCycle)
    {
        $price = $basePrice * $quantity;
        
        // Apply discount based on billing cycle
        switch ($billingCycle) {
            case 'quarterly':
                // 5% discount for quarterly billing
                $price = $price * 3 * 0.95;
                break;
            case 'annual':
                // 15% discount for annual billing
                $price = $price * 12 * 0.85;
                break;
            case 'monthly':
            default:
                // No discount for monthly billing
                break;
        }
        
        return round($price, 2);
    }
}