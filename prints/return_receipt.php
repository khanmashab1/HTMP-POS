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

// Get return ID
$return_id = isset($_GET['return_id']) ? intval($_GET['return_id']) : 0;

if ($return_id <= 0) {
    die("Invalid return ID!");
}

// Fetch return details
$return_sql = "SELECT r.*, u.full_name, s.bill_number as original_bill 
               FROM returns r
               LEFT JOIN users u ON r.user_id = u.id
               LEFT JOIN sales s ON r.original_sale_id = s.id
               WHERE r.id = '$return_id'";
$return_result = $conn->query($return_sql);

if ($return_result->num_rows == 0) {
    die("Return not found!");
}

$return = $return_result->fetch_assoc();

// Fetch return items
$items_sql = "SELECT * FROM return_items WHERE return_id = '$return_id'";
$items_result = $conn->query($items_sql);
$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}

// Get store settings
$settings_sql = "SELECT * FROM store_settings WHERE id = 1";
$settings_result = $conn->query($settings_sql);
$settings = $settings_result->num_rows > 0 ? $settings_result->fetch_assoc() : [
    'store_name' => 'ZIC Mart',
    'store_address' => 'ZIC Petrol Pump, Murree Road, Abbottabad',
    'store_phone' => '0313-5881633'
];

// Auto print
if (!isset($_GET['noauto'])) {
    echo '<script>window.onload = function() { setTimeout(() => window.print(), 300); }</script>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Return Receipt - <?php echo $return['return_bill_number']; ?></title>
    <style>
        /* THERMAL RETURN RECEIPT - 80mm */
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
                padding: 0;
            }
            body {
                margin: 0 !important;
                padding: 5px !important;
                width: 80mm !important;
                font-family: 'Courier New', monospace !important;
                font-size: 14px !important;
                font-weight: bold !important;
                line-height: 1.2 !important;
                color: black !important;
                background: white !important;
                -webkit-print-color-adjust: exact !important;
            }
            .no-print { display: none !important; }
        }
        
        body {
            font-family: 'Courier New', monospace;
            width: 80mm;
            margin: 0 auto;
            padding: 5px;
            font-size: 14px;
            font-weight: bold;
            line-height: 1.2;
            color: black;
            background: white;
        }
        
        /* ALL TEXT IS BOLD AND BLACK */
        * {
            font-weight: bold !important;
            color: black !important;
        }
        
        .header {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 3px solid black;
        }
        
        .store-name {
            font-size: 20px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        
        .return-title {
            font-size: 18px;
            color: #d63031;
            margin: 10px 0;
            text-align: center;
            text-transform: uppercase;
        }
        
        .receipt-info {
            border-bottom: 2px solid black;
            padding: 6px 0;
            margin-bottom: 8px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            border-bottom: 1px dotted #333;
            padding-bottom: 3px;
        }
        
        .item-name {
            width: 45%;
        }
        
        .item-qty {
            width: 15%;
            text-align: center;
        }
        
        .item-price {
            width: 20%;
            text-align: right;
        }
        
        .item-total {
            width: 20%;
            text-align: right;
        }
        
        .totals {
            border-top: 3px solid black;
            padding-top: 8px;
            margin-bottom: 8px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        
        .refund-amount {
            border-top: 2px dashed #d63031;
            border-bottom: 2px dashed #d63031;
            padding: 6px 0;
            margin: 6px 0;
            font-size: 16px;
            color: #d63031;
            background: #ffeaa7 !important;
        }
        
        .reason {
            background: #f0f0f0 !important;
            padding: 8px;
            border-radius: 3px;
            margin: 10px 0;
            font-size: 12px;
            border: 1px solid #ddd;
        }
        
        .footer {
            text-align: center;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 2px solid black;
        }
        
        .thank-you {
            font-size: 16px;
            margin: 5px 0;
            color: #d63031;
            text-transform: uppercase;
        }
        
        /* FORCE BLACK IN PRINT */
        @media print {
            body * {
                color: #000000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .refund-amount, .reason {
                background-color: #F0F0F0 !important;
                -webkit-print-color-adjust: exact;
            }
            
            .return-title, .refund-amount, .thank-you {
                color: #d63031 !important;
            }
        }
    </style>
</head>
<body>
    <!-- RETURN RECEIPT -->
    <div class="header">
        <div class="store-name"><?php echo $settings['store_name']; ?></div>
        <div class="store-details"><?php echo $settings['store_address']; ?></div>
        <div class="store-details">TEL: <?php echo $settings['store_phone']; ?></div>
    </div>
    
    <div class="return-title">RETURN RECEIPT</div>
    
    <div class="receipt-info">
        <div class="info-row">
            <span>RETURN BILL:</span>
            <span><?php echo $return['return_bill_number']; ?></span>
        </div>
        <div class="info-row">
            <span>ORIGINAL BILL:</span>
            <span><?php echo $return['original_bill']; ?></span>
        </div>
        <div class="info-row">
            <span>DATE:</span>
            <span><?php echo $return['return_date']; ?></span>
        </div>
        <div class="info-row">
            <span>TIME:</span>
            <span><?php echo $return['return_time']; ?></span>
        </div>
        <div class="info-row">
            <span>PROCESSED BY:</span>
            <span><?php echo $return['full_name']; ?></span>
        </div>
    </div>
    
    <!-- Items -->
    <div style="margin-bottom: 10px;">
        <div class="item-row" style="border-bottom: 2px solid black; padding-bottom: 4px;">
            <div class="item-name"><strong>ITEM</strong></div>
            <div class="item-qty"><strong>QTY</strong></div>
            <div class="item-price"><strong>PRICE</strong></div>
            <div class="item-total"><strong>TOTAL</strong></div>
        </div>
        
        <?php foreach ($items as $item): ?>
        <div class="item-row">
            <div class="item-name"><?php echo substr($item['product_name'], 0, 16); ?></div>
            <div class="item-qty"><?php echo $item['quantity']; ?></div>
            <div class="item-price"><?php echo number_format($item['unit_price'], 2); ?></div>
            <div class="item-total"><?php echo number_format($item['total_price'], 2); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Totals -->
    <div class="totals">
        <div class="total-row refund-amount">
            <span>REFUND AMOUNT:</span>
            <span>Rs. <?php echo number_format($return['total_amount'], 2); ?></span>
        </div>
    </div>
    
    <!-- Reason -->
    <div class="reason">
        <div><strong>REASON:</strong> <?php echo $return['reason']; ?></div>
        <?php if (!empty($return['reason_details'])): ?>
        <div style="margin-top: 5px;"><?php echo $return['reason_details']; ?></div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <div class="thank-you">AMOUNT REFUNDED</div>
        <div style="font-size: 12px; margin: 5px 0;">Return processed successfully</div>
        <div style="margin-top: 5px; font-size: 12px;">Store Copy</div>
        <div style="margin-top: 10px; font-size: 11px;">
            <?php echo $settings['receipt_footer'] ?? 'Thank you!'; ?>
        </div>
    </div>
    
    <!-- Print Controls -->
    <div class="no-print" style="text-align: center; margin-top: 20px; padding: 20px; border-top: 2px solid #ccc;">
        <button onclick="window.print()" style="padding: 10px 20px; margin: 5px; background: #2196F3; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer;">
            🖨️ PRINT RETURN RECEIPT
        </button>
        <a href="../cashier/return_items.php" style="display: inline-block; padding: 10px 20px; margin: 5px; background: #ed8936; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
            🔄 NEW RETURN
        </a>
        <a href="../cashier/pos.php" style="display: inline-block; padding: 10px 20px; margin: 5px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
            🛒 BACK TO POS
        </a>
        
        <p style="margin-top: 15px; font-size: 12px; color: #666;">
            <strong>Receipt will auto-print.</strong> If not, click PRINT button.
        </p>
    </div>
</body>
</html>
<?php $conn->close(); ?>