/**
 * ZIC MART POS - ADMIN JAVASCRIPT
 * Admin panel specific functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize admin functionality
    initAdminSidebar();
    initDashboardCharts();
    initDataTables();
    initFormSubmissions();
    initBulkActions();
    initDateRangePicker();
    initExportButtons();
});

/**
 * Initialize admin sidebar toggle
 */
function initAdminSidebar() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const adminSidebar = document.querySelector('.admin-sidebar');
    const adminMain = document.querySelector('.admin-main');
    
    if (sidebarToggle && adminSidebar) {
        sidebarToggle.addEventListener('click', function() {
            adminSidebar.classList.toggle('collapsed');
            adminMain.classList.toggle('expanded');
            
            // Save preference to localStorage
            const isCollapsed = adminSidebar.classList.contains('collapsed');
            localStorage.setItem('adminSidebarCollapsed', isCollapsed);
        });
        
        // Load saved preference
        const savedCollapsed = localStorage.getItem('adminSidebarCollapsed') === 'true';
        if (savedCollapsed) {
            adminSidebar.classList.add('collapsed');
            adminMain.classList.add('expanded');
        }
    }
    
    // Highlight current page in sidebar
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const linkPath = link.getAttribute('href');
        if (linkPath && currentPath.includes(linkPath)) {
            link.classList.add('active');
            // Also highlight parent if exists
            const parentItem = link.closest('.nav-item.has-submenu');
            if (parentItem) {
                parentItem.classList.add('active');
            }
        }
    });
    
    // Submenu toggle
    const submenuToggles = document.querySelectorAll('.nav-item.has-submenu > .nav-link');
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                const parent = this.closest('.nav-item.has-submenu');
                parent.classList.toggle('open');
            }
        });
    });
}

/**
 * Initialize dashboard charts
 */
function initDashboardCharts() {
    // Sales Chart
    const salesChartCtx = document.getElementById('salesChart');
    if (salesChartCtx) {
        const salesChart = new Chart(salesChartCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Daily Sales',
                    data: [12000, 19000, 15000, 25000, 22000, 30000, 28000],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [5, 5]
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rs. ' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // Sales by Category Chart
    const categoryChartCtx = document.getElementById('categoryChart');
    if (categoryChartCtx) {
        const categoryChart = new Chart(categoryChartCtx, {
            type: 'doughnut',
            data: {
                labels: ['Beverages', 'Snacks', 'Cigarettes', 'Groceries', 'Personal Care', 'Others'],
                datasets: [{
                    data: [25, 20, 15, 18, 12, 10],
                    backgroundColor: [
                        '#667eea',
                        '#48bb78',
                        '#ed8936',
                        '#f56565',
                        '#4299e1',
                        '#9f7aea'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 20
                        }
                    }
                }
            }
        });
    }
    
    // Top Products Chart
    const productsChartCtx = document.getElementById('productsChart');
    if (productsChartCtx) {
        const productsChart = new Chart(productsChartCtx, {
            type: 'bar',
            data: {
                labels: ['Coca Cola', 'Pepsi', 'Lays', 'Kurkure', 'Dairy Milk', 'Marlboro', 'Gold Leaf', 'Sugar', 'Tea', 'Lifebuoy'],
                datasets: [{
                    label: 'Units Sold',
                    data: [150, 120, 90, 85, 70, 65, 60, 55, 50, 45],
                    backgroundColor: '#4299e1',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [5, 5]
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45
                        }
                    }
                }
            }
        });
    }
    
    // Sales Trends Chart
    const trendsChartCtx = document.getElementById('trendsChart');
    if (trendsChartCtx) {
        const trendsChart = new Chart(trendsChartCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Monthly Sales',
                    data: [350000, 420000, 380000, 450000, 500000, 480000, 520000, 550000, 530000, 600000, 580000, 650000],
                    borderColor: '#48bb78',
                    backgroundColor: 'rgba(72, 187, 120, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [5, 5]
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rs. ' + (value / 1000) + 'k';
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
}

/**
 * Initialize data tables with search and sort
 */
function initDataTables() {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        const searchInput = table.parentElement.querySelector('.table-search');
        const sortButtons = table.querySelectorAll('.sort-button');
        
        // Search functionality
        if (searchInput) {
            searchInput.addEventListener('input', debounce(function() {
                const searchTerm = this.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            }, 300));
        }
        
        // Sort functionality
        sortButtons.forEach(button => {
            button.addEventListener('click', function() {
                const column = parseInt(this.dataset.column);
                const isAscending = this.classList.contains('asc');
                
                // Clear other sort buttons
                sortButtons.forEach(btn => {
                    btn.classList.remove('asc', 'desc');
                    btn.innerHTML = btn.innerHTML.replace('↑', '').replace('↓', '');
                });
                
                // Set new sort direction
                if (isAscending) {
                    this.classList.remove('asc');
                    this.classList.add('desc');
                    this.innerHTML += ' ↓';
                    sortTable(table, column, false);
                } else {
                    this.classList.remove('desc');
                    this.classList.add('asc');
                    this.innerHTML += ' ↑';
                    sortTable(table, column, true);
                }
            });
        });
    });
}

/**
 * Sort table function
 */
function sortTable(table, column, ascending = true) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aVal = a.cells[column].textContent.trim();
        const bVal = b.cells[column].textContent.trim();
        
        // Check if values are numbers
        const aNum = parseFloat(aVal.replace(/[^0-9.-]+/g, ''));
        const bNum = parseFloat(bVal.replace(/[^0-9.-]+/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return ascending ? aNum - bNum : bNum - aNum;
        } else {
            // String comparison
            return ascending ? 
                aVal.localeCompare(bVal) : 
                bVal.localeCompare(aVal);
        }
    });
    
    // Reorder rows
    rows.forEach(row => tbody.appendChild(row));
}

/**
 * Initialize form submissions with AJAX
 */
function initFormSubmissions() {
    const ajaxForms = document.querySelectorAll('form[data-ajax]');
    
    ajaxForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            
            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner"></span> Processing...';
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    
                    // If form has redirect URL
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    }
                    
                    // If form should reset
                    if (this.hasAttribute('data-reset')) {
                        this.reset();
                    }
                    
                    // Refresh data if needed
                    if (data.refresh) {
                        setTimeout(() => location.reload(), 1000);
                    }
                } else {
                    showNotification(data.message || 'Operation failed', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                // Restore button state
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
        });
    });
}

/**
 * Initialize bulk actions
 */
function initBulkActions() {
    const selectAllCheckbox = document.querySelector('.select-all');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    const bulkActionForm = document.querySelector('.bulk-actions');
    
    if (selectAllCheckbox && itemCheckboxes.length > 0) {
        // Select all functionality
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updateBulkActionButton();
        });
        
        // Individual checkbox functionality
        itemCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
                const anyChecked = Array.from(itemCheckboxes).some(cb => cb.checked);
                
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = anyChecked && !allChecked;
                
                updateBulkActionButton();
            });
        });
        
        // Bulk action form
        if (bulkActionForm) {
            const bulkActionSelect = bulkActionForm.querySelector('select[name="bulk_action"]');
            const bulkActionButton = bulkActionForm.querySelector('button[type="submit"]');
            
            function updateBulkActionButton() {
                const checkedCount = Array.from(itemCheckboxes).filter(cb => cb.checked).length;
                bulkActionButton.disabled = checkedCount === 0;
                bulkActionButton.textContent = `Apply to ${checkedCount} item${checkedCount !== 1 ? 's' : ''}`;
            }
            
            bulkActionForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const action = bulkActionSelect.value;
                const selectedIds = Array.from(itemCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);
                
                if (selectedIds.length === 0 || !action) {
                    showNotification('Please select items and an action', 'warning');
                    return;
                }
                
                if (confirm(`Are you sure you want to ${action} ${selectedIds.length} item(s)?`)) {
                    const formData = new FormData(this);
                    formData.append('ids', JSON.stringify(selectedIds));
                    
                    fetch('api/bulk_action.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('An error occurred', 'error');
                    });
                }
            });
            
            updateBulkActionButton();
        }
    }
}

/**
 * Initialize date range picker
 */
function initDateRangePicker() {
    const dateRangeInput = document.querySelector('.date-range-picker');
    
    if (dateRangeInput) {
        // Set default date range (last 30 days)
        const end = new Date();
        const start = new Date();
        start.setDate(start.getDate() - 30);
        
        dateRangeInput.value = 
            start.toISOString().split('T')[0] + ' to ' + 
            end.toISOString().split('T')[0];
        
        // Add change event
        dateRangeInput.addEventListener('change', function() {
            const dates = this.value.split(' to ');
            if (dates.length === 2) {
                const startDate = dates[0];
                const endDate = dates[1];
                
                // Update charts with new date range
                if (window.salesChart) {
                    fetchSalesData(startDate, endDate);
                }
            }
        });
    }
}

/**
 * Fetch sales data for date range
 */
function fetchSalesData(startDate, endDate) {
    fetch(`api/sales_data.php?start=${startDate}&end=${endDate}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && window.salesChart) {
                window.salesChart.data.labels = data.labels;
                window.salesChart.data.datasets[0].data = data.values;
                window.salesChart.update();
            }
        })
        .catch(error => {
            console.error('Error fetching sales data:', error);
        });
}

/**
 * Initialize export buttons
 */
function initExportButtons() {
    const exportButtons = document.querySelectorAll('.export-btn');
    
    exportButtons.forEach(button => {
        button.addEventListener('click', function() {
            const format = this.dataset.format || 'csv';
            const type = this.dataset.type || 'sales';
            const dateRange = document.querySelector('.date-range-picker')?.value || '';
            
            // Show loading
            this.disabled = true;
            const originalText = this.textContent;
            this.textContent = 'Exporting...';
            
            fetch(`api/export.php?format=${format}&type=${type}&range=${encodeURIComponent(dateRange)}`)
                .then(response => {
                    if (response.ok) {
                        return response.blob();
                    }
                    throw new Error('Export failed');
                })
                .then(blob => {
                    // Create download link
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `${type}_export_${new Date().toISOString().split('T')[0]}.${format}`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    showNotification(`Export successful. ${type} data downloaded.`, 'success');
                })
                .catch(error => {
                    console.error('Export error:', error);
                    showNotification('Export failed. Please try again.', 'error');
                })
                .finally(() => {
                    // Restore button
                    this.disabled = false;
                    this.textContent = originalText;
                });
        });
    });
}

/**
 * Initialize product barcode scanner
 */
function initProductScanner() {
    const scannerInput = document.querySelector('.barcode-scanner-input');
    if (scannerInput) {
        scannerInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const barcode = this.value.trim();
                
                if (barcode) {
                    fetch(`api/get_product.php?barcode=${encodeURIComponent(barcode)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Add product to form
                                addProductToForm(data.product);
                                this.value = '';
                                showNotification(`Product "${data.product.name}" added`, 'success');
                            } else {
                                showNotification('Product not found', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showNotification('Error fetching product', 'error');
                        });
                }
            }
        });
    }
}

/**
 * Add product to form
 */
function addProductToForm(product) {
    const form = document.querySelector('#productForm');
    if (!form) return;
    
    // Set form values
    form.querySelector('[name="barcode"]').value = product.barcode;
    form.querySelector('[name="name"]').value = product.name;
    form.querySelector('[name="category_id"]').value = product.category_id;
    form.querySelector('[name="sale_price"]').value = product.sale_price;
    
    // Focus on quantity field
    form.querySelector('[name="quantity"]')?.focus();
}

/**
 * Generate barcode
 */
function generateBarcode() {
    const barcodeInput = document.querySelector('[name="barcode"]');
    if (barcodeInput && !barcodeInput.value) {
        // Generate random barcode (13 digits)
        const barcode = '890' + Math.random().toString().slice(2, 13);
        barcodeInput.value = barcode;
        showNotification('Barcode generated', 'info');
    }
}

/**
 * Print report
 */
function printReport() {
    const printContent = document.querySelector('.report-content');
    if (printContent) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>ZIC Mart - Report</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .report-header { text-align: center; margin-bottom: 30px; }
                    .report-title { font-size: 24px; font-weight: bold; }
                    .report-date { color: #666; margin-top: 5px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
                    th { background-color: #f5f5f5; }
                    .total-row { font-weight: bold; background-color: #f9f9f9; }
                    .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                ${printContent.innerHTML}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
}

// Register admin keyboard shortcuts
KeyboardShortcuts.register('f1', function() {
    document.querySelector('.manual-search-btn')?.click();
}, { preventDefault: true });

KeyboardShortcuts.register('f2', function() {
    document.querySelector('.export-btn')?.click();
}, { preventDefault: true });

KeyboardShortcuts.register('f5', function() {
    window.location.reload();
}, { preventDefault: true });

KeyboardShortcuts.register('escape', function() {
    const modal = document.querySelector('.modal.show');
    if (modal) {
        modal.classList.remove('show');
    }
}, { preventDefault: false });

// Export admin functions
window.Admin = {
    printReport,
    generateBarcode,
    fetchSalesData,
    sortTable
};