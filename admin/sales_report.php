<?php
require_once '../includes/config.php';
checkLogin();
checkAdmin();

// Default to today's date
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get sales for selected date with profit information
$sales_sql = "SELECT s.*, u.full_name, 
                     COALESCE(ps.total_profit, 0) as profit
              FROM sales s 
              LEFT JOIN users u ON s.user_id = u.id 
              LEFT JOIN profit_summary ps ON s.id = ps.sale_id 
              WHERE s.sale_date = '$date' 
              AND s.status = 'completed'
              ORDER BY s.id DESC";
$sales_result = $conn->query($sales_sql);

// Get daily summary with profit
$summary_sql = "SELECT 
                COUNT(*) as total_bills,
                SUM(total_items) as total_items,
                SUM(net_amount) as total_sales,
                COALESCE(SUM(ps.total_profit), 0) as total_profit
                FROM sales s
                LEFT JOIN profit_summary ps ON s.id = ps.sale_id
                WHERE s.sale_date = '$date' 
                AND s.status = 'completed'";
$summary_result = $conn->query($summary_sql);
$summary = $summary_result->fetch_assoc();

// Calculate profit percentage
$profit_percentage = $summary['total_sales'] > 0 ? 
    ($summary['total_profit'] / $summary['total_sales'] * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - <?php echo $settings['store_name']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f7fafc; }
        .container { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 250px;
            background: #2d3748;
            color: white;
            padding: 20px 0;
        }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid #4a5568; }
        .store-name { font-size: 20px; font-weight: bold; }
        .store-subtitle { font-size: 12px; color: #cbd5e0; }
        .user-info { padding: 20px; border-bottom: 1px solid #4a5568; }
        .nav { list-style: none; }
        .nav-item { border-bottom: 1px solid #4a5568; }
        .nav-link {
            display: block;
            padding: 15px 20px;
            color: #cbd5e0;
            text-decoration: none;
        }
        .nav-link:hover, .nav-link.active {
            background: #4a5568;
            color: white;
            border-left: 4px solid #667eea;
        }
        
        .main { flex: 1; padding: 20px; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .page-title { font-size: 24px; color: #2d3748; }
        .logout-btn {
            background: #fc8181;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
        }
        
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filter-form { display: flex; align-items: center; gap: 15px; }
        label { font-weight: bold; color: #4a5568; }
        input[type="date"] {
            padding: 8px;
            border: 1px solid #cbd5e0;
            border-radius: 5px;
        }
        .btn-filter {
            background: #4299e1;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border: 2px solid transparent;
            transition: transform 0.2s;
        }
        .summary-card:hover {
            transform: translateY(-2px);
        }
        .summary-title { 
            color: #718096; 
            font-size: 14px; 
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-value { 
            font-size: 28px; 
            font-weight: bold; 
            color: #2d3748; 
            margin-bottom: 5px;
        }
        .summary-subtitle {
            font-size: 12px;
            color: #a0aec0;
        }
        .profit-positive { 
            color: #38a169; 
            border-color: #c6f6d5;
        }
        .profit-negative { 
            color: #e53e3e; 
            border-color: #fed7d7;
        }
        .profit-neutral { 
            color: #718096; 
            border-color: #e2e8f0;
        }
        .summary-card.profit-highlight {
            background: linear-gradient(135deg, #f0fff4 0%, #e6fffa 100%);
            border-color: #38a169;
        }
        .summary-card.loss-highlight {
            background: linear-gradient(135deg, #fff5f5 0%, #fff0f0 100%);
            border-color: #fc8181;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            overflow-x: auto;
        }
        .table-title { 
            font-size: 18px; 
            color: #2d3748; 
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .date-info {
            font-size: 14px;
            color: #718096;
            font-weight: normal;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            min-width: 900px;
        }
        th { 
            background: #f7fafc; 
            padding: 12px; 
            text-align: left; 
            color: #4a5568;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        td { 
            padding: 12px; 
            border-bottom: 1px solid #e2e8f0; 
            vertical-align: middle;
        }
        tr:hover {
            background: #f8fafc;
        }
        
        .profit-cell {
            font-weight: 600;
            font-size: 14px;
        }
        .profit-amount {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }
        .profit-badge {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 6px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-profit {
            background: #c6f6d5;
            color: #22543d;
        }
        .badge-loss {
            background: #fed7d7;
            color: #c53030;
        }
        .badge-neutral {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-view {
            background: #48bb78;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        .btn-view:hover {
            background: #38a169;
            transform: translateY(-1px);
        }
        .btn-print {
            background: #4299e1;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
            margin-left: 6px;
        }
        .btn-print:hover {
            background: #3182ce;
            transform: translateY(-1px);
        }
        .btn-details {
            background: #ed8936;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
            margin-left: 6px;
        }
        .btn-details:hover {
            background: #dd6b20;
            transform: translateY(-1px);
        }
        
        .action-buttons {
            display: flex;
            gap: 6px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        .empty-state p {
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
            .container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
            }
            .main {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="store-name"><?php echo $settings['store_name']; ?></div>
                <div class="store-subtitle">Admin Panel</div>
            </div>
            
            <div class="user-info">
                <div>Welcome, <?php echo $_SESSION['full_name']; ?></div>
                <div>(<?php echo ucfirst($_SESSION['user_role']); ?>)</div>
            </div>
            
            <ul class="nav">
                <li class="nav-item"><a href="index.php" class="nav-link">📊 Dashboard</a></li>
                <li class="nav-item"><a href="products.php" class="nav-link">📦 Products</a></li>
                <li class="nav-item"><a href="categories.php" class="nav-link">🏷️ Categories</a></li>
                <li class="nav-item"><a href="sales_report.php" class="nav-link active">📈 Sales Report</a></li>
                <li class="nav-item"><a href="profit_report.php" class="nav-link">💰 Profit Report</a></li>
                <li class="nav-item"><a href="returns.php" class="nav-link">🔄 Returns</a></li>
                <li class="nav-item"><a href="settings.php" class="nav-link">⚙️ Settings</a></li>
            </ul>
        </div>
        
        <main class="main">
            <div class="header">
                <h1 class="page-title">Sales Report</h1>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="filter-container">
                <form method="GET" action="" class="filter-form">
                    <label for="date">Select Date:</label>
                    <input type="date" id="date" name="date" value="<?php echo $date; ?>">
                    <button type="submit" class="btn-filter">Filter</button>
                </form>
            </div>
            
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-title">Total Bills</div>
                    <div class="summary-value"><?php echo $summary['total_bills'] ?? 0; ?></div>
                    <div class="summary-subtitle">Completed transactions</div>
                </div>
                <div class="summary-card">
                    <div class="summary-title">Items Sold</div>
                    <div class="summary-value"><?php echo $summary['total_items'] ?? 0; ?></div>
                    <div class="summary-subtitle">Total quantity sold</div>
                </div>
                <div class="summary-card">
                    <div class="summary-title">Total Sales</div>
                    <div class="summary-value"><?php echo formatCurrency($summary['total_sales'] ?? 0); ?></div>
                    <div class="summary-subtitle">Gross revenue</div>
                </div>
                <div class="summary-card <?php echo $summary['total_profit'] >= 0 ? 'profit-highlight' : 'loss-highlight'; ?>">
                    <div class="summary-title">Total Profit</div>
                    <div class="summary-value <?php echo $summary['total_profit'] > 0 ? 'profit-positive' : ($summary['total_profit'] < 0 ? 'profit-negative' : 'profit-neutral'); ?>">
                        <?php echo formatCurrency($summary['total_profit'] ?? 0); ?>
                    </div>
                    <div class="summary-subtitle">
                        <?php if ($summary['total_sales'] > 0): ?>
                        Margin: <?php echo number_format($profit_percentage, 2); ?>%
                        <?php else: ?>
                        No sales data
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <h2 class="table-title">
                    Sales Details 
                    <span class="date-info"><?php echo $date; ?></span>
                </h2>
                
                <?php if ($sales_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Bill No</th>
                            <th>Time</th>
                            <th>Cashier</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Profit</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($sale = $sales_result->fetch_assoc()): 
                            $profit = $sale['profit'];
                            $profit_class = $profit > 0 ? 'profit-positive' : 
                                         ($profit < 0 ? 'profit-negative' : 'profit-neutral');
                            $badge_class = $profit > 0 ? 'badge-profit' : 
                                         ($profit < 0 ? 'badge-loss' : 'badge-neutral');
                            $badge_text = $profit > 0 ? 'PROFIT' : 
                                        ($profit < 0 ? 'LOSS' : 'BREAK EVEN');
                        ?>
                        <tr>
                            <td><strong><?php echo $sale['bill_number']; ?></strong></td>
                            <td><?php echo date('h:i A', strtotime($sale['sale_time'])); ?></td>
                            <td><?php echo $sale['full_name'] ?? 'N/A'; ?></td>
                            <td><?php echo $sale['total_items']; ?></td>
                            <td><?php echo formatCurrency($sale['net_amount']); ?></td>
                            <td class="profit-cell">
                                <span class="profit-amount <?php echo $profit_class; ?>">
                                    <?php echo formatCurrency($profit); ?>
                                </span>
                                <span class="profit-badge <?php echo $badge_class; ?>">
                                    <?php echo $badge_text; ?>
                                </span>
                            </td>
                            <td><?php echo ucfirst($sale['payment_method']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="../prints/print.php?bill=<?php echo urlencode($sale['bill_number']); ?>" 
                                       class="btn-view" 
                                       target="_blank">
                                        👁️ View
                                    </a>
                                    <a href="../prints/print.php?bill=<?php echo urlencode($sale['bill_number']); ?>" 
                                       class="btn-print" 
                                       target="_blank">
                                        🖨️ Print
                                    </a>
                                    <button onclick="showSaleDetails(<?php echo $sale['id']; ?>)" 
                                            class="btn-details">
                                        📊 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <p>No sales found for <?php echo $date; ?></p>
                    <p style="color: #a0aec0; font-size: 14px;">
                        Select a different date to view sales data
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Sale Details Modal -->
    <div id="saleDetailsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Sale Details</h3>
                <button onclick="closeModal()" style="background:none;border:none;color:white;font-size:20px;cursor:pointer;">×</button>
            </div>
            <div class="modal-body" id="saleDetailsContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>

    <script>
        // Show sale details modal
        function showSaleDetails(saleId) {
            // Create a simple AJAX request to get sale details
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `get_sale_details.php?sale_id=${saleId}`, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    document.getElementById('saleDetailsContent').innerHTML = xhr.responseText;
                    document.getElementById('saleDetailsModal').style.display = 'flex';
                }
            };
            xhr.send();
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('saleDetailsModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('saleDetailsModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Format currency for JavaScript
        function formatCurrencyJS(amount) {
            return 'Rs. ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>