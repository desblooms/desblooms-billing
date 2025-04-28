 
<?php
/**
 * Header Template
 * 
 * Includes the HTML head, navigation, and header components
 * Used across the Digital Service Billing App
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and helper functions
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';

// Set page title if not already set
if (!isset($page_title)) {
    $page_title = 'Digital Service Billing';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo htmlspecialchars($page_title); ?> - Digital Service Billing</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/mobile.css">
    
    <!-- PWA meta tags -->
    <link rel="manifest" href="<?php echo BASE_URL; ?>/manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/assets/icons/icon-192x192.png">
    
    <!-- PWA script -->
    <script src="<?php echo BASE_URL; ?>/assets/js/pwa.js" defer></script>
    
    <!-- Main JavaScript -->
    <script src="<?php echo BASE_URL; ?>/assets/js/app.js" defer></script>
    
    <?php if (strpos($current_page, 'admin') !== false): ?>
    <!-- Admin specific styles and scripts -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
    <script src="<?php echo BASE_URL; ?>/assets/js/admin.js" defer></script>
    <?php endif; ?>
</head>
<body class="bg-gray-100 text-gray-900 min-h-screen flex flex-col">
    <!-- Offline notification (for PWA) -->
    <div id="offline-notification" class="hidden fixed top-0 w-full bg-yellow-500 text-white p-2 text-center z-50">
        You are currently offline. Some features may be unavailable.
    </div>

    <!-- Mobile header -->
    <header class="bg-indigo-600 text-white shadow-md">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-3">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="<?php echo BASE_URL; ?>" class="font-bold text-xl">
                        Digital<span class="text-indigo-200">Billing</span>
                    </a>
                </div>
                
                <!-- Mobile navigation toggle -->
                <div class="md:hidden">
                    <button id="mobile-menu-toggle" class="focus:outline-none">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
                
                <!-- Desktop navigation -->
                <nav class="hidden md:flex space-x-6">
                    <a href="<?php echo BASE_URL; ?>" class="<?php echo $current_page === 'index.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Home</a>
                    <a href="<?php echo BASE_URL; ?>/services.php" class="<?php echo $current_page === 'services.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Services</a>
                    
                    <?php if ($is_logged_in): ?>
                        <a href="<?php echo BASE_URL; ?>/invoices.php" class="<?php echo $current_page === 'invoices.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Invoices</a>
                        <a href="<?php echo BASE_URL; ?>/pending.php" class="<?php echo $current_page === 'pending.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Pending</a>
                        <a href="<?php echo BASE_URL; ?>/outstanding.php" class="<?php echo $current_page === 'outstanding.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Outstanding</a>
                        <a href="<?php echo BASE_URL; ?>/support.php" class="<?php echo $current_page === 'support.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Support</a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/support.php" class="<?php echo $current_page === 'support.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Support</a>
                    <?php endif; ?>
                </nav>
                
                <!-- User actions -->
                <div class="hidden md:flex items-center space-x-4">
                    <?php if ($is_logged_in): ?>
                        <!-- Cart icon with counter -->
                        <a href="<?php echo BASE_URL; ?>/cart.php" class="relative group">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <?php 
                            // Get cart item count
                            $cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
                            if ($cart_count > 0): 
                            ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo $cart_count; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                        
                        <!-- User dropdown -->
                        <div class="relative" id="user-dropdown-container">
                            <button id="user-dropdown-toggle" class="flex items-center space-x-1 focus:outline-none">
                                <span><?php echo htmlspecialchars($user_name); ?></span>
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div id="user-dropdown" class="absolute right-0 mt-2 w-48 bg-white text-gray-900 rounded shadow-lg py-1 hidden z-10">
                                <a href="<?php echo BASE_URL; ?>/profile.php" class="block px-4 py-2 hover:bg-indigo-100 transition">Profile</a>
                                
                                <?php if ($user_role === 'admin'): ?>
                                <a href="<?php echo BASE_URL; ?>/admin/" class="block px-4 py-2 hover:bg-indigo-100 transition">Admin Dashboard</a>
                                <?php endif; ?>
                                
                                <div class="border-t border-gray-200 my-1"></div>
                                <a href="<?php echo BASE_URL; ?>/logout.php" class="block px-4 py-2 hover:bg-indigo-100 transition">Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/login.php" class="hover:text-indigo-200 transition">Login</a>
                        <a href="<?php echo BASE_URL; ?>/register.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md hover:bg-indigo-50 transition">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Mobile navigation menu (hidden by default) -->
        <div id="mobile-menu" class="hidden md:hidden bg-indigo-700 px-4 pb-4">
            <nav class="flex flex-col space-y-3 pt-2 pb-3">
                <a href="<?php echo BASE_URL; ?>" class="<?php echo $current_page === 'index.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Home</a>
                <a href="<?php echo BASE_URL; ?>/services.php" class="<?php echo $current_page === 'services.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Services</a>
                
                <?php if ($is_logged_in): ?>
                    <a href="<?php echo BASE_URL; ?>/invoices.php" class="<?php echo $current_page === 'invoices.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Invoices</a>
                    <a href="<?php echo BASE_URL; ?>/pending.php" class="<?php echo $current_page === 'pending.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Pending</a>
                    <a href="<?php echo BASE_URL; ?>/outstanding.php" class="<?php echo $current_page === 'outstanding.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Outstanding</a>
                    <a href="<?php echo BASE_URL; ?>/support.php" class="<?php echo $current_page === 'support.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Support</a>
                    <a href="<?php echo BASE_URL; ?>/cart.php" class="<?php echo $current_page === 'cart.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition flex items-center">
                        Cart
                        <?php if ($cart_count > 0): ?>
                        <span class="ml-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo $cart_count; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/profile.php" class="<?php echo $current_page === 'profile.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Profile</a>
                    
                    <?php if ($user_role === 'admin'): ?>
                    <a href="<?php echo BASE_URL; ?>/admin/" class="<?php echo strpos($current_page, 'admin') !== false ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Admin Dashboard</a>
                    <?php endif; ?>
                    
                    <div class="border-t border-indigo-600 my-2"></div>
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="hover:text-indigo-200 transition">Logout</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/support.php" class="<?php echo $current_page === 'support.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Support</a>
                    <div class="border-t border-indigo-600 my-2"></div>
                    <a href="<?php echo BASE_URL; ?>/login.php" class="<?php echo $current_page === 'login.php' ? 'font-bold' : ''; ?> hover:text-indigo-200 transition">Login</a>
                    <a href="<?php echo BASE_URL; ?>/register.php" class="<?php echo $current_page === 'register.php' ? 'font-bold' : ''; ?> bg-white text-indigo-600 px-3 py-1 rounded-md hover:bg-indigo-50 transition inline-block w-max">Sign Up</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Page alerts container -->
    <div id="alerts-container" class="container mx-auto px-4 mt-4">
        <?php
        // Display flash messages if they exist
        if (isset($_SESSION['success_message'])) {
            echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">';
            echo '<p>' . htmlspecialchars($_SESSION['success_message']) . '</p>';
            echo '</div>';
            unset($_SESSION['success_message']);
        }
        
        if (isset($_SESSION['error_message'])) {
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
            echo '<p>' . htmlspecialchars($_SESSION['error_message']) . '</p>';
            echo '</div>';
            unset($_SESSION['error_message']);
        }
        ?>
    </div>

    <!-- Main content wrapper -->
    <main class="flex-grow container mx-auto px-4 py-6">
        <!-- Page title section -->
        <?php if (isset($show_page_title) && $show_page_title): ?>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if (isset($page_description)): ?>
            <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($page_description); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Page content starts here -->