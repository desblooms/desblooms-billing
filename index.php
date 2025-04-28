<?php
/**
 * Digital Service Billing Mobile App
 * Main Entry Point (index.php)
 * 
 * This is the main entry point for the application.
 * It handles the initial routing and displays the home page.
 */

// Start session
session_start();

// Include configuration and database connection
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
$user = null;
$isLoggedIn = false;

if (isset($_SESSION['user_id'])) {
    $isLoggedIn = true;
    $user = getUserById($_SESSION['user_id']);
}

// Handle page routing
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Include header
include 'templates/header.php';

// Main content
?>

<div class="flex flex-col min-h-screen bg-gray-100">
    <!-- Mobile Navigation Bar -->
    <nav class="sticky top-0 z-50 bg-white shadow-md px-4 py-3 flex justify-between items-center md:hidden">
        <div class="flex items-center">
            <button id="mobile-menu-button" class="text-gray-600 focus:outline-none">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <h1 class="ml-4 text-xl font-bold text-blue-600">Digital Billing</h1>
        </div>
        <?php if ($isLoggedIn) : ?>
            <div class="flex items-center">
                <a href="profile.php" class="block p-2 rounded-full bg-gray-200">
                    <svg class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </a>
            </div>
        <?php else : ?>
            <div class="flex items-center">
                <a href="login.php" class="text-blue-600 font-medium">Login</a>
            </div>
        <?php endif; ?>
    </nav>

    <!-- Mobile Sidebar Menu (Hidden by default) -->
    <div id="mobile-sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full w-64 bg-white z-50 shadow-lg transition duration-300 ease-in-out md:hidden">
        <div class="px-4 py-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-blue-600">Menu</h2>
                <button id="close-sidebar" class="text-gray-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <?php if ($isLoggedIn) : ?>
                <div class="mb-6 border-b pb-4">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">
                            <?php echo substr($user['name'] ?? 'U', 0, 1); ?>
                        </div>
                        <div class="ml-3">
                            <p class="font-medium"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></p>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <ul class="space-y-2">
                <li>
                    <a href="index.php" class="block py-2 text-gray-800 hover:text-blue-600">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Home
                        </div>
                    </a>
                </li>
                <li>
                    <a href="services.php" class="block py-2 text-gray-800 hover:text-blue-600">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            Services
                        </div>
                    </a>
                </li>
                <?php if ($isLoggedIn) : ?>
                    <li>
                        <a href="invoices.php" class="block py-2 text-gray-800 hover:text-blue-600">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Invoices
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="pending.php" class="block py-2 text-gray-800 hover:text-blue-600">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Pending
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="outstanding.php" class="block py-2 text-gray-800 hover:text-blue-600">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                Outstanding
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="support.php" class="block py-2 text-gray-800 hover:text-blue-600">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                                Support
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="block py-2 text-gray-800 hover:text-blue-600">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Profile
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php" class="block py-2 text-gray-800 hover:text-blue-600">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Logout
                            </div>
                        </a>
                    </li>
                <?php else : ?>
                    <li>
                        <a href="login.php" class="block py-2 text-gray-800 hover:text-blue-600">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                </svg>
                                Login
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="register.php" class="block py-2 text-gray-800 hover:text-blue-600">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                                Register
                            </div>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Desktop Sidebar (visible on medium and larger screens) -->
    <div class="hidden md:flex">
        <aside class="w-64 bg-white shadow-md h-screen sticky top-0">
            <div class="px-6 py-6">
                <h1 class="text-2xl font-bold text-blue-600 mb-6">Digital Billing</h1>
                <?php if ($isLoggedIn) : ?>
                    <div class="mb-6 border-b pb-4">
                        <div class="flex items-center mb-3">
                            <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">
                                <?php echo substr($user['name'] ?? 'U', 0, 1); ?>
                            </div>
                            <div class="ml-3">
                                <p class="font-medium"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <ul class="space-y-2">
                    <li>
                        <a href="index.php" class="block px-4 py-2 rounded-lg <?php echo $page === 'home' ? 'bg-blue-100 text-blue-600' : 'text-gray-800 hover:bg-gray-100'; ?>">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                Home
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="services.php" class="block px-4 py-2 rounded-lg <?php echo $page === 'services' ? 'bg-blue-100 text-blue-600' : 'text-gray-800 hover:bg-gray-100'; ?>">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Services
                            </div>
                        </a>
                    </li>
                    <?php if ($isLoggedIn) : ?>
                        <li>
                            <a href="invoices.php" class="block px-4 py-2 rounded-lg <?php echo $page === 'invoices' ? 'bg-blue-100 text-blue-600' : 'text-gray-800 hover:bg-gray-100'; ?>">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Invoices
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="pending.php" class="block px-4 py-2 rounded-lg <?php echo $page === 'pending' ? 'bg-blue-100 text-blue-600' : 'text-gray-800 hover:bg-gray-100'; ?>">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Pending
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="outstanding.php" class="block px-4 py-2 rounded-lg <?php echo $page === 'outstanding' ? 'bg-blue-100 text-blue-600' : 'text-gray-800 hover:bg-gray-100'; ?>">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    Outstanding
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="support.php" class="block px-4 py-2 rounded-lg <?php echo $page === 'support' ? 'bg-blue-100 text-blue-600' : 'text-gray-800 hover:bg-gray-100'; ?>">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                    </svg>
                                    Support
                                </div>
                            </a>
                        </li>
                        <li class="mt-6">
                            <a href="profile.php" class="block px-4 py-2 rounded-lg <?php echo $page === 'profile' ? 'bg-blue-100 text-blue-600' : 'text-gray-800 hover:bg-gray-100'; ?>">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    Profile
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="logout.php" class="block px-4 py-2 rounded-lg text-gray-800 hover:bg-gray-100">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Logout
                                </div>
                            </a>
                        </li>
                    <?php else : ?>
                        <li class="mt-6">
                            <a href="login.php" class="block px-4 py-2 rounded-lg <?php echo $page === 'login' ? 'bg-blue-100 text-blue-600' : 'text-gray-800 hover:bg-gray-100'; ?>">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                    </svg>
                                    Login
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="register.php" class="block px-4 py-2 rounded-lg <?php echo $page === 'register' ? 'bg-blue-100 text-blue-600' : 'text-gray-800 hover:bg-gray-100'; ?>">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                    </svg>
                                    Register
                                </div>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </aside>

        <!-- Main Content for Desktop -->
        <main class="flex-1 p-6 bg-gray-100">
            <?php
            // Display main content based on the page parameter
            switch ($page) {
                case 'home':
                default:
                    include 'pages/home.php';
                    break;
            }
            ?>
        </main>
    </div>

    <!-- Main Content for Mobile -->
    <main class="flex-1 p-4 bg-gray-100 md:hidden">
        <?php
        // Display main content based on the page parameter
        switch ($page) {
            case 'home':
            default:
                include 'pages/home.php';
                break;
        }
        ?>
    </main>
</div>

<?php
// Include footer
include 'templates/footer.php';
?>

<script>
    // Mobile menu toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const closeSidebarButton = document.getElementById('close-sidebar');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        
        mobileMenuButton.addEventListener('click', function() {
            mobileSidebar.classList.toggle('-translate-x-full');
        });
        
        closeSidebarButton.addEventListener('click', function() {
            mobileSidebar.classList.add('-translate-x-full');
        });
        
        // Close sidebar when clicking outside of it
        document.addEventListener('click', function(event) {
            const isClickInsideSidebar = mobileSidebar.contains(event.target);
            const isClickOnMenuButton = mobileMenuButton.contains(event.target);
            
            if (!isClickInsideSidebar && !isClickOnMenuButton && !mobileSidebar.classList.contains('-translate-x-full')) {
                mobileSidebar.classList.add('-translate-x-full');
            }
        });
        
        // Initialize PWA functionality if available
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/service-worker.js').then(function(registration) {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                }, function(err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    });
</script>