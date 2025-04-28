 
<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/invoice-functions.php';
require_once 'includes/payment-functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Save current page to redirect back after login
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header('Location: login.php');
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Initialize variables
$cart = [];
$cart_total = 0;
$tax_amount = 0;
$discount_amount = 0;
$grand_total = 0;
$error = '';
$success = '';
$coupon_code = '';
$coupon_valid = false;
$coupon_discount = 0;
$available_payment_methods = getAvailablePaymentMethods();

// Check if cart exists in session
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $cart = $_SESSION['cart'];
    
    // Calculate cart total
    foreach ($cart as $item) {
        $cart_total += $item['price'] * $item['quantity'];
    }
    
    // Calculate tax (example: 10%)
    $tax_rate = getTaxRate(); // Get from settings
    $tax_amount = $cart_total * ($tax_rate / 100);
    
    // Check if coupon code is submitted
    if (isset($_POST['apply_coupon']) && isset($_POST['coupon_code'])) {
        $coupon_code = trim($_POST['coupon_code']);
        
        // Validate coupon code
        $coupon = validateCoupon($coupon_code, $user_id, $cart_total);
        
        if ($coupon['valid']) {
            $coupon_valid = true;
            $discount_amount = $coupon['discount_amount'];
            $success = 'Coupon applied successfully!';
            
            // Store coupon in session
            $_SESSION['applied_coupon'] = [
                'code' => $coupon_code,
                'discount_amount' => $discount_amount
            ];
        } else {
            $error = $coupon['message'];
        }
    }
    
    // Check if coupon is already applied in session
    if (isset($_SESSION['applied_coupon'])) {
        $coupon_code = $_SESSION['applied_coupon']['code'];
        $discount_amount = $_SESSION['applied_coupon']['discount_amount'];
        $coupon_valid = true;
    }
    
    // Calculate grand total
    $grand_total = $cart_total + $tax_amount - $discount_amount;
    
    // Process checkout
    if (isset($_POST['process_payment'])) {
        $payment_method = $_POST['payment_method'];
        $billing_address = [
            'name' => $_POST['billing_name'],
            'email' => $_POST['billing_email'],
            'phone' => $_POST['billing_phone'],
            'address' => $_POST['billing_address'],
            'city' => $_POST['billing_city'],
            'state' => $_POST['billing_state'],
            'zip' => $_POST['billing_zip'],
            'country' => $_POST['billing_country']
        ];
        
        // Save billing address to user profile if checkbox is checked
        if (isset($_POST['save_billing_info'])) {
            saveBillingInfo($user_id, $billing_address);
        }
        
        // Create order in database
        $order_id = createOrder($user_id, $cart, $cart_total, $tax_amount, $discount_amount, $grand_total, $coupon_code);
        
        if ($order_id) {
            // Generate invoice
            $invoice_id = generateInvoice($order_id);
            
            if ($invoice_id) {
                // Process payment based on selected method
                $payment_result = processPayment($payment_method, $grand_total, $invoice_id, $user_id, $billing_address);
                
                if ($payment_result['status'] === 'success') {
                    // Clear cart and coupon from session
                    unset($_SESSION['cart']);
                    unset($_SESSION['applied_coupon']);
                    
                    // Redirect to success page
                    $_SESSION['payment_success'] = [
                        'order_id' => $order_id,
                        'invoice_id' => $invoice_id,
                        'amount' => $grand_total,
                        'transaction_id' => $payment_result['transaction_id']
                    ];
                    
                    header('Location: payment-success.php');
                    exit;
                } else {
                    $error = 'Payment failed: ' . $payment_result['message'];
                    
                    // For failed payments, mark invoice as pending
                    updateInvoiceStatus($invoice_id, 'pending');
                }
            } else {
                $error = 'Failed to generate invoice. Please try again.';
            }
        } else {
            $error = 'Failed to create order. Please try again.';
        }
    }
} else {
    // Redirect to cart if empty
    header('Location: cart.php?error=cart_empty');
    exit;
}

// Get saved billing info if available
$saved_billing_info = getBillingInfo($user_id);

// Page title
$page_title = 'Checkout';

// Include header
include 'templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl md:text-3xl font-bold mb-6">Checkout</h1>
    
    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p><?php echo $error; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p><?php echo $success; ?></p>
        </div>
    <?php endif; ?>
    
    <div class="lg:flex lg:gap-6">
        <!-- Checkout Form -->
        <div class="lg:w-2/3">
            <form method="post" action="checkout.php" id="checkout-form" class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Billing Information</h2>
                
                <?php if ($saved_billing_info): ?>
                    <div class="mb-4">
                        <div class="flex items-center mb-2">
                            <input type="checkbox" id="use_saved_billing" name="use_saved_billing" class="mr-2" checked>
                            <label for="use_saved_billing" class="text-sm font-medium">Use saved billing information</label>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div id="billing-info-fields">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="billing_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" id="billing_name" name="billing_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo $saved_billing_info ? $saved_billing_info['name'] : $user['full_name']; ?>" required>
                        </div>
                        <div>
                            <label for="billing_email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <input type="email" id="billing_email" name="billing_email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo $saved_billing_info ? $saved_billing_info['email'] : $user['email']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="billing_phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="tel" id="billing_phone" name="billing_phone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo $saved_billing_info ? $saved_billing_info['phone'] : ''; ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="billing_address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input type="text" id="billing_address" name="billing_address" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo $saved_billing_info ? $saved_billing_info['address'] : ''; ?>" required>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="billing_city" class="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <input type="text" id="billing_city" name="billing_city" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo $saved_billing_info ? $saved_billing_info['city'] : ''; ?>" required>
                        </div>
                        <div>
                            <label for="billing_state" class="block text-sm font-medium text-gray-700 mb-1">State/Province</label>
                            <input type="text" id="billing_state" name="billing_state" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo $saved_billing_info ? $saved_billing_info['state'] : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="billing_zip" class="block text-sm font-medium text-gray-700 mb-1">ZIP/Postal Code</label>
                            <input type="text" id="billing_zip" name="billing_zip" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo $saved_billing_info ? $saved_billing_info['zip'] : ''; ?>" required>
                        </div>
                        <div>
                            <label for="billing_country" class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                            <select id="billing_country" name="billing_country" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <option value="">Select Country</option>
                                <?php 
                                $countries = getCountries();
                                $selected_country = $saved_billing_info ? $saved_billing_info['country'] : '';
                                
                                foreach ($countries as $code => $name) {
                                    echo '<option value="' . $code . '"' . ($code === $selected_country ? ' selected' : '') . '>' . $name . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <div class="flex items-center">
                            <input type="checkbox" id="save_billing_info" name="save_billing_info" class="mr-2" checked>
                            <label for="save_billing_info" class="text-sm font-medium">Save billing information for future purchases</label>
                        </div>
                    </div>
                </div>
                
                <h2 class="text-xl font-semibold mb-4">Payment Method</h2>
                
                <div class="mb-6">
                    <?php foreach ($available_payment_methods as $method): ?>
                        <div class="flex items-center p-3 border rounded-md mb-2 hover:bg-gray-50">
                            <input type="radio" id="payment_method_<?php echo $method['id']; ?>" name="payment_method" value="<?php echo $method['id']; ?>" class="mr-2" <?php echo ($method['id'] === 'credit_card') ? 'checked' : ''; ?> required>
                            <label for="payment_method_<?php echo $method['id']; ?>" class="flex items-center cursor-pointer">
                                <img src="assets/images/payment/<?php echo $method['icon']; ?>" alt="<?php echo $method['name']; ?>" class="h-8 w-12 object-contain mr-2">
                                <span class="font-medium"><?php echo $method['name']; ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Credit Card Fields (shown/hidden based on selected payment method) -->
                <div id="credit-card-fields" class="mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="card_number" class="block text-sm font-medium text-gray-700 mb-1">Card Number</label>
                            <input type="text" id="card_number" name="card_number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="1234 5678 9012 3456">
                        </div>
                        <div>
                            <label for="card_name" class="block text-sm font-medium text-gray-700 mb-1">Name on Card</label>
                            <input type="text" id="card_name" name="card_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="card_expiry" class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                            <input type="text" id="card_expiry" name="card_expiry" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="MM/YY">
                        </div>
                        <div>
                            <label for="card_cvv" class="block text-sm font-medium text-gray-700 mb-1">CVV</label>
                            <input type="text" id="card_cvv" name="card_cvv" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="123">
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="process_payment" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-md transition duration-200">Complete Payment</button>
            </form>
        </div>
        
        <!-- Order Summary -->
        <div class="lg:w-1/3">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6 sticky top-6">
                <h2 class="text-xl font-semibold mb-4">Order Summary</h2>
                
                <div class="border-b pb-4 mb-4">
                    <?php foreach ($cart as $item): ?>
                        <div class="flex justify-between mb-2">
                            <div>
                                <span class="font-medium"><?php echo $item['name']; ?></span>
                                <?php if ($item['quantity'] > 1): ?>
                                    <span class="text-gray-600 text-sm"> × <?php echo $item['quantity']; ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="font-medium"><?php echo formatCurrency($item['price'] * $item['quantity']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-medium"><?php echo formatCurrency($cart_total); ?></span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-600">Tax (<?php echo $tax_rate; ?>%)</span>
                        <span class="font-medium"><?php echo formatCurrency($tax_amount); ?></span>
                    </div>
                    <?php if ($discount_amount > 0): ?>
                        <div class="flex justify-between mb-2 text-green-600">
                            <span>Discount (<?php echo $coupon_code; ?>)</span>
                            <span class="font-medium">-<?php echo formatCurrency($discount_amount); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Coupon Code Form -->
                <?php if (!$coupon_valid): ?>
                    <form method="post" action="checkout.php" class="mb-4">
                        <div class="flex">
                            <input type="text" name="coupon_code" placeholder="Coupon code" class="flex-grow px-3 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="submit" name="apply_coupon" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-r-md transition duration-200">Apply</button>
                        </div>
                    </form>
                <?php endif; ?>
                
                <div class="border-t pt-4">
                    <div class="flex justify-between">
                        <span class="text-lg font-semibold">Total</span>
                        <span class="text-lg font-bold"><?php echo formatCurrency($grand_total); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle saved billing info
    const useSavedBillingCheckbox = document.getElementById('use_saved_billing');
    const billingInfoFields = document.getElementById('billing-info-fields');
    
    if (useSavedBillingCheckbox) {
        useSavedBillingCheckbox.addEventListener('change', function() {
            const inputs = billingInfoFields.querySelectorAll('input, select');
            
            if (this.checked) {
                // Fill in saved billing info
                <?php if ($saved_billing_info): ?>
                document.getElementById('billing_name').value = '<?php echo addslashes($saved_billing_info['name']); ?>';
                document.getElementById('billing_email').value = '<?php echo addslashes($saved_billing_info['email']); ?>';
                document.getElementById('billing_phone').value = '<?php echo addslashes($saved_billing_info['phone']); ?>';
                document.getElementById('billing_address').value = '<?php echo addslashes($saved_billing_info['address']); ?>';
                document.getElementById('billing_city').value = '<?php echo addslashes($saved_billing_info['city']); ?>';
                document.getElementById('billing_state').value = '<?php echo addslashes($saved_billing_info['state']); ?>';
                document.getElementById('billing_zip').value = '<?php echo addslashes($saved_billing_info['zip']); ?>';
                document.getElementById('billing_country').value = '<?php echo addslashes($saved_billing_info['country']); ?>';
                <?php endif; ?>
            } else {
                // Clear fields
                inputs.forEach(input => {
                    if (input.type !== 'checkbox') {
                        input.value = '';
                    }
                });
                
                // Set default email from user account
                document.getElementById('billing_email').value = '<?php echo addslashes($user['email']); ?>';
                document.getElementById('billing_name').value = '<?php echo addslashes($user['full_name']); ?>';
            }
        });
    }
    
    // Toggle payment method fields
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const creditCardFields = document.getElementById('credit-card-fields');
    
    paymentMethods.forEach(method => {
        method.addEventListener('change', function() {
            if (this.value === 'credit_card') {
                creditCardFields.style.display = 'block';
            } else {
                creditCardFields.style.display = 'none';
            }
        });
    });
    
    // Format credit card input
    const cardNumberInput = document.getElementById('card_number');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            let formattedValue = '';
            
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            
            e.target.value = formattedValue.substring(0, 19); // Limit to 16 digits + 3 spaces
        });
    }
    
    // Format expiry date input
    const cardExpiryInput = document.getElementById('card_expiry');
    if (cardExpiryInput) {
        cardExpiryInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            let formattedValue = '';
            
            if (value.length > 0) {
                formattedValue = value.substring(0, 2);
                if (value.length > 2) {
                    formattedValue += '/' + value.substring(2, 4);
                }
            }
            
            e.target.value = formattedValue;
        });
    }
    
    // Validate CVV input (numbers only)
    const cardCvvInput = document.getElementById('card_cvv');
    if (cardCvvInput) {
        cardCvvInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
        });
    }
    
    // Form validation before submission
    const checkoutForm = document.getElementById('checkout-form');
    checkoutForm.addEventListener('submit', function(e) {
        const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
        
        if (selectedPaymentMethod === 'credit_card') {
            const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
            const cardExpiry = document.getElementById('card_expiry').value;
            const cardCvv = document.getElementById('card_cvv').value;
            const cardName = document.getElementById('card_name').value;
            
            if (!cardNumber || cardNumber.length < 13) {
                alert('Please enter a valid card number');
                e.preventDefault();
                return false;
            }
            
            if (!cardExpiry || !cardExpiry.includes('/')) {
                alert('Please enter a valid expiry date (MM/YY)');
                e.preventDefault();
                return false;
            }
            
            if (!cardCvv || cardCvv.length < 3) {
                alert('Please enter a valid CVV code');
                e.preventDefault();
                return false;
            }
            
            if (!cardName.trim()) {
                alert('Please enter the name on the card');
                e.preventDefault();
                return false;
            }
        }
    });
});
</script>

<?php
// Include footer
include 'templates/footer.php';
?>