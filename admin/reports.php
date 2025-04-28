 
<?php
/**
 * Admin Reports - Digital Service Billing Mobile App
 * 
 * This file handles the reports and analytics functionality for administrators
 * Includes: Sales Reports, Invoice Reports, Customer Activity, and Transaction Logs
 */

// Start session
session_start();

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !isAdmin()) {
    // Redirect to login page
    header('Location: ../login.php?redirect=admin/reports.php');
    exit;
}

// Default date range (last 30 days)
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-30 days'));

// Get date range from request if set
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
}

// Get report type (sales, invoices, customers, transactions)
$reportType = isset($_GET['type']) ? $_GET['type'] : 'sales';

// Function to get sales data by date range
function getSalesReport($startDate, $endDate) {
    global $conn;
    
    $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_sales,
                SUM(total_amount) as revenue
              FROM 
                invoices
              WHERE 
                status = 'paid' 
                AND created_at BETWEEN ? AND ? 
              GROUP BY 
                DATE(created_at)
              ORDER BY 
                date ASC";
                
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get invoice data by status and date range
function getInvoiceReport($startDate, $endDate) {
    global $conn;
    
    $query = "SELECT 
                status,
                COUNT(*) as count,
                SUM(total_amount) as amount
              FROM 
                invoices
              WHERE 
                created_at BETWEEN ? AND ?
              GROUP BY 
                status
              ORDER BY 
                status ASC";
                
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get customer activity report
function getCustomerActivityReport($startDate, $endDate) {
    global $conn;
    
    $query = "SELECT 
                u.id, 
                u.username,
                u.email,
                COUNT(i.id) as invoice_count,
                SUM(i.total_amount) as total_spent,
                MAX(i.created_at) as last_purchase
              FROM 
                users u
              LEFT JOIN 
                invoices i ON u.id = i.user_id AND i.created_at BETWEEN ? AND ?
              WHERE 
                u.role = 'customer'
              GROUP BY 
                u.id
              ORDER BY 
                total_spent DESC
              LIMIT 50";
                
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get transaction logs
function getTransactionLogs($startDate, $endDate) {
    global $conn;
    
    $query = "SELECT 
                t.id,
                t.invoice_id,
                t.user_id,
                u.username,
                t.amount,
                t.payment_method,
                t.transaction_id,
                t.status,
                t.created_at
              FROM 
                transactions t
              JOIN 
                users u ON t.user_id = u.id
              WHERE 
                t.created_at BETWEEN ? AND ?
              ORDER BY 
                t.created_at DESC
              LIMIT 100";
                
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get daily summary data for the dashboard chart
function getDailySummary($startDate, $endDate) {
    global $conn;
    
    $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as sales_count,
                SUM(total_amount) as revenue,
                COUNT(DISTINCT user_id) as unique_customers
              FROM 
                invoices
              WHERE 
                status = 'paid' 
                AND created_at BETWEEN ? AND ? 
              GROUP BY 
                DATE(created_at)
              ORDER BY 
                date ASC";
                
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $labels = [];
    $salesData = [];
    $revenueData = [];
    $customerData = [];
    
    while ($row = $result->fetch_assoc()) {
        $labels[] = date('M d', strtotime($row['date']));
        $salesData[] = $row['sales_count'];
        $revenueData[] = $row['revenue'];
        $customerData[] = $row['unique_customers'];
    }
    
    return [
        'labels' => $labels,
        'salesData' => $salesData,
        'revenueData' => $revenueData,
        'customerData' => $customerData
    ];
}

// Get the appropriate report data based on type
switch ($reportType) {
    case 'sales':
        $reportData = getSalesReport($startDate, $endDate);
        break;
    case 'invoices':
        $reportData = getInvoiceReport($startDate, $endDate);
        break;
    case 'customers':
        $reportData = getCustomerActivityReport($startDate, $endDate);
        break;
    case 'transactions':
        $reportData = getTransactionLogs($startDate, $endDate);
        break;
    default:
        $reportData = getSalesReport($startDate, $endDate);
}

// Get summary data for charts
$summaryData = getDailySummary($startDate, $endDate);

// Calculate totals for the dashboard
$totalSales = array_sum($summaryData['salesData']);
$totalRevenue = array_sum($summaryData['revenueData']);
$totalCustomers = array_sum($summaryData['customerData']);

// Get export format if requested
$exportFormat = isset($_GET['export']) ? $_GET['export'] : '';

// Handle CSV export
if ($exportFormat === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $reportType . '_report_' . $startDate . '_to_' . $endDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Export different CSV formats based on report type
    switch ($reportType) {
        case 'sales':
            fputcsv($output, ['Date', 'Total Sales', 'Revenue']);
            foreach ($reportData as $row) {
                fputcsv($output, [$row['date'], $row['total_sales'], $row['revenue']]);
            }
            break;
        case 'invoices':
            fputcsv($output, ['Status', 'Count', 'Amount']);
            foreach ($reportData as $row) {
                fputcsv($output, [$row['status'], $row['count'], $row['amount']]);
            }
            break;
        case 'customers':
            fputcsv($output, ['ID', 'Username', 'Email', 'Invoice Count', 'Total Spent', 'Last Purchase']);
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['id'], 
                    $row['username'], 
                    $row['email'], 
                    $row['invoice_count'], 
                    $row['total_spent'], 
                    $row['last_purchase']
                ]);
            }
            break;
        case 'transactions':
            fputcsv($output, ['ID', 'Invoice ID', 'User ID', 'Username', 'Amount', 'Payment Method', 'Transaction ID', 'Status', 'Date']);
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['invoice_id'],
                    $row['user_id'],
                    $row['username'],
                    $row['amount'],
                    $row['payment_method'],
                    $row['transaction_id'],
                    $row['status'],
                    $row['created_at']
                ]);
            }
            break;
    }
    
    fclose($output);
    exit;
}

// Page title
$pageTitle = 'Admin Reports';

// Include header
include '../templates/admin-header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Reports & Analytics</h1>
        <div class="flex space-x-2">
            <a href="dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
            <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                <i class="fas fa-print mr-2"></i>Print
            </button>
            <a href="?type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&export=csv" 
               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                <i class="fas fa-download mr-2"></i>Export CSV
            </a>
        </div>
    </div>
    
    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
        <div class="flex flex-wrap mb-6">
            <div class="w-full lg:w-auto flex flex-wrap items-center">
                <div class="font-medium mr-4 mb-2 lg:mb-0">Report Type:</div>
                <div class="flex space-x-2 mb-2 lg:mb-0">
                    <a href="?type=sales&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
                       class="px-4 py-2 rounded <?php echo $reportType === 'sales' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">
                        Sales
                    </a>
                    <a href="?type=invoices&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
                       class="px-4 py-2 rounded <?php echo $reportType === 'invoices' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">
                        Invoices
                    </a>
                    <a href="?type=customers&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
                       class="px-4 py-2 rounded <?php echo $reportType === 'customers' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">
                        Customers
                    </a>
                    <a href="?type=transactions&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
                       class="px-4 py-2 rounded <?php echo $reportType === 'transactions' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?>">
                        Transactions
                    </a>
                </div>
            </div>
            
            <div class="w-full lg:w-auto lg:ml-auto mt-4 lg:mt-0">
                <form method="GET" action="" class="flex flex-wrap items-center">
                    <input type="hidden" name="type" value="<?php echo $reportType; ?>">
                    <div class="mr-2 mb-2 lg:mb-0">
                        <label for="start_date" class="mr-2">From:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>" 
                               class="border rounded px-2 py-1">
                    </div>
                    <div class="mr-2 mb-2 lg:mb-0">
                        <label for="end_date" class="mr-2">To:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>" 
                               class="border rounded px-2 py-1">
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        Apply
                    </button>
                </form>
            </div>
        </div>
        
        <div class="mb-6">
            <div class="flex flex-wrap -mx-2">
                <div class="w-full md:w-1/3 px-2 mb-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="text-blue-700 font-medium">Total Sales</div>
                        <div class="text-2xl font-bold"><?php echo $totalSales; ?></div>
                    </div>
                </div>
                <div class="w-full md:w-1/3 px-2 mb-4">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="text-green-700 font-medium">Total Revenue</div>
                        <div class="text-2xl font-bold">$<?php echo number_format($totalRevenue, 2); ?></div>
                    </div>
                </div>
                <div class="w-full md:w-1/3 px-2 mb-4">
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <div class="text-purple-700 font-medium">Unique Customers</div>
                        <div class="text-2xl font-bold"><?php echo $totalCustomers; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Chart Section -->
        <div class="mb-8">
            <canvas id="reportChart" width="400" height="200"></canvas>
        </div>
    </div>
    
    <!-- Report Content Based on Type -->
    <div class="bg-white shadow-md rounded-lg p-6">
        <?php if ($reportType === 'sales'): ?>
            <h2 class="text-xl font-bold mb-4">Sales Report (<?php echo $startDate; ?> to <?php echo $endDate; ?>)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Date</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Total Sales</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo $row['total_sales']; ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">$<?php echo number_format($row['revenue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reportData)): ?>
                            <tr>
                                <td colspan="3" class="py-4 text-center text-gray-500">No sales data found for the selected period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        
        <?php elseif ($reportType === 'invoices'): ?>
            <h2 class="text-xl font-bold mb-4">Invoice Report (<?php echo $startDate; ?> to <?php echo $endDate; ?>)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Status</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Count</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <span class="inline-block px-2 py-1 rounded text-xs font-medium 
                                    <?php
                                        switch ($row['status']) {
                                            case 'paid':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'pending':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'outstanding':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo $row['count']; ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">$<?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reportData)): ?>
                            <tr>
                                <td colspan="3" class="py-4 text-center text-gray-500">No invoice data found for the selected period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($reportType === 'customers'): ?>
            <h2 class="text-xl font-bold mb-4">Customer Activity Report (<?php echo $startDate; ?> to <?php echo $endDate; ?>)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">ID</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Username</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Email</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Invoices</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Total Spent</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Last Purchase</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo $row['id']; ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo htmlspecialchars($row['username']); ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo $row['invoice_count']; ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">$<?php echo number_format($row['total_spent'], 2); ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <?php echo $row['last_purchase'] ? date('M d, Y', strtotime($row['last_purchase'])) : 'Never'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reportData)): ?>
                            <tr>
                                <td colspan="6" class="py-4 text-center text-gray-500">No customer activity found for the selected period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($reportType === 'transactions'): ?>
            <h2 class="text-xl font-bold mb-4">Transaction Logs (<?php echo $startDate; ?> to <?php echo $endDate; ?>)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">ID</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Invoice</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">User</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Amount</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Method</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Transaction ID</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Status</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-medium text-gray-500">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo $row['id']; ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <a href="../admin/invoices.php?id=<?php echo $row['invoice_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                        #<?php echo $row['invoice_id']; ?>
                                    </a>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo htmlspecialchars($row['username']); ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">$<?php echo number_format($row['amount'], 2); ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo ucfirst($row['payment_method']); ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <span class="text-xs"><?php echo $row['transaction_id']; ?></span>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <span class="inline-block px-2 py-1 rounded text-xs font-medium 
                                    <?php
                                        switch ($row['status']) {
                                            case 'completed':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'pending':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'failed':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reportData)): ?>
                            <tr>
                                <td colspan="8" class="py-4 text-center text-gray-500">No transaction logs found for the selected period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Include Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Generate the chart based on report type
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('reportChart').getContext('2d');
    
    <?php if ($reportType === 'sales' || $reportType === 'invoices'): ?>
    // Sales and revenue chart
    var chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($summaryData['labels']); ?>,
            datasets: [
                {
                    label: 'Sales',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    data: <?php echo json_encode($summaryData['salesData']); ?>,
                    yAxisID: 'y',
                    fill: true
                },
                {
                    label: 'Revenue ($)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    data: <?php echo json_encode($summaryData['revenueData']); ?>,
                    yAxisID: 'y1',
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Number of Sales'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                    title: {
                        display: true,
                        text: 'Revenue ($)'
                    }
                }
            }
        }
    });
    <?php elseif ($reportType === 'customers'): ?>
    // Customer activity chart - pie chart for top customers
    var customerData = <?php 
        // Get only top 5 customers for pie chart
        $topCustomers = array_slice($reportData, 0, 5);
        $labels = [];
        $values = [];
        
        foreach ($topCustomers as $row) {
            $labels[] = $row['username'];
            $values[] = $row['total_spent'];
        }
        
        echo json_encode([
            'labels' => $labels,
            'values' => $values
        ]); 
    ?>;
    
    var chart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: customerData.labels,
            datasets: [{
                data: customerData.values,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.7)',
                    'rgba(16, 185, 129, 0.7)',
                    'rgba(124, 58, 237, 0.7)',
                    'rgba(239, 68, 68, 0.7)',
                    'rgba(245, 158, 11, 0.7)'
                ],
                borderColor: [
                    'rgba(59, 130, 246, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(124, 58, 237, 1)',
                    'rgba(239, 68, 68, 1)',
                    'rgba(245, 158, 11, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                },
                title: {
                    display: true,
                    text: 'Top 5 Customers by Spending'
                }
            }
        }
    });
    <?php elseif ($reportType === 'transactions'): ?>
    // Transactions status chart - doughnut chart
    var chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Pending', 'Failed'],
            datasets: [{
                data: [
                    <?php 
                        $completed = 0;
                        $pending = 0;
                        $failed = 0;
                        
                        foreach ($reportData as $row) {
                            if ($row['status'] === 'completed') $completed++;
                            elseif ($row['status'] === 'pending') $pending++;
                            elseif ($row['status'] === 'failed') $failed++;
                        }
                        
                        echo $completed . ', ' . $pending . ', ' . $failed;
                    ?>
                ],
                backgroundColor: [
                    'rgba(16, 185, 129, 0.7)',
                    'rgba(59, 130, 246, 0.7)',
                    'rgba(239, 68, 68, 0.7)'
                ],
                borderColor: [
                    'rgba(16, 185, 129, 1)',
                    'rgba(59, 130, 246, 1)',
                    'rgba(239, 68, 68, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                },
                title: {
                    display: true,
                    text: 'Transaction Status Distribution'
                }
            }
        }
    });
    <?php endif; ?>
});