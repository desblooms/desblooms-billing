<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\Service;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\ReportingService;
use App\Services\AuthService;

/**
 * Dashboard Controller
 * 
 * Handles dashboard views and data for different user roles
 */
class DashboardController
{
    private $reportingService;
    private $authService;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->reportingService = new ReportingService();
        $this->authService = new AuthService();
    }
    
    /**
     * Display the appropriate dashboard based on user role
     */
    public function index()
    {
        // Get current authenticated user
        $user = $this->authService->getCurrentUser();
        
        if (!$user) {
            // Redirect to login if not authenticated
            header('Location: /auth/login');
            exit;
        }
        
        // Route to appropriate dashboard based on role
        switch ($user->role) {
            case 'admin':
                return $this->adminDashboard();
            case 'business':
                return $this->businessDashboard();
            case 'customer':
                return $this->customerDashboard();
            default:
                // Fallback to customer dashboard for undefined roles
                return $this->customerDashboard();
        }
    }
    
    /**
     * Admin Dashboard
     */
    private function adminDashboard()
    {
        // Get dashboard statistics
        $stats = [
            'total_users' => (new User())->count(),
            'total_customers' => (new Customer())->count(),
            'total_orders' => (new Order())->count(),
            'total_services' => (new Service())->count(),
            'recent_payments' => (new Payment())->getRecent(5),
            'revenue_summary' => $this->reportingService->getRevenueSummary(),
            'service_usage' => $this->reportingService->getServiceUsageStats(),
            'customer_activity' => $this->reportingService->getCustomerActivityStats()
        ];
        
        // Load admin dashboard view with data
        include_once 'app/views/dashboard/admin.php';
    }
    
    /**
     * Business Dashboard
     */
    private function businessDashboard()
    {
        // Get current business ID
        $user = $this->authService->getCurrentUser();
        $businessId = $user->id;
        
        // Get business-specific dashboard statistics
        $stats = [
            'active_services' => (new Service())->getByBusiness($businessId),
            'pending_orders' => (new Order())->getPendingByBusiness($businessId),
            'recent_payments' => (new Payment())->getRecentByBusiness($businessId, 5),
            'revenue_summary' => $this->reportingService->getBusinessRevenueSummary($businessId),
            'top_services' => $this->reportingService->getTopServicesByBusiness($businessId),
            'customer_growth' => $this->reportingService->getBusinessCustomerGrowth($businessId)
        ];
        
        // Load business dashboard view with data
        include_once 'app/views/dashboard/business.php';
    }
    
    /**
     * Customer Dashboard
     */
    private function customerDashboard()
    {
        // Get current customer ID
        $user = $this->authService->getCurrentUser();
        $customerId = $user->id;
        
        // Get customer-specific dashboard data
        $stats = [
            'active_subscriptions' => (new Service())->getActiveByCustomer($customerId),
            'recent_orders' => (new Order())->getRecentByCustomer($customerId, 5),
            'pending_payments' => (new Payment())->getPendingByCustomer($customerId),
            'payment_history' => (new Payment())->getHistoryByCustomer($customerId, 5),
            'service_recommendations' => (new Service())->getRecommendedForCustomer($customerId)
        ];
        
        // Load customer dashboard view with data
        include_once 'app/views/dashboard/customer.php';
    }
    
    /**
     * API endpoint for dashboard chart data
     */
    public function getChartData()
    {
        // Verify AJAX request
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
        
        // Get current user
        $user = $this->authService->getCurrentUser();
        
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        // Get chart type from request
        $chartType = isset($_GET['type']) ? $_GET['type'] : 'revenue';
        $timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'month';
        
        // Get appropriate chart data based on user role and request
        switch ($chartType) {
            case 'revenue':
                $data = $this->reportingService->getRevenueChartData($user, $timeframe);
                break;
            case 'service-usage':
                $data = $this->reportingService->getServiceUsageChartData($user, $timeframe);
                break;
            case 'customer-activity':
                $data = $this->reportingService->getCustomerActivityChartData($user, $timeframe);
                break;
            default:
                $data = ['error' => 'Invalid chart type'];
        }
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Export dashboard data
     */
    public function exportData()
    {
        // Get current user
        $user = $this->authService->getCurrentUser();
        
        if (!$user || !in_array($user->role, ['admin', 'business'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
        
        // Get report type from request
        $reportType = isset($_GET['report']) ? $_GET['report'] : 'revenue';
        $format = isset($_GET['format']) ? $_GET['format'] : 'csv';
        
        // Generate report data
        $reportData = $this->reportingService->generateReport($user, $reportType);
        
        // Set headers for download
        $filename = $reportType . '_report_' . date('Y-m-d') . '.' . $format;
        
        switch ($format) {
            case 'csv':
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                $this->outputCsv($reportData);
                break;
            case 'json':
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo json_encode($reportData);
                break;
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Unsupported format']);
        }
        
        exit;
    }
    
    /**
     * Helper method to output CSV data
     */
    private function outputCsv($data)
    {
        if (empty($data)) {
            echo '';
            return;
        }
        
        $output = fopen('php://output', 'w');
        
        // Add headers
        fputcsv($output, array_keys($data[0]));
        
        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
    }
}