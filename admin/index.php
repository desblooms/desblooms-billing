 
<?php
/**
 * Admin Index/Login Page
 * 
 * This serves as the entry point for the admin area of the Digital Service Billing App.
 * It handles admin authentication and displays the login form for unauthenticated users
 * or redirects to the admin dashboard for authenticated admin users.
 */

// Include necessary configuration and functions
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    // Already logged in as admin, redirect to dashboard
    header('Location: dashboard.php');
    exit;
}

// Initialize variables
$error = '';
$email = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        // Attempt to authenticate as admin
        $user = authenticate_user($email, $password);
        
        if ($user && $user['role'] === 'admin') {
            // Valid admin login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Set JWT token for API calls
            $jwt_token = generate_jwt_token($user);
            $_SESSION['jwt_token'] = $jwt_token;
            
            // Redirect to admin dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            // Invalid credentials or not an admin
            $error = 'Invalid credentials or insufficient permissions';
        }
    }
}

// Page title
$page_title = 'Admin Login | Digital Service Billing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#2563eb">
    <link rel="manifest" href="../manifest.json">
    <link rel="apple-touch-icon" href="../assets/icons/icon-192x192.png">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    Admin Control Panel
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Digital Service Billing App
                </p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="rounded-md bg-red-50 p-4 mt-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">
                                Error
                            </h3>
                            <div class="mt-2 text-sm text-red-700">
                                <p><?php echo $error; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <form class="mt-8 space-y-6" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                <input type="hidden" name="remember" value="true">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="email" class="sr-only">Email address</label>
                        <input id="email" name="email" type="email" autocomplete="email" required 
                            class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 
                            placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 
                            focus:border-blue-500 focus:z-10 sm:text-sm" 
                            placeholder="Email address" value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                    <div>
                        <label for="password" class="sr-only">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required 
                            class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 
                            placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-blue-500 
                            focus:border-blue-500 focus:z-10 sm:text-sm" 
                            placeholder="Password">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox" 
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="remember-me" class="ml-2 block text-sm text-gray-900">
                            Remember me
                        </label>
                    </div>

                    <div class="text-sm">
                        <a href="../forgot-password.php" class="font-medium text-blue-600 hover:text-blue-500">
                            Forgot your password?
                        </a>
                    </div>
                </div>

                <div>
                    <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm 
                        font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none 
                        focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-lock text-blue-500 group-hover:text-blue-400"></i>
                        </span>
                        Sign in
                    </button>
                </div>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    <a href="../index.php" class="font-medium text-blue-600 hover:text-blue-500">
                        <i class="fas fa-arrow-left mr-1"></i> Return to main site
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/admin.js"></script>
    
    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('../service-worker.js').then(function(registration) {
                    console.log('ServiceWorker registration successful');
                }, function(err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
</body>
</html>