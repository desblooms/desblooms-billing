<?php
/**
 * Invoice Functions
 * 
 * This file contains all the functions related to invoice generation,
 * management, updating, and downloading for the Digital Service Billing App.
 * 
 * @package Digital-Billing-App
 */

// Include required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/tcpdf/tcpdf.php';

/**
 * Generate a new invoice for a user's order
 * 
 * @param int $userId The ID of the user
 * @param array $items Array of service items to include in the invoice
 * @param array $extra Additional information like discounts, taxes, etc.
 * @return int|bool Returns the invoice ID on success, false on failure
 */
function generateInvoice($userId, $items, $extra = []) {
    global $conn;
    
    // Get user details
    $userQuery = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($userQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userResult = $stmt->get_result();
    
    if ($userResult->num_rows === 0) {
        return false; // User not found
    }
    
    $user = $userResult->fetch_assoc();
    
    // Calculate invoice totals
    $subTotal = 0;
    foreach ($items as $item) {
        $subTotal += $item['price'] * $item['quantity'];
    }
    
    // Apply discount if available
    $discount = isset($extra['discount']) ? $extra['discount'] : 0;
    $discountAmount = ($subTotal * $discount) / 100;
    
    // Calculate tax
    $taxRate = isset($extra['tax_rate']) ? $extra['tax_rate'] : getDefaultTaxRate();
    $taxAmount = (($subTotal - $discountAmount) * $taxRate) / 100;
    
    // Calculate total
    $total = $subTotal - $discountAmount + $taxAmount;
    
    // Generate invoice number
    $invoiceNumber = 'INV-' . date('Ymd') . '-' . generateRandomString(6);
    
    // Set due date (default: 14 days from now)
    $dueDate = isset($extra['due_date']) ? $extra['due_date'] : date('Y-m-d', strtotime('+14 days'));
    
    // Insert invoice into database
    $invoiceQuery = "INSERT INTO invoices (invoice_number, user_id, subtotal, discount, tax, total, status, issue_date, due_date, notes)
                     VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), ?, ?)";
    
    $stmt = $conn->prepare($invoiceQuery);
    $status = 'pending';
    $notes = isset($extra['notes']) ? $extra['notes'] : '';
    
    $stmt->bind_param("sidddss", $invoiceNumber, $userId, $subTotal, $discountAmount, $taxAmount, $total, $dueDate, $notes);
    
    if (!$stmt->execute()) {
        return false; // Failed to create invoice
    }
    
    $invoiceId = $conn->insert_id;
    
    // Insert invoice items
    foreach ($items as $item) {
        $itemQuery = "INSERT INTO invoice_items (invoice_id, service_id, description, quantity, price, subtotal)
                      VALUES (?, ?, ?, ?, ?, ?)";
        
        $itemSubtotal = $item['price'] * $item['quantity'];
        $stmt = $conn->prepare($itemQuery);
        $stmt->bind_param("iisidi", $invoiceId, $item['service_id'], $item['description'], $item['quantity'], $item['price'], $itemSubtotal);
        
        if (!$stmt->execute()) {
            // If item insertion fails, consider rollback
            // For simplicity, we're not implementing transaction rollback here
        }
    }
    
    // Create invoice PDF
    createInvoicePDF($invoiceId);
    
    // Send invoice notification
    sendInvoiceNotification($invoiceId);
    
    return $invoiceId;
}

/**
 * Get invoice details by ID
 * 
 * @param int $invoiceId The invoice ID
 * @return array|bool Returns invoice details as array or false if not found
 */
function getInvoice($invoiceId) {
    global $conn;
    
    $query = "SELECT i.*, u.name, u.email, u.address, u.phone
              FROM invoices i
              JOIN users u ON i.user_id = u.id
              WHERE i.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $invoice = $result->fetch_assoc();
    
    // Get invoice items
    $itemsQuery = "SELECT i.*, s.name as service_name
                   FROM invoice_items i
                   LEFT JOIN services s ON i.service_id = s.id
                   WHERE i.invoice_id = ?";
    
    $stmt = $conn->prepare($itemsQuery);
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $itemsResult = $stmt->get_result();
    
    $invoice['items'] = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $invoice['items'][] = $item;
    }
    
    return $invoice;
}

/**
 * Get all invoices for a user
 * 
 * @param int $userId The user ID
 * @param string $status Optional filter by status (pending, outstanding, paid)
 * @return array Returns array of invoices
 */
function getUserInvoices($userId, $status = null) {
    global $conn;
    
    $query = "SELECT * FROM invoices WHERE user_id = ?";
    
    if ($status) {
        $query .= " AND status = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $userId, $status);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $invoices = [];
    while ($invoice = $result->fetch_assoc()) {
        $invoices[] = $invoice;
    }
    
    return $invoices;
}

/**
 * Get pending invoices for a user
 * 
 * @param int $userId The user ID
 * @return array Returns array of pending invoices
 */
function getPendingInvoices($userId) {
    return getUserInvoices($userId, 'pending');
}

/**
 * Get outstanding invoices for a user
 * 
 * @param int $userId The user ID
 * @return array Returns array of outstanding invoices
 */
function getOutstandingInvoices($userId) {
    return getUserInvoices($userId, 'outstanding');
}

/**
 * Get paid invoices for a user
 * 
 * @param int $userId The user ID
 * @return array Returns array of paid invoices
 */
function getPaidInvoices($userId) {
    return getUserInvoices($userId, 'paid');
}

/**
 * Update invoice status
 * 
 * @param int $invoiceId The invoice ID
 * @param string $status New status (pending, outstanding, paid)
 * @return bool Returns true on success, false on failure
 */
function updateInvoiceStatus($invoiceId, $status) {
    global $conn;
    
    $validStatuses = ['pending', 'outstanding', 'paid'];
    
    if (!in_array($status, $validStatuses)) {
        return false;
    }
    
    $query = "UPDATE invoices SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $invoiceId);
    
    if ($stmt->execute()) {
        // If status is changed to paid, record payment date
        if ($status === 'paid') {
            $paymentQuery = "UPDATE invoices SET payment_date = NOW() WHERE id = ?";
            $paymentStmt = $conn->prepare($paymentQuery);
            $paymentStmt->bind_param("i", $invoiceId);
            $paymentStmt->execute();
        }
        
        // Send notification about status change
        sendInvoiceStatusNotification($invoiceId, $status);
        
        return true;
    }
    
    return false;
}

/**
 * Process an invoice payment
 * 
 * @param int $invoiceId The invoice ID
 * @param float $amount Amount paid
 * @param string $paymentMethod Payment method used
 * @param array $paymentDetails Additional payment details
 * @return bool Returns true on success, false on failure
 */
function processInvoicePayment($invoiceId, $amount, $paymentMethod, $paymentDetails = []) {
    global $conn;
    
    // Get invoice details
    $invoice = getInvoice($invoiceId);
    
    if (!$invoice) {
        return false;
    }
    
    // Check if payment amount matches invoice total
    // For partial payments, this logic would need to be modified
    if (abs($amount - $invoice['total']) > 0.01) { // Small epsilon for floating point comparison
        // Partial payment logic could be implemented here
        // For now, we're requiring exact payment
        return false;
    }
    
    // Record payment in database
    $query = "INSERT INTO payments (invoice_id, amount, payment_method, transaction_id, payment_date, status)
              VALUES (?, ?, ?, ?, NOW(), 'completed')";
    
    $transactionId = isset($paymentDetails['transaction_id']) ? $paymentDetails['transaction_id'] : generateRandomString(12);
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("idss", $invoiceId, $amount, $paymentMethod, $transactionId);
    
    if ($stmt->execute()) {
        // Update invoice status to paid
        updateInvoiceStatus($invoiceId, 'paid');
        
        // Send payment confirmation
        sendPaymentConfirmation($invoiceId);
        
        return true;
    }
    
    return false;
}

/**
 * Create a PDF invoice using TCPDF
 *
 * @param int $invoiceId The invoice ID
 * @return string|bool Returns the path to the created PDF or false on failure
 */
function createInvoicePDF($invoiceId) {
    $invoice = getInvoice($invoiceId);
    
    if (!$invoice) {
        return false;
    }
    
    // Get company settings
    $company = getCompanySettings();
    
    // Initialize TCPDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Digital Billing App');
    $pdf->SetAuthor($company['name']);
    $pdf->SetTitle('Invoice #' . $invoice['invoice_number']);
    $pdf->SetSubject('Invoice');
    
    // Remove header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(true, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Company logo and info
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(180, 10, $company['name'], 0, 1, 'R');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(180, 6, $company['address'], 0, 1, 'R');
    $pdf->Cell(180, 6, $company['city'] . ', ' . $company['state'] . ' ' . $company['zip'], 0, 1, 'R');
    $pdf->Cell(180, 6, 'Phone: ' . $company['phone'], 0, 1, 'R');
    $pdf->Cell(180, 6, 'Email: ' . $company['email'], 0, 1, 'R');
    
    // Invoice title
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(180, 10, 'INVOICE', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Invoice details
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(90, 6, 'Invoice To:', 0, 0);
    $pdf->Cell(90, 6, 'Invoice Details:', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(90, 6, $invoice['name'], 0, 0);
    $pdf->Cell(40, 6, 'Invoice Number:', 0, 0);
    $pdf->Cell(50, 6, $invoice['invoice_number'], 0, 1);
    
    $pdf->Cell(90, 6, $invoice['address'], 0, 0);
    $pdf->Cell(40, 6, 'Invoice Date:', 0, 0);
    $pdf->Cell(50, 6, date('d/m/Y', strtotime($invoice['issue_date'])), 0, 1);
    
    $pdf->Cell(90, 6, 'Phone: ' . $invoice['phone'], 0, 0);
    $pdf->Cell(40, 6, 'Due Date:', 0, 0);
    $pdf->Cell(50, 6, date('d/m/Y', strtotime($invoice['due_date'])), 0, 1);
    
    $pdf->Cell(90, 6, 'Email: ' . $invoice['email'], 0, 0);
    $pdf->Cell(40, 6, 'Status:', 0, 0);
    $pdf->Cell(50, 6, ucfirst($invoice['status']), 0, 1);
    
    $pdf->Ln(10);
    
    // Items table header
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(10, 8, '#', 1, 0, 'C', true);
    $pdf->Cell(80, 8, 'Service', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Quantity', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Price', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Total', 1, 1, 'C', true);
    
    // Items
    $pdf->SetFont('helvetica', '', 10);
    $i = 1;
    foreach ($invoice['items'] as $item) {
        $pdf->Cell(10, 7, $i, 1, 0, 'C');
        $pdf->Cell(80, 7, $item['service_name'] ? $item['service_name'] : $item['description'], 1, 0);
        $pdf->Cell(30, 7, $item['quantity'], 1, 0, 'C');
        $pdf->Cell(30, 7, formatCurrency($item['price']), 1, 0, 'R');
        $pdf->Cell(30, 7, formatCurrency($item['subtotal']), 1, 1, 'R');
        $i++;
    }
    
    // Totals
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(120, 7, 'Subtotal', 1, 0, 'R');
    $pdf->Cell(60, 7, formatCurrency($invoice['subtotal']), 1, 1, 'R');
    
    if ($invoice['discount'] > 0) {
        $pdf->Cell(120, 7, 'Discount', 1, 0, 'R');
        $pdf->Cell(60, 7, formatCurrency($invoice['discount']), 1, 1, 'R');
    }
    
    $pdf->Cell(120, 7, 'Tax', 1, 0, 'R');
    $pdf->Cell(60, 7, formatCurrency($invoice['tax']), 1, 1, 'R');
    
    $pdf->Cell(120, 7, 'Total', 1, 0, 'R', true);
    $pdf->Cell(60, 7, formatCurrency($invoice['total']), 1, 1, 'R', true);
    
    // Payment information
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(180, 7, 'Payment Information', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(180, 6, $company['payment_info'], 0, 'L');
    
    // Terms and notes
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(180, 7, 'Terms & Conditions', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(180, 6, $company['terms'], 0, 'L');
    
    // Add notes if available
    if (!empty($invoice['notes'])) {
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(180, 7, 'Notes', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(180, 6, $invoice['notes'], 0, 'L');
    }
    
    // File path for saving
    $invoiceDir = __DIR__ . '/../invoices/';
    if (!file_exists($invoiceDir)) {
        mkdir($invoiceDir, 0755, true);
    }
    
    $filename = 'invoice_' . $invoice['invoice_number'] . '.pdf';
    $filepath = $invoiceDir . $filename;
    
    // Output PDF to file
    $pdf->Output($filepath, 'F');
    
    // Update invoice with PDF path
    global $conn;
    $query = "UPDATE invoices SET pdf_path = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $filename, $invoiceId);
    $stmt->execute();
    
    return $filepath;
}

/**
 * Get the path to an invoice PDF
 * 
 * @param int $invoiceId The invoice ID
 * @return string|bool Returns the path to the PDF or false if not found
 */
function getInvoicePDFPath($invoiceId) {
    global $conn;
    
    $query = "SELECT pdf_path FROM invoices WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $invoice = $result->fetch_assoc();
    
    if (empty($invoice['pdf_path'])) {
        // PDF doesn't exist, create it
        return createInvoicePDF($invoiceId);
    }
    
    return __DIR__ . '/../invoices/' . $invoice['pdf_path'];
}

/**
 * Send invoice notification to user
 * 
 * @param int $invoiceId The invoice ID
 * @return bool Returns true on success, false on failure
 */
function sendInvoiceNotification($invoiceId) {
    $invoice = getInvoice($invoiceId);
    
    if (!$invoice) {
        return false;
    }
    
    $to = $invoice['email'];
    $subject = 'New Invoice #' . $invoice['invoice_number'];
    
    // Get email template
    $template = file_get_contents(__DIR__ . '/email-templates/invoice-notification.html');
    
    // Replace placeholders
    $template = str_replace('{NAME}', $invoice['name'], $template);
    $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
    $template = str_replace('{INVOICE_DATE}', date('d/m/Y', strtotime($invoice['issue_date'])), $template);
    $template = str_replace('{DUE_DATE}', date('d/m/Y', strtotime($invoice['due_date'])), $template);
    $template = str_replace('{AMOUNT}', formatCurrency($invoice['total']), $template);
    $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $invoiceId, $template);
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
    
    // Send email
    return mail($to, $subject, $template, $headers);
}

/**
 * Send invoice status notification to user
 * 
 * @param int $invoiceId The invoice ID
 * @param string $status The new status
 * @return bool Returns true on success, false on failure
 */
function sendInvoiceStatusNotification($invoiceId, $status) {
    $invoice = getInvoice($invoiceId);
    
    if (!$invoice) {
        return false;
    }
    
    $to = $invoice['email'];
    $subject = 'Invoice #' . $invoice['invoice_number'] . ' Status Update';
    
    // Get email template
    $template = file_get_contents(__DIR__ . '/email-templates/invoice-status-update.html');
    
    // Replace placeholders
    $template = str_replace('{NAME}', $invoice['name'], $template);
    $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
    $template = str_replace('{STATUS}', ucfirst($status), $template);
    $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $invoiceId, $template);
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
    
    // Send email
    return mail($to, $subject, $template, $headers);
}

/**
 * Send payment confirmation to user
 * 
 * @param int $invoiceId The invoice ID
 * @return bool Returns true on success, false on failure
 */
function sendPaymentConfirmation($invoiceId) {
    $invoice = getInvoice($invoiceId);
    
    if (!$invoice) {
        return false;
    }
    
    $to = $invoice['email'];
    $subject = 'Payment Confirmation for Invoice #' . $invoice['invoice_number'];
    
    // Get email template
    $template = file_get_contents(__DIR__ . '/email-templates/payment-confirmation.html');
    
    // Replace placeholders
    $template = str_replace('{NAME}', $invoice['name'], $template);
    $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
    $template = str_replace('{AMOUNT}', formatCurrency($invoice['total']), $template);
    $template = str_replace('{PAYMENT_DATE}', date('d/m/Y', strtotime($invoice['payment_date'])), $template);
    $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $invoiceId, $template);
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
    
    // Attach PDF
    $pdfPath = getInvoicePDFPath($invoiceId);
    
    // For advanced email with attachment, you might need additional libraries
    // Basic email without attachment for simplicity
    return mail($to, $subject, $template, $headers);
}

/**
 * Check for overdue invoices and update their status
 * This function should be called via a cron job
 * 
 * @return int Number of invoices updated
 */
function checkOverdueInvoices() {
    global $conn;
    
    $query = "UPDATE invoices 
              SET status = 'outstanding' 
              WHERE status = 'pending' 
              AND due_date < CURDATE()";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $updatedCount = $conn->affected_rows;
    
    // Send notifications for newly outstanding invoices
    if ($updatedCount > 0) {
        $getNewlyOutstandingQuery = "SELECT id FROM invoices 
                                     WHERE status = 'outstanding' 
                                     AND due_date < CURDATE() 
                                     AND (last_reminder IS NULL OR last_reminder < DATE_SUB(NOW(), INTERVAL 1 DAY))";
        
        $result = $conn->query($getNewlyOutstandingQuery);
        
        while ($row = $result->fetch_assoc()) {
            sendInvoiceStatusNotification($row['id'], 'outstanding');
            
            // Update last reminder date
            $updateQuery = "UPDATE invoices SET last_reminder = NOW() WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("i", $row['id']);
            $updateStmt->execute();
        }
    }
    
    return $updatedCount;
}

/**
 * Send payment reminders for pending invoices
 * This function should be called via a cron job
 * 
 * @param int $daysBeforeDue Send reminders for invoices due in this many days
 * @return int Number of reminders sent
 */
function sendPaymentReminders($daysBeforeDue = 3) {
    global $conn;
    
    $dueDate = date('Y-m-d', strtotime("+{$daysBeforeDue} days"));
    
    $query = "SELECT id FROM invoices 
              WHERE status = 'pending' 
              AND due_date = ? 
              AND (last_reminder IS NULL OR last_reminder < DATE_SUB(NOW(), INTERVAL 1 DAY))";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $dueDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reminderCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        $invoice = getInvoice($row['id']);
        
        $to = $invoice['email'];
        $subject = 'Payment Reminder for Invoice #' . $invoice['invoice_number'];
        
        // Get email template
        $template = file_get_contents(__DIR__ . '/email-templates/payment-reminder.html');
        
        // Replace placeholders
        $template = str_replace('{NAME}', $invoice['name'], $template);
        $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
        $template = str_replace('{DUE_DATE}', date('d/m/Y', strtotime($invoice['due_date'])), $template);
        $template = str_replace('{AMOUNT}', formatCurrency($invoice['total']), $template);
        $template = str_replace('{DAYS_LEFT}', $daysBeforeDue, $template);
        $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $row['id'], $template);
        
        // Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
        
        // Send email
        if (mail($to, $subject, $template, $headers)) {
            $reminderCount++;
            
            // Update last reminder date
            $updateQuery = "UPDATE invoices SET last_reminder = NOW() WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("i", $row['id']);
            $updateStmt->execute();
        }
    }
    
    return $reminderCount;
}

/**
 * Get company settings for invoice
 * 
 * @return array Company settings
 */
function getCompanySettings() {
    global $conn;
    
    $query = "SELECT * FROM settings WHERE setting_group = 'company'";
    $result = $conn->query($query);
    
    $settings = [];
    
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Default values if not found in database
    $defaults = [
        'name' => 'Digital Billing App',
        'address' => '123 Main Street',
        'city' => 'Anytown',
        'state' => 'State',
        'zip' => '12345',
        'phone' => '(123) 456-7890',
        'email' => 'billing@example.com',
        'website' => 'https://example.com',
        'payment_info' => 'Please make payment to our account:\nBank: Example Bank\nAccount: 1234567890\nReference: Your invoice number',
        'terms' => 'Payment due within 14 days. Late payment may result in service interruption.'
    ];
    
    foreach ($defaults as $key => $value) {
        if (!isset($settings[$key])) {
            $settings[$key] = $value;
        }
    }
    
    return $settings;
}

/**
 * Get default tax rate from settings
 * 
 * @return float Default tax rate
 */
function getDefaultTaxRate() {
    global $conn;
    
    $query = "SELECT setting_value FROM settings WHERE setting_group = 'billing' AND setting_key = 'default_tax_rate'";

    $result = $conn->query($query);
   
   if ($result && $result->num_rows > 0) {
       $row = $result->fetch_assoc();
       return floatval($row['setting_value']);
   }
   
   // Default tax rate if not found in settings
   return 10.0; // 10% as default
}

/**
* Format currency amount
* 
* @param float $amount Amount to format
* @param string $currency Currency code (default: USD)
* @return string Formatted amount
*/
function formatCurrency($amount, $currency = 'USD') {
   // Get currency symbol from settings if available
   $currencySymbol = getCurrencySymbol($currency);
   
   return $currencySymbol . number_format($amount, 2);
}

/**
* Get currency symbol
* 
* @param string $currency Currency code
* @return string Currency symbol
*/
function getCurrencySymbol($currency = 'USD') {
   $symbols = [
       'USD' => '$',
       'EUR' => '€',
       'GBP' => '£',
       'INR' => '₹',
       'JPY' => '¥',
       'CAD' => 'C$',
       'AUD' => 'A$'
   ];
   
   return isset($symbols[$currency]) ? $symbols[$currency] : '$';
}

/**
* Generate a random string (used for invoice numbers, etc.)
* 
* @param int $length Length of string to generate
* @return string Random string
*/
function generateRandomString($length = 6) {
   $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
   $string = '';
   
   for ($i = 0; $i < $length; $i++) {
       $string .= $characters[rand(0, strlen($characters) - 1)];
   }
   
   return $string;
}

/**
* Get application URL
* 
* @return string Application URL
*/
function getAppUrl() {
   $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
   $host = $_SERVER['HTTP_HOST'];
   $path = dirname($_SERVER['PHP_SELF']);
   
   return $protocol . "://" . $host . $path;
}

/**
* Apply late payment penalty to outstanding invoices
* This function should be called via a cron job
* 
* @param int $daysOverdue Apply penalty to invoices overdue by this many days
* @param float $penaltyRate Penalty rate as percentage
* @return int Number of invoices penalized
*/
function applyLatePenalty($daysOverdue = 7, $penaltyRate = 5.0) {
   global $conn;
   
   // Get settings
   $query = "SELECT setting_value FROM settings WHERE setting_group = 'billing' AND setting_key = 'late_payment_penalty'";
   $result = $conn->query($query);
   
   if ($result && $result->num_rows > 0) {
       $row = $result->fetch_assoc();
       $penaltyRate = floatval($row['setting_value']);
   }
   
   // Get qualifying invoices
   $overdueDate = date('Y-m-d', strtotime("-{$daysOverdue} days"));
   
   $query = "SELECT id, total FROM invoices 
             WHERE status = 'outstanding' 
             AND due_date <= ? 
             AND penalty_applied = 0";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("s", $overdueDate);
   $stmt->execute();
   $result = $stmt->get_result();
   
   $penalizedCount = 0;
   
   while ($invoice = $result->fetch_assoc()) {
       $penaltyAmount = ($invoice['total'] * $penaltyRate) / 100;
       
      // Add penalty to invoice
$currentDate = date('Y-m-d');
$penaltyNote = '\nLate payment penalty of ' . $penaltyRate . '% applied on ' . $currentDate . '.';

$updateQuery = "UPDATE invoices 
                SET penalty_amount = ?, 
                    total = total + ?, 
                    penalty_applied = 1, 
                    notes = CONCAT(IFNULL(notes, ''), ?)
                WHERE id = ?";

$updateStmt = $conn->prepare($updateQuery);
$updateStmt->bind_param("ddsi", $penaltyAmount, $penaltyAmount, $penaltyNote, $invoice['id']);
       
       if ($updateStmt->execute()) {
           $penalizedCount++;
           
           // Regenerate PDF with new total
           createInvoicePDF($invoice['id']);
           
           // Send notification about penalty
           $penaltyNotification = getInvoice($invoice['id']);
           
           $to = $penaltyNotification['email'];
           $subject = 'Late Payment Penalty Applied - Invoice #' . $penaltyNotification['invoice_number'];
           
           // Get email template
           $template = file_get_contents(__DIR__ . '/email-templates/penalty-notification.html');
           
           // Replace placeholders
           $template = str_replace('{NAME}', $penaltyNotification['name'], $template);
           $template = str_replace('{INVOICE_NUMBER}', $penaltyNotification['invoice_number'], $template);
           $template = str_replace('{PENALTY_AMOUNT}', formatCurrency($penaltyAmount), $template);
           $template = str_replace('{NEW_TOTAL}', formatCurrency($penaltyNotification['total']), $template);
           $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $invoice['id'], $template);
           
           // Email headers
           $headers = "MIME-Version: 1.0" . "\r\n";
           $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
           $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
           
           // Send email
           mail($to, $subject, $template, $headers);
       }
   }
   
   return $penalizedCount;
}

/**
* Check for services that need to be suspended due to overdue payments
* This function should be called via a cron job
* 
* @param int $daysOverdue Suspend services for invoices overdue by this many days
* @return int Number of services suspended
*/
function suspendOverdueServices($daysOverdue = 30) {
   global $conn;
   
   // Get settings
   $query = "SELECT setting_value FROM settings WHERE setting_group = 'billing' AND setting_key = 'suspension_days'";
   $result = $conn->query($query);
   
   if ($result && $result->num_rows > 0) {
       $row = $result->fetch_assoc();
       $daysOverdue = intval($row['setting_value']);
   }
   
   // Get qualifying invoices
   $overdueDate = date('Y-m-d', strtotime("-{$daysOverdue} days"));
   
   $query = "SELECT i.id, i.invoice_number, i.user_id, u.email, u.name 
             FROM invoices i
             JOIN users u ON i.user_id = u.id
             WHERE i.status = 'outstanding' 
             AND i.due_date <= ? 
             AND i.service_suspended = 0";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("s", $overdueDate);
   $stmt->execute();
   $result = $stmt->get_result();
   
   $suspendedCount = 0;
   
   while ($invoice = $result->fetch_assoc()) {
       // Get services from this invoice
       $servicesQuery = "SELECT i.service_id, s.name 
                         FROM invoice_items i
                         JOIN services s ON i.service_id = s.id
                         WHERE i.invoice_id = ?";
       
       $servicesStmt = $conn->prepare($servicesQuery);
       $servicesStmt->bind_param("i", $invoice['id']);
       $servicesStmt->execute();
       $servicesResult = $servicesStmt->get_result();
       
       $suspendedServices = [];
       
       while ($service = $servicesResult->fetch_assoc()) {
           // Suspend service
           $suspendQuery = "UPDATE user_services 
                           SET status = 'suspended', 
                               suspended_date = NOW()
                           WHERE user_id = ? 
                           AND service_id = ? 
                           AND status = 'active'";
           
           $suspendStmt = $conn->prepare($suspendQuery);
           $suspendStmt->bind_param("ii", $invoice['user_id'], $service['service_id']);
           
           if ($suspendStmt->execute() && $conn->affected_rows > 0) {
               $suspendedServices[] = $service['name'];
           }
       }
       
       if (!empty($suspendedServices)) {
           // Update invoice
           $updateQuery = "UPDATE invoices SET service_suspended = 1 WHERE id = ?";
           $updateStmt = $conn->prepare($updateQuery);
           $updateStmt->bind_param("i", $invoice['id']);
           $updateStmt->execute();
           
           $suspendedCount++;
           
           // Send notification about suspension
           $to = $invoice['email'];
           $subject = 'Service Suspension Notice - Invoice #' . $invoice['invoice_number'];
           
           // Get email template
           $template = file_get_contents(__DIR__ . '/email-templates/suspension-notification.html');
           
           // Replace placeholders
           $template = str_replace('{NAME}', $invoice['name'], $template);
           $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
           $template = str_replace('{SERVICES}', implode(', ', $suspendedServices), $template);
           $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $invoice['id'], $template);
           
           // Email headers
           $headers = "MIME-Version: 1.0" . "\r\n";
           $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
           $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
           
           // Send email
           mail($to, $subject, $template, $headers);
       }
   }
   
   return $suspendedCount;
}

/**
* Re-activate suspended services after payment
* 
* @param int $userId The user ID
* @param int $invoiceId The invoice ID
* @return int Number of services reactivated
*/
function reactivateServices($userId, $invoiceId) {
   global $conn;
   
   // Get services from this invoice
   $servicesQuery = "SELECT i.service_id 
                     FROM invoice_items i
                     WHERE i.invoice_id = ?";
   
   $servicesStmt = $conn->prepare($servicesQuery);
   $servicesStmt->bind_param("i", $invoiceId);
   $servicesStmt->execute();
   $servicesResult = $servicesStmt->get_result();
   
   $reactivatedCount = 0;
   
   while ($service = $servicesResult->fetch_assoc()) {
       // Reactivate service
       $reactivateQuery = "UPDATE user_services 
                          SET status = 'active', 
                              suspended_date = NULL,
                              reactivated_date = NOW()
                          WHERE user_id = ? 
                          AND service_id = ? 
                          AND status = 'suspended'";
       
       $reactivateStmt = $conn->prepare($reactivateQuery);
       $reactivateStmt->bind_param("ii", $userId, $service['service_id']);
       
       if ($reactivateStmt->execute() && $conn->affected_rows > 0) {
           $reactivatedCount++;
       }
   }
   
   if ($reactivatedCount > 0) {
       // Update invoice
       $updateQuery = "UPDATE invoices SET service_suspended = 0 WHERE id = ?";
       $updateStmt = $conn->prepare($updateQuery);
       $updateStmt->bind_param("i", $invoiceId);
       $updateStmt->execute();
       
       // Send notification about reactivation
       $invoice = getInvoice($invoiceId);
       
       $to = $invoice['email'];
       $subject = 'Service Reactivation Notice - Invoice #' . $invoice['invoice_number'];
       
       // Get email template
       $template = file_get_contents(__DIR__ . '/email-templates/reactivation-notification.html');
       
       // Replace placeholders
       $template = str_replace('{NAME}', $invoice['name'], $template);
       $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
       $template = str_replace('{REACTIVATED_COUNT}', $reactivatedCount, $template);
       
       // Email headers
       $headers = "MIME-Version: 1.0" . "\r\n";
       $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
       $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
       
       // Send email
       mail($to, $subject, $template, $headers);
   }
   
   return $reactivatedCount;
}

/**
* Generate recurring invoices for subscription services
* This function should be called via a cron job
* 
* @return int Number of invoices generated
*/
function generateRecurringInvoices() {
   global $conn;
   
   // Get subscriptions due for renewal
   $today = date('Y-m-d');
   
   $query = "SELECT s.id, s.user_id, s.service_id, s.billing_cycle, s.next_billing_date, 
                    s.price, s.quantity, ser.name as service_name, u.email 
             FROM subscriptions s
             JOIN services ser ON s.service_id = ser.id
             JOIN users u ON s.user_id = u.id
             WHERE s.status = 'active' 
             AND s.next_billing_date <= ?";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("s", $today);
   $stmt->execute();
   $result = $stmt->get_result();
   
   $generatedCount = 0;
   
   while ($subscription = $result->fetch_assoc()) {
       // Prepare items for invoice
       $items = [
           [
               'service_id' => $subscription['service_id'],
               'description' => $subscription['service_name'] . ' (' . ucfirst($subscription['billing_cycle']) . ' subscription)',
               'quantity' => $subscription['quantity'],
               'price' => $subscription['price']
           ]
       ];
       
       // Generate invoice
       $invoiceId = generateInvoice($subscription['user_id'], $items);
       
       if ($invoiceId) {
           $generatedCount++;
           
           // Update next billing date
           $nextBillingDate = '';
           
           switch ($subscription['billing_cycle']) {
               case 'monthly':
                   $nextBillingDate = date('Y-m-d', strtotime('+1 month', strtotime($subscription['next_billing_date'])));
                   break;
               case 'quarterly':
                   $nextBillingDate = date('Y-m-d', strtotime('+3 months', strtotime($subscription['next_billing_date'])));
                   break;
               case 'biannually':
                   $nextBillingDate = date('Y-m-d', strtotime('+6 months', strtotime($subscription['next_billing_date'])));
                   break;
               case 'annually':
                   $nextBillingDate = date('Y-m-d', strtotime('+1 year', strtotime($subscription['next_billing_date'])));
                   break;
               default:
                   $nextBillingDate = date('Y-m-d', strtotime('+1 month', strtotime($subscription['next_billing_date'])));
           }
           
           $updateQuery = "UPDATE subscriptions 
                          SET last_billing_date = ?, 
                              next_billing_date = ?,
                              last_invoice_id = ?
                          WHERE id = ?";
           
           $updateStmt = $conn->prepare($updateQuery);
           $updateStmt->bind_param("ssii", $today, $nextBillingDate, $invoiceId, $subscription['id']);
           $updateStmt->execute();
       }
   }
   
   return $generatedCount;
}

/**
* Get invoice statistics for a specific period
* 
* @param string $period Period type (today, week, month, year)
* @return array Statistics data
*/
function getInvoiceStatistics($period = 'month') {
   global $conn;
   
   $startDate = '';
   $endDate = date('Y-m-d 23:59:59');
   
   switch ($period) {
       case 'today':
           $startDate = date('Y-m-d 00:00:00');
           break;
       case 'week':
           $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
           break;
       case 'month':
           $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
           break;
       case 'year':
           $startDate = date('Y-m-d 00:00:00', strtotime('-365 days'));
           break;
       default:
           $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
   }
   
   // Get total invoices
   $totalQuery = "SELECT COUNT(*) as total_count, SUM(total) as total_amount 
                  FROM invoices 
                  WHERE issue_date BETWEEN ? AND ?";
   
   $stmt = $conn->prepare($totalQuery);
   $stmt->bind_param("ss", $startDate, $endDate);
   $stmt->execute();
   $totalResult = $stmt->get_result()->fetch_assoc();
   
   // Get paid invoices
   $paidQuery = "SELECT COUNT(*) as paid_count, SUM(total) as paid_amount 
                 FROM invoices 
                 WHERE status = 'paid' 
                 AND issue_date BETWEEN ? AND ?";
   
   $stmt = $conn->prepare($paidQuery);
   $stmt->bind_param("ss", $startDate, $endDate);
   $stmt->execute();
   $paidResult = $stmt->get_result()->fetch_assoc();
   
   // Get pending invoices
   $pendingQuery = "SELECT COUNT(*) as pending_count, SUM(total) as pending_amount 
                    FROM invoices 
                    WHERE status = 'pending' 
                    AND issue_date BETWEEN ? AND ?";
   
   $stmt = $conn->prepare($pendingQuery);
   $stmt->bind_param("ss", $startDate, $endDate);
   $stmt->execute();
   $pendingResult = $stmt->get_result()->fetch_assoc();
   
   // Get outstanding invoices
   $outstandingQuery = "SELECT COUNT(*) as outstanding_count, SUM(total) as outstanding_amount 
                        FROM invoices 
                        WHERE status = 'outstanding' 
                        AND issue_date BETWEEN ? AND ?";
   
   $stmt = $conn->prepare($outstandingQuery);
   $stmt->bind_param("ss", $startDate, $endDate);
   $stmt->execute();
   $outstandingResult = $stmt->get_result()->fetch_assoc();
   
   return [
       'period' => $period,
       'start_date' => $startDate,
       'end_date' => $endDate,
       'total_count' => $totalResult['total_count'] ?? 0,
       'total_amount' => $totalResult['total_amount'] ?? 0,
       'paid_count' => $paidResult['paid_count'] ?? 0,
       'paid_amount' => $paidResult['paid_amount'] ?? 0,
       'pending_count' => $pendingResult['pending_count'] ?? 0,
       'pending_amount' => $pendingResult['pending_amount'] ?? 0,
       'outstanding_count' => $outstandingResult['outstanding_count'] ?? 0,
       'outstanding_amount' => $outstandingResult['outstanding_amount'] ?? 0,
       'payment_percentage' => ($totalResult['total_count'] > 0) ? 
           (($paidResult['paid_count'] / $totalResult['total_count']) * 100) : 0
   ];
}

/**
* Process partial payment for an invoice
* 
* @param int $invoiceId The invoice ID
* @param float $amount Amount paid
* @param string $paymentMethod Payment method used
* @param array $paymentDetails Additional payment details
* @return bool Returns true on success, false on failure
*/
function processPartialPayment($invoiceId, $amount, $paymentMethod, $paymentDetails = []) {
   global $conn;
   
   // Get invoice details
   $invoice = getInvoice($invoiceId);
   
   if (!$invoice) {
       return false;
   }
   
   // Check if amount is valid
   if ($amount <= 0 || $amount > $invoice['total']) {
       return false;
   }
   
   // Record payment in database
   $query = "INSERT INTO payments (invoice_id, amount, payment_method, transaction_id, payment_date, status)
             VALUES (?, ?, ?, ?, NOW(), 'completed')";
   
   $transactionId = isset($paymentDetails['transaction_id']) ? $paymentDetails['transaction_id'] : generateRandomString(12);
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("idss", $invoiceId, $amount, $paymentMethod, $transactionId);
   
   if ($stmt->execute()) {
       // Calculate total paid for this invoice
       $paidQuery = "SELECT SUM(amount) as total_paid FROM payments WHERE invoice_id = ? AND status = 'completed'";
       $paidStmt = $conn->prepare($paidQuery);
       $paidStmt->bind_param("i", $invoiceId);
       $paidStmt->execute();
       $paidResult = $paidStmt->get_result()->fetch_assoc();
       
       $totalPaid = $paidResult['total_paid'] ?? 0;
       $remainingBalance = $invoice['total'] - $totalPaid;
       
       // Update invoice with payment information
       $updateQuery = "UPDATE invoices SET amount_paid = ?, remaining_balance = ? WHERE id = ?";
       $updateStmt = $conn->prepare($updateQuery);
       $updateStmt->bind_param("ddi", $totalPaid, $remainingBalance, $invoiceId);
       $updateStmt->execute();
       
       // If fully paid, update status
       if ($remainingBalance <= 0) {
           updateInvoiceStatus($invoiceId, 'paid');
           
           // Reactivate services if they were suspended
           if ($invoice['service_suspended'] == 1) {
               reactivateServices($invoice['user_id'], $invoiceId);
           }
       }
       
       // Send payment confirmation
       $to = $invoice['email'];
       $subject = 'Partial Payment Confirmation for Invoice #' . $invoice['invoice_number'];
       
       // Get email template
       $template = file_get_contents(__DIR__ . '/email-templates/partial-payment-confirmation.html');
       
       // Replace placeholders
       $template = str_replace('{NAME}', $invoice['name'], $template);
       $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
       $template = str_replace('{PAYMENT_AMOUNT}', formatCurrency($amount), $template);
       $template = str_replace('{TOTAL_PAID}', formatCurrency($totalPaid), $template);
       $template = str_replace('{REMAINING_BALANCE}', formatCurrency($remainingBalance), $template);
       $template = str_replace('{PAYMENT_DATE}', date('d/m/Y'), $template);
       $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $invoiceId, $template);
       
       // Email headers
       $headers = "MIME-Version: 1.0" . "\r\n";
       $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
       $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
       
       // Send email
       mail($to, $subject, $template, $headers);
       
       return true;
   }
   
   return false;
}

/**
* Apply coupon code to an invoice
* 
* @param int $invoiceId The invoice ID
* @param string $couponCode Coupon code to apply
* @return bool|array Returns updated invoice on success, false on failure
*/
function applyCouponCode($invoiceId, $couponCode) {
   global $conn;
   
   // Get invoice details
   $invoice = getInvoice($invoiceId);
   
   if (!$invoice || $invoice['status'] !== 'pending') {
       return false; // Can only apply coupons to pending invoices
   }
   
   // Check if coupon exists and is valid
   $couponQuery = "SELECT * FROM coupons WHERE code = ? AND status = 'active'";
   $couponStmt = $conn->prepare($couponQuery);
   $couponStmt->bind_param("s", $couponCode);
   $couponStmt->execute();
   $couponResult = $couponStmt->get_result();
   
   if ($couponResult->num_rows === 0) {
       return false; // Coupon not found or inactive
   }
   
   $coupon = $couponResult->fetch_assoc();
   
   // Check if coupon is expired
   if ($coupon['expiry_date'] && strtotime($coupon['expiry_date']) < time()) {
       return false; // Coupon expired
   }
   
   // Check if coupon has usage limit
   if ($coupon['usage_limit'] > 0) {
       $usageQuery = "SELECT COUNT(*) as usage_count FROM invoice_coupons WHERE coupon_id = ?";
       $usageStmt = $conn->prepare($usageQuery);
       $usageStmt->bind_param("i", $coupon['id']);
       $usageStmt->execute();
       $usageResult = $usageStmt->get_result()->fetch_assoc();
       
       if ($usageResult['usage_count'] >= $coupon['usage_limit']) {
           return false; // Coupon usage limit reached
       }
   }
   
   // Check if user has already used this coupon
   if ($coupon['one_time_per_user'] == 1) {
       $userUsageQuery = "SELECT COUNT(*) as user_usage 
                          FROM invoice_coupons ic
                          JOIN invoices i ON ic.invoice_id = i.id
                          WHERE ic.coupon_id = ? AND i.user_id = ?";
       
       $userUsageStmt = $conn->prepare($userUsageQuery);
       $userUsageStmt->bind_param("ii", $coupon['id'], $invoice['user_id']);
       $userUsageStmt->execute();
       $userUsageResult = $userUsageStmt->get_result()->fetch_assoc();
       
       if ($userUsageResult['user_usage'] > 0) {
           return false; // User has already used this coupon
       }
   }
   
   // Calculate discount amount
   $discountAmount = 0;
   
   if ($coupon['type'] == 'percentage') {
       $discountAmount = ($invoice['subtotal'] * $coupon['value']) / 100;
   } else { // fixed amount
       $discountAmount = $coupon['value'];
       
       // Make sure discount doesn't exceed subtotal
       if ($discountAmount > $invoice['subtotal']) {
           $discountAmount = $invoice['subtotal'];
       }
   }
   
   // Recalculate invoice totals
   $newSubtotal = $invoice['subtotal'];
   $newDiscount = $invoice['discount'] + $discountAmount;
   $newTaxable = $newSubtotal - $newDiscount;
   $newTax = ($newTaxable * getDefaultTaxRate()) / 100;
   $newTotal = $newTaxable + $newTax;
   
   // Update invoice with new totals
   $updateQuery = "UPDATE invoices 
                   SET discount = ?, 
                       tax = ?, 
                       total = ?, 
                       coupon_code = ?,
                       coupon_value = ?
                   WHERE id = ?";
   
   $updateStmt = $conn->prepare($updateQuery);
   $updateStmt->bind_param("dddsdsi", $newDiscount, $newTax, $newTotal, $couponCode, $coupon['value'], $invoiceId);
   
   if ($updateStmt->execute()) {
       // Record coupon usage
       $usageQuery = "INSERT INTO invoice_coupons (invoice_id, coupon_id, discount_amount, applied_date)
                      VALUES (?, ?, ?, NOW())";
       
       $usageStmt = $conn->prepare($usageQuery);
       $usageStmt->bind_param("iid", $invoiceId, $coupon['id'], $discountAmount);
       $usageStmt->execute();
       
       // Regenerate PDF with new totals
       createInvoicePDF($invoiceId);
       
       // Return updated invoice
       return getInvoice($invoiceId);
   }
   
   return false;
}

/**
* Get total revenue for a given period
* 
* @param string $period Period (today, week, month, year, all)
* @return array Revenue statistics
*/
function getTotalRevenue($period = 'month') {
   global $conn;
   
   $startDate = '';
   $endDate = date('Y-m-d 23:59:59');
   
   switch ($period) {
       case 'today':
           $startDate = date('Y-m-d 00:00:00');
           break;
       case 'week':
           $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
           break;
       case 'month':
           $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
           break;
       case 'year':
           $startDate = date('Y-m-d 00:00:00', strtotime('-365 days'));
           break;
       case 'all':
           $startDate = '2000-01-01 00:00:00'; // Far back in time
           break;
       default:
           $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
   }
   
   // Calculate total


   // Revenue from payments
   $query = "SELECT SUM(amount) as total_revenue, 
                    COUNT(DISTINCT invoice_id) as invoice_count, 
                    COUNT(*) as payment_count
             FROM payments 
             WHERE status = 'completed' 
             AND payment_date BETWEEN ? AND ?";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("ss", $startDate, $endDate);
   $stmt->execute();
   $result = $stmt->get_result()->fetch_assoc();
   
   // Get payment methods breakdown
   $methodsQuery = "SELECT payment_method, SUM(amount) as amount, COUNT(*) as count
                    FROM payments
                    WHERE status = 'completed' 
                    AND payment_date BETWEEN ? AND ?
                    GROUP BY payment_method
                    ORDER BY amount DESC";
   
   $methodsStmt = $conn->prepare($methodsQuery);
   $methodsStmt->bind_param("ss", $startDate, $endDate);
   $methodsStmt->execute();
   $methodsResult = $methodsStmt->get_result();
   
   $paymentMethods = [];
   while ($method = $methodsResult->fetch_assoc()) {
       $paymentMethods[] = $method;
   }
   
   // Get daily revenue for charts
   $dailyQuery = "SELECT DATE(payment_date) as date, SUM(amount) as amount
                  FROM payments
                  WHERE status = 'completed' 
                  AND payment_date BETWEEN ? AND ?
                  GROUP BY DATE(payment_date)
                  ORDER BY date";
   
   $dailyStmt = $conn->prepare($dailyQuery);
   $dailyStmt->bind_param("ss", $startDate, $endDate);
   $dailyStmt->execute();
   $dailyResult = $dailyStmt->get_result();
   
   $dailyRevenue = [];
   while ($day = $dailyResult->fetch_assoc()) {
       $dailyRevenue[] = $day;
   }
   
   return [
       'period' => $period,
       'start_date' => $startDate,
       'end_date' => $endDate,
       'total_revenue' => $result['total_revenue'] ?? 0,
       'invoice_count' => $result['invoice_count'] ?? 0,
       'payment_count' => $result['payment_count'] ?? 0,
       'payment_methods' => $paymentMethods,
       'daily_revenue' => $dailyRevenue
   ];
}

/**
* Get top services by revenue
* 
* @param string $period Period (today, week, month, year, all)
* @param int $limit Number of services to return
* @return array Top services
*/
function getTopServices($period = 'month', $limit = 5) {
   global $conn;
   
   $startDate = '';
   $endDate = date('Y-m-d 23:59:59');
   
   switch ($period) {
       case 'today':
           $startDate = date('Y-m-d 00:00:00');
           break;
       case 'week':
           $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
           break;
       case 'month':
           $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
           break;
       case 'year':
           $startDate = date('Y-m-d 00:00:00', strtotime('-365 days'));
           break;
       case 'all':
           $startDate = '2000-01-01 00:00:00'; // Far back in time
           break;
       default:
           $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
   }
   
   $query = "SELECT s.id, s.name, s.description, s.price, 
                    COUNT(ii.id) as order_count, 
                    SUM(ii.subtotal) as revenue
             FROM services s
             JOIN invoice_items ii ON s.id = ii.service_id
             JOIN invoices i ON ii.invoice_id = i.id
             WHERE i.status = 'paid' 
             AND i.issue_date BETWEEN ? AND ?
             GROUP BY s.id
             ORDER BY revenue DESC
             LIMIT ?";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("ssi", $startDate, $endDate, $limit);
   $stmt->execute();
   $result = $stmt->get_result();
   
   $services = [];
   while ($service = $result->fetch_assoc()) {
       $services[] = $service;
   }
   
   return $services;
}

/**
* Get top customers by revenue
* 
* @param string $period Period (today, week, month, year, all)
* @param int $limit Number of customers to return
* @return array Top customers
*/
function getTopCustomers($period = 'month', $limit = 5) {
   global $conn;
   
   $startDate = '';
   $endDate = date('Y-m-d 23:59:59');
   
   switch ($period) {
       case 'today':
           $startDate = date('Y-m-d 00:00:00');
           break;
       case 'week':
           $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
           break;
       case 'month':
           $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
           break;
       case 'year':
           $startDate = date('Y-m-d 00:00:00', strtotime('-365 days'));
           break;
       case 'all':
           $startDate = '2000-01-01 00:00:00'; // Far back in time
           break;
       default:
           $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
   }
   
   $query = "SELECT u.id, u.name, u.email, 
                    COUNT(i.id) as invoice_count, 
                    SUM(p.amount) as total_spent
             FROM users u
             JOIN invoices i ON u.id = i.user_id
             JOIN payments p ON i.id = p.invoice_id
             WHERE p.status = 'completed' 
             AND p.payment_date BETWEEN ? AND ?
             GROUP BY u.id
             ORDER BY total_spent DESC
             LIMIT ?";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("ssi", $startDate, $endDate, $limit);
   $stmt->execute();
   $result = $stmt->get_result();
   
   $customers = [];
   while ($customer = $result->fetch_assoc()) {
       $customers[] = $customer;
   }
   
   return $customers;
}

/**
* Archive old invoices to save database space
* This function should be called periodically for database maintenance
* 
* @param int $months Months older than which to archive invoices
* @return int Number of invoices archived
*/
function archiveOldInvoices($months = 24) {
   global $conn;
   
   $archiveDate = date('Y-m-d', strtotime("-{$months} months"));
   
   // Get invoices to archive
   $query = "SELECT id, invoice_number, pdf_path FROM invoices 
             WHERE issue_date < ? 
             AND archived = 0";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("s", $archiveDate);
   $stmt->execute();
   $result = $stmt->get_result();
   
   $archivedCount = 0;
   
   while ($invoice = $result->fetch_assoc()) {
       // Make sure we have a PDF for this invoice
       if (empty($invoice['pdf_path'])) {
           createInvoicePDF($invoice['id']);
           $invoice = getInvoice($invoice['id']);
       }
       
       // Copy invoice data to archive table
       $archiveQuery = "INSERT INTO archived_invoices 
                        SELECT * FROM invoices WHERE id = ?";
       
       $archiveStmt = $conn->prepare($archiveQuery);
       $archiveStmt->bind_param("i", $invoice['id']);
       
       if ($archiveStmt->execute()) {
           // Archive invoice items
           $archiveItemsQuery = "INSERT INTO archived_invoice_items 
                                 SELECT * FROM invoice_items WHERE invoice_id = ?";
           
           $archiveItemsStmt = $conn->prepare($archiveItemsQuery);
           $archiveItemsStmt->bind_param("i", $invoice['id']);
           $archiveItemsStmt->execute();
           
           // Archive payments
           $archivePaymentsQuery = "INSERT INTO archived_payments 
                                    SELECT * FROM payments WHERE invoice_id = ?";
           
           $archivePaymentsStmt = $conn->prepare($archivePaymentsQuery);
           $archivePaymentsStmt->bind_param("i", $invoice['id']);
           $archivePaymentsStmt->execute();
           
           // Mark invoice as archived
           $updateQuery = "UPDATE invoices SET archived = 1 WHERE id = ?";
           $updateStmt = $conn->prepare($updateQuery);
           $updateStmt->bind_param("i", $invoice['id']);
           
           if ($updateStmt->execute()) {
               $archivedCount++;
           }
       }
   }
   
   return $archivedCount;
}

/**
* Clean up database by removing old temporary data
* This function should be called periodically for database maintenance
* 
* @return bool Success status
*/
function cleanupDatabase() {
   global $conn;
   
   // Clean up logged-in sessions older than 30 days
   $oldSessionDate = date('Y-m-d H:i:s', strtotime('-30 days'));
   $conn->query("DELETE FROM user_sessions WHERE last_activity < '$oldSessionDate'");
   
   // Clean up failed login attempts older than 24 hours
   $oldLoginDate = date('Y-m-d H:i:s', strtotime('-24 hours'));
   $conn->query("DELETE FROM login_attempts WHERE attempt_time < '$oldLoginDate'");
   
   // Clean up password reset tokens older than 24 hours
   $oldTokenDate = date('Y-m-d H:i:s', strtotime('-24 hours'));
   $conn->query("DELETE FROM password_reset_tokens WHERE created_at < '$oldTokenDate'");
   
   // Remove old notifications older than 90 days
   $oldNotificationDate = date('Y-m-d H:i:s', strtotime('-90 days'));
   $conn->query("DELETE FROM notifications WHERE created_at < '$oldNotificationDate'");
   
   // Optional: Remove old system logs older than 90 days
   $oldLogDate = date('Y-m-d H:i:s', strtotime('-90 days'));
   $conn->query("DELETE FROM system_logs WHERE log_time < '$oldLogDate'");
   
   return true;
}

/**
* Export invoices to CSV for specified period
* 
* @param string $period Period (today, week, month, year, all)
* @param string $status Optional filter by status
* @return string Path to generated CSV file
*/
function exportInvoicesToCSV($period = 'month', $status = null) {
   global $conn;
   
   $startDate = '';
   $endDate = date('Y-m-d 23:59:59');
   
   switch ($period) {
       case 'today':
           $startDate = date('Y-m-d 00:00:00');
           break;
       case 'week':
           $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
           break;
       case 'month':
           $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
           break;
       case 'year':
           $startDate = date('Y-m-d 00:00:00', strtotime('-365 days'));
           break;
       case 'all':
           $startDate = '2000-01-01 00:00:00'; // Far back in time
           break;
       default:
           $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
   }
   
   // Prepare query
   $query = "SELECT i.id, i.invoice_number, i.issue_date, i.due_date, 
                    i.status, i.subtotal, i.discount, i.tax, i.total, 
                    i.coupon_code, i.payment_date, u.name as customer_name, 
                    u.email as customer_email
             FROM invoices i
             JOIN users u ON i.user_id = u.id
             WHERE i.issue_date BETWEEN ? AND ?";
   
   $params = [$startDate, $endDate];
   $types = "ss";
   
   if ($status) {
       $query .= " AND i.status = ?";
       $params[] = $status;
       $types .= "s";
   }
   
   $query .= " ORDER BY i.issue_date DESC";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param($types, ...$params);
   $stmt->execute();
   $result = $stmt->get_result();
   
   // Create CSV file
   $filename = 'invoices_export_' . date('Ymd_His') . '.csv';
   $filepath = __DIR__ . '/../exports/' . $filename;
   
   // Create exports directory if it doesn't exist
   if (!file_exists(__DIR__ . '/../exports/')) {
       mkdir(__DIR__ . '/../exports/', 0755, true);
   }
   
   $fp = fopen($filepath, 'w');
   
   // Add CSV headers
   fputcsv($fp, [
       'Invoice ID', 
       'Invoice Number', 
       'Issue Date', 
       'Due Date', 
       'Status', 
       'Subtotal', 
       'Discount', 
       'Tax', 
       'Total',
       'Coupon Code',
       'Payment Date',
       'Customer Name',
       'Customer Email'
   ]);
   
   // Add data
   while ($row = $result->fetch_assoc()) {
       fputcsv($fp, [
           $row['id'],
           $row['invoice_number'],
           $row['issue_date'],
           $row['due_date'],
           $row['status'],
           $row['subtotal'],
           $row['discount'],
           $row['tax'],
           $row['total'],
           $row['coupon_code'],
           $row['payment_date'],
           $row['customer_name'],
           $row['customer_email']
       ]);
   }
   
   fclose($fp);
   
   return $filepath;
}

/**
* Get payment history for a user
* 
* @param int $userId The user ID
* @param int $limit Optional limit of records to return
* @param int $offset Optional offset for pagination
* @return array Payment history
*/
function getUserPaymentHistory($userId, $limit = 10, $offset = 0) {
   global $conn;
   
   $query = "SELECT p.id, p.invoice_id, p.amount, p.payment_method, 
                    p.transaction_id, p.payment_date, p.status,
                    i.invoice_number
             FROM payments p
             JOIN invoices i ON p.invoice_id = i.id
             WHERE i.user_id = ?
             ORDER BY p.payment_date DESC
             LIMIT ? OFFSET ?";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("iii", $userId, $limit, $offset);
   $stmt->execute();
   $result = $stmt->get_result();
   
   $payments = [];
   while ($payment = $result->fetch_assoc()) {
       $payments[] = $payment;
   }
   
   return $payments;
}

/**
* Send early payment reminder
* 
* @param int $invoiceId The invoice ID
* @return bool Success status
*/
function sendEarlyPaymentReminder($invoiceId) {
   $invoice = getInvoice($invoiceId);
   
   if (!$invoice || $invoice['status'] !== 'pending') {
       return false;
   }
   
   $to = $invoice['email'];
   $subject = 'Early Payment Reminder - Invoice #' . $invoice['invoice_number'];
   
   // Get email template
   $template = file_get_contents(__DIR__ . '/email-templates/early-payment-reminder.html');
   
   // Replace placeholders
   $template = str_replace('{NAME}', $invoice['name'], $template);
   $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
   $template = str_replace('{DUE_DATE}', date('d/m/Y', strtotime($invoice['due_date'])), $template);
   $template = str_replace('{AMOUNT}', formatCurrency($invoice['total']), $template);
   $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $invoiceId, $template);
   
   // Email headers
   $headers = "MIME-Version: 1.0" . "\r\n";
   $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
   $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
   
   // Send email
   if (mail($to, $subject, $template, $headers)) {
       // Update last reminder date
       global $conn;
       $updateQuery = "UPDATE invoices SET last_reminder = NOW() WHERE id = ?";
       $updateStmt = $conn->prepare($updateQuery);
       $updateStmt->bind_param("i", $invoiceId);
       $updateStmt->execute();
       
       return true;
   }
   
   return false;
}

/**
* Schedule service suspension for overdue invoices
* 
* @param int $daysOverdue Days after which to schedule suspension
* @return array Scheduled suspensions
*/
function scheduleServiceSuspension($daysOverdue = 30) {
   global $conn;
   
   // Get settings
   $query = "SELECT setting_value FROM settings WHERE setting_group = 'billing' AND setting_key = 'suspension_warning_days'";
   $result = $conn->query($query);
   
   if ($result && $result->num_rows > 0) {
       $row = $result->fetch_assoc();
       $daysOverdue = intval($row['setting_value']);
   }
   
   // Get qualifying invoices
   $warnDate = date('Y-m-d', strtotime("+{$daysOverdue} days"));
   
   $query = "SELECT i.id, i.invoice_number, i.due_date, i.user_id, u.email, u.name 
             FROM invoices i
             JOIN users u ON i.user_id = u.id
             WHERE i.status = 'pending' 
             AND i.due_date = ? 
             AND i.suspension_warning_sent = 0";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("s", $warnDate);
   $stmt->execute();
   $result = $stmt->get_result();
   
   $scheduledSuspensions = [];
   
   while ($invoice = $result->fetch_assoc()) {
       // Get services from this invoice
       $servicesQuery = "SELECT i.service_id, s.name 
                         FROM invoice_items i
                         JOIN services s ON i.service_id = s.id
                         WHERE i.invoice_id = ?";
       
       $servicesStmt = $conn->prepare($servicesQuery);
       $servicesStmt->bind_param("i", $invoice['id']);
       $servicesStmt->execute();
       $servicesResult = $servicesStmt->get_result();
       
       $services = [];
       
       while ($service = $servicesResult->fetch_assoc()) {
           $services[] = $service['name'];
       }
       
       if (!empty($services)) {
           // Send suspension warning
           $to = $invoice['email'];
           $subject = 'Service Suspension Warning - Invoice #' . $invoice['invoice_number'];
           
           // Get email template
           $template = file_get_contents(__DIR__ . '/email-templates/suspension-warning.html');
           
           // Replace placeholders
           $template = str_replace('{NAME}', $invoice['name'], $template);
           $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
           $template = str_replace('{DUE_DATE}', date('d/m/Y', strtotime($invoice['due_date'])), $template);
           $template = str_replace('{SERVICES}', implode(', ', $services), $template);
           $template = str_replace('{SUSPENSION_DATE}', date('d/m/Y', strtotime($invoice['due_date'] . " + {$daysOverdue} days")), $template);
           $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $invoice['id'], $template);
           
           // Email headers
           $headers = "MIME-Version: 1.0" . "\r\n";
           $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
           $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
           
           // Send email
           if (mail($to, $subject, $template, $headers)) {
               // Update suspension warning flag
               $updateQuery = "UPDATE invoices SET suspension_warning_sent = 1 WHERE id = ?";
               $updateStmt = $conn->prepare($updateQuery);
               $updateStmt->bind_param("i", $invoice['id']);
               $updateStmt->execute();
               
               $scheduledSuspensions[] = [
                   'invoice_id' => $invoice['id'],
                   'invoice_number' => $invoice['invoice_number'],
                   'user_id' => $invoice['user_id'],
                   'due_date' => $invoice['due_date'],
                   'suspension_date' => date('Y-m-d', strtotime($invoice['due_date'] . " + {$daysOverdue} days")),
                   'services' => $services
               ];
           }
       }
   }
   
   return $scheduledSuspensions;
}

/**
* Mark an invoice as void
* 
* @param int $invoiceId The invoice ID
* @param string $reason Reason for voiding
* @return bool Success status
*/
function voidInvoice($invoiceId, $reason = '') {
   global $conn;
   
   $invoice = getInvoice($invoiceId);
   
   if (!$invoice || $invoice['status'] === 'paid') {
       return false; // Cannot void a paid invoice
   }
   
   $query = "UPDATE invoices 
             SET status = 'void', 
                 notes = CONCAT(IFNULL(notes, ''), '\nVOIDED: ', ?)
             WHERE id = ?";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("si", $reason, $invoiceId);
   
   if ($stmt->execute()) {
       // Regenerate PDF with void status
       createInvoicePDF($invoiceId);
       
       // Send notification
       $to = $invoice['email'];
       $subject = 'Invoice #' . $invoice['invoice_number'] . ' has been Voided';
       
       // Get email template
       $template = file_get_contents(__DIR__ . '/email-templates/invoice-voided.html');
       
       // Replace placeholders
       $template = str_replace('{NAME}', $invoice['name'], $template);
       $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
       $template = str_replace('{REASON}', $reason, $template);
       
       // Email headers
       $headers = "MIME-Version: 1.0" . "\r\n";
       $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
       $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
       
       // Send email
       mail($to, $subject, $template, $headers);
       
       return true;
   }
   
   return false;
}

/**
* Create a credit note for an invoice
* 
* @param int $invoiceId The invoice ID
* @param float $amount Credit amount (default: full invoice amount)
* @param string $reason Reason for credit
* @return int|bool Credit note ID or false on failure
*/
function createCreditNote($invoiceId, $amount = null, $reason = '') {
   global $conn;
   
   $invoice = getInvoice($invoiceId);
   
   if (!$invoice) {
       return false;
   }
   
   // If amount not specified, use full invoice amount
   if ($amount === null) {
       $amount = $invoice['total'];
   }
   
   // Make sure credit doesn't exceed invoice total
   if ($amount > $invoice['total']) {
       $amount = $invoice['total'];
   }
   
   // Generate credit note number
   $creditNoteNumber = 'CN-' . date('Ymd') . '-' . generateRandomString(6);
   
   // Insert credit note
   $query = "INSERT INTO credit_notes (invoice_id, credit_note_number, amount, reason, issue_date, status)
             VALUES (?, ?, ?, ?, NOW(), 'active')";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("isds", $invoiceId, $creditNoteNumber, $amount, $reason);
   
   if ($stmt->execute()) {
       $creditNoteId = $conn->insert_id;
       
       // Update invoice balance
       $remainingBalance = $invoice['total'] - $amount;
       
       // If full credit, mark invoice as credited
       if ($amount >= $invoice['total']) {
           $updateQuery = "UPDATE invoices 
                          SET status = 'credited', 
                              remaining_balance = 0,
                              amount_paid = total
                          WHERE id = ?";
           
           $updateStmt = $conn->prepare($updateQuery);
           $updateStmt->bind_param("i", $invoiceId);
       } else {
           // Partial credit
           $updateQuery = "UPDATE invoices 
                          SET remaining_balance = ?,
                              amount_paid = amount_paid + ?
                          WHERE id = ?";
           
           $updateStmt = $conn->prepare($updateQuery);
           $updateStmt->bind_param("ddi", $remainingBalance, $amount, $invoiceId);
       }
       
       $updateStmt->execute();
       
       // Send notification
       $to = $invoice['email'];
       $subject = 'Credit Note #' . $creditNoteNumber . ' for Invoice #' . $invoice['invoice_number'];
       
       // Get email template
       $template = file_get_contents(__DIR__ . '/email-templates/credit-note.html');
       
       // Replace placeholders
       $template = str_replace('{NAME}', $invoice['name'], $template);
       $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
       $template = str_replace('{CREDIT_NOTE_NUMBER}', $creditNoteNumber);
       $template = str_replace('{AMOUNT}', formatCurrency($amount), $template);
       $template = str_replace('{REASON}', $reason, $template);
       $template = str_replace('{DATE}', date('d/m/Y'), $template);
       
       // Email headers
       $headers = "MIME-Version: 1.0" . "\r\n";
       $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
       $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
       
       // Send email
       mail($to, $subject, $template, $headers);
       
       return $creditNoteId;
   }
   
   return false;
}

/**
* Change invoice due date
* 
* @param int $invoiceId The invoice ID
* @param string $newDueDate New due date (Y-m-d format)
* @param string $reason Optional reason for date change
* @return bool Success status
*/
function changeInvoiceDueDate($invoiceId, $newDueDate, $reason = '') {
   global $conn;
   
   $invoice = getInvoice($invoiceId);
   
   if (!$invoice || $invoice['status'] === 'paid' || $invoice['status'] === 'void') {
       return false; // Cannot change due date of paid or void invoices
   }
   
   // Validate date format
   if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDueDate)) {
       return false; // Invalid date format
   }
   
   $query = "UPDATE invoices 
             SET due_date = ?, 
                 notes = CONCAT(IFNULL(notes, ''), '\nDue date changed from ", $invoice['due_date'], " to ", $newDueDate, ". Reason: ", $reason, "')
             WHERE id = ?";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("si", $newDueDate, $invoiceId);
   
   if ($stmt->execute()) {
       // Regenerate PDF with new due date
       createInvoicePDF($invoiceId);
       
       // Send notification
       $to = $invoice['email'];
       $subject = 'Due Date Changed for Invoice #' . $invoice['invoice_number'];
       
       // Get email template
       $template = file_get_contents(__DIR__ . '/email-templates/due-date-change.html');
       
       // Replace placeholders
       $template = str_replace('{NAME}', $invoice['name'], $template);
       $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
       $template = str_replace('{OLD_DUE_DATE}', date('d/m/Y', strtotime($invoice['due_date'])), $template);
       $template = str_replace('{NEW_DUE_DATE}', date('d/m/Y', strtotime($newDueDate)), $template);
       $template = str_replace('{REASON}', $reason, $template);
       $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $invoiceId, $template);
       
       // Email headers
       $headers = "MIME-Version: 1.0" . "\r\n";
       $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
       $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
       // Send email
       mail($to, $subject, $template, $headers);
       
       return true;
   }
   
   return false;
}

/**
* Add a manual payment to an invoice
* 
* @param int $invoiceId The invoice ID
* @param float $amount Payment amount
* @param string $paymentMethod Payment method used
* @param string $notes Optional notes
* @param string $paymentDate Optional payment date (defaults to current date)
* @return bool Success status
*/
function addManualPayment($invoiceId, $amount, $paymentMethod, $notes = '', $paymentDate = null) {
   global $conn;
   
   $invoice = getInvoice($invoiceId);
   
   if (!$invoice) {
       return false;
   }
   
   // Validate amount
   if ($amount <= 0) {
       return false;
   }
   
   // Set payment date
   if ($paymentDate === null) {
       $paymentDate = date('Y-m-d H:i:s');
   } else {
       // Validate date format
       if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $paymentDate)) {
           return false; // Invalid date format
       }
   }
   
   // Generate transaction ID
   $transactionId = 'MANUAL-' . date('Ymd') . '-' . generateRandomString(6);
   
   // Insert payment
   $query = "INSERT INTO payments (invoice_id, amount, payment_method, transaction_id, payment_date, status, notes)
             VALUES (?, ?, ?, ?, ?, 'completed', ?)";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("idssss", $invoiceId, $amount, $paymentMethod, $transactionId, $paymentDate, $notes);
   
   if ($stmt->execute()) {
       // Calculate total paid for this invoice
       $paidQuery = "SELECT SUM(amount) as total_paid FROM payments WHERE invoice_id = ? AND status = 'completed'";
       $paidStmt = $conn->prepare($paidQuery);
       $paidStmt->bind_param("i", $invoiceId);
       $paidStmt->execute();
       $paidResult = $paidStmt->get_result()->fetch_assoc();
       
       $totalPaid = $paidResult['total_paid'] ?? 0;
       $remainingBalance = $invoice['total'] - $totalPaid;
       
       // Update invoice with payment information
       $updateQuery = "UPDATE invoices SET amount_paid = ?, remaining_balance = ? WHERE id = ?";
       $updateStmt = $conn->prepare($updateQuery);
       $updateStmt->bind_param("ddi", $totalPaid, $remainingBalance, $invoiceId);
       $updateStmt->execute();
       
       // If fully paid, update status
       if ($remainingBalance <= 0) {
           updateInvoiceStatus($invoiceId, 'paid');
           
           // Reactivate services if they were suspended
           if ($invoice['service_suspended'] == 1) {
               reactivateServices($invoice['user_id'], $invoiceId);
           }
       }
       
       // Send payment confirmation
       $to = $invoice['email'];
       $subject = 'Payment Received for Invoice #' . $invoice['invoice_number'];
       
       // Get email template
       $template = file_get_contents(__DIR__ . '/email-templates/manual-payment-confirmation.html');
       
       // Replace placeholders
       $template = str_replace('{NAME}', $invoice['name'], $template);
       $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
       $template = str_replace('{PAYMENT_AMOUNT}', formatCurrency($amount), $template);
       $template = str_replace('{PAYMENT_METHOD}', $paymentMethod, $template);
       $template = str_replace('{PAYMENT_DATE}', date('d/m/Y', strtotime($paymentDate)), $template);
       $template = str_replace('{TOTAL_PAID}', formatCurrency($totalPaid), $template);
       $template = str_replace('{REMAINING_BALANCE}', formatCurrency($remainingBalance), $template);
       $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $invoiceId, $template);
       
       // Email headers
       $headers = "MIME-Version: 1.0" . "\r\n";
       $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
       $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
       
       // Send email
       mail($to, $subject, $template, $headers);
       
       return true;
   }
   
   return false;
}

/**
* Delete draft invoice
* Only drafts can be deleted, other invoices must be voided
* 
* @param int $invoiceId The invoice ID
* @return bool Success status
*/
function deleteDraftInvoice($invoiceId) {
   global $conn;
   
   $invoice = getInvoice($invoiceId);
   
   if (!$invoice || $invoice['status'] !== 'draft') {
       return false; // Can only delete draft invoices
   }
   
   // Delete invoice items first
   $deleteItemsQuery = "DELETE FROM invoice_items WHERE invoice_id = ?";
   $deleteItemsStmt = $conn->prepare($deleteItemsQuery);
   $deleteItemsStmt->bind_param("i", $invoiceId);
   $deleteItemsStmt->execute();
   
   // Then delete the invoice
   $deleteQuery = "DELETE FROM invoices WHERE id = ? AND status = 'draft'";
   $deleteStmt = $conn->prepare($deleteQuery);
   $deleteStmt->bind_param("i", $invoiceId);
   
   return $deleteStmt->execute() && $conn->affected_rows > 0;
}

/**
* Share invoice with additional email addresses
* 
* @param int $invoiceId The invoice ID
* @param array $emails Array of email addresses
* @param string $message Optional custom message
* @return bool Success status
*/
function shareInvoice($invoiceId, $emails, $message = '') {
   $invoice = getInvoice($invoiceId);
   
   if (!$invoice) {
       return false;
   }
   
   // Make sure we have a PDF for this invoice
   if (empty($invoice['pdf_path'])) {
       createInvoicePDF($invoiceId);
       $invoice = getInvoice($invoiceId);
   }
   
   // Validate emails
   $validEmails = [];
   foreach ($emails as $email) {
       if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
           $validEmails[] = $email;
       }
   }
   
   if (empty($validEmails)) {
       return false;
   }
   
   // Get email template
   $template = file_get_contents(__DIR__ . '/email-templates/shared-invoice.html');
   
   // Custom message
   $customMessage = $message ? '<p>' . htmlspecialchars($message) . '</p>' : '';
   
   // Replace placeholders
   $template = str_replace('{CUSTOMER_NAME}', $invoice['name'], $template);
   $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
   $template = str_replace('{ISSUE_DATE}', date('d/m/Y', strtotime($invoice['issue_date'])), $template);
   $template = str_replace('{DUE_DATE}', date('d/m/Y', strtotime($invoice['due_date'])), $template);
   $template = str_replace('{AMOUNT}', formatCurrency($invoice['total']), $template);
   $template = str_replace('{CUSTOM_MESSAGE}', $customMessage, $template);
   $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $invoiceId . '&token=' . md5($invoice['invoice_number']), $template);
   
   $subject = 'Invoice #' . $invoice['invoice_number'] . ' Shared by ' . $invoice['name'];
   
   // Email headers
   $headers = "MIME-Version: 1.0" . "\r\n";
   $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
   $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
   
   $successCount = 0;
   
   // Send to each email
   foreach ($validEmails as $email) {
       if (mail($email, $subject, $template, $headers)) {
           $successCount++;
           
           // Log sharing activity
           global $conn;
           $logQuery = "INSERT INTO invoice_share_logs (invoice_id, shared_to, shared_by, shared_date)
                        VALUES (?, ?, ?, NOW())";
           
           $logStmt = $conn->prepare($logQuery);
           $logStmt->bind_param("isi", $invoiceId, $email, $invoice['user_id']);
           $logStmt->execute();
       }
   }
   
   return $successCount > 0;
}

/**
* Clone an invoice 
* Creates a new invoice with the same items as an existing one
* 
* @param int $invoiceId The invoice ID to clone
* @param int $userId Optional user ID (defaults to same user)
* @param string $dueDate Optional new due date
* @return int|bool New invoice ID or false on failure
*/
function cloneInvoice($invoiceId, $userId = null, $dueDate = null) {
   $invoice = getInvoice($invoiceId);
   
   if (!$invoice) {
       return false;
   }
   
   // Use same user if not specified
   if ($userId === null) {
       $userId = $invoice['user_id'];
   }
   
   // Set due date
   if ($dueDate === null) {
       $dueDate = date('Y-m-d', strtotime('+14 days'));
   }
   
   // Get invoice items
   $items = [];
   foreach ($invoice['items'] as $item) {
       $items[] = [
           'service_id' => $item['service_id'],
           'description' => $item['description'],
           'quantity' => $item['quantity'],
           'price' => $item['price']
       ];
   }
   
   // Additional information
   $extra = [
       'tax_rate' => getDefaultTaxRate(),
       'due_date' => $dueDate,
       'notes' => 'Cloned from Invoice #' . $invoice['invoice_number']
   ];
   
   // Generate new invoice
   $newInvoiceId = generateInvoice($userId, $items, $extra);
   
   if ($newInvoiceId) {
       // Log cloning activity
       global $conn;
       $logQuery = "INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, log_time)
                    VALUES (?, 'clone', 'invoice', ?, ?, NOW())";
       
       $details = 'Cloned from Invoice #' . $invoice['invoice_number'];
       
       $logStmt = $conn->prepare($logQuery);
       $logStmt->bind_param("iis", $userId, $newInvoiceId, $details);
       $logStmt->execute();
   }
   
   return $newInvoiceId;
}

/**
* Get total outstanding amount for a user
* 
* @param int $userId The user ID
* @return float Total outstanding amount
*/
function getUserOutstandingTotal($userId) {
   global $conn;
   
   $query = "SELECT SUM(total) as total_outstanding 
             FROM invoices 
             WHERE user_id = ? 
             AND (status = 'pending' OR status = 'outstanding')";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("i", $userId);
   $stmt->execute();
   $result = $stmt->get_result()->fetch_assoc();
   
   return $result['total_outstanding'] ?? 0;
}

/**
* Check if a user has overdue invoices
* 
* @param int $userId The user ID
* @return bool True if user has overdue invoices
*/
function hasOverdueInvoices($userId) {
   global $conn;
   
   $query = "SELECT COUNT(*) as overdue_count 
             FROM invoices 
             WHERE user_id = ? 
             AND status = 'outstanding'";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("i", $userId);
   $stmt->execute();
   $result = $stmt->get_result()->fetch_assoc();
   
   return ($result['overdue_count'] ?? 0) > 0;
}

/**
* Add a note to an invoice
* 
* @param int $invoiceId The invoice ID
* @param string $note Note to add
* @param int $userId User ID adding the note
* @return bool Success status
*/
function addInvoiceNote($invoiceId, $note, $userId) {
   global $conn;
   
   $query = "INSERT INTO invoice_notes (invoice_id, user_id, note, created_at)
             VALUES (?, ?, ?, NOW())";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("iis", $invoiceId, $userId, $note);
   
   if ($stmt->execute()) {
       // Update invoice notes field
       $updateQuery = "UPDATE invoices 
                       SET notes = CONCAT(IFNULL(notes, ''), '\n', ?, ' (', NOW(), ')')
                       WHERE id = ?";
       
       $updateStmt = $conn->prepare($updateQuery);
       $updateStmt->bind_param("si", $note, $invoiceId);
       $updateStmt->execute();
       
       return true;
   }
   
   return false;
}

/**
* Get notes for an invoice
* 
* @param int $invoiceId The invoice ID
* @return array Array of notes
*/
function getInvoiceNotes($invoiceId) {
   global $conn;
   
   $query = "SELECT n.*, u.name as user_name 
             FROM invoice_notes n
             LEFT JOIN users u ON n.user_id = u.id
             WHERE n.invoice_id = ?
             ORDER BY n.created_at DESC";
   
   $stmt = $conn->prepare($query);
   $stmt->bind_param("i", $invoiceId);
   $stmt->execute();
   $result = $stmt->get_result();
   
   $notes = [];
   while ($note = $result->fetch_assoc()) {
       $notes[] = $note;
   }
   
   return $notes;
}

/**
* Send bulk invoices
* 
* @param array $invoiceIds Array of invoice IDs to send
* @return int Number of invoices successfully sent
*/
function sendBulkInvoices($invoiceIds) {
   $sentCount = 0;
   
   foreach ($invoiceIds as $invoiceId) {
       $invoice = getInvoice($invoiceId);
       
       if ($invoice) {
           // Make sure we have a PDF for this invoice
           if (empty($invoice['pdf_path'])) {
               createInvoicePDF($invoiceId);
               $invoice = getInvoice($invoiceId);
           }
           
           $to = $invoice['email'];
           $subject = 'Invoice #' . $invoice['invoice_number'];
           
           // Get email template
           $template = file_get_contents(__DIR__ . '/email-templates/invoice-notification.html');
           
           // Replace placeholders
           $template = str_replace('{NAME}', $invoice['name'], $template);
           $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
           $template = str_replace('{INVOICE_DATE}', date('d/m/Y', strtotime($invoice['issue_date'])), $template);
           $template = str_replace('{DUE_DATE}', date('d/m/Y', strtotime($invoice['due_date'])), $template);
           $template = str_replace('{AMOUNT}', formatCurrency($invoice['total']), $template);
           $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $invoiceId, $template);
           
           // Email headers
           $headers = "MIME-Version: 1.0" . "\r\n";
           $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
           $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
           
           // Send email
           if (mail($to, $subject, $template, $headers)) {
               $sentCount++;
               
               // Update last sent date
               global $conn;
               $updateQuery = "UPDATE invoices SET last_sent = NOW() WHERE id = ?";
               $updateStmt = $conn->prepare($updateQuery);
               $updateStmt->bind_param("i", $invoiceId);
               $updateStmt->execute();
           }
       }
   }
   
   return $sentCount;
}

/**
* Send bulk payment reminders
* 
* @param array $invoiceIds Array of invoice IDs
* @return int Number of reminders successfully sent
*/
function sendBulkReminders($invoiceIds) {
   $sentCount = 0;
   
   foreach ($invoiceIds as $invoiceId) {
       $invoice = getInvoice($invoiceId);
       
       if ($invoice && ($invoice['status'] === 'pending' || $invoice['status'] === 'outstanding')) {
           $to = $invoice['email'];
           $subject = 'Payment Reminder for Invoice #' . $invoice['invoice_number'];
           
           // Template varies based on status
           $templateFile = $invoice['status'] === 'pending' ? 
                           'payment-reminder.html' : 
                           'overdue-payment-reminder.html';
           
           // Get email template
           $template = file_get_contents(__DIR__ . '/email-templates/' . $templateFile);
           
           // Replace placeholders
           $template = str_replace('{NAME}', $invoice['name'], $template);
           $template = str_replace('{INVOICE_NUMBER}', $invoice['invoice_number'], $template);
           $template = str_replace('{DUE_DATE}', date('d/m/Y', strtotime($invoice['due_date'])), $template);
           $template = str_replace('{AMOUNT}', formatCurrency($invoice['total']), $template);
           
           if ($invoice['status'] === 'outstanding') {
               $daysOverdue = floor((time() - strtotime($invoice['due_date'])) / 86400);
               $template = str_replace('{DAYS_OVERDUE}', $daysOverdue, $template);
           } else {
               $daysLeft = floor((strtotime($invoice['due_date']) - time()) / 86400);
               $template = str_replace('{DAYS_LEFT}', $daysLeft, $template);
           }
           
           $template = str_replace('{INVOICE_URL}', getAppUrl() . '/invoices.php?id=' . $invoiceId, $template);
           
           // Email headers
           $headers = "MIME-Version: 1.0" . "\r\n";
           $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
           $headers .= 'From: ' . getCompanySettings()['name'] . ' <' . getCompanySettings()['email'] . '>' . "\r\n";
           
           // Send email
           if (mail($to, $subject, $template, $headers)) {
               $sentCount++;
               
               // Update last reminder date
               global $conn;
               $updateQuery = "UPDATE invoices SET last_reminder = NOW() WHERE id = ?";
               $updateStmt = $conn->prepare($updateQuery);
               $updateStmt->bind_param("i", $invoiceId);
               $updateStmt->execute();
           }
       }
   }
   
   return $sentCount;
}

/**
* Search invoices by multiple criteria
* 
* @param array $criteria Search criteria
* @param int $limit Optional result limit
* @param int $offset Optional offset for pagination
* @return array Search results
*/
function searchInvoices($criteria, $limit = 100, $offset = 0) {
   global $conn;
   
   $query = "SELECT i.*, u.name as customer_name, u.email as customer_email
             FROM invoices i
             JOIN users u ON i.user_id = u.id
             WHERE 1=1";
   
   $params = [];
   $types = "";
   
   // Add search criteria
   if (!empty($criteria['invoice_number'])) {
       $query .= " AND i.invoice_number LIKE ?";
       $params[] = "%" . $criteria['invoice_number'] . "%";
       $types .= "s";
   }
   
   if (!empty($criteria['status'])) {
       $query .= " AND i.status = ?";
       $params[] = $criteria['status'];
       $types .= "s";
   }
   
   if (!empty($criteria['user_id'])) {
       $query .= " AND i.user_id = ?";
       $params[] = $criteria['user_id'];
       $types .= "i";
   }
   
   if (!empty($criteria['customer_name'])) {
       $query .= " AND u.name LIKE ?";
       $params[] = "%" . $criteria['customer_name'] . "%";
       $types .= "s";
   }
   
   if (!empty($criteria['customer_email'])) {
       $query .= " AND u.email LIKE ?";
       $params[] = "%" . $criteria['customer_email'] . "%";
       $types .= "s";
   }
   
   if (!empty($criteria['date_from'])) {
       $query .= " AND i.issue_date >= ?";
       $params[] = $criteria['date_from'];
       $types .= "s";
   }
   
   if (!empty($criteria['date_to'])) {
       $query .= " AND i.issue_date <= ?";
       $params[] = $criteria['date_to'];
       $types .= "s";
   }
   
   if (!empty($criteria['due_date_from'])) {
       $query .= " AND i.due_date >= ?";
       $params[] = $criteria['due_date_from'];
       $types .= "s";
   }
   
   if (!empty($criteria['due_date_to'])) {
       $query .= " AND i.due_date <= ?";
       $params[] = $criteria['due_date_to'];
       $types .= "s";
   }
   
   if (isset($criteria['min_amount'])) {
       $query .= " AND i.total >= ?";
       $params[] = $criteria['min_amount'];
       $types .= "d";
   }
   
   if (isset($criteria['max_amount'])) {
       $query .= " AND i.total <= ?";
       $params[] = $criteria['max_amount'];
       $types .= "d";
   }
   
   // Order by
   $query .= " ORDER BY " . (!empty($criteria['order_by']) ? $criteria['order_by'] : "i.issue_date DESC");
   
   // Limit and offset
   $query .= " LIMIT ? OFFSET ?";
   $params[] = $limit;
   $params[] = $offset;
   $types .= "ii";
   
   $stmt = $conn->prepare($query);
   
   if (!empty($params)) {
       $stmt->bind_param($types, ...$params);
   }
   
   $stmt->execute();
   $result = $stmt->get_result();
   
   $invoices = [];
   while ($invoice = $result->fetch_assoc()) {
       $invoices[] = $invoice;
   }
   
   return $invoices;
}

/**
* Get count of searchable invoices
* 
* @param array $criteria Search criteria
* @return int Count of matching invoices
*/
function getInvoiceSearchCount($criteria) {
   global $conn;
   
   $query = "SELECT COUNT(*) as count
             FROM invoices i
             JOIN users u ON i.user_id = u.id
             WHERE 1=1";
   
   $params = [];
   $types = "";
   
   // Add search criteria
   if (!empty($criteria['invoice_number'])) {
       $query .= " AND i.invoice_number LIKE ?";
       $params[] = "%" . $criteria['invoice_number'] . "%";
       $types .= "s";
   }
   
   if (!empty($criteria['status'])) {
       $query .= " AND i.status = ?";
       $params[] = $criteria['status'];
       $types .= "s";
   }
   
   if (!empty($criteria['user_id'])) {
       $query .= " AND i.user_id = ?";
       $params[] = $criteria['user_id'];
       $types .= "i";
   }
   
   if (!empty($criteria['customer_name'])) {
       $query .= " AND u.name LIKE ?";
       $params[] = "%" . $criteria['customer_name'] . "%";
       $types .= "s";
   }
   
   if (!empty($criteria['customer_email'])) {
       $query .= " AND u.email LIKE ?";
       $params[] = "%" . $criteria['customer_email'] . "%";
       $types .= "s";
   }
   
   if (!empty($criteria['date_from'])) {
       $query .= " AND i.issue_date >= ?";
       $params[] = $criteria['date_from'];
       $types .= "s";
   }
   
   if (!empty($criteria['date_to'])) {
       $query .= " AND i.issue_date <= ?";
       $params[] = $criteria['date_to'];
       $types .= "s";
   }
   
   if (!empty($criteria['due_date_from'])) {
       $query .= " AND i.due_date >= ?";
       $params[] = $criteria['due_date_from'];
       $types .= "s";
   }
   
   if (!empty($criteria['due_date_to'])) {
       $query .= " AND i.due_date <= ?";
       $params[] = $criteria['due_date_to'];
       $types .= "s";
   }
   
   if (isset($criteria['min_amount'])) {
       $query .= " AND i.total >= ?";
       $params[] = $criteria['min_amount'];
       $types .= "d";
   }
   
   if (isset($criteria['max_amount'])) {
       $query .= " AND i.total <= ?";
       $params[] = $criteria['max_amount'];
       $types .= "d";
   }
   
   $stmt = $conn->prepare($query);
   
   if (!empty($params)) {
       $stmt->bind_param($types, ...$params);
   }
   
   $stmt->execute();
   $result = $stmt->get_result()->fetch_assoc();
   
   return $result['count'] ?? 0;
}

?>