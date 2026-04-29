<?php
session_start();
header('Content-Type: application/json');

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "zic_mart_pos";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

// Get parameters
$bill_number = isset($_GET['bill_number']) ? $conn->real_escape_string($_GET['bill_number']) : '';
$barcode = isset($_GET['barcode']) ? $conn->real_escape_string($_GET['barcode']) : '';

if (empty($bill_number) || empty($barcode)) {
    die(json_encode(['success' => false, 'message' => 'Bill number and barcode required']));
}

try {
    // Check if sale exists
    $sale_sql = "SELECT s.* FROM sales s 
                 INNER JOIN sale_items si ON s.id = si.sale_id 
                 WHERE s.bill_number = '$bill_number' 
                 AND si.barcode = '$barcode'";
    $sale_result = $conn->query($sale_sql);
    
    if ($sale_result->num_rows == 0) {
        die(json_encode(['success' => false, 'message' => 'Bill or product not found']));
    }
    
    $sale = $sale_result->fetch_assoc();
    
    // Get product details
    $product_sql = "SELECT * FROM products WHERE barcode = '$barcode'";
    $product_result = $conn->query($product_sql);
    
    if ($product_result->num_rows == 0) {
        die(json_encode(['success' => false, 'message' => 'Product not found']));
    }
    
    $product = $product_result->fetch_assoc();
    
    // Get sale item details
    $item_sql = "SELECT * FROM sale_items 
                 WHERE sale_id = '{$sale['id']}' 
                 AND product_id = '{$product['id']}'";
    $item_result = $conn->query($item_sql);
    
    if ($item_result->num_rows == 0) {
        die(json_encode(['success' => false, 'message' => 'Product not found in this bill']));
    }
    
    $sale_item = $item_result->fetch_assoc();
    
    // Return success with data
    echo json_encode([
        'success' => true,
        'message' => 'Valid for return',
        'data' => [
            'sale' => [
                'id' => $sale['id'],
                'bill_number' => $sale['bill_number'],
                'date' => $sale['sale_date'],
                'time' => $sale['sale_time']
            ],
            'product' => [
                'id' => $product['id'],
                'name' => $product['name'],
                'barcode' => $product['barcode'],
                'sale_price' => $product['sale_price']
            ],
            'sale_item' => [
                'quantity' => $sale_item['quantity'],
                'unit_price' => $sale_item['unit_price'],
                'total_price' => $sale_item['total_price']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>