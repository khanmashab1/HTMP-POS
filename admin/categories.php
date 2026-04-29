<?php
require_once '../includes/config.php';
checkLogin();
checkAdmin();

$message = '';

// Handle category operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add category
    if (isset($_POST['add_category'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $description = $conn->real_escape_string($_POST['description']);
        
        $sql = "INSERT INTO categories (name, description) VALUES ('$name', '$description')";
        if ($conn->query($sql) === TRUE) {
            $message = "<div style='background:#c6f6d5;color:#22543d;padding:10px;border-radius:5px;margin-bottom:20px;'>Category added successfully!</div>";
        } else {
            $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Error: " . $conn->error . "</div>";
        }
    }
    
    // Update category
    elseif (isset($_POST['update_category'])) {
        $category_id = intval($_POST['category_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $description = $conn->real_escape_string($_POST['description']);
        
        $sql = "UPDATE categories SET 
                name = '$name',
                description = '$description'
                WHERE id = '$category_id'";
        
        if ($conn->query($sql) === TRUE) {
            $message = "<div style='background:#c6f6d5;color:#22543d;padding:10px;border-radius:5px;margin-bottom:20px;'>Category updated successfully!</div>";
        } else {
            $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Error: " . $conn->error . "</div>";
        }
    }
}

// Handle delete via GET
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Check if category has products before deleting
    $check_sql = "SELECT COUNT(*) as count FROM products WHERE category_id = '$delete_id'";
    $check_result = $conn->query($check_sql);
    $check_data = $check_result->fetch_assoc();
    
    if ($check_data['count'] > 0) {
        $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Cannot delete category. It has products assigned to it!</div>";
    } else {
        $delete_sql = "DELETE FROM categories WHERE id = '$delete_id'";
        if ($conn->query($delete_sql) === TRUE) {
            $message = "<div style='background:#c6f6d5;color:#22543d;padding:10px;border-radius:5px;margin-bottom:20px;'>Category deleted successfully!</div>";
        } else {
            $message = "<div style='background:#fed7d7;color:#c53030;padding:10px;border-radius:5px;margin-bottom:20px;'>Error: " . $conn->error . "</div>";
        }
    }
}

// Get category for editing
$edit_category = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_sql = "SELECT * FROM categories WHERE id = '$edit_id'";
    $edit_result = $conn->query($edit_sql);
    if ($edit_result->num_rows > 0) {
        $edit_category = $edit_result->fetch_assoc();
    }
}

// Get all categories
$categories_sql = "SELECT c.*, 
                  (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
                  FROM categories c 
                  ORDER BY name";
$categories_result = $conn->query($categories_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - <?php echo $settings['store_name']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f7fafc; }
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
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
        
        /* Main Content */
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
        
        /* Form */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .form-title { 
            font-size: 18px; 
            color: #2d3748; 
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
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
        .btn-cancel {
            background: #718096;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }
        
        /* Table */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            overflow-x: auto;
        }
        .table-title { font-size: 18px; color: #2d3748; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { background: #f7fafc; padding: 12px; text-align: left; color: #4a5568; font-weight: 600; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .btn-action {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            margin-right: 5px;
            display: inline-block;
            border: none;
            cursor: pointer;
        }
        .btn-edit { background: #4299e1; color: white; }
        .btn-delete { background: #fc8181; color: white; }
        .btn-action:hover { opacity: 0.9; transform: translateY(-1px); }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            width: 500px;
            max-width: 90%;
            border-radius: 10px;
            overflow: hidden;
        }
        .modal-header {
            padding: 20px;
            background: #4299e1;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            padding: 20px;
            background: #f7fafc;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-confirm { background: #fc8181; color: white; }
        .btn-cancel-modal { background: #718096; color: white; }
        
        /* Product count badge */
        .product-count {
            background: #4299e1;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin-left: 5px;
        }
        .product-count-zero {
            background: #cbd5e0;
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
                <li class="nav-item"><a href="products.php" class="nav-link">📦 Products</a></li>
                <li class="nav-item"><a href="categories.php" class="nav-link active">🏷️ Categories</a></li>
                <li class="nav-item"><a href="sales_report.php" class="nav-link">📈 Sales Report</a></li>
                <li class="nav-item"><a href="returns.php" class="nav-link">🔄 Returns</a></li>
                <li class="nav-item"><a href="settings.php" class="nav-link">⚙️ Settings</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <main class="main">
            <div class="header">
                <h1 class="page-title">Category Management</h1>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
            
            <?php echo $message; ?>
            
            <!-- Add/Edit Category Form -->
            <div class="form-container">
                <h2 class="form-title">
                    <?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?>
                    <?php if ($edit_category): ?>
                    <a href="categories.php" class="btn-cancel">Cancel Edit</a>
                    <?php endif; ?>
                </h2>
                <form method="POST" action="">
                    <?php if ($edit_category): ?>
                    <input type="hidden" name="category_id" value="<?php echo $edit_category['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Category Name *</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo $edit_category ? htmlspecialchars($edit_category['name']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description"><?php echo $edit_category ? htmlspecialchars($edit_category['description']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" name="<?php echo $edit_category ? 'update_category' : 'add_category'; ?>" 
                            class="btn-submit">
                        <?php echo $edit_category ? 'Update Category' : 'Add Category'; ?>
                    </button>
                    
                    <?php if ($edit_category): ?>
                    <a href="categories.php" class="btn-cancel">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Categories List -->
            <div class="table-container">
                <h2 class="table-title">All Categories</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $categories_result->data_seek(0);
                        while($category = $categories_result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo $category['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($category['name']); ?>
                                <span class="product-count <?php echo $category['product_count'] == 0 ? 'product-count-zero' : ''; ?>">
                                    <?php echo $category['product_count']; ?> products
                                </span>
                            </td>
                            <td><?php echo $category['description'] ? htmlspecialchars($category['description']) : '-'; ?></td>
                            <td><?php echo $category['product_count']; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($category['created_at'])); ?></td>
                            <td>
                                <a href="?edit_id=<?php echo $category['id']; ?>" class="btn-action btn-edit">✏️ Edit</a>
                                <button onclick="confirmDelete(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars(addslashes($category['name'])); ?>', <?php echo $category['product_count']; ?>)" 
                                        class="btn-action btn-delete">🗑️ Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
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
                <p>Are you sure you want to delete category: <strong id="deleteCategoryName"></strong>?</p>
                <p id="productCountWarning" style="color: #f56565; font-size: 14px; display: none;">
                    ⚠️ This category has <span id="productCount"></span> products. Deleting will remove all products from this category!
                </p>
                <p style="color: #f56565; font-size: 14px;">⚠️ This action cannot be undone!</p>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal()" class="btn-action btn-cancel-modal">Cancel</button>
                <button onclick="proceedDelete()" class="btn-action btn-confirm">Delete</button>
            </div>
        </div>
    </div>

    <script>
        let categoryToDelete = null;
        let categoryNameToDelete = '';
        
        // Confirm delete function
        function confirmDelete(categoryId, categoryName, productCount) {
            categoryToDelete = categoryId;
            categoryNameToDelete = categoryName;
            document.getElementById('deleteCategoryName').textContent = categoryName;
            
            const warningElement = document.getElementById('productCountWarning');
            const countElement = document.getElementById('productCount');
            
            if (productCount > 0) {
                countElement.textContent = productCount;
                warningElement.style.display = 'block';
            } else {
                warningElement.style.display = 'none';
            }
            
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
            categoryToDelete = null;
            categoryNameToDelete = '';
        }
        
        // Proceed with delete
        function proceedDelete() {
            if (categoryToDelete) {
                window.location.href = '?delete_id=' + categoryToDelete;
            }
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Auto-focus first input field
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input');
            if (firstInput) {
                firstInput.focus();
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Show message for a few seconds then fade out
        setTimeout(function() {
            const message = document.querySelector('[style*="background:#c6f6d5"]');
            if (message) {
                message.style.transition = 'opacity 0.5s';
                message.style.opacity = '0';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.parentNode.removeChild(message);
                    }
                }, 500);
            }
        }, 3000);
    </script>
</body>
</html>
<?php $conn->close(); ?>