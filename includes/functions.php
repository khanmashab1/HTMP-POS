<?php
// ============================================
// COMMON FUNCTIONS - ZIC MART POS
// ============================================

// Include config - FIXED PATH
require_once __DIR__ . '/config.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

/**
 * Check if user is cashier
 */
function isCashier() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'cashier';
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /Zic-Mart/login.php");
        exit();
    }
}

/**
 * Redirect to appropriate dashboard based on role
 */
function redirectByRole() {
    if (isAdmin()) {
        header("Location: /Zic-Mart/admin/index.php");
    } elseif (isCashier()) {
        header("Location: /Zic-Mart/cashier/pos.php");
    } else {
        header("Location: /Zic-Mart/login.php");
    }
    exit();
}

/**
 * Generate bill number
 */
function generateBillNumber() {
    global $conn;
    
    $prefix = "ZIC";
    $date = date("Ymd");
    $sequence = 1;
    
    // Get last bill number for today
    $sql = "SELECT bill_number FROM sales WHERE bill_number LIKE '$prefix-$date-%' 
            ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_bill = $row['bill_number'];
        $parts = explode('-', $last_bill);
        $last_seq = end($parts);
        $sequence = intval($last_seq) + 1;
    }
    
    return $prefix . "-" . $date . "-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

/**
 * Format currency (PKR)
 */
function formatCurrency($amount) {
    return "Rs. " . number_format($amount, 2);
}

/**
 * Get store settings from database
 */
function getStoreSettings() {
    global $conn;
    
    $sql = "SELECT * FROM store_settings WHERE id = 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    // Return default settings if not found
    return [
        'store_name' => STORE_NAME,
        'store_address' => STORE_ADDRESS,
        'store_phone' => STORE_PHONE,
        'tax_rate' => 0
    ];
}

/**
 * Escape string for security
 */
function escape($string) {
    global $conn;
    return $conn->real_escape_string($string);
}

/**
 * Show success message
 */
function showSuccess($message) {
    echo '<div class="alert alert-success">' . $message . '</div>';
}

/**
 * Show error message
 */
function showError($message) {
    echo '<div class="alert alert-danger">' . $message . '</div>';
}
?>