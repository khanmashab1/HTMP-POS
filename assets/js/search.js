/**
 * ZIC MART POS - SEARCH JAVASCRIPT
 * Product search functionality
 */

class ProductSearch {
    constructor(options = {}) {
        this.options = {
            searchUrl: 'api/search_products.php',
            minQueryLength: 2,
            debounceTime: 300,
            maxResults: 20,
            ...options
        };
        
        this.searchInput = null;
        this.resultsContainer = null;
        this.currentRequest = null;
        this.selectedIndex = -1;
        this.results = [];
        
        this.init();
    }
    
    init() {
        // Find search elements
        this.searchInput = document.querySelector(this.options.inputSelector || '.product-search-input');
        this.resultsContainer = document.querySelector(this.options.resultsSelector || '.search-results');
        
        if (!this.searchInput) return;
        
        // Add event listeners
        this.searchInput.addEventListener('input', 
            debounce(this.handleInput.bind(this), this.options.debounceTime)
        );
        
        this.searchInput.addEventListener('keydown', this.handleKeydown.bind(this));
        this.searchInput.addEventListener('focus', this.handleFocus.bind(this));
        this.searchInput.addEventListener('blur', this.handleBlur.bind(this));
        
        // Close results when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.searchInput.contains(e.target) && !this.resultsContainer.contains(e.target)) {
                this.hideResults();
            }
        });
    }
    
    handleInput(e) {
        const query = e.target.value.trim();
        
        if (query.length < this.options.minQueryLength) {
            this.hideResults();
            return;
        }
        
        this.searchProducts(query);
    }
    
    handleKeydown(e) {
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.navigateResults(1);
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                this.navigateResults(-1);
                break;
                
            case 'Enter':
                e.preventDefault();
                if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
                    this.selectProduct(this.results[this.selectedIndex]);
                }
                break;
                
            case 'Escape':
                this.hideResults();
                this.searchInput.blur();
                break;
        }
    }
    
    handleFocus() {
        const query = this.searchInput.value.trim();
        if (query.length >= this.options.minQueryLength) {
            this.searchProducts(query);
        }
    }
    
    handleBlur() {
        // Small delay to allow click on results
        setTimeout(() => {
            if (!document.activeElement.closest('.search-results')) {
                this.hideResults();
            }
        }, 200);
    }
    
    async searchProducts(query) {
        // Cancel previous request if exists
        if (this.currentRequest) {
            this.currentRequest.abort();
        }
        
        // Show loading state
        this.showLoading();
        
        try {
            // Create new request
            const controller = new AbortController();
            this.currentRequest = controller;
            
            const response = await fetch(`${this.options.searchUrl}?q=${encodeURIComponent(query)}`, {
                signal: controller.signal
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const products = await response.json();
            this.displayResults(products);
            
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Search error:', error);
                this.displayError('Error searching products');
            }
        } finally {
            this.currentRequest = null;
        }
    }
    
    displayResults(products) {
        this.results = products;
        this.selectedIndex = -1;
        
        if (!products || products.length === 0) {
            this.resultsContainer.innerHTML = `
                <div class="search-no-results">
                    <div class="no-results-icon">🔍</div>
                    <div class="no-results-text">No products found</div>
                    <div class="no-results-hint">Try a different search term</div>
                </div>
            `;
            this.showResults();
            return;
        }
        
        let html = '';
        products.forEach((product, index) => {
            const isLowStock = product.stock_quantity <= product.min_stock_alert;
            const stockClass = isLowStock ? 'low-stock' : 'in-stock';
            const stockText = isLowStock ? 'Low Stock' : 'In Stock';
            
            html += `
                <div class="search-result-item ${stockClass}" 
                     data-index="${index}"
                     data-product-id="${product.id}"
                     data-barcode="${product.barcode}">
                    
                    <div class="product-info">
                        <div class="product-name">${this.escapeHtml(product.name)}</div>
                        <div class="product-details">
                            <span class="product-barcode">${product.barcode}</span>
                            <span class="product-category">${product.category_name || 'Uncategorized'}</span>
                            <span class="product-stock ${stockClass}">${stockText}</span>
                        </div>
                    </div>
                    
                    <div class="product-action">
                        <div class="product-price">${formatCurrency(product.sale_price)}</div>
                        <div class="product-stock-quantity">Stock: ${product.stock_quantity}</div>
                        <button class="add-product-btn" 
                                onclick="productSearch.selectProductAtIndex(${index})"
                                title="Add to cart">
                            ➕
                        </button>
                    </div>
                </div>
            `;
        });
        
        this.resultsContainer.innerHTML = html;
        this.showResults();
        
        // Add click listeners to result items
        this.resultsContainer.querySelectorAll('.search-result-item').forEach(item => {
            item.addEventListener('click', (e) => {
                if (!e.target.classList.contains('add-product-btn')) {
                    const index = parseInt(item.dataset.index);
                    this.selectProduct(this.results[index]);
                }
            });
        });
    }
    
    displayError(message) {
        this.resultsContainer.innerHTML = `
            <div class="search-error">
                <div class="error-icon">⚠️</div>
                <div class="error-text">${message}</div>
            </div>
        `;
        this.showResults();
    }
    
    showLoading() {
        this.resultsContainer.innerHTML = `
            <div class="search-loading">
                <div class="loading-spinner"></div>
                <div class="loading-text">Searching products...</div>
            </div>
        `;
        this.showResults();
    }
    
    showResults() {
        this.resultsContainer.style.display = 'block';
        this.positionResults();
    }
    
    hideResults() {
        this.resultsContainer.style.display = 'none';
        this.selectedIndex = -1;
    }
    
    positionResults() {
        const inputRect = this.searchInput.getBoundingClientRect();
        const containerRect = this.resultsContainer.getBoundingClientRect();
        
        // Position below input
        this.resultsContainer.style.position = 'fixed';
        this.resultsContainer.style.top = (inputRect.bottom + window.scrollY + 5) + 'px';
        this.resultsContainer.style.left = (inputRect.left + window.scrollX) + 'px';
        this.resultsContainer.style.width = inputRect.width + 'px';
        this.resultsContainer.style.maxHeight = '400px';
        this.resultsContainer.style.zIndex = '1000';
    }
    
    navigateResults(direction) {
        if (this.results.length === 0) return;
        
        // Remove previous selection
        if (this.selectedIndex >= 0) {
            const prevItem = this.resultsContainer.querySelector(`[data-index="${this.selectedIndex}"]`);
            if (prevItem) prevItem.classList.remove('selected');
        }
        
        // Calculate new index
        this.selectedIndex += direction;
        
        // Wrap around
        if (this.selectedIndex < 0) this.selectedIndex = this.results.length - 1;
        if (this.selectedIndex >= this.results.length) this.selectedIndex = 0;
        
        // Add new selection
        const newItem = this.resultsContainer.querySelector(`[data-index="${this.selectedIndex}"]`);
        if (newItem) {
            newItem.classList.add('selected');
            newItem.scrollIntoView({ block: 'nearest' });
        }
    }
    
    selectProduct(product) {
        if (!product) return;
        
        // Hide results
        this.hideResults();
        
        // Clear search input
        this.searchInput.value = '';
        
        // Dispatch custom event
        const event = new CustomEvent('productSelected', {
            detail: { product }
        });
        this.searchInput.dispatchEvent(event);
        
        // Show notification
        showNotification(`Added "${product.name}" to cart`, 'success');
        
        // Add to cart via AJAX
        this.addToCart(product);
    }
    
    selectProductAtIndex(index) {
        if (this.results[index]) {
            this.selectProduct(this.results[index]);
        }
    }
    
    async addToCart(product) {
        try {
            const response = await fetch('api/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: product.id,
                    quantity: 1
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update cart UI
                this.updateCartUI(data.cart);
            } else {
                showNotification(data.message || 'Failed to add to cart', 'error');
            }
            
        } catch (error) {
            console.error('Error adding to cart:', error);
            showNotification('Error adding to cart', 'error');
        }
    }
    
    updateCartUI(cartData) {
        // Update cart count
        const cartCount = document.querySelector('.cart-count');
        if (cartCount) {
            const totalItems = cartData.reduce((sum, item) => sum + item.quantity, 0);
            cartCount.textContent = totalItems;
            cartCount.style.display = totalItems > 0 ? 'inline' : 'none';
        }
        
        // Update cart total
        const cartTotal = document.querySelector('.cart-total');
        if (cartTotal) {
            const totalAmount = cartData.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            cartTotal.textContent = formatCurrency(totalAmount);
        }
        
        // Update cart items list
        const cartItemsContainer = document.querySelector('.cart-items');
        if (cartItemsContainer) {
            this.updateCartItemsList(cartItemsContainer, cartData);
        }
    }
    
    updateCartItemsList(container, cartData) {
        if (cartData.length === 0) {
            container.innerHTML = `
                <div class="empty-cart">
                    <div class="empty-icon">🛒</div>
                    <div class="empty-text">Cart is empty</div>
                </div>
            `;
            return;
        }
        
        let html = '';
        cartData.forEach(item => {
            html += `
                <div class="cart-item" data-product-id="${item.product_id}">
                    <div class="cart-item-name">${this.escapeHtml(item.name)}</div>
                    <div class="cart-item-quantity">
                        <button class="qty-decrease" onclick="productSearch.updateQuantity(${item.product_id}, ${item.quantity - 1})">-</button>
                        <span class="qty-value">${item.quantity}</span>
                        <button class="qty-increase" onclick="productSearch.updateQuantity(${item.product_id}, ${item.quantity + 1})">+</button>
                    </div>
                    <div class="cart-item-price">${formatCurrency(item.price)}</div>
                    <div class="cart-item-total">${formatCurrency(item.price * item.quantity)}</div>
                    <button class="cart-item-remove" onclick="productSearch.removeFromCart(${item.product_id})">×</button>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    async updateQuantity(productId, newQuantity) {
        if (newQuantity < 1) {
            this.removeFromCart(productId);
            return;
        }
        
        try {
            const response = await fetch('api/update_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: newQuantity
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.updateCartUI(data.cart);
            }
            
        } catch (error) {
            console.error('Error updating quantity:', error);
            showNotification('Error updating quantity', 'error');
        }
    }
    
    async removeFromCart(productId) {
        try {
            const response = await fetch('api/remove_from_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ product_id: productId })
            });
            
            const data = await response.json();
            if (data.success) {
                this.updateCartUI(data.cart);
                showNotification('Item removed from cart', 'info');
            }
            
        } catch (error) {
            console.error('Error removing from cart:', error);
            showNotification('Error removing item', 'error');
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Barcode scanner functionality
class BarcodeScanner {
    constructor() {
        this.scanBuffer = '';
        this.lastScanTime = 0;
        this.scanTimeout = 100; // ms between key presses to consider as barcode
        
        this.init();
    }
    
    init() {
        document.addEventListener('keydown', this.handleKeydown.bind(this));
    }
    
    handleKeydown(e) {
        // Ignore modifier keys and function keys
        if (e.altKey || e.ctrlKey || e.metaKey || 
            e.key === 'Shift' || e.key === 'Control' || 
            e.key === 'Alt' || e.key === 'Meta' ||
            e.key.startsWith('F')) {
            return;
        }
        
        const currentTime = Date.now();
        
        // If too much time passed since last key, reset buffer
        if (currentTime - this.lastScanTime > this.scanTimeout) {
            this.scanBuffer = '';
        }
        
        // Add key to buffer
        if (e.key.length === 1) { // Single character
            this.scanBuffer += e.key;
        } else if (e.key === 'Enter') { // End of barcode
            this.processBarcode(this.scanBuffer);
            this.scanBuffer = '';
            e.preventDefault();
        }
        
        this.lastScanTime = currentTime;
    }
    
    processBarcode(barcode) {
        barcode = barcode.trim();
        
        if (barcode.length < 3) return; // Too short for barcode
        
        console.log('Barcode scanned:', barcode);
        
        // Show scanning animation
        this.showScanAnimation();
        
        // Search for product
        fetch(`api/search_products.php?barcode=${encodeURIComponent(barcode)}`)
            .then(response => response.json())
            .then(products => {
                if (products.length > 0) {
                    const product = products[0];
                    
                    // Dispatch event
                    const event = new CustomEvent('barcodeScanned', {
                        detail: { product, barcode }
                    });
                    document.dispatchEvent(event);
                    
                    // Show success notification
                    showNotification(`Scanned: ${product.name}`, 'success');
                    
                    // Auto-add to cart
                    productSearch.selectProduct(product);
                    
                } else {
                    showNotification(`Product not found for barcode: ${barcode}`, 'warning');
                }
            })
            .catch(error => {
                console.error('Barcode search error:', error);
                showNotification('Error scanning barcode', 'error');
            });
    }
    
    showScanAnimation() {
        // Create scan animation element
        const scanAnim = document.createElement('div');
        scanAnim.className = 'scan-animation';
        scanAnim.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100px;
            height: 100px;
            background: rgba(72, 187, 120, 0.9);
            border-radius: 50%;
            animation: scanPulse 0.5s ease-out;
            z-index: 9999;
            pointer-events: none;
        `;
        
        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes scanPulse {
                0% { transform: translate(-50%, -50%) scale(0); opacity: 1; }
                100% { transform: translate(-50%, -50%) scale(2); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        document.body.appendChild(scanAnim);
        
        // Remove after animation
        setTimeout(() => {
            scanAnim.remove();
            style.remove();
        }, 500);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize product search
    window.productSearch = new ProductSearch({
        inputSelector: '.product-search-input',
        resultsSelector: '.search-results'
    });
    
    // Initialize barcode scanner
    window.barcodeScanner = new BarcodeScanner();
    
    // Setup keyboard shortcuts for search
    KeyboardShortcuts.register('ctrl+f', function() {
        const searchInput = document.querySelector('.product-search-input');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }, { preventDefault: true });
    
    KeyboardShortcuts.register('/', function() {
        const searchInput = document.querySelector('.product-search-input');
        if (searchInput && document.activeElement !== searchInput) {
            searchInput.focus();
            searchInput.select();
            return false;
        }
    }, { preventDefault: true });
});

// Export functions for use in console if needed
window.ProductSearch = ProductSearch;
window.BarcodeScanner = BarcodeScanner;