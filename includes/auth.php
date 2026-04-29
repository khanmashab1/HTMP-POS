<?php
session_start();

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "zic_mart_pos";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

function isCashier() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'cashier';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: ../cashier/pos.php");
        exit();
    }
}

function redirectByRole() {
    if (isAdmin()) {
        header("Location: admin/index.php");
    } elseif (isCashier()) {
        header("Location: cashier/pos.php");
    } else {
        header("Location: login.php");
    }
    exit();
}

// Get store settings
function getStoreSettings($conn) {
    $sql = "SELECT * FROM store_settings WHERE id = 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return [
        'store_name' => 'ZIC Mart',
        'store_address' => 'ZIC Petrol Pump, Murree Road, Abbottabad',
        'store_phone' => '0313-5881633',
        'tax_rate' => 0
    ];
}

// Format currency
function formatCurrency($amount) {
    return "Rs. " . number_format($amount, 2);
}

// Escape string
function escape($conn, $string) {
    return $conn->real_escape_string($string);
}
?>