<?php
require_once '../includes/config.php';
checkLogin();
checkAdmin();

// Get statistics
$today = date('Y-m-d');

// Today's sales
$sales_sql = "SELECT COUNT(*) as bills, SUM(net_amount) as total, SUM(total_items) as items 
              FROM sales WHERE sale_date = '$today'";
$sales_result = $conn->query($sales_sql);
$sales_data = $sales_result->fetch_assoc();

// Low stock
$low_stock_sql = "SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_alert AND is_active = 1";
$low_result = $conn->query($low_stock_sql);
$low_data = $low_result->fetch_assoc();

// Recent sales
$recent_sql = "SELECT s.*, u.full_name 
               FROM sales s
               LEFT JOIN users u ON s.user_id = u.id
               WHERE DATE(s.sale_date) = '$today'
               ORDER BY s.id DESC
               LIMIT 10";

$recent_result = $conn->query($recent_sql);

// Low stock items
$items_sql = "SELECT * FROM products WHERE stock_quantity <= min_stock_alert AND is_active = 1 LIMIT 10";
$items_result = $conn->query($items_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo $settings['store_name']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f7fafc; }
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
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
        .user-name { font-weight: bold; }
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
        
        /* Main Content */
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
        
        /* Header Buttons */
        .header-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .header-btn {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        .add-user-btn {
            background: #4299e1;
            color: white;
        }
        .add-user-btn:hover {
            background: #3182ce;
            transform: translateY(-2px);
        }
        .logout-btn {
            background: #fc8181;
            color: white;
        }
        .logout-btn:hover {
            background: #e53e3e;
            transform: translateY(-2px);
        }
        
        /* Cards */
        .cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card-title { color: #718096; font-size: 14px; margin-bottom: 10px; }
        .card-value { font-size: 28px; font-weight: bold; color: #2d3748; }
        .card-icon { float: right; font-size: 40px; color: #cbd5e0; }
        
        /* Tables */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .table-title { font-size: 18px; color: #2d3748; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f7fafc; padding: 12px; text-align: left; color: #4a5568; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        .low { color: #f56565; font-weight: bold; }
        
        /* Quick Actions */
        .quick-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-top: 20px; }
        .action-btn {
            display: block;
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            color: #2d3748;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .action-icon { font-size: 30px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="store-name"><?php echo $settings['store_name']; ?></div>
                <div class="store-subtitle">Admin Panel</div>
            </div>
            
            <div class="user-info">
                <div>Welcome,</div>
                <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                <div>(<?php echo ucfirst($_SESSION['user_role']); ?>)</div>
            </div>
            
            <ul class="nav">
                <li class="nav-item"><a href="index.php" class="nav-link active">📊 Dashboard</a></li>
                <li class="nav-item"><a href="products.php" class="nav-link">📦 Products</a></li>
                <li class="nav-item"><a href="categories.php" class="nav-link">🏷️ Categories</a></li>
                <li class="nav-item"><a href="users.php" class="nav-link">👥 Users</a></li>
                <li class="nav-item"><a href="sales_report.php" class="nav-link">📈 Sales Report</a></li>
                <li class="nav-item"><a href="returns.php" class="nav-link">🔄 Returns</a></li>
                <li class="nav-item"><a href="settings.php" class="nav-link">⚙️ Settings</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <main class="main">
            <div class="header">
                <h1 class="page-title">Dashboard</h1>
                <div class="header-buttons">
                    <a href="users.php?action=add" class="header-btn add-user-btn">
                        <span>👤</span>
                        Add User
                    </a>
                    <a href="../logout.php" class="header-btn logout-btn">
                        <span>🚪</span>
                        Logout
                    </a>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="cards">
                <div class="card">
                    <div class="card-title">Today's Sales</div>
                    <div class="card-value"><?php echo formatCurrency($sales_data['total'] ?? 0); ?></div>
                    <div class="card-icon">💰</div>
                </div>
                
                <div class="card">
                    <div class="card-title">Total Bills</div>
                    <div class="card-value"><?php echo $sales_data['bills'] ?? 0; ?></div>
                    <div class="card-icon">🧾</div>
                </div>
                
                <div class="card">
                    <div class="card-title">Items Sold</div>
                    <div class="card-value"><?php echo $sales_data['items'] ?? 0; ?></div>
                    <div class="card-icon">📦</div>
                </div>
                
                <div class="card">
                    <div class="card-title">Low Stock Items</div>
                    <div class="card-value"><?php echo $low_data['count'] ?? 0; ?></div>
                    <div class="card-icon">⚠️</div>
                </div>
            </div>
            
            <!-- Recent Sales -->
            <div class="table-container">
                <div class="table-title">Recent Sales</div>
                <table>
                    <thead>
                        <tr>
                            <th>Bill No</th>
                            <th>Date</th>
                            <th>Cashier</th>
                            <th>Items</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($sale = $recent_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $sale['bill_number']; ?></td>
                            <td><?php echo $sale['sale_date']; ?></td>
                            <td><?php echo $sale['full_name']; ?></td>
                            <td><?php echo $sale['total_items']; ?></td>
                            <td><?php echo formatCurrency($sale['net_amount']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Low Stock Items -->
            <div class="table-container">
                <div class="table-title">Low Stock Items</div>
                <table>
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Barcode</th>
                            <th>Stock</th>
                            <th>Alert</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($item = $items_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $item['name']; ?></td>
                            <td><?php echo $item['barcode']; ?></td>
                            <td class="low"><?php echo $item['stock_quantity']; ?></td>
                            <td><?php echo $item['min_stock_alert']; ?></td>
                            <td><?php echo formatCurrency($item['sale_price']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($items_result->num_rows == 0): ?>
                        <tr><td colspan="5" style="text-align: center;">No low stock items</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="products.php" class="action-btn">
                    <div class="action-icon">➕</div>
                    <div>Add Product</div>
                </a>
                <a href="sales_report.php" class="action-btn">
                    <div class="action-icon">📊</div>
                    <div>View Reports</div>
                </a>
                <a href="users.php" class="action-btn">
                    <div class="action-icon">👥</div>
                    <div>Manage Users</div>
                </a>
                <a href="settings.php" class="action-btn">
                    <div class="action-icon">⚙️</div>
                    <div>Settings</div>
                </a>
            </div>
        </main>
    </div>
</body>
</html>
<?php $conn->close(); ?>