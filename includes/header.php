<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'ZIC Mart POS'; ?></title>
    
    <!-- Common CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php if (isset($page) && $page == 'admin'): ?>
        <link rel="stylesheet" href="../assets/css/admin.css">
    <?php elseif (isset($page) && $page == 'pos'): ?>
        <link rel="stylesheet" href="../assets/css/pos.css">
    <?php elseif (isset($page) && $page == 'login'): ?>
        <link rel="stylesheet" href="../assets/css/login.css">
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <!-- Common JavaScript -->
    <script src="../assets/js/main.js"></script>
</head>
<body>
    <!-- Header content will be included by each page -->
    <?php if (isset($show_header) && $show_header): ?>
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1><?php echo isset($store_name) ? $store_name : 'ZIC Mart POS'; ?></h1>
                </div>
                <div class="user-info">
                    <?php if (isset($_SESSION['full_name'])): ?>
                        <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                        <a href="../logout.php" class="btn-logout">Logout</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>
    
    <main>