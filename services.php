 
<?php
/**
 * Services.php - Browse, search and view digital services
 * 
 * This file handles:
 * - Listing all services with pagination
 * - Filtering by category/subcategory
 * - Searching services
 * - Viewing service details
 * - Adding services to cart
 */

// Start session if not already started
session_start();

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
$loggedIn = isLoggedIn();
$user = null;
if ($loggedIn) {
    $user = getCurrentUser();
}

// Get page parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$subcategory_id = isset($_GET['subcategory']) ? (int)$_GET['subcategory'] : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$service_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'grid'; // grid or list view
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest'; // newest, price_low, price_high, popularity

// Records per page
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Determine if we're viewing a single service or listing services
$single_service_view = ($service_id !== null);

// Handle Add to Cart action
if (isset($_POST['add_to_cart']) && $loggedIn) {
    $service_id_to_add = (int)$_POST['service_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    // Add service to cart
    $added = addToCart($user['id'], $service_id_to_add, $quantity);
    
    if ($added) {
        $_SESSION['success_message'] = 'Service added to cart successfully!';
    } else {
        $_SESSION['error_message'] = 'Failed to add service to cart. Please try again.';
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Handle Direct Billing action
if (isset($_POST['direct_billing']) && $loggedIn) {
    $service_id_to_bill = (int)$_POST['service_id'];
    
    // Add service to cart
    $added = addToCart($user['id'], $service_id_to_bill, 1);
    
    if ($added) {
        // Redirect to checkout
        header("Location: checkout.php?direct=1");
        exit;
    } else {
        $_SESSION['error_message'] = 'Failed to process direct billing. Please try again.';
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Load categories for filtering
$categories = getAllCategories();

// Database queries
if ($single_service_view) {
    // Get single service details
    $service = getServiceById($service_id);
    if (!$service) {
        // Service not found, redirect to services listing
        header("Location: services.php");
        exit;
    }
    
    // Get related services
    $related_services = getRelatedServices($service_id, $service['category_id'], 4);
} else {
    // Build query for services listing
    $where_conditions = ["status = 'active'"];
    $params = [];
    
    if ($category_id) {
        $where_conditions[] = "category_id = ?";
        $params[] = $category_id;
    }
    
    if ($subcategory_id) {
        $where_conditions[] = "subcategory_id = ?";
        $params[] = $subcategory_id;
    }
    
    if ($search_query) {
        $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    // Determine sort order
    switch ($sort_by) {
        case 'price_low':
            $order_by = "price ASC";
            break;
        case 'price_high':
            $order_by = "price DESC";
            break;
        case 'popularity':
            $order_by = "orders_count DESC";
            break;
        case 'newest':
        default:
            $order_by = "created_at DESC";
    }
    
    // Get services count for pagination
    $services_count = getServicesCount($where_clause, $params);
    $total_pages = ceil($services_count / $per_page);
    
    // Get services
    $services = getServices($where_clause, $params, $order_by, $per_page, $offset);
    
    // Get subcategories for the selected category
    $subcategories = [];
    if ($category_id) {
        $subcategories = getSubcategoriesByCategory($category_id);
    }
}

// Include header
$page_title = $single_service_view ? htmlspecialchars($service['name']) : 'Digital Services';
include 'templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['success_message']; ?></span>
            <button class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Close</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
            <button class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Close</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if ($single_service_view): ?>
        <!-- Single Service View -->
        <div class="mb-6">
            <a href="services.php" class="text-blue-600 hover:text-blue-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Services
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="md:flex">
                <div class="md:w-1/2">
                    <img src="<?php echo htmlspecialchars($service['image_url']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>" class="w-full h-auto object-cover" onerror="this.src='assets/images/service-placeholder.jpg'">
                </div>
                <div class="md:w-1/2 p-6">
                    <div class="flex justify-between items-start">
                        <h1 class="text-2xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($service['name']); ?></h1>
                        <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                            <?php echo getCategoryName($service['category_id']); ?>
                        </span>
                    </div>
                    
                    <div class="flex items-center mb-4">
                        <div class="flex items-center">
                            <?php
                            $rating = $service['rating'];
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $rating) {
                                    echo '<svg class="w-4 h-4 text-yellow-300" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>';
                                } else {
                                    echo '<svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>';
                                }
                            }
                            ?>
                            <span class="text-xs font-semibold text-gray-500 ml-1">(<?php echo $service['reviews_count']; ?> reviews)</span>
                        </div>
                    </div>
                    
                    <p class="text-2xl font-bold text-gray-900 mb-4">
                        <?php echo formatCurrency($service['price']); ?>
                        <?php if ($service['recurring']): ?>
                            <span class="text-sm font-normal text-gray-500">/ <?php echo $service['billing_cycle']; ?></span>
                        <?php endif; ?>
                    </p>
                    
                    <div class="prose max-w-none mb-6">
                        <?php echo nl2br(htmlspecialchars($service['description'])); ?>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <span class="text-gray-500 text-sm">Type</span>
                                <p class="font-medium"><?php echo $service['recurring'] ? 'Subscription' : 'One-time'; ?></p>
                            </div>
                            <?php if ($service['recurring']): ?>
                            <div>
                                <span class="text-gray-500 text-sm">Billing Cycle</span>
                                <p class="font-medium"><?php echo ucfirst($service['billing_cycle']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($loggedIn): ?>
                        <div class="flex flex-wrap gap-4">
                            <form method="post" class="inline-block">
                                <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                <button type="submit" name="add_to_cart" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    Add to Cart
                                </button>
                            </form>
                            
                            <form method="post" class="inline-block">
                                <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                <button type="submit" name="direct_billing" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    Direct Billing
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="bg-gray-100 p-4 rounded-lg text-center">
                            <p class="text-gray-600 mb-2">Please login to purchase this service</p>
                            <a href="login.php?redirect=services.php?id=<?php echo $service_id; ?>" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                                Login / Register
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($related_services)): ?>
        <div class="mt-10">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Related Services</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($related_services as $related): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <a href="services.php?id=<?php echo $related['id']; ?>">
                        <img class="w-full h-48 object-cover" src="<?php echo htmlspecialchars($related['image_url']); ?>" alt="<?php echo htmlspecialchars($related['name']); ?>" onerror="this.src='assets/images/service-placeholder.jpg'">
                    </a>
                    <div class="p-4">
                        <a href="services.php?id=<?php echo $related['id']; ?>" class="text-lg font-semibold text-gray-800 hover:text-blue-600"><?php echo htmlspecialchars($related['name']); ?></a>
                        <p class="text-gray-600 mt-2 text-sm line-clamp-2"><?php echo htmlspecialchars(substr($related['description'], 0, 100)) . (strlen($related['description']) > 100 ? '...' : ''); ?></p>
                        <div class="mt-4 flex justify-between items-center">
                            <span class="font-bold text-gray-900"><?php echo formatCurrency($related['price']); ?></span>
                            <a href="services.php?id=<?php echo $related['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View Details</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Services Listing View -->
        <div class="flex flex-col md:flex-row">
            <!-- Sidebar Filters -->
            <div class="md:w-1/4 pr-0 md:pr-6">
                <div class="bg-white rounded-lg shadow-md p-4 mb-6 sticky top-4">
                    <h2 class="text-lg font-semibold mb-4">Filter Services</h2>
                    
                    <!-- Search Box -->
                    <form action="services.php" method="get" class="mb-6">
                        <?php if ($category_id): ?>
                            <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                        <?php endif; ?>
                        <?php if ($subcategory_id): ?>
                            <input type="hidden" name="subcategory" value="<?php echo $subcategory_id; ?>">
                        <?php endif; ?>
                        <div class="relative">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search services..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <button type="submit" class="absolute right-2 top-2 text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Categories -->
                    <div class="mb-6">
                        <h3 class="font-medium text-gray-900 mb-2">Categories</h3>
                        <ul>
                            <li class="mb-1">
                                <a href="services.php" class="text-sm <?php echo (!$category_id) ? 'text-blue-600 font-medium' : 'text-gray-600 hover:text-blue-600'; ?>">
                                    All Categories
                                </a>
                            </li>
                            <?php foreach ($categories as $category): ?>
                            <li class="mb-1">
                                <a href="services.php?category=<?php echo $category['id']; ?>" class="text-sm <?php echo ($category_id == $category['id']) ? 'text-blue-600 font-medium' : 'text-gray-600 hover:text-blue-600'; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <!-- Subcategories (if category selected) -->
                    <?php if ($category_id && !empty($subcategories)): ?>
                    <div class="mb-6">
                        <h3 class="font-medium text-gray-900 mb-2">Subcategories</h3>
                        <ul>
                            <li class="mb-1">
                                <a href="services.php?category=<?php echo $category_id; ?>" class="text-sm <?php echo (!$subcategory_id) ? 'text-blue-600 font-medium' : 'text-gray-600 hover:text-blue-600'; ?>">
                                    All Subcategories
                                </a>
                            </li>
                            <?php foreach ($subcategories as $subcategory): ?>
                            <li class="mb-1">
                                <a href="services.php?category=<?php echo $category_id; ?>&subcategory=<?php echo $subcategory['id']; ?>" class="text-sm <?php echo ($subcategory_id == $subcategory['id']) ? 'text-blue-600 font-medium' : 'text-gray-600 hover:text-blue-600'; ?>">
                                    <?php echo htmlspecialchars($subcategory['name']); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Sort Order -->
                    <div class="mb-4">
                        <h3 class="font-medium text-gray-900 mb-2">Sort By</h3>
                        <form id="sort-form" action="services.php" method="get">
                            <?php if ($category_id): ?>
                                <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                            <?php endif; ?>
                            <?php if ($subcategory_id): ?>
                                <input type="hidden" name="subcategory" value="<?php echo $subcategory_id; ?>">
                            <?php endif; ?>
                            <?php if ($search_query): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                            <?php endif; ?>
                            <select name="sort" id="sort-select" onchange="document.getElementById('sort-form').submit()" class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="newest" <?php echo ($sort_by == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                                <option value="price_low" <?php echo ($sort_by == 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo ($sort_by == 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="popularity" <?php echo ($sort_by == 'popularity') ? 'selected' : ''; ?>>Popularity</option>
                            </select>
                        </form>
                    </div>
                    
                    <!-- View Mode -->
                    <div>
                        <h3 class="font-medium text-gray-900 mb-2">View</h3>
                        <div class="flex space-x-2">
                            <a href="<?php echo buildQueryString(['view' => 'grid']); ?>" class="flex-1 py-2 px-3 text-center rounded-md <?php echo ($view_mode == 'grid') ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                                <svg class="w-5 h-5 mx-auto" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                </svg>
                            </a>
                            <a href="<?php echo buildQueryString(['view' => 'list']); ?>" class="flex-1 py-2 px-3 text-center rounded-md <?php echo ($view_mode == 'list') ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                                <svg class="w-5 h-5 mx-auto" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="md:w-3/4">
                <!-- Services Header -->
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800">
                        <?php if ($search_query): ?>
                            Search Results for "<?php echo htmlspecialchars($search_query); ?>"
                        <?php elseif ($category_id && $subcategory_id): ?>
                            <?php echo htmlspecialchars(getCategoryName($category_id) . ' - ' . getSubcategoryName($subcategory_id)); ?>
                        <?php elseif ($category_id): ?>
                            <?php echo htmlspecialchars(getCategoryName($category_id)); ?>
                        <?php else: ?>
                            All Digital Services
                        <?php endif; ?>
                    </h1>
                    <p class="text-gray-600 text-sm"><?php echo $services_count; ?> services found</p>
                </div>
                
                <?php if (empty($services)): ?>
                <div class="bg-gray-50 rounded-lg p-8 text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">No services found</h3>
                    <p class="text-gray-500">
                        <?php if ($search_query): ?>
                            No results match your search criteria. Try different keywords or browse all services.
                        <?php else: ?>
                            No services available in this category. Please check back later or browse other categories.
                        <?php endif; ?>
                    </p>
                    <a href="services.php" class="mt-4 inline-flex items-center text-blue-600 hover:text-blue-800">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        View all services
                    </a>
                </div>
                <?php elseif ($view_mode == 'grid'): ?>
                <!-- Grid View -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($services as $service): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden flex flex-col">
                        <a href="services.php?id=<?php echo $service['id']; ?>">
                            <img class="w-full h-48 object-cover" src="<?php echo htmlspecialchars($service['image_url']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>" onerror="this.src='assets/images/service-placeholder.jpg'">
                        </a>
                        <div class="p-4 flex-grow">
                            <div class="flex justify-between items-start mb-2">
                                <a href="services.php?id=<?php echo $service['id']; ?>" class="text-lg font-semibold text-gray-800 hover:text-blue-600"><?php echo htmlspecialchars($service['name']); ?></a>
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                                    <?php echo getCategoryName($service['category_id']); ?>
                                </span>
                            </div>
                            <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars(substr($service['description'], 0, 100)) . (strlen($service['description']) > 100 ? '...' : ''); ?></p>
                            <div class="flex items-center mb-2">
                                <?php
                                $rating = $service['rating'];
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<svg class="w-4 h-4 text-yellow-300" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>';
                                    } else {
                                        echo '<svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>';
                                    }
                                }
                                ?>
                                <span class="text-xs text-gray-500 ml-1">(<?php echo $service['reviews_count']; ?>)</span>
                            </div>
                        </div>
                        <div class="p-4 border-t border-gray-200 mt-auto">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-gray-900">
                                    <?php echo formatCurrency($service['price']); ?>
                                    <?php if ($service['recurring']): ?>
                                        <span class="text-xs font-normal text-gray-500">/ <?php echo $service['billing_cycle']; ?></span>
                                    <?php endif; ?>
                                </span>
                                <a href="services.php?id=<?php echo $service['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View Details</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <!-- List View -->
                <div class="space-y-4">
                    <?php foreach ($services as $service): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="md:flex">
                            <div class="md:w-1/4">
                                <a href="services.php?id=<?php echo $service['id']; ?>">
                                    <img class="w-full h-48 md:h-full object-cover" src="<?php echo htmlspecialchars($service['image_url']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>" onerror="this.src='assets/images/service-placeholder.jpg'">
                                </a>
                            </div>
                            <div class="md:w-3/4 p-4">
                                <div class="flex justify-between items-start">
                                    <a href="services.php?id=<?php echo $service['id']; ?>" class="text-lg font-semibold text-gray-800 hover:text-blue-600"><?php echo htmlspecialchars($service['name']); ?></a>
                                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                                        <?php echo getCategoryName($service['category_id']); ?>
                                    </span>
                                </div>
                                
                                <div class="flex items-center mt-1 mb-2">
                                    <?php
                                    $rating = $service['rating'];
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '<svg class="w-4 h-4 text-yellow-300" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>';
                                        } else {
                                            echo '<svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>';
                                        }
                                    }
                                    ?>
                                    <span class="text-xs text-gray-500 ml-1">(<?php echo $service['reviews_count']; ?>)</span>
                                </div>
                                
                                <p class="text-gray-600 text-sm mb-3"><?php echo htmlspecialchars(substr($service['description'], 0, 150)) . (strlen($service['description']) > 150 ? '...' : ''); ?></p>
                                
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-900">
                                        <?php echo formatCurrency($service['price']); ?>
                                        <?php if ($service['recurring']): ?>
                                            <span class="text-xs font-normal text-gray-500">/ <?php echo $service['billing_cycle']; ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <div class="flex space-x-2">
                                        <?php if ($loggedIn): ?>
                                        <form method="post" class="inline-block">
                                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                            <button type="submit" name="add_to_cart" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-1 px-3 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                                                Add to Cart
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <a href="services.php?id=<?php echo $service['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium py-1 px-3 border border-blue-600 rounded">View Details</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="mt-8 flex justify-center">
                    <nav class="flex items-center space-x-1 text-sm">
                        <?php if ($page > 1): ?>
                        <a href="<?php echo buildQueryString(['page' => $page - 1]); ?>" class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50">
                            <span class="sr-only">Previous</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                        <?php else: ?>
                        <span class="px-4 py-2 border rounded-md text-gray-300 cursor-not-allowed">
                            <span class="sr-only">Previous</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $start_page + 4);
                        if ($end_page - $start_page < 4) {
                            $start_page = max(1, $end_page - 4);
                        }
                        
                        if ($start_page > 1): ?>
                        <a href="<?php echo buildQueryString(['page' => 1]); ?>" class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50">1</a>
                        <?php if ($start_page > 2): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i == $page): ?>
                        <span class="px-4 py-2 border rounded-md bg-blue-50 border-blue-500 text-blue-600"><?php echo $i; ?></span>
                        <?php else: ?>
                        <a href="<?php echo buildQueryString(['page' => $i]); ?>" class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50"><?php echo $i; ?></a>
                        <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-4 py-2 text-gray-500">...</span>
                        <?php endif; ?>
                        <a href="<?php echo buildQueryString(['page' => $total_pages]); ?>" class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50"><?php echo $total_pages; ?></a>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="<?php echo buildQueryString(['page' => $page + 1]); ?>" class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50">
                            <span class="sr-only">Next</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                        <?php else: ?>
                        <span class="px-4 py-2 border rounded-md text-gray-300 cursor-not-allowed">
                            <span class="sr-only">Next</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <?php endif; ?>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Helper function to build query string for pagination and view switching
function buildQueryString($params_to_update = []) {
    $current_params = $_GET;
    $params = array_merge($current_params, $params_to_update);
    return 'services.php?' . http_build_query($params);
}

// Include footer
include 'templates/footer.php';
?>