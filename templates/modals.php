 
<?php
/**
 * Reusable Modal Templates
 * 
 * This file contains all the modal structures used throughout the application.
 * Modals are hidden by default and shown via JavaScript.
 */

// Prevent direct access
if (!defined('APP_ACCESS')) {
    header('Location: ../index.php');
    exit();
}
?>

<!-- Modal backdrop - shared by all modals -->
<div id="modal-backdrop" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden transition-opacity duration-300 opacity-0"></div>

<!-- Confirm Action Modal -->
<div id="confirm-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
        <div class="p-5">
            <div class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
                <h3 id="confirm-title" class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Action</h3>
                <button type="button" class="modal-close text-gray-400 hover:text-gray-500 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="py-4">
                <p id="confirm-message" class="text-gray-700 dark:text-gray-300">Are you sure you want to perform this action?</p>
            </div>
            <div class="flex justify-end space-x-3 pt-3 border-t dark:border-gray-700">
                <button type="button" class="modal-close px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                    Cancel
                </button>
                <button id="confirm-action" type="button" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Alert Modal -->
<div id="alert-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
        <div class="p-5">
            <div class="flex justify-between items-center pb-3">
                <h3 id="alert-title" class="text-lg font-semibold text-gray-900 dark:text-white">Notification</h3>
                <button type="button" class="modal-close text-gray-400 hover:text-gray-500 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="py-4">
                <div id="alert-icon-container" class="mb-4 flex justify-center">
                    <!-- Will be populated via JS with success/error/warning icon -->
                </div>
                <p id="alert-message" class="text-center text-gray-700 dark:text-gray-300">Alert message here</p>
            </div>
            <div class="flex justify-center pt-3">
                <button type="button" class="modal-close w-full sm:w-auto px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="payment-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
        <div class="p-5">
            <div class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Complete Payment</h3>
                <button type="button" class="modal-close text-gray-400 hover:text-gray-500 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="payment-details" class="py-4">
                <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-md">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-600 dark:text-gray-300">Invoice:</span>
                        <span id="payment-invoice-id" class="font-medium text-gray-800 dark:text-gray-200">#INV-0000</span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-600 dark:text-gray-300">Service:</span>
                        <span id="payment-service-name" class="font-medium text-gray-800 dark:text-gray-200">Service Name</span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-600 dark:text-gray-300">Due Date:</span>
                        <span id="payment-due-date" class="font-medium text-gray-800 dark:text-gray-200">Jan 01, 2023</span>
                    </div>
                    <div class="flex justify-between font-medium pt-2 border-t dark:border-gray-600">
                        <span class="text-gray-800 dark:text-white">Total Amount:</span>
                        <span id="payment-amount" class="text-blue-600 dark:text-blue-400">$0.00</span>
                    </div>
                </div>
                
                <form id="payment-form" class="space-y-4">
                    <input type="hidden" id="payment-invoice-id-input" name="invoice_id" value="">
                    
                    <div class="space-y-2">
                        <label for="payment-method" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Method</label>
                        <select id="payment-method" name="payment_method" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="credit_card">Credit Card</option>
                            <option value="paypal">PayPal</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="wallet">Wallet Balance</option>
                        </select>
                    </div>
                    
                    <!-- Credit Card Section - shown/hidden based on payment method -->
                    <div id="credit-card-section" class="space-y-4">
                        <div class="space-y-2">
                            <label for="card-number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Card Number</label>
                            <input type="text" id="card-number" name="card_number" placeholder="1234 5678 9012 3456" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label for="card-expiry" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Expiry Date</label>
                                <input type="text" id="card-expiry" name="card_expiry" placeholder="MM/YY" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div class="space-y-2">
                                <label for="card-cvc" class="block text-sm font-medium text-gray-700 dark:text-gray-300">CVC</label>
                                <input type="text" id="card-cvc" name="card_cvc" placeholder="123" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <label for="card-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cardholder Name</label>
                            <input type="text" id="card-name" name="card_name" placeholder="John Doe" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                    </div>
                    
                    <!-- PayPal Section -->
                    <div id="paypal-section" class="hidden space-y-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">You will be redirected to PayPal to complete your payment.</p>
                    </div>
                    
                    <!-- Bank Transfer Section -->
                    <div id="bank-transfer-section" class="hidden space-y-4">
                        <div class="p-3 bg-yellow-50 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded-md text-sm">
                            Please transfer the exact amount to the bank account below. Your invoice will be marked as paid once we confirm the transfer (usually within 24 hours).
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-md text-sm">
                            <p class="mb-1"><span class="font-medium">Bank Name:</span> Example Bank</p>
                            <p class="mb-1"><span class="font-medium">Account Name:</span> Your Company Ltd</p>
                            <p class="mb-1"><span class="font-medium">Account Number:</span> 12345678</p>
                            <p class="mb-1"><span class="font-medium">Sort Code:</span> 01-02-03</p>
                            <p class="mb-1"><span class="font-medium">Reference:</span> <span id="bank-reference">INV-0000</span></p>
                        </div>
                    </div>
                    
                    <!-- Wallet Section -->
                    <div id="wallet-section" class="hidden space-y-4">
                        <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-md">
                            <div class="flex justify-between mb-2">
                                <span class="text-gray-600 dark:text-gray-300">Current Balance:</span>
                                <span id="wallet-balance" class="font-medium text-gray-800 dark:text-gray-200">$0.00</span>
                            </div>
                            <div class="flex justify-between mb-2">
                                <span class="text-gray-600 dark:text-gray-300">Amount Due:</span>
                                <span id="wallet-amount-due" class="font-medium text-gray-800 dark:text-gray-200">$0.00</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t dark:border-gray-600">
                                <span class="text-gray-600 dark:text-gray-300">Remaining Balance:</span>
                                <span id="wallet-remaining" class="font-medium text-gray-800 dark:text-gray-200">$0.00</span>
                            </div>
                        </div>
                        <div id="insufficient-funds" class="hidden p-3 bg-red-50 text-red-700 dark:bg-red-900 dark:text-red-200 rounded-md text-sm">
                            Insufficient funds in your wallet. Please add funds or choose another payment method.
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t dark:border-gray-700">
                        <button type="button" class="modal-close px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" id="submit-payment" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Pay Now
                        </button>
                    </div>
                </form>
            </div>
            <div id="payment-processing" class="py-8 text-center hidden">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-600 mb-4"></div>
                <p class="text-gray-700 dark:text-gray-300">Processing your payment...</p>
            </div>
            <div id="payment-result" class="py-8 text-center hidden">
                <div id="payment-success" class="hidden">
                    <svg class="mx-auto h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <p class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Payment Successful!</p>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">Your invoice has been marked as paid.</p>
                </div>
                <div id="payment-error" class="hidden">
                    <svg class="mx-auto h-12 w-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <p class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Payment Failed</p>
                    <p id="payment-error-message" class="mt-2 text-gray-600 dark:text-gray-400">There was an error processing your payment.</p>
                </div>
                <button type="button" id="payment-done" class="mt-6 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Done
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Service Details Modal -->
<div id="service-details-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-3xl w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
        <div class="p-5">
            <div class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
                <h3 id="service-name" class="text-lg font-semibold text-gray-900 dark:text-white">Service Name</h3>
                <button type="button" class="modal-close text-gray-400 hover:text-gray-500 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="py-4">
                <div class="md:flex md:space-x-6">
                    <div class="md:w-1/3 mb-4 md:mb-0">
                        <div id="service-image" class="h-48 bg-gray-200 dark:bg-gray-700 rounded-md flex items-center justify-center">
                            <svg class="h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center">
                                <span class="text-yellow-400">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                </span>
                                <span id="service-rating" class="ml-1 text-gray-700 dark:text-gray-300">4.5</span>
                                <span class="mx-2 text-gray-400">•</span>
                                <span id="service-reviews" class="text-gray-700 dark:text-gray-300">24 reviews</span>
                            </div>
                        </div>
                    </div>
                    <div class="md:w-2/3">
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</h4>
                                <p id="service-description" class="mt-1 text-gray-700 dark:text-gray-300">
                                    Service description will be displayed here.
                                </p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Category</h4>
                                <p id="service-category" class="mt-1 text-gray-700 dark:text-gray-300">
                                    Service Category
                                </p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Features</h4>
                                <ul id="service-features" class="mt-1 space-y-1 list-disc list-inside text-gray-700 dark:text-gray-300">
                                    <!-- Features will be added here via JS -->
                                </ul>
                            </div>
                            <div class="flex items-center justify-between pt-4 border-t dark:border-gray-700">
                                <div>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Price</span>
                                    <div class="flex items-center">
                                        <span id="service-price" class="text-xl font-bold text-blue-600 dark:text-blue-400">$0.00</span>
                                        <span id="service-billing-cycle" class="ml-1 text-sm text-gray-600 dark:text-gray-400">/month</span>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button id="add-to-cart" type="button" class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-100 rounded-md hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">
                                        Add to Cart
                                    </button>
                                    <button id="buy-now" type="button" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Buy Now
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invoice Details Modal -->
<div id="invoice-details-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-3xl w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
        <div class="p-5">
            <div class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Invoice Details</h3>
                <button type="button" class="modal-close text-gray-400 hover:text-gray-500 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="py-4">
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-600 dark:text-gray-300">Invoice Number:</span>
                        <span id="invoice-id" class="font-medium text-gray-800 dark:text-gray-200">#INV-0000</span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-600 dark:text-gray-300">Date Issued:</span>
                        <span id="invoice-date" class="font-medium text-gray-800 dark:text-gray-200">Jan 01, 2023</span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-600 dark:text-gray-300">Due Date:</span>
                        <span id="invoice-due-date" class="font-medium text-gray-800 dark:text-gray-200">Jan 15, 2023</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-300">Status:</span>
                        <span id="invoice-status" class="font-medium px-2 py-1 rounded-full text-xs">Pending</span>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Services</h4>
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Service
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Price
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Qty
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="invoice-items" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Invoice items will be added here via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="flex flex-col md:flex-row md:space-x-4">
                    <div class="md:w-1/2 mb-4 md:mb-0">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Payment Information</h4>
                        <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-md">
                            <p id="payment-method-display" class="text-sm text-gray-700 dark:text-gray-300 mb-2">Payment Method: <span class="font-medium">Credit Card</span></p>
                            <p id="payment-date" class="text-sm text-gray-700 dark:text-gray-300 mb-2">Payment Date: <span class="font-medium">Not paid yet</span></p>
                            <p id="payment-transaction" class="text-sm text-gray-700 dark:text-gray-300">Transaction ID: <span class="font-medium">-</span></p>
                        </div>
                    </div>
                    <div class="md:w-1/2">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Summary</h4>
                        <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-md">
                            <div class="flex justify-between mb-2">
                                <span class="text-sm text-gray-600 dark:text-gray-300">Subtotal:</span>
                                <span id="invoice-subtotal" class="text-sm font-medium text-gray-800 dark:text-gray-200">$0.00</span>
                            </div>
                            <div id="invoice-tax-row" class="flex justify-between mb-2">
                                <span class="text-sm text-gray-600 dark:text-gray-300">Tax (10%):</span>
                                <span id="invoice-tax" class="text-sm font-medium text-gray-800 dark:text-gray-200">$0.00</span>
                            </div>
                            <div id="invoice-discount-row" class="flex justify-between mb-2">
                                <span class="text-sm text-gray-600 dark:text-gray-300">Discount:</span>
                                <span id="invoice-discount" class="text-sm font-medium text-gray-800 dark:text-gray-200">-$0.00</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t dark:border-gray-600">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Total:</span>
                                <span id="invoice-total" class="text-sm font-bold text-blue-600 dark:text-blue-400">$0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-between items-center mt-6 pt-4 border-t dark:border-gray-700">
                    <div class="flex space-x-3">
                        <button id="download-invoice" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 flex items-center">
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download PDF
                        </button>
                        <button id="share-invoice" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 flex items-center">
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                            </svg>
                            Share
                        </button>
                    </div>
                    <div>
                        <button id="pay-invoice" type="button" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Pay Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add to Cart Success Modal -->
<div id="cart-success-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
        <div class="p-5">
            <div class="text-center py-4">
                <svg class="mx-auto h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Added to Cart</h3>
                <p class="mt-2 text-gray-600 dark:text-gray-400">The service has been added to your cart.</p>
            </div>
            <div class="flex justify-center space-x-3 pt-4 border-t dark:border-gray-700">
                <button type="button" class="modal-close px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                    Continue Shopping
                </button>
                <a href="cart.php" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    View Cart
                </a>
            </div>
        </div>
    </div>
</div>

<!-- User Profile Modal -->
<div id="user-profile-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
        <div class="p-5">
            <div class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Profile</h3>
                <button type="button" class="modal-close text-gray-400 hover:text-gray-500 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="py-4">
                <form id="profile-form" class="space-y-4">
                    <div class="flex items-center justify-center">
                        <div class="relative">
                            <div id="profile-image" class="h-24 w-24 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center overflow-hidden">
                                <!-- User avatar will be displayed here -->
                                <svg class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <button type="button" id="change-avatar" class="absolute bottom-0 right-0 bg-blue-600 rounded-full p-1 text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                            <input type="file" id="avatar-upload" name="avatar" accept="image/*" class="hidden">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label for="first-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name</label>
                            <input type="text" id="first-name" name="first_name" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div class="space-y-2">
                            <label for="last-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
                            <input type="text" id="last-name" name="last_name" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="email" id="email" name="email" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                        <input type="tel" id="phone" name="phone" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label>
                        <textarea id="address" name="address" rows="2" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="language" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Preferred Language</label>
                        <select id="language" name="language" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="en">English</option>
                            <option value="es">Spanish</option>
                            <option value="fr">French</option>
                            <option value="de">German</option>
                            <option value="it">Italian</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t dark:border-gray-700">
                        <button type="button" class="modal-close px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div id="change-password-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
        <div class="p-5">
            <div class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Change Password</h3>
                <button type="button" class="modal-close text-gray-400 hover:text-gray-500 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="py-4">
                <form id="password-form" class="space-y-4">
                    <div class="space-y-2">
                        <label for="current-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current Password</label>
                        <input type="password" id="current-password" name="current_password" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="new-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
                        <input type="password" id="new-password" name="new_password" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="confirm-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Password</label>
                        <input type="password" id="confirm-password" name="confirm_password" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    
                    <div class="bg-yellow-50 dark:bg-yellow-900 p-3 rounded-md mt-2">
                        <ul class="text-xs text-yellow-800 dark:text-yellow-200 list-disc list-inside space-y-1">
                            <li>Password must be at least 8 characters long</li>
                            <li>Password must contain at least one uppercase letter</li>
                            <li>Password must contain at least one number</li>
                            <li>Password must contain at least one special character</li>
                        </ul>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t dark:border-gray-700">
                        <button type="button" class="modal-close px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Support Ticket Modal -->
<div id="support-ticket-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
        <div class="p-5">
            <div class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Create Support Ticket</h3>
                <button type="button" class="modal-close text-gray-400 hover:text-gray-500 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="py-4">
                <form id="support-form" class="space-y-4">
                    <div class="space-y-2">
                        <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Subject</label>
                        <input type="text" id="subject" name="subject" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                        <select id="category" name="category" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="billing">Billing Issue</option>
                            <option value="technical">Technical Support</option>
                            <option value="account">Account Question</option>
                            <option value="feature">Feature Request</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Priority</label>
                        <select id="priority" name="priority" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Message</label>
                        <textarea id="message" name="message" rows="4" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Describe your issue in detail..."></textarea>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="attachment" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Attachment (optional)</label>
                        <div class="flex items-center">
                            <button type="button" id="add-attachment" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 flex items-center">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                                Add File
                            </button>
                            <span id="file-name" class="ml-2 text-sm text-gray-600 dark:text-gray-400"></span>
                        </div>
                        <input type="file" id="attachment" name="attachment" class="hidden">
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t dark:border-gray-700">
                        <button type="button" class="modal-close px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Submit Ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Notification Settings Modal -->
<div id="notification-settings-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
        <div class="p-5">
            <div class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Notification Settings</h3>
                <button type="button" class="modal-close text-gray-400 hover:text-gray-500 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="py-4">
                <form id="notification-form" class="space-y-4">
                    <div class="space-y-3">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Email Notifications</h4>
                        
                        <div class="flex items-center justify-between">
                            <label for="email-invoice" class="text-sm text-gray-700 dark:text-gray-300">New Invoices</label>
                            <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                <input type="checkbox" id="email-invoice" name="email_invoice" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked>
                                <label for="email-invoice" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label for="email-payment" class="text-sm text-gray-700 dark:text-gray-300">Payment Confirmations</label>
                            <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                <input type="checkbox" id="email-payment" name="email_payment" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked>
                                <label for="email-payment" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label for="email-reminder" class="text-sm text-gray-700 dark:text-gray-300">Payment Reminders</label>
                            <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                <input type="checkbox" id="email-reminder" name="email_reminder" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked>
                                <label for="email-reminder" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label for="email-support" class="text-sm text-gray-700 dark:text-gray-300">Support Ticket Updates</label>
                            <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                <input type="checkbox" id="email-support" name="email_support" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked>
                                <label for="email-support" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label for="email-marketing" class="text-sm text-gray-700 dark:text-gray-300">Marketing & Promotions</label>
                            <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                <input type="checkbox" id="email-marketing" name="email_marketing" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer">
                                <label for="email-marketing" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-3 pt-4 border-t dark:border-gray-700">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Push Notifications</h4>
                        
                        <div class="flex items-center justify-between">
                            <label for="push-invoice" class="text-sm text-gray-700 dark:text-gray-300">New Invoices</label>
                            <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                <input type="checkbox" id="push-invoice" name="push_invoice" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked>
                                <label for="push-invoice" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label for="push-payment" class="text-sm text-gray-700 dark:text-gray-300">Payment Confirmations</label>
                            <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                <input type="checkbox" id="push-payment" name="push_payment" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked>
                                <label for="push-payment" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label for="push-reminder" class="text-sm text-gray-700 dark:text-gray-300">Payment Reminders</label>
                            <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                <input type="checkbox" id="push-reminder" name="push_reminder" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked>
                                <label for="push-reminder" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label for="push-support" class="text-sm text-gray-700 dark:text-gray-300">Support Ticket Updates</label>
                            <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                <input type="checkbox" id="push-support" name="push_support" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked>
                                <label for="push-support" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-3 pt-4 border-t dark:border-gray-700">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">SMS Notifications</h4>
                        
                        <div class="flex items-center justify-between">
                            <label for="sms-enabled" class="text-sm text-gray-700 dark:text-gray-300">Enable SMS Notifications</label>
                            <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                <input type="checkbox" id="sms-enabled" name="sms_enabled" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer">
                                <label for="sms-enabled" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                            </div>
                        </div>
                        
                        <div id="sms-options" class="pl-4 space-y-3 hidden">
                            <div class="flex items-center justify-between">
                                <label for="sms-reminder" class="text-sm text-gray-700 dark:text-gray-300">Payment Reminders</label>
                                <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                    <input type="checkbox" id="sms-reminder" name="sms_reminder" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked>
                                    <label for="sms-reminder" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <label for="sms-payment" class="text-sm text-gray-700 dark:text-gray-300">Payment Confirmations</label>
                                <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                    <input type="checkbox" id="sms-payment" name="sms_payment" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked>
                                    <label for="sms-payment" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <label for="phone-number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone Number for SMS</label>
                                <input type="tel" id="phone-number" name="phone_number" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t dark:border-gray-700">
                        <button type="button" class="modal-close px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Fund to Wallet Modal -->
<div id="add-fund-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
        <div class="p-5">
            <div class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add Funds to Wallet</h3>
                <button type="button" class="modal-close text-gray-400 hover:text-gray-500 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="py-4">
                <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-md mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-sm text-gray-600 dark:text-gray-300">Current Balance:</span>
                        <span id="current-wallet-balance" class="font-medium text-gray-800 dark:text-gray-200">$0.00</span>
                    </div>
                </div>
                
                <form id="add-fund-form" class="space-y-4">
                    <div class="space-y-2">
                        <label for="fund-amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Amount to Add</label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">$</span>
                            </div>
                            <input type="number" id="fund-amount" name="amount" min="1" step="0.01" class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-7 pr-12 sm:text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0.00">
                            <div class="absolute inset-y-0 right-0 flex items-center">
                                <label for="currency" class="sr-only">Currency</label>
                                <select id="currency" name="currency" class="focus:ring-blue-500 focus:border-blue-500 h-full py-0 pl-2 pr-7 border-transparent bg-transparent text-gray-500 sm:text-sm rounded-r-md dark:text-gray-300">
                                    <option>USD</option>
                                    <option>EUR</option>
                                    <option>GBP</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="fund-payment-method" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Method</label>
                        <select id="fund-payment-method" name="payment_method" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="credit_card">Credit Card</option>
                            <option value="paypal">PayPal</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <!-- Credit Card Section - shown/hidden based on payment method -->
                    <div id="fund-credit-card-section" class="space-y-4">
                        <div class="space-y-2">
                            <label for="fund-card-number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Card Number</label>
                            <input type="text" id="fund-card-number" name="card_number" placeholder="1234 5678 9012 3456" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label for="fund-card-expiry" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Expiry Date</label>
                                <input type="text" id="fund-card-expiry" name="card_expiry" placeholder="MM/YY" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div class="space-y-2">
                                <label for="fund-card-cvc" class="block text-sm font-medium text-gray-700 dark:text-gray-300">CVC</label>
                                <input type="text" id="fund-card-cvc" name="card_cvc" placeholder="123" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </div>
                    </div>
                    
                    <!-- PayPal Section -->
                    <div id="fund-paypal-section" class="hidden space-y-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">You will be redirected to PayPal to complete your payment.</p>
                    </div>
                    
                    <!-- Bank Transfer Section -->
                    <div id="fund-bank-transfer-section" class="hidden space-y-4">
                        <div class="p-3 bg-yellow-50 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded-md text-sm">
                            Please transfer the exact amount to the bank account below. Your wallet will be credited once we confirm the transfer (usually within 24 hours).
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-md text-sm">
                            <p class="mb-1"><span class="font-medium">Bank Name:</span> Example Bank</p>
                            <p class="mb-1"><span class="font-medium">Account Name:</span> Your Company Ltd</p>
                            <p class="mb-1"><span class="font-medium">Account Number:</span> 12345678</p>
                            <p class="mb-1"><span class="font-medium">Sort Code:</span> 01-02-03</p>
                            <p class="mb-1"><span class="font-medium">Reference:</span> <span id="wallet-reference">WALLET-123</span></p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t dark:border-gray-700">
                        <button type="button" class="modal-close px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" id="add-fund-submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Add Funds
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for modal functionality -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Modal management
        const modalBackdrop = document.getElementById('modal-backdrop');
        const modalCloseButtons = document.querySelectorAll('.modal-close');
        
        // Function to open a modal
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            
            // Show backdrop
            modalBackdrop.classList.remove('hidden');
            setTimeout(() => {
                modalBackdrop.classList.remove('opacity-0');
            }, 10);
            
            // Show modal
            modal.classList.remove('hidden');
            const modalContent = modal.querySelector('div');
            setTimeout(() => {
                modalContent.classList.remove('opacity-0', 'scale-95');
                modalContent.classList.add('opacity-100', 'scale-100');
            }, 10);
            
            // Close modal when clicking outside
            modalBackdrop.addEventListener('click', function() {
                closeModal(modalId);
            });
        }
        
        // Function to close a modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            
            // Hide modal
            const modalContent = modal.querySelector('div');
            modalContent.classList.remove('opacity-100', 'scale-100');
            modalContent.classList.add('opacity-0', 'scale-95');
            
            // Hide backdrop
            modalBackdrop.classList.add('opacity-0');
            
            // Wait for animation
            setTimeout(() => {
                modal.classList.add('hidden');
                modalBackdrop.classList.add('hidden');
            }, 300);
        }
        
        // Close button click handler
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('[id$="-modal"]');
                if (modal) {
                    closeModal(modal.id);
                }
            });
        });
        
        // Make openModal and closeModal available globally
        window.openModal = openModal;
        window.closeModal = closeModal;
        
        // Payment method change handler in payment modal
        const paymentMethodSelect = document.getElementById('payment-method');
        if (paymentMethodSelect) {
            paymentMethodSelect.addEventListener('change', function() {
                // Hide all payment sections
                document.getElementById('credit-card-section').classList.add('hidden');
                document.getElementById('paypal-section').classList.add('hidden');
                document.getElementById('bank-transfer-section').classList.add('hidden');
                document.getElementById('wallet-section').classList.add('hidden');
                
                // Show selected payment section
                const selectedMethod = this.value;
                if (selectedMethod === 'credit_card') {
                    document.getElementById('credit-card-section').classList.remove('hidden');
                } else if (selectedMethod === 'paypal') {
                    document.getElementById('paypal-section').classList.remove('hidden');
                } else if (selectedMethod === 'bank_transfer') {
                    document.getElementById('bank-transfer-section').classList.remove('hidden');
                } else if (selectedMethod === 'wallet') {
                    document.getElementById('wallet-section').classList.remove('hidden');
                    
                    // Check if wallet balance is sufficient
                    const walletBalance = parseFloat(document.getElementById('wallet-balance').textContent.replace(', ''));
                    const amountDue = parseFloat(document.getElementById('wallet-amount-due').textContent.replace(', ''));
                    
                    if (walletBalance < amountDue) {
                        document.getElementById('insufficient-funds').classList.remove('hidden');
                        document.getElementById('submit-payment').disabled = true;
                    } else {
                        document.getElementById('insufficient-funds').classList.add('hidden');
                        document.getElementById('submit-payment').disabled = false;
                    }
                }
            });
        }
        
        // Add Fund payment method change handler
        const fundPaymentMethodSelect = document.getElementById('fund-payment-method');
        if (fundPaymentMethodSelect) {
            fundPaymentMethodSelect.addEventListener('change', function() {
                // Hide all payment sections
                document.getElementById('fund-credit-card-section').classList.add('hidden');
                document.getElementById('fund-paypal-section').classList.add('hidden');
                document.getElementById('fund-bank-transfer-section').classList.add('hidden');
                
                // Show selected payment section
                const selectedMethod = this.value;
                if (selectedMethod === 'credit_card') {
                    document.getElementById('fund-credit-card-section').classList.remove('hidden');
                } else if (selectedMethod === 'paypal') {
                    document.getElementById('fund-paypal-section').classList.remove('hidden');
                } else if (selectedMethod === 'bank_transfer') {
                    document.getElementById('fund-bank-transfer-section').classList.remove('hidden');
                }
            });
        }
        
        // SMS notifications toggle
        const smsEnabledToggle = document.getElementById('sms-enabled');
        if (smsEnabledToggle) {
            smsEnabledToggle.addEventListener('change', function() {
                const smsOptions = document.getElementById('sms-options');
                if (this.checked) {
                    smsOptions.classList.remove('hidden');
                } else {
                    smsOptions.classList.add('hidden');
                }
            });
        }
        
        // Avatar upload handler
        const changeAvatarBtn = document.getElementById('change-avatar');
        const avatarUploadInput = document.getElementById('avatar-upload');
        if (changeAvatarBtn && avatarUploadInput) {
            changeAvatarBtn.addEventListener('click', function() {
                avatarUploadInput.click();
            });
            
            avatarUploadInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const profileImage = document.getElementById('profile-image');
                        profileImage.innerHTML = `<img src="${e.target.result}" alt="Profile" class="h-full w-full object-cover">`;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Support ticket attachment handler
        const addAttachmentBtn = document.getElementById('add-attachment');
        const attachmentInput = document.getElementById('attachment');
        const fileNameDisplay = document.getElementById('file-name');
        if (addAttachmentBtn && attachmentInput && fileNameDisplay) {
            addAttachmentBtn.addEventListener('click', function() {
                attachmentInput.click();
            });
            
            attachmentInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    fileNameDisplay.textContent = file.name;
                } else {
                    fileNameDisplay.textContent = '';
                }
            });
        }
        
        // Payment form submission handler
        const paymentForm = document.getElementById('payment-form');
        if (paymentForm) {
            paymentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Show processing state
                document.getElementById('payment-details').classList.add('hidden');
                document.getElementById('payment-processing').classList.remove('hidden');
                
                // Simulate payment processing
                setTimeout(function() {
                    document.getElementById('payment-processing').classList.add('hidden');
                    document.getElementById('payment-result').classList.remove('hidden');
                    
                    // Show success (in a real app, this would depend on the payment result)
                    const success = Math.random() > 0.2; // 80% success rate for demo
                    if (success) {
                        document.getElementById('payment-success').classList.remove('hidden');
                    } else {
                        document.getElementById('payment-error').classList.remove('hidden');
                        document.getElementById('payment-error-message').textContent = 'Your card was declined. Please try another payment method.';
                    }
                }, 2000);
            });
            
            // Done button after payment result
            const paymentDoneBtn = document.getElementById('payment-done');
            if (paymentDoneBtn) {
                paymentDoneBtn.addEventListener('click', function() {
                    closeModal('payment-modal');
                    
                    // Reset payment modal for next use
                    setTimeout(function() {
                        document.getElementById('payment-result').classList.add('hidden');
                        document.getElementById('payment-success').classList.add('hidden');
                        document.getElementById('payment-error').classList.add('hidden');
                        document.getElementById('payment-details').classList.remove('hidden');
                    }, 300);
                });
            }
        }
        
        // Add to Cart button handler in service details modal
        const addToCartBtn = document.getElementById('add-to-cart');
        if (addToCartBtn) {
            addToCartBtn.addEventListener('click', function() {
                closeModal('service-details-modal');
                setTimeout(() => {
                    openModal('cart-success-modal');
                }, 300);
            });
        }
        
        // Buy Now button handler in service details modal
        const buyNowBtn = document.getElementById('buy-now');
        if (buyNowBtn) {
            buyNowBtn.addEventListener('click', function() {
                closeModal('service-details-modal');
                setTimeout(() => {
                    // Set up payment details and open payment modal
                    document.getElementById('payment-service-name').textContent = document.getElementById('service-name').textContent;
                    document.getElementById('payment-amount').textContent = document.getElementById('service-price').textContent;
                    
                    // Generate a random invoice ID for demo
                    const invoiceId = 'INV-' + Math.floor(Math.random() * 10000).toString().padStart(4, '0');
                    document.getElementById('payment-invoice-id').textContent = '#' + invoiceId;
                    document.getElementById('payment-invoice-id-input').value = invoiceId;
                    document.getElementById('bank-reference').textContent = invoiceId;
                    
                    // Set due date (current date + 14 days)
                    const dueDate = new Date();
                    dueDate.setDate(dueDate.getDate() + 14);
                    document.getElementById('payment-due-date').textContent = dueDate.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                    
                    openModal('payment-modal');
                }, 300);
            });
        }
        
        // Pay Invoice button handler in invoice details modal
        const payInvoiceBtn = document.getElementById('pay-invoice');
        if (payInvoiceBtn) {
            payInvoiceBtn.addEventListener('click', function() {
                closeModal('invoice-details-modal');
                setTimeout(() => {
                    // Set up payment details from invoice modal
                    document.getElementById('payment-invoice-id').textContent = document.getElementById('invoice-id').textContent;
                    document.getElementById('payment-invoice-id-input').value = document.getElementById('invoice-id').textContent.replace('#', '');
                    document.getElementById('bank-reference').textContent = document.getElementById('invoice-id').textContent.replace('#', '');
                    document.getElementById('payment-amount').textContent = document.getElementById('invoice-total').textContent;
                    document.getElementById('payment-due-date').textContent = document.getElementById('invoice-due-date').textContent;
                    
                    openModal('payment-modal');
                }, 300);
            });
        }
    });
</script>
<?php
// End of file
?>