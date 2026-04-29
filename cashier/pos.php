<?php
require_once '../includes/config.php';
checkLogin();

// Only cashiers should access this page
if ($_SESSION['user_role'] == 'admin') {
    header("Location: ../admin/index.php");
    exit();
}

$message = '';
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

// Handle cart operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
   if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode'])) {

        $barcode = trim($_POST['barcode']);
        
        if (empty($barcode)) {
            $message = "<div class='error-message'>Please enter a barcode</div>";
        } else {
            // Find product using prepared statement
            $stmt = $conn->prepare("SELECT * FROM products WHERE barcode = ? AND is_active = 1");
            $stmt->bind_param("s", $barcode);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();
                
                // Check if already in cart WITHOUT using references
                $found = false;
                foreach ($cart as $key => $item) {
                    if ($item['id'] == $product['id']) {
                        // Update the item directly in the array
                        $cart[$key]['quantity']++;
                        $cart[$key]['total'] = $cart[$key]['quantity'] * $cart[$key]['price'];
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    // Add new item with validated data
                    $cart[] = [
                        'id' => intval($product['id']),
                        'barcode' => htmlspecialchars($product['barcode']),
                        'name' => htmlspecialchars($product['name']),
                        'price' => floatval($product['sale_price']),
                        'quantity' => 1,
                        'total' => floatval($product['sale_price'])
                    ];
                }
                
                $_SESSION['cart'] = $cart;
                $message = "<div class='success-message'>Product added to cart!</div>";
                $stmt->close();
            } else {
                $message = "<div class='error-message'>Product not found!</div>";
            }
        }
    }
    
    elseif (isset($_POST['remove_item'])) {
        $index = intval($_POST['item_index']);
        if (isset($cart[$index])) {
            unset($cart[$index]);
            $cart = array_values($cart); // Reindex array
            $_SESSION['cart'] = $cart;
            $message = "<div class='success-message'>Item removed from cart</div>";
        }
    }
    
    elseif (isset($_POST['update_quantity'])) {
        $index = intval($_POST['item_index']);
        $quantity = intval($_POST['quantity']);
        
        if (isset($cart[$index]) && $quantity > 0) {
            $cart[$index]['quantity'] = $quantity;
            $cart[$index]['total'] = $quantity * $cart[$index]['price'];
            $_SESSION['cart'] = $cart;
            $message = "<div class='success-message'>Quantity updated</div>";
        } elseif ($quantity <= 0) {
            $message = "<div class='error-message'>Quantity must be greater than zero</div>";
        }
    }
    
    elseif (isset($_POST['clear_cart'])) {
        if (!empty($cart)) {
            $_SESSION['cart'] = [];
            $cart = [];
            $message = "<div class='success-message'>Cart cleared</div>";
        }
    }
    
    elseif (isset($_POST['process_sale'])) {
        if (empty($cart)) {
            $message = "<div class='error-message'>Cart is empty!</div>";
        } else {
            $cash_received = floatval($_POST['cash_received']);
            
            // Calculate totals
            $subtotal = 0;
            $total_items = 0;
            
            foreach ($cart as $item) {
                $subtotal += $item['total'];
                $total_items += $item['quantity'];
            }
            
            $tax = $settings['tax_rate'];
            $tax_amount = ($subtotal * $tax) / 100;
            $net_amount = $subtotal + $tax_amount;
            
            if ($cash_received < $net_amount) {
                $message = "<div class='error-message'>Insufficient cash received!</div>";
            } else {
                $change_amount = $cash_received - $net_amount;
                
                // Generate bill number
                $bill_number = generateBillNumber();
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Insert sale with prepared statement
                    $stmt = $conn->prepare("INSERT INTO sales (bill_number, user_id, total_items, total_amount, tax, net_amount, cash_received, change_amount, sale_date, sale_time) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME())");
                    $stmt->bind_param("siiddddd", $bill_number, $_SESSION['user_id'], $total_items, $subtotal, $tax_amount, $net_amount, $cash_received, $change_amount);
                    
                    if ($stmt->execute()) {
                        $sale_id = $conn->insert_id;
                        $stmt->close();
                        
                        // Prepare statements for reuse
                        $item_stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, barcode, quantity, unit_price, total_price) 
                                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $update_stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                        
                        // Insert sale items and update stock
                        foreach ($cart as $item) {
                            $item_stmt->bind_param("iissidd", $sale_id, $item['id'], $item['name'], $item['barcode'], $item['quantity'], $item['price'], $item['total']);
                            $item_stmt->execute();
                            
                            // Update product stock
                            $update_stmt->bind_param("ii", $item['quantity'], $item['id']);
                            $update_stmt->execute();
                        }
                        
                        $item_stmt->close();
                        $update_stmt->close();
                        
                        $conn->commit();
                        
                        // Store sale info for receipt
                        $_SESSION['last_sale'] = [
                            'bill_number' => $bill_number,
                            'total_items' => $total_items,
                            'subtotal' => $subtotal,
                            'tax_amount' => $tax_amount,
                            'net_amount' => $net_amount,
                            'cash_received' => $cash_received,
                            'change_amount' => $change_amount,
                            'cart' => $cart
                        ];
                        
                        // Clear cart
                        $_SESSION['cart'] = [];
                        
                        // Redirect to receipt
                        header("Location: ../prints/receipt.php?bill=" . urlencode($bill_number));
                        exit();
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Sale processing error: " . $e->getMessage());
                    $message = "<div class='error-message'>Error processing sale. Please try again.</div>";
                }
            }
        }
    }
}

// Calculate cart totals
$subtotal = 0;
$total_items = 0;
foreach ($cart as $item) {
    $subtotal += $item['total'];
    $total_items += $item['quantity'];
}
$tax_amount = ($subtotal * $settings['tax_rate']) / 100;
$net_amount = $subtotal + $tax_amount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - <?php echo htmlspecialchars($settings['store_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f4f8; }
        
        .pos-container {
            display: grid;
            grid-template-columns: 70% 30%;
            height: 100vh;
        }
        
        /* Left Panel - Products & Cart */
        .left-panel { padding: 20px; }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .store-info h1 { color: #2d3748; font-size: 24px; }
        .store-info p { color: #718096; font-size: 14px; }
        
        .user-info {
            text-align: right;
        }
        .user-name { font-weight: bold; color: #2d3748; }
        .logout-btn {
            background: #fc8181;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            margin-top: 5px;
        }
        
        /* Product Search */
        .search-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .search-form { display: flex; gap: 10px; }
        .barcode-input {
            flex: 1;
            padding: 12px;
            border: 2px solid #cbd5e0;
            border-radius: 5px;
            font-size: 16px;
        }
        .barcode-input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-scan {
            background: #48bb78;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        .btn-scan:hover { background: #38a169; }
        
        .manual-search-btn {
            background: #4299e1;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            width: 100%;
        }
        
        /* Cart Items */
        .cart-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: calc(100vh - 250px);
            overflow-y: auto;
        }
        
        .cart-header {
            padding: 15px 20px;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
            font-weight: bold;
            color: #2d3748;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 50px;
            gap: 10px;
        }
        
        .cart-items {
            padding: 0;
        }
        
        .cart-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 50px;
            gap: 10px;
            align-items: center;
        }
        
        .cart-item:nth-child(even) {
            background: #f9fafb;
        }
        
        .item-name { font-weight: bold; }
        .item-price { color: #4a5568; }
        .item-total { color: #2d3748; font-weight: bold; }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .qty-input {
            width: 60px;
            padding: 5px;
            text-align: center;
            border: 1px solid #cbd5e0;
            border-radius: 3px;
        }
        
        .btn-update {
            background: #4299e1;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-remove {
            background: #fc8181;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }
        
        /* Right Panel - Totals & Payment */
        .right-panel {
            background: #2d3748;
            color: white;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        
        .totals-container {
            flex: 1;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #4a5568;
        }
        
        .total-label { color: #cbd5e0; }
        .total-value { font-weight: bold; }
        
        .net-total {
            font-size: 24px;
            color: #48bb78;
        }
        
        /* Payment Section */
        .payment-container {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #4a5568;
        }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #cbd5e0; }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .change-display {
            background: #4a5568;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .change-label { color: #cbd5e0; font-size: 14px; }
        .change-value {
            font-size: 28px;
            color: #48bb78;
            font-weight: bold;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .btn-primary {
            background: #48bb78;
            color: white;
            padding: 15px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .btn-secondary {
            background: #4299e1;
            color: white;
            padding: 15px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        
        .btn-danger {
            background: #fc8181;
            color: white;
            padding: 15px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        
        .keyboard-shortcuts {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #4a5568;
            font-size: 12px;
            color: #cbd5e0;
        }
        
        .shortcut { display: flex; justify-content: space-between; margin-bottom: 5px; }
        
        /* Message Styles */
        .message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 300px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .success-message {
            background: #c6f6d5 !important;
            color: #22543d !important;
            padding: 15px 20px !important;
            border-radius: 8px !important;
            margin-bottom: 10px !important;
            border-left: 4px solid #38a169 !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
        }
        
        .error-message {
            background: #fed7d7 !important;
            color: #c53030 !important;
            padding: 15px 20px !important;
            border-radius: 8px !important;
            margin-bottom: 10px !important;
            border-left: 4px solid #e53e3e !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
        }
        
        /* Modal for manual search */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1001;
        }
        
        .modal-content {
            background: white;
            width: 500px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 10px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close-modal {
            background: #fc8181;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 50%;
            cursor: pointer;
        }
        
        #searchResults {
            max-height: 300px;
            overflow-y: auto;
            margin-top: 10px;
        }
        
        .search-result-item {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .search-result-item:hover {
            background: #f7fafc;
        }
        
        .header-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .return-btn {
            background: #ed8936 !important;
            color: white !important;
            padding: 8px 15px !important;
            border-radius: 5px !important;
            text-decoration: none !important;
            display: inline-block !important;
            font-size: 14px !important;
        }
        
        .return-btn:hover {
            background: #dd6b20 !important;
        }
    </style>
</head>
<body>
    <?php if ($message): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <div class="pos-container">
        <!-- Left Panel -->
        <div class="left-panel">
            <div class="header">
                <div class="store-info">
                    <h1><?php echo htmlspecialchars($settings['store_name']); ?> - POS</h1>
                    <p>ZIC Petrol Pump, Murree Road, Abbottabad</p>
                </div>
                
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?> (Cashier)</div>
                    <div class="header-buttons">
                        <a href="return_items.php" class="return-btn">
                            🔄 Return Items
                        </a>
                        <a href="../logout.php" class="logout-btn">Logout</a>
                    </div>
                </div>
            </div>
            
            <!-- Product Search -->
            <div class="search-container">
                <form method="POST" action="" class="search-form">
                    <input type="text" 
                           name="barcode" 
                           class="barcode-input" 
                           placeholder="Scan barcode or enter manually..." 
                           autofocus
                           required>
                    <button type="submit" name="add_item" class="btn-scan">Add Item</button>
                </form>
                
                <button type="button" class="manual-search-btn" onclick="openSearchModal()">
                    🔍 Manual Product Search
                </button>
            </div>
            
            <!-- Cart Items -->
            <div class="cart-container">
                <div class="cart-header">
                    <div>Product Name</div>
                    <div>Price</div>
                    <div>Quantity</div>
                    <div>Total</div>
                    <div></div>
                </div>
                
                <div class="cart-items">
                    <?php if (empty($cart)): ?>
                        <div class="empty-cart">
                            <div style="font-size: 48px; margin-bottom: 10px;">🛒</div>
                            <div>Cart is empty</div>
                            <div style="font-size: 12px; margin-top: 5px;">Scan items to add to cart</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($cart as $index => $item): ?>
                        <div class="cart-item">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-price"><?php echo formatCurrency($item['price']); ?></div>
                            <div class="quantity-control">
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="item_index" value="<?php echo $index; ?>">
                                    <input type="number" 
                                           name="quantity" 
                                           value="<?php echo $item['quantity']; ?>" 
                                           min="1" 
                                           class="qty-input"
                                           onchange="this.form.submit()">
                                    <input type="hidden" name="update_quantity">
                                </form>
                            </div>
                            <div class="item-total"><?php echo formatCurrency($item['total']); ?></div>
                            <div>
                                <form method="POST" action="">
                                    <input type="hidden" name="item_index" value="<?php echo $index; ?>">
                                    <button type="submit" name="remove_item" class="btn-remove" onclick="return confirm('Remove this item from cart?')">×</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Panel -->
        <div class="right-panel">
            <div class="totals-container">
                <h2 style="margin-bottom: 20px;">Bill Summary</h2>
                
                <div class="total-row">
                    <span class="total-label">Items:</span>
                    <span class="total-value"><?php echo $total_items; ?></span>
                </div>
                
                <div class="total-row">
                    <span class="total-label">Subtotal:</span>
                    <span class="total-value"><?php echo formatCurrency($subtotal); ?></span>
                </div>
                
                <div class="total-row">
                    <span class="total-label">Tax (<?php echo $settings['tax_rate']; ?>%):</span>
                    <span class="total-value"><?php echo formatCurrency($tax_amount); ?></span>
                </div>
                
                <div class="total-row" style="border-bottom: none;">
                    <span class="total-label">Net Total:</span>
                    <span class="total-value net-total"><?php echo formatCurrency($net_amount); ?></span>
                </div>
            </div>
            
            <div class="payment-container">
                <form method="POST" action="" id="paymentForm">
                    <div class="form-group">
                        <label for="cash_received">Cash Received (Rs.)</label>
                        <input type="number" 
                               step="0.01" 
                               id="cash_received" 
                               name="cash_received" 
                               value="<?php echo number_format($net_amount, 2); ?>"
                               required
                               oninput="calculateChange()">
                    </div>
                    
                    <div class="change-display">
                        <div class="change-label">Change</div>
                        <div class="change-value" id="changeAmount">Rs. 0.00</div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" name="process_sale" class="btn-primary">
                            💳 Process Sale (F2)
                        </button>
                        
                        <button type="button" class="btn-secondary" onclick="window.print()">
                            🖨️ Print Bill
                        </button>
                    </div>
                    
                    <button type="submit" name="clear_cart" class="btn-danger" onclick="return confirm('Clear all items from cart?')">
                        ❌ Clear Cart (F4)
                    </button>
                </form>
                
                <div class="keyboard-shortcuts">
                    <div class="shortcut"><span>Enter</span><span>Add Item</span></div>
                    <div class="shortcut"><span>F2</span><span>Process Sale</span></div>
                    <div class="shortcut"><span>F4</span><span>Clear Cart</span></div>
                    <div class="shortcut"><span>Esc</span><span>Cancel Sale</span></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Manual Search Modal -->
    <div id="searchModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Manual Product Search</h2>
                <button class="close-modal" onclick="closeSearchModal()">×</button>
            </div>
            
            <input type="text" 
                   id="productSearch" 
                   placeholder="Type product name..." 
                   style="width: 100%; padding: 10px; margin-bottom: 10px;"
                   onkeyup="searchProducts(this.value)">
            
            <div id="searchResults"></div>
        </div>
    </div>
    
    <script>
        // Auto-focus barcode input
        document.addEventListener('DOMContentLoaded', function() {
            const barcodeInput = document.querySelector('.barcode-input');
            if (barcodeInput) barcodeInput.focus();
            
            // Form validation
            const paymentForm = document.getElementById('paymentForm');
            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    const submitter = e.submitter;
                    
                    if (submitter.name === 'process_sale') {
                        const cashReceived = parseFloat(document.getElementById('cash_received').value) || 0;
                        const netAmount = <?php echo $net_amount; ?>;
                        
                        if (cashReceived < netAmount) {
                            e.preventDefault();
                            alert('Insufficient cash received! Please enter more cash.');
                            document.getElementById('cash_received').focus();
                            document.getElementById('cash_received').select();
                        }
                    }
                    
                    if (submitter.name === 'clear_cart') {
                        if (!confirm('Are you sure you want to clear all items from the cart?')) {
                            e.preventDefault();
                        }
                    }
                });
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'Enter':
    // Allow Enter to submit when barcode input is focused
    if (e.target.classList.contains('barcode-input')) {
        return; // ✅ allow form submit
    }

    // Prevent Enter elsewhere
    if (!e.target.matches('.qty-input') && !e.target.matches('#productSearch')) {
        e.preventDefault();
    }
    break;

                    break;
                case 'F2':
                    e.preventDefault();
                    if (<?php echo !empty($cart) ? 'true' : 'false'; ?>) {
                        document.querySelector('[name="process_sale"]').click();
                    } else {
                        alert('Cart is empty!');
                    }
                    break;
                case 'F4':
                    e.preventDefault();
                    document.querySelector('[name="clear_cart"]').click();
                    break;
                case 'Escape':
                    e.preventDefault();
                    const modal = document.getElementById('searchModal');
                    if (modal.style.display === 'block') {
                        closeSearchModal();
                    } else {
                        if (confirm('Cancel this sale and clear cart?')) {
                            document.querySelector('[name="clear_cart"]').click();
                        }
                    }
                    break;
            }
        });
        
        // Calculate change
        function calculateChange() {
            const cashReceived = parseFloat(document.getElementById('cash_received').value) || 0;
            const netAmount = <?php echo $net_amount; ?>;
            const change = cashReceived - netAmount;
            
            const changeElement = document.getElementById('changeAmount');
            if (change < 0) {
                changeElement.style.color = '#fc8181';
                changeElement.textContent = 'Rs. ' + Math.abs(change).toFixed(2) + ' Due';
            } else {
                changeElement.style.color = '#48bb78';
                changeElement.textContent = 'Rs. ' + change.toFixed(2);
            }
        }
        
        // Initialize change calculation
        calculateChange();
        
        // Manual search modal
        function openSearchModal() {
            document.getElementById('searchModal').style.display = 'block';
            document.getElementById('productSearch').focus();
            document.getElementById('productSearch').value = '';
            document.getElementById('searchResults').innerHTML = '';
        }
        
        function closeSearchModal() {
            document.getElementById('searchModal').style.display = 'none';
            document.getElementById('searchResults').innerHTML = '';
            document.querySelector('.barcode-input').focus();
        }
        
        // Search products via AJAX
        function searchProducts(query) {
            if (query.length < 2) {
                document.getElementById('searchResults').innerHTML = '<div style="padding: 20px; text-align: center; color: #718096;">Type at least 2 characters</div>';
                return;
            }
            
            fetch(`../api/search_products.php?q=${encodeURIComponent(query)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    let html = '';
                    if (data.length > 0) {
                        data.forEach(product => {
                            html += `
                                <div class="search-result-item" onclick="addProductToCart('${product.barcode.replace(/'/g, "\\'")}')">
                                    <strong>${product.name.replace(/</g, "&lt;").replace(/>/g, "&gt;")}</strong><br>
                                    <small>Barcode: ${product.barcode} | Price: ${formatCurrency(product.sale_price)} | Stock: ${product.stock_quantity}</small>
                                </div>
                            `;
                        });
                    } else {
                        html = '<div style="padding: 20px; text-align: center; color: #718096;">No products found</div>';
                    }
                    document.getElementById('searchResults').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('searchResults').innerHTML = '<div style="padding: 20px; text-align: center; color: #c53030;">Error searching products</div>';
                });
        }
        
        // Format currency helper
        function formatCurrency(amount) {
            return 'Rs. ' + parseFloat(amount).toFixed(2);
        }
        
        function addProductToCart(barcode) {
            closeSearchModal();
            
            // Set barcode value and submit form
            const barcodeInput = document.querySelector('.barcode-input');
            if (barcodeInput) {
                barcodeInput.value = barcode;
                
                // Submit the form
                const form = barcodeInput.closest('form');
                if (form) {
                    form.submit();
                }
            }
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('searchModal');
            if (event.target == modal) {
                closeSearchModal();
            }
        }
        
        // Auto-hide message after 5 seconds
        setTimeout(function() {
            const message = document.querySelector('.message');
            if (message) {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.remove();
                    }
                }, 500);
            }
        }, 5000);
        
       // Auto-clear barcode input after successful scan (POS-safe)
document.addEventListener('DOMContentLoaded', function () {
    const barcodeInput = document.querySelector('.barcode-input');

    if (!barcodeInput) return;

    // If the page reloaded after POST, clear input immediately
    barcodeInput.value = '';
    barcodeInput.focus();
});


    </script>
</body>
</html>
<?php 
// Close connection if it exists
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>