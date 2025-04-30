<?php
/**
 * ServiceController - Handles all service-related functionality
 * 
 * This controller manages service catalog display, service details,
 * custom service requests, and service management operations.
 */
class ServiceController
{
    private $serviceModel;
    private $categoryModel;
    private $authService;
    private $validationHelper;

    /**
     * Constructor - Initialize models and services
     */
    public function __construct()
    {
        // Load required models and services
        $this->serviceModel = new Service();
        $this->categoryModel = new Category();
        $this->authService = new AuthService();
        $this->validationHelper = new ValidationHelper();
    }

    /**
     * Display the service catalog
     */
    public function catalog()
    {
        // Get filter parameters
        $category = isset($_GET['category']) ? $this->validationHelper->sanitizeInput($_GET['category']) : null;
        $search = isset($_GET['search']) ? $this->validationHelper->sanitizeInput($_GET['search']) : null;
        $sort = isset($_GET['sort']) ? $this->validationHelper->sanitizeInput($_GET['sort']) : 'name_asc';
        
        // Get services based on filters
        $services = $this->serviceModel->getServices($category, $search, $sort);
        $categories = $this->categoryModel->getAllCategories();
        
        // Load view
        require_once('app/views/services/catalog.php');
    }

    /**
     * Display service details
     * 
     * @param int $id Service ID
     */
    public function details($id)
    {
        // Validate service ID
        $id = $this->validationHelper->sanitizeInput($id);
        
        // Get service details
        $service = $this->serviceModel->getServiceById($id);
        
        if (!$service) {
            // Service not found
            $_SESSION['error'] = 'Service not found.';
            header('Location: /services/catalog');
            exit;
        }
        
        // Related services
        $relatedServices = $this->serviceModel->getRelatedServices($service->category_id, $id);
        
        // Load view
        require_once('app/views/services/details.php');
    }

    /**
     * Display custom service request form
     */
    public function requestForm()
    {
        // Ensure user is logged in
        if (!$this->authService->isLoggedIn()) {
            $_SESSION['redirect_after_login'] = '/services/request';
            $_SESSION['info'] = 'Please log in to request a custom service.';
            header('Location: /auth/login');
            exit;
        }
        
        $categories = $this->categoryModel->getAllCategories();
        
        // Load view
        require_once('app/views/services/request.php');
    }

    /**
     * Process custom service request
     */
    public function submitRequest()
    {
        // Ensure user is logged in
        if (!$this->authService->isLoggedIn()) {
            $_SESSION['error'] = 'You must be logged in to submit a service request.';
            header('Location: /auth/login');
            exit;
        }
        
        // Validate form data
        $title = $this->validationHelper->sanitizeInput($_POST['title']);
        $description = $this->validationHelper->sanitizeInput($_POST['description']);
        $categoryId = $this->validationHelper->sanitizeInput($_POST['category_id']);
        $budget = $this->validationHelper->sanitizeInput($_POST['budget']);
        $timeframe = $this->validationHelper->sanitizeInput($_POST['timeframe']);
        
        // Validation
        $errors = [];
        
        if (empty($title)) {
            $errors[] = 'Service title is required';
        }
        
        if (empty($description)) {
            $errors[] = 'Service description is required';
        }
        
        if (empty($categoryId)) {
            $errors[] = 'Please select a category';
        }
        
        if (!empty($budget) && !is_numeric($budget)) {
            $errors[] = 'Budget must be a number';
        }
        
        // If there are errors, redirect back with error messages
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            header('Location: /services/request');
            exit;
        }
        
        // Process file attachments if any
        $attachments = [];
        if (isset($_FILES['attachments']) && $_FILES['attachments']['error'][0] != UPLOAD_ERR_NO_FILE) {
            $attachments = $this->processAttachments($_FILES['attachments']);
        }
        
        // Save service request
        $userId = $_SESSION['user_id'];
        $result = $this->serviceModel->createServiceRequest(
            $userId,
            $title,
            $description,
            $categoryId,
            $budget,
            $timeframe,
            $attachments
        );
        
        if ($result) {
            $_SESSION['success'] = 'Your service request has been submitted successfully. Our team will contact you shortly.';
            header('Location: /dashboard');
            exit;
        } else {
            $_SESSION['error'] = 'Failed to submit your service request. Please try again.';
            $_SESSION['form_data'] = $_POST;
            header('Location: /services/request');
            exit;
        }
    }

    /**
     * Process file attachments
     * 
     * @param array $files Files array from $_FILES
     * @return array Array of saved file paths
     */
    private function processAttachments($files)
    {
        $savedFiles = [];
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                // Validate file type
                if (!in_array($files['type'][$i], $allowedTypes)) {
                    $_SESSION['errors'][] = 'File type not allowed: ' . $files['name'][$i];
                    continue;
                }
                
                // Validate file size
                if ($files['size'][$i] > $maxFileSize) {
                    $_SESSION['errors'][] = 'File exceeds maximum size (5MB): ' . $files['name'][$i];
                    continue;
                }
                
                // Generate unique filename
                $extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                $newFilename = uniqid() . '.' . $extension;
                $uploadDir = 'uploads/service_requests/';
                
                // Ensure upload directory exists
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $filePath = $uploadDir . $newFilename;
                
                // Move uploaded file
                if (move_uploaded_file($files['tmp_name'][$i], $filePath)) {
                    $savedFiles[] = $filePath;
                } else {
                    $_SESSION['errors'][] = 'Failed to upload file: ' . $files['name'][$i];
                }
            }
        }
        
        return $savedFiles;
    }

    /**
     * Admin - List services (requires admin access)
     */
    public function adminList()
    {
        // Check admin permissions
        if (!$this->authService->hasPermission('admin_services')) {
            $_SESSION['error'] = 'You do not have permission to access this page.';
            header('Location: /dashboard');
            exit;
        }
        
        // Get all services
        $services = $this->serviceModel->getAllServices();
        
        // Load admin view
        require_once('app/views/admin/services/list.php');
    }

    /**
     * Admin - Create or edit service form (requires admin access)
     * 
     * @param int $id Service ID (null for new service)
     */
    public function adminForm($id = null)
    {
        // Check admin permissions
        if (!$this->authService->hasPermission('admin_services')) {
            $_SESSION['error'] = 'You do not have permission to access this page.';
            header('Location: /dashboard');
            exit;
        }
        
        $service = null;
        $categories = $this->categoryModel->getAllCategories();
        
        if ($id) {
            // Edit existing service
            $id = $this->validationHelper->sanitizeInput($id);
            $service = $this->serviceModel->getServiceById($id);
            
            if (!$service) {
                $_SESSION['error'] = 'Service not found.';
                header('Location: /admin/services');
                exit;
            }
        }
        
        // Load admin form view
        require_once('app/views/admin/services/form.php');
    }

    /**
     * Admin - Save service (create or update)
     */
    public function adminSave()
    {
        // Check admin permissions
        if (!$this->authService->hasPermission('admin_services')) {
            $_SESSION['error'] = 'You do not have permission to perform this action.';
            header('Location: /dashboard');
            exit;
        }
        
        // Determine if this is an update or create
        $id = isset($_POST['id']) ? $this->validationHelper->sanitizeInput($_POST['id']) : null;
        
        // Validate form data
        $name = $this->validationHelper->sanitizeInput($_POST['name']);
        $description = $this->validationHelper->sanitizeInput($_POST['description']);
        $categoryId = $this->validationHelper->sanitizeInput($_POST['category_id']);
        $price = $this->validationHelper->sanitizeInput($_POST['price']);
        $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;
        $billingCycle = $isRecurring ? $this->validationHelper->sanitizeInput($_POST['billing_cycle']) : null;
        $status = $this->validationHelper->sanitizeInput($_POST['status']);
        
        // Validation
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Service name is required';
        }
        
        if (empty($description)) {
            $errors[] = 'Service description is required';
        }
        
        if (empty($categoryId)) {
            $errors[] = 'Please select a category';
        }
        
        if (empty($price) || !is_numeric($price) || $price < 0) {
            $errors[] = 'Please enter a valid price';
        }
        
        if ($isRecurring && empty($billingCycle)) {
            $errors[] = 'Please select a billing cycle for recurring services';
        }
        
        // If there are errors, redirect back with error messages
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            
            if ($id) {
                header("Location: /admin/services/edit/{$id}");
            } else {
                header('Location: /admin/services/new');
            }
            exit;
        }
        
        // Process service image if uploaded
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] != UPLOAD_ERR_NO_FILE) {
            $imagePath = $this->processServiceImage($_FILES['image']);
            
            if (isset($_SESSION['errors'])) {
                $_SESSION['form_data'] = $_POST;
                
                if ($id) {
                    header("Location: /admin/services/edit/{$id}");
                } else {
                    header('Location: /admin/services/new');
                }
                exit;
            }
        }
        
        // Save service
        $serviceData = [
            'name' => $name,
            'description' => $description,
            'category_id' => $categoryId,
            'price' => $price,
            'is_recurring' => $isRecurring,
            'billing_cycle' => $billingCycle,
            'status' => $status
        ];
        
        if ($imagePath) {
            $serviceData['image'] = $imagePath;
        }
        
        if ($id) {
            // Update existing service
            $result = $this->serviceModel->updateService($id, $serviceData);
            $message = 'Service updated successfully.';
        } else {
            // Create new service
            $result = $this->serviceModel->createService($serviceData);
            $message = 'Service created successfully.';
        }
        
        if ($result) {
            $_SESSION['success'] = $message;
            header('Location: /admin/services');
            exit;
        } else {
            $_SESSION['error'] = 'Failed to save service. Please try again.';
            $_SESSION['form_data'] = $_POST;
            
            if ($id) {
                header("Location: /admin/services/edit/{$id}");
            } else {
                header('Location: /admin/services/new');
            }
            exit;
        }
    }

    /**
     * Process service image upload
     * 
     * @param array $file File array from $_FILES
     * @return string|null Path to saved image or null if failed
     */
    private function processServiceImage($file)
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxFileSize = 2 * 1024 * 1024; // 2MB
        
        // Validate file type
        if (!in_array($file['type'], $allowedTypes)) {
            $_SESSION['errors'][] = 'Image type not allowed. Please use JPG, PNG, or WebP.';
            return null;
        }
        
        // Validate file size
        if ($file['size'] > $maxFileSize) {
            $_SESSION['errors'][] = 'Image exceeds maximum size (2MB).';
            return null;
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilename = uniqid() . '.' . $extension;
        $uploadDir = 'uploads/services/';
        
        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filePath = $uploadDir . $newFilename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return $filePath;
        } else {
            $_SESSION['errors'][] = 'Failed to upload image.';
            return null;
        }
    }

    /**
     * Admin - Delete service (requires admin access)
     * 
     * @param int $id Service ID
     */
    public function adminDelete($id)
    {
        // Check admin permissions
        if (!$this->authService->hasPermission('admin_services')) {
            $_SESSION['error'] = 'You do not have permission to perform this action.';
            header('Location: /dashboard');
            exit;
        }
        
        // Validate service ID
        $id = $this->validationHelper->sanitizeInput($id);
        
        // Check if service exists
        $service = $this->serviceModel->getServiceById($id);
        
        if (!$service) {
            $_SESSION['error'] = 'Service not found.';
            header('Location: /admin/services');
            exit;
        }
        
        // Check if service is in use
        if ($this->serviceModel->isServiceInUse($id)) {
            $_SESSION['error'] = 'This service cannot be deleted because it is currently in use. Consider deactivating it instead.';
            header('Location: /admin/services');
            exit;
        }
        
        // Delete service
        $result = $this->serviceModel->deleteService($id);
        
        if ($result) {
            $_SESSION['success'] = 'Service deleted successfully.';
        } else {
            $_SESSION['error'] = 'Failed to delete service. Please try again.';
        }
        
        header('Location: /admin/services');
        exit;
    }
}