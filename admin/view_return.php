<?php
// view_return.php (save this in admin folder)
require_once '../includes/config.php';
checkLogin();
checkAdmin();

$return_id = isset($_GET['return_id']) ? intval($_GET['return_id']) : 0;

if ($return_id <= 0) {
    die("Error: Return ID is required!");
}

// Fetch return details
$return_sql = "SELECT r.*, u.full_name, s.bill_number as original_bill 
               FROM returns r
               LEFT JOIN users u ON r.user_id = u.id
               LEFT JOIN sales s ON r.original_sale_id = s.id
               WHERE r.id = '$return_id'";
$return_result = $conn->query($return_sql);

if ($return_result->num_rows == 0) {
    die("Error: Return not found!");
}

$return = $return_result->fetch_assoc();

// Fetch return items
$items_sql = "SELECT * FROM return_items WHERE return_id = '$return_id'";
$items_result = $conn->query($items_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Details - <?php echo $return['return_bill_number']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f7fafc; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
        .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .title { font-size: 24px; color: #2d3748; margin-bottom: 10px; }
        .subtitle { color: #718096; font-size: 16px; }
        
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
        .info-card { background: #f7fafc; padding: 15px; border-radius: 5px; }
        .info-label { font-size: 12px; color: #718096; margin-bottom: 5px; }
        .info-value { font-size: 16px; font-weight: 600; color: #2d3748; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { background: #edf2f7; padding: 12px; text-align: left; color: #4a5568; }
        .items-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        
        .actions { display: flex; gap: 10px; margin-top: 30px; }
        .btn { padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: 600; }
        .btn-print { background: #4299e1; color: white; }
        .btn-back { background: #718096; color: white; }
        .btn:hover { opacity: 0.9; }
        
        .total-section { background: #e6fffa; padding: 20px; border-radius: 5px; margin-top: 20px; }
        .total-row { display: flex; justify-content: space-between; font-size: 18px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">Return Details</h1>
            <div class="subtitle">Return Bill: <?php echo htmlspecialchars($return['return_bill_number']); ?></div>
        </div>
        
        <div class="info-grid">
            <div class="info-card">
                <div class="info-label">Return Bill Number</div>
                <div class="info-value"><?php echo htmlspecialchars($return['return_bill_number']); ?></div>
            </div>
            <div class="info-card">
                <div class="info-label">Original Bill</div>
                <div class="info-value"><?php echo htmlspecialchars($return['original_bill']); ?></div>
            </div>
            <div class="info-card">
                <div class="info-label">Return Date</div>
                <div class="info-value"><?php echo $return['return_date']; ?></div>
            </div>
            <div class="info-card">
                <div class="info-label">Return Time</div>
                <div class="info-value"><?php echo $return['return_time']; ?></div>
            </div>
            <div class="info-card">
                <div class="info-label">Processed By</div>
                <div class="info-value"><?php echo htmlspecialchars($return['full_name']); ?></div>
            </div>
            <div class="info-card">
                <div class="info-label">Reason</div>
                <div class="info-value"><?php echo htmlspecialchars($return['reason']); ?></div>
            </div>
        </div>
        
        <h3>Returned Items</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Barcode</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php while($item = $items_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['barcode']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo formatCurrency($item['unit_price']); ?></td>
                    <td><?php echo formatCurrency($item['total_price']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <div class="total-section">
            <div class="total-row">
                <span>Total Items Returned:</span>
                <span><?php echo $return['total_items']; ?></span>
            </div>
            <div class="total-row">
                <span>Total Refund Amount:</span>
                <span><?php echo formatCurrency($return['total_amount']); ?></span>
            </div>
        </div>
        
        <div class="actions">
            <a href="javascript:window.print()" class="btn btn-print">Print Receipt</a>
            <a href="returns.php" class="btn btn-back">Back to Returns</a>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>