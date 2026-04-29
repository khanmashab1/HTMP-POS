<?php
require_once '../includes/config.php';
checkLogin();
checkAdmin();

// Get date filter (optional)
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Base query
$returns_sql = "
    SELECT r.*, u.full_name, s.bill_number AS original_bill
    FROM returns r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN sales s ON r.original_sale_id = s.id
";

// Apply date filter if selected
if (!empty($date_filter)) {
    $returns_sql .= " WHERE r.return_date = '$date_filter'";
}

// Order by latest
$returns_sql .= " ORDER BY r.id DESC";

// Execute query
$returns_result = $conn->query($returns_sql);

// Debug: Check if returns exist
$total_returns = $returns_result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns - <?php echo $settings['store_name']; ?></title>
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
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .table-title { font-size: 18px; color: #2d3748; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f7fafc; padding: 12px; text-align: left; color: #4a5568; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        .btn-view {
            background: #4299e1;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            margin-right: 5px;
        }
        .btn-print {
            background: #48bb78;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
        }
        .btn-view:hover, .btn-print:hover {
            opacity: 0.9;
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
                <li class="nav-item"><a href="sales_report.php" class="nav-link">📈 Sales Report</a></li>
                <li class="nav-item"><a href="returns.php" class="nav-link active">🔄 Returns</a></li>
                <li class="nav-item"><a href="settings.php" class="nav-link">⚙️ Settings</a></li>
            </ul>
        </div>
        
        <main class="main">
            <div class="header">
                <h1 class="page-title">Returns Management</h1>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>

            <!-- 🔍 Date Filter -->
            <div style="margin: 15px 0; background: #f7fafc; padding: 15px; border-radius: 5px;">
                <form method="GET" action="" style="display: flex; gap: 10px; align-items: center;">
                    <label style="font-weight: 600;">Filter by Date:</label>
                    <input 
                        type="date" 
                        name="date" 
                        value="<?php echo isset($_GET['date']) ? $_GET['date'] : ''; ?>" 
                        style="padding: 8px; border: 1px solid #cbd5e0; border-radius: 5px;"
                    >
                    <button 
                        type="submit" 
                        style="padding: 8px 15px; background: #4299e1; color: white; border: none; border-radius: 5px; cursor: pointer;"
                    >
                        Filter
                    </button>
                    <a 
                        href="returns.php" 
                        style="padding: 8px 15px; background: #718096; color: white; text-decoration: none; border-radius: 5px;"
                    >
                        Clear
                    </a>
                </form>
            </div>
            
            <!-- Debug Info -->
            <div style="margin-bottom: 15px; padding: 10px; background: #e6fffa; border-radius: 5px; font-size: 14px;">
                <strong>Debug Info:</strong> Found <?php echo $total_returns; ?> return(s) in database.
            </div>

            <div class="table-container">
                <h2 class="table-title">All Returns</h2>
                <?php if($total_returns > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Return Bill No</th>
                            <th>Original Bill</th>
                            <th>Date</th>
                            <th>Processed By</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Reset pointer and loop through results
                        $returns_result->data_seek(0);
                        while($return = $returns_result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo $return['id']; ?></td>
                            <td><?php echo htmlspecialchars($return['return_bill_number']); ?></td>
                            <td><?php echo htmlspecialchars($return['original_bill']); ?></td>
                            <td><?php echo $return['return_date'] . ' ' . $return['return_time']; ?></td>
                            <td><?php echo htmlspecialchars($return['full_name']); ?></td>
                            <td><?php echo $return['total_items']; ?></td>
                            <td><?php echo formatCurrency($return['total_amount']); ?></td>
                            <td>
                                <!-- FIXED: Added proper links with correct path -->
                                <a href="view_return.php?return_id=<?php echo $return['id']; ?>" 
                                   class="btn-view" 
                                   target="_blank">
                                    View Details
                                </a>
                                
                                
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #718096;">
                    <h3>No returns found</h3>
                    <p>There are no returns in the database.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
<?php $conn->close(); ?>