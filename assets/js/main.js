/**
 * ZIC MART POS - MAIN JAVASCRIPT FILE
 * Common functions used across all pages
 */

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initTooltips();
    
    // Initialize form validations
    initFormValidations();
    
    // Initialize alerts auto-dismiss
    initAutoDismissAlerts();
    
    // Initialize print functionality
    initPrintFunctionality();
    
    // Initialize session timeout warning
    initSessionTimeout();
});

/**
 * Initialize Bootstrap-like tooltips
 */
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-toggle="tooltip"]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltipText = this.getAttribute('title');
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = tooltipText;
            
            const rect = this.getBoundingClientRect();
            tooltip.style.position = 'fixed';
            tooltip.style.top = (rect.top - 40) + 'px';
            tooltip.style.left = (rect.left + (rect.width / 2) - (tooltipText.length * 3)) + 'px';
            tooltip.style.zIndex = '9999';
            
            document.body.appendChild(tooltip);
            this.tooltipElement = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this.tooltipElement) {
                this.tooltipElement.remove();
                this.tooltipElement = null;
            }
        });
    });
}

/**
 * Initialize form validations
 */
function initFormValidations() {
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    showFieldError(field, 'This field is required');
                    isValid = false;
                } else {
                    clearFieldError(field);
                }
                
                // Email validation
                if (field.type === 'email' && field.value.trim()) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(field.value)) {
                        showFieldError(field, 'Please enter a valid email address');
                        isValid = false;
                    }
                }
                
                // Number validation
                if (field.type === 'number' && field.value.trim()) {
                    if (field.min && parseFloat(field.value) < parseFloat(field.min)) {
                        showFieldError(field, `Value must be at least ${field.min}`);
                        isValid = false;
                    }
                    if (field.max && parseFloat(field.value) > parseFloat(field.max)) {
                        showFieldError(field, `Value must be at most ${field.max}`);
                        isValid = false;
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Please fill all required fields correctly', 'error');
            }
        });
    });
}

/**
 * Show field error
 */
function showFieldError(field, message) {
    clearFieldError(field);
    field.classList.add('error');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.color = '#f56565';
    errorDiv.style.fontSize = '12px';
    errorDiv.style.marginTop = '5px';
    
    field.parentNode.appendChild(errorDiv);
    field.errorElement = errorDiv;
}

/**
 * Clear field error
 */
function clearFieldError(field) {
    field.classList.remove('error');
    if (field.errorElement) {
        field.errorElement.remove();
        field.errorElement = null;
    }
}

/**
 * Initialize auto-dismiss alerts
 */
function initAutoDismissAlerts() {
    const alerts = document.querySelectorAll('.alert[data-auto-dismiss]');
    alerts.forEach(alert => {
        const timeout = alert.getAttribute('data-dismiss-timeout') || 5000;
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(100%)';
            setTimeout(() => alert.remove(), 300);
        }, parseInt(timeout));
    });
}

/**
 * Show notification
 * @param {string} message - Notification message
 * @param {string} type - success, error, warning, info
 * @param {number} duration - Duration in milliseconds
 */
function showNotification(message, type = 'info', duration = 3000) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.global-notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `global-notification notification-${type}`;
    
    // Icons for each type
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${icons[type] || icons.info}</span>
            <span class="notification-message">${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 15px;
        z-index: 9999;
        animation: slideInRight 0.3s ease-out;
        max-width: 400px;
    `;
    
    // Type-specific styles
    const typeStyles = {
        success: 'border-left: 4px solid #48bb78;',
        error: 'border-left: 4px solid #f56565;',
        warning: 'border-left: 4px solid #ed8936;',
        info: 'border-left: 4px solid #4299e1;'
    };
    
    notification.style.cssText += typeStyles[type] || typeStyles.info;
    
    document.body.appendChild(notification);
    
    // Auto remove after duration
    if (duration > 0) {
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }
        }, duration);
    }
}

/**
 * Initialize print functionality
 */
function initPrintFunctionality() {
    const printButtons = document.querySelectorAll('[data-print]');
    printButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const target = this.getAttribute('data-print-target') || 'body';
            const elements = document.querySelectorAll(target);
            
            if (elements.length === 0) {
                showNotification('No content to print', 'error');
                return;
            }
            
            // Create print window
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Print</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        @media print {
                            body { margin: 0; }
                        }
                        .no-print { display: none !important; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { padding: 8px; border: 1px solid #ddd; }
                    </style>
                </head>
                <body>
            `);
            
            elements.forEach(element => {
                printWindow.document.write(element.outerHTML);
            });
            
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            
            // Wait for content to load, then print
            printWindow.onload = function() {
                printWindow.print();
                printWindow.onafterprint = function() {
                    printWindow.close();
                };
            };
        });
    });
}

/**
 * Initialize session timeout warning
 */
function initSessionTimeout() {
    // Session timeout in minutes
    const SESSION_TIMEOUT = 30;
    let timeoutWarning;
    let timeoutLogout;
    
    function resetSessionTimer() {
        clearTimeout(timeoutWarning);
        clearTimeout(timeoutLogout);
        
        // Show warning 5 minutes before timeout
        timeoutWarning = setTimeout(() => {
            showNotification(`Your session will expire in 5 minutes. Click to extend.`, 'warning', 0);
            
            // Add click to extend functionality
            const notification = document.querySelector('.global-notification');
            if (notification) {
                notification.style.cursor = 'pointer';
                notification.addEventListener('click', () => {
                    // Send AJAX request to extend session
                    fetch('api/extend_session.php')
                        .then(() => {
                            notification.remove();
                            resetSessionTimer();
                            showNotification('Session extended', 'success');
                        })
                        .catch(() => {
                            showNotification('Failed to extend session', 'error');
                        });
                });
            }
        }, (SESSION_TIMEOUT - 5) * 60 * 1000);
        
        // Logout after full timeout
        timeoutLogout = setTimeout(() => {
            showNotification('Session expired. Redirecting to login...', 'error', 2000);
            setTimeout(() => {
                window.location.href = 'logout.php';
            }, 2000);
        }, SESSION_TIMEOUT * 60 * 1000);
    }
    
    // Reset timer on user activity
    ['click', 'mousemove', 'keypress', 'scroll'].forEach(event => {
        document.addEventListener(event, resetSessionTimer, { passive: true });
    });
    
    // Start timer
    resetSessionTimer();
}

/**
 * Format currency
 * @param {number} amount - Amount to format
 * @param {string} currency - Currency symbol
 * @returns {string} Formatted currency string
 */
function formatCurrency(amount, currency = 'Rs. ') {
    if (isNaN(amount)) amount = 0;
    return currency + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Calculate change
 * @param {number} total - Total amount
 * @param {number} received - Cash received
 * @returns {number} Change amount
 */
function calculateChange(total, received) {
    return Math.max(0, parseFloat(received) - parseFloat(total));
}

/**
 * Debounce function for performance
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @returns {Function} Debounced function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function for performance
 * @param {Function} func - Function to throttle
 * @param {number} limit - Time limit in milliseconds
 * @returns {Function} Throttled function
 */
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Copy to clipboard
 * @param {string} text - Text to copy
 * @param {string} successMessage - Success message
 */
function copyToClipboard(text, successMessage = 'Copied to clipboard!') {
    navigator.clipboard.writeText(text).then(() => {
        showNotification(successMessage, 'success');
    }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            showNotification(successMessage, 'success');
        } catch (err) {
            showNotification('Failed to copy', 'error');
        }
        document.body.removeChild(textArea);
    });
}

/**
 * Get URL parameters
 * @param {string} name - Parameter name
 * @returns {string|null} Parameter value
 */
function getUrlParameter(name) {
    name = name.replace(/[\[\]]/g, '\\$&');
    const regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)');
    const results = regex.exec(window.location.href);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, ' '));
}

/**
 * Set URL parameter
 * @param {string} key - Parameter key
 * @param {string} value - Parameter value
 */
function setUrlParameter(key, value) {
    const url = new URL(window.location);
    url.searchParams.set(key, value);
    window.history.pushState({}, '', url);
}

/**
 * Remove URL parameter
 * @param {string} key - Parameter key to remove
 */
function removeUrlParameter(key) {
    const url = new URL(window.location);
    url.searchParams.delete(key);
    window.history.pushState({}, '', url);
}

/**
 * Toggle element visibility
 * @param {string} selector - CSS selector
 * @param {boolean} show - Whether to show or hide
 */
function toggleElement(selector, show) {
    const element = document.querySelector(selector);
    if (element) {
        element.style.display = show ? '' : 'none';
    }
}

/**
 * Show loading spinner
 * @param {HTMLElement} element - Element to show spinner in
 */
function showLoading(element) {
    const spinner = document.createElement('div');
    spinner.className = 'loading-spinner';
    spinner.innerHTML = '<div class="spinner"></div>';
    element.appendChild(spinner);
}

/**
 * Hide loading spinner
 * @param {HTMLElement} element - Element with spinner
 */
function hideLoading(element) {
    const spinner = element.querySelector('.loading-spinner');
    if (spinner) {
        spinner.remove();
    }
}

// Keyboard shortcuts helper
const KeyboardShortcuts = {
    shortcuts: {},
    
    register: function(key, callback, options = {}) {
        this.shortcuts[key] = { callback, options };
    },
    
    unregister: function(key) {
        delete this.shortcuts[key];
    },
    
    init: function() {
        document.addEventListener('keydown', (e) => {
            // Don't trigger shortcuts when typing in inputs
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
                return;
            }
            
            // Check for registered shortcuts
            const shortcutKey = e.key.toLowerCase();
            if (this.shortcuts[shortcutKey]) {
                const shortcut = this.shortcuts[shortcutKey];
                if (shortcut.options.preventDefault !== false) {
                    e.preventDefault();
                }
                shortcut.callback(e);
            }
        });
    }
};

// Initialize keyboard shortcuts
KeyboardShortcuts.init();

// Export functions for use in other files
window.ZICMart = {
    showNotification,
    formatCurrency,
    calculateChange,
    copyToClipboard,
    getUrlParameter,
    setUrlParameter,
    removeUrlParameter,
    toggleElement,
    showLoading,
    hideLoading,
    debounce,
    throttle,
    KeyboardShortcuts
};