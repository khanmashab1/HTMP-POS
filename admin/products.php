<?php
require_once '../includes/config.php';
checkLogin();
checkAdmin();

// Helper function for safe number formatting
function safeNumber($number, $decimals = 2, $default = 0.00) {
    if ($number === null || $number === '' || !is_numeric($number)) {
        return number_format($default, $decimals);
    }
    return number_format((float)$number, $decimals);
}

// Helper function to safely calculate profit margin
function safeMargin($cost_price, $sale_price, $decimals = 2) {
    if (empty($cost_price) || (float)$cost_price == 0 || empty($sale_price)) {
        return number_format(0, $decimals);
    }
    
    $cost = (float)$cost_price;
    $sale = (float)$sale_price;
    $profit = $sale - $cost;
    
    if ($cost == 0) {
        return number_format(0, $decimals);
    }
    
    $margin = ($profit / $cost) * 100;
    
    return number_format($margin, $decimals);
}

// Helper function to safely calculate profit
function safeProfit($cost_price, $sale_price, $decimals = 2) {
    if (empty($cost_price) || empty($sale_price)) {
        return number_format(0, $decimals);
    }
    
    $profit = (float)$sale_price - (float)$cost_price;
    return number_format($profit, $decimals);
}

$message = '';

// Generate random EAN-13 barcode (13 digits)
function generateRandomBarcode() {
    // EAN-13: MUST be exactly 13 digits
    // First 12 digits (random) + 1 check digit
    $prefix = '123'; // First 3 digits (can be any valid GS1 prefix)
    $company_code = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT); // 5 digits
    $product_code = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT); // 4 digits
    
    // Combine to make 12 digits: prefix(3) + company(5) + product(4) = 12 digits
    $barcode_without_check = $prefix . $company_code . $product_code;
    
    // Calculate EAN-13 check digit
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $digit = (int)$barcode_without_check[$i];
        // Multiply by 1 or 3 based on position (odd/even from right)
        $sum += ($i % 2 == 0) ? $digit * 1 : $digit * 3;
    }
    
    $check_digit = (10 - ($sum % 10)) % 10;
    
    return $barcode_without_check . $check_digit; // 13 digits total
}

// Function to validate EAN-13 checksum
function validateEAN13Checksum($barcode) {
    if (strlen($barcode) != 13 || !is_numeric($barcode)) {
        return false;
    }
    
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $digit = (int)$barcode[$i];
        $sum += ($i % 2 == 0) ? $digit * 1 : $digit * 3;
    }
    
    $check_digit = (10 - ($sum % 10)) % 10;
    $actual_check_digit = (int)$barcode[12];
    
    return $check_digit == $actual_check_digit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add product
    if (isset($_POST['add_product'])) {
        $barcode = $conn->real_escape_string($_POST['barcode']);
        $name = $conn->real_escape_string($_POST['name']);
        $category_id = intval($_POST['category_id']);
        $sale_price = floatval($_POST['sale_price']);
        $cost_price = floatval($_POST['cost_price']);
        $stock = intval($_POST['stock_quantity']);
        $alert = intval($_POST['min_stock_alert']);
        
        // Validate prices
        if ($cost_price < 0) {
            $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Cost price cannot be negative!</div>";
        } elseif ($sale_price < 0) {
            $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Sale price cannot be negative!</div>";
        } elseif (!is_numeric($barcode)) {
            $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Barcode must contain only numbers!</div>";
        } elseif (!in_array(strlen($barcode), [10, 11, 12, 13])) {
            $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Barcode must be 10, 11, 12, or 13 digits!</div>";
        } elseif (strlen($barcode) == 13 && !validateEAN13Checksum($barcode)) {
            $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Invalid EAN-13 barcode checksum!</div>";
        } else {
            // Check if barcode already exists
            $check_sql = "SELECT id FROM products WHERE barcode = '$barcode'";
            $check_result = $conn->query($check_sql);
            
            if ($check_result->num_rows > 0) {
                $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Barcode already exists! Please use a different barcode.</div>";
            } else {
                // Insert with cost price
                $sql = "INSERT INTO products (barcode, name, category_id, sale_price, cost_price, stock_quantity, min_stock_alert) 
                        VALUES ('$barcode', '$name', '$category_id', '$sale_price', '$cost_price', '$stock', '$alert')";
                
                if ($conn->query($sql) === TRUE) {
                    $message = "<div class='alert-message alert-success'>Product added successfully!</div>";

                } else {
                    $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Error: " . $conn->error . "</div>";
                }
            }
        }
    }
    
    // Update product
    elseif (isset($_POST['update_product'])) {
        $product_id = intval($_POST['product_id']);
        $barcode = $conn->real_escape_string($_POST['barcode']);
        $name = $conn->real_escape_string($_POST['name']);
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

        $sale_price = floatval($_POST['sale_price']);
        $cost_price = floatval($_POST['cost_price']);
        $stock = intval($_POST['stock_quantity']);
        $alert = intval($_POST['min_stock_alert']);
        
        // Validate prices
        if ($cost_price < 0) {
           $message = "<div class='alert-message alert-error'>Cost price cannot be negative!</div>";

        } elseif ($sale_price < 0) {
            $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Sale price cannot be negative!</div>";
        } elseif (!is_numeric($barcode)) {
            $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Barcode must contain only numbers!</div>";
        } elseif (!in_array(strlen($barcode), [10, 11, 12, 13])) {
            $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Barcode must be 10, 11, 12, or 13 digits!</div>";
        } elseif (strlen($barcode) == 13 && !validateEAN13Checksum($barcode)) {
            $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Invalid EAN-13 barcode checksum!</div>";
        } else {
            // Check if barcode already exists (excluding current product)
            $check_sql = "SELECT id FROM products WHERE barcode = '$barcode' AND id != '$product_id'";
            $check_result = $conn->query($check_sql);
            
            if ($check_result->num_rows > 0) {
                $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Barcode already exists! Please use a different barcode.</div>";
            } else {
                // Update with cost price
                $sql = "UPDATE products SET 
                        barcode = '$barcode',
                        name = '$name',
                        category_id = '$category_id',
                        sale_price = '$sale_price',
                        cost_price = '$cost_price',
                        stock_quantity = '$stock',
                        min_stock_alert = '$alert'
                        WHERE id = '$product_id'";
                
                if ($conn->query($sql) === TRUE) {
                    $message = "<div style='background:#c6f6d5;color:#22543d;padding:10px;border-radius:5px;margin-bottom:20px;'>Product updated successfully!</div>";
                } else {
                    $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Error: " . $conn->error . "</div>";
                }
            }
        }
    }
}

// Handle delete via GET
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Check if product exists in sales before deleting
    $check_sql = "SELECT COUNT(*) as count FROM sale_items WHERE product_id = '$delete_id'";
    $check_result = $conn->query($check_sql);
    $check_data = $check_result->fetch_assoc();
    
    if ($check_data['count'] > 0) {
        $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Cannot delete product. It has been sold in previous transactions!</div>";
    } else {
        $delete_sql = "DELETE FROM products WHERE id = '$delete_id'";
        if ($conn->query($delete_sql) === TRUE) {
            $message = "<div style='background:#c6f6d5;color:#22543d;padding:10px;border-radius:5px;margin-bottom:20px;'>Product deleted successfully!</div>";
        } else {
            $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Error: " . $conn->error . "</div>";
        }
    }
}

// Handle print barcode request
if (isset($_GET['print_barcode'])) {
    $print_id = intval($_GET['print_barcode']);
    $copies = isset($_GET['copies']) ? intval($_GET['copies']) : 1;
    $print_sql = "SELECT barcode, name, sale_price FROM products WHERE id = '$print_id'";
    $print_result = $conn->query($print_sql);
    
    if ($print_result->num_rows > 0) {
        $print_product = $print_result->fetch_assoc();
        // Redirect to print page in a new window/tab
        echo "<script>
            window.open('../prints/print_barcode.php?barcode=" . urlencode($print_product['barcode']) . 
               "&name=" . urlencode($print_product['name']) . 
               "&price=" . urlencode($print_product['sale_price']) . 
               "&copies=" . $copies . "', '_blank');
        </script>";
    }
}

// Get all products with profit calculation - UPDATED WITH SAFE CALCULATIONS
$products_sql = "SELECT p.*, c.name as category_name, 
                        COALESCE((p.sale_price - p.cost_price), 0) as profit,
                        CASE 
                            WHEN (p.sale_price - p.cost_price) > 0 THEN 'profit'
                            WHEN (p.sale_price - p.cost_price) < 0 THEN 'loss'
                            ELSE 'break-even'
                        END as profit_status,
                        CASE 
                            WHEN p.cost_price IS NULL OR p.cost_price = 0 OR p.sale_price IS NULL THEN 0.00
                            ELSE ROUND(((p.sale_price - p.cost_price) / NULLIF(p.cost_price, 0) * 100), 2)
                        END as profit_margin
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 ORDER BY p.id DESC";
$products_result = $conn->query($products_sql);

// Get categories for dropdown
$categories_sql = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - <?php echo $settings['store_name']; ?></title>
    <style>
/* === GLOBAL DENSITY FIX === */
html {
  font-size: 14px;
}

body {
  line-height: 1.35;
}

/* ========== BASE RESET ========== */
:root {
    --spacing-xs: 0.25rem;   /* 4px */
    --spacing-sm: 0.5rem;    /* 8px */
    --spacing-md: 0.75rem;   /* 12px */
    --spacing-lg: 1rem;      /* 16px */
    --spacing-xl: 1.25rem;   /* 20px */
    --spacing-2xl: 1.5rem;   /* 24px */
    
    --font-size-xs: 0.75rem;   /* 12px */
    --font-size-sm: 0.875rem;  /* 14px */
    --font-size-base: 1rem;    /* 16px */
    --font-size-lg: 1.125rem;  /* 18px */
    --font-size-xl: 1.25rem;   /* 20px */
    --font-size-2xl: 1.5rem;   /* 24px */
    
    --input-height: 2.25rem;   /* 36px */
    --button-height: 2.25rem;  /* 36px */
    --border-radius: 0.375rem; /* 6px */
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; 
    background: #f7fafc; 
    font-size: var(--font-size-sm);
    line-height: 1.4;
}

/* ========== LAYOUT CONTAINERS ========== */
.container {
    display: flex;
    min-height: 100vh;
    width: 100%;
    max-width: 100%;
    overflow-x: hidden; /* 🔑 */
}


/* Sidebar - Keep mostly as is, but fix font sizes */
.sidebar {
    width: 16rem; /* 256px - fixed width */
    background: #2d3748;
    color: white;
    padding: var(--spacing-lg) 0;
    flex-shrink: 0; /* Prevent sidebar from shrinking */
}
.sidebar-header { 
    padding: 0 var(--spacing-lg) var(--spacing-lg); 
    border-bottom: 1px solid #4a5568; 
}
.store-name { font-size: var(--font-size-lg); font-weight: 600; }
.store-subtitle { font-size: var(--font-size-xs); color: #cbd5e0; }
.user-info { 
    padding: var(--spacing-lg); 
    border-bottom: 1px solid #4a5568; 
    font-size: var(--font-size-sm);
}
.user-info div:last-child { 
    font-size: var(--font-size-xs);
    color: #cbd5e0;
    margin-top: var(--spacing-xs);
}
.nav { list-style: none; }
.nav-item { border-bottom: 1px solid #4a5568; }
.nav-link {
    display: block;
    padding: var(--spacing-md) var(--spacing-lg);
    color: #cbd5e0;
    text-decoration: none;
    font-size: var(--font-size-sm);
    transition: all 0.15s;
}
.nav-link:hover, .nav-link.active {
    background: #4a5568;
    color: white;
    border-left: 3px solid #667eea;
}

/* ========== MAIN CONTENT AREA (CRITICAL FIX) ========== */
.main {
    flex: 1;
    padding: var(--spacing-xl);
    max-width: calc(100vw - 16rem); /* subtract sidebar width */
    margin: 0;
    overflow-x: hidden;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-lg);
    border-bottom: 1px solid #e2e8f0;
    max-width: 100rem; /* Match main content width */
}
.page-title { 
    font-size: var(--font-size-xl); 
    color: #2d3748; 
    font-weight: 600;
}
.logout-btn {
    background: #fc8181;
    color: white;
    padding: var(--spacing-sm) var(--spacing-lg);
    border-radius: var(--border-radius);
    text-decoration: none;
    font-size: var(--font-size-sm);
    font-weight: 500;
}

/* ========== ALERTS (for PHP messages) ========== */
.alert-message {
    padding: var(--spacing-md) var(--spacing-lg);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-xl);
    font-size: var(--font-size-sm);
    border: 1px solid transparent;
    max-width: 100rem; /* Match main content width */
}
.alert-error {
    background: #fed7d7;
    color: #c53030;
    border-color: #feb2b2;
}
.alert-success {
    background: #c6f6d5;
    color: #22543d;
    border-color: #9ae6b4;
}

/* ========== QUICK STATS ========== */
.quick-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
    max-width: 100rem; /* Match main content width */
}
.stat-card {
    background: white;
    padding: var(--spacing-lg);
    border-radius: var(--border-radius);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid #e2e8f0;
}
.stat-title {
    font-size: var(--font-size-xs);
    color: #718096;
    margin-bottom: var(--spacing-xs);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-value {
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: #2d3748;
}
.stat-profit { color: #38a169; }
.stat-loss { color: #e53e3e; }

/* ========== FORM CONTAINER (CRITICAL WIDTH FIX) ========== */
.form-container {
    background: white;
    padding: var(--spacing-xl);
    border-radius: calc(var(--border-radius) * 1.5);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: var(--spacing-xl);
    border: 1px solid #e2e8f0;
    max-width: 50rem; /* MUCH narrower form (800px vs 1024px) */
    margin-left: auto;
    margin-right: auto;
}
.form-title { 
    font-size: var(--font-size-lg); 
    color: #2d3748; 
    margin-bottom: var(--spacing-lg);
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* ========== FORM LAYOUT ========== */
.form-group { 
    margin-bottom: var(--spacing-md);
}
.form-group label {
    display: block;
    margin-bottom: var(--spacing-xs);
    color: #4a5568;
    font-weight: 500;
    font-size: var(--font-size-sm);
}
.form-group .required::after {
    content: " *";
    color: #f56565;
}

/* Inputs and Selects - COMPACT */
input, select {
    width: 100%;
    padding: 6px 10px;          /* reduced vertical padding */
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
    line-height: 1.2;
    min-height: 32px;           /* reduced height */
    transition: border-color 0.15s, box-shadow 0.15s;
    max-width: 100%;
}

input:focus, select:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

/* Form Grid - Tighter spacing */
.form-row { 
    display: grid; 
    grid-template-columns: repeat(2, 1fr); 
    gap: var(--spacing-lg);
}

/* ========== BARCODE SECTION ========== */
.barcode-input-group {
    display: flex;
    gap: var(--spacing-sm);
}
.barcode-input-group input {
    flex: 1;
    font-family: 'Courier New', monospace;
    font-size: var(--font-size-base);
    letter-spacing: 0.5px;
}
.btn-generate {
    background: #4299e1;
    color: white;
    border: none;
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--border-radius);
    font-size: var(--font-size-sm);
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    min-height: var(--input-height);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}
.btn-generate:hover {
    background: #3182ce;
}

.barcode-validation {
    font-size: var(--font-size-xs);
    margin-top: var(--spacing-xs);
    padding: var(--spacing-xs);
    border-radius: calc(var(--border-radius) / 2);
}
.barcode-valid {
    color: #38a169;
    background: #f0fff4;
    border: 1px solid #c6f6d5;
}
.barcode-invalid {
    color: #e53e3e;
    background: #fff5f5;
    border: 1px solid #fed7d7;
}
.input-help {
    font-size: var(--font-size-xs);
    color: #718096;
    margin-top: var(--spacing-xs);
    line-height: 1.3;
}

/* ========== PRICING SECTION ========== */
.price-warning {
    color: #e53e3e;
    font-size: var(--font-size-xs);
    margin-top: var(--spacing-xs);
    padding: var(--spacing-xs);
    background: #fff5f5;
    border: 1px solid #fed7d7;
    border-radius: calc(var(--border-radius) / 2);
    display: none;
}

/* ========== PROFIT DISPLAY ========== */
.profit-display {
        padding: 8px 10px;

    border: 1px solid #e2e8f0;
    border-radius: var(--border-radius);
    background: #f8fafc;
        font-size: 13px;

}
.profit-positive { color: #38a169; }
.profit-negative { color: #e53e3e; }
.profit-zero { color: #718096; }

.profit-status {
    font-size: var(--font-size-xs);
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: 1rem;
    margin-left: var(--spacing-sm);
    font-weight: 600;
}
.profit-status-profit {
    background: #c6f6d5;
    color: #22543d;
}
.profit-status-loss {
    background: #fed7d7;
    color: #c53030;
}
.profit-status-break-even {
    background: #e2e8f0;
    color: #4a5568;
}

/* ========== FORM BUTTONS ========== */
.form-actions {
    display: flex;
    gap: var(--spacing-md);
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-lg);
    border-top: 1px solid #e2e8f0;
}
.btn-submit, .btn-cancel {
    padding: var(--spacing-sm) var(--spacing-xl);
    border-radius: var(--border-radius);
    font-size: var(--font-size-sm);
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--button-height);
    border: none;
    transition: all 0.15s;
}
.btn-submit {
    background: #48bb78;
    color: white;
}
.btn-submit:hover {
    background: #38a169;
}
.btn-cancel {
    background: #718096;
    color: white;
}
.btn-cancel:hover {
    background: #4a5568;
}

/* ========== TABLE STYLES (Width constraints) ========== */
.table-container {
    background: white;
    border-radius: calc(var(--border-radius) * 1.5);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    padding: var(--spacing-xl);
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    max-width: 100rem; /* Match main content width */
}
.table-title { 
    font-size: var(--font-size-lg); 
    color: #2d3748; 
    margin-bottom: var(--spacing-lg);
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.export-btn {
    background: #4299e1;
    color: white;
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--border-radius);
    text-decoration: none;
    font-size: var(--font-size-sm);
    font-weight: 500;
}
table { 
    width: 100%; 
    border-collapse: collapse; 
    min-width: 70rem; /* Reduced from 80rem */
    font-size: var(--font-size-sm);
}
th { 
    background: #f7fafc; 
    padding: var(--spacing-md); 
    text-align: left; 
    color: #4a5568; 
    font-weight: 600;
    border-bottom: 2px solid #e2e8f0;
    font-size: var(--font-size-sm);
}
td { 
    padding: var(--spacing-md); 
    border-bottom: 1px solid #e2e8f0; 
    vertical-align: middle; 
}
tr:hover {
    background: #f8fafc;
}
.stock-low { color: #f56565; font-weight: 600; }
.stock-ok { color: #48bb78; }
.stock-out { color: #718096; font-style: italic; }

.btn-action {
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: calc(var(--border-radius) / 1.5);
    text-decoration: none;
    font-size: var(--font-size-xs);
    margin-right: var(--spacing-xs);
    display: inline-block;
    border: none;
    cursor: pointer;
    transition: all 0.15s;
    min-height: 1.75rem;
}
.btn-edit { background: #4299e1; color: white; }
.btn-delete { background: #fc8181; color: white; }
.btn-print { background: #ed8936; color: white; }
.btn-action:hover { 
    opacity: 0.9; 
    transform: translateY(-1px); 
    box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
}

/* ========== TABLE CONTROLS ========== */
.table-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    gap: var(--spacing-lg);
}
.search-box {
    flex: 1;
    max-width: 20rem; /* Reduced from 24rem */
}
.search-box input {
    font-size: var(--font-size-sm);
}
.filter-box {
    display: flex;
    gap: var(--spacing-sm);
}
.filter-box select {
    font-size: var(--font-size-sm);
    padding: var(--spacing-sm) var(--spacing-md);
}

/* ========== MODALS ========== */
/* ========== MODALS (Fixed) ========== */
.modal {
    display: none; /* Hidden by default */
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5); /* Dimmed background */
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
}

.modal-content {
    background: white;
    width: 32rem;
    max-width: 95%;
    border-radius: calc(var(--border-radius) * 1.5);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    animation: modalFadeIn 0.2s ease-out;
}

@keyframes modalFadeIn {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.modal-header {
    padding: var(--spacing-lg);
    background: #4299e1;
    color: white;
}

.modal-body {
     padding: 14px 18px;
}

.modal-footer {
    padding: 10px 16px ;
    background: #f7fafc;
    display: flex;
    justify-content: flex-end;
    gap: var(--spacing-md);
    border-top: 1px solid #e2e8f0;
}

.btn-confirm, .btn-cancel-modal {
    padding: 6px 16px;
    min-height: 34px;
    font-size: var(--font-size-sm);
    border-radius: var(--border-radius);
    cursor: pointer;
    border: none;
    font-weight: 500;
}
.modal-content {
    max-height: 90vh;
    overflow-y: auto;
}

.btn-cancel-modal { background: #e2e8f0; color: #4a5568; }
.btn-confirm { background: #e53e3e; color: white; }

/* Print Modal Specific Layout */
.print-options {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-md);
}

.print-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-md);
    border: 1px solid #e2e8f0;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: background 0.2s;
}

.print-option:hover { background: #ebf8ff; border-color: #bee3f8; }
.print-option input[type="radio"] { width: auto; min-height: auto; }

/* ========== COLUMN WIDTH FIXES ========== */
table th:nth-child(8), 
table td:nth-child(8),
table th:nth-child(9),
table td:nth-child(9) {
    width: 85px !important;
    min-width: 85px !important;
    max-width: 85px !important;
    text-align: center !important;
    white-space: nowrap !important;
    padding-left: 8px !important;
    padding-right: 8px !important;
}

table th:nth-child(11),
table td:nth-child(11) {
    width: 180px !important;
    min-width: 180px !important;
    max-width: 180px !important;
    white-space: nowrap !important;
}

table td:nth-child(5), 
table td:nth-child(6), 
table td:nth-child(7), 
table td:nth-child(8), 
table td:nth-child(9) {
    text-align: center !important;
    white-space: nowrap !important;
    font-family: 'Courier New', monospace !important;
    font-weight: 500 !important;
}

table th:nth-child(5),
table th:nth-child(6),
table th:nth-child(7),
table th:nth-child(8),
table th:nth-child(9) {
    text-align: center !important;
}

/* ========== RESPONSIVE DESIGN ========== */
@media (max-width: 1024px) {
    .form-row { grid-template-columns: 1fr; gap: var(--spacing-md); }
    .quick-stats { grid-template-columns: repeat(2, 1fr); }
    .table-controls { flex-direction: column; align-items: stretch; gap: var(--spacing-md); }
    .search-box { max-width: 100%; }
}

@media (max-width: 768px) {
    .container { flex-direction: column; }
    .sidebar { width: 100%; height: auto; }
    .main { padding: var(--spacing-lg); max-width: 100%; }
    .quick-stats { grid-template-columns: 1fr; }
    .form-container { padding: var(--spacing-lg); max-width: 100%; }
    .form-actions { flex-direction: column; }
    .modal-content { width: 90%; }
}
    
    /* Adjust column widths for mobile */
    table th:nth-child(8),
    table td:nth-child(8),
    table th:nth-child(9),
    table td:nth-child(9) {
        width: 80px !important;
        min-width: 80px !important;
        max-width: 80px !important;
    }
    
    table th:nth-child(11),
    table td:nth-child(11) {
        width: 160px !important;
        min-width: 160px !important;
        max-width: 160px !important;
    }
}

/* For very large screens (1920px+) */
@media (min-width: 120rem) {
    .form-container {
        max-width: 56rem; /* 896px - comfortable width for large screens */
    }
    .table-container {
        max-width: 100rem;
        margin: 0 auto;
    }
    .container {
        max-width: 160rem;
    }
}

/* For standard desktop screens (1366px - 1919px) */
@media (min-width: 85.375rem) and (max-width: 119.9375rem) {
    .form-container {
        max-width: 50rem; /* 800px - optimal for standard desktops */
    }
    .table-container {
        max-width: 90rem; /* 1440px */
    }
    .main {
        max-width: 90rem;
    }
}

/* For smaller screens (1366px and below) */
@media (max-width: 85.375rem) {
    .form-container {
        max-width: 100%; /* Use available space on smaller screens */
    }
    table {
        min-width: 60rem; /* Further reduced for smaller screens */
    }
    .main {
        max-width: 100%;
    }
}

/* For very small screens */
@media (max-width: 64rem) {
    .form-container {
        padding: var(--spacing-lg);
    }
    table {
        min-width: 50rem;
    }
}
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
                <div>Welcome, <?php echo $_SESSION['full_name']; ?></div>
                <div>(<?php echo ucfirst($_SESSION['user_role']); ?>)</div>
            </div>
            
            <ul class="nav">
                <li class="nav-item"><a href="index.php" class="nav-link">📊 Dashboard</a></li>
                <li class="nav-item"><a href="products.php" class="nav-link active">📦 Products</a></li>
                <li class="nav-item"><a href="categories.php" class="nav-link">🏷️ Categories</a></li>
                <li class="nav-item"><a href="sales_report.php" class="nav-link">📈 Sales Report</a></li>
                <li class="nav-item"><a href="profit_report.php" class="nav-link">💰 Profit Report</a></li>
                <li class="nav-item"><a href="returns.php" class="nav-link">🔄 Returns</a></li>
                <li class="nav-item"><a href="settings.php" class="nav-link">⚙️ Settings</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <main class="main">
            <div class="header">
                <h1 class="page-title">Product Management</h1>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
            
            <?php echo $message; ?>
            
            <!-- Quick Stats -->
            <?php
            // Calculate quick stats
            $stats_sql = "SELECT 
                        COUNT(*) as total_products,
                        SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock,
                        SUM(CASE WHEN stock_quantity <= min_stock_alert AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
                        COALESCE(SUM((sale_price - cost_price) * stock_quantity), 0) as potential_profit
                        FROM products";
            $stats_result = $conn->query($stats_sql);
            $stats = $stats_result->fetch_assoc();
            ?>
            <div class="quick-stats">
                <div class="stat-card">
                    <div class="stat-title">Total Products</div>
                    <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Out of Stock</div>
                    <div class="stat-value stock-low"><?php echo $stats['out_of_stock']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Low Stock</div>
                    <div class="stat-value stock-low"><?php echo $stats['low_stock']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Potential Profit</div>
                    <div class="stat-value <?php echo $stats['potential_profit'] >= 0 ? 'stat-profit' : 'stat-loss'; ?>">
                       Rs. <?php echo safeNumber($stats['potential_profit']); ?>

                    </div>
                </div>
            </div>
            
            <!-- Add/Edit Product Form -->
            <div class="form-container">
                <h2 class="form-title">Add New Product</h2>
                <form method="POST" action="" id="productForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="barcode">Barcode * (10, 11, 12, or 13 digits)</label>
                            <div class="barcode-input-group">
                                <input type="text" id="barcode" name="barcode" required maxlength="13" oninput="validateBarcode()">
                                <button type="button" class="btn-generate" onclick="generateSingleBarcode()">
                                    🔄 Generate
                                </button>
                            </div>
                            <div id="barcodeValidation" class="barcode-validation"></div>
                            <small style="color: #718096; font-size: 12px; margin-top: 5px; display: block;">
                                Enter 10, 11, or 12-digit numeric barcode, or 13-digit EAN-13 barcode
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="name">Product Name *</label>
                            <input type="text" id="name" name="name" required maxlength="100">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category_id">Category</label>
                            <select id="category_id" name="category_id">
                                <option value="">Select Category</option>
                                <?php 
                                $categories_result->data_seek(0);
                                while($cat = $categories_result->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $cat['id']; ?>">
                                    <?php echo $cat['name']; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="cost_price">Cost Price (Rs.) *</label>
                            <input type="number" step="0.01" id="cost_price" name="cost_price" value="0.00" required min="0" oninput="validatePrices()">
                            <div id="costPriceWarning" class="price-warning"></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sale_price">Sale Price (Rs.) *</label>
                            <input type="number" step="0.01" id="sale_price" name="sale_price" required min="0" oninput="validatePrices()">
                            <div id="salePriceWarning" class="price-warning"></div>
                        </div>
                        <div class="form-group">
                            <label>Profit/Loss Analysis</label>
                            <div id="profitDisplay" class="profit-display">
                                <span id="profitAmount">0.00</span> Rs.
                                <span id="profitStatus" class="profit-status"></span>
                                <br>
                                <small>Margin: <span id="profitMargin">0.00</span>%</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="stock_quantity">Stock Quantity</label>
                            <input type="number" id="stock_quantity" name="stock_quantity" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label for="min_stock_alert">Low Stock Alert</label>
                            <input type="number" id="min_stock_alert" name="min_stock_alert" value="5" min="1">
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <button type="submit" name="add_product" class="btn-submit" id="submitBtn">
                            Add Product
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Products List -->
            <div class="table-container">
                <div class="table-title">
                    <h2>All Products</h2>
                    <a href="export_products.php" class="export-btn">📥 Export to Excel</a>
                </div>
                
                <div class="table-controls">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search by name or barcode..." 
                               onkeyup="filterProducts()">
                    </div>
                    <div class="filter-box">
                        <select id="categoryFilter" onchange="filterProducts()">
                            <option value="">All Categories</option>
                            <?php 
                            $categories_result->data_seek(0);
                            while($cat = $categories_result->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $cat['name']; ?>"><?php echo $cat['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                        <select id="stockFilter" onchange="filterProducts()">
                            <option value="">All Stock</option>
                            <option value="in_stock">In Stock</option>
                            <option value="low_stock">Low Stock</option>
                            <option value="out_of_stock">Out of Stock</option>
                        </select>
                    </div>
                </div>
                
                <table id="productsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Barcode</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Cost Price</th>
                            <th>Sale Price</th>
                            <th>Profit/Loss</th>
                            <th>Margin</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $products_result->data_seek(0);
                        while($product = $products_result->fetch_assoc()): 
                            // Determine stock status
                            if ($product['stock_quantity'] <= 0) {
                                $stock_class = 'stock-out';
                                $status_text = 'Out of Stock';
                            } elseif ($product['stock_quantity'] <= $product['min_stock_alert']) {
                                $stock_class = 'stock-low';
                                $status_text = 'Low Stock';
                            } else {
                                $stock_class = 'stock-ok';
                                $status_text = 'In Stock';
                            }
                            
                            // Determine profit class and status using safe calculations
                            $profit = safeProfit($product['cost_price'], $product['sale_price']);
                            $profit_margin = safeMargin($product['cost_price'], $product['sale_price']);
                            
                            if ((float)$product['sale_price'] > (float)$product['cost_price']) {
                                $profit_class = 'profit-positive';
                                $profit_status_class = 'profit-status-profit';
                                $profit_text = '+' . $profit;
                                $profit_status_text = 'Profit';
                            } elseif ((float)$product['sale_price'] < (float)$product['cost_price']) {
                                $profit_class = 'profit-negative';
                                $profit_status_class = 'profit-status-loss';
                                $profit_text = $profit;
                                $profit_status_text = 'Loss';
                            } else {
                                $profit_class = 'profit-zero';
                                $profit_status_class = 'profit-status-break-even';
                                $profit_text = $profit;
                                $profit_status_text = 'Break Even';
                            }
                            
                            $margin_class = (float)$profit_margin > 0 ? 'profit-positive' : 
                                          ((float)$profit_margin < 0 ? 'profit-negative' : 'profit-zero');
                        ?>
                        <tr>
                            <td><?php echo $product['id']; ?></td>
                            <td><strong><?php echo $product['barcode']; ?></strong></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo $product['category_name'] ?? '-'; ?></td>
                            <td>Rs. <?php echo safeNumber($product['cost_price']); ?></td>
                            <td>Rs. <?php echo safeNumber($product['sale_price']); ?></td>
                            <td>
                                <span class="<?php echo $profit_class; ?>">
                                    <?php echo $profit_text; ?>
                                </span>
                                <span class="profit-status <?php echo $profit_status_class; ?>">
                                    <?php echo $profit_status_text; ?>
                                </span>
                            </td>
                            <td class="<?php echo $margin_class; ?>">
                                <?php echo $profit_margin; ?>%
                            </td>
                            <td class="<?php echo $stock_class; ?>">
                                <?php echo $product['stock_quantity']; ?>
                            </td>
                            <td><span class="<?php echo $stock_class; ?>"><?php echo $status_text; ?></span></td>
                            <td>
                                <button onclick="openEditModal(<?php echo $product['id']; ?>)" class="btn-action btn-edit">✏️ Edit</button>
                                <button onclick="showPrintModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')" 
                                        class="btn-action btn-print">🖨️ Print</button>
                                <button onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')" 
                                        class="btn-action btn-delete">🗑️ Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <?php if ($products_result->num_rows == 0): ?>
                <div style="text-align: center; padding: 40px; color: #718096;">
                    <p style="font-size: 16px;">No products found. Add your first product!</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0;">Confirm Delete</h3>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete product: <strong id="deleteProductName"></strong>?</p>
                <p style="color: #f56565; font-size: 14px;">⚠️ This action cannot be undone!</p>
                <p style="color: #718096; font-size: 12px; margin-top: 10px;">
                    Note: Products that have been sold in previous transactions cannot be deleted.
                </p>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal()" class="btn-cancel-modal">Cancel</button>
                <button onclick="proceedDelete()" class="btn-confirm">Delete</button>
            </div>
        </div>
    </div>

    <!-- Print Barcode Modal -->
    <div id="printModal" class="modal">
        <div class="modal-content print-modal-content">
            <div class="modal-header">
                <h3 style="margin: 0;">Print Barcode</h3>
            </div>
            <div class="modal-body">
                <p>Print barcode for: <strong id="printProductName"></strong></p>

                <div class="print-options">
                    <div class="print-option" onclick="selectPrintOption(1)">
                        <div class="print-option-label">
                            <strong>1 Copy</strong>
                            <div class="print-option-desc">Single label for testing</div>
                        </div>
                        <input type="radio" name="print_copies" id="print1" value="1" checked>
                    </div>

                    <div class="print-option" onclick="selectPrintOption(5)">
                        <div class="print-option-label">
                            <strong>5 Copies</strong>
                            <div class="print-option-desc">Standard quantity</div>
                        </div>
                        <input type="radio" name="print_copies" id="print5" value="5">
                    </div>

                    <div class="print-option" onclick="selectPrintOption(10)">
                        <div class="print-option-label">
                            <strong>10 Copies</strong>
                            <div class="print-option-desc">For inventory use</div>
                        </div>
                        <input type="radio" name="print_copies" id="print10" value="10">
                    </div>

                    <div class="print-option" onclick="selectPrintOption(50)">
                        <div class="print-option-label">
                            <strong>50 Copies</strong>
                            <div class="print-option-desc">For wholesale packaging</div>
                        </div>
                        <input type="radio" name="print_copies" id="print50" value="50">
                    </div>

                    <div class="print-option" onclick="selectPrintOption('custom')">
                        <div class="print-option-label">
                            <strong>Custom</strong>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <input type="number"
                                   id="customCopies"
                                   value="1"
                                   min="1"
                                   max="1000"
                                   disabled
                                   style="width:70px;padding:4px;text-align:center;">
                            <span style="font-size:12px;color:#718096;">copies</span>
                            <input type="radio" name="print_copies" id="printCustom" value="custom">
                        </div>
                    </div>
                </div>

                <div style="margin-top: 16px; padding: 10px; background: #f7fafc; border-radius: 5px; font-size: 12px; color: #4a5568;">
                    <strong>Tip:</strong> Use landscape paper (1.47" × 1") for best results
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closePrintModal()" class="btn-cancel-modal">Cancel</button>
                <button onclick="proceedPrint()" class="btn-confirm" style="background: #ed8936;">🖨️ Print</button>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0;">Edit Product</h3>
            </div>
            <form method="POST" action="" id="editProductForm">
                <div class="modal-body">
                    <input type="hidden" name="product_id" id="editProductId">
                    <input type="hidden" name="update_product" value="1">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="edit_barcode">Barcode * (10, 11, 12, or 13 digits)</label>
                        <div class="barcode-input-group">
                            <input type="text" id="edit_barcode" name="barcode" required maxlength="13" oninput="validateEditBarcode()">
                            <button type="button" class="btn-generate" onclick="generateSingleEditBarcode()">
                                🔄 Generate
                            </button>
                        </div>
                        <div id="editBarcodeValidation" class="barcode-validation"></div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="edit_name">Product Name *</label>
                        <input type="text" id="edit_name" name="name" required maxlength="100">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="edit_category_id">Category</label>
                        <select id="edit_category_id" name="category_id">
                            <option value="">Select Category</option>
                            <?php 
                            $categories_result->data_seek(0);
                            while($cat = $categories_result->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $cat['id']; ?>">
                                <?php echo $cat['name']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_cost_price">Cost Price (Rs.) *</label>
                            <input type="number" step="0.01" id="edit_cost_price" name="cost_price" required min="0" oninput="validateEditPrices()">
                            <div id="editCostPriceWarning" class="price-warning"></div>
                        </div>
                        <div class="form-group">
                            <label for="edit_sale_price">Sale Price (Rs.) *</label>
                            <input type="number" step="0.01" id="edit_sale_price" name="sale_price" required min="0" oninput="validateEditPrices()">
                            <div id="editSalePriceWarning" class="price-warning"></div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Profit/Loss Analysis</label>
                        <div id="editProfitDisplay" class="profit-display">
                            <span id="editProfitAmount">0.00</span> Rs.
                            <span id="editProfitStatus" class="profit-status"></span>
                            <br>
                            <small>Margin: <span id="editProfitMargin">0.00</span>%</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_stock_quantity">Stock Quantity</label>
                            <input type="number" id="edit_stock_quantity" name="stock_quantity" min="0">
                        </div>
                        <div class="form-group">
                            <label for="edit_min_stock_alert">Low Stock Alert</label>
                            <input type="number" id="edit_min_stock_alert" name="min_stock_alert" min="1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeEditModal()" class="btn-cancel-modal">Cancel</button>
                    <button type="submit" class="btn-confirm" style="background: #4299e1;">Update Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let productToDelete = null;
        let productNameToDelete = '';
        let productToPrint = null;
        let productNameToPrint = '';
        let selectedCopies = 1;
        
        // Generate a single valid EAN-13 barcode
        function generateSingleBarcode() {
            let prefix = '123';
            let company = Math.floor(Math.random() * 100000).toString().padStart(5, '0');
            let product = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
            let barcodeWithoutCheck = prefix + company + product;
            
            let sum = 0;
            for (let i = 0; i < 12; i++) {
                let digit = parseInt(barcodeWithoutCheck[i]);
                sum += (i % 2 === 0) ? digit * 1 : digit * 3;
            }
            let checkDigit = (10 - (sum % 10)) % 10;
            let fullBarcode = barcodeWithoutCheck + checkDigit;
            
            document.getElementById('barcode').value = fullBarcode;
            validateBarcode();
        }

        function generateSingleEditBarcode() {
            let prefix = '123';
            let company = Math.floor(Math.random() * 100000).toString().padStart(5, '0');
            let product = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
            let barcodeWithoutCheck = prefix + company + product;
            
            let sum = 0;
            for (let i = 0; i < 12; i++) {
                let digit = parseInt(barcodeWithoutCheck[i]);
                sum += (i % 2 === 0) ? digit * 1 : digit * 3;
            }
            let checkDigit = (10 - (sum % 10)) % 10;
            let fullBarcode = barcodeWithoutCheck + checkDigit;
            
            document.getElementById('edit_barcode').value = fullBarcode;
            validateEditBarcode();
        }
        
        // Validate barcode
        function validateBarcode() {
            const barcodeInput = document.getElementById('barcode');
            const validationDiv = document.getElementById('barcodeValidation');
            const submitBtn = document.getElementById('submitBtn');
            const barcode = barcodeInput.value.trim();
            
            if (barcode.length === 0) {
                validationDiv.textContent = '';
                validationDiv.className = 'barcode-validation';
                submitBtn.disabled = false;
                return;
            }
            
            // Check if contains only numbers
            if (!/^\d+$/.test(barcode)) {
                validationDiv.textContent = '❌ Barcode must contain only numbers';
                validationDiv.className = 'barcode-validation barcode-invalid';
                submitBtn.disabled = true;
                return;
            }
            
            const length = barcode.length;
            const validLengths = [10, 11, 12, 13];
            
            if (!validLengths.includes(length)) {
                validationDiv.textContent = `❌ Barcode must be ${validLengths.join(', ')} digits (currently ${length})`;
                validationDiv.className = 'barcode-validation barcode-invalid';
                submitBtn.disabled = true;
                return;
            }
            
            if (length === 10 || length === 11 || length === 12) {
                validationDiv.textContent = `✅ Valid ${length}-digit barcode`;
                validationDiv.className = 'barcode-validation barcode-valid';
                submitBtn.disabled = false;
                return;
            }
            
            if (length === 13) {
                let sum = 0;
                for (let i = 0; i < 12; i++) {
                    let digit = parseInt(barcode[i]);
                    sum += (i % 2 === 0) ? digit * 1 : digit * 3;
                }
                let checkDigit = (10 - (sum % 10)) % 10;
                let actualCheckDigit = parseInt(barcode[12]);
                
                if (checkDigit !== actualCheckDigit) {
                    validationDiv.textContent = `❌ Invalid EAN-13 checksum. Expected ${checkDigit}, got ${actualCheckDigit}`;
                    validationDiv.className = 'barcode-validation barcode-invalid';
                    submitBtn.disabled = true;
                    return;
                }
                
                validationDiv.textContent = '✅ Valid EAN-13 barcode';
                validationDiv.className = 'barcode-validation barcode-valid';
                submitBtn.disabled = false;
                return;
            }
        }

        function validateEditBarcode() {
            const barcodeInput = document.getElementById('edit_barcode');
            const validationDiv = document.getElementById('editBarcodeValidation');
            const submitBtn = document.querySelector('#editProductForm button[type="submit"]');
            const barcode = barcodeInput.value.trim();
            
            if (barcode.length === 0) {
                validationDiv.textContent = '';
                validationDiv.className = 'barcode-validation';
                submitBtn.disabled = false;
                return;
            }
            
            // Check if contains only numbers
            if (!/^\d+$/.test(barcode)) {
                validationDiv.textContent = '❌ Barcode must contain only numbers';
                validationDiv.className = 'barcode-validation barcode-invalid';
                submitBtn.disabled = true;
                return;
            }
            
            const length = barcode.length;
            const validLengths = [10, 11, 12, 13];
            
            if (!validLengths.includes(length)) {
                validationDiv.textContent = `❌ Barcode must be ${validLengths.join(', ')} digits (currently ${length})`;
                validationDiv.className = 'barcode-validation barcode-invalid';
                submitBtn.disabled = true;
                return;
            }
            
            if (length === 10 || length === 11 || length === 12) {
                validationDiv.textContent = `✅ Valid ${length}-digit barcode`;
                validationDiv.className = 'barcode-validation barcode-valid';
                submitBtn.disabled = false;
                return;
            }
            
            if (length === 13) {
                let sum = 0;
                for (let i = 0; i < 12; i++) {
                    let digit = parseInt(barcode[i]);
                    sum += (i % 2 === 0) ? digit * 1 : digit * 3;
                }
                let checkDigit = (10 - (sum % 10)) % 10;
                let actualCheckDigit = parseInt(barcode[12]);
                
                if (checkDigit !== actualCheckDigit) {
                    validationDiv.textContent = `❌ Invalid EAN-13 checksum. Expected ${checkDigit}, got ${actualCheckDigit}`;
                    validationDiv.className = 'barcode-validation barcode-invalid';
                    submitBtn.disabled = true;
                    return;
                }
                
                validationDiv.textContent = '✅ Valid EAN-13 barcode';
                validationDiv.className = 'barcode-validation barcode-valid';
                submitBtn.disabled = false;
                return;
            }
        }
        
        // Validate prices and calculate profit
        function validatePrices() {
            const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
            const salePrice = parseFloat(document.getElementById('sale_price').value) || 0;
            const profitAmount = document.getElementById('profitAmount');
            const profitStatus = document.getElementById('profitStatus');
            const profitMargin = document.getElementById('profitMargin');
            const costWarning = document.getElementById('costPriceWarning');
            const saleWarning = document.getElementById('salePriceWarning');
            const submitBtn = document.getElementById('submitBtn');
            
            // Calculate profit and margin safely
            const profit = salePrice - costPrice;
            let margin = 0;
            if (costPrice > 0) {
                margin = (profit / costPrice) * 100;
            } else if (salePrice > 0) {
                margin = 100;
            }
            
            profitAmount.textContent = profit.toFixed(2);
            profitMargin.textContent = margin.toFixed(2);
            
            // Set profit status
            if (profit > 0) {
                profitStatus.textContent = 'Profit';
                profitStatus.className = 'profit-status profit-status-profit';
                profitAmount.className = 'profit-positive';
                profitMargin.className = 'profit-positive';
            } else if (profit < 0) {
                profitStatus.textContent = 'Loss';
                profitStatus.className = 'profit-status profit-status-loss';
                profitAmount.className = 'profit-negative';
                profitMargin.className = 'profit-negative';
            } else {
                profitStatus.textContent = 'Break Even';
                profitStatus.className = 'profit-status profit-status-break-even';
                profitAmount.className = 'profit-zero';
                profitMargin.className = 'profit-zero';
            }
            
            // Show warnings if cost price > sale price
            if (costPrice > salePrice && salePrice > 0) {
                costWarning.textContent = '⚠️ Cost price is higher than sale price!';
                costWarning.style.display = 'block';
                saleWarning.textContent = '⚠️ Sale price is lower than cost price!';
                saleWarning.style.display = 'block';
            } else {
                costWarning.style.display = 'none';
                saleWarning.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.style.background = '#48bb78';
            }
        }

        function validateEditPrices() {
            const costPrice = parseFloat(document.getElementById('edit_cost_price').value) || 0;
            const salePrice = parseFloat(document.getElementById('edit_sale_price').value) || 0;
            const profitAmount = document.getElementById('editProfitAmount');
            const profitStatus = document.getElementById('editProfitStatus');
            const profitMargin = document.getElementById('editProfitMargin');
            const costWarning = document.getElementById('editCostPriceWarning');
            const saleWarning = document.getElementById('editSalePriceWarning');
            const submitBtn = document.querySelector('#editProductForm button[type="submit"]');
            
            // Calculate profit and margin safely
            const profit = salePrice - costPrice;
            let margin = 0;
            if (costPrice > 0) {
                margin = (profit / costPrice) * 100;
            } else if (salePrice > 0) {
                margin = 100;
            }
            
            profitAmount.textContent = profit.toFixed(2);
            profitMargin.textContent = margin.toFixed(2);
            
            // Set profit status
            if (profit > 0) {
                profitStatus.textContent = 'Profit';
                profitStatus.className = 'profit-status profit-status-profit';
                profitAmount.className = 'profit-positive';
                profitMargin.className = 'profit-positive';
            } else if (profit < 0) {
                profitStatus.textContent = 'Loss';
                profitStatus.className = 'profit-status profit-status-loss';
                profitAmount.className = 'profit-negative';
                profitMargin.className = 'profit-negative';
            } else {
                profitStatus.textContent = 'Break Even';
                profitStatus.className = 'profit-status profit-status-break-even';
                profitAmount.className = 'profit-zero';
                profitMargin.className = 'profit-zero';
            }
            
            // Show warnings if cost price > sale price
            if (costPrice > salePrice && salePrice > 0) {
                costWarning.textContent = '⚠️ Cost price is higher than sale price!';
                costWarning.style.display = 'block';
                saleWarning.textContent = '⚠️ Sale price is lower than cost price!';
                saleWarning.style.display = 'block';
            } else {
                costWarning.style.display = 'none';
                saleWarning.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.style.background = '#4299e1';
            }
        }
        
        // Confirm delete function
        function confirmDelete(productId, productName) {
            productToDelete = productId;
            productNameToDelete = productName;
            document.getElementById('deleteProductName').textContent = productName;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        // Open edit modal and load product data
        function openEditModal(productId) {
            fetch(`../ajax/get_product.php?id=${productId}`)

                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        document.getElementById('editProductId').value = data.product.id;
                        document.getElementById('edit_barcode').value = data.product.barcode;
                        document.getElementById('edit_name').value = data.product.name;
                        document.getElementById('edit_category_id').value = data.product.category_id;
                        document.getElementById('edit_cost_price').value = data.product.cost_price;
                        document.getElementById('edit_sale_price').value = data.product.sale_price;
                        document.getElementById('edit_stock_quantity').value = data.product.stock_quantity;
                        document.getElementById('edit_min_stock_alert').value = data.product.min_stock_alert;
                        
                        validateEditBarcode();
                        validateEditPrices();
                        document.getElementById('editModal').style.display = 'flex';
                    } else {
                        alert('Error loading product data: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading product data. Please try again.');
                });
        }
        
        // Show print modal
        function showPrintModal(productId, productName) {
            productToPrint = productId;
            productNameToPrint = productName;
            document.getElementById('printProductName').textContent = productName;
            document.getElementById('printModal').style.display = 'flex';
        }
        
        // Select print option
        function selectPrintOption(copies) {
            if (copies === 'custom') {
                document.getElementById('printCustom').checked = true;
                document.getElementById('customCopies').disabled = false;
                document.getElementById('customCopies').focus();
                selectedCopies = parseInt(document.getElementById('customCopies').value) || 1;
            } else {
                selectedCopies = copies;
                document.getElementById('customCopies').disabled = true;
                document.querySelector(`input[value="${copies}"]`).checked = true;
            }
        }
        
        // Close modals
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
            productToDelete = null;
            productNameToDelete = '';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            // Reset form validation
            document.getElementById('editBarcodeValidation').textContent = '';
            document.getElementById('editBarcodeValidation').className = 'barcode-validation';
        }
        
        function closePrintModal() {
            document.getElementById('printModal').style.display = 'none';
            productToPrint = null;
            productNameToPrint = '';
            selectedCopies = 1;
        }
        
        // Proceed with delete
        function proceedDelete() {
            if (productToDelete) {
                window.location.href = '?delete_id=' + productToDelete;
            }
        }
        
        // Proceed with print
        function proceedPrint() {
            if (productToPrint) {
                let copies = selectedCopies;
                if (document.getElementById('printCustom').checked) {
                    copies = parseInt(document.getElementById('customCopies').value) || 1;
                }
                window.location.href = `?print_barcode=${productToPrint}&copies=${copies}`;
            }
        }
        
        // Filter products
        function filterProducts() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            const stockFilter = document.getElementById('stockFilter').value;
            const rows = document.querySelectorAll('#productsTable tbody tr');
            
            rows.forEach(row => {
                const name = row.cells[2].textContent.toLowerCase();
                const barcode = row.cells[1].textContent.toLowerCase();
                const category = row.cells[3].textContent;
                const stockText = row.cells[8].textContent;
                const stock = parseInt(stockText);
                const minStock = parseInt(row.cells[9].querySelector('span').getAttribute('data-min') || 5);
                
                let show = true;
                
                // Search filter
                if (searchInput && !name.includes(searchInput) && !barcode.includes(searchInput)) {
                    show = false;
                }
                
                // Category filter
                if (categoryFilter && category !== categoryFilter) {
                    show = false;
                }
                
                // Stock filter
                if (stockFilter === 'in_stock' && (stock <= 0 || stock <= minStock)) {
                    show = false;
                } else if (stockFilter === 'low_stock' && (stock <= 0 || stock > minStock)) {
                    show = false;
                } else if (stockFilter === 'out_of_stock' && stock > 0) {
                    show = false;
                }
                
                row.style.display = show ? '' : 'none';
            });
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            const editModal = document.getElementById('editModal');
            const printModal = document.getElementById('printModal');
            
            if (event.target == deleteModal) {
                closeModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == printModal) {
                closePrintModal();
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus first input
            const firstInput = document.querySelector('input');
            if (firstInput) {
                firstInput.focus();
            }
            
            // Initialize validations
            validateBarcode();
            validatePrices();
            
            // Add event listeners
            document.getElementById('customCopies').addEventListener('input', function() {
                if (this.value > 1000) this.value = 1000;
                if (this.value < 1) this.value = 1;
                selectedCopies = parseInt(this.value) || 1;
            });
            
            document.getElementById('barcode').addEventListener('input', validateBarcode);
            document.getElementById('cost_price').addEventListener('input', validatePrices);
            document.getElementById('sale_price').addEventListener('input', validatePrices);
            
            // Add edit form event listeners
            document.getElementById('edit_barcode').addEventListener('input', validateEditBarcode);
            document.getElementById('edit_cost_price').addEventListener('input', validateEditPrices);
            document.getElementById('edit_sale_price').addEventListener('input', validateEditPrices);
            
            // Add edit form validation
            document.getElementById('editProductForm').addEventListener('submit', function(e) {
                const barcode = document.getElementById('edit_barcode').value;
                const length = barcode.length;
                const validLengths = [10, 11, 12, 13];
                
                if (!validLengths.includes(length)) {
                    e.preventDefault();
                    alert(`Barcode must be ${validLengths.join(', ')} digits!`);
                    document.getElementById('edit_barcode').focus();
                    document.getElementById('edit_barcode').select();
                    return;
                }
                
                if (!/^\d+$/.test(barcode)) {
                    e.preventDefault();
                    alert('Barcode must contain only numbers!');
                    document.getElementById('edit_barcode').focus();
                    document.getElementById('edit_barcode').select();
                    return;
                }
                
                if (length === 13) {
                    let sum = 0;
                    for (let i = 0; i < 12; i++) {
                        let digit = parseInt(barcode[i]);
                        sum += (i % 2 === 0) ? digit * 1 : digit * 3;
                    }
                    let checkDigit = (10 - (sum % 10)) % 10;
                    let actualCheckDigit = parseInt(barcode[12]);
                    
                    if (checkDigit !== actualCheckDigit) {
                        e.preventDefault();
                        alert(`Invalid EAN-13 barcode! Checksum should be ${checkDigit}, but found ${actualCheckDigit}`);
                        document.getElementById('edit_barcode').focus();
                        document.getElementById('edit_barcode').select();
                        return;
                    }
                }
                
                // Price validation
                const costPrice = parseFloat(document.getElementById('edit_cost_price').value);
                const salePrice = parseFloat(document.getElementById('edit_sale_price').value);
                
                if (costPrice < 0) {
                    e.preventDefault();
                    alert('Cost price cannot be negative!');
                    document.getElementById('edit_cost_price').focus();
                    return;
                }
                
                if (salePrice < 0) {
                    e.preventDefault();
                    alert('Sale price cannot be negative!');
                    document.getElementById('edit_sale_price').focus();
                    return;
                }
                
                // Optional: Confirm if cost price > sale price
                if (costPrice > salePrice) {
                    if (!confirm('⚠️ Cost price is higher than sale price! This will result in a loss. Continue anyway?')) {
                        e.preventDefault();
                        return;
                    }
                }
            });
            
            // Add data-min attribute for stock filtering
            const stockCells = document.querySelectorAll('#productsTable tbody tr');
            stockCells.forEach(row => {
                const statusCell = row.cells[9];
                const statusText = statusCell.querySelector('span').textContent;
                let minStock = 5;
                if (statusText === 'Low Stock') {
                    minStock = 5; // Default value
                }
                statusCell.querySelector('span').setAttribute('data-min', minStock);
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeEditModal();
                closePrintModal();
            }
            
            // Generate barcode with Ctrl+G
            if (e.ctrlKey && e.key === 'g') {
                e.preventDefault();
                generateSingleBarcode();
            }
            
            // Focus search
            if (e.key === '/' && !e.target.matches('input, select, textarea')) {
                e.preventDefault();
                const searchInput = document.getElementById('searchInput');
                if (searchInput) searchInput.focus();
            }
        });
        
        // Form validation
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const barcode = document.getElementById('barcode').value;
            const length = barcode.length;
            const validLengths = [10, 11, 12, 13];
            
            if (!validLengths.includes(length)) {
                e.preventDefault();
                alert(`Barcode must be ${validLengths.join(', ')} digits!`);
                document.getElementById('barcode').focus();
                document.getElementById('barcode').select();
                return;
            }
            
            if (!/^\d+$/.test(barcode)) {
                e.preventDefault();
                alert('Barcode must contain only numbers!');
                document.getElementById('barcode').focus();
                document.getElementById('barcode').select();
                return;
            }
            
            if (length === 13) {
                let sum = 0;
                for (let i = 0; i < 12; i++) {
                    let digit = parseInt(barcode[i]);
                    sum += (i % 2 === 0) ? digit * 1 : digit * 3;
                }
                let checkDigit = (10 - (sum % 10)) % 10;
                let actualCheckDigit = parseInt(barcode[12]);
                
                if (checkDigit !== actualCheckDigit) {
                    e.preventDefault();
                    alert(`Invalid EAN-13 barcode! Checksum should be ${checkDigit}, but found ${actualCheckDigit}`);
                    document.getElementById('barcode').focus();
                    document.getElementById('barcode').select();
                    return;
                }
            }
            
            // Price validation
            const costPrice = parseFloat(document.getElementById('cost_price').value);
            const salePrice = parseFloat(document.getElementById('sale_price').value);
            
            if (costPrice < 0) {
                e.preventDefault();
                alert('Cost price cannot be negative!');
                document.getElementById('cost_price').focus();
                return;
            }
            
            if (salePrice < 0) {
                e.preventDefault();
                alert('Sale price cannot be negative!');
                document.getElementById('sale_price').focus();
                return;
            }
            
            // Optional: Confirm if cost price > sale price
            if (costPrice > salePrice) {
                if (!confirm('⚠️ Cost price is higher than sale price! This will result in a loss. Continue anyway?')) {
                    e.preventDefault();
                    return;
                }
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>