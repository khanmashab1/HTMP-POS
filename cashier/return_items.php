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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Only cashiers should access this page
if ($_SESSION['user_role'] == 'admin') {
    header("Location: ../admin/index.php");
    exit();
}

$message = '';
$success = false;
$sale_items = [];
$sale_info = null;
$selected_product = null;

// Handle bill number scan (via GET or POST)
if (isset($_GET['scan_bill']) || isset($_POST['scan_bill'])) {
    $bill_number = isset($_POST['bill_number']) ? $conn->real_escape_string($_POST['bill_number']) : 
                   (isset($_GET['bill']) ? $conn->real_escape_string($_GET['bill']) : '');
    
    if (!empty($bill_number)) {
        // Fetch sale details
        $sale_sql = "SELECT s.*, u.full_name FROM sales s 
                     LEFT JOIN users u ON s.user_id = u.id 
                     WHERE s.bill_number = '$bill_number'";
        $sale_result = $conn->query($sale_sql);
        
        if ($sale_result->num_rows > 0) {
            $sale_info = $sale_result->fetch_assoc();
            
            // Fetch all items from this sale
            $items_sql = "SELECT si.*, p.name as product_name, p.barcode, 
                         (SELECT COALESCE(SUM(ri.quantity), 0) 
                          FROM return_items ri 
                          JOIN returns r ON ri.return_id = r.id 
                          WHERE r.original_sale_id = s.id AND ri.product_id = si.product_id) as already_returned_qty
                         FROM sale_items si
                         LEFT JOIN products p ON si.product_id = p.id
                         LEFT JOIN sales s ON si.sale_id = s.id
                         WHERE si.sale_id = '{$sale_info['id']}'";
            $items_result = $conn->query($items_sql);
            
            while ($item = $items_result->fetch_assoc()) {
                $remaining_qty = $item['quantity'] - $item['already_returned_qty'];
                if ($remaining_qty > 0) {
                    $item['remaining_qty'] = $remaining_qty;
                    $sale_items[] = $item;
                }
            }
            
            if (count($sale_items) > 0) {
                $message = "<div class='success'>Found bill: <strong>$bill_number</strong> with " . count($sale_items) . " returnable item(s)</div>";
            } else {
                $message = "<div class='error'>All items from bill $bill_number have already been returned or no items found.</div>";
            }
        } else {
            $message = "<div class='error'>Bill number not found: $bill_number</div>";
        }
    }
}

// Handle product selection
if (isset($_POST['select_product'])) {
    $bill_number = $conn->real_escape_string($_POST['bill_number']);
    $product_id = intval($_POST['product_id']);
    
    // Get sale info
    $sale_sql = "SELECT s.* FROM sales s WHERE s.bill_number = '$bill_number'";
    $sale_result = $conn->query($sale_sql);
    
    if ($sale_result->num_rows > 0) {
        $sale_info = $sale_result->fetch_assoc();
        
        // Get the specific product from sale with remaining quantity
        $product_sql = "SELECT si.*, p.name as product_name, p.barcode, 
                       (SELECT COALESCE(SUM(ri.quantity), 0) 
                        FROM return_items ri 
                        JOIN returns r ON ri.return_id = r.id 
                        WHERE r.original_sale_id = si.sale_id AND ri.product_id = si.product_id) as already_returned_qty
                       FROM sale_items si
                       LEFT JOIN products p ON si.product_id = p.id
                       WHERE si.sale_id = '{$sale_info['id']}' AND si.product_id = '$product_id'";
        $product_result = $conn->query($product_sql);
        
        if ($product_result->num_rows > 0) {
            $selected_product = $product_result->fetch_assoc();
            $selected_product['remaining_qty'] = $selected_product['quantity'] - $selected_product['already_returned_qty'];
            
            if ($selected_product['remaining_qty'] <= 0) {
                $message = "<div class='error'>This product has already been fully returned!</div>";
                $selected_product = null;
            }
        }
        
        // Get all items for display
        $items_sql = "SELECT si.*, p.name as product_name, p.barcode,
                     (SELECT COALESCE(SUM(ri.quantity), 0) 
                      FROM return_items ri 
                      JOIN returns r ON ri.return_id = r.id 
                      WHERE r.original_sale_id = si.sale_id AND ri.product_id = si.product_id) as already_returned_qty
                     FROM sale_items si
                     LEFT JOIN products p ON si.product_id = p.id
                     WHERE si.sale_id = '{$sale_info['id']}'";
        $items_result = $conn->query($items_sql);
        
        while ($item = $items_result->fetch_assoc()) {
            $remaining_qty = $item['quantity'] - $item['already_returned_qty'];
            if ($remaining_qty > 0) {
                $item['remaining_qty'] = $remaining_qty;
                $sale_items[] = $item;
            }
        }
    }
}

// Handle return form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_return'])) {
    $bill_number = $conn->real_escape_string($_POST['bill_number']);
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $reason = $conn->real_escape_string($_POST['reason']);
    $reason_details = $conn->real_escape_string($_POST['reason_details'] ?? '');
    
    // Validate inputs
    if (empty($bill_number) || $product_id <= 0 || $quantity <= 0) {
        $message = "<div class='error'>Please fill all required fields correctly!</div>";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get sale info
            $sale_sql = "SELECT * FROM sales WHERE bill_number = '$bill_number'";
            $sale_result = $conn->query($sale_sql);
            
            if ($sale_result->num_rows == 0) {
                throw new Exception("Sale not found!");
            }
            
            $sale = $sale_result->fetch_assoc();
            
            // Get product details
            $product_sql = "SELECT * FROM products WHERE id = '$product_id'";
            $product_result = $conn->query($product_sql);
            
            if ($product_result->num_rows == 0) {
                throw new Exception("Product not found!");
            }
            
            $product = $product_result->fetch_assoc();
            
            // Get sale item
            $item_sql = "SELECT si.*, 
                        (SELECT COALESCE(SUM(ri.quantity), 0) 
                         FROM return_items ri 
                         JOIN returns r ON ri.return_id = r.id 
                         WHERE r.original_sale_id = si.sale_id AND ri.product_id = si.product_id) as already_returned_qty
                        FROM sale_items si 
                        WHERE si.sale_id = '{$sale['id']}' AND si.product_id = '$product_id'";
            $item_result = $conn->query($item_sql);
            
            if ($item_result->num_rows == 0) {
                throw new Exception("Product not found in sale!");
            }
            
            $sale_item = $item_result->fetch_assoc();
            
            // Calculate remaining quantity
            $remaining_qty = $sale_item['quantity'] - $sale_item['already_returned_qty'];
            
            // Check quantity
            if ($quantity > $remaining_qty) {
                throw new Exception("Return quantity cannot exceed remaining quantity ($remaining_qty units left)!");
            }
            
            // Generate unique return bill number
            // First, get the next sequence number for this original bill
            $sequence_sql = "SELECT COUNT(*) + 1 as next_seq FROM returns 
                            WHERE original_sale_id = '{$sale['id']}'";
            $sequence_result = $conn->query($sequence_sql);
            $sequence = $sequence_result->fetch_assoc()['next_seq'];
            
            // Format sequence as 3-digit number
            $sequence_formatted = str_pad($sequence, 3, '0', STR_PAD_LEFT);
            
            // Create return bill number: RET-{original_bill}-{sequence}
            $return_bill_number = "RET-" . $bill_number . "-" . $sequence_formatted;
            
            // Check if this return bill number already exists (just in case)
            $check_sql = "SELECT COUNT(*) as count FROM returns WHERE return_bill_number = '$return_bill_number'";
            $check_result = $conn->query($check_sql);
            $exists = $check_result->fetch_assoc()['count'] > 0;
            
            if ($exists) {
                // If somehow duplicate, add timestamp
                $return_bill_number = "RET-" . $bill_number . "-" . $sequence_formatted . "-" . time();
            }
            
            // Calculate total amount
            $return_amount = $sale_item['unit_price'] * $quantity;
            
            // Check if reason_details column exists
            $check_column_sql = "SHOW COLUMNS FROM returns LIKE 'reason_details'";
            $column_result = $conn->query($check_column_sql);
            
            if ($column_result->num_rows > 0) {
                // Column exists, include it in the insert
                $return_sql = "INSERT INTO returns 
                              (original_sale_id, return_bill_number, user_id, total_items, total_amount, reason, reason_details, return_date, return_time) 
                              VALUES 
                              ('{$sale['id']}', '$return_bill_number', '{$_SESSION['user_id']}', '$quantity', 
                              '$return_amount', '$reason', '$reason_details', CURDATE(), CURTIME())";
            } else {
                // Column doesn't exist, insert without it
                $return_sql = "INSERT INTO returns 
                              (original_sale_id, return_bill_number, user_id, total_items, total_amount, reason, return_date, return_time) 
                              VALUES 
                              ('{$sale['id']}', '$return_bill_number', '{$_SESSION['user_id']}', '$quantity', 
                              '$return_amount', '$reason', CURDATE(), CURTIME())";
            }
            
            if (!$conn->query($return_sql)) {
                throw new Exception("Error creating return record: " . $conn->error);
            }
            
            $return_id = $conn->insert_id;
            
            // Create return item record
            $return_item_sql = "INSERT INTO return_items 
                               (return_id, product_id, product_name, barcode, quantity, unit_price, total_price) 
                               VALUES 
                               ('$return_id', '{$product['id']}', '{$product['name']}', '{$product['barcode']}', 
                               '$quantity', '{$sale_item['unit_price']}', '$return_amount')";
            
            if (!$conn->query($return_item_sql)) {
                throw new Exception("Error creating return item: " . $conn->error);
            }
            
            // Update product stock
            $update_stock_sql = "UPDATE products SET stock_quantity = stock_quantity + $quantity 
                                WHERE id = '{$product['id']}'";
            
            if (!$conn->query($update_stock_sql)) {
                throw new Exception("Error updating stock: " . $conn->error);
            }
            
            // Update sale totals
            $update_sale_sql = "UPDATE sales 
                               SET total_items = total_items - $quantity, 
                                   total_amount = total_amount - $return_amount,
                                   net_amount = net_amount - $return_amount
                               WHERE id = '{$sale['id']}'";
            
            if (!$conn->query($update_sale_sql)) {
                throw new Exception("Error updating sale totals: " . $conn->error);
            }
            
            // Commit transaction
            $conn->commit();
            
            $success = true;
            $message = "<div class='success'>
                <h3>✅ RETURN PROCESSED SUCCESSFULLY!</h3>
                <p><strong>Return Bill:</strong> $return_bill_number</p>
                <p><strong>Original Bill:</strong> $bill_number</p>
                <p><strong>Product:</strong> {$product['name']}</p>
                <p><strong>Barcode:</strong> {$product['barcode']}</p>
                <p><strong>Quantity Returned:</strong> $quantity</p>
                <p><strong>Amount Refunded:</strong> Rs. " . number_format($return_amount, 2) . "</p>
                <p><strong>Reason:</strong> $reason</p>";
            
            if (!empty($reason_details)) {
                $message .= "<p><strong>Additional Notes:</strong> $reason_details</p>";
            }
            
            $message .= "<p><strong>Processed by:</strong> {$_SESSION['full_name']}</p>
                <p><strong>Time:</strong> " . date('H:i:s') . "</p>
                <br>
                <a href='../prints/return_receipt.php?return_id=$return_id' class='print-btn' target='_blank'>
                    🖨️ Print Return Receipt
                </a>
                <br><br>
                <form method='POST' style='display: inline;'>
                    <input type='hidden' name='bill_number' value='$bill_number'>
                    <button type='submit' name='scan_bill' class='nav-btn'>
                        🔄 Return Another Item from Same Bill
                    </button>
                </form>
            </div>";
            
            // Clear selected product
            $selected_product = null;
            $sale_items = [];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $message = "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Get store settings
$settings_sql = "SELECT * FROM store_settings WHERE id = 1";
$settings_result = $conn->query($settings_sql);
$settings = $settings_result->num_rows > 0 ? $settings_result->fetch_assoc() : [
    'store_name' => 'ZIC Mart',
    'store_address' => 'ZIC Petrol Pump, Murree Road, Abbottabad'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Returns - <?php echo $settings['store_name']; ?></title>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            padding: 25px 30px;
        }
        
        .store-info h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .store-info p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .nav-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .nav-btn {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .content {
            padding: 30px;
        }
        
        .message {
            margin-bottom: 25px;
            padding: 20px;
            border-radius: 10px;
        }
        
        .success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #fc8181;
        }
        
        .print-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 20px;
            background: #4299e1;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
        }
        
        /* Bill Scan Section */
        .scan-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px dashed #cbd5e0;
        }
        
        .scan-title {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .scan-form {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .scan-input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid #cbd5e0;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            letter-spacing: 1px;
        }
        
        .scan-input:focus {
            outline: none;
            border-color: #ed8936;
            box-shadow: 0 0 0 3px rgba(237, 137, 54, 0.1);
        }
        
        .btn-scan {
            padding: 15px 30px;
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-scan:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(237, 137, 54, 0.3);
        }
        
        .scan-hint {
            font-size: 14px;
            color: #718096;
            text-align: center;
            margin-top: 10px;
        }
        
        /* Sale Info Display */
        .sale-info {
            background: #e6fffa;
            border: 1px solid #81e6d9;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            display: <?php echo $sale_info ? 'block' : 'none'; ?>;
        }
        
        .sale-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #81e6d9;
        }
        
        .sale-title {
            font-size: 18px;
            color: #234e52;
            font-weight: 600;
        }
        
        .sale-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            padding: 10px;
            background: white;
            border-radius: 8px;
            border: 1px solid #b2f5ea;
        }
        
        .detail-label {
            font-size: 12px;
            color: #718096;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: #234e52;
        }
        
        /* Items Grid */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .item-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .item-card:hover {
            border-color: #ed8936;
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .item-card.selected {
            border-color: #ed8936;
            background: #fffaf0;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .item-name {
            font-weight: 600;
            color: #2d3748;
            font-size: 16px;
        }
        
        .item-barcode {
            font-size: 12px;
            color: #718096;
            background: #f7fafc;
            padding: 3px 8px;
            border-radius: 4px;
        }
        
        .item-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .item-detail {
            font-size: 14px;
        }
        
        .detail-label-small {
            color: #718096;
            font-size: 12px;
            margin-bottom: 2px;
        }
        
        .detail-value-small {
            color: #2d3748;
            font-weight: 500;
        }
        
        .select-btn {
            width: 100%;
            margin-top: 15px;
            padding: 10px;
            background: #ed8936;
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .select-btn:hover {
            background: #dd6b20;
        }
        
        /* Return Form */
        .return-form-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            border: 2px solid #e2e8f0;
            display: <?php echo $selected_product ? 'block' : 'none'; ?>;
        }
        
        .form-title {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .product-selected {
            background: #e6fffa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #81e6d9;
        }
        
        .selected-product-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
            font-size: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #cbd5e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #ed8936;
            box-shadow: 0 0 0 3px rgba(237, 137, 54, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .qty-btn {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid #cbd5e0;
            background: white;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .qty-input {
            width: 100px;
            padding: 12px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            border: 2px solid #cbd5e0;
            border-radius: 8px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex: 1;
        }
        
        .btn-process {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }
        
        .btn-process:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(72, 187, 120, 0.3);
        }
        
        .btn-cancel {
            background: #718096;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #4a5568;
            transform: translateY(-2px);
        }
        
        /* Recent Returns */
        .recent-section {
            margin-top: 40px;
            background: white;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #e2e8f0;
        }
        
        .section-title {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .returns-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .returns-table th {
            background: #f7fafc;
            padding: 12px 15px;
            text-align: left;
            color: #4a5568;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .returns-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #2d3748;
        }
        
        .no-data {
            text-align: center;
            padding: 30px;
            color: #a0aec0;
            font-size: 16px;
        }
        
        /* Toast Notification Styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
        }
        
        .toast {
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out, fadeOut 0.3s ease-in 9.7s;
            animation-fill-mode: forwards;
            transform: translateX(120%);
            opacity: 0;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .toast.success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            border-left: 4px solid #2f855a;
        }
        
        .toast.error {
            background: linear-gradient(135deg, #f56565 0%, #c53030 100%);
            color: white;
            border-left: 4px solid #9b2c2c;
        }
        
        .toast.warning {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            border-left: 4px solid #9c4221;
        }
        
        .toast.info {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            border-left: 4px solid #2b6cb0;
        }
        
        .toast-icon {
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .toast-content {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
        }
        
        .toast-close {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .toast-close:hover {
            opacity: 1;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 0 0 10px 10px;
            overflow: hidden;
        }
        
        .toast-progress::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 100%;
            background: rgba(255, 255, 255, 0.5);
            animation: progress 10s linear;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(120%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(120%);
                opacity: 0;
            }
        }
        
        @keyframes progress {
            from {
                transform: translateX(-100%);
            }
            to {
                transform: translateX(0);
            }
        }
        
        /* Toast Actions */
        .toast-actions {
            display: flex;
            gap: 8px;
            margin-left: 10px;
        }
        
        .toast-btn {
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            font-size: 12px;
            border: none;
            transition: all 0.2s;
        }
        
        .toast-btn-confirm {
            background: white;
            color: #dd6b20;
        }
        
        .toast-btn-confirm:hover {
            background: #f7fafc;
            transform: translateY(-1px);
        }
        
        .toast-btn-cancel {
            background: transparent;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .toast-btn-cancel:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                margin: 10px;
            }
            
            .header {
                padding: 20px;
            }
            
            .content {
                padding: 20px;
            }
            
            .scan-form {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .items-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .toast-container {
                left: 20px;
                right: 20px;
                max-width: none;
            }
            
            .toast {
                padding: 14px 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="store-info">
                <h1><?php echo $settings['store_name']; ?> - Smart Returns</h1>
                <p>Scan bill barcode or enter bill number to process returns</p>
            </div>
            
            <div class="nav-buttons">
                <a href="pos.php" class="nav-btn">← Back to POS</a>
                <a href="#" class="nav-btn" onclick="window.print()">🖨️ Print</a>
                <a href="#" class="nav-btn" onclick="location.reload()">🔄 Refresh</a>
            </div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Bill Scan Section -->
            <div class="scan-section">
                <h2 class="scan-title">📄 Scan Bill Barcode</h2>
                
                <form method="POST" action="" class="scan-form" id="scanForm">
                    <input type="text" 
                           name="bill_number" 
                           class="scan-input" 
                           id="billNumberInput"
                           value="<?php echo isset($bill_number) ? htmlspecialchars($bill_number) : ''; ?>"
                           placeholder="Scan bill barcode or enter bill number (e.g., ZIC-20251216-0001)"
                           required 
                           autofocus>
                    
                    <button type="submit" name="scan_bill" class="btn-scan">
                        🔍 Find Bill
                    </button>
                </form>
                
                <div class="scan-hint">
                    💡 Tip: Scan the barcode from the original receipt or manually enter the bill number
                </div>
            </div>
            
            <!-- Sale Information (Shown when bill is found) -->
            <?php if ($sale_info && count($sale_items) > 0): ?>
            <div class="sale-info" id="saleInfo">
                <div class="sale-header">
                    <div class="sale-title">
                        📋 Bill Found: <?php echo $sale_info['bill_number']; ?>
                    </div>
                    <div style="color: #718096; font-size: 14px;">
                        Date: <?php echo $sale_info['sale_date'] . ' ' . $sale_info['sale_time']; ?>
                    </div>
                </div>
                
                <div class="sale-details">
                    <div class="detail-item">
                        <div class="detail-label">Cashier</div>
                        <div class="detail-value"><?php echo isset($sale_info['full_name']) ? $sale_info['full_name'] : 'Unknown'; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Total Items</div>
                        <div class="detail-value"><?php echo isset($sale_info['total_items']) ? $sale_info['total_items'] : 0; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Net Amount</div>
                        <div class="detail-value">Rs. <?php echo isset($sale_info['net_amount']) ? number_format($sale_info['net_amount'], 2) : '0.00'; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Payment Method</div>
                        <div class="detail-value"><?php echo isset($sale_info['payment_method']) ? strtoupper($sale_info['payment_method']) : 'CASH'; ?></div>
                    </div>
                </div>
                
                <h3 style="margin: 20px 0 15px; color: #2d3748;">Select Product to Return:</h3>
                
                <div class="items-grid" id="itemsGrid">
                    <?php foreach ($sale_items as $item): ?>
                    <div class="item-card <?php echo ($selected_product && $selected_product['product_id'] == $item['product_id']) ? 'selected' : ''; ?>"
                         data-product-id="<?php echo $item['product_id']; ?>"
                         data-product-name="<?php echo htmlspecialchars($item['product_name']); ?>"
                         data-barcode="<?php echo $item['barcode']; ?>"
                         data-quantity="<?php echo $item['remaining_qty']; ?>"
                         data-price="<?php echo $item['unit_price']; ?>">
                        
                        <div class="item-header">
                            <div class="item-name"><?php echo $item['product_name']; ?></div>
                            <div class="item-barcode"><?php echo $item['barcode']; ?></div>
                        </div>
                        
                        <div class="item-details">
                            <div class="item-detail">
                                <div class="detail-label-small">Original Qty</div>
                                <div class="detail-value-small"><?php echo $item['quantity']; ?></div>
                            </div>
                            <div class="item-detail">
                                <div class="detail-label-small">Already Returned</div>
                                <div class="detail-value-small"><?php echo $item['quantity'] - $item['remaining_qty']; ?></div>
                            </div>
                            <div class="item-detail">
                                <div class="detail-label-small">Price</div>
                                <div class="detail-value-small">Rs. <?php echo number_format($item['unit_price'], 2); ?></div>
                            </div>
                            <div class="item-detail">
                                <div class="detail-label-small">Available</div>
                                <div class="detail-value-small"><?php echo $item['remaining_qty']; ?> units</div>
                            </div>
                        </div>
                        
                        <form method="POST" action="" style="margin-top: 15px;">
                            <input type="hidden" name="bill_number" value="<?php echo $sale_info['bill_number']; ?>">
                            <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                            <button type="submit" name="select_product" class="select-btn">
                                ✅ Select for Return
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php elseif ($sale_info && count($sale_items) == 0): ?>
                <div class="no-data" style="text-align: center; padding: 30px; color: #718096;">
                    All items from this bill have been returned.
                </div>
            <?php endif; ?>
            
            <!-- Return Form (Shown when product is selected) -->
            <?php if ($selected_product): ?>
            <div class="return-form-section" id="returnForm">
                <h2 class="form-title">🔄 Process Return</h2>
                
                <div class="product-selected">
                    <h3 style="margin-bottom: 15px; color: #234e52;">Selected Product:</h3>
                    <div class="selected-product-info">
                        <div>
                            <div class="detail-label-small">Product Name</div>
                            <div class="detail-value-small" style="font-weight: 600;"><?php echo $selected_product['product_name']; ?></div>
                        </div>
                        <div>
                            <div class="detail-label-small">Barcode</div>
                            <div class="detail-value-small"><?php echo $selected_product['barcode']; ?></div>
                        </div>
                        <div>
                            <div class="detail-label-small">Original Qty</div>
                            <div class="detail-value-small"><?php echo $selected_product['quantity']; ?> units</div>
                        </div>
                        <div>
                            <div class="detail-label-small">Already Returned</div>
                            <div class="detail-value-small"><?php echo $selected_product['quantity'] - $selected_product['remaining_qty']; ?> units</div>
                        </div>
                        <div>
                            <div class="detail-label-small">Remaining Qty</div>
                            <div class="detail-value-small" style="color: #38a169; font-weight: 600;"><?php echo $selected_product['remaining_qty']; ?> units</div>
                        </div>
                        <div>
                            <div class="detail-label-small">Unit Price</div>
                            <div class="detail-value-small">Rs. <?php echo number_format($selected_product['unit_price'], 2); ?></div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="" id="processReturnForm">
                    <input type="hidden" name="bill_number" value="<?php echo $sale_info['bill_number']; ?>">
                    <input type="hidden" name="product_id" value="<?php echo $selected_product['product_id']; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Quantity to Return *</label>
                            <div class="quantity-control">
                                <button type="button" class="qty-btn" onclick="changeQuantity(-1)">-</button>
                                <input type="number" 
                                       name="quantity" 
                                       id="quantityInput" 
                                       class="qty-input" 
                                       value="1" 
                                       min="1" 
                                       max="<?php echo $selected_product['remaining_qty']; ?>" 
                                       required>
                                <button type="button" class="qty-btn" onclick="changeQuantity(1)">+</button>
                                <div style="color: #718096; font-size: 14px;">
                                    Max: <?php echo $selected_product['remaining_qty']; ?> units
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reason" class="form-label">Reason for Return *</label>
                            <select id="reason" name="reason" class="form-control" required>
                                <option value="">Select Reason</option>
                                <option value="Defective Product">Defective Product</option>
                                <option value="Wrong Item">Wrong Item</option>
                                <option value="Customer Changed Mind">Customer Changed Mind</option>
                                <option value="Expired Product">Expired Product</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reason_details" class="form-label">Additional Notes (Optional)</label>
                        <textarea id="reason_details" 
                                  name="reason_details" 
                                  class="form-control" 
                                  placeholder="Any additional details about the return..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" id="processReturnBtn" class="btn btn-process">
                            ✅ Process Return
                        </button>
                        <button type="button" class="btn btn-cancel" onclick="showCancelToast()">
                            ❌ Cancel
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Recent Returns -->
            <div class="recent-section">
                <h3 class="section-title">📜 Recent Returns (Last 10)</h3>
                <?php
                // Get recent returns for this cashier
                $recent_sql = "SELECT r.return_bill_number, r.return_date, r.return_time, 
                                      r.total_amount, p.name as product_name, r.total_items as quantity
                               FROM returns r
                               INNER JOIN return_items ri ON r.id = ri.return_id
                               INNER JOIN products p ON ri.product_id = p.id
                               WHERE r.user_id = '{$_SESSION['user_id']}'
                               ORDER BY r.id DESC 
                               LIMIT 10";
                
                $recent_result = $conn->query($recent_sql);
                
                if ($recent_result->num_rows > 0):
                ?>
                <table class="returns-table">
                    <thead>
                        <tr>
                            <th>Return Bill</th>
                            <th>Date & Time</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($return = $recent_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $return['return_bill_number']; ?></td>
                            <td><?php echo $return['return_date'] . ' ' . $return['return_time']; ?></td>
                            <td><?php echo substr($return['product_name'], 0, 20); ?></td>
                            <td><?php echo $return['quantity']; ?></td>
                            <td>Rs. <?php echo number_format($return['total_amount'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">No returns processed yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        // Toast Notification System
        class Toast {
            static show(message, type = 'info', duration = 10000) {
                const container = document.getElementById('toastContainer');
                if (!container) return;
                
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                
                // Get icon based on type
                const icons = {
                    success: '✅',
                    error: '❌',
                    warning: '⚠️',
                    info: 'ℹ️'
                };
                
                toast.innerHTML = `
                    <span class="toast-icon">${icons[type] || icons.info}</span>
                    <span class="toast-content">${message}</span>
                    <button class="toast-close" onclick="this.parentElement.remove()">×</button>
                    <div class="toast-progress"></div>
                `;
                
                container.appendChild(toast);
                
                // Remove toast after duration
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, duration);
                
                return toast;
            }
            
            static confirm(message, onConfirm, onCancel, duration = 10000) {
                const container = document.getElementById('toastContainer');
                if (!container) return;
                
                const toast = document.createElement('div');
                toast.className = 'toast warning';
                
                toast.innerHTML = `
                    <span class="toast-icon">⚠️</span>
                    <span class="toast-content">${message}</span>
                    <div class="toast-actions">
                        <button class="toast-btn toast-btn-confirm" onclick="this.closest('.toast').confirmCallback()">
                            Yes, Process Return
                        </button>
                        <button class="toast-btn toast-btn-cancel" onclick="this.closest('.toast').cancelCallback()">
                            Cancel
                        </button>
                    </div>
                    <div class="toast-progress"></div>
                `;
                
                // Store callbacks on the element
                toast.confirmCallback = () => {
                    if (onConfirm) onConfirm();
                    toast.remove();
                };
                
                toast.cancelCallback = () => {
                    if (onCancel) onCancel();
                    toast.remove();
                };
                
                container.appendChild(toast);
                
                // Auto-remove after duration
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                        if (onCancel) onCancel();
                    }
                }, duration);
                
                return toast;
            }
        }

        // JavaScript functions
        function changeQuantity(change) {
            const quantityInput = document.getElementById('quantityInput');
            if (!quantityInput) return;
            
            let currentValue = parseInt(quantityInput.value);
            const maxValue = parseInt(quantityInput.max);
            const minValue = parseInt(quantityInput.min);
            
            let newValue = currentValue + change;
            
            if (newValue < minValue) {
                newValue = minValue;
                Toast.show('Minimum quantity is 1', 'warning', 5000);
            }
            if (newValue > maxValue) {
                newValue = maxValue;
                Toast.show(`Maximum quantity is ${maxValue}`, 'warning', 5000);
            }
            
            quantityInput.value = newValue;
        }
        
        function showCancelToast() {
            Toast.confirm(
                'Are you sure you want to cancel this return?',
                () => {
                    Toast.show('Return cancelled', 'warning', 5000);
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                },
                () => {
                    Toast.show('Return not cancelled', 'info', 3000);
                }
            );
        }
        
        function submitReturnForm() {
            // Create a hidden submit button
            const form = document.getElementById('processReturnForm');
            if (!form) return;
            
            // Create a hidden submit button
            const submitBtn = document.createElement('button');
            submitBtn.type = 'submit';
            submitBtn.name = 'process_return';
            submitBtn.style.display = 'none';
            
            // Add it to the form and click it
            form.appendChild(submitBtn);
            submitBtn.click();
        }
        
        // Auto-focus functionality
        document.addEventListener('DOMContentLoaded', function() {
            const billInput = document.getElementById('billNumberInput');
            if (billInput) {
                billInput.focus();
                billInput.select();
            }
            
            // Listen for Enter key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.id === 'billNumberInput') {
                    e.preventDefault();
                    Toast.show('Searching for bill...', 'info', 3000);
                    document.getElementById('scanForm').submit();
                }
            });
            
            // Auto-submit if bill number is in URL
            const urlParams = new URLSearchParams(window.location.search);
            const billFromUrl = urlParams.get('bill');
            if (billFromUrl && !document.getElementById('saleInfo').style.display) {
                document.getElementById('billNumberInput').value = billFromUrl;
                setTimeout(() => {
                    Toast.show('Auto-searching for bill...', 'info', 3000);
                    document.getElementById('scanForm').submit();
                }, 500);
            }
            
            // Add click event to process return button
            const processReturnBtn = document.getElementById('processReturnBtn');
            if (processReturnBtn) {
                processReturnBtn.addEventListener('click', function() {
                    const quantity = document.getElementById('quantityInput').value;
                    const reason = document.getElementById('reason').value;
                    const quantityNum = parseInt(quantity);
                    const maxQuantity = parseInt(document.getElementById('quantityInput').max);
                    
                    // Validation
                    if (!reason) {
                        Toast.show('Please select a reason for return', 'error', 5000);
                        document.getElementById('reason').focus();
                        document.getElementById('reason').style.borderColor = '#f56565';
                        setTimeout(() => {
                            document.getElementById('reason').style.borderColor = '';
                        }, 3000);
                        return false;
                    }
                    
                    if (quantityNum <= 0) {
                        Toast.show('Quantity must be at least 1', 'error', 5000);
                        document.getElementById('quantityInput').focus();
                        document.getElementById('quantityInput').style.borderColor = '#f56565';
                        setTimeout(() => {
                            document.getElementById('quantityInput').style.borderColor = '';
                        }, 3000);
                        return false;
                    }
                    
                    if (quantityNum > maxQuantity) {
                        Toast.show(`Cannot exceed maximum quantity of ${maxQuantity}`, 'error', 5000);
                        document.getElementById('quantityInput').focus();
                        document.getElementById('quantityInput').style.borderColor = '#f56565';
                        setTimeout(() => {
                            document.getElementById('quantityInput').style.borderColor = '';
                        }, 3000);
                        return false;
                    }
                    
                    // Show confirmation toast
                    Toast.confirm(
                        `Process return of ${quantity} unit(s)? This action cannot be undone.`,
                        () => {
                            // Show processing toast
                            Toast.show('Processing return...', 'info', 3000);
                            
                            // Submit the form
                            submitReturnForm();
                        },
                        () => {
                            Toast.show('Return cancelled', 'warning', 3000);
                        }
                    );
                });
            }
            
            // Success message toast if PHP shows success
            <?php if ($success): ?>
                setTimeout(() => {
                    Toast.show('Return processed successfully!', 'success', 10000);
                }, 500);
            <?php endif; ?>
            
            // Error message toast if PHP shows error
            <?php if ($message && !$success): ?>
                setTimeout(() => {
                    Toast.show('<?php echo addslashes(strip_tags($message)); ?>', 'error', 10000);
                }, 500);
            <?php endif; ?>
            
            // Highlight invalid form fields
            const formControls = document.querySelectorAll('.form-control, .qty-input');
            formControls.forEach(control => {
                control.addEventListener('input', function() {
                    if (this.style.borderColor === 'rgb(245, 101, 101)') {
                        this.style.borderColor = '';
                    }
                });
                
                control.addEventListener('change', function() {
                    if (this.style.borderColor === 'rgb(245, 101, 101)') {
                        this.style.borderColor = '';
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>