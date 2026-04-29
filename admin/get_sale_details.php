<?php
require_once '../includes/config.php';
checkLogin();
checkAdmin();

$sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;

if ($sale_id > 0) {
    // Get sale details with profit breakdown
    $sql = "SELECT 
                s.*, 
                u.full_name as cashier,
                ps.total_profit as profit
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN profit_summary ps ON s.id = ps.sale_id
            WHERE s.id = $sale_id";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $sale = $result->fetch_assoc();
        
        // Get sale items with profit per item
        $items_sql = "SELECT 
                        si.*, 
                        p.name as product_name,
                        p.cost_price,
                        (si.unit_price - p.cost_price) * si.quantity as item_profit
                    FROM sale_items si
                    JOIN products p ON si.product_id = p.id
                    WHERE si.sale_id = $sale_id";
        
        $items_result = $conn->query($items_sql);
        
        echo '<div class="sale-details">';
        echo '<div class="details-header">';
        echo '<h4>Bill No: ' . htmlspecialchars($sale['bill_number']) . '</h4>';
        echo '<p>Date: ' . $sale['sale_date'] . ' ' . $sale['sale_time'] . '</p>';
        echo '<p>Cashier: ' . htmlspecialchars($sale['cashier']) . '</p>';
        echo '<p>Payment Method: ' . ucfirst($sale['payment_method']) . '</p>';
        echo '</div>';
        
        echo '<div class="details-summary">';
        echo '<div class="summary-row">';
        echo '<span>Total Items:</span><span>' . $sale['total_items'] . '</span>';
        echo '</div>';
        echo '<div class="summary-row">';
        echo '<span>Total Amount:</span><span>' . formatCurrency($sale['net_amount']) . '</span>';
        echo '</div>';
        echo '<div class="summary-row ' . ($sale['profit'] > 0 ? 'profit-positive' : 'profit-negative') . '">';
        echo '<span>Total Profit:</span><span>' . formatCurrency($sale['profit']) . '</span>';
        echo '</div>';
        echo '</div>';
        
        if ($items_result->num_rows > 0) {
            echo '<div class="items-table">';
            echo '<h5>Items Sold</h5>';
            echo '<table>';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Product</th>';
            echo '<th>Qty</th>';
            echo '<th>Unit Price</th>';
            echo '<th>Cost Price</th>';
            echo '<th>Profit</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            while ($item = $items_result->fetch_assoc()) {
                $item_profit_class = $item['item_profit'] > 0 ? 'profit-positive' : 
                                   ($item['item_profit'] < 0 ? 'profit-negative' : 'profit-neutral');
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($item['product_name']) . '</td>';
                echo '<td>' . $item['quantity'] . '</td>';
                echo '<td>' . formatCurrency($item['unit_price']) . '</td>';
                echo '<td>' . formatCurrency($item['cost_price']) . '</td>';
                echo '<td class="' . $item_profit_class . '">' . formatCurrency($item['item_profit']) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
        
        echo '</div>';
    } else {
        echo '<p>Sale not found.</p>';
    }
} else {
    echo '<p>Invalid sale ID.</p>';
}

$conn->close();
?>