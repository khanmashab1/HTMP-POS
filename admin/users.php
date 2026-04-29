<?php
require_once '../includes/config.php';
checkLogin();
checkAdmin();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$error = '';
$success = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        // Add new user
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $user_role = $conn->real_escape_string($_POST['user_role']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        if (empty($full_name) || empty($username) || empty($password)) {
            $error = "All fields are required!";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters!";
        } else {
            // Check if username already exists
            $check_sql = "SELECT id FROM users WHERE username = '$username'";
            $check_result = $conn->query($check_sql);
            
            if ($check_result->num_rows > 0) {
                $error = "Username already exists!";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user
                $sql = "INSERT INTO users (full_name, username, password, user_role, is_active, created_at) 
                        VALUES ('$full_name', '$username', '$hashed_password', '$user_role', '$is_active', NOW())";
                
                if ($conn->query($sql)) {
                    $success = true;
                    $message = "User added successfully!";
                    $action = 'list'; // Redirect to list view
                } else {
                    $error = "Error adding user: " . $conn->error;
                }
            }
        }
    } elseif (isset($_POST['edit_user'])) {
        // Edit existing user
        $user_id = intval($_POST['user_id']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $username = $conn->real_escape_string($_POST['username']);
        $user_role = $conn->real_escape_string($_POST['user_role']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $change_password = isset($_POST['change_password']) ? 1 : 0;
        
        // Validation
        if (empty($full_name) || empty($username)) {
            $error = "Name and username are required!";
        } else {
            // Check if username already exists (excluding current user)
            $check_sql = "SELECT id FROM users WHERE username = '$username' AND id != '$user_id'";
            $check_result = $conn->query($check_sql);
            
            if ($check_result->num_rows > 0) {
                $error = "Username already exists!";
            } else {
                // Build SQL query
                if ($change_password && !empty($_POST['new_password'])) {
                    $new_password = $_POST['new_password'];
                    $confirm_password = $_POST['confirm_password'];
                    
                    if ($new_password !== $confirm_password) {
                        $error = "New passwords do not match!";
                    } elseif (strlen($new_password) < 6) {
                        $error = "New password must be at least 6 characters!";
                    } else {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $sql = "UPDATE users SET 
                                full_name = '$full_name', 
                                username = '$username', 
                                password = '$hashed_password',
                                user_role = '$user_role',
                                is_active = '$is_active',
                                updated_at = NOW()
                                WHERE id = '$user_id'";
                    }
                } else {
                    $sql = "UPDATE users SET 
                            full_name = '$full_name', 
                            username = '$username', 
                            user_role = '$user_role',
                            is_active = '$is_active',
                            updated_at = NOW()
                            WHERE id = '$user_id'";
                }
                
                if (empty($error) && $conn->query($sql)) {
                    $success = true;
                    $message = "User updated successfully!";
                    $action = 'list';
                } elseif (empty($error)) {
                    $error = "Error updating user: " . $conn->error;
                }
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        // Delete user
        $user_id = intval($_POST['user_id']);
        
        // Prevent deleting own account
        if ($user_id == $_SESSION['user_id']) {
            $error = "You cannot delete your own account!";
        } else {
            $sql = "DELETE FROM users WHERE id = '$user_id'";
            
            if ($conn->query($sql)) {
                $success = true;
                $message = "User deleted successfully!";
            } else {
                $error = "Error deleting user: " . $conn->error;
            }
        }
    }
}

// Get user data for editing
$user_data = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    $sql = "SELECT * FROM users WHERE id = '$user_id'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    } else {
        $error = "User not found!";
        $action = 'list';
    }
}

// Get all users for listing
$users = [];
if ($action == 'list') {
    $sql = "SELECT * FROM users ORDER BY created_at DESC";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo $settings['store_name']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7fafc; }
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #2d3748;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100%;
        }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid #4a5568; }
        .store-name { font-size: 20px; font-weight: bold; }
        .store-subtitle { font-size: 12px; color: #cbd5e0; }
        .user-info { padding: 20px; border-bottom: 1px solid #4a5568; }
        .user-name { font-weight: bold; }
        .nav { list-style: none; }
        .nav-item { border-bottom: 1px solid #4a5568; }
        .nav-link {
            display: block;
            padding: 15px 20px;
            color: #cbd5e0;
            text-decoration: none;
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            background: #4a5568;
            color: white;
            border-left: 4px solid #667eea;
        }
        
        /* Main Content */
        .main { 
            flex: 1; 
            padding: 20px;
            margin-left: 250px;
            width: calc(100% - 250px);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .page-title { 
            font-size: 24px; 
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Header Buttons */
        .header-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .header-btn {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .add-user-btn {
            background: #4299e1;
            color: white;
        }
        .add-user-btn:hover {
            background: #3182ce;
            transform: translateY(-2px);
        }
        .back-btn {
            background: #718096;
            color: white;
        }
        .back-btn:hover {
            background: #4a5568;
            transform: translateY(-2px);
        }
        .logout-btn {
            background: #fc8181;
            color: white;
        }
        .logout-btn:hover {
            background: #e53e3e;
            transform: translateY(-2px);
        }
        
        /* Messages */
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        .error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #fc8181;
        }
        
        /* Form Styles */
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            max-width: 600px;
            margin: 0 auto;
        }
        .form-title {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #cbd5e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .form-check-input {
            width: 18px;
            height: 18px;
        }
        .form-check-label {
            color: #4a5568;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        /* Buttons */
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary {
            background: #4299e1;
            color: white;
        }
        .btn-primary:hover {
            background: #3182ce;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(66, 153, 225, 0.3);
        }
        .btn-secondary {
            background: #718096;
            color: white;
        }
        .btn-secondary:hover {
            background: #4a5568;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #f56565;
            color: white;
        }
        .btn-danger:hover {
            background: #e53e3e;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #48bb78;
            color: white;
        }
        .btn-success:hover {
            background: #38a169;
            transform: translateY(-2px);
        }
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .table-title {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th {
            background: #f7fafc;
            padding: 12px 15px;
            text-align: left;
            color: #4a5568;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #2d3748;
        }
        tr:hover {
            background: #f7fafc;
        }
        .status-active {
            color: #48bb78;
            font-weight: bold;
        }
        .status-inactive {
            color: #f56565;
            font-weight: bold;
        }
        .role-admin {
            background: #c6f6d5;
            color: #22543d;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .role-cashier {
            background: #bee3f8;
            color: #2c5282;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .edit-btn {
            background: #4299e1;
            color: white;
        }
        .edit-btn:hover {
            background: #3182ce;
        }
        .delete-btn {
            background: #f56565;
            color: white;
            border: none;
            cursor: pointer;
        }
        .delete-btn:hover {
            background: #e53e3e;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            .main {
                margin-left: 0;
                width: 100%;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
            }
            .table-title {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
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
                <div>Welcome,</div>
                <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                <div>(<?php echo ucfirst($_SESSION['user_role']); ?>)</div>
            </div>
            
            <ul class="nav">
                <li class="nav-item"><a href="index.php" class="nav-link">📊 Dashboard</a></li>
                <li class="nav-item"><a href="products.php" class="nav-link">📦 Products</a></li>
                <li class="nav-item"><a href="categories.php" class="nav-link">🏷️ Categories</a></li>
                <li class="nav-item"><a href="users.php" class="nav-link active">👥 Users</a></li>
                <li class="nav-item"><a href="sales_report.php" class="nav-link">📈 Sales Report</a></li>
                <li class="nav-item"><a href="returns.php" class="nav-link">🔄 Returns</a></li>
                <li class="nav-item"><a href="settings.php" class="nav-link">⚙️ Settings</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <main class="main">
            <!-- Header -->
            <div class="header">
                <h1 class="page-title">
                    <?php if ($action == 'add'): ?>
                        👤 Add New User
                    <?php elseif ($action == 'edit'): ?>
                        ✏️ Edit User
                    <?php else: ?>
                        👥 User Management
                    <?php endif; ?>
                </h1>
                
                <div class="header-buttons">
                    <?php if ($action == 'add' || $action == 'edit'): ?>
                        <a href="users.php" class="header-btn back-btn">
                            ← Back to Users
                        </a>
                    <?php else: ?>
                        <a href="users.php?action=add" class="header-btn add-user-btn">
                            👤 Add User
                        </a>
                    <?php endif; ?>
                    <a href="../logout.php" class="header-btn logout-btn">
                        🚪 Logout
                    </a>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message || $error): ?>
                <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                    <?php echo $success ? '✅' : '❌'; ?>
                    <?php echo $message ?: $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Content based on action -->
            <?php if ($action == 'add' || $action == 'edit'): ?>
                <!-- Add/Edit User Form -->
                <div class="form-container">
                    <h2 class="form-title">
                        <?php echo $action == 'add' ? 'Add New User' : 'Edit User: ' . ($user_data ? $user_data['full_name'] : ''); ?>
                    </h2>
                    
                    <form method="POST" action="">
                        <?php if ($action == 'edit' && $user_data): ?>
                            <input type="hidden" name="user_id" value="<?php echo $user_data['id']; ?>">
                            <input type="hidden" name="edit_user" value="1">
                        <?php else: ?>
                            <input type="hidden" name="add_user" value="1">
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="full_name">Full Name *</label>
                                <input type="text" 
                                       id="full_name" 
                                       name="full_name" 
                                       class="form-control" 
                                       value="<?php echo $user_data ? htmlspecialchars($user_data['full_name']) : ''; ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="username">Username *</label>
                                <input type="text" 
                                       id="username" 
                                       name="username" 
                                       class="form-control" 
                                       value="<?php echo $user_data ? htmlspecialchars($user_data['username']) : ''; ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <?php if ($action == 'add'): ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="password">Password *</label>
                                    <input type="password" 
                                           id="password" 
                                           name="password" 
                                           class="form-control" 
                                           required 
                                           minlength="6">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="confirm_password">Confirm Password *</label>
                                    <input type="password" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           class="form-control" 
                                           required 
                                           minlength="6">
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="user_role">Role *</label>
                                <select id="user_role" name="user_role" class="form-control" required>
                                    <option value="">Select Role</option>
                                    <option value="admin" <?php echo ($user_data && $user_data['user_role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                    <option value="cashier" <?php echo ($user_data && $user_data['user_role'] == 'cashier') ? 'selected' : ''; ?>>Cashier</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <div class="form-check">
                                    <input type="checkbox" 
                                           id="is_active" 
                                           name="is_active" 
                                           class="form-check-input"
                                           <?php echo ($action == 'add' || ($user_data && $user_data['is_active'] == 1)) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($action == 'edit'): ?>
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" 
                                           id="change_password" 
                                           name="change_password" 
                                           class="form-check-input">
                                    <label class="form-check-label" for="change_password">Change Password</label>
                                </div>
                            </div>
                            
                            <div id="password-fields" style="display: none;">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="new_password">New Password</label>
                                        <input type="password" 
                                               id="new_password" 
                                               name="new_password" 
                                               class="form-control" 
                                               minlength="6">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                                        <input type="password" 
                                               id="confirm_password" 
                                               name="confirm_password" 
                                               class="form-control" 
                                               minlength="6">
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action == 'add' ? '➕ Add User' : '💾 Save Changes'; ?>
                            </button>
                            <a href="users.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
                
            <?php else: ?>
                <!-- Users List -->
                <div class="table-container">
                    <div class="table-title">
                        <div>Registered Users (<?php echo count($users); ?>)</div>
                        <a href="users.php?action=add" class="btn btn-success">
                            👤 Add New User
                        </a>
                    </div>
                    
                    <?php if (count($users) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td>
                                            <span class="role-<?php echo $user['user_role']; ?>">
                                                <?php echo ucfirst($user['user_role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active'] == 1): ?>
                                                <span class="status-active">Active</span>
                                            <?php else: ?>
                                                <span class="status-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="action-btn edit-btn">
                                                    Edit
                                                </a>
                                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="delete_user" class="action-btn delete-btn">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #718096;">
                            <div style="font-size: 48px; margin-bottom: 20px;">👤</div>
                            <h3>No Users Found</h3>
                            <p>Click "Add User" button to create your first user.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Toggle password fields in edit form
        document.addEventListener('DOMContentLoaded', function() {
            const changePasswordCheckbox = document.getElementById('change_password');
            const passwordFields = document.getElementById('password-fields');
            
            if (changePasswordCheckbox && passwordFields) {
                changePasswordCheckbox.addEventListener('change', function() {
                    passwordFields.style.display = this.checked ? 'block' : 'none';
                });
            }
            
            // Form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Password match validation for add form
                    const password = document.getElementById('password');
                    const confirmPassword = document.getElementById('confirm_password');
                    
                    if (password && confirmPassword && password.value !== confirmPassword.value) {
                        e.preventDefault();
                        alert('Passwords do not match!');
                        confirmPassword.focus();
                    }
                    
                    // New password validation for edit form
                    const newPassword = document.getElementById('new_password');
                    const confirmNewPassword = document.getElementById('confirm_password');
                    const changePassword = document.getElementById('change_password');
                    
                    if (changePassword && changePassword.checked && newPassword && confirmNewPassword) {
                        if (newPassword.value !== confirmNewPassword.value) {
                            e.preventDefault();
                            alert('New passwords do not match!');
                            confirmNewPassword.focus();
                        } else if (newPassword.value.length < 6) {
                            e.preventDefault();
                            alert('New password must be at least 6 characters!');
                            newPassword.focus();
                        }
                    }
                });
            });
            
            // Auto-focus first input
            const firstInput = document.querySelector('form input[type="text"]');
            if (firstInput) firstInput.focus();
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>