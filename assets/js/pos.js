// POS System JavaScript

// Global variables
let cart = [];
let totalAmount = 0;

// Initialize POS
document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus barcode input
    const barcodeInput = document.getElementById('barcode-input');
    if (barcodeInput) {
        barcodeInput.focus();
    }
    
    // Load cart from session
    loadCart();
    
    // Setup keyboard shortcuts
    setupKeyboardShortcuts();
});

// Keyboard shortcuts
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Don't trigger shortcuts when typing in inputs
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
        switch(e.key) {
            case 'F2':
                e.preventDefault();
                processSale();
                break;
            case 'F4':
                e.preventDefault();
                clearCart();
                break;
            case 'Escape':
                e.preventDefault();
                if (confirm('Cancel current sale?')) {
                    clearCart();
                }
                break;
        }
    });
}

// Calculate change
function calculateChange() {
    const cashReceived = parseFloat(document.getElementById('cash_received').value) || 0;
    const netAmount = parseFloat(document.getElementById('net_amount').value) || 0;
    const change = cashReceived - netAmount;
    
    document.getElementById('change_amount').value = change.toFixed(2);
    document.getElementById('change_display').textContent = 'Rs. ' + change.toFixed(2);
}

// Manual search functions
function openManualSearch() {
    document.getElementById('searchModal').style.display = 'block';
    document.getElementById('searchInput').focus();
}

function closeManualSearch() {
    document.getElementById('searchModal').style.display = 'none';
    document.getElementById('searchInput').value = '';
    document.getElementById('searchResults').innerHTML = '';
}

function searchProducts() {
    const query = document.getElementById('searchInput').value.trim();
    
    if (query.length < 2) {
        document.getElementById('searchResults').innerHTML = '';
        return;
    }
    
    fetch(`../api/search_products.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(products => {
            let html = '';
            if (products.length > 0) {
                products.forEach(product => {
                    html += `
                        <div class="search-result" onclick="selectProduct('${product.barcode}')">
                            <div class="product-name">${product.name}</div>
                            <div class="product-details">
                                Barcode: ${product.barcode} | 
                                Price: Rs. ${product.sale_price} | 
                                Stock: ${product.stock_quantity}
                            </div>
                        </div>
                    `;
                });
            } else {
                html = '<div class="no-results">No products found</div>';
            }
            document.getElementById('searchResults').innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('searchResults').innerHTML = '<div class="error">Error searching products</div>';
        });
}

function selectProduct(barcode) {
    document.getElementById('barcode-input').value = barcode;
    closeManualSearch();
    // Trigger form submission
    document.getElementById('add-item-form').submit();
}

// Auto-submit barcode on enter (outside form)
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.id === 'barcode-input') {
        e.preventDefault();
        document.getElementById('add-item-form').submit();
    }
});

// Print receipt
function printReceipt() {
    window.print();
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = message;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Helper functions
function formatCurrency(amount) {
    return 'Rs. ' + parseFloat(amount).toFixed(2);
}

function loadCart() {
    // Load cart from localStorage or session
    const savedCart = localStorage.getItem('pos_cart');
    if (savedCart) {
        cart = JSON.parse(savedCart);
        updateCartDisplay();
    }
}

function saveCart() {
    localStorage.setItem('pos_cart', JSON.stringify(cart));
}

function updateCartDisplay() {
    // Update cart display logic here
    saveCart();
}

function processSale() {
    // Process sale logic
    console.log('Processing sale...');
}

function clearCart() {
    if (confirm('Are you sure you want to clear the cart?')) {
        cart = [];
        updateCartDisplay();
        showNotification('Cart cleared', 'info');
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('searchModal');
    if (event.target === modal) {
        closeManualSearch();
    }
}