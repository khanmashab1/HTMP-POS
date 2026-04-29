<?php
session_start();

// Database configuration
$host = "localhost";
$user = "root";
$pass = "";
$db = "zic_mart_pos";

// Create connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");

// Get store settings
$settings = [];
$settings_sql = "SELECT * FROM store_settings WHERE id = 1";
$settings_result = $conn->query($settings_sql);
if ($settings_result->num_rows > 0) {
    $settings = $settings_result->fetch_assoc();
} else {
    $settings = [
        'store_name' => 'ZIC Mart',
        'store_address' => 'ZIC Petrol Pump, Murree Road, Abbottabad',
        'store_phone' => '0313-5881633',
        'tax_rate' => 0
    ];
}

// Check if user is logged in (for protected pages)
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
}

// Check if user is admin
function checkAdmin() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
        header("Location: ../cashier/pos.php");
        exit();
    }
}

// Format currency
function formatCurrency($amount) {
    $amount = $amount ?? 0; // convert NULL to 0
    return 'Rs. ' . number_format((float)$amount, 2);
}

// Generate bill number
function generateBillNumber() {
    global $conn;
    $prefix = "ZIC";
    $date = date("Ymd");
    $sequence = 1;
    
    $sql = "SELECT bill_number FROM sales WHERE bill_number LIKE '$prefix-$date-%' ORDER BY id DESC LIMIT 1";
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
?>