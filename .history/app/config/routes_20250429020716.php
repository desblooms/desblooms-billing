<?php
/**
 * Routes Configuration
 * 
 * Defines all application routes and maps them to their respective controllers and methods.
 * This routing system follows a simple pattern: 'url_path' => 'controller/method'
 */

// Define the routes array
$routes = [
    // Public routes
    '/' => 'HomeController/index',
    '/about' => 'HomeController/about',
    '/contact' => 'HomeController/contact',
    
    // Authentication routes
    '/login' => 'AuthController/login',
    '/register' => 'AuthController/register',
    '/logout' => 'AuthController/logout',
    '/forgot-password' => 'AuthController/forgotPassword',
    '/reset-password' => 'AuthController/resetPassword',
    
    // Service routes
    '/services' => 'ServiceController/index',
    '/services/catalog' => 'ServiceController/catalog',
    '/services/details/:id' => 'ServiceController/details',
    '/services/request' => 'ServiceController/request',
    
    // Order routes
    '/orders/create' => 'OrderController/create',
    '/orders/history' => 'OrderController/history',
    '/orders/details/:id' => 'OrderController/details',
    
    // Billing routes
    '/billing/invoices' => 'BillingController/invoices',
    '/billing/payments' => 'BillingController/payments',
    '/billing/subscription' => 'BillingController/subscription',
    '/billing/process-payment' => 'BillingController/processPayment',
    
    // Dashboard routes
    '/dashboard' => 'DashboardController/index',
    '/dashboard/admin' => 'DashboardController/admin',
    '/dashboard/business' => 'DashboardController/business',
    '/dashboard/customer' => 'DashboardController/customer',
    
    // Customer routes
    '/customers/profile' => 'CustomerController/profile',
    '/customers/support' => 'CustomerController/support',
    '/customers/tickets' => 'CustomerController/tickets',
    
    // Report routes
    '/reports/revenue' => 'ReportController/revenue',
    '/reports/service-usage' => 'ReportController/serviceUsage',
    '/reports/customer-activity' => 'ReportController/customerActivity',
    
    // API routes
    '/api/services' => 'Api/ServiceController/index',
    '/api/orders' => 'Api/OrderController/index',
    '/api/customers' => 'Api/CustomerController/index',
    '/api/payments' => 'Api/BillingController/payments',
    
    // Error routes
    '/404' => 'ErrorController/notFound',
    '/403' => 'ErrorController/forbidden',
    '/500' => 'ErrorController/serverError'
];

/**
 * Router Class
 * 
 * Simple router implementation that maps URLs to controller/method combinations
 */
class Router {
    private $routes = [];
    private $notFoundCallback = null;
    
    /**
     * Constructor - initializes routes
     *
     * @param array $routes Array of route definitions
     */
    public function __construct($routes) {
        $this->routes = $routes;
        $this->notFoundCallback = function() {
            header('HTTP/1.0 404 Not Found');
            include_once('app/views/errors/404.php');
            exit();
        };
    }
    
    /**
     * Match and dispatch the current request
     */
    public function dispatch() {
        // Get current URL
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        
        // Remove query string if present
        if (($pos = strpos($requestUri, '?')) !== false) {
            $requestUri = substr($requestUri, 0, $pos);
        }
        
        // Remove trailing slash except for root
        if ($requestUri != '/' && substr($requestUri, -1) == '/') {
            $requestUri = rtrim($requestUri, '/');
            header('Location: ' . $requestUri);
            exit();
        }
        
        // Try to match a route
        foreach ($this->routes as $route => $handler) {
            // Convert route parameters to regex pattern
            $pattern = preg_replace('/:[a-zA-Z0-9]+/', '([a-zA-Z0-9]+)', $route);
            $pattern = '@^' . $pattern . '$@';
            
            if (preg_match($pattern, $requestUri, $matches)) {
                // Remove the first match (the full URL)
                array_shift($matches);
                
                // Get controller and method
                list($controller, $method) = explode('/', $handler);
                
                // Check if it's an API controller
                if (strpos($controller, 'Api/') === 0) {
                    $controller = str_replace('Api/', '', $controller);
                    $controllerClass = "App\\Controllers\\Api\\{$controller}";
                } else {
                    $controllerClass = "App\\Controllers\\{$controller}";
                }
                
                // Initialize controller
                if (class_exists($controllerClass)) {
                    $controllerInstance = new $controllerClass();
                    
                    // Call method with parameters
                    if (method_exists($controllerInstance, $method)) {
                        call_user_func_array([$controllerInstance, $method], $matches);
                        return;
                    }
                }
                
                // If we get here, the controller or method doesn't exist
                call_user_func($this->notFoundCallback);
                return;
            }
        }
        
        // No route match found
        call_user_func($this->notFoundCallback);
    }
    
    /**
     * Set custom not found handler
     *
     * @param callable $callback Function to call when no route matches
     */
    public function setNotFoundHandler($callback) {
        $this->notFoundCallback = $callback;
    }
}

// Initialize and dispatch the router
$router = new Router($routes);
$router->dispatch();