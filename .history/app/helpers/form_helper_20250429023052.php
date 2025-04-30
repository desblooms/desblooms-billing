<?php
/**
 * Form Helper
 * 
 * A collection of functions to assist with form creation, validation, and processing
 * for the Digital Service Billing Mobile App.
 */

// Prevent direct access to this file
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
/**
 * Create a form stepper (numbered steps with description)
 * 
 * @param array $steps Array of steps as ['title' => '', 'description' => '']
 * @param int $current_step Current step (0-based index)
 * @return string HTML for form stepper
 */
function form_stepper($steps, $current_step) {
    $total_steps = count($steps);
    
    $html = '<nav aria-label="Progress" class="mb-8">';
    $html .= '<ol role="list" class="overflow-hidden">';
    
    foreach ($steps as $index => $step) {
        $status = '';
        
        if ($index < $current_step) {
            $status = 'complete';
        } elseif ($index === $current_step) {
            $status = 'current';
        } else {
            $status = 'upcoming';
        }
        
        $html .= '<li class="relative pb-8">';
        
        // Connect line between steps (except last)
        if ($index !== $total_steps - 1) {
            if ($status === 'complete') {
                $html .= '<div class="-ml-px absolute mt-0.5 top-4 left-4 w-0.5 h-full bg-primary-600" aria-hidden="true"></div>';
            } else {
                $html .= '<div class="-ml-px absolute mt-0.5 top-4 left-4 w-0.5 h-full bg-gray-300" aria-hidden="true"></div>';
            }
        }
        
        $html .= '<div class="relative flex items-start group">';
        
        // Circle/number indicator
        $html .= '<span class="h-9 flex items-center">';
        
        if ($status === 'complete') {
            $html .= '<span class="relative z-10 w-8 h-8 flex items-center justify-center bg-primary-600 rounded-full">';
            // Checkmark icon
            $html .= '<svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
            $html .= '<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />';
            $html .= '</svg>';
            $html .= '</span>';
        } elseif ($status === 'current') {
            $html .= '<span class="relative z-10 w-8 h-8 flex items-center justify-center bg-primary-600 rounded-full text-white">';
            $html .= ($index + 1);
            $html .= '</span>';
        } else {
            $html .= '<span class="relative z-10 w-8 h-8 flex items-center justify-center bg-white border-2 border-gray-300 rounded-full">';
            $html .= '<span class="text-gray-500">' . ($index + 1) . '</span>';
            $html .= '</span>';
        }
        
        $html .= '</span>';
        
        // Step content
        $html .= '<span class="ml-4 min-w-0 flex flex-col">';
        
        // Step title
        if ($status === 'complete') {
            $html .= '<span class="text-sm font-medium text-primary-600">' . $step['title'] . '</span>';
        } elseif ($status === 'current') {
            $html .= '<span class="text-sm font-medium text-primary-600">' . $step['title'] . '</span>';
        } else {
            $html .= '<span class="text-sm font-medium text-gray-500">' . $step['title'] . '</span>';
        }
        
        // Step description
        if (!empty($step['description'])) {
            $html .= '<span class="text-sm text-gray-500">' . $step['description'] . '</span>';
        }
        
        $html .= '</span>';
        $html .= '</div>';
        $html .= '</li>';
    }
    
    $html .= '</ol>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * Create a mobile-friendly dropdown menu
 * 
 * @param string $label The dropdown button label
 * @param array $items Array of menu items as ['url' => '', 'label' => '', 'icon' => '']
 * @return string HTML for dropdown menu
 */
function mobile_dropdown_menu($label, $items) {
    $dropdown_id = 'dropdown_' . rand(1000, 9999);
    
    $html = '<div class="relative inline-block text-left">';
    
    // Dropdown button
    $html .= '<div>';
    $html .= '<button type="button" id="' . $dropdown_id . '_button" aria-expanded="false" aria-haspopup="true" class="inline-flex items-center justify-center w-full rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">';
    $html .= $label;
    
    // Dropdown arrow icon
    $html .= '<svg class="ml-2 -mr-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">';
    $html .= '<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />';
    $html .= '</svg>';
    $html .= '</button>';
    $html .= '</div>';
    
    // Dropdown menu items
    $html .= '<div id="' . $dropdown_id . '_menu" class="hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10" role="menu" aria-orientation="vertical" aria-labelledby="' . $dropdown_id . '_button" tabindex="-1">';
    $html .= '<div class="py-1" role="none">';
    
    foreach ($items as $item) {
        $html .= '<a href="' . $item['url'] . '" class="group flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900" role="menuitem" tabindex="-1">';
        
        // Icon if provided
        if (!empty($item['icon'])) {
            $html .= '<span class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-500">';
            $html .= $item['icon'];
            $html .= '</span>';
        }
        
        $html .= $item['label'];
        $html .= '</a>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // JavaScript for dropdown functionality
    $html .= '<script>';
    $html .= 'document.addEventListener("DOMContentLoaded", function() {';
    $html .= '    const button = document.getElementById("' . $dropdown_id . '_button");';
    $html .= '    const menu = document.getElementById("' . $dropdown_id . '_menu");';
    
    $html .= '    if (button && menu) {';
    $html .= '        button.addEventListener("click", function() {';
    $html .= '            const expanded = button.getAttribute("aria-expanded") === "true";';
    $html .= '            button.setAttribute("aria-expanded", !expanded);';
    $html .= '            menu.classList.toggle("hidden");';
    $html .= '        });';
    
    $html .= '        // Close when clicking outside';
    $html .= '        document.addEventListener("click", function(event) {';
    $html .= '            if (!button.contains(event.target) && !menu.contains(event.target)) {';
    $html .= '                button.setAttribute("aria-expanded", "false");';
    $html .= '                menu.classList.add("hidden");';
    $html .= '            }';
    $html .= '        });';
    $html .= '    }';
    $html .= '});';
    $html .= '</script>';
    
    return $html;
}

/**
 * Create a form section with title and description
 * 
 * @param string $title Section title
 * @param string $description Section description
 * @param string $content Section content
 * @return string HTML for form section
 */
function form_section($title, $description, $content) {
    $html = '<div class="form-section py-4">';
    
    // Section header
    $html .= '<div class="md:grid md:grid-cols-3 md:gap-6">';
    $html .= '<div class="md:col-span-1">';
    $html .= '<h3 class="text-lg font-medium text-gray-900">' . $title . '</h3>';
    
    if (!empty($description)) {
        $html .= '<p class="mt-1 text-sm text-gray-500">' . $description . '</p>';
    }
    
    $html .= '</div>';
    
    // Section content
    $html .= '<div class="mt-5 md:mt-0 md:col-span-2">';
    $html .= '<div class="px-4 py-5 bg-white sm:p-6 shadow sm:rounded-md">';
    $html .= $content;
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Create a dynamic form field adder (to add multiple items)
 * 
 * @param string $container_id ID for the container element
 * @param string $template_html HTML template for each new field (use {index} for dynamic index)
 * @param string $button_text Text for the "Add" button
 * @return string HTML for dynamic field adder
 */
function dynamic_field_adder($container_id, $template_html, $button_text = 'Add Another') {
    $html = '<div id="' . $container_id . '">';
    // Initial field will already be in place
    $html .= '</div>';
    
    // Add button
    $html .= '<div class="mt-2">';
    $html .= '<button type="button" id="' . $container_id . '_add" class="inline-flex items-center px-3 py-2 border border-transparent shadow-sm text-sm leading-4 font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">';
    
    // Plus icon
    $html .= '<svg class="-ml-0.5 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">';
    $html .= '<path fill-rule="evenodd" d="M10 3a1 1 0 00-1 1v5H4a1 1 0 100 2h5v5a1 1 0 102 0v-5h5a1 1 0 100-2h-5V4a1 1 0 00-1-1z" clip-rule="evenodd" />';
    $html .= '</svg>';
    $html .= $button_text;
    $html .= '</button>';
    $html .= '</div>';
    
    // JavaScript for dynamic field functionality
    $html .= '<script>';
    $html .= 'document.addEventListener("DOMContentLoaded", function() {';
    $html .= '    const container = document.getElementById("' . $container_id . '");';
    $html .= '    const addButton = document.getElementById("' . $container_id . '_add");';
    $html .= '    let fieldIndex = container.children.length;';
    
    $html .= '    function addField() {';
    $html .= '        const template = `' . str_replace('{index}', '${fieldIndex}', $template_html) . '`;';
    $html .= '        const tempDiv = document.createElement("div");';
    $html .= '        tempDiv.innerHTML = template;';
    $html .= '        const fieldDiv = tempDiv.firstChild;';
    
    $html .= '        // Add remove button functionality';
    $html .= '        const removeButton = fieldDiv.querySelector(".remove-field");';
    $html .= '        if (removeButton) {';
    $html .= '            removeButton.addEventListener("click", function() {';
    $html .= '                fieldDiv.remove();';
    $html .= '            });';
    $html .= '        }';
    
    $html .= '        container.appendChild(fieldDiv);';
    $html .= '        fieldIndex++;';
    $html .= '    }';
    
    $html .= '    if (addButton) {';
    $html .= '        addButton.addEventListener("click", addField);';
    $html .= '    }';
    
    $html .= '    // Setup remove buttons for existing fields';
    $html .= '    const removeButtons = container.querySelectorAll(".remove-field");';
    $html .= '    removeButtons.forEach(button => {';
    $html .= '        button.addEventListener("click", function() {';
    $html .= '            this.closest(".dynamic-field").remove();';
    $html .= '        });';
    $html .= '    });';
    $html .= '});';
    $html .= '</script>';
    
    return $html;
}

/**
 * Create field template for dynamic adder with remove button
 * 
 * @param string $content Form field content
 * @return string HTML template for dynamic field
 */
function dynamic_field_template($content) {
    $html = '<div class="dynamic-field relative border border-gray-200 rounded-md p-4 mb-2">';
    $html .= $content;
    
    // Remove button
    $html .= '<button type="button" class="remove-field absolute top-2 right-2 text-gray-400 hover:text-gray-500">';
    $html .= '<span class="sr-only">Remove</span>';
    $html .= '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
    $html .= '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />';
    $html .= '</svg>';
    $html .= '</button>';
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Create a quantity input with plus/minus buttons
 * 
 * @param string $name Input name
 * @param int $value Current value
 * @param int $min Minimum value
 * @param int $max Maximum value
 * @param string $label Label text
 * @return string HTML for quantity input
 */
function quantity_input($name, $value = 1, $min = 1, $max = 100, $label = '') {
    $input_id = $name . '_qty';
    
    $html = '<div class="quantity-input">';
    
    if (!empty($label)) {
        $html .= '<label for="' . $input_id . '" class="block text-sm font-medium text-gray-700 mb-1">' . $label . '</label>';
    }
    
    $html .= '<div class="flex rounded-md shadow-sm">';
    
    // Minus button
    $html .= '<button type="button" class="qty-btn qty-minus relative inline-flex items-center px-2 py-2 border border-r-0 border-gray-300 rounded-l-md bg-gray-50 text-gray-500 hover:bg-gray-100">';
    $html .= '<span class="sr-only">Decrease</span>';
    $html .= '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
    $html .= '<path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />';
    $html .= '</svg>';
    $html .= '</button>';
    
    // Input
    $html .= '<input type="number" id="' . $input_id . '" name="' . $name . '" value="' . $value . '" min="' . $min . '" max="' . $max . '" ';
    $html .= 'class="qty-input block w-14 text-center border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm">';
    
    // Plus button
    $html .= '<button type="button" class="qty-btn qty-plus relative inline-flex items-center px-2 py-2 border border-l-0 border-gray-300 rounded-r-md bg-gray-50 text-gray-500 hover:bg-gray-100">';
    $html .= '<span class="sr-only">Increase</span>';
    $html .= '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
    $html .= '<path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />';
    $html .= '</svg>';
    $html .= '</button>';
    
    $html .= '</div>';
    $html .= '</div>';
    
    // JavaScript for quantity input functionality
    $html .= '<script>';
    $html .= 'document.addEventListener("DOMContentLoaded", function() {';
    $html .= '    const input = document.getElementById("' . $input_id . '");';
    $html .= '    const minusBtn = input.parentNode.querySelector(".qty-minus");';
    $html .= '    const plusBtn = input.parentNode.querySelector(".qty-plus");';
    
    $html .= '    minusBtn.addEventListener("click", function() {';
    $html .= '        const currentValue = parseInt(input.value);';
    $html .= '        const min = parseInt(input.min);';
    $html .= '        if (currentValue > min) {';
    $html .= '            input.value = currentValue - 1;';
    $html .= '            input.dispatchEvent(new Event("change"));';
    $html .= '        }';
    $html .= '    });';
    
    $html .= '    plusBtn.addEventListener("click", function() {';
    $html .= '        const currentValue = parseInt(input.value);';
    $html .= '        const max = parseInt(input.max);';
    $html .= '        if (currentValue < max) {';
    $html .= '            input.value = currentValue + 1;';
    $html .= '            input.dispatchEvent(new Event("change"));';
    $html .= '        }';
    $html .= '    });';
    $html .= '});';
    $html .= '</script>';
    
    return $html;
}

/**
 * Create a conditional form field that shows/hides based on another field's value
 * 
 * @param string $trigger_field_id ID of the field that triggers visibility
 * @param string $trigger_value Value that should show the conditional field
 * @param string $content Content of the conditional field
 * @param bool $is_select Whether the trigger is a select field
 * @return string HTML for conditional form field
 */
function conditional_field($trigger_field_id, $trigger_value, $content, $is_select = false) {
    $container_id = 'conditional_' . rand(1000, 9999);
    
    $html = '<div id="' . $container_id . '" class="hidden mt-4">';
    $html .= $content;
    $html .= '</div>';
    
    // JavaScript for conditional visibility
    $html .= '<script>';
    $html .= 'document.addEventListener("DOMContentLoaded", function() {';
    $html .= '    const container = document.getElementById("' . $container_id . '");';
    $html .= '    const triggerField = document.getElementById("' . $trigger_field_id . '");';
    
    $html .= '    function checkVisibility() {';
    
    if ($is_select) {
        $html .= '        const fieldValue = triggerField.options[triggerField.selectedIndex].value;';
    } else {
        $html .= '        const fieldValue = triggerField.value;';
        
        // For checkbox/radio
        $html .= '        const isChecked = triggerField.type === "checkbox" || triggerField.type === "radio" ? triggerField.checked : true;';
    }
    
    $html .= '        if (fieldValue === "' . $trigger_value . '"' . (!$is_select ? ' && isChecked' : '') . ') {';
    $html .= '            container.classList.remove("hidden");';
    $html .= '        } else {';
    $html .= '            container.classList.add("hidden");';
    $html .= '        }';
    $html .= '    }';
    
    $html .= '    if (triggerField) {';
    $html .= '        triggerField.addEventListener("change", checkVisibility);';
    $html .= '        // Initial check';
    $html .= '        checkVisibility();';
    $html .= '    }';
    $html .= '});';
    $html .= '</script>';
    
    return $html;
}

// End of Form Helper
}

/**
 * Generate a random string
 * 
 * @param int $length The length of the random string
 * @param string $keyspace The characters to use
 * @return string The random string
 */
function generate_random_string($length = 16, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
    $str = '';
    $max = mb_strlen($keyspace, '8bit') - 1;
    
    if ($max < 1) {
        throw new Exception('Keyspace must be at least two characters long');
    }
    
    for ($i = 0; $i < $length; ++$i) {
        $str .= $keyspace[random_int(0, $max)];
    }
    
    return $str;
}

/**
 * Create a multi-step form with progress tracking
 * 
 * @param string $form_id Unique ID for the form
 * @param array $steps Array of step information
 * @param int $current_step Current step (0-based index)
 * @param string $form_action Form action URL
 * @param array $form_attributes Additional form attributes
 * @return string HTML for the multi-step form
 */
function form_multi_step($form_id, $steps, $current_step, $form_action = '', $form_attributes = []) {
    // Store current step in session
    $_SESSION[$form_id . '_step'] = $current_step;
    
    // Generate step indicators
    $step_names = array_column($steps, 'name');
    $step_indicators = form_wizard_steps($step_names, $current_step);
    
    // Start form
    $html = $step_indicators;
    $html .= form_open($form_action, 'post', array_merge(['id' => $form_id], $form_attributes));
    
    // Add current step content
    $html .= '<div class="step-content">';
    $html .= $steps[$current_step]['content'];
    $html .= '</div>';
    
    // Add navigation buttons
    $html .= '<div class="flex justify-between mt-8">';
    
    // Previous button (except for first step)
    if ($current_step > 0) {
        $html .= '<button type="button" onclick="window.location=\'' . $steps[$current_step - 1]['url'] . '\'" class="py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">Previous</button>';
    } else {
        $html .= '<div></div>'; // Empty div for spacing
    }
    
    // Next/Submit button
    if ($current_step < count($steps) - 1) {
        $html .= '<button type="submit" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">Next</button>';
    } else {
        $html .= '<button type="submit" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">Submit</button>';
    }
    
    $html .= '</div>';
    
    // Add hidden field to track current step
    $html .= '<input type="hidden" name="form_step" value="' . $current_step . '">';
    
    // Close form
    $html .= form_close();
    
    return $html;
}

/**
 * Create a sortable table header
 * 
 * @param string $column The column name
 * @param string $label The display label
 * @param string $current_sort Current sort column
 * @param string $current_order Current sort order (asc/desc)
 * @param string $url_pattern URL pattern with {sort} and {order} placeholders
 * @return string HTML for the sortable header
 */
function sortable_header($column, $label, $current_sort, $current_order, $url_pattern) {
    $html = '<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">';
    
    // Determine next sort order
    $next_order = 'asc';
    if ($current_sort === $column && $current_order === 'asc') {
        $next_order = 'desc';
    }
    
    // Generate the sort URL
    $sort_url = str_replace(['{sort}', '{order}'], [$column, $next_order], $url_pattern);
    
    // Create the header link
    $html .= '<a href="' . $sort_url . '" class="group inline-flex items-center">';
    $html .= $label;
    
    // Add sort indicators
    if ($current_sort === $column) {
        if ($current_order === 'asc') {
            $html .= '<svg class="ml-2 h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
            $html .= '<path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />';
            $html .= '</svg>';
        } else {
            $html .= '<svg class="ml-2 h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
            $html .= '<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />';
            $html .= '</svg>';
        }
    } else {
        $html .= '<svg class="ml-2 h-4 w-4 text-gray-400 opacity-0 group-hover:opacity-100" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
        $html .= '<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />';
        $html .= '</svg>';
    }
    
    $html .= '</a>';
    $html .= '</th>';
    
    return $html;
}

/**
 * Create form tabs
 * 
 * @param string $id Unique ID for the tabs
 * @param array $tabs Array of tabs as ['name' => '', 'content' => '']
 * @param string $active_tab Currently active tab name
 * @return string HTML for the tabs
 */
function form_tabs($id, $tabs, $active_tab = '') {
    // If no active tab is specified, use the first one
    if (empty($active_tab)) {
        $active_tab = array_key_first($tabs);
    }
    
    $html = '<div class="tabs-container" id="' . $id . '">';
    
    // Tab navigation
    $html .= '<div class="border-b border-gray-200">';
    $html .= '<nav class="-mb-px flex space-x-8" aria-label="Tabs">';
    
    foreach ($tabs as $tab_name => $tab) {
        $is_active = ($tab_name === $active_tab);
        $tab_class = $is_active 
            ? 'border-primary-500 text-primary-600' 
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';
        
        $html .= '<button type="button" class="tab-button ' . $tab_class . ' whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="' . $tab_name . '">';
        $html .= $tab['name'];
        $html .= '</button>';
    }
    
    $html .= '</nav>';
    $html .= '</div>';
    
    // Tab content
    $html .= '<div class="tab-content mt-4">';
    
    foreach ($tabs as $tab_name => $tab) {
        $is_active = ($tab_name === $active_tab);
        $content_class = $is_active ? 'block' : 'hidden';
        
        $html .= '<div class="tab-pane ' . $content_class . '" data-tab-content="' . $tab_name . '">';
        $html .= $tab['content'];
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    // JavaScript for tab functionality
    $html .= '<script>';
    $html .= 'document.addEventListener("DOMContentLoaded", function() {';
    $html .= '    const tabContainer = document.getElementById("' . $id . '");';
    $html .= '    if (tabContainer) {';
    $html .= '        const tabButtons = tabContainer.querySelectorAll(".tab-button");';
    $html .= '        const tabContents = tabContainer.querySelectorAll("[data-tab-content]");';
    
    $html .= '        tabButtons.forEach(button => {';
    $html .= '            button.addEventListener("click", () => {';
    $html .= '                const tabName = button.getAttribute("data-tab");';
    
    $html .= '                // Update button styles';
    $html .= '                tabButtons.forEach(btn => {';
    $html .= '                    btn.classList.remove("border-primary-500", "text-primary-600");';
    $html .= '                    btn.classList.add("border-transparent", "text-gray-500", "hover:text-gray-700", "hover:border-gray-300");';
    $html .= '                });';
    
    $html .= '                button.classList.remove("border-transparent", "text-gray-500", "hover:text-gray-700", "hover:border-gray-300");';
    $html .= '                button.classList.add("border-primary-500", "text-primary-600");';
    
    $html .= '                // Show selected content, hide others';
    $html .= '                tabContents.forEach(content => {';
    $html .= '                    if (content.getAttribute("data-tab-content") === tabName) {';
    $html .= '                        content.classList.remove("hidden");';
    $html .= '                        content.classList.add("block");';
    $html .= '                    } else {';
    $html .= '                        content.classList.remove("block");';
    $html .= '                        content.classList.add("hidden");';
    $html .= '                    }';
    $html .= '                });';
    $html .= '            });';
    $html .= '        });';
    $html .= '    });';
    $html .= '});';
    $html .= '</script>';
    
    $html .= '</div>';
    
    return $html;
} '        });';
    $html .= '    }';
    $html .= '});';
    $html .= '</script>';
    
    return $html;
}

/**
 * Create a date range picker
 * 
 * @param string $start_name Name for the start date input
 * @param string $end_name Name for the end date input
 * @param string $start_value Current start date value
 * @param string $end_value Current end date value
 * @param string $label Label for the date range
 * @return string HTML for the date range picker
 */
function form_date_range($start_name, $end_name, $start_value = '', $end_value = '', $label = 'Date Range') {
    $html = '<div class="date-range-picker">';
    
    if (!empty($label)) {
        $html .= '<label class="block text-sm font-medium text-gray-700 mb-1">' . $label . '</label>';
    }
    
    $html .= '<div class="flex space-x-2 items-center">';
    
    // Start date
    $html .= '<div class="flex-1">';
    $html .= '<label for="' . $start_name . '" class="block text-xs text-gray-500">From</label>';
    $html .= '<input type="date" name="' . $start_name . '" id="' . $start_name . '" value="' . $start_value . '" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">';
    $html .= '</div>';
    
    // End date
    $html .= '<div class="flex-1">';
    $html .= '<label for="' . $end_name . '" class="block text-xs text-gray-500">To</label>';
    $html .= '<input type="date" name="' . $end_name . '" id="' . $end_name . '" value="' . $end_value . '" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">';
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Create a mobile-optimized form group (stacked layout)
 * 
 * @param string $type The input type
 * @param string $name The input name
 * @param string $label The label text
 * @param string $value The input value
 * @param array $attributes Additional input attributes
 * @param bool $required Whether the field is required
 * @param string $error Error message for this field
 * @return string HTML for mobile-optimized form group
 */
function mobile_form_group($type, $name, $label = '', $value = '', $attributes = [], $required = false, $error = '') {
    $html = '<div class="mb-4">';
    
    // Add the label if provided
    if (!empty($label)) {
        $html .= '<label for="' . $name . '" class="block text-sm font-medium text-gray-700 mb-1">' . $label;
        if ($required) {
            $html .= ' <span class="text-red-500">*</span>';
        }
        $html .= '</label>';
    }
    
    // Add default mobile-friendly classes if not specified
    if (!isset($attributes['class'])) {
        $attributes['class'] = 'block w-full px-3 py-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm';
    }
    
    // Add the appropriate form element based on type
    switch ($type) {
        case 'textarea':
            $html .= form_textarea($name, '', $value, $attributes, $required);
            break;
        case 'select':
            // For select, $value should be the options array and $attributes['selected'] should be the selected value
            $selected = $attributes['selected'] ?? '';
            unset($attributes['selected']);
            $html .= form_select($name, $value, '', $selected, $attributes, $required);
            break;
        case 'file':
            $html .= form_file($name, '', $attributes, $required);
            break;
        default:
            $html .= form_input($type, $name, '', $value, $attributes, $required);
    }
    
    // Add error message if provided
    if (!empty($error)) {
        $html .= '<p class="mt-1 text-sm text-red-600">' . $error . '</p>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Create a mobile-optimized button (full width, larger touch target)
 * 
 * @param string $text The button text
 * @param string $type The button type (button, submit, reset)
 * @param array $attributes Additional button attributes
 * @return string The HTML mobile-optimized button
 */
function mobile_button($text, $type = 'button', $attributes = []) {
    // Add default mobile-friendly classes if not specified
    if (!isset($attributes['class'])) {
        $attributes['class'] = 'w-full py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500';
    }
    
    return form_button($text, $type, $attributes);
}

/**
 * Create a responsive grid layout for form fields
 * 
 * @param array $fields Array of field HTML
 * @param int $columns Number of columns on desktop (1-6)
 * @return string HTML for the responsive grid
 */
function form_grid($fields, $columns = 2) {
    // Validate columns
    $columns = min(max(1, $columns), 6);
    
    // Map columns to grid-cols-* classes
    $grid_cols_map = [
        1 => 'md:grid-cols-1',
        2 => 'md:grid-cols-2',
        3 => 'md:grid-cols-3',
        4 => 'md:grid-cols-4',
        5 => 'md:grid-cols-5',
        6 => 'md:grid-cols-6'
    ];
    
    $grid_class = $grid_cols_map[$columns];
    
    $html = '<div class="grid grid-cols-1 ' . $grid_class . ' gap-4">';
    
    foreach ($fields as $field) {
        $html .= '<div class="form-grid-item">';
        $html .= $field;
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Create an auto-submit form (submits on change)
 * 
 * @param string $action The form action URL
 * @param string $content The form content (HTML form elements)
 * @param array $attributes Additional form attributes
 * @return string HTML for the auto-submit form
 */
function auto_submit_form($action, $content, $attributes = []) {
    $form_id = 'auto_submit_form_' . rand(1000, 9999);
    
    // Merge attributes with default form ID
    $attributes = array_merge(['id' => $form_id], $attributes);
    
    $html = form_open($action, 'get', $attributes);
    $html .= $content;
    $html .= form_close();
    
    // JavaScript for auto-submit
    $html .= '<script>';
    $html .= 'document.addEventListener("DOMContentLoaded", function() {';
    $html .= '    const form = document.getElementById("' . $form_id . '");';
    $html .= '    if (form) {';
    $html .= '        const inputs = form.querySelectorAll("select, input[type=\'radio\'], input[type=\'checkbox\']");';
    $html .= '        inputs.forEach(input => {';
    $html .= '            input.addEventListener("change", function() {';
    $html .= '                form.submit();';
    $html .= '            });';
    $html .= '        });';
    $html .= '    }';
    $html .= '});';
    $html .= '</script>';
    
    return $html;
}

/**
 * Create a character counter for text inputs
 * 
 * @param string $input_id The ID of the input to count characters for
 * @param int $max_length Maximum allowed length
 * @return string HTML and JavaScript for character counter
 */
function character_counter($input_id, $max_length) {
    $counter_id = 'char_counter_' . $input_id;
    
    $html = '<div id="' . $counter_id . '" class="text-xs text-gray-500 mt-1 text-right">0/' . $max_length . ' characters</div>';
    
    // JavaScript for counter
    $html .= '<script>';
    $html .= 'document.addEventListener("DOMContentLoaded", function() {';
    $html .= '    const input = document.getElementById("' . $input_id . '");';
    $html .= '    const counter = document.getElementById("' . $counter_id . '");';
    
    $html .= '    if (input && counter) {';
    $html .= '        // Set initial count';
    $html .= '        updateCount();';
    
    $html .= '        // Update on input';
    $html .= '        input.addEventListener("input", updateCount);';
    
    $html .= '        function updateCount() {';
    $html .= '            const length = input.value.length;';
    $html .= '            counter.textContent = length + "/" + ' . $max_length . ' + " characters";';
    
    $html .= '            // Highlight counter if over limit';
    $html .= '            if (length > ' . $max_length . ') {';
    $html .= '                counter.classList.add("text-red-500");';
    $html .= '                counter.classList.remove("text-gray-500");';
    $html .= '            } else {';
    $html .= '                counter.classList.add("text-gray-500");';
    $html .= '                counter.classList.remove("text-red-500");';
    $html .= '            }';
    $html .= '        }';
    $html .= '    }';
    $html .= '});';
    $html .= '</script>';
    
    return $html;
}

/**
 * Create a mobile-optimized form card
 * 
 * @param string $title The card title
 * @param string $content The card content
 * @return string HTML for mobile-optimized form card
 */
function mobile_form_card($title, $content) {
    $html = '<div class="bg-white rounded-lg shadow-sm overflow-hidden mb-4">';
    
    if (!empty($title)) {
        $html .= '<div class="px-4 py-3 border-b border-gray-200">';
        $html .= '<h3 class="text-lg font-medium text-gray-900">' . $title . '</h3>';
        $html .= '</div>';
    }
    
    $html .= '<div class="p-4">';
    $html .= $content;
    $html .= '</div>';
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Create a loading indicator
 * 
 * @param string $form_id The ID of the form to show loading for
 * @param string $button_text The button text to replace on loading
 * @param string $loading_text The text to show during loading
 * @return string JavaScript for loading indicator
 */
function form_loading_indicator($form_id, $button_text = 'Submit', $loading_text = 'Processing...') {
    $html = '<script>';
    $html .= 'document.addEventListener("DOMContentLoaded", function() {';
    $html .= '    const form = document.getElementById("' . $form_id . '");';
    $html .= '    if (form) {';
    $html .= '        form.addEventListener("submit", function() {';
    $html .= '            const submitButton = form.querySelector("button[type=\'submit\']");';
    $html .= '            if (submitButton) {';
    $html .= '                const originalText = submitButton.textContent;';
    $html .= '                if (originalText === "' . $button_text . '") {';
    $html .= '                    submitButton.textContent = "' . $loading_text . '";';
    $html .= '                    submitButton.disabled = true;';
    $html .= '                    submitButton.classList.add("opacity-75", "cursor-wait");';
    $html .= '                }';
    $html .= '            }';
    $html .= '        });';
    $html .= '    }';
    $html .= '});';
    $html .= '</script>';
    
    return $html;
}

/**
 * Create a mobile-friendly bottom fixed action bar
 * 
 * @param string $content The action bar content (buttons, etc.)
 * @return string HTML for the bottom action bar
 */
function mobile_action_bar($content) {
    $html = '<div class="fixed bottom-0 left-0 right-0 z-10 bg-white border-t border-gray-200 p-4">';
    $html .= '<div class="flex justify-end space-x-3">';
    $html .= $content;
    $html .= '</div>';
    $html .= '</div>';
    
    // Add margin to page to prevent content from being hidden behind the bar
    $html .= '<div class="h-20"></div>'; // Spacer at bottom of page
    
    return $html;
}

/**
 * Create a star rating input
 * 
 * @param string $name The input name
 * @param int $value The current rating value (1-5)
 * @return string HTML for star rating input
 */
function form_star_rating($name, $value = 0) {
    $html = '<div class="star-rating">';
    $html .= '<input type="hidden" name="' . $name . '" id="' . $name . '" value="' . $value . '">';
    
    $html .= '<div class="flex items-center">';
    
    for ($i = 1; $i <= 5; $i++) {
        $star_id = $name . '_star_' . $i;
        $is_active = ($i <= $value);
        $star_class = $is_active ? 'text-yellow-400' : 'text-gray-300';
        
        $html .= '<button type="button" id="' . $star_id . '" data-value="' . $i . '" class="star-btn ' . $star_class . ' h-8 w-8 focus:outline-none">';
        
        // Star SVG
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
        $html .= '<path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />';
        $html .= '</svg>';
        
        $html .= '</button>';
    }
    
    $html .= '</div>';
    
    // JavaScript for star rating
    $html .= '<script>';
    $html .= 'document.addEventListener("DOMContentLoaded", function() {';
    $html .= '    const starInput = document.getElementById("' . $name . '");';
    $html .= '    const stars = document.querySelectorAll("#' . $name . ' ~ .flex .star-btn");';
    
    $html .= '    stars.forEach(star => {';
    $html .= '        star.addEventListener("click", function() {';
    $html .= '            const value = parseInt(this.getAttribute("data-value"));';
    $html .= '            starInput.value = value;';
    
    $html .= '            // Update star colors';
    $html .= '            stars.forEach(s => {';
    $html .= '                const starValue = parseInt(s.getAttribute("data-value"));';
    $html .= '                if (starValue <= value) {';
    $html .= '                    s.classList.add("text-yellow-400");';
    $html .= '                    s.classList.remove("text-gray-300");';
    $html .= '                } else {';
    $html .= '                    s.classList.add("text-gray-300");';
    $html .= '                    s.classList.remove("text-yellow-400");';
    $html .= '                }';
    $html .= '            });';
    $html .=

/**
 * Generate a CSRF token and store it in the session
 * 
 * @return string The generated CSRF token
 */
function generate_csrf_token() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Validate a submitted CSRF token against the stored token
 * 
 * @param string $token The submitted token to validate
 * @return bool Whether the token is valid
 */
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== $token) {
        return false;
    }
    return true;
}

/**
 * Create an HTML form open tag with automatic CSRF protection
 * 
 * @param string $action The form action URL
 * @param string $method The form method (POST, GET)
 * @param array $attributes Additional form attributes
 * @return string The HTML form opening tag with CSRF token field
 */
function form_open($action = '', $method = 'post', $attributes = []) {
    $html = '<form action="' . $action . '" method="' . $method . '"';
    
    foreach ($attributes as $key => $value) {
        $html .= ' ' . $key . '="' . $value . '"';
    }
    
    $html .= '>';
    
    // Add CSRF token field for POST forms
    if (strtolower($method) === 'post') {
        $token = generate_csrf_token();
        $html .= '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
    
    return $html;
}

/**
 * Create an HTML form close tag
 * 
 * @return string The HTML form closing tag
 */
function form_close() {
    return '</form>';
}

/**
 * Create an input field with label
 * 
 * @param string $type The input type
 * @param string $name The input name
 * @param string $label The label text
 * @param string $value The input value
 * @param array $attributes Additional input attributes
 * @param bool $required Whether the field is required
 * @return string The HTML input field with label
 */
function form_input($type, $name, $label = '', $value = '', $attributes = [], $required = false) {
    $html = '';
    
    // Add label if provided
    if (!empty($label)) {
        $html .= '<label for="' . $name . '" class="block text-sm font-medium text-gray-700 mb-1">' . $label;
        if ($required) {
            $html .= ' <span class="text-red-500">*</span>';
        }
        $html .= '</label>';
    }
    
    // Build the input element
    $html .= '<input type="' . $type . '" name="' . $name . '" id="' . $name . '" value="' . htmlspecialchars($value) . '"';
    
    // Add required attribute if needed
    if ($required) {
        $html .= ' required';
    }
    
    // Add any additional attributes
    foreach ($attributes as $key => $val) {
        $html .= ' ' . $key . '="' . $val . '"';
    }
    
    // Add default Tailwind classes if not specified
    if (!isset($attributes['class'])) {
        $html .= ' class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm"';
    }
    
    $html .= '>';
    
    return $html;
}

/**
 * Create a textarea field with label
 * 
 * @param string $name The textarea name
 * @param string $label The label text
 * @param string $value The textarea value
 * @param array $attributes Additional textarea attributes
 * @param bool $required Whether the field is required
 * @return string The HTML textarea field with label
 */
function form_textarea($name, $label = '', $value = '', $attributes = [], $required = false) {
    $html = '';
    
    // Add label if provided
    if (!empty($label)) {
        $html .= '<label for="' . $name . '" class="block text-sm font-medium text-gray-700 mb-1">' . $label;
        if ($required) {
            $html .= ' <span class="text-red-500">*</span>';
        }
        $html .= '</label>';
    }
    
    // Build the textarea element
    $html .= '<textarea name="' . $name . '" id="' . $name . '"';
    
    // Add required attribute if needed
    if ($required) {
        $html .= ' required';
    }
    
    // Add any additional attributes
    foreach ($attributes as $key => $val) {
        $html .= ' ' . $key . '="' . $val . '"';
    }
    
    // Add default Tailwind classes if not specified
    if (!isset($attributes['class'])) {
        $html .= ' class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm"';
    }
    
    $html .= '>' . htmlspecialchars($value) . '</textarea>';
    
    return $html;
}

/**
 * Create a select dropdown field with label
 * 
 * @param string $name The select name
 * @param array $options The select options as [value => label]
 * @param string $label The label text
 * @param string $selected The selected value
 * @param array $attributes Additional select attributes
 * @param bool $required Whether the field is required
 * @return string The HTML select field with label
 */
function form_select($name, $options, $label = '', $selected = '', $attributes = [], $required = false) {
    $html = '';
    
    // Add label if provided
    if (!empty($label)) {
        $html .= '<label for="' . $name . '" class="block text-sm font-medium text-gray-700 mb-1">' . $label;
        if ($required) {
            $html .= ' <span class="text-red-500">*</span>';
        }
        $html .= '</label>';
    }
    
    // Build the select element
    $html .= '<select name="' . $name . '" id="' . $name . '"';
    
    // Add required attribute if needed
    if ($required) {
        $html .= ' required';
    }
    
    // Add any additional attributes
    foreach ($attributes as $key => $val) {
        $html .= ' ' . $key . '="' . $val . '"';
    }
    
    // Add default Tailwind classes if not specified
    if (!isset($attributes['class'])) {
        $html .= ' class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm"';
    }
    
    $html .= '>';
    
    // Add options
    foreach ($options as $value => $option_label) {
        $html .= '<option value="' . $value . '"';
        if ((string) $value === (string) $selected) {
            $html .= ' selected';
        }
        $html .= '>' . $option_label . '</option>';
    }
    
    $html .= '</select>';
    
    return $html;
}

/**
 * Create a checkbox or radio input with label
 * 
 * @param string $type Either 'checkbox' or 'radio'
 * @param string $name The input name
 * @param string $value The input value
 * @param string $label The label text
 * @param bool $checked Whether the input is checked
 * @param array $attributes Additional input attributes
 * @return string The HTML checkbox or radio input with label
 */
function form_checkbox_radio($type, $name, $value, $label, $checked = false, $attributes = []) {
    $html = '<div class="flex items-center">';
    
    // Build the input element
    $html .= '<input type="' . $type . '" name="' . $name . '" id="' . $name . '_' . $value . '" value="' . $value . '"';
    
    if ($checked) {
        $html .= ' checked';
    }
    
    // Add any additional attributes
    foreach ($attributes as $key => $val) {
        $html .= ' ' . $key . '="' . $val . '"';
    }
    
    // Add default Tailwind classes if not specified
    if (!isset($attributes['class'])) {
        $html .= ' class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"';
    }
    
    $html .= '>';
    
    // Add label
    $html .= '<label for="' . $name . '_' . $value . '" class="ml-2 block text-sm text-gray-700">' . $label . '</label>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Create a submit button
 * 
 * @param string $text The button text
 * @param array $attributes Additional button attributes
 * @return string The HTML submit button
 */
function form_submit($text = 'Submit', $attributes = []) {
    $html = '<button type="submit"';
    
    // Add any additional attributes
    foreach ($attributes as $key => $val) {
        $html .= ' ' . $key . '="' . $val . '"';
    }
    
    // Add default Tailwind classes if not specified
    if (!isset($attributes['class'])) {
        $html .= ' class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"';
    }
    
    $html .= '>' . $text . '</button>';
    
    return $html;
}

/**
 * Get and sanitize POST data
 * 
 * @param string $key The POST key to retrieve
 * @param mixed $default The default value if the key doesn't exist
 * @return mixed The sanitized POST value or default
 */
function get_post($key, $default = '') {
    if (isset($_POST[$key])) {
        return sanitize_input($_POST[$key]);
    }
    return $default;
}

/**
 * Get and sanitize GET data
 * 
 * @param string $key The GET key to retrieve
 * @param mixed $default The default value if the key doesn't exist
 * @return mixed The sanitized GET value or default
 */
function get_get($key, $default = '') {
    if (isset($_GET[$key])) {
        return sanitize_input($_GET[$key]);
    }
    return $default;
}

/**
 * Sanitize input data
 * 
 * @param mixed $data The data to sanitize
 * @return mixed The sanitized data
 */
function sanitize_input($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize_input($value);
        }
    } else {
        // Trim whitespace
        $data = trim($data);
        
        // Strip HTML and PHP tags
        $data = strip_tags($data);
        
        // Convert special characters to HTML entities
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    return $data;
}

/**
 * Display validation errors
 * 
 * @param array $errors Array of error messages
 * @return string HTML for displaying errors
 */
function display_errors($errors) {
    $html = '';
    
    if (!empty($errors)) {
        $html .= '<div class="bg-red-50 p-4 rounded-md mb-4">';
        $html .= '<div class="flex">';
        $html .= '<div class="flex-shrink-0">';
        // SVG for error icon
        $html .= '<svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">';
        $html .= '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />';
        $html .= '</svg>';
        $html .= '</div>';
        $html .= '<div class="ml-3">';
        $html .= '<h3 class="text-sm font-medium text-red-800">Please fix the following errors:</h3>';
        $html .= '<div class="mt-2 text-sm text-red-700">';
        $html .= '<ul class="list-disc pl-5 space-y-1">';
        
        foreach ($errors as $error) {
            $html .= '<li>' . $error . '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    return $html;
}

/**
 * Display success message
 * 
 * @param string $message The success message
 * @return string HTML for displaying success message
 */
function display_success($message) {
    if (empty($message)) {
        return '';
    }
    
    $html = '<div class="bg-green-50 p-4 rounded-md mb-4">';
    $html .= '<div class="flex">';
    $html .= '<div class="flex-shrink-0">';
    // SVG for success icon
    $html .= '<svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">';
    $html .= '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />';
    $html .= '</svg>';
    $html .= '</div>';
    $html .= '<div class="ml-3">';
    $html .= '<p class="text-sm font-medium text-green-800">' . $message . '</p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Create a file upload input with label
 * 
 * @param string $name The input name
 * @param string $label The label text
 * @param array $attributes Additional input attributes
 * @param bool $required Whether the field is required
 * @return string The HTML file upload input with label
 */
function form_file($name, $label = '', $attributes = [], $required = false) {
    $html = '';
    
    // Add label if provided
    if (!empty($label)) {
        $html .= '<label for="' . $name . '" class="block text-sm font-medium text-gray-700 mb-1">' . $label;
        if ($required) {
            $html .= ' <span class="text-red-500">*</span>';
        }
        $html .= '</label>';
    }
    
    $html .= '<div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">';
    $html .= '<div class="space-y-1 text-center">';
    $html .= '<svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">';
    $html .= '<path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />';
    $html .= '</svg>';
    $html .= '<div class="flex text-sm text-gray-600">';
    $html .= '<label for="' . $name . '" class="relative cursor-pointer bg-white rounded-md font-medium text-primary-600 hover:text-primary-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-primary-500">';
    $html .= '<span>Upload a file</span>';
    
    // Build the input element
    $html .= '<input type="file" name="' . $name . '" id="' . $name . '"';
    
    // Add required attribute if needed
    if ($required) {
        $html .= ' required';
    }
    
    // Add any additional attributes
    foreach ($attributes as $key => $val) {
        $html .= ' ' . $key . '="' . $val . '"';
    }
    
    // Add default Tailwind classes if not specified
    if (!isset($attributes['class'])) {
        $html .= ' class="sr-only"';
    }
    
    $html .= '>';
    $html .= '</label>';
    $html .= '<p class="pl-1">or drag and drop</p>';
    $html .= '</div>';
    $html .= '<p class="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Create a form group with label and input field
 * 
 * @param string $type The input type
 * @param string $name The input name
 * @param string $label The label text
 * @param string $value The input value
 * @param array $attributes Additional input attributes
 * @param bool $required Whether the field is required
 * @param string $error Error message for this field
 * @return string HTML for form group
 */
function form_group($type, $name, $label = '', $value = '', $attributes = [], $required = false, $error = '') {
    $html = '<div class="mb-4">';
    
    // Add the appropriate form element based on type
    switch ($type) {
        case 'textarea':
            $html .= form_textarea($name, $label, $value, $attributes, $required);
            break;
        case 'select':
            // For select, $value should be the options array and $attributes['selected'] should be the selected value
            $selected = $attributes['selected'] ?? '';
            unset($attributes['selected']);
            $html .= form_select($name, $value, $label, $selected, $attributes, $required);
            break;
        case 'file':
            $html .= form_file($name, $label, $attributes, $required);
            break;
        default:
            $html .= form_input($type, $name, $label, $value, $attributes, $required);
    }
    
    // Add error message if provided
    if (!empty($error)) {
        $html .= '<p class="mt-1 text-sm text-red-600">' . $error . '</p>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Create a button with specified type and text
 * 
 * @param string $text The button text
 * @param string $type The button type (button, submit, reset)
 * @param array $attributes Additional button attributes
 * @return string The HTML button
 */
function form_button($text, $type = 'button', $attributes = []) {
    $html = '<button type="' . $type . '"';
    
    // Add any additional attributes
    foreach ($attributes as $key => $val) {
        $html .= ' ' . $key . '="' . $val . '"';
    }
    
    // Add default Tailwind classes if not specified
    if (!isset($attributes['class'])) {
        $html .= ' class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"';
    }
    
    $html .= '>' . $text . '</button>';
    
    return $html;
}

/**
 * Generate pagination links
 * 
 * @param int $total_items Total number of items
 * @param int $items_per_page Number of items per page
 * @param int $current_page Current page number
 * @param string $url_pattern URL pattern with {page} placeholder
 * @return string HTML for pagination links
 */
function pagination($total_items, $items_per_page, $current_page, $url_pattern) {
    $total_pages = ceil($total_items / $items_per_page);
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav class="px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">';
    $html .= '<div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">';
    $html .= '<div>';
    $html .= '<p class="text-sm text-gray-700">';
    $html .= 'Showing <span class="font-medium">' . (($current_page - 1) * $items_per_page + 1) . '</span> to <span class="font-medium">' . min($current_page * $items_per_page, $total_items) . '</span> of <span class="font-medium">' . $total_items . '</span> results';
    $html .= '</p>';
    $html .= '</div>';
    $html .= '<div>';
    $html .= '<nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">';
    
    // Previous page link
    if ($current_page > 1) {
        $prev_url = str_replace('{page}', $current_page - 1, $url_pattern);
        $html .= '<a href="' . $prev_url . '" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">';
        $html .= '<span class="sr-only">Previous</span>';
        $html .= '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">';
        $html .= '<path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />';
        $html .= '</svg>';
        $html .= '</a>';
    } else {
        $html .= '<span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-500">';
        $html .= '<span class="sr-only">Previous</span>';
        $html .= '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">';
        $html .= '<path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />';
        $html .= '</svg>';
        $html .= '</span>';
    }
    
    // Page number links
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    // Show dots if necessary at the beginning
    if ($start_page > 1) {
        $html .= '<a href="' . str_replace('{page}', 1, $url_pattern) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
        if ($start_page > 2) {
            $html .= '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
        }
    }
    
    // Page links
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $html .= '<span aria-current="page" class="z-10 bg-primary-50 border-primary-500 text-primary-600 relative inline-flex items-center px-4 py-2 border text-sm font-medium">' . $i . '</span>';
        } else {
            $html .= '<a href="' . str_replace('{page}', $i, $url_pattern) . '" class="bg-white border-gray-300 text-gray-500 hover:bg-gray-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium">' . $i . '</a>';
        }
    }
    
    // Show dots if necessary at the end
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
        }
        $html .= '<a href="' . str_replace('{page}', $total_pages, $url_pattern) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $total_pages . '</a>';
    }
    
    // Next page link
    if ($current_page < $total_pages) {
        $next_url = str_replace('{page}', $current_page + 1, $url_pattern);
        $html .= '<a href="' . $next_url . '" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">';
        $html .= '<span class="sr-only">Next</span>';
        $html .= '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">';
        $html .= '<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />';
        $html .= '</svg>';
        $html .= '</a>';
    } else {
        $html .= '<span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-500">';
        $html .= '<span class="sr-only">Next</span>';
        $html .= '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">';
        $html .= '<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />';
        $html .= '</svg>';
        $html .= '</span>';
    }
    
    $html .= '