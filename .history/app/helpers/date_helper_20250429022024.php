<?php
/**
 * Date Helper
 * 
 * A collection of helper functions for date and time manipulation
 * for the Digital Service Billing Mobile App
 */

// Prevent direct access to this file
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Format a date to the application's standard format
 * 
 * @param string|int $date Date string or timestamp
 * @param string $format Custom format (optional)
 * @return string Formatted date
 */
function format_date($date, $format = 'Y-m-d') {
    if (is_numeric($date)) {
        return date($format, $date);
    }
    
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format a datetime to the application's standard format
 * 
 * @param string|int $datetime Date string or timestamp
 * @param string $format Custom format (optional)
 * @return string Formatted datetime
 */
function format_datetime($datetime, $format = 'Y-m-d H:i:s') {
    return format_date($datetime, $format);
}

/**
 * Get current date in the specified format
 * 
 * @param string $format Date format (optional)
 * @return string Current date
 */
function current_date($format = 'Y-m-d') {
    return date($format);
}

/**
 * Get current datetime in the specified format
 * 
 * @param string $format Datetime format (optional)
 * @return string Current datetime
 */
function current_datetime($format = 'Y-m-d H:i:s') {
    return date($format);
}

/**
 * Calculate the difference between two dates in the specified unit
 * 
 * @param string|int $date1 First date string or timestamp
 * @param string|int $date2 Second date string or timestamp (defaults to current time)
 * @param string $unit Unit of measurement (days, months, years, hours, minutes, seconds)
 * @return int Difference in the specified unit
 */
function date_difference($date1, $date2 = 'now', $unit = 'days') {
    if (!is_numeric($date1)) {
        $date1 = strtotime($date1);
    }
    
    if (!is_numeric($date2)) {
        $date2 = strtotime($date2);
    }
    
    $diff = abs($date2 - $date1);
    
    switch ($unit) {
        case 'years':
            return floor($diff / (365 * 24 * 60 * 60));
        case 'months':
            return floor($diff / (30 * 24 * 60 * 60));
        case 'days':
            return floor($diff / (24 * 60 * 60));
        case 'hours':
            return floor($diff / (60 * 60));
        case 'minutes':
            return floor($diff / 60);
        case 'seconds':
            return $diff;
        default:
            return floor($diff / (24 * 60 * 60));
    }
}

/**
 * Add a specified interval to a date
 * 
 * @param string|int $date Date string or timestamp
 * @param int $value Number of units to add
 * @param string $unit Unit (days, months, years, hours, minutes, seconds)
 * @param string $format Return format (optional)
 * @return string Modified date
 */
function date_add($date, $value, $unit = 'days', $format = 'Y-m-d') {
    if (!is_numeric($date)) {
        $date = strtotime($date);
    }
    
    switch ($unit) {
        case 'years':
            $interval = 'P' . $value . 'Y';
            break;
        case 'months':
            $interval = 'P' . $value . 'M';
            break;
        case 'days':
            $interval = 'P' . $value . 'D';
            break;
        case 'hours':
            $interval = 'PT' . $value . 'H';
            break;
        case 'minutes':
            $interval = 'PT' . $value . 'M';
            break;
        case 'seconds':
            $interval = 'PT' . $value . 'S';
            break;
        default:
            $interval = 'P' . $value . 'D';
    }
    
    $date_obj = new DateTime('@' . $date);
    $date_obj->add(new DateInterval($interval));
    
    return $date_obj->format($format);
}

/**
 * Subtract a specified interval from a date
 * 
 * @param string|int $date Date string or timestamp
 * @param int $value Number of units to subtract
 * @param string $unit Unit (days, months, years, hours, minutes, seconds)
 * @param string $format Return format (optional)
 * @return string Modified date
 */
function date_subtract($date, $value, $unit = 'days', $format = 'Y-m-d') {
    return date_add($date, -$value, $unit, $format);
}

/**
 * Check if a date is in the past
 * 
 * @param string|int $date Date string or timestamp
 * @return bool True if date is in the past
 */
function is_past_date($date) {
    if (!is_numeric($date)) {
        $date = strtotime($date);
    }
    
    return $date < time();
}

/**
 * Check if a date is in the future
 * 
 * @param string|int $date Date string or timestamp
 * @return bool True if date is in the future
 */
function is_future_date($date) {
    if (!is_numeric($date)) {
        $date = strtotime($date);
    }
    
    return $date > time();
}

/**
 * Check if a date is today
 * 
 * @param string|int $date Date string or timestamp
 * @return bool True if date is today
 */
function is_today($date) {
    if (!is_numeric($date)) {
        $date = strtotime($date);
    }
    
    return date('Y-m-d', $date) === date('Y-m-d');
}

/**
 * Generate an array of dates between two dates
 * 
 * @param string|int $start_date Start date
 * @param string|int $end_date End date
 * @param string $format Return format (optional)
 * @return array Array of dates
 */
function date_range($start_date, $end_date, $format = 'Y-m-d') {
    if (!is_numeric($start_date)) {
        $start_date = strtotime($start_date);
    }
    
    if (!is_numeric($end_date)) {
        $end_date = strtotime($end_date);
    }
    
    $dates = [];
    $current = $start_date;
    
    while ($current <= $end_date) {
        $dates[] = date($format, $current);
        $current = strtotime('+1 day', $current);
    }
    
    return $dates;
}

/**
 * Convert a date to MySQL format
 * 
 * @param string|int $date Date string or timestamp
 * @return string MySQL formatted date
 */
function mysql_date($date) {
    return format_date($date, 'Y-m-d');
}

/**
 * Convert a datetime to MySQL format
 * 
 * @param string|int $datetime Date string or timestamp
 * @return string MySQL formatted datetime
 */
function mysql_datetime($datetime) {
    return format_date($datetime, 'Y-m-d H:i:s');
}

/**
 * Get the first day of the month for a given date
 * 
 * @param string|int $date Date string or timestamp (optional, defaults to current date)
 * @param string $format Return format (optional)
 * @return string First day of the month
 */
function first_day_of_month($date = null, $format = 'Y-m-d') {
    if (is_null($date)) {
        $date = time();
    } elseif (!is_numeric($date)) {
        $date = strtotime($date);
    }
    
    return date($format, strtotime(date('Y-m-01', $date)));
}

/**
 * Get the last day of the month for a given date
 * 
 * @param string|int $date Date string or timestamp (optional, defaults to current date)
 * @param string $format Return format (optional)
 * @return string Last day of the month
 */
function last_day_of_month($date = null, $format = 'Y-m-d') {
    if (is_null($date)) {
        $date = time();
    } elseif (!is_numeric($date)) {
        $date = strtotime($date);
    }
    
    return date($format, strtotime(date('Y-m-t', $date)));
}

/**
 * Format a timestamp or date string to a human-readable relative time
 * (e.g., "2 days ago", "in 3 hours", "just now")
 * 
 * @param string|int $date Date string or timestamp
 * @return string Human-readable relative time
 */
function time_elapsed($date) {
    if (!is_numeric($date)) {
        $date = strtotime($date);
    }
    
    $diff = time() - $date;
    
    if ($diff < 0) {
        $diff = abs($diff);
        $prefix = 'in ';
        $suffix = '';
    } else {
        $prefix = '';
        $suffix = ' ago';
    }
    
    $units = [
        365 * 24 * 60 * 60 => 'year',
        30 * 24 * 60 * 60 => 'month',
        24 * 60 * 60 => 'day',
        60 * 60 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];
    
    if ($diff < 5) {
        return 'just now';
    }
    
    foreach ($units as $unit => $text) {
        if ($diff >= $unit) {
            $value = floor($diff / $unit);
            return $prefix . $value . ' ' . $text . ($value > 1 ? 's' : '') . $suffix;
        }
    }
}

/**
 * Get the name of the month
 * 
 * @param int $month_num Month number (1-12)
 * @param bool $short Whether to return the short name (3 letters)
 * @return string Month name
 */
function month_name($month_num, $short = false) {
    $month_num = intval($month_num);
    
    if ($month_num < 1 || $month_num > 12) {
        return false;
    }
    
    $format = $short ? 'M' : 'F';
    return date($format, mktime(0, 0, 0, $month_num, 1));
}

/**
 * Get the name of the day of the week
 * 
 * @param string|int $date Date string or timestamp
 * @param bool $short Whether to return the short name (3 letters)
 * @return string Day name
 */
function day_name($date, $short = false) {
    if (!is_numeric($date)) {
        $date = strtotime($date);
    }
    
    $format = $short ? 'D' : 'l';
    return date($format, $date);
}

/**
 * Check if a year is a leap year
 * 
 * @param int $year Year to check (defaults to current year)
 * @return bool True if it's a leap year
 */
function is_leap_year($year = null) {
    if (is_null($year)) {
        $year = date('Y');
    }
    
    return ((($year % 4) == 0) && ((($year % 100) != 0) || (($year % 400) == 0)));
}

/**
 * Calculate age from a birthdate
 * 
 * @param string|int $birthdate Birth date string or timestamp
 * @return int Age in years
 */
function calculate_age($birthdate) {
    if (!is_numeric($birthdate)) {
        $birthdate = strtotime($birthdate);
    }
    
    $birth_year = date('Y', $birthdate);
    $birth_month = date('m', $birthdate);
    $birth_day = date('d', $birthdate);
    
    $current_year = date('Y');
    $current_month = date('m');
    $current_day = date('d');
    
    $age = $current_year - $birth_year;
    
    if ($current_month < $birth_month || 
        ($current_month == $birth_month && $current_day < $birth_day)) {
        $age--;
    }
    
    return $age;
}

/**
 * Get quarter number from a date
 * 
 * @param string|int $date Date string or timestamp (defaults to current date)
 * @return int Quarter number (1-4)
 */
function get_quarter($date = null) {
    if (is_null($date)) {
        $date = time();
    } elseif (!is_numeric($date)) {
        $date = strtotime($date);
    }
    
    $month = date('n', $date);
    return ceil($month / 3);
}

/**
 * Get date range for a quarter
 * 
 * @param int $quarter Quarter number (1-4)
 * @param int $year Year (defaults to current year)
 * @param string $format Date format (optional)
 * @return array Array with start_date and end_date
 */
function quarter_range($quarter, $year = null, $format = 'Y-m-d') {
    if (is_null($year)) {
        $year = date('Y');
    }
    
    if ($quarter < 1 || $quarter > 4) {
        return false;
    }
    
    $start_month = (($quarter - 1) * 3) + 1;
    $end_month = $start_month + 2;
    
    $start_date = date($format, mktime(0, 0, 0, $start_month, 1, $year));
    $end_date = date($format, mktime(23, 59, 59, $end_month, date('t', mktime(0, 0, 0, $end_month, 1, $year)), $year));
    
    return [
        'start_date' => $start_date,
        'end_date' => $end_date
    ];
}

/**
 * Format a timestamp as ISO 8601 (useful for APIs)
 * 
 * @param string|int $date Date string or timestamp (defaults to current time)
 * @return string ISO 8601 formatted date
 */
function iso_date($date = null) {
    if (is_null($date)) {
        $date = time();
    } elseif (!is_numeric($date)) {
        $date = strtotime($date);
    }
    
    return date('c', $date);
}