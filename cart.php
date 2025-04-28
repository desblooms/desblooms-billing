 
<?php
/**
 * Cart Page for Digital Service Billing Mobile App
 * Handles displaying cart items, updating quantities, removing items,
 * applying discount codes, and proceeding to checkout
 */

// Include required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isLoggedIn()) {
    // Store current page as redirect destination after login
    $_SESSION['redirect_after_login'] = 'cart.php';
    header('Location: login.php');
    exit;
}

// Initialize variables
$userId = $_SESSION['user_id'];
$errorMsg = '';
$successMsg = '';
$cartItems = [];
$cartTotal = 0;
$discountAmount = 0;
$taxAmount = 0;
$finalTotal = 0;
$appliedCoupon = isset($_SESSION['applied_coupon']) ? $_SESSION['applied_coupon'] : null;

// Process cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle remove item from cart
    if (isset($_POST['remove_item']) && isset($_POST['item_id'])) {
        $itemId = filter_input(INPUT_POST, 'item_id', FILTER_SANITIZE_NUMBER_INT);
        removeFromCart($userId, $itemId);
        $successMsg = 'Item removed from cart.';
    }
    
    // Handle update item quantity
    if (isset($_POST['update_quantity']) && isset($_POST['item_id']) && isset($_POST['quantity'])) {
        $itemId = filter_input(INPUT_POST, 'item_id', FILTER_SANITIZE_NUMBER_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT);
        
        if ($quantity > 0) {
            updateCartItemQuantity($userId, $itemId, $quantity);
            $successMsg = 'Cart updated successfully.';
        } else {
            $errorMsg = 'Quantity must be greater than zero.';
        }
    }
    
    // Handle apply coupon code
    if (isset($_POST['apply_coupon']) && isset($_POST['coupon_code'])) {
        $couponCode = filter_input(INPUT_POST, 'coupon_code', FILTER_SANITIZE_STRING);
        $couponResult = applyCouponCode($userId, $couponCode);
        
        if ($couponResult['success']) {
            $_SESSION['applied_coupon'] = [
                'code' => $couponCode,
                'type' => $couponResult['type'],
                'value' => $couponResult['value']
            ];
            $successMsg = 'Coupon applied successfully.';
            $appliedCoupon = $_SESSION['applied_coupon'];
        } else {
            $errorMsg = $couponResult['message'];
        }
    }
    
    // Handle remove coupon
    if (isset($_POST['remove_coupon'])) {
        unset($_SESSION['applied_coupon']);
        $appliedCoupon = null;
        $successMsg = 'Coupon removed.';
    }
    
    // Handle proceed to checkout
    if (isset($_POST['checkout'])) {
        // Check if cart is not empty
        if (getCartItemCount($userId) > 0) {
            header('Location: checkout.php');
            exit;
        } else {
            $errorMsg = 'Your cart is empty. Please add services before checkout.';
        }
    }
}

// Get cart items
$cartItems = getCartItems($userId);

// Calculate totals
$cartTotal = calculateCartSubtotal($userId);

// Apply discount if coupon is applied
if ($appliedCoupon) {
    if ($appliedCoupon['type'] === 'percentage') {
        $discountAmount = ($cartTotal * $appliedCoupon['value']) / 100;
    } else {
        $discountAmount = $appliedCoupon['value'];
    }
    // Ensure discount doesn't exceed cart total
    $discountAmount = min($discountAmount, $cartTotal);
}

// Apply tax
$taxRate = getSystemTaxRate(); // Get from system settings
$taxableAmount = $cartTotal - $discountAmount;
$taxAmount = ($taxableAmount * $taxRate) / 100;

// Calculate final total
$finalTotal = $taxableAmount + $taxAmount;

// Helper functions (these would typically be in includes/functions.php)
function getCartItems($userId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT c.id, c.service_id, c.quantity, c.price, s.name, s.description, s.image_url
        FROM cart c
        JOIN services s ON c.service_id = s.id
        WHERE c.user_id = ?
        ORDER BY c.id DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    return $items;
}

function removeFromCart($userId, $itemId) {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $itemId, $userId);
    $stmt->execute();
    
    return $stmt->affected_rows > 0;
}

function updateCartItemQuantity($userId, $itemId, $quantity) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("iii", $quantity, $itemId, $userId);
    $stmt->execute();
    
    return $stmt->affected_rows > 0;
}

function getCartItemCount($userId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

function calculateCartSubtotal($userId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT SUM(price * quantity) as subtotal FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['subtotal'] ?: 0;
}

function applyCouponCode($userId, $couponCode) {
    global $conn;
    
    // Check if coupon exists and is valid
    $stmt = $conn->prepare("
        SELECT * FROM coupons 
        WHERE code = ? 
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        AND (max_uses IS NULL OR uses < max_uses)
        AND active = 1
    ");
    $stmt->bind_param("s", $couponCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return [
            'success' => false,
            'message' => 'Invalid or expired coupon code.'
        ];
    }
    
    $coupon = $result->fetch_assoc();
    
    // Check if user has already used this coupon
    if ($coupon['one_per_user']) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as used 
            FROM orders 
            WHERE user_id = ? AND coupon_code = ?
        ");
        $stmt->bind_param("is", $userId, $couponCode);
        $stmt->execute();
        $usageResult = $stmt->get_result();
        $usageRow = $usageResult->fetch_assoc();
        
        if ($usageRow['used'] > 0) {
            return [
                'success' => false,
                'message' => 'You have already used this coupon.'
            ];
        }
    }
    
    // Check minimum order value if applicable
    if ($coupon['min_order_value'] > 0) {
        $cartTotal = calculateCartSubtotal($userId);
        
        if ($cartTotal < $coupon['min_order_value']) {
            return [
                'success' => false,
                'message' => 'Minimum order value of $' . number_format($coupon['min_order_value'], 2) . ' required for this coupon.'
            ];
        }
    }
    
    // Coupon is valid, return discount info
    return [
        'success' => true,
        'type' => $coupon['discount_type'], // 'percentage' or 'fixed'
        'value' => $coupon['discount_value']
    ];
}

function getSystemTaxRate() {
    global $conn;
    
    // This would typically come from a system settings table
    $stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'tax_rate'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (float)$row['value'];
    }
    
    return 0; // Default tax rate if not found
}

// Include header
$pageTitle = "Shopping Cart";
include 'templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-6">Your Cart</h1>
    
    <?php if (!empty($errorMsg)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p><?php echo $errorMsg; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($successMsg)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p><?php echo $successMsg; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($cartItems)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <p class="text-center text-gray-600">Your cart is empty. <a href="services.php" class="text-blue-500 hover:underline">Browse services</a> to add items to your cart.</p>
        </div>
    <?php else: ?>
        <div class="flex flex-col md:flex-row gap-6">
            <!-- Cart Items Section -->
            <div class="md:w-2/3">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <!-- Cart Items Table -->
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($cartItems as $item): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <?php if (!empty($item['image_url'])): ?>
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <img class="h-10 w-10 rounded-full" src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                </div>
                                            <?php endif; ?>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($item['description'], 0, 50)) . (strlen($item['description']) > 50 ? '...' : ''); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">$<?php echo number_format($item['price'], 2); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <form method="post" action="cart.php" class="flex items-center">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="99" class="w-16 p-1 border border-gray-300 rounded-md text-center">
                                            <button type="submit" name="update_quantity" class="ml-2 p-1 text-blue-600 hover:text-blue-800">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <form method="post" action="cart.php" onsubmit="return confirm('Are you sure you want to remove this item?');">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="remove_item" class="text-red-600 hover:text-red-900">
                                                Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Order Summary Section -->
            <div class="md:w-1/3 mt-6 md:mt-0">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-medium">$<?php echo number_format($cartTotal, 2); ?></span>
                        </div>
                        
                        <?php if ($discountAmount > 0): ?>
                            <div class="flex justify-between text-green-600">
                                <span>Discount (<?php echo $appliedCoupon['code']; ?>)</span>
                                <span>-$<?php echo number_format($discountAmount, 2); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax (<?php echo number_format($taxRate, 2); ?>%)</span>
                            <span>$<?php echo number_format($taxAmount, 2); ?></span>
                        </div>
                        
                        <div class="border-t pt-4 flex justify-between">
                            <span class="font-semibold">Total</span>
                            <span class="font-bold text-xl">$<?php echo number_format($finalTotal, 2); ?></span>
                        </div>
                    </div>
                    
                    <!-- Coupon Code Form -->
                    <div class="mt-6 mb-6">
                        <h3 class="text-sm font-medium mb-2">Have a coupon code?</h3>
                        
                        <?php if ($appliedCoupon): ?>
                            <div class="bg-green-50 border border-green-200 rounded-md p-3 mb-3">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <span class="font-semibold"><?php echo htmlspecialchars($appliedCoupon['code']); ?></span>
                                        <span class="text-sm text-gray-600 ml-2">
                                            <?php echo $appliedCoupon['type'] === 'percentage' ? $appliedCoupon['value'] . '% off' : '$' . number_format($appliedCoupon['value'], 2) . ' off'; ?>
                                        </span>
                                    </div>
                                    <form method="post" action="cart.php">
                                        <button type="submit" name="remove_coupon" class="text-red-600 hover:text-red-800 text-sm">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="post" action="cart.php" class="flex space-x-2">
                                <input type="text" name="coupon_code" placeholder="Enter coupon code" class="flex-1 p-2 border border-gray-300 rounded-md">
                                <button type="submit" name="apply_coupon" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                                    Apply
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Checkout Button -->
                    <form method="post" action="cart.php">
                        <button type="submit" name="checkout" class="w-full bg-green-500 text-white py-3 rounded-md font-semibold hover:bg-green-600 transition duration-200">
                            Proceed to Checkout
                        </button>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <a href="services.php" class="text-blue-500 hover:underline text-sm">
                            Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'templates/footer.php'; ?>