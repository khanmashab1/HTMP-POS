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

// Get bill number from GET parameter
$bill_number = isset($_GET['bill']) ? $_GET['bill'] : '';

if (empty($bill_number)) {
    die("Error: Bill number is required!");
}

// Fetch sale details from database
$sql = "SELECT s.*, u.full_name 
        FROM sales s 
        LEFT JOIN users u ON s.user_id = u.id 
        WHERE s.bill_number = '" . $conn->real_escape_string($bill_number) . "'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Error: Bill '$bill_number' not found!");
}

$sale = $result->fetch_assoc();

// Fetch sale items
$items_sql = "SELECT si.*, p.name as product_name 
              FROM sale_items si
              LEFT JOIN products p ON si.product_id = p.id
              WHERE si.sale_id = '{$sale['id']}'";
$items_result = $conn->query($items_sql);
$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}

// Get store settings
$settings_sql = "SELECT * FROM store_settings WHERE id = 1";
$settings_result = $conn->query($settings_sql);
if ($settings_result->num_rows > 0) {
    $settings = $settings_result->fetch_assoc();
} else {
    $settings = [
        'store_name' => 'ZIC Mart',
        'store_address' => 'ZIC Petrol Pump, Murree Road, Abbottabad',
        'store_phone' => '0313-5881633',
        'tax_rate' => 0,
        'receipt_footer' => 'Thank you for shopping!'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo $bill_number; ?></title>
    <style>
        /* ============================================
           ZIC MART – THERMAL RECEIPT (80mm)
           ============================================ */

        /* ---------- RECEIPT WRAPPER (CENTERING FIX) ---------- */

        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }

            body {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                font-family: "Courier New", monospace !important;
                font-size: 14px !important;
                font-weight: 700 !important;
                line-height: 1.25 !important;
                color: #000 !important;
                background: #fff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            * {
                color: #000 !important;
                background: #fff !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            .back-button {
                display: none !important;
            }
        }

        /* SCREEN */
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            background: #fff;
            font-family: "Courier New", monospace;
        }

        /* ✅ THIS IS THE KEY */
        .receipt-wrapper {
            width: 80mm;              /* FULL PAPER WIDTH */
            margin: 0 auto;           /* CENTER IT */
            padding: 4mm 3mm;         /* CONTROL WHITE SPACE */
            box-sizing: border-box;
        }

        .receipt-end {
            page-break-after: always;
        }

        /* HEADER */
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .store-name {
            font-size: 20px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .store-address,
        .store-phone {
            font-size: 12px;
            font-weight: 700;
        }

        /* INFO */
        .receipt-info {
            border-bottom: 1px dashed #000;
            padding: 6px 0;
            margin-bottom: 8px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
        }

        /* ITEMS */
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            border-bottom: 2px solid #000;
            font-size: 12px;
            font-weight: 900;
            text-align: left;
            padding: 3px 0;
        }

        .items-table td {
            font-size: 12px;
            font-weight: 700;
            padding: 3px 0;
            border-bottom: 1px dotted #444;
        }

        .item-name-col { width: 45%; }
        .item-qty-col  { width: 15%; text-align: center; }
        .item-price-col,
        .item-total-col { width: 20%; text-align: right; }

        /* TOTALS */
        .totals-section {
            border-top: 2px solid #000;
            margin-top: 6px;
            padding-top: 6px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            font-weight: 900;
        }

        .net-total-row {
            border-top: 2px dashed #000;
            border-bottom: 2px dashed #000;
            padding: 6px 0;
            margin: 6px 0;
            font-size: 16px;
            font-weight: 900;
        }

        /* PAYMENT */
        .payment-section {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 6px 0;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
        }

        /* BARCODE */
        .barcode {
            text-align: center;
            margin: 8px 0;
        }

        .barcode-text {
            font-family: 'Libre Barcode 39', cursive;
            font-size: 40px;
        }

        .barcode-number {
            font-size: 12px;
            font-weight: 700;
            margin-top: 5px;
        }

        /* FOOTER */
        .receipt-footer {
            text-align: center;
            margin-top: 10px;
            border-top: 1px dashed #000;
            padding-top: 6px;
        }

        .thank-you {
            font-size: 14px;
            font-weight: 900;
        }

        /* BACK BUTTON - SHOW ON SCREEN, HIDE WHEN PRINTING */
        .back-button {
            text-align: center;
            margin: 20px auto;
            padding: 15px 30px;
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: block;
            text-decoration: none;
            max-width: 200px;
        }

        .back-button:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(237, 137, 54, 0.3);
        }

        /* INFO TEXT */
        .info-label {
            font-weight: 900;
        }

        .info-value {
            font-weight: 700;
        }

        /* TOTAL LABELS */
        .total-label {
            font-weight: 900;
        }

        .total-value {
            font-weight: 700;
        }

        .net-total-label {
            font-weight: 900;
        }

        .net-total-value {
            font-weight: 900;
        }

        /* FOOTER TEXT */
        .footer-text {
            font-size: 12px;
            font-weight: 700;
            margin: 3px 0;
        }

        .visit-again {
            font-size: 12px;
            font-weight: 700;
            margin: 3px 0;
        }

        /* CHANGE ROW */
        .change-row {
            font-weight: 900;
        }
    </style>
    
    <!-- Barcode font -->
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap" rel="stylesheet">
    
    <script>
        // Print receipt when page loads
        window.onload = function () {
            // Small delay to ensure everything is loaded
            setTimeout(function() {
                window.print();
            }, 500);
        };

        // Redirect back to admin sales report after printing
        window.onafterprint = function () {
            // Check if we have a referrer
            if (document.referrer && document.referrer.includes('sales_report')) {
                // Go back to the referrer page
                setTimeout(function() {
                    window.location.href = document.referrer;
                }, 500);
            } else {
                // Default to admin sales report
                setTimeout(function() {
                    window.location.href = '../admin/sales_report.php';
                }, 500);
            }
        };

        // Manual back button click handler
        function goBack() {
            if (document.referrer && document.referrer.includes('sales_report')) {
                window.location.href = document.referrer;
            } else {
                window.location.href = '../admin/sales_report.php';
            }
        }
    </script>
</head>
<body>

    <!-- BACK BUTTON (SHOWS ON SCREEN, HIDES WHEN PRINTING) -->
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button class="back-button" onclick="goBack()">
            ← Back to Sales Report
        </button>
    </div>

    <!-- RECEIPT WRAPPER (THIS CENTERS THE BILL) -->
    <div class="receipt-wrapper">

        <!-- HEADER -->
        <div class="receipt-header">
            <div class="store-name"><?php echo htmlspecialchars($settings['store_name']); ?></div>
            <div class="store-address"><?php echo htmlspecialchars($settings['store_address']); ?></div>
            <div class="store-phone">TEL: <?php echo htmlspecialchars($settings['store_phone']); ?></div>
        </div>

        <!-- RECEIPT INFO -->
        <div class="receipt-info">
            <div class="info-row">
                <span class="info-label">BILL NO:</span>
                <span class="info-value"><?php echo htmlspecialchars($bill_number); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">DATE:</span>
                <span class="info-value"><?php echo date('d-m-Y', strtotime($sale['sale_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">TIME:</span>
                <span class="info-value"><?php echo htmlspecialchars($sale['sale_time']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">CASHIER:</span>
                <span class="info-value"><?php echo htmlspecialchars($sale['full_name'] ?? $_SESSION['full_name']); ?></span>
            </div>
        </div>

        <!-- BARCODE -->
        <div class="barcode">
            <div class="barcode-text">*<?php echo str_replace('-', '', $bill_number); ?>*</div>
            <div class="barcode-number"><?php echo htmlspecialchars($bill_number); ?></div>
        </div>

        <!-- ITEMS TABLE -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="item-name-col">ITEM</th>
                    <th class="item-qty-col">QTY</th>
                    <th class="item-price-col">PRICE</th>
                    <th class="item-total-col">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $subtotal = 0;
                foreach ($items as $item):
                    $name = $item['product_name'];
                    $qty = $item['quantity'];
                    $price = $item['unit_price'];
                    $total = $item['total_price'];
                    $subtotal += $total;
                ?>
                <tr>
                    <td class="item-name-col"><?php echo htmlspecialchars(substr($name, 0, 18)); ?></td>
                    <td class="item-qty-col"><?php echo htmlspecialchars($qty); ?></td>
                    <td class="item-price-col"><?php echo number_format($price, 2); ?></td>
                    <td class="item-total-col"><?php echo number_format($total, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- TOTALS -->
        <div class="totals-section">
            <div class="total-row">
                <span class="total-label">SUBTOTAL:</span>
                <span class="total-value"><?php echo number_format($subtotal, 2); ?></span>
            </div>

            <?php
            $tax_rate = $settings['tax_rate'] ?? 0;
            $tax_amount = ($subtotal * $tax_rate) / 100;
            if ($tax_amount > 0):
            ?>
            <div class="total-row">
                <span class="total-label">TAX (<?php echo $tax_rate; ?>%):</span>
                <span class="total-value"><?php echo number_format($tax_amount, 2); ?></span>
            </div>
            <?php endif; ?>

            <div class="total-row net-total-row">
                <span class="net-total-label">NET TOTAL:</span>
                <span class="net-total-value">
                    Rs. <?php echo number_format($sale['net_amount'], 2); ?>
                </span>
            </div>
        </div>

        <!-- PAYMENT -->
        <div class="payment-section">
            <div class="payment-row">
                <span>CASH RECEIVED:</span>
                <span>Rs. <?php echo number_format($sale['cash_received'], 2); ?></span>
            </div>
            <div class="payment-row change-row">
                <span>CHANGE:</span>
                <span>Rs. <?php echo number_format($sale['change_amount'], 2); ?></span>
            </div>
            <div class="payment-row">
                <span>PAYMENT METHOD:</span>
                <span><?php echo strtoupper($sale['payment_method'] ?? 'CASH'); ?></span>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="receipt-footer">
            <?php if (!empty($settings['receipt_footer'])): ?>
                <div class="footer-text"><?php echo htmlspecialchars($settings['receipt_footer']); ?></div>
            <?php endif; ?>
            <div class="thank-you">THANK YOU!</div>
            <div class="visit-again">PLEASE VISIT AGAIN</div>
            <div class="footer-text">Customer Copy</div>
        </div>

    </div><!-- /receipt-wrapper -->

    <!-- FORCE PAGE END (CUT) -->
    <div class="receipt-end"></div>
    
    <!-- SECOND COPY (IF NEEDED) -->
    <div class="no-print" style="text-align: center; margin-top: 20px; padding: 20px;">
        <p style="font-size: 14px; color: #666; font-family: Arial, sans-serif;">
            <strong>Print Instructions:</strong><br>
            1. Receipt should print automatically<br>
            2. If not, press <kbd>Ctrl+P</kbd> (Windows) or <kbd>Cmd+P</kbd> (Mac)<br>
            3. Select your printer<br>
            4. Click "Print"<br>
            5. Click "Back to Sales Report" when done
        </p>
        <button class="back-button" onclick="goBack()" style="margin-top: 10px;">
            ← Back to Sales Report
        </button>
    </div>
</body>
</html>
<?php $conn->close(); ?>