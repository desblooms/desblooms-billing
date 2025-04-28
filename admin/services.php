<?php
/**
 * Admin Services Management
 * This file handles CRUD operations for digital services in the billing app
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !isAdmin()) {
    // Redirect to login page if not logged in or not admin
    header('Location: ../login.php?redirect=admin/services.php');
    exit;
}

// Initialize variables
$errorMsg = '';
$successMsg = '';
$services = [];
$categories = [];

// Database connection
$conn = getDbConnection();

// Get all categories for the dropdown
try {
    $stmt = $conn->prepare("SELECT * FROM categories ORDER BY name ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMsg = "Database error: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create or Update service
    if (isset($_POST['action']) && ($_POST['action'] === 'create' || $_POST['action'] === 'update')) {
        $serviceId = isset($_POST['service_id']) ? filter_var($_POST['service_id'], FILTER_SANITIZE_NUMBER_INT) : null;
        $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
        $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);
        $categoryId = filter_var($_POST['category_id'], FILTER_SANITIZE_NUMBER_INT);
        $price = filter_var($_POST['price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;
        $billingCycle = filter_var($_POST['billing_cycle'], FILTER_SANITIZE_STRING);
        $status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);
        
        // Validate input data
        if (empty($name) || empty($description) || empty($categoryId) || empty($price)) {
            $errorMsg = "All fields are required";
        } else {
            try {
                if ($_POST['action'] === 'create') {
                    // Insert new service
                    $stmt = $conn->prepare("
                        INSERT INTO services (name, description, category_id, price, is_recurring, billing_cycle, status, created_at, updated_at)
                        VALUES (:name, :description, :category_id, :price, :is_recurring, :billing_cycle, :status, NOW(), NOW())
                    ");
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':category_id', $categoryId);
                    $stmt->bindParam(':price', $price);
                    $stmt->bindParam(':is_recurring', $isRecurring);
                    $stmt->bindParam(':billing_cycle', $billingCycle);
                    $stmt->bindParam(':status', $status);
                    $stmt->execute();
                    
                    $successMsg = "Service created successfully";
                } else {
                    // Update existing service
                    $stmt = $conn->prepare("
                        UPDATE services 
                        SET name = :name, 
                            description = :description, 
                            category_id = :category_id, 
                            price = :price, 
                            is_recurring = :is_recurring, 
                            billing_cycle = :billing_cycle, 
                            status = :status, 
                            updated_at = NOW()
                        WHERE id = :service_id
                    ");
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':category_id', $categoryId);
                    $stmt->bindParam(':price', $price);
                    $stmt->bindParam(':is_recurring', $isRecurring);
                    $stmt->bindParam(':billing_cycle', $billingCycle);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':service_id', $serviceId);
                    $stmt->execute();
                    
                    $successMsg = "Service updated successfully";
                }

                // Handle image upload if present
                if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] === UPLOAD_ERR_OK) {
                    $targetDir = "../uploads/services/";
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    
                    // Get file extension
                    $imageFileType = strtolower(pathinfo($_FILES["service_image"]["name"], PATHINFO_EXTENSION));
                    
                    // Generate unique filename
                    $serviceId = $serviceId ?? $conn->lastInsertId();
                    $fileName = "service_" . $serviceId . "_" . time() . "." . $imageFileType;
                    $targetFile = $targetDir . $fileName;
                    
                    // Check if file is an actual image
                    $check = getimagesize($_FILES["service_image"]["tmp_name"]);
                    if ($check !== false) {
                        // Check file size (max 5MB)
                        if ($_FILES["service_image"]["size"] > 5000000) {
                            $errorMsg = "Image file is too large. Max 5MB allowed.";
                        } 
                        // Allow only certain file formats
                        elseif (!in_array($imageFileType, ["jpg", "jpeg", "png", "gif"])) {
                            $errorMsg = "Only JPG, JPEG, PNG & GIF files are allowed.";
                        } 
                        // If everything is ok, try to upload file
                        elseif (move_uploaded_file($_FILES["service_image"]["tmp_name"], $targetFile)) {
                            // Update the service with the image path
                            $stmt = $conn->prepare("UPDATE services SET image_path = :image_path WHERE id = :service_id");
                            $imagePath = "uploads/services/" . $fileName;
                            $stmt->bindParam(':image_path', $imagePath);
                            $stmt->bindParam(':service_id', $serviceId);
                            $stmt->execute();
                            
                            $successMsg .= " and image uploaded successfully";
                        } else {
                            $errorMsg = "There was an error uploading your file.";
                        }
                    } else {
                        $errorMsg = "File is not an image.";
                    }
                }
                
            } catch (PDOException $e) {
                $errorMsg = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Delete service
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $serviceId = filter_var($_POST['service_id'], FILTER_SANITIZE_NUMBER_INT);
        
        try {
            // Check if service is being used in any active invoices
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM invoice_items 
                JOIN invoices ON invoice_items.invoice_id = invoices.id
                WHERE invoice_items.service_id = :service_id 
                AND invoices.status IN ('pending', 'outstanding')
            ");
            $stmt->bindParam(':service_id', $serviceId);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $errorMsg = "Cannot delete service as it is currently used in active invoices";
            } else {
                // First get the image path to delete the associated image
                $stmt = $conn->prepare("SELECT image_path FROM services WHERE id = :service_id");
                $stmt->bindParam(':service_id', $serviceId);
                $stmt->execute();
                $imagePath = $stmt->fetchColumn();
                
                // Delete the service
                $stmt = $conn->prepare("DELETE FROM services WHERE id = :service_id");
                $stmt->bindParam(':service_id', $serviceId);
                $stmt->execute();
                
                // Delete the image file if it exists
                if (!empty($imagePath) && file_exists("../" . $imagePath)) {
                    unlink("../" . $imagePath);
                }
                
                $successMsg = "Service deleted successfully";
            }
        } catch (PDOException $e) {
            $errorMsg = "Database error: " . $e->getMessage();
        }
    }
}

// Get services with category information
try {
    $stmt = $conn->prepare("
        SELECT s.*, c.name as category_name 
        FROM services s
        LEFT JOIN categories c ON s.category_id = c.id
        ORDER BY s.name ASC
    ");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMsg = "Database error: " . $e->getMessage();
}

// Get service details for edit
$editService = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $serviceId = filter_var($_GET['edit'], FILTER_SANITIZE_NUMBER_INT);
    
    try {
        $stmt = $conn->prepare("SELECT * FROM services WHERE id = :service_id");
        $stmt->bindParam(':service_id', $serviceId);
        $stmt->execute();
        $editService = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMsg = "Database error: " . $e->getMessage();
    }
}

// Close connection
$conn = null;

// Page title
$pageTitle = "Manage Services";
?>

<?php include '../templates/admin/header.php'; ?>

<div class="container mx-auto px-4 py-6">
    <div class="flex flex-col md:flex-row mb-6">
        <div class="w-full md:w-1/2">
            <h1 class="text-2xl font-bold text-gray-800">Manage Digital Services</h1>
            <p class="text-gray-600">Create, edit, and manage digital services for billing</p>
        </div>
        <div class="w-full md:w-1/2 flex justify-end items-center mt-4 md:mt-0">
            <button id="addServiceBtn" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                Add New Service
            </button>
        </div>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $errorMsg; ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $successMsg; ?></span>
        </div>
    <?php endif; ?>

    <!-- Service Form -->
    <div id="serviceFormContainer" class="bg-white rounded-lg shadow-md p-6 mb-6 <?php echo isset($_GET['edit']) ? '' : 'hidden'; ?>">
        <h2 class="text-xl font-semibold mb-4"><?php echo $editService ? 'Edit Service' : 'Add New Service'; ?></h2>
        
        <form method="POST" action="services.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="<?php echo $editService ? 'update' : 'create'; ?>">
            <?php if ($editService): ?>
                <input type="hidden" name="service_id" value="<?php echo $editService['id']; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Service Name</label>
                    <input type="text" name="name" id="name" value="<?php echo $editService ? htmlspecialchars($editService['name']) : ''; ?>" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category_id" id="category_id" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($editService && $editService['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="description" rows="3" required 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo $editService ? htmlspecialchars($editService['description']) : ''; ?></textarea>
                </div>
                
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input type="number" name="price" id="price" step="0.01" min="0" 
                               value="<?php echo $editService ? htmlspecialchars($editService['price']) : ''; ?>" required 
                               class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="status" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="active" <?php echo ($editService && $editService['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($editService && $editService['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div>
                    <div class="flex items-center">
                        <input type="checkbox" name="is_recurring" id="is_recurring" 
                               <?php echo ($editService && $editService['is_recurring'] == 1) ? 'checked' : ''; ?> 
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_recurring" class="ml-2 block text-sm text-gray-700">
                            Recurring Service
                        </label>
                    </div>
                </div>
                
                <div id="billingCycleContainer" class="<?php echo ($editService && $editService['is_recurring'] == 0) ? 'hidden' : ''; ?>">
                    <label for="billing_cycle" class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle</label>
                    <select name="billing_cycle" id="billing_cycle" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="monthly" <?php echo ($editService && $editService['billing_cycle'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                        <option value="quarterly" <?php echo ($editService && $editService['billing_cycle'] == 'quarterly') ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="biannually" <?php echo ($editService && $editService['billing_cycle'] == 'biannually') ? 'selected' : ''; ?>>Bi-annually</option>
                        <option value="annually" <?php echo ($editService && $editService['billing_cycle'] == 'annually') ? 'selected' : ''; ?>>Annually</option>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label for="service_image" class="block text-sm font-medium text-gray-700 mb-1">Service Image</label>
                    <?php if ($editService && !empty($editService['image_path'])): ?>
                        <div class="mb-2">
                            <img src="../<?php echo htmlspecialchars($editService['image_path']); ?>" alt="Service Image" class="h-32 w-auto object-cover rounded">
                            <p class="text-xs text-gray-500 mt-1">Upload a new image to replace the current one</p>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="service_image" id="service_image" accept="image/*"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, PNG, GIF. Max size: 5MB</p>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 pt-4">
                <button type="button" id="cancelBtn" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php echo $editService ? 'Update Service' : 'Create Service'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Services List -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Service
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Category
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Price
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Type
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                No services found. Create your first service to get started.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <?php if (!empty($service['image_path'])): ?>
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-md object-cover" src="../<?php echo htmlspecialchars($service['image_path']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>">
                                            </div>
                                        <?php else: ?>
                                            <div class="flex-shrink-0 h-10 w-10 bg-gray-200 rounded-md flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($service['name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500 max-w-xs truncate">
                                                <?php echo htmlspecialchars($service['description']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($service['category_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    $<?php echo number_format($service['price'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($service['is_recurring']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                            Recurring (<?php echo ucfirst(htmlspecialchars($service['billing_cycle'])); ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            One-time
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($service['status'] === 'active'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="?edit=<?php echo $service['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                        Edit
                                    </a>
                                    <button type="button" onclick="confirmDelete(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars(addslashes($service['name'])); ?>')" class="text-red-600 hover:text-red-900">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Delete Service
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500" id="delete-message">
                                Are you sure you want to delete this service? This action cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="service_id" id="delete_service_id">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Delete
                    </button>
                </form>
                <button type="button" id="cancelDeleteBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle service form visibility
    document.getElementById('addServiceBtn').addEventListener('click', function() {
        document.getElementById('serviceFormContainer').classList.remove('hidden');
    });

    document.getElementById('cancelBtn').addEventListener('click', function() {
        document.getElementById('serviceFormContainer').classList.add('hidden');
        // If we're in edit mode, redirect to the services page without the edit parameter
        if (window.location.search.includes('edit=')) {
            window.location.href = 'services.php';
        }
    });

    // Toggle billing cycle field based on recurring checkbox
    document.getElementById('is_recurring').addEventListener('change', function() {
        const billingCycleContainer = document.getElementById('billingCycleContainer');
        if (this.checked) {
            billingCycleContainer.classList.remove('hidden');
        } else {
            billingCycleContainer.classList.add('hidden');
        }
    });

    // Delete confirmation modal
    function confirmDelete(serviceId, serviceName) {
        document.getElementById('delete_service_id').value = serviceId;
        document.getElementById('delete-message').textContent = 
            'Are you sure you want to delete the service "' + serviceName + '"? This action cannot be undone.';
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    document.getElementById('cancelDeleteBtn').addEventListener('click', function() {
        document.getElementById('deleteModal').classList.add('hidden');
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.bg-red-100, .bg-green-100');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = 0;
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        });
    }, 5000);

    // Preview image before upload
    document.getElementById('service_image').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                // Check if there's an existing preview, if not create one
                let preview = document.querySelector('#image_preview');
                if (!preview) {
                    preview = document.createElement('img');
                    preview.id = 'image_preview';
                    preview.className = 'h-32 w-auto object-cover rounded mt-2';
                    preview.alt = 'Service Image Preview';
                    const container = document.querySelector('#service_image').parentNode;
                    container.insertBefore(preview, container.querySelector('p'));
                }
                preview.src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
</script>

<?php include '../templates/admin/footer.php'; ?>