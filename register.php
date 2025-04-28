 
<?php
/**
 * User Registration Page
 * 
 * Handles new user registration with form validation,
 * database integration, and security measures
 */

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Initialize variables
$errors = [];
$success = false;
$fullname = '';
$email = '';
$phone = '';

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect to appropriate dashboard based on user role
    $user_role = $_SESSION['user_role'];
    if ($user_role == 'admin') {
        header('Location: admin/index.php');
        exit;
    } else {
        header('Location: index.php');
        exit;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data and sanitize inputs
    $fullname = sanitizeInput($_POST['fullname'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate full name
    if (empty($fullname)) {
        $errors['fullname'] = 'Full name is required';
    } elseif (strlen($fullname) < 3) {
        $errors['fullname'] = 'Full name must be at least 3 characters';
    }
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = 'Email address is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    } else {
        // Check if email already exists in database
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors['email'] = 'Email address already registered. Please login instead.';
        }
    }
    
    // Validate phone number (optional)
    if (!empty($phone) && !preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number';
    }
    
    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must include at least one uppercase letter, one lowercase letter, and one number';
    }
    
    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    // If validation passes, register the user
    if (empty($errors)) {
        try {
            // Hash the password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate a unique token for email verification
            $token = bin2hex(random_bytes(32));
            
            // Set default role as 'customer'
            $role = 'customer';
            
            // Current timestamp
            $created_at = date('Y-m-d H:i:s');
            
            // Insert user data into database
            $stmt = $pdo->prepare("INSERT INTO users (fullname, email, phone, password, role, verification_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$fullname, $email, $phone, $password_hash, $role, $token, $created_at]);
            
            if ($result) {
                // Send verification email (implementation in auth.php)
                sendVerificationEmail($email, $fullname, $token);
                
                $success = true;
                
                // Clear form fields after successful registration
                $fullname = '';
                $email = '';
                $phone = '';
            } else {
                $errors['db'] = 'Registration failed. Please try again later.';
            }
        } catch (PDOException $e) {
            $errors['db'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Include header
$page_title = "Register";
require_once 'templates/header.php';
?>

<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-lg shadow-md">
        <div>
            <h2 class="mt-6 text-center text-2xl font-extrabold text-gray-900">
                Create your account
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Or
                <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                    sign in to your account
                </a>
            </p>
        </div>
        
        <?php if ($success): ?>
            <div class="rounded-md bg-green-50 p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Registration successful!</h3>
                        <div class="mt-2 text-sm text-green-700">
                            <p>Please check your email to verify your account. If you don't see it, check your spam folder.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errors['db'])): ?>
            <div class="rounded-md bg-red-50 p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Error</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p><?php echo $errors['db']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <form class="mt-8 space-y-6" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="rounded-md shadow-sm -space-y-px">
                <!-- Full Name Field -->
                <div class="mb-4">
                    <label for="fullname" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input id="fullname" name="fullname" type="text" required 
                           class="appearance-none rounded-md relative block w-full px-3 py-2 border <?php echo isset($errors['fullname']) ? 'border-red-300' : 'border-gray-300'; ?> placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                           placeholder="John Doe" value="<?php echo htmlspecialchars($fullname); ?>">
                    <?php if (isset($errors['fullname'])): ?>
                        <p class="mt-2 text-sm text-red-600"><?php echo $errors['fullname']; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Email Field -->
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           class="appearance-none rounded-md relative block w-full px-3 py-2 border <?php echo isset($errors['email']) ? 'border-red-300' : 'border-gray-300'; ?> placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                           placeholder="email@example.com" value="<?php echo htmlspecialchars($email); ?>">
                    <?php if (isset($errors['email'])): ?>
                        <p class="mt-2 text-sm text-red-600"><?php echo $errors['email']; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Phone Field (Optional) -->
                <div class="mb-4">
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number (Optional)</label>
                    <input id="phone" name="phone" type="tel"
                           class="appearance-none rounded-md relative block w-full px-3 py-2 border <?php echo isset($errors['phone']) ? 'border-red-300' : 'border-gray-300'; ?> placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                           placeholder="1234567890" value="<?php echo htmlspecialchars($phone); ?>">
                    <?php if (isset($errors['phone'])): ?>
                        <p class="mt-2 text-sm text-red-600"><?php echo $errors['phone']; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Password Field -->
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required
                           class="appearance-none rounded-md relative block w-full px-3 py-2 border <?php echo isset($errors['password']) ? 'border-red-300' : 'border-gray-300'; ?> placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                           placeholder="Password">
                    <?php if (isset($errors['password'])): ?>
                        <p class="mt-2 text-sm text-red-600"><?php echo $errors['password']; ?></p>
                    <?php endif; ?>
                    <p class="mt-1 text-xs text-gray-500">Must be at least 8 characters with uppercase, lowercase, and number</p>
                </div>
                
                <!-- Confirm Password Field -->
                <div class="mb-4">
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input id="confirm_password" name="confirm_password" type="password" required
                           class="appearance-none rounded-md relative block w-full px-3 py-2 border <?php echo isset($errors['confirm_password']) ? 'border-red-300' : 'border-gray-300'; ?> placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                           placeholder="Confirm Password">
                    <?php if (isset($errors['confirm_password'])): ?>
                        <p class="mt-2 text-sm text-red-600"><?php echo $errors['confirm_password']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div>
                <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    Register
                </button>
            </div>
            
            <div class="text-center mt-4">
                <p class="text-sm text-gray-600">
                    By registering, you agree to our
                    <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">
                        Terms of Service
                    </a>
                    and
                    <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">
                        Privacy Policy
                    </a>
                </p>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
require_once 'templates/footer.php';
?>