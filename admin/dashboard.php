<?php
/**
 * Admin Dashboard
 * Main control panel for administrators
 */

// Initialize session and check for admin privileges
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !isAdmin()) {
    header("Location: index.php");
    exit;
}

// Get admin user details
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Dashboard statistics
$totalUsers = countUsers();
$totalCustomers = countUsersByRole('customer');
$totalStaff = countUsersByRole('staff');
$totalServices = countServices();
$totalActiveServices = countActiveServices();

// Revenue statistics
$todayRevenue = getRevenue('today');
$weekRevenue = getRevenue('week');
$monthRevenue = getRevenue('month');
$yearRevenue = getRevenue('year');

// Invoice statistics
$pendingInvoices = countInvoicesByStatus('pending');
$outstandingInvoices = countInvoicesByStatus('outstanding');
$paidInvoices = countInvoicesByStatus('paid');
$totalInvoices = $pendingInvoices + $outstandingInvoices + $paidInvoices;

// Recent transactions
$recentTransactions = getRecentTransactions(10);

// Recent users
$recentUsers = getRecentUsers(5);

// Top services
$topServices = getTopServices(5);

// Page title
$pageTitle = "Admin Dashboard";

// Include header
include_once '../templates/header.php';
?>

<div class="flex min-h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include_once 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 p-4 md:p-6 lg:p-8">
        <!-- Dashboard Header -->
        <div class="mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Dashboard</h1>
            <p class="text-gray-600">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</p>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- Total Revenue -->
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Revenue (Month)</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo formatCurrency($monthRevenue); ?></p>
                    </div>
                </div>
                <div class="mt-2">
                    <p class="text-xs text-gray-500">
                        <span class="<?php echo $monthRevenue > $weekRevenue ? 'text-green-500' : 'text-red-500'; ?>">
                            <?php echo $monthRevenue > $weekRevenue ? '↑' : '↓'; ?> 
                            <?php echo calculatePercentageChange($weekRevenue, $monthRevenue); ?>%
                        </span> 
                        since last week
                    </p>
                </div>
            </div>

            <!-- Total Users -->
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Customers</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo $totalCustomers; ?></p>
                    </div>
                </div>
                <div class="mt-2">
                    <p class="text-xs text-gray-500">
                        <span class="text-green-500">
                            <?php 
                                $newCustomers = getNewUsers('week', 'customer');
                                echo "+$newCustomers"; 
                            ?>
                        </span> 
                        new this week
                    </p>
                </div>
            </div>

            <!-- Total Services -->
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Services</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo $totalActiveServices; ?> <span class="text-sm text-gray-500">/ <?php echo $totalServices; ?></span></p>
                    </div>
                </div>
                <div class="mt-2">
                    <p class="text-xs text-gray-500">
                        <span class="text-purple-500">
                            <?php echo calculateServiceUtilizationRate($totalActiveServices, $totalServices); ?>%
                        </span> 
                        utilization rate
                    </p>
                </div>
            </div>

            <!-- Invoice Status -->
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Pending/Outstanding</p>
                        <p class="text-lg font-semibold text-gray-800">
                            <?php echo $pendingInvoices; ?> / <?php echo $outstandingInvoices; ?>
                        </p>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="flex rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-l-full" style="width: <?php echo calculatePercentage($paidInvoices, $totalInvoices); ?>%"></div>
                            <div class="bg-yellow-400 h-2" style="width: <?php echo calculatePercentage($pendingInvoices, $totalInvoices); ?>%"></div>
                            <div class="bg-red-500 h-2 rounded-r-full" style="width: <?php echo calculatePercentage($outstandingInvoices, $totalInvoices); ?>%"></div>
                        </div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                        <span>Paid: <?php echo $paidInvoices; ?></span>
                        <span>Total: <?php echo $totalInvoices; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Revenue Chart -->
            <div class="bg-white p-4 rounded-lg shadow">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Revenue Overview</h2>
                <div class="relative" style="height: 300px;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- User Activity Chart -->
            <div class="bg-white p-4 rounded-lg shadow">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">User Activity</h2>
                <div class="relative" style="height: 300px;">
                    <canvas id="userActivityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Transactions and Top Services -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Recent Transactions -->
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Recent Transactions</h2>
                    <a href="invoices.php" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($recentTransactions)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500">No recent transactions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <a href="invoices.php?view=<?php echo $transaction['invoice_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                #<?php echo $transaction['invoice_number']; ?>
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-600">
                                                    <?php echo strtoupper(substr($transaction['customer_name'], 0, 1)); ?>
                                                </div>
                                                <div class="ml-2">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($transaction['customer_name']); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($transaction['customer_email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo formatCurrency($transaction['amount']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php 
                                                switch($transaction['status']) {
                                                    case 'paid':
                                                        echo 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'pending':
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'outstanding':
                                                        echo 'bg-red-100 text-red-800';
                                                        break;
                                                    default:
                                                        echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?php echo ucfirst($transaction['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo formatDate($transaction['transaction_date']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Services and Recent Users Tabs -->
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="mb-4 border-b">
                    <ul class="flex flex-wrap -mb-px text-sm font-medium" id="dashboardTabs" role="tablist">
                        <li class="mr-2" role="presentation">
                            <button class="inline-block p-4 border-b-2 border-blue-600 rounded-t-lg active" 
                                   id="top-services-tab" 
                                   data-tabs-target="#top-services" 
                                   type="button" 
                                   role="tab" 
                                   aria-controls="top-services" 
                                   aria-selected="true">
                                Top Services
                            </button>
                        </li>
                        <li class="mr-2" role="presentation">
                            <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300" 
                                   id="recent-users-tab" 
                                   data-tabs-target="#recent-users" 
                                   type="button" 
                                   role="tab" 
                                   aria-controls="recent-users" 
                                   aria-selected="false">
                                Recent Users
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div id="dashboardTabContent">
                    <!-- Top Services Tab -->
                    <div class="block" id="top-services" role="tabpanel" aria-labelledby="top-services-tab">
                        <ul class="divide-y divide-gray-200">
                            <?php if (empty($topServices)): ?>
                                <li class="py-3 text-center text-sm text-gray-500">No services found</li>
                            <?php else: ?>
                                <?php foreach ($topServices as $index => $service): ?>
                                    <li class="py-3">
                                        <div class="flex items-center space-x-4">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-<?php echo getRandomColor(); ?>-100 flex items-center justify-center">
                                                <span class="text-<?php echo getRandomColor(); ?>-600 text-lg font-semibold"><?php echo $index + 1; ?></span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                    <?php echo htmlspecialchars($service['name']); ?>
                                                </p>
                                                <p class="text-sm text-gray-500 truncate">
                                                    <?php echo htmlspecialchars($service['category']); ?>
                                                </p>
                                            </div>
                                            <div class="inline-flex items-center space-x-3">
                                                <span class="text-sm font-semibold text-gray-900"><?php echo formatCurrency($service['price']); ?></span>
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    <?php echo $service['usage_count']; ?> uses
                                                </span>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                        <div class="mt-4 text-center">
                            <a href="services.php" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                View All Services
                            </a>
                        </div>
                    </div>
                    
                    <!-- Recent Users Tab -->
                    <div class="hidden" id="recent-users" role="tabpanel" aria-labelledby="recent-users-tab">
                        <ul class="divide-y divide-gray-200">
                            <?php if (empty($recentUsers)): ?>
                                <li class="py-3 text-center text-sm text-gray-500">No recent users found</li>
                            <?php else: ?>
                                <?php foreach ($recentUsers as $user): ?>
                                    <li class="py-3">
                                        <div class="flex items-center space-x-4">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-600">
                                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                    <?php echo htmlspecialchars($user['name']); ?>
                                                </p>
                                                <p class="text-sm text-gray-500 truncate">
                                                    <?php echo htmlspecialchars($user['email']); ?>
                                                </p>
                                            </div>
                                            <div class="inline-flex items-center">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                    <?php 
                                                    switch($user['role']) {
                                                        case 'admin':
                                                            echo 'bg-purple-100 text-purple-800';
                                                            break;
                                                        case 'staff':
                                                            echo 'bg-blue-100 text-blue-800';
                                                            break;
                                                        case 'customer':
                                                            echo 'bg-green-100 text-green-800';
                                                            break;
                                                        default:
                                                            echo 'bg-gray-100 text-gray-800';
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                        <div class="mt-4 text-center">
                            <a href="users.php" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                View All Users
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="services.php?action=add" class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                    <div class="h-12 w-12 rounded-full bg-blue-200 flex items-center justify-center mb-2">
                        <svg class="h-6 w-6 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-blue-700">Add Service</span>
                </a>
                
                <a href="invoices.php?action=create" class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                    <div class="h-12 w-12 rounded-full bg-purple-200 flex items-center justify-center mb-2">
                        <svg class="h-6 w-6 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-purple-700">Create Invoice</span>
                </a>
                
                <a href="users.php?action=add" class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                    <div class="h-12 w-12 rounded-full bg-green-200 flex items-center justify-center mb-2">
                        <svg class="h-6 w-6 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-green-700">Add User</span>
                </a>
                
                <a href="reports.php" class="flex flex-col items-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors">
                    <div class="h-12 w-12 rounded-full bg-yellow-200 flex items-center justify-center mb-2">
                        <svg class="h-6 w-6 text-yellow-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-yellow-700">View Reports</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Charts & other JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize tabs
document.addEventListener('DOMContentLoaded', function() {
    // Tabs functionality
    const tabButtons = document.querySelectorAll('[data-tabs-target]');
    const tabContents = document.querySelectorAll('[role="tabpanel"]');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-tabs-target');
            
            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active state from all buttons
            tabButtons.forEach(btn => {
                btn.classList.remove('active', 'border-blue-600');
                btn.classList.add('border-transparent');
                btn.setAttribute('aria-selected', 'false');
            });
            
            // Show the selected tab content
            document.querySelector(targetId).classList.remove('hidden');
            
            // Set active state on the clicked button
            button.classList.add('active', 'border-blue-600');
            button.classList.remove('border-transparent');
            button.setAttribute('aria-selected', 'true');
        });
    });
    
    // Revenue Chart
    const revenueChart = new Chart(
        document.getElementById('revenueChart').getContext('2d'),
        {
            type: 'line',
            data: {
                labels: <?php echo json_encode(getLastSixMonths()); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(getMonthlyRevenue(6)); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '<?php echo CURRENCY_SYMBOL; ?>' + value;
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: <?php echo CURRENCY_SYMBOL; ?>' + context.parsed.y;
                            }
                        }
                    }
                }
            }
        }
    );
    
    // User Activity Chart
    const userActivityChart = new Chart(
        document.getElementById('userActivityChart').getContext('2d'),
        {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(getLastSixMonths()); ?>,
                datasets: [
                    {
                        label: 'New Users',
                        data: <?php echo json_encode(getMonthlyNewUsers(6)); ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    },
                    {
                        label: 'Active Users',
                        data: <?php echo json_encode(getMonthlyActiveUsers(6)); ?>,
                        backgroundColor: 'rgba(245, 158, 11, 0.7)',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        }
    );
});
</script>

<?php
/**
 * Helper functions for dashboard data
 */

/**
 * Get last six months as labels for charts
 */
function getLastSixMonths() {
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $months[] = date('M Y', strtotime("-$i months"));
    }
    return $months;
}

/**
 * Get monthly revenue for the last n months
 */
function getMonthlyRevenue($months = 6) {
    global $conn;
    
    $data = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $startDate = date('Y-m-01', strtotime("-$i months"));
        $endDate = date('Y-m-t', strtotime("-$i months"));
        
        $query = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date BETWEEN ? AND ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $data[] = $row['total'];
    }
    
    return $data;
}

/**
 * Get monthly new users for the last n months
 */
function getMonthlyNewUsers($months = 6) {
    global $conn;
    
    $data = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $startDate = date('Y-m-01', strtotime("-$i months"));
        $endDate = date('Y-m-t', strtotime("-$i months"));
        
        $query = "SELECT COUNT(*) as total FROM users WHERE created_at BETWEEN ? AND ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $data[] = $row['total'];
    }
    
    return $data;
}

/**
 * Get monthly active users for the last n months
 */
function getMonthlyActiveUsers($months = 6) {
    global $conn;
    
    $data = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $startDate = date('Y-m-01', strtotime("-$i months"));
        $endDate = date('Y-m-t', strtotime("-$i months"));
        
        $query = "SELECT COUNT(DISTINCT user_id) as total FROM user_logins WHERE login_time BETWEEN ? AND ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $data[] = $row['total'];
    }
    
    return $data;
}

/**
 * Count users in the system
 */
function countUsers() {
    global $conn;
    
    $query = "SELECT COUNT(*) as total FROM users";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

/**
 * Count users by role
 */
function countUsersByRole($role) {
    global $conn;
    
    $query = "SELECT COUNT(*) as total FROM users WHERE role = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

/**
 * Count services in the system
 */
function countServices() {
    global $conn;
    
    $query = "SELECT COUNT(*) as total FROM services";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

/**
 * Count active services (services with at least one purchase)
 */
function countActiveServices() {
    global $conn;
    
    $query = "SELECT COUNT(DISTINCT service_id) as total FROM invoice_items";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

/**
 * Get revenue for a specific time period
 */
function getRevenue($period = 'today') {
    global $conn;
    
    $startDate = '';
    $endDate = date('Y-m-d H:i:s');
    
    switch ($period) {
        case 'today':
            $startDate = date('Y-m-d 00:00:00');
            break;
        case 'week':
            $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
            break;
        case 'month':
            $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
            break;
        case 'year':
            $startDate = date('Y-m-d 00:00:00', strtotime('-365 days'));
            break;
        default:
            $startDate = date('Y-m-d 00:00:00');
            break;
    }
    
    $query = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

/**
 * Count invoices by status
 */
function countInvoicesByStatus($status) {
    global $conn;
    
    $query = "SELECT COUNT(*) as total FROM invoices WHERE status = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

/**
 * Get recent transactions
 */
function getRecentTransactions($limit = 10) {
    global $conn;
    
    $query = "SELECT i.id as invoice_id, i.invoice_number, i.status, i.created_at as transaction_date, 
              i.total_amount as amount, u.name as customer_name, u.email as customer_email
              FROM invoices i
              JOIN users u ON i.user_id = u.id
              ORDER BY i.created_at DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    return $transactions;
}

/**
 * Get recent users
 */
function getRecentUsers($limit = 5) {
    global $conn;
    
    $query = "SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    return $users;
}

/**
 * Get new users count for a specific time period
 */
function getNewUsers($period = 'week', $role = null) {
    global $conn;
    
    $startDate = '';
    $endDate = date('Y-m-d H:i:s');
    
    switch ($period) {
        case 'week':
            $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
            break;
        case 'month':
            $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
            break;
        default:
            $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
            break;
    }
    
    if ($role) {
        $query = "SELECT COUNT(*) as total FROM users WHERE created_at BETWEEN ? AND ? AND role = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $startDate, $endDate, $role);
    } else {
        $query = "SELECT COUNT(*) as total FROM users WHERE created_at BETWEEN ? AND ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

/**
 * Get top services
 */
function getTopServices($limit = 5) {
    global $conn;
    
    $query = "SELECT s.id, s.name, s.price, c.name as category, COUNT(ii.id) as usage_count
              FROM services s
              LEFT JOIN invoice_items ii ON s.id = ii.service_id
              LEFT JOIN categories c ON s.category_id = c.id
              GROUP BY s.id
              ORDER BY usage_count DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $services = [];
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    
    return $services;
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

/**
 * Format date
 */
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

/**
 * Calculate percentage
 */
function calculatePercentage($value, $total) {
    if ($total == 0) return 0;
    return round(($value / $total) * 100);
}

/**
 * Calculate percentage change
 */
function calculatePercentageChange($oldValue, $newValue) {
    if ($oldValue == 0) return 0;
    return round((($newValue - $oldValue) / $oldValue) * 100);
}

/**
 * Calculate service utilization rate
 */
function calculateServiceUtilizationRate($activeServices, $totalServices) {
    if ($totalServices == 0) return 0;
    return round(($activeServices / $totalServices) * 100);
}

/**
 * Get random color for UI elements
 */
function getRandomColor() {
    $colors = ['blue', 'green', 'purple', 'yellow', 'red', 'pink', 'indigo'];
    return $colors[array_rand($colors)];
}

// Include footer
include_once '../templates/footer.php';
?>