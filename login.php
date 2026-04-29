<?php
session_start();

// Database configuration
$host = "localhost";
$user = "root";
$pass = "";
$db = "zic_mart_pos";

// Create connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: cashier/pos.php");
    }
    exit();
}

// Process login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    
    // Check user in database
    $sql = "SELECT * FROM users WHERE username = '$username' AND is_active = 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify password - using password_verify for hashed passwords
        if (password_verify($password, $user['password'])) {
            // Password is correct
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['user_role']; // Changed from 'role' to 'user_role'
            
            if ($user['user_role'] == 'admin') {
                header("Location: admin/index.php");
            } else {
                header("Location: cashier/pos.php");
            }
            exit();
        } else {
            // For demo/testing only - remove this in production
            if ($password == '123') {
                // If using the demo password '123', check if it's actually '123'
                // This is only for initial setup/testing
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['user_role'];
                
                if ($user['user_role'] == 'admin') {
                    header("Location: admin/index.php");
                } else {
                    header("Location: cashier/pos.php");
                }
                exit();
            } else {
                $error = "Invalid password!";
            }
        }
    } else {
        $error = "Invalid username or account is inactive!";
    }
}

// Get store name
$store_sql = "SELECT store_name FROM store_settings LIMIT 1";
$store_result = $conn->query($store_sql);
$store_name = "ZIC Mart POS";
if ($store_result->num_rows > 0) {
    $store_row = $store_result->fetch_assoc();
    $store_name = $store_row['store_name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo $store_name; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .store-info { text-align: center; margin-bottom: 30px; }
        .store-name {
            color: #2d3748;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .store-subtitle { color: #718096; font-size: 14px; }
        .login-title {
            text-align: center;
            color: #4a5568;
            margin-bottom: 30px;
            font-size: 18px;
        }
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            color: #4a5568;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e0;
            border-radius: 5px;
            font-size: 16px;
        }
        input:focus { outline: none; border-color: #667eea; }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-login:hover { opacity: 0.9; }
        .error {
            background: #fed7d7;
            color: #c53030;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .demo {
            background: #e6fffa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 14px;
        }
        .demo h4 { margin-bottom: 10px; color: #285e61; }
        .demo div { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .demo .role { font-weight: bold; }
        .note {
            font-size: 12px;
            color: #718096;
            margin-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="store-info">
            <div class="store-name"><?php echo $store_name; ?></div>
            <div class="store-subtitle">Point of Sale System</div>
        </div>
        
        <div class="login-title">Please login to continue</div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
        
        
        
        <div class="note">Default password for all users is <strong>123</strong></div>
    </div>
</body>
</html>
<?php $conn->close(); ?>