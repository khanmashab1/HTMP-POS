<?php
require_once '../includes/config.php';
checkLogin();
checkAdmin();

// Get profit summary data
$profit_sql = "SELECT 
                    DATE(s.created_at) as sale_date,
                    COUNT(s.id) as total_sales,
                    SUM(s.total_amount) as total_revenue,
                    COALESCE(SUM(ps.total_profit), 0) as total_profit,
                    COALESCE(AVG(ps.total_profit), 0) as avg_profit_per_sale
                FROM sales s
                LEFT JOIN profit_summary ps ON s.id = ps.sale_id
                WHERE s.status = 'completed'
                GROUP BY DATE(s.created_at)
                ORDER BY sale_date DESC";

$profit_result = $conn->query($profit_sql);

// Prepare data for charts
$chart_data = [];
$chart_dates = [];
$chart_profits = [];
$chart_revenues = [];

while ($row = $profit_result->fetch_assoc()) {
    $profit_margin = $row['total_revenue'] > 0 ? 
        ($row['total_profit'] / $row['total_revenue']) * 100 : 0;
    
    $chart_data[] = $row;
    $chart_dates[] = date('M d', strtotime($row['sale_date']));
    $chart_profits[] = floatval($row['total_profit']);
    $chart_revenues[] = floatval($row['total_revenue']);
}

// Reset pointer for table display
$chart_data_reset = $chart_data; // Copy for table display

// Calculate summary stats
$total_revenue = array_sum($chart_revenues);
$total_profit = array_sum($chart_profits);
$avg_profit_margin = $total_revenue > 0 ? ($total_profit / $total_revenue * 100) : 0;
$best_day_profit = !empty($chart_profits) ? max($chart_profits) : 0;
$worst_day_profit = !empty($chart_profits) ? min($chart_profits) : 0;

// Get date range for display
$date_range = '';
if (!empty($chart_dates)) {
    $first_date = end($chart_dates); // Because it's sorted DESC
    $last_date = reset($chart_dates);
    $date_range = "$first_date - $last_date";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit Report - <?php echo $settings['store_name']; ?></title>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ========== BASE RESET ========== */
        :root {
            --primary-color: #3b82f6;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --dark-color: #1f2937;
            --light-color: #f9fafb;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --sidebar-width: 260px;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 0.75rem;
            --spacing-lg: 1rem;
            --spacing-xl: 1.25rem;
            --spacing-2xl: 1.5rem;
            --border-radius: 0.5rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f3f4f6;
            color: var(--text-primary);
            line-height: 1.5;
        }

        /* ========== LAYOUT CONTAINER ========== */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* ========== SIDEBAR ========== */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 100;
        }

        .sidebar-header {
            padding: var(--spacing-2xl);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .store-name {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: var(--spacing-xs);
            color: white;
        }

        .store-subtitle {
            font-size: 0.75rem;
            color: #94a3b8;
            opacity: 0.8;
        }

        .user-info {
            padding: var(--spacing-xl) var(--spacing-2xl);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .user-name {
            font-weight: 600;
            margin-bottom: var(--spacing-xs);
        }

        .user-role {
            font-size: 0.875rem;
            color: #cbd5e1;
        }

        .nav {
            list-style: none;
            padding: var(--spacing-lg) 0;
        }

        .nav-item {
            margin-bottom: 2px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-md) var(--spacing-2xl);
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.9375rem;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .nav-link.active {
            background: rgba(59, 130, 246, 0.1);
            color: white;
            border-left: 4px solid var(--primary-color);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.125rem;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: var(--spacing-2xl);
            background: #f3f4f6;
        }

        /* ========== HEADER ========== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-2xl);
            padding-bottom: var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
        }

        .page-title-section h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: var(--spacing-xs);
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.9375rem;
        }

        .header-actions {
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-export {
            background: #10b981;
            color: white;
        }

        .btn-export:hover {
            background: #059669;
        }

        /* ========== SUMMARY CARDS ========== */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--spacing-xl);
            margin-bottom: var(--spacing-2xl);
        }

        .summary-card {
            background: white;
            padding: var(--spacing-xl);
            border-radius: var(--border-radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
        }

        .summary-title {
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .summary-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .summary-icon.revenue { background: #dbeafe; color: #1d4ed8; }
        .summary-icon.profit { background: #d1fae5; color: #047857; }
        .summary-icon.margin { background: #fef3c7; color: #92400e; }
        .summary-icon.sales { background: #e0e7ff; color: #4338ca; }

        .summary-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
        }

        .summary-trend {
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }

        .trend-up { color: var(--success-color); }
        .trend-down { color: var(--danger-color); }
        .trend-neutral { color: var(--text-secondary); }

        /* ========== CHARTS SECTION ========== */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-xl);
            margin-bottom: var(--spacing-2xl);
        }

        .chart-card {
            background: white;
            padding: var(--spacing-xl);
            border-radius: var(--border-radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
        }

        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* ========== DATA TABLE ========== */
        .data-table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .table-header {
            padding: var(--spacing-xl);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .table-actions {
            display: flex;
            gap: var(--spacing-md);
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .profit-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .profit-table th {
            background: #f9fafb;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .profit-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 0.9375rem;
        }

        .profit-table tr:hover {
            background: #f9fafb;
        }

        .profit-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* ========== PROFIT STYLING ========== */
        .profit-positive {
            color: var(--success-color);
            font-weight: 600;
        }

        .profit-negative {
            color: var(--danger-color);
            font-weight: 600;
        }

        .profit-cell {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .profit-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-profit {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-loss {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: var(--spacing-lg);
            opacity: 0.5;
        }

        /* ========== RESPONSIVE DESIGN ========== */
        @media (max-width: 1200px) {
            .summary-grid,
            .charts-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
                padding: var(--spacing-lg);
            }
            
            .summary-grid,
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                gap: var(--spacing-lg);
                align-items: stretch;
            }
            
            .header-actions {
                flex-wrap: wrap;
            }
        }

        /* ========== LOADING STATE ========== */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* ========== PRINT STYLES ========== */
        @media print {
            .sidebar,
            .header-actions,
            .charts-grid {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .profit-table {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="store-name"><?php echo $settings['store_name']; ?></div>
                <div class="store-subtitle">Inventory Management System</div>
            </div>
            
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                <div class="user-role"><?php echo ucfirst($_SESSION['user_role']); ?> User</div>
            </div>
            
            <ul class="nav">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">
                        <span>📊</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php" class="nav-link">
                        <span>📦</span>
                        <span>Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="sales_report.php" class="nav-link">
                        <span>📈</span>
                        <span>Sales Report</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profit_report.php" class="nav-link active">
                        <span>💰</span>
                        <span>Profit Report</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="returns.php" class="nav-link">
                        <span>🔄</span>
                        <span>Returns</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <span>⚙️</span>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <header class="page-header">
                <div class="page-title-section">
                    <h1>Profit Analytics Dashboard</h1>
                    <p class="page-subtitle">Track your business profitability and margins <?php echo $date_range ? "($date_range)" : ""; ?></p>
                </div>
                <div class="header-actions">
                    <a href="index.php" class="btn btn-secondary">
                        ← Back to Dashboard
                    </a>
                    <button onclick="window.print()" class="btn">
                        🖨️ Print Report
                    </button>
                    <a href="export_profit.php" class="btn btn-export">
                        📥 Export Excel
                    </a>
                </div>
            </header>

            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-header">
                        <div class="summary-title">Total Revenue</div>
                        <div class="summary-icon revenue">💰</div>
                    </div>
                    <div class="summary-value"><?php echo formatCurrency($total_revenue); ?></div>
                    <div class="summary-trend">
                        <span class="trend-neutral">Overall revenue</span>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-header">
                        <div class="summary-title">Total Profit</div>
                        <div class="summary-icon profit">📈</div>
                    </div>
                    <div class="summary-value <?php echo $total_profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                        <?php echo formatCurrency($total_profit); ?>
                    </div>
                    <div class="summary-trend">
                        <span class="<?php echo $total_profit >= 0 ? 'trend-up' : 'trend-down'; ?>">
                            <?php echo $total_profit >= 0 ? '▲' : '▼'; ?>
                            <?php echo formatCurrency(abs($total_profit)); ?>
                        </span>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-header">
                        <div class="summary-title">Avg Profit Margin</div>
                        <div class="summary-icon margin">%</div>
                    </div>
                    <div class="summary-value <?php echo $avg_profit_margin >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                        <?php echo number_format($avg_profit_margin, 2); ?>%
                    </div>
                    <div class="summary-trend">
                        <span class="<?php echo $avg_profit_margin >= 20 ? 'trend-up' : ($avg_profit_margin >= 10 ? 'trend-neutral' : 'trend-down'); ?>">
                            <?php echo $avg_profit_margin >= 20 ? 'Excellent' : ($avg_profit_margin >= 10 ? 'Good' : 'Needs Improvement'); ?>
                        </span>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-header">
                        <div class="summary-title">Total Sales</div>
                        <div class="summary-icon sales">📊</div>
                    </div>
                    <div class="summary-value"><?php echo count($chart_data); ?></div>
                    <div class="summary-trend">
                        <span class="trend-neutral">Days with sales</span>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Daily Profit Trend</h3>
                        <span class="summary-trend">
                            <?php if ($best_day_profit > 0): ?>
                                <span class="trend-up">Best: <?php echo formatCurrency($best_day_profit); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="chart-container">
                        <canvas id="profitChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Revenue vs Profit Comparison</h3>
                        <span class="summary-trend">
                            <?php if ($worst_day_profit < 0): ?>
                                <span class="trend-down">Watch: <?php echo formatCurrency($worst_day_profit); ?> loss</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueProfitChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="data-table-container">
                <div class="table-header">
                    <h3 class="table-title">Daily Profit Breakdown</h3>
                    <div class="table-actions">
                        <select id="sortBy" onchange="sortTable()" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                            <option value="date_desc">Date (Newest First)</option>
                            <option value="date_asc">Date (Oldest First)</option>
                            <option value="profit_desc">Profit (High to Low)</option>
                            <option value="profit_asc">Profit (Low to High)</option>
                            <option value="margin_desc">Margin (High to Low)</option>
                        </select>
                    </div>
                </div>
                
                <div class="table-wrapper">
                    <?php if (!empty($chart_data_reset)): ?>
                    <table class="profit-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total Sales</th>
                                <th>Total Revenue</th>
                                <th>Total Profit</th>
                                <th>Avg Profit/Sale</th>
                                <th>Profit Margin</th>
                            </tr>
                        </thead>
                        <tbody id="profitTableBody">
                            <?php foreach ($chart_data_reset as $row): 
                                $profit_margin = $row['total_revenue'] > 0 ? 
                                    ($row['total_profit'] / $row['total_revenue']) * 100 : 0;
                                $is_profit = $row['total_profit'] >= 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('M d, Y', strtotime($row['sale_date'])); ?></strong>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                        <?php echo date('l', strtotime($row['sale_date'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo $row['total_sales']; ?></div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                        transactions
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo formatCurrency($row['total_revenue']); ?></div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                        revenue
                                    </div>
                                </td>
                                <td>
                                    <div class="profit-cell">
                                        <span class="<?php echo $is_profit ? 'profit-positive' : 'profit-negative'; ?>">
                                            <?php echo formatCurrency($row['total_profit']); ?>
                                        </span>
                                        <span class="profit-badge <?php echo $is_profit ? 'badge-profit' : 'badge-loss'; ?>">
                                            <?php echo $is_profit ? 'PROFIT' : 'LOSS'; ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="<?php echo $row['avg_profit_per_sale'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                        <?php echo formatCurrency($row['avg_profit_per_sale']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="<?php echo $profit_margin >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                        <?php echo number_format($profit_margin, 2); ?>%
                                    </div>
                                    <?php if ($profit_margin >= 20): ?>
                                    <div style="font-size: 0.75rem; color: var(--success-color);">Excellent</div>
                                    <?php elseif ($profit_margin >= 10): ?>
                                    <div style="font-size: 0.75rem; color: var(--warning-color);">Good</div>
                                    <?php elseif ($profit_margin >= 0): ?>
                                    <div style="font-size: 0.75rem; color: var(--danger-color);">Low</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <h3>No Profit Data Available</h3>
                        <p>Start making sales to see profit analytics here.</p>
                        <a href="sales_report.php" class="btn btn-primary" style="margin-top: 1rem;">
                            View Sales Report
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Prepare data for charts
        const chartDates = <?php echo json_encode($chart_dates); ?>;
        const chartProfits = <?php echo json_encode($chart_profits); ?>;
        const chartRevenues = <?php echo json_encode($chart_revenues); ?>;

        // Profit Trend Line Chart
        const profitCtx = document.getElementById('profitChart').getContext('2d');
        const profitChart = new Chart(profitCtx, {
            type: 'line',
            data: {
                labels: chartDates,
                datasets: [{
                    label: 'Daily Profit',
                    data: chartProfits,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: function(context) {
                        const value = context.dataset.data[context.dataIndex];
                        return value >= 0 ? '#10b981' : '#ef4444';
                    },
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: '#6b7280',
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                const value = context.parsed.y;
                                label += 'Rs. ' + value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            color: '#6b7280',
                            maxRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            color: '#6b7280',
                            callback: function(value) {
                                return 'Rs. ' + value.toLocaleString();
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'nearest'
                }
            }
        });

        // Revenue vs Profit Bar Chart
        const revenueProfitCtx = document.getElementById('revenueProfitChart').getContext('2d');
        const revenueProfitChart = new Chart(revenueProfitCtx, {
            type: 'bar',
            data: {
                labels: chartDates,
                datasets: [
                    {
                        label: 'Revenue',
                        data: chartRevenues,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Profit',
                        data: chartProfits,
                        backgroundColor: function(context) {
                            const value = context.dataset.data[context.dataIndex];
                            return value >= 0 ? 
                                'rgba(16, 185, 129, 0.7)' : 
                                'rgba(239, 68, 68, 0.7)';
                        },
                        borderColor: function(context) {
                            const value = context.dataset.data[context.dataIndex];
                            return value >= 0 ? 
                                'rgba(16, 185, 129, 1)' : 
                                'rgba(239, 68, 68, 1)';
                        },
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: '#6b7280',
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                const value = context.parsed.y;
                                label += 'Rs. ' + value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6b7280',
                            maxRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            color: '#6b7280',
                            callback: function(value) {
                                return 'Rs. ' + value.toLocaleString();
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        // Table Sorting Functionality
        function sortTable() {
            const select = document.getElementById('sortBy');
            const sortValue = select.value;
            const tbody = document.getElementById('profitTableBody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.sort((a, b) => {
                const cellsA = a.querySelectorAll('td');
                const cellsB = b.querySelectorAll('td');
                
                switch(sortValue) {
                    case 'date_desc':
                        return new Date(cellsB[0].querySelector('strong').textContent) - 
                               new Date(cellsA[0].querySelector('strong').textContent);
                    case 'date_asc':
                        return new Date(cellsA[0].querySelector('strong').textContent) - 
                               new Date(cellsB[0].querySelector('strong').textContent);
                    case 'profit_desc':
                        return parseFloat(cellsB[3].querySelector('.profit-cell span').textContent.replace(/[^0-9.-]+/g,"")) - 
                               parseFloat(cellsA[3].querySelector('.profit-cell span').textContent.replace(/[^0-9.-]+/g,""));
                    case 'profit_asc':
                        return parseFloat(cellsA[3].querySelector('.profit-cell span').textContent.replace(/[^0-9.-]+/g,"")) - 
                               parseFloat(cellsB[3].querySelector('.profit-cell span').textContent.replace(/[^0-9.-]+/g,""));
                    case 'margin_desc':
                        return parseFloat(cellsB[5].querySelector('div').textContent) - 
                               parseFloat(cellsA[5].querySelector('div').textContent);
                    default:
                        return 0;
                }
            });
            
            // Reappend sorted rows
            rows.forEach(row => tbody.appendChild(row));
        }

        // Handle window resize for charts
        window.addEventListener('resize', function() {
            profitChart.resize();
            revenueProfitChart.resize();
        });

        // Export to Excel (placeholder function)
        function exportToExcel() {
            alert('Export functionality would download an Excel file with all profit data.');
            // In production, this would link to a PHP export script
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>