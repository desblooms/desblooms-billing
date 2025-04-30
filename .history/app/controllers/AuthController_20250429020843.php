<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\AuthService;
use App\Helpers\ValidationHelper;

/**
 * Authentication Controller
 * 
 * Handles user authentication, registration, login, and password management
 */
class AuthController
{
    private $authService;
    private $validationHelper;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->authService = new AuthService();
        $this->validationHelper = new ValidationHelper();
    }
    
    /**
     * Display login page
     */
    public function login()
    {
        // Check if user is already logged in
        if ($this->authService->isLoggedIn()) {
            $this->redirectToDashboard();
            return;
        }
        
        // Load the login view
        require_once 'app/views/auth/login.php';
    }
    
    /**
     * Process login form submission
     */
    public function processLogin()
    {
        // Validate form data
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        
        // Validate input
        $errors = [];
        
        if (!$this->validationHelper->isValidEmail($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        
        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        }
        
        // If validation fails, return to login form with errors
        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = ['email' => $email];
            header('Location: /login');
            exit;
        }
        
        // Attempt login
        $loginResult = $this->authService->attemptLogin($email, $password, $rememberMe);
        
        if ($loginResult['success']) {
            // Set session variables and redirect
            $_SESSION['auth_user'] = $loginResult['user'];
            
            // Set remember me cookie if requested
            if ($rememberMe) {
                $this->authService->setRememberMeCookie($loginResult['user']);
            }
            
            $this->redirectToDashboard();
        } else {
            // Authentication failed
            $_SESSION['form_errors'] = ['auth' => $loginResult['message']];
            $_SESSION['form_data'] = ['email' => $email];
            header('Location: /login');
            exit;
        }
    }
    
    /**
     * Display registration page
     */
    public function register()
    {
        // Check if user is already logged in
        if ($this->authService->isLoggedIn()) {
            $this->redirectToDashboard();
            return;
        }
        
        // Load the registration view
        require_once 'app/views/auth/register.php';
    }
    
    /**
     * Process registration form submission
     */
    public function processRegistration()
    {
        // Sanitize and validate form data
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $userType = filter_input(INPUT_POST, 'user_type', FILTER_SANITIZE_STRING);
        
        // Validate input
        $errors = [];
        
        if (empty($name) || strlen($name) < 3) {
            $errors['name'] = 'Name must be at least 3 characters long.';
        }
        
        if (!$this->validationHelper->isValidEmail($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif ($this->authService->emailExists($email)) {
            $errors['email'] = 'This email is already registered.';
        }
        
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long.';
        }
        
        if ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }
        
        if (!in_array($userType, ['customer', 'business'])) {
            $errors['user_type'] = 'Please select a valid user type.';
        }
        
        // If validation fails, return to registration form with errors
        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = [
                'name' => $name,
                'email' => $email,
                'user_type' => $userType
            ];
            header('Location: /register');
            exit;
        }
        
        // Register new user
        $registrationResult = $this->authService->registerUser([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'user_type' => $userType
        ]);
        
        if ($registrationResult['success']) {
            // Set success message and redirect to login
            $_SESSION['auth_message'] = 'Registration successful! You can now log in.';
            header('Location: /login');
            exit;
        } else {
            // Registration failed
            $_SESSION['form_errors'] = ['registration' => $registrationResult['message']];
            $_SESSION['form_data'] = [
                'name' => $name,
                'email' => $email,
                'user_type' => $userType
            ];
            header('Location: /register');
            exit;
        }
    }
    
    /**
     * Display forgot password page
     */
    public function forgotPassword()
    {
        // Load the forgot password view
        require_once 'app/views/auth/forgot-password.php';
    }
    
    /**
     * Process forgot password form submission
     */
    public function processForgotPassword()
    {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        
        // Validate email
        if (!$this->validationHelper->isValidEmail($email)) {
            $_SESSION['form_errors'] = ['email' => 'Please enter a valid email address.'];
            $_SESSION['form_data'] = ['email' => $email];
            header('Location: /forgot-password');
            exit;
        }
        
        // Generate and send reset token
        $result = $this->authService->sendPasswordResetEmail($email);
        
        // Always show success message even if email doesn't exist (security best practice)
        $_SESSION['auth_message'] = 'If your email exists in our system, you will receive a password reset link shortly.';
        header('Location: /login');
        exit;
    }
    
    /**
     * Display reset password page
     */
    public function resetPassword($token)
    {
        // Validate token
        if (!$this->authService->validateResetToken($token)) {
            $_SESSION['auth_message'] = 'Invalid or expired password reset token.';
            header('Location: /login');
            exit;
        }
        
        // Load the reset password view with token
        require_once 'app/views/auth/reset-password.php';
    }
    
    /**
     * Process reset password form submission
     */
    public function processResetPassword()
    {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        
        // Validate token and passwords
        $errors = [];
        
        if (!$this->authService->validateResetToken($token)) {
            $_SESSION['auth_message'] = 'Invalid or expired password reset token.';
            header('Location: /login');
            exit;
        }
        
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long.';
        }
        
        if ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }
        
        // If validation fails, return to reset form with errors
        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            header("Location: /reset-password/$token");
            exit;
        }
        
        // Reset password
        $result = $this->authService->resetPassword($token, $password);
        
        if ($result['success']) {
            $_SESSION['auth_message'] = 'Your password has been reset successfully. You can now log in with your new password.';
            header('Location: /login');
            exit;
        } else {
            $_SESSION['form_errors'] = ['reset' => $result['message']];
            header("Location: /reset-password/$token");
            exit;
        }
    }
    
    /**
     * Logout user
     */
    public function logout()
    {
        $this->authService->logout();
        $_SESSION['auth_message'] = 'You have been logged out successfully.';
        header('Location: /login');
        exit;
    }
    
    /**
     * Redirect user to appropriate dashboard based on role
     */
    private function redirectToDashboard()
    {
        if (!isset($_SESSION['auth_user'])) {
            header('Location: /login');
            exit;
        }
        
        $user = $_SESSION['auth_user'];
        
        switch ($user['role']) {
            case 'admin':
                header('Location: /admin/dashboard');
                break;
            case 'business':
                header('Location: /business/dashboard');
                break;
            case 'customer':
                header('Location: /customer/dashboard');
                break;
            default:
                header('Location: /');
                break;
        }
        exit;
    }
}