 
<?php
/**
 * Profile Page
 * 
 * Allows users to view and update their profile information
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Redirect to login page with return URL
    header("Location: login.php?redirect=profile.php");
    exit();
}

// Get current user data
$userId = $_SESSION['user_id'];
$userData = getUserById($userId);

if (!$userData) {
    // Handle error - user not found
    setFlashMessage('error', 'User account not found.');
    header("Location: index.php");
    exit();
}

// Initialize variables for form values
$name = $userData['name'] ?? '';
$email = $userData['email'] ?? '';
$phone = $userData['phone'] ?? '';
$address = $userData['address'] ?? '';
$company = $userData['company'] ?? '';
$language = $userData['language'] ?? 'en';

// Process form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Validate form inputs
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $company = sanitizeInput($_POST['company'] ?? '');
        $language = sanitizeInput($_POST['language'] ?? 'en');
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid email address.';
        } 
        // Check if new email is already used by another user
        elseif ($email !== $userData['email'] && isEmailExists($email)) {
            $errorMessage = 'Email address is already in use by another account.';
        } 
        else {
            // Update profile
            $updateResult = updateUserProfile($userId, [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'company' => $company,
                'language' => $language
            ]);
            
            if ($updateResult) {
                $successMessage = 'Profile information updated successfully.';
                // Refresh user data after update
                $userData = getUserById($userId);
            } else {
                $errorMessage = 'Failed to update profile. Please try again.';
            }
        }
    } 
    elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate passwords
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMessage = 'All password fields are required.';
        } 
        elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'New password and confirm password do not match.';
        } 
        elseif (strlen($newPassword) < 8) {
            $errorMessage = 'Password must be at least 8 characters long.';
        } 
        elseif (!verifyPassword($currentPassword, $userData['password'])) {
            $errorMessage = 'Current password is incorrect.';
        } 
        else {
            // Update password
            $updateResult = updateUserPassword($userId, $newPassword);
            
            if ($updateResult) {
                $successMessage = 'Password changed successfully.';
            } else {
                $errorMessage = 'Failed to change password. Please try again.';
            }
        }
    }
    elseif (isset($_POST['upload_avatar'])) {
        // Handle avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = handleAvatarUpload($userId, $_FILES['avatar']);
            
            if ($uploadResult['success']) {
                $successMessage = 'Profile picture updated successfully.';
                // Refresh user data after update
                $userData = getUserById($userId);
            } else {
                $errorMessage = $uploadResult['message'];
            }
        } else {
            $errorMessage = 'Please select a valid image file.';
        }
    }
}

// Get user role name
$roleName = getUserRoleName($userData['role_id']);

// Page title
$pageTitle = "My Profile";

// Include header
include 'templates/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <!-- Mobile header with back button -->
    <div class="lg:hidden bg-white shadow-sm p-4 flex items-center justify-between sticky top-0 z-10">
        <a href="index.php" class="flex items-center text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back
        </a>
        <h1 class="text-lg font-semibold text-gray-800">My Profile</h1>
        <div class="w-5"></div><!-- Spacer for balance -->
    </div>

    <div class="container mx-auto px-4 py-8">
        <!-- Desktop page title - hidden on mobile -->
        <div class="hidden lg:block mb-6">
            <h1 class="text-2xl font-bold text-gray-800">My Profile</h1>
            <p class="text-gray-600">Manage your account information and settings</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($successMessage)): ?>
            <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow" role="alert">
                <p><?php echo $successMessage; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow" role="alert">
                <p><?php echo $errorMessage; ?></p>
            </div>
        <?php endif; ?>

        <div class="lg:flex lg:space-x-6">
            <!-- Profile Overview Card -->
            <div class="lg:w-1/3 mb-6 lg:mb-0">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-4 py-8 text-center">
                        <div class="relative inline-block">
                            <?php if (!empty($userData['avatar'])): ?>
                                <img src="uploads/avatars/<?php echo htmlspecialchars($userData['avatar']); ?>" alt="Profile Picture" class="w-24 h-24 rounded-full border-4 border-white shadow-md mx-auto">
                            <?php else: ?>
                                <div class="w-24 h-24 rounded-full bg-gray-300 flex items-center justify-center border-4 border-white shadow-md mx-auto">
                                    <span class="text-2xl font-semibold text-gray-600"><?php echo strtoupper(substr($name, 0, 1)); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Avatar upload button -->
                            <button type="button" id="change-avatar-btn" class="absolute bottom-0 right-0 bg-white rounded-full p-2 shadow-lg border border-gray-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                        </div>
                        <h2 class="text-white text-xl font-semibold mt-4"><?php echo htmlspecialchars($name); ?></h2>
                        <p class="text-blue-100"><?php echo htmlspecialchars($email); ?></p>
                        <div class="inline-block bg-blue-800 bg-opacity-30 text-blue-100 rounded-full px-3 py-1 text-sm mt-2">
                            <?php echo htmlspecialchars($roleName); ?>
                        </div>
                    </div>
                    
                    <div class="p-4 border-b">
                        <h3 class="text-gray-700 font-semibold mb-3">Account Details</h3>
                        <ul class="space-y-3">
                            <?php if (!empty($phone)): ?>
                            <li class="flex items-center text-gray-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                <?php echo htmlspecialchars($phone); ?>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (!empty($company)): ?>
                            <li class="flex items-center text-gray-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <?php echo htmlspecialchars($company); ?>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (!empty($address)): ?>
                            <li class="flex items-start text-gray-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span><?php echo nl2br(htmlspecialchars($address)); ?></span>
                            </li>
                            <?php endif; ?>
                            
                            <li class="flex items-center text-gray-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                                </svg>
                                <?php
                                $languages = [
                                    'en' => 'English',
                                    'es' => 'Spanish',
                                    'fr' => 'French',
                                    'de' => 'German',
                                    'zh' => 'Chinese',
                                    'ar' => 'Arabic',
                                    // Add more languages as needed
                                ];
                                echo $languages[$language] ?? 'English';
                                ?>
                            </li>
                            
                            <li class="flex items-center text-gray-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Member since: <?php echo date('M d, Y', strtotime($userData['created_at'])); ?>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="p-4">
                        <h3 class="text-gray-700 font-semibold mb-3">Quick Actions</h3>
                        <div class="space-y-2">
                            <a href="invoices.php" class="flex items-center text-blue-600 hover:text-blue-800 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                My Invoices
                            </a>
                            <a href="services.php" class="flex items-center text-blue-600 hover:text-blue-800 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                Browse Services
                            </a>
                            <a href="pending.php" class="flex items-center text-blue-600 hover:text-blue-800 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Pending Payments
                            </a>
                            <a href="support.php" class="flex items-center text-blue-600 hover:text-blue-800 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                Get Support
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Edit Forms -->
            <div class="lg:w-2/3">
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">Edit Profile Information</h3>
                        <p class="text-gray-600 text-sm">Update your account information</p>
                    </div>
                    
                    <div class="p-6">
                        <form action="profile.php" method="POST">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($name); ?>" required 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" required 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                    <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($phone); ?>" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="company" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                                    <input type="text" name="company" id="company" value="<?php echo htmlspecialchars($company); ?>" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                    <textarea name="address" id="address" rows="3" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($address); ?></textarea>
                                </div>
                                
                                <div>
                                    <label for="language" class="block text-sm font-medium text-gray-700 mb-1">Preferred Language</label>
                                    <select name="language" id="language" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                        <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>>English</option>
                                        <option value="es" <?php echo $language === 'es' ? 'selected' : ''; ?>>Spanish</option>
                                        <option value="fr" <?php echo $language === 'fr' ? 'selected' : ''; ?>>French</option>
                                        <option value="de" <?php echo $language === 'de' ? 'selected' : ''; ?>>German</option>
                                        <option value="zh" <?php echo $language === 'zh' ? 'selected' : ''; ?>>Chinese</option>
                                        <option value="ar" <?php echo $language === 'ar' ? 'selected' : ''; ?>>Arabic</option>
                                        <!-- Add more languages as needed -->
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" name="update_profile" value="1" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password Section -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">Change Password</h3>
                        <p class="text-gray-600 text-sm">Update your account password</p>
                    </div>
                    
                    <div class="p-6">
                        <form action="profile.php" method="POST">
                            <div class="space-y-4">
                                <div>
                                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                    <input type="password" name="current_password" id="current_password" required 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                    <input type="password" name="new_password" id="new_password" required 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    <p class="text-sm text-gray-500 mt-1">Password must be at least 8 characters long</p>
                                </div>
                                
                                <div>
                                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                    <input type="password" name="confirm_password" id="confirm_password" required 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" name="change_password" value="1" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Notification Preferences -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">Notification Preferences</h3>
                        <p class="text-gray-600 text-sm">Manage how you receive notifications</p>
                    </div>
                    
                    <div class="p-6">
                        <form action="profile.php" method="POST">
                            <div class="space-y-4">
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="email_invoice" id="email_invoice" checked 
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="email_invoice" class="font-medium text-gray-700">Email Notifications</label>
                                        <p class="text-gray-500">Receive new invoice notifications via email</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="email_payment" id="email_payment" checked 
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="email_payment" class="font-medium text-gray-700">Payment Reminders</label>
                                        <p class="text-gray-500">Receive payment due date reminders</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="email_promotion" id="email_promotion" 
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="email_promotion" class="font-medium text-gray-700">Promotional Emails</label>
                                        <p class="text-gray-500">Receive special offers and promotions</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="push_notifications" id="push_notifications" checked 
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="push_notifications" class="font-medium text-gray-700">Push Notifications</label>
                                        <p class="text-gray-500">Receive push notifications on this device</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" name="update_notifications" value="1" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                                    Save Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Avatar Upload Modal -->
<div id="avatar-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="px-6 py-4 border-b">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Update Profile Picture</h3>
                <button type="button" id="close-avatar-modal" class="text-gray-400 hover:text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select a new profile picture</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600">
                                <label for="avatar" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                                    <span>Upload a file</span>
                                    <input id="avatar" name="avatar" type="file" class="sr-only" accept="image/jpeg,image/png,image/gif">
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-5 flex justify-end space-x-3">
                    <button type="button" id="cancel-avatar" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Cancel
                    </button>
                    <button type="submit" name="upload_avatar" value="1" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                        Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for modals and interactions -->
<script>
    // Avatar modal functionality
    const avatarModal = document.getElementById('avatar-modal');
    const changeAvatarBtn = document.getElementById('change-avatar-btn');
    const closeAvatarModalBtn = document.getElementById('close-avatar-modal');
    const cancelAvatarBtn = document.getElementById('cancel-avatar');
    
    // Show avatar modal
    changeAvatarBtn.addEventListener('click', function() {
        avatarModal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden'); // Prevent background scrolling
    });
    
    // Hide avatar modal
    function hideAvatarModal() {
        avatarModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
    
    closeAvatarModalBtn.addEventListener('click', hideAvatarModal);
    cancelAvatarBtn.addEventListener('click', hideAvatarModal);
    
    // Close modal when clicking outside
    avatarModal.addEventListener('click', function(e) {
        if (e.target === avatarModal) {
            hideAvatarModal();
        }
    });
    
    // Preview selected image
    const avatarInput = document.getElementById('avatar');
    avatarInput.addEventListener('change', function() {
        const file = this.files[0];
        
        if (file) {
            const reader = new FileReader();
            const imagePreviewContainer = document.createElement('div');
            imagePreviewContainer.id = 'image-preview-container';
            imagePreviewContainer.className = 'mt-4';
            
            reader.onload = function(e) {
                // Remove any existing preview
                const existingPreview = document.getElementById('image-preview-container');
                if (existingPreview) {
                    existingPreview.remove();
                }
                
                // Create preview element
                imagePreviewContainer.innerHTML = `
                    <p class="text-sm font-medium text-gray-700 mb-2">Preview:</p>
                    <div class="flex justify-center">
                        <img src="${e.target.result}" alt="Preview" class="h-32 w-32 object-cover rounded-full border-2 border-gray-300">
                    </div>
                `;
                
                // Insert preview before form buttons
                const formButtons = avatarInput.closest('form').querySelector('.mt-5');
                formButtons.parentNode.insertBefore(imagePreviewContainer, formButtons);
            };
            
            reader.readAsDataURL(file);
        }
    });
    
    // Success message auto-hide
    const successMessage = document.querySelector('.bg-green-100');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.transition = 'opacity 1s ease';
            successMessage.style.opacity = '0';
            setTimeout(() => {
                successMessage.remove();
            }, 1000);
        }, 5000);
    }
</script>

<?php
// Include footer
include 'templates/footer.php';
?>