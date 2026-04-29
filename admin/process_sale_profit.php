<?php
// Example code for processing sale with profit calculation
require_once '../includes/config.php';
checkLogin();

// When adding items to sale
function addSaleItemWithProfit($sale_id, $product_id, $quantity, $sale_price) {
    global $conn;
    
    // Get product cost price
    $sql = "SELECT cost_price FROM products WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $cost_price = $product['cost_price'];
    
    // Calculate profit for this item
    $profit_per_item = ($sale_price - $cost_price) * $quantity;
    
    // Insert sale item with profit
    $sql = "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, profit) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiidd", $sale_id, $product_id, $quantity, $sale_price, $profit_per_item);
    
    return $stmt->execute();
}

// After completing sale, calculate total profit
function calculateSaleProfit($sale_id) {
    global $conn;
    
    $sql = "SELECT SUM(profit) as total_profit FROM sale_items WHERE sale_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    return $data['total_profit'] ?? 0;
}

// Update sale with total profit
function updateSaleProfit($sale_id, $total_profit) {
    global $conn;
    
    $sql = "INSERT INTO profit_summary (sale_id, date, total_profit) 
            VALUES (?, CURDATE(), ?)
            ON DUPLICATE KEY UPDATE total_profit = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idd", $sale_id, $total_profit, $total_profit);
    
    return $stmt->execute();
}
?>