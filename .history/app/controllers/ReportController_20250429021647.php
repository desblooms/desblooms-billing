<?php
/**
 * Report Controller
 * 
 * Handles all reporting functionality for the Digital Service Billing Mobile App
 * Including revenue reports, customer activity, and service usage statistics
 */

namespace App\Controllers;

use App\Models\Report;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Customer;
use App\Services\ReportingService;
use App\Helpers\DateHelper;
use App\Middlewares\AdminMiddleware;
use App\Middlewares\AuthMiddleware;

class ReportController {
    
    private $reportModel;
    private $orderModel;
    private $paymentModel;
    private $serviceModel;
    private $customerModel;
    private $reportingService;
    private $dateHelper;
    
    /**
     * Constructor - initialize models and services
     */
    public function __construct() {
        // Initialize middleware
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle();
        
        // Initialize models
        $this->reportModel = new Report();
        $this->orderModel = new Order();
        $this->paymentModel = new Payment();
        $this->serviceModel = new Service();
        $this->customerModel = new Customer();
        
        // Initialize services and helpers
        $this->reportingService = new ReportingService();
        $this->dateHelper = new DateHelper();
    }
    
    /**
     * Dashboard summary - shows key metrics for quick overview
     */
    public function dashboard() {
        // Check if user has permission to view reports
        $this->checkReportPermissions();
        
        $data = [
            'totalRevenue' => $this->paymentModel->getTotalRevenue(),
            'monthlyRevenue' => $this->paymentModel->getMonthlyRevenue(),
            'activeCustomers' => $this->customerModel->getActiveCustomersCount(),
            'popularServices' => $this->serviceModel->getPopularServices(5),
            'recentOrders' => $this->orderModel->getRecentOrders(5),
            'revenueTrend' => $this->reportingService->getRevenueTrend('last_6_months')
        ];
        
        // Determine if business or admin view
        if ($this->isAdmin()) {
            $data['customerGrowth'] = $this->reportingService->getCustomerGrowthRate();
            $data['allBusinesses'] = $this->reportingService->getBusinessPerformance();
            
            // Load the admin dashboard view
            $this->loadView('dashboard/admin', $data);
        } else {
            // Load the business dashboard view
            $this->loadView('dashboard/business', $data);
        }
    }
    
    /**
     * Revenue reports
     */
    public function revenueReport() {
        // Check if user has permission to view reports
        $this->checkReportPermissions();
        
        $period = isset($_GET['period']) ? $_GET['period'] : 'monthly';
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        
        // Sanitize and validate dates
        if ($startDate && $endDate) {
            $startDate = $this->dateHelper->sanitizeDate($startDate);
            $endDate = $this->dateHelper->sanitizeDate($endDate);
        } else {
            // Set default date range if not specified
            list($startDate, $endDate) = $this->dateHelper->getDateRangeForPeriod($period);
        }
        
        $data = [
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'revenueData' => $this->reportingService->getRevenueData($startDate, $endDate, $period),
            'topServices' => $this->reportingService->getTopRevenueServices($startDate, $endDate, 5),
            'paymentMethods' => $this->reportingService->getRevenueByPaymentMethod($startDate, $endDate)
        ];
        
        // Load the revenue reports view
        $this->loadView('reports/revenue', $data);
    }
    
    /**
     * Customer activity reports
     */
    public function customerActivityReport() {
        // Check if user has permission to view reports
        $this->checkReportPermissions();
        
        $period = isset($_GET['period']) ? $_GET['period'] : 'monthly';
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
        
        // Sanitize and validate dates
        if ($startDate && $endDate) {
            $startDate = $this->dateHelper->sanitizeDate($startDate);
            $endDate = $this->dateHelper->sanitizeDate($endDate);
        } else {
            // Set default date range if not specified
            list($startDate, $endDate) = $this->dateHelper->getDateRangeForPeriod($period);
        }
        
        $data = [
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'activityData' => $this->reportingService->getCustomerActivityData($startDate, $endDate, $customerId),
            'newCustomers' => $this->reportingService->getNewCustomersData($startDate, $endDate),
            'customerRetention' => $this->reportingService->getCustomerRetentionRate($startDate, $endDate)
        ];
        
        if ($this->isAdmin()) {
            $data['allCustomers'] = $this->customerModel->getAllCustomers();
        } else {
            $data['businessCustomers'] = $this->customerModel->getBusinessCustomers($_SESSION['business_id']);
        }
        
        // Load the customer activity reports view
        $this->loadView('reports/customer-activity', $data);
    }
    
    /**
     * Service usage reports
     */
    public function serviceUsageReport() {
        // Check if user has permission to view reports
        $this->checkReportPermissions();
        
        $period = isset($_GET['period']) ? $_GET['period'] : 'monthly';
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        $serviceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : null;
        
        // Sanitize and validate dates
        if ($startDate && $endDate) {
            $startDate = $this->dateHelper->sanitizeDate($startDate);
            $endDate = $this->dateHelper->sanitizeDate($endDate);
        } else {
            // Set default date range if not specified
            list($startDate, $endDate) = $this->dateHelper->getDateRangeForPeriod($period);
        }
        
        $data = [
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'usageData' => $this->reportingService->getServiceUsageData($startDate, $endDate, $serviceId),
            'servicePerformance' => $this->reportingService->getServicePerformanceData($startDate, $endDate),
            'serviceGrowth' => $this->reportingService->getServiceGrowthRate($startDate, $endDate)
        ];
        
        $data['allServices'] = $this->serviceModel->getAllServices();
        
        // Load the service usage reports view
        $this->loadView('reports/service-usage', $data);
    }
    
    /**
     * Generate report exports (CSV, PDF)
     */
    public function exportReport() {
        // Check if user has permission to export reports
        $this->checkReportPermissions();
        
        $reportType = isset($_GET['type']) ? $_GET['type'] : 'revenue';
        $format = isset($_GET['format']) ? $_GET['format'] : 'csv';
        $startDate = isset($_GET['start_date']) ? $this->dateHelper->sanitizeDate($_GET['start_date']) : null;
        $endDate = isset($_GET['end_date']) ? $this->dateHelper->sanitizeDate($_GET['end_date']) : null;
        
        // Validate required parameters
        if (!$startDate || !$endDate) {
            // Set default to last 30 days if not specified
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime('-30 days'));
        }
        
        // Generate the requested report
        switch ($reportType) {
            case 'revenue':
                $data = $this->reportingService->getRevenueData($startDate, $endDate, 'daily');
                $filename = 'revenue_report_' . $startDate . '_to_' . $endDate;
                break;
            case 'customer':
                $data = $this->reportingService->getCustomerActivityData($startDate, $endDate);
                $filename = 'customer_activity_' . $startDate . '_to_' . $endDate;
                break;
            case 'service':
                $data = $this->reportingService->getServiceUsageData($startDate, $endDate);
                $filename = 'service_usage_' . $startDate . '_to_' . $endDate;
                break;
            default:
                // Invalid report type
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(['error' => 'Invalid report type specified']);
                return;
        }
        
        // Generate the export in the requested format
        if ($format === 'csv') {
            $this->exportCsv($data, $filename);
        } elseif ($format === 'pdf') {
            $this->exportPdf($data, $filename, $reportType);
        } else {
            // Invalid format
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Invalid export format specified']);
        }
    }
    
    /**
     * Export data as CSV
     */
    private function exportCsv($data, $filename) {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add headers if data is not empty
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
        }
        
        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export data as PDF
     */
    private function exportPdf($data, $filename, $reportType) {
        // Implementation would depend on PDF library used
        // This is a placeholder for the actual implementation
        
        // Example with a hypothetical PDF library:
        // $pdf = new PDFGenerator();
        // $pdf->setTitle($filename);
        // $pdf->addTable($data);
        // $pdf->output($filename . '.pdf', 'D');
        
        // For now, just return JSON indicating this would be a PDF
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'PDF export functionality will be implemented in phase 3',
            'data' => [
                'filename' => $filename . '.pdf',
                'reportType' => $reportType,
                'rowCount' => count($data)
            ]
        ]);
        exit;
    }
    
    /**
     * Custom report builder
     */
    public function customReport() {
        // Check if user has permission to view reports
        $this->checkReportPermissions();
        
        // Only admins can build custom reports
        if (!$this->isAdmin()) {
            $this->redirectWithError('dashboard', 'You do not have permission to build custom reports');
            return;
        }
        
        // If form was submitted, process the custom report
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $metrics = isset($_POST['metrics']) ? $_POST['metrics'] : [];
            $filters = isset($_POST['filters']) ? $_POST['filters'] : [];
            $groupBy = isset($_POST['group_by']) ? $_POST['group_by'] : 'day';
            $startDate = isset($_POST['start_date']) ? $this->dateHelper->sanitizeDate($_POST['start_date']) : null;
            $endDate = isset($_POST['end_date']) ? $this->dateHelper->sanitizeDate($_POST['end_date']) : null;
            
            // Validate inputs
            if (empty($metrics)) {
                $this->setFlashMessage('error', 'Please select at least one metric for your report');
                $this->loadView('reports/custom-report');
                return;
            }
            
            $reportData = $this->reportingService->buildCustomReport($metrics, $filters, $groupBy, $startDate, $endDate);
            
            $data = [
                'reportData' => $reportData,
                'metrics' => $metrics,
                'filters' => $filters,
                'groupBy' => $groupBy,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'hasResults' => !empty($reportData)
            ];
            
            $this->loadView('reports/custom-report-results', $data);
        } else {
            // Show the form to build a custom report
            $data = [
                'availableMetrics' => $this->reportingService->getAvailableMetrics(),
                'availableFilters' => $this->reportingService->getAvailableFilters(),
                'availableGroupings' => $this->reportingService->getAvailableGroupings()
            ];
            
            $this->loadView('reports/custom-report', $data);
        }
    }
    
    /**
     * Check if user has permission to view reports
     */
    private function checkReportPermissions() {
        // Only admins and business users can access reports
        if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'business')) {
            // Redirect to login if not logged in, or to dashboard if insufficient permissions
            if (!isset($_SESSION['user_id'])) {
                $this->redirect('auth/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            } else {
                $this->redirectWithError('dashboard', 'You do not have permission to view reports');
            }
            exit;
        }
        
        // For business users, check if they're trying to access data they don't own
        if ($_SESSION['user_role'] === 'business' && isset($_GET['business_id']) && $_GET['business_id'] != $_SESSION['business_id']) {
            $this->redirectWithError('dashboard', 'You can only view reports for your own business');
            exit;
        }
    }
    
    /**
     * Check if current user is an admin
     * 
     * @return boolean
     */
    private function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
    
    /**
     * Load view with data
     * 
     * @param string $view
     * @param array $data
     */
    private function loadView($view, $data = []) {
        // Implementation would depend on your framework or custom code
        // This is a placeholder for the actual implementation
        
        // Example:
        // extract($data);
        // include '../app/views/' . $view . '.php';
    }
    
    /**
     * Redirect with error message
     * 
     * @param string $path
     * @param string $message
     */
    private function redirectWithError($path, $message) {
        $this->setFlashMessage('error', $message);
        $this->redirect($path);
    }
    
    /**
     * Set flash message
     * 
     * @param string $type
     * @param string $message
     */
    private function setFlashMessage($type, $message) {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }
    
    /**
     * Redirect to path
     * 
     * @param string $path
     */
    private function redirect($path) {
        header('Location: /' . $path);
        exit;
    }
}