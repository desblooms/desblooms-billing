<?php
/**
 * Form Helper
 * 
 * Collection of functions to assist with form creation, validation,
 * and processing for the Digital Service Billing Mobile App
 */

// Prevent direct access to this file
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Create a form opening tag with CSRF protection
 * 
 * @param string $action The form action URL
 * @param string $method The form method (POST, GET)
 * @param string $id Form ID (optional)
 * @param string $class Form CSS classes (optional)
 * @param boolean $multipart Whether to enable file uploads (optional)
 * @return string The HTML form opening tag
 */
function form_open($action, $method = 'POST', $id = '', $class = '', $multipart = false) {
    $csrf_token = generate_csrf_token();
    
    $form = '<form action="' . htmlspecialchars($action) . '" method="' . htmlspecialchars($method) . '"';
    
    if (!empty($id)) {
        $form .= ' id="' . htmlspecialchars($id) . '"';
    }
    
    if (!empty($class)) {
        $form .= ' class="' . htmlspecialchars($class) . '"';
    }
    
    if ($multipart) {
        $form .= ' enctype="multipart/form-data"';
    }
    
    $form .= '>' . PHP_EOL;
    $form .= '<input type="hidden" name="csrf_token" value="' . $csrf_token . '">' . PHP_EOL;
    
    return $form;
}

/**
 * Create a form closing tag
 * 
 * @return string The HTML form closing tag
 */
function form_close() {
    return '</form>' . PHP_EOL;
}

/**
 * Create a text input field
 * 
 * @param string $name The input name
 * @param string $value The input value (optional)
 * @param string $id The input ID (optional, defaults to $name)
 * @param string $class The input CSS classes (optional)
 * @param array $attributes Additional attributes (optional)
 * @return string The HTML input field
 */
function form_input($name, $value = '', $id = '', $class = '', $attributes = []) {
    if (empty($id)) {
        $id = $name;
    }
    
    $input = '<input type="text" name="' . htmlspecialchars($name) . '" id="' . htmlspecialchars($id) . '"';
    
    if (!empty($value)) {
        $input .= ' value="' . htmlspecialchars($value) . '"';
    }
    
    if (!empty($class)) {
        $input .= ' class="' . htmlspecialchars($class) . '"';
    }
    
    foreach ($attributes as $key => $val) {
        $input .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
    }
    
    $input .= '>';
    
    return $input;
}

/**
 * Create a password input field
 * 
 * @param string $name The input name
 * @param string $id The input ID (optional, defaults to $name)
 * @param string $class The input CSS classes (optional)
 * @param array $attributes Additional attributes (optional)
 * @return string The HTML password input field
 */
function form_password($name, $id = '', $class = '', $attributes = []) {
    if (empty($id)) {
        $id = $name;
    }
    
    $input = '<input type="password" name="' . htmlspecialchars($name) . '" id="' . htmlspecialchars($id) . '"';
    
    if (!empty($class)) {
        $input .= ' class="' . htmlspecialchars($class) . '"';
    }
    
    foreach ($attributes as $key => $val) {
        $input .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
    }
    
    $input .= '>';
    
    return $input;
}

/**
 * Create a textarea field
 * 
 * @param string $name The textarea name
 * @param string $value The textarea value (optional)
 * @param string $id The textarea ID (optional, defaults to $name)
 * @param string $class The textarea CSS classes (optional)
 * @param array $attributes Additional attributes (optional)
 * @return string The HTML textarea field
 */
function form_textarea($name, $value = '', $id = '', $class = '', $attributes = []) {
    if (empty($id)) {
        $id = $name;
    }
    
    $textarea = '<textarea name="' . htmlspecialchars($name) . '" id="' . htmlspecialchars($id) . '"';
    
    if (!empty($class)) {
        $textarea .= ' class="' . htmlspecialchars($class) . '"';
    }
    
    foreach ($attributes as $key => $val) {
        $textarea .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
    }
    
    $textarea .= '>' . htmlspecialchars($value) . '</textarea>';
    
    return $textarea;
}

/**
 * Create a select dropdown field
 * 
 * @param string $name The select name
 * @param array $options The options as key-value pairs
 * @param string $selected The selected value (optional)
 * @param string $id The select ID (optional, defaults to $name)
 * @param string $class The select CSS classes (optional)
 * @param array $attributes Additional attributes (optional)
 * @return string The HTML select field
 */
function form_dropdown($name, $options = [], $selected = '', $id = '', $class = '', $attributes = []) {
    if (empty($id)) {
        $id = $name;
    }
    
    $dropdown = '<select name="' . htmlspecialchars($name) . '" id="' . htmlspecialchars($id) . '"';
    
    if (!empty($class)) {
        $dropdown .= ' class="' . htmlspecialchars($class) . '"';
    }
    
    foreach ($attributes as $key => $val) {
        $dropdown .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
    }
    
    $dropdown .= '>' . PHP_EOL;
    
    foreach ($options as $value => $text) {
        $dropdown .= '<option value="' . htmlspecialchars($value) . '"';
        
        if ($selected == $value) {
            $dropdown .= ' selected';
        }
        
        $dropdown .= '>' . htmlspecialchars($text) . '</option>' . PHP_EOL;
    }
    
    $dropdown .= '</select>';
    
    return $dropdown;
}

/**
 * Create a checkbox input field
 * 
 * @param string $name The checkbox name
 * @param string $value The checkbox value
 * @param boolean $checked Whether the checkbox is checked (optional)
 * @param string $id The checkbox ID (optional, defaults to $name)
 * @param string $class The checkbox CSS classes (optional)
 * @param array $attributes Additional attributes (optional)
 * @return string The HTML checkbox input field
 */
function form_checkbox($name, $value, $checked = false, $id = '', $class = '', $attributes = []) {
    if (empty($id)) {
        $id = $name;
    }
    
    $checkbox = '<input type="checkbox" name="' . htmlspecialchars($name) . '" id="' . htmlspecialchars($id) . '" value="' . htmlspecialchars($value) . '"';
    
    if ($checked) {
        $checkbox .= ' checked';
    }
    
    if (!empty($class)) {
        $checkbox .= ' class="' . htmlspecialchars($class) . '"';
    }
    
    foreach ($attributes as $key => $val) {
        $checkbox .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
    }
    
    $checkbox .= '>';
    
    return $checkbox;
}

/**
 * Create a radio input field
 * 
 * @param string $name The radio name
 * @param string $value The radio value
 * @param boolean $checked Whether the radio is checked (optional)
 * @param string $id The radio ID (optional, defaults to $name_$value)
 * @param string $class The radio CSS classes (optional)
 * @param array $attributes Additional attributes (optional)
 * @return string The HTML radio input field
 */
function form_radio($name, $value, $checked = false, $id = '', $class = '', $attributes = []) {
    if (empty($id)) {
        $id = $name . '_' . $value;
    }
    
    $radio = '<input type="radio" name="' . htmlspecialchars($name) . '" id="' . htmlspecialchars($id) . '" value="' . htmlspecialchars($value) . '"';
    
    if ($checked) {
        $radio .= ' checked';
    }
    
    if (!empty($class)) {
        $radio .= ' class="' . htmlspecialchars($class) . '"';
    }
    
    foreach ($attributes as $key => $val) {
        $radio .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
    }
    
    $radio .= '>';
    
    return $radio;
}

/**
 * Create a submit button
 * 
 * @param string $name The button name
 * @param string $value The button value/text
 * @param string $id The button ID (optional)
 * @param string $class The button CSS classes (optional)
 * @param array $attributes Additional attributes (optional)
 * @return string The HTML submit button
 */
function form_submit($name, $value, $id = '', $class = '', $attributes = []) {
    $submit = '<button type="submit" name="' . htmlspecialchars($name) . '"';
    
    if (!empty($id)) {
        $submit .= ' id="' . htmlspecialchars($id) . '"';
    }
    
    if (!empty($class)) {
        $submit .= ' class="' . htmlspecialchars($class) . '"';
    }
    
    foreach ($attributes as $key => $val) {
        $submit .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
    }
    
    $submit .= '>' . htmlspecialchars($value) . '</button>';
    
    return $submit;
}

/**
 * Create a button
 * 
 * @param string $name The button name
 * @param string $value The button value/text
 * @param string $type The button type (button, reset)
 * @param string $id The button ID (optional)
 * @param string $class The button CSS classes (optional)
 * @param array $attributes Additional attributes (optional)
 * @return string The HTML button
 */
function form_button($name, $value, $type = 'button', $id = '', $class = '', $attributes = []) {
    $button = '<button type="' . htmlspecialchars($type) . '" name="' . htmlspecialchars($name) . '"';
    
    if (!empty($id)) {
        $button .= ' id="' . htmlspecialchars($id) . '"';
    }
    
    if (!empty($class)) {
        $button .= ' class="' . htmlspecialchars($class) . '"';
    }
    
    foreach ($attributes as $key => $val) {
        $button .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
    }
    
    $button .= '>' . htmlspecialchars($value) . '</button>';
    
    return $button;
}

/**
 * Create a hidden input field
 * 
 * @param string $name The input name
 * @param string $value The input value
 * @return string The HTML hidden input field
 */
function form_hidden($name, $value) {
    return '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '">';
}

/**
 * Create a file upload input field
 * 
 * @param string $name The input name
 * @param string $id The input ID (optional, defaults to $name)
 * @param string $class The input CSS classes (optional)
 * @param array $attributes Additional attributes (optional)
 * @return string The HTML file input field
 */
function form_upload($name, $id = '', $class = '', $attributes = []) {
    if (empty($id)) {
        $id = $name;
    }
    
    $upload = '<input type="file" name="' . htmlspecialchars($name) . '" id="' . htmlspecialchars($id) . '"';
    
    if (!empty($class)) {
        $upload .= ' class="' . htmlspecialchars($class) . '"';
    }
    
    foreach ($attributes as $key => $val) {
        $upload .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
    }
    
    $upload .= '>';
    
    return $upload;
}

/**
 * Create a label element
 * 
 * @param string $for The input ID this label is for
 * @param string $text The label text
 * @param string $class The label CSS classes (optional)
 * @param array $attributes Additional attributes (optional)
 * @return string The HTML label element
 */
function form_label($for, $text, $class = '', $attributes = []) {
    $label = '<label for="' . htmlspecialchars($for) . '"';
    
    if (!empty($class)) {
        $label .= ' class="' . htmlspecialchars($class) . '"';
    }
    
    foreach ($attributes as $key => $val) {
        $label .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
    }
    
    $label .= '>' . htmlspecialchars($text) . '</label>';
    
    return $label;
}

/**
 * Get form error for a specific field
 * 
 * @param array $errors Array of error messages
 * @param string $field Field name
 * @param string $class CSS class for error message (optional)
 * @return string HTML error message or empty string
 */
function form_error($errors, $field, $class = 'text-red-500 text-sm mt-1') {
    if (isset($errors[$field])) {
        return '<div class="' . htmlspecialchars($class) . '">' . htmlspecialchars($errors[$field]) . '</div>';
    }
    return '';
}

/**
 * Generate a CSRF token and store it in the session
 * 
 * @return string The generated CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token
 * 
 * @return boolean True if token is valid, false otherwise
 */
function validate_csrf_token() {
    if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token'])) {
        return false;
    }
    
    $token = $_POST['csrf_token'];
    
    if (hash_equals($_SESSION['csrf_token'], $token)) {
        // Generate a new token for the next request
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return true;
    }
    
    return false;
}

/**
 * Retrieve and sanitize POST data
 * 
 * @param string $field Field name
 * @param string $default Default value if field doesn't exist (optional)
 * @return string Sanitized field value or default
 */
function post($field, $default = '') {
    if (isset($_POST[$field])) {
        return htmlspecialchars(trim($_POST[$field]));
    }
    return $default;
}

/**
 * Retrieve and sanitize GET data
 * 
 * @param string $field Field name
 * @param string $default Default value if field doesn't exist (optional)
 * @return string Sanitized field value or default
 */
function get($field, $default = '') {
    if (isset($_GET[$field])) {
        return htmlspecialchars(trim($_GET[$field]));
    }
    return $default;
}

/**
 * Set and retrieve old input values to repopulate forms after submission
 * 
 * @param string $field Field name
 * @param string $value Field value (if setting)
 * @return mixed Field value (if getting) or void (if setting)
 */
function old($field, $value = null) {
    // Setting value
    if ($value !== null) {
        $_SESSION['old_input'][$field] = $value;
        return;
    }
    
    // Getting value
    if (isset($_SESSION['old_input'][$field])) {
        $value = $_SESSION['old_input'][$field];
        unset($_SESSION['old_input'][$field]);
        return $value;
    }
    
    return '';
}

/**
 * Check if a form has been submitted via POST method
 * 
 * @return boolean True if form submitted, false otherwise
 */
function is_submitted() {
    return ($_SERVER['REQUEST_METHOD'] === 'POST');
}

/**
 * Create form input group with label and error message
 * 
 * @param string $label Label text
 * @param string $input HTML input element
 * @param string $error Error message (optional)
 * @param string $group_class CSS classes for the group div (optional)
 * @return string Complete HTML form group
 */
function form_group($label, $input, $error = '', $group_class = 'mb-4') {
    $html = '<div class="' . htmlspecialchars($group_class) . '">';
    $html .= $label;
    $html .= $input;
    if (!empty($error)) {
        $html .= $error;
    }
    $html .= '</div>';
    
    return $html;
}