 
<?php
/**
 * Footer template for Digital Service Billing Mobile App
 * This template is used across the application to maintain consistent footer layout
 */
?>

<!-- Footer Start -->
<footer class="bg-gray-800 text-white mt-auto">
    <!-- Mobile App Navigation - Only visible on mobile -->
    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-gray-900 shadow-lg z-50">
        <div class="flex justify-between items-center px-3 py-2">
            <!-- Home -->
            <a href="index.php" class="flex flex-col items-center p-2 <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-blue-400' : 'text-gray-300'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="text-xs">Home</span>
            </a>
            
            <!-- Services -->
            <a href="services.php" class="flex flex-col items-center p-2 <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'text-blue-400' : 'text-gray-300'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <span class="text-xs">Services</span>
            </a>
            
            <!-- Invoices -->
            <a href="invoices.php" class="flex flex-col items-center p-2 <?php echo basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'text-blue-400' : 'text-gray-300'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span class="text-xs">Invoices</span>
            </a>
            
            <!-- Support -->
            <a href="support.php" class="flex flex-col items-center p-2 <?php echo basename($_SERVER['PHP_SELF']) == 'support.php' ? 'text-blue-400' : 'text-gray-300'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span class="text-xs">Support</span>
            </a>
            
            <!-- Profile -->
            <a href="profile.php" class="flex flex-col items-center p-2 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'text-blue-400' : 'text-gray-300'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span class="text-xs">Profile</span>
            </a>
        </div>
    </div>

    <!-- Main Footer Content -->
    <div class="container mx-auto px-4 py-8 md:py-10 mb-16 md:mb-0">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Company Info -->
            <div class="mb-6 md:mb-0">
                <h3 class="text-lg font-semibold mb-4">Digital Service Billing</h3>
                <p class="text-gray-400 text-sm mb-4">Simplify your digital service management and billing with our comprehensive mobile application.</p>
                <div class="flex space-x-4">
                    <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                        </svg>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/>
                        </svg>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="mb-6 md:mb-0">
                <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                <ul class="text-gray-400 text-sm">
                    <li class="mb-2"><a href="index.php" class="hover:text-white transition-colors duration-300">Home</a></li>
                    <li class="mb-2"><a href="services.php" class="hover:text-white transition-colors duration-300">Services</a></li>
                    <li class="mb-2"><a href="invoices.php" class="hover:text-white transition-colors duration-300">My Invoices</a></li>
                    <li class="mb-2"><a href="pending.php" class="hover:text-white transition-colors duration-300">Pending Payments</a></li>
                    <li class="mb-2"><a href="outstanding.php" class="hover:text-white transition-colors duration-300">Outstanding Bills</a></li>
                </ul>
            </div>

            <!-- Support -->
            <div class="mb-6 md:mb-0">
                <h3 class="text-lg font-semibold mb-4">Support</h3>
                <ul class="text-gray-400 text-sm">
                    <li class="mb-2"><a href="support.php" class="hover:text-white transition-colors duration-300">Help Center</a></li>
                    <li class="mb-2"><a href="support.php?page=tickets" class="hover:text-white transition-colors duration-300">Submit a Ticket</a></li>
                    <li class="mb-2"><a href="support.php?page=faq" class="hover:text-white transition-colors duration-300">FAQs</a></li>
                    <li class="mb-2"><a href="support.php?page=contact" class="hover:text-white transition-colors duration-300">Contact Us</a></li>
                </ul>
            </div>

            <!-- Newsletter (Optional) -->
            <div class="mb-6 md:mb-0">
                <h3 class="text-lg font-semibold mb-4">Stay Updated</h3>
                <p class="text-gray-400 text-sm mb-4">Subscribe to our newsletter for the latest updates and offers.</p>
                <form action="#" method="post" class="flex flex-col md:flex-row">
                    <input type="email" name="email" placeholder="Your email address" required 
                           class="bg-gray-700 text-white px-4 py-2 rounded-lg md:rounded-r-none mb-2 md:mb-0 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg md:rounded-l-none transition-colors duration-300">
                        Subscribe
                    </button>
                </form>
            </div>
        </div>

        <!-- Bottom Footer -->
        <div class="border-t border-gray-700 mt-8 pt-8 text-sm text-gray-400">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    &copy; <?php echo date('Y'); ?> Digital Service Billing. All rights reserved.
                </div>
                <div class="flex space-x-4">
                    <a href="#" class="hover:text-white transition-colors duration-300">Privacy Policy</a>
                    <a href="#" class="hover:text-white transition-colors duration-300">Terms of Service</a>
                    <a href="#" class="hover:text-white transition-colors duration-300">Cookie Policy</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- JavaScript Files -->
<script src="assets/js/app.js"></script>

<?php if (isset($page_specific_js)): ?>
    <!-- Page Specific JavaScript -->
    <?php foreach ($page_specific_js as $js_file): ?>
        <script src="<?php echo $js_file; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<!-- PWA Service Worker Registration -->
<script src="assets/js/pwa.js"></script>

<?php
// Display toast notifications if any exist in session
if (isset($_SESSION['notifications']) && !empty($_SESSION['notifications'])) {
    echo '<div id="toast-container" class="fixed bottom-24 md:bottom-5 right-5 z-50">';
    
    foreach ($_SESSION['notifications'] as $index => $notification) {
        $type_class = '';
        switch ($notification['type']) {
            case 'success':
                $type_class = 'bg-green-500';
                $icon = '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
                break;
            case 'error':
                $type_class = 'bg-red-500';
                $icon = '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
                break;
            case 'warning':
                $type_class = 'bg-yellow-500';
                $icon = '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
                break;
            default:
                $type_class = 'bg-blue-500';
                $icon = '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
        }
        
        echo '<div class="toast ' . $type_class . ' text-white rounded-lg shadow-lg flex items-center p-4 mb-3 transform transition-all duration-300" 
                  style="opacity: 0; transform: translateX(100%);" data-index="' . $index . '">
                <div class="flex-shrink-0 mr-3">' . $icon . '</div>
                <div class="flex-grow">' . $notification['message'] . '</div>
                <button class="ml-4 focus:outline-none" onclick="dismissToast(' . $index . ')">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
              </div>';
    }
    
    echo '</div>';
    
    // Clear notifications after displaying
    unset($_SESSION['notifications']);
}
?>

<script>
// Toast notification system
document.addEventListener('DOMContentLoaded', function() {
    // Show toasts with a slight delay between each
    const toasts = document.querySelectorAll('.toast');
    toasts.forEach((toast, index) => {
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                dismissToast(toast.dataset.index);
            }, 5000);
        }, index * 300);
    });
});

function dismissToast(index) {
    const toast = document.querySelector(`.toast[data-index="${index}"]`);
    if (toast) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        
        setTimeout(() => {
            toast.remove();
        }, 300);
    }
}

// Check if we need to adjust padding for mobile navigation
const body = document.body;
const mobileNav = document.querySelector('.md\\:hidden.fixed.bottom-0');

if (mobileNav && window.innerWidth < 768) {
    body.style.paddingBottom = '64px'; // Add padding to body to account for fixed navigation
}

window.addEventListener('resize', function() {
    if (mobileNav) {
        if (window.innerWidth < 768) {
            body.style.paddingBottom = '64px';
        } else {
            body.style.paddingBottom = '0';
        }
    }
});
</script>

</body>
</html>