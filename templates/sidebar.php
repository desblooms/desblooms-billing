 
<?php
/**
 * Sidebar Navigation Template for Digital Service Billing Mobile App
 * 
 * This template handles the sidebar navigation which adapts based on user role
 * (Admin, Customer, Staff) and provides links to different sections of the app.
 */

// Get current page to highlight active menu item
$current_page = basename($_SERVER['PHP_SELF']);

// Check if user is logged in and get role
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_role = $is_logged_in ? $_SESSION['user_role'] : 'guest';

// Function to determine if menu item is active
function isActive($page_name) {
    global $current_page;
    return ($current_page == $page_name) ? 'active' : '';
}
?>

<div class="sidebar-container transition-all duration-300 h-full bg-gray-800 text-white">
    <!-- Mobile Toggle Button -->
    <div class="flex justify-between items-center p-4 md:hidden">
        <a href="index.php" class="text-white text-xl font-bold">ServiceBill</a>
        <button id="sidebar-toggle" class="text-white focus:outline-none">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
            </svg>
        </button>
    </div>

    <!-- Sidebar Content -->
    <div id="sidebar-content" class="hidden md:block px-4 py-6 overflow-y-auto h-full">
        <!-- User Profile Section -->
        <?php if ($is_logged_in): ?>
            <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-gray-700">
                <div class="w-10 h-10 rounded-full bg-gray-600 flex items-center justify-center">
                    <span class="text-lg"><?= substr($_SESSION['user_name'] ?? 'U', 0, 1); ?></span>
                </div>
                <div>
                    <p class="text-sm font-medium"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                    <p class="text-xs text-gray-400"><?= ucfirst($user_role); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Navigation Links -->
        <nav>
            <ul class="space-y-2">
                <!-- Common Links for all users -->
                <li>
                    <a href="index.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('index.php'); ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        Home
                    </a>
                </li>
                <li>
                    <a href="services.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('services.php'); ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Services
                    </a>
                </li>

                <?php if ($is_logged_in): ?>
                    <!-- Authenticated User Links -->
                    <li>
                        <a href="profile.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('profile.php'); ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            My Profile
                        </a>
                    </li>
                    <li>
                        <a href="invoices.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('invoices.php'); ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            My Invoices
                        </a>
                    </li>
                    <li>
                        <a href="pending.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('pending.php'); ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Pending
                        </a>
                    </li>
                    <li>
                        <a href="outstanding.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('outstanding.php'); ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            Outstanding
                        </a>
                    </li>
                    <li>
                        <a href="support.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('support.php'); ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            Support
                        </a>
                    </li>
                    
                    <?php if ($user_role == 'customer'): ?>
                        <!-- Customer Specific Links -->
                        <li>
                            <a href="cart.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('cart.php'); ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                My Cart
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_role == 'admin' || $user_role == 'staff'): ?>
                        <!-- Admin and Staff Links -->
                        <li class="pt-4 mt-4 border-t border-gray-700">
                            <h3 class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                Administration
                            </h3>
                        </li>
                        <li>
                            <a href="admin/dashboard.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('dashboard.php'); ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                </svg>
                                Dashboard
                            </a>
                        </li>
                        
                        <?php if ($user_role == 'admin'): ?>
                            <!-- Admin Only Links -->
                            <li>
                                <a href="admin/users.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('users.php'); ?>">
                                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                    Manage Users
                                </a>
                            </li>
                            <li>
                                <a href="admin/services.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('services.php'); ?>">
                                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    Manage Services
                                </a>
                            </li>
                            <li>
                                <a href="admin/settings.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('settings.php'); ?>">
                                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    System Settings
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Common Admin/Staff Links -->
                        <li>
                            <a href="admin/invoices.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('invoices.php'); ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Manage Invoices
                            </a>
                        </li>
                        <li>
                            <a href="admin/reports.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('reports.php'); ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                Reports
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Logout Link for All Authenticated Users -->
                    <li class="mt-6">
                        <a href="logout.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-red-700 text-red-300 hover:text-white">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            Logout
                        </a>
                    </li>
                <?php else: ?>
                    <!-- Non-Authenticated User Links -->
                    <li>
                        <a href="login.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('login.php'); ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                            Login
                        </a>
                    </li>
                    <li>
                        <a href="register.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-gray-700 <?= isActive('register.php'); ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                            </svg>
                            Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>

<!-- JavaScript for Mobile Sidebar Toggle -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarContent = document.getElementById('sidebar-content');
    
    // Toggle sidebar on mobile
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebarContent.classList.toggle('hidden');
        });
    }
    
    // Auto-close sidebar when clicking a link on mobile
    const mobileLinks = sidebarContent.querySelectorAll('a');
    const isMobile = window.innerWidth < 768;
    
    if (isMobile) {
        mobileLinks.forEach(link => {
            link.addEventListener('click', function() {
                setTimeout(function() {
                    sidebarContent.classList.add('hidden');
                }, 150);
            });
        });
    }
});
</script>