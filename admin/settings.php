<?php
require_once '../includes/config.php';
checkLogin();
checkAdmin();

$message = '';

// Update settings
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $store_name = $conn->real_escape_string($_POST['store_name']);
    $store_address = $conn->real_escape_string($_POST['store_address']);
    $store_phone = $conn->real_escape_string($_POST['store_phone']);
    $tax_rate = floatval($_POST['tax_rate']);
    $receipt_footer = $conn->real_escape_string($_POST['receipt_footer']);
    
    // Check if settings exist
    $check_sql = "SELECT id FROM store_settings WHERE id = 1";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        // Update existing
        $sql = "UPDATE store_settings SET 
                store_name = '$store_name',
                store_address = '$store_address',
                store_phone = '$store_phone',
                tax_rate = '$tax_rate',
                receipt_footer = '$receipt_footer',
                updated_at = NOW()
                WHERE id = 1";
    } else {
        // Insert new
        $sql = "INSERT INTO store_settings (store_name, store_address, store_phone, tax_rate, receipt_footer) 
                VALUES ('$store_name', '$store_address', '$store_phone', '$tax_rate', '$receipt_footer')";
    }
    
    if ($conn->query($sql) === TRUE) {
        $message = "<div style='background:#c6f6d5;color:#22543d;padding:10px;border-radius:5px;margin-bottom:20px;'>Settings updated successfully!</div>";
        // Update current settings
        $settings = [
            'store_name' => $store_name,
            'store_address' => $store_address,
            'store_phone' => $store_phone,
            'tax_rate' => $tax_rate,
            'receipt_footer' => $receipt_footer
        ];
    } else {
        $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Error: " . $conn->error . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo $settings['store_name']; ?></title>
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
        
        .settings-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-title { font-size: 18px; color: #2d3748; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #4a5568; font-weight: bold; }
        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e0;
            border-radius: 5px;
            font-size: 16px;
        }
        textarea { height: 100px; resize: vertical; }
        .btn-submit {
            background: #48bb78;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
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
                <li class="nav-item"><a href="returns.php" class="nav-link">🔄 Returns</a></li>
                <li class="nav-item"><a href="settings.php" class="nav-link active">⚙️ Settings</a></li>
            </ul>
        </div>
        
        <main class="main">
            <div class="header">
                <h1 class="page-title">Store Settings</h1>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
            
            <?php echo $message; ?>
            
            <div class="settings-container">
                <h2 class="form-title">Store Information</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="store_name">Store Name *</label>
                        <input type="text" id="store_name" name="store_name" 
                               value="<?php echo htmlspecialchars($settings['store_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="store_address">Store Address *</label>
                        <textarea id="store_address" name="store_address" required><?php echo htmlspecialchars($settings['store_address']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="store_phone">Phone Number *</label>
                        <input type="text" id="store_phone" name="store_phone" 
                               value="<?php echo htmlspecialchars($settings['store_phone']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="tax_rate">Tax Rate (%)</label>
                        <input type="number" step="0.01" id="tax_rate" name="tax_rate" 
                               value="<?php echo $settings['tax_rate']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="receipt_footer">Receipt Footer Text</label>
                        <textarea id="receipt_footer" name="receipt_footer"><?php echo htmlspecialchars($settings['receipt_footer'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit">Save Settings</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
<?php $conn->close(); ?>