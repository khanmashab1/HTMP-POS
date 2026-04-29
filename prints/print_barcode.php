<?php
require_once __DIR__ . '/../includes/config.php';


$barcode = $_GET['barcode'] ?? '';
$name = $_GET['name'] ?? '';
$price = $_GET['price'] ?? '';
$copies = isset($_GET['copies']) ? max(1, intval($_GET['copies'])) : 1;
$type = $_GET['type'] ?? 'code128';


if (empty($barcode)) {
    die('No barcode provided');
}

function getBarcodeImage($barcode, $type = 'code128', $width = 200, $height = 40) {

    // Supported barcode types
    $type_map = [
        'code128' => 'Code128',
        'ean13'   => 'EAN13',
        'code39'  => 'Code39',
        'upca'    => 'UPCA',
        'ean8'    => 'EAN8'
    ];

    // Default to Code128 (SAFE)
    $barcode_type = $type_map[$type] ?? 'Code128';

    // 🔒 Safety fallback: EAN-13 MUST be exactly 13 digits
    if ($barcode_type === 'EAN13' && !preg_match('/^\d{13}$/', $barcode)) {
        $barcode_type = 'Code128';
    }

    $encoded_barcode = urlencode($barcode);

    return "https://barcode.tec-it.com/barcode.ashx"
        . "?data={$encoded_barcode}"
        . "&code={$barcode_type}"
        . "&multiplebarcodes=false"
        . "&translate-esc=false"
        . "&unit=Fit"
        . "&dpi=300"
        . "&imagetype=Gif"
        . "&rotation=0"
        . "&color=%23000000"
        . "&bgcolor=%23ffffff"
        . "&qunit=Mm"
        . "&quiet=0"
        . "&width={$width}"
        . "&height={$height}"
        . "&textfont=Arial"
        . "&textsize=6";
}


// Get product details from database if not provided
if (empty($name) || empty($price)) {
    try {
        $stmt = $db->prepare("SELECT product_name, selling_price FROM products WHERE barcode = ? OR product_id = ?");
        $stmt->execute([$barcode, $barcode]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            if (empty($name)) $name = $product['product_name'];
            if (empty($price)) $price = $product['selling_price'];
        }
    } catch (Exception $e) {
        // Silently fail, use provided values
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Barcode - <?php echo htmlspecialchars($barcode); ?></title>
    <style>
        /* ========== PRINT STYLES ========== */
        @media print {

    /* ===============================
       PAGE SETUP (DO NOT SCALE)
       =============================== */
    @page {
        size: A4 landscape;
        margin: 0;
    }

    html, body {
        width: 100%;
        height: 100%;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
    }

    /* ===============================
       BODY LAYOUT – CENTER SAFE
       =============================== */
    body {
        background: #fff !important;
        display: flex !important;
        flex-wrap: wrap !important;
        justify-content: center !important;   /* 🔥 FIX: center from left */
        align-content: flex-start !important;
        transform-origin: center center !important; /* 🔥 FIX: scaling origin */
    }

    .no-print {
        display: none !important;
    }

    /* ===============================
       BARCODE LABEL SIZE (REAL SIZE)
       =============================== */
    .barcode-label {
        width: 1.47in !important;
        height: 1in !important;
        margin: 0 !important;
        padding: 0.03in !important;
        box-sizing: border-box !important;

        page-break-inside: avoid !important;
        break-inside: avoid !important;

        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}


        /* ========== SCREEN STYLES ========== */
        @media screen {
            body {
                padding: 20px;
                background: #f5f5f5;
            }
            
            .barcode-label {
                border: 1px solid #ddd;
                border-radius: 4px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                background: white;
                margin-bottom: 10px;
                display: inline-block;
                vertical-align: top;
            }
        }

        /* ========== LANDSCAPE LABEL LAYOUT ========== */
        .barcode-label {
            width: 1.47in;  /* Wider */
            height: 1in;    /* Shorter */
            padding: 0.03in;
            box-sizing: border-box;
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
        }

        /* Product name - now has more width */
        .product-name {
            font-size: 6pt;
            font-weight: bold;
            margin: 0;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            max-height: 0.15in;
            text-align: center;
        }

        /* Barcode image - wider for landscape */
        .barcode-image {
            width: 100%;
            height: 0.45in; /* Slightly shorter but wider */
            object-fit: contain;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        /* Barcode number */
        .barcode-text {
            font-family: 'Courier New', monospace;
            font-size: 5pt;
            margin: 0.01in 0;
            letter-spacing: 0.3pt;
            text-align: center;
            font-weight: bold;
        }

        /* Price - more prominent */
        .price {
            font-size: 6.5pt;
            font-weight: bold;
            color: #000;
            margin: 0;
            padding: 0.01in 0.02in;
            text-align: center;
            background: #f8f8f8;
            border-radius: 2px;
            width: 100%;
            box-sizing: border-box;
        }

        /* ========== PRINT CONTROLS ========== */
        .no-print {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 800px;
            margin: 0 auto 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }

        .print-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .control-group {
            display: flex;
            flex-direction: column;
        }

        .control-group label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .control-group input,
        .control-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(1.47in, 1fr));
            gap: 15px;
            justify-items: center;
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }

        .preview-label {
            border: 1px dashed #ccc;
            background: white;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .page-info {
            text-align: center;
            margin-top: 10px;
            color: #666;
            font-size: 13px;
        }

        /* Alert for important info */
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 13px;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        /* Layout options */
        .layout-options {
            display: flex;
            gap: 15px;
            margin: 15px 0;
        }

        .layout-btn {
            padding: 8px 16px;
            border: 2px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .layout-btn.active {
            border-color: #007bff;
            background: #e7f1ff;
            color: #007bff;
        }

        .layout-btn i {
            font-size: 16px;
        }
    </style>
</head>
<body>
    <!-- Print Controls -->
    <div class="no-print">
        <h2 style="text-align: center; margin-bottom: 20px; color: #333;">
            🏷️ Print Barcode Labels
        </h2>
        
        <div class="layout-options">
            <button class="layout-btn active" onclick="setLandscape(true)">
                📐 Landscape (1.47" × 1")
            </button>
            <button class="layout-btn" onclick="setLandscape(false)">
                📏 Portrait (1" × 1.47")
            </button>
        </div>
        
        <div class="print-controls">
            <div class="control-group">
                <label for="copies">Number of Copies:</label>
                <input type="number" id="copies" name="copies" value="<?php echo $copies; ?>" min="1" max="100">
            </div>
            
            <div class="control-group">
                <label for="barcodeType">Barcode Type:</label>
                <select id="barcodeType" name="type">
                    <option value="ean13" <?php echo $type === 'ean13' ? 'selected' : ''; ?>>EAN-13</option>
                    <option value="code128" <?php echo $type === 'code128' ? 'selected' : ''; ?>>Code 128</option>
                    <option value="code39" <?php echo $type === 'code39' ? 'selected' : ''; ?>>Code 39</option>
                    <option value="upca" <?php echo $type === 'upca' ? 'selected' : ''; ?>>UPC-A</option>
                </select>
            </div>
            
            <div class="control-group">
                <label for="productName">Product Name:</label>
                <input type="text" id="productName" name="name" value="<?php echo htmlspecialchars($name); ?>" maxlength="40">
            </div>
            
            <div class="control-group">
                <label for="productPrice">Price (PKR):</label>
                <input type="number" id="productPrice" name="price" value="<?php echo $price; ?>" step="0.01" min="0">
            </div>
        </div>

        <div class="alert alert-info">
            <strong>Label Size:</strong> 1.47 inch × 1 inch (Landscape) | 
            <strong>Layout:</strong> 6×2 labels per A4 sheet
        </div>

        <div class="alert alert-warning">
            <strong>Printer Setup:</strong> Set paper to "Custom: 1.47in × 1in", orientation to "Landscape", margins to "None"
        </div>

        <div class="action-buttons">
            <button onclick="updatePreview()" class="btn btn-secondary">
                🔄 Update Preview
            </button>
            <button onclick="printLabels()" class="btn btn-primary">
                🖨️ Print Labels
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                ✕ Close Window
            </button>
        </div>

        <!-- Preview Area -->
        <div class="page-info">
            Preview (<?php echo $copies; ?> copies) - Landscape Orientation:
        </div>
        <div class="preview-grid" id="previewContainer">
            <?php for($i = 0; $i < min($copies, 12); $i++): ?>
            <div class="barcode-label preview-label">
                <?php if ($name): ?>
                <div class="product-name"><?php echo htmlspecialchars($name); ?></div>
                <?php endif; ?>
                
                <img src="<?php echo getBarcodeImage($barcode, $type, 200, 40); ?>" 
                     alt="Barcode" class="barcode-image" id="barcodeImg">
                
                <div class="barcode-text"><?php echo $barcode; ?></div>
                
                <?php if ($price): ?>
                <div class="price">PKR <?php echo number_format($price, 2); ?></div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Print Labels (hidden on screen, shown when printing) -->
    <?php for($i = 0; $i < $copies; $i++): ?>
    <div class="barcode-label">
        <?php if ($name): ?>
        <div class="product-name"><?php echo htmlspecialchars($name); ?></div>
        <?php endif; ?>
        
        <img src="<?php echo getBarcodeImage($barcode, $type, 200, 40); ?>" 
             alt="Barcode" class="barcode-image">
        
        <div class="barcode-text"><?php echo $barcode; ?></div>
        
        <?php if ($price): ?>
        <div class="price">PKR <?php echo number_format($price, 2); ?></div>
        <?php endif; ?>
    </div>
    <?php endfor; ?>

    <script>
        let isLandscape = true;

        // Function to set orientation
        function setLandscape(landscape) {
            isLandscape = landscape;
            const landscapeBtn = document.querySelector('.layout-btn:first-child');
            const portraitBtn = document.querySelector('.layout-btn:last-child');
            
            if (landscape) {
                landscapeBtn.classList.add('active');
                portraitBtn.classList.remove('active');
                document.querySelector('.alert-info strong').textContent = 'Label Size: 1.47 inch × 1 inch (Landscape)';
                document.querySelector('.alert-info em').textContent = 'Layout: 6×2 labels per A4 sheet';
            } else {
                portraitBtn.classList.add('active');
                landscapeBtn.classList.remove('active');
                document.querySelector('.alert-info strong').textContent = 'Label Size: 1 inch × 1.47 inch (Portrait)';
                document.querySelector('.alert-info em').textContent = 'Layout: 2×4 labels per A4 sheet';
            }
            
            updatePreview();
        }

        // Function to update preview
        function updatePreview() {
            const barcode = "<?php echo $barcode; ?>";
            const name = document.getElementById('productName').value;
            const price = document.getElementById('productPrice').value;
            const copies = document.getElementById('copies').value;
            const type = document.getElementById('barcodeType').value;
            
            // Update preview container style
            const previewContainer = document.getElementById('previewContainer');
            previewContainer.style.gridTemplateColumns = isLandscape 
                ? 'repeat(auto-fill, minmax(1.47in, 1fr))' 
                : 'repeat(auto-fill, minmax(1in, 1fr))';
            
            previewContainer.innerHTML = '';
            
            const maxPreview = 12;
            const previewCount = Math.min(copies, maxPreview);
            
            for(let i = 0; i < previewCount; i++) {
                const label = document.createElement('div');
                label.className = 'barcode-label preview-label';
                
                // Adjust label dimensions
                if (!isLandscape) {
                    label.style.width = '1in';
                    label.style.height = '1.47in';
                } else {
                    label.style.width = '1.47in';
                    label.style.height = '1in';
                }
                
                let html = '';
                if (name) {
                    html += `<div class="product-name">${name}</div>`;
                }
                
                // Update barcode image dimensions based on orientation
                const barcodeWidth = isLandscape ? 200 : 180;
                const barcodeHeight = isLandscape ? 40 : 50;
                
                const barcodeImg = `https://barcode.tec-it.com/barcode.ashx?data=${encodeURIComponent(barcode)}&code=${getBarcodeTypeCode(type)}&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=300&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&codepage=&qunit=Mm&quiet=0&width=${barcodeWidth}&height=${barcodeHeight}&textfont=Arial&textsize=6`;
                
                html += `<img src="${barcodeImg}" alt="Barcode" class="barcode-image" style="height: ${isLandscape ? '0.45in' : '0.55in'}">`;
                html += `<div class="barcode-text">${barcode}</div>`;
                
                if (price) {
                    html += `<div class="price">PKR ${parseFloat(price).toFixed(2)}</div>`;
                }
                
                label.innerHTML = html;
                previewContainer.appendChild(label);
            }
            
            // Update page info
            document.querySelector('.page-info').textContent = `Preview (${copies} copies) - ${isLandscape ? 'Landscape' : 'Portrait'} Orientation:`;
        }

        // Helper function to get barcode type code
        function getBarcodeTypeCode(type) {
            const types = {
                'ean13': 'EAN13',
                'code128': 'Code128',
                'code39': 'Code39',
                'upca': 'UPCA',
                'ean8': 'EAN8'
            };
            return types[type] || 'EAN13';
        }

        // Function to print labels
        function printLabels() {
            // Update print labels with current values
            const name = document.getElementById('productName').value;
            const price = document.getElementById('productPrice').value;
            const copies = document.getElementById('copies').value;
            const type = document.getElementById('barcodeType').value;
            const barcode = "<?php echo $barcode; ?>";
            
            // Remove existing print labels
            const printLabels = document.querySelectorAll('.barcode-label:not(.preview-label)');
            printLabels.forEach(label => label.remove());
            
            // Create new print labels
            const body = document.body;
            
            // Update CSS for print orientation
            const style = document.createElement('style');
            style.innerHTML = `
                @media print {
                    @page {
                        size: ${isLandscape ? '1.47in 1in' : '1in 1.47in'};
                        margin: 0;
                        padding: 0;
                        ${isLandscape ? 'orientation: landscape;' : ''}
                    }
                    body {
                        ${isLandscape ? 
                            'grid-template-columns: repeat(6, 1.47in) !important;' : 
                            'grid-template-columns: repeat(2, 1in) !important;'}
                        ${isLandscape ?
                            'grid-template-rows: repeat(2, 1in) !important;' :
                            'grid-template-rows: repeat(4, 1.47in) !important;'}
                    }
                    .barcode-label {
                        width: ${isLandscape ? '1.47in' : '1in'} !important;
                        height: ${isLandscape ? '1in' : '1.47in'} !important;
                    }
                    .barcode-image {
                        height: ${isLandscape ? '0.45in' : '0.55in'} !important;
                    }
                }
            `;
            document.head.appendChild(style);
            
            // Adjust barcode dimensions based on orientation
            const barcodeWidth = isLandscape ? 200 : 180;
            const barcodeHeight = isLandscape ? 40 : 50;
            
            for(let i = 0; i < copies; i++) {
                const label = document.createElement('div');
                label.className = 'barcode-label';
                label.style.width = isLandscape ? '1.47in' : '1in';
                label.style.height = isLandscape ? '1in' : '1.47in';
                
                let html = '';
                if (name) {
                    html += `<div class="product-name">${name}</div>`;
                }
                
                const barcodeImg = `https://barcode.tec-it.com/barcode.ashx?data=${encodeURIComponent(barcode)}&code=${getBarcodeTypeCode(type)}&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=300&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&codepage=&qunit=Mm&quiet=0&width=${barcodeWidth}&height=${barcodeHeight}&textfont=Arial&textsize=6`;
                
                html += `<img src="${barcodeImg}" alt="Barcode" class="barcode-image" style="height: ${isLandscape ? '0.45in' : '0.55in'}">`;
                html += `<div class="barcode-text">${barcode}</div>`;
                
                if (price) {
                    html += `<div class="price">PKR ${parseFloat(price).toFixed(2)}</div>`;
                }
                
                label.innerHTML = html;
                body.appendChild(label);
            }
            
            // Wait a moment for images to load, then print
            setTimeout(() => {
                window.print();
                
                // Remove the dynamic style after printing
                style.remove();
                
                // Restore preview after printing
                setTimeout(updatePreview, 100);
            }, 500);
        }

        // Auto-print when page loads (only if copies > 0)
        window.onload = function() {
            if (<?php echo $copies; ?> > 0) {
                setTimeout(function() {
                    if (!sessionStorage.getItem('barcode_printed')) {
                        sessionStorage.setItem('barcode_printed', 'true');
                        updatePreview();
                    }
                }, 100);
            }
            
            // Add emoji icons
            const style = document.createElement('style');
            style.innerHTML = `
                @font-face {
                    font-family: 'Emoji';
                    src: local('Apple Color Emoji'), local('Segoe UI Emoji'), local('Segoe UI Symbol'), local('Noto Color Emoji');
                    unicode-range: U+1F300-1F5FF, U+1F600-1F64F, U+1F680-1F6FF, U+2600-26FF;
                }
                * {
                    font-family: Arial, Helvetica, 'Emoji', sans-serif;
                }
            `;
            document.head.appendChild(style);
        };

        // Make inputs update on enter key
        document.querySelectorAll('input, select').forEach(element => {
            element.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    updatePreview();
                }
            });
        });
    </script>
</body>
</html>