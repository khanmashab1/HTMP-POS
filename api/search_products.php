<?php
require_once '../includes/config.php';
checkLogin();

header('Content-Type: application/json');

if (isset($_GET['q']) && strlen($_GET['q']) >= 2) {
    $search = $conn->real_escape_string($_GET['q']);
    
    $sql = "SELECT * FROM products 
            WHERE (name LIKE '%$search%' OR barcode LIKE '%$search%') 
            AND is_active = 1 
            ORDER BY name 
            LIMIT 20";
    
    $result = $conn->query($sql);
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    echo json_encode($products);
} else {
    echo json_encode([]);
}

$conn->close();
?>