<?php
require_once '../config/constants.php';

// Redirect to login if not authenticated as admin
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Set theme
if(!isset($_SESSION['admin_theme'])) {
    $_SESSION['admin_theme'] = 'light';
}

if(isset($_POST['toggle_theme'])) {
    $_SESSION['admin_theme'] = $_SESSION['admin_theme'] === 'light' ? 'dark' : 'light';
}

// Get pending message count for badge
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();
$pending_count = $conn->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['admin_theme']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --admin-bg: #f8f9fa;
            --admin-card-bg: #ffffff;
            --admin-text: #333333;
            --admin-border: #dee2e6;
            --sidebar-bg: #2c3e50;
            --sidebar-text: #ecf0f1;
            --primary-color: #d4af37;
            --accent-color: #c0a062;
            --primary-color: #d4af37;
            --secondary-color: #2c3e50;
            --accent-color: #c0a062;
            --text-dark: #333;
            --text-light: #666;
            --bg-light: #f8f9fa;
            --border-color: #dee2e6;
        }

        [data-theme="dark"] {
            --admin-bg: #1a1a1a;
            --admin-card-bg: #2d2d2d;
            --admin-text: #ffffff;
            --admin-border: #444444;
            --sidebar-bg: #1a1a1a;
            --sidebar-text: #cccccc;
        }

        body {
            background-color: var(--admin-bg);
            color: var(--admin-text);
            transition: all 0.3s ease;
        }

        .sidebar {
            min-height: 100vh;
        }

        .sidebar .nav-link {
            color: var(--sidebar-text);
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(212, 175, 55, 0.1);
            color: var(--primary-color);
        }

        .admin-role-badge {
            background: var(--primary-color);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 5px;
        }

        .bg-dark{
            background-color: #000000 !important;
        }

        .btn-gold {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 25px;
            transition: all 0.3s ease;
        }

        .btn-gold:hover {
            background-color: var(--accent-color);
            color: white;
            transform: translateY(-2px);
        }

        .text-gold {
            color: var(--primary-color) !important;
        }

        .active>.page-link, .page-link.active{
            background-color: var(--primary-color) !important;
            border: var(--primary-color) !important;
        }

        .page-link{
            color: var(--dark-color) !important;
        }

        .active .page-link{
             color: white !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar bg-dark p-0">
                <div class="p-3 text-center">
                    <h5 class="text-white mb-1">
                        <img src="<?php echo SITE_URL; ?>/assets/images/logo.webp" style="width: 150px" />
                    </h5>
                    <div class="mt-2">
                        <span class="admin-role-badge">
                            <?php echo ucfirst($_SESSION['admin_role']); ?>
                            <?php if ($_SESSION['is_super_admin']): ?>
                                <i class="fas fa-crown ms-1"></i>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>" href="products.php">
                        <i class="fas fa-box me-2"></i>Products
                    </a>
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>" href="orders.php">
                        <i class="fas fa-shopping-cart me-2"></i>Orders
                    </a>
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" href="categories.php">
                        <i class="fas fa-list me-2"></i>Categories
                    </a>
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                        <i class="fas fa-users me-2"></i>Users
                    </a>

                    <!-- Contact Queries Menu Item -->
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contact-queries.php' ? 'active' : ''; ?>" href="contact-queries.php">
                        <i class="fas fa-envelope me-2"></i>Contact Queries
                        <?php if ($pending_count > 0): ?>
                            <span class="badge bg-danger float-end"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </a>

                    <?php if ($_SESSION['is_super_admin']): ?>
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admins.php' ? 'active' : ''; ?>" href="admins.php">
                        <i class="fas fa-user-shield me-2"></i>Admin Users
                    </a>
                    <?php endif; ?>

                    <!--<a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>-->
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ml-sm-auto p-0">
                <!-- Header -->
                <nav class="navbar navbar-expand-lg admin-header">
                    <div class="container-fluid">
                        <span class="navbar-brand">
                            <?php
                            $pageTitles = [
                                'dashboard.php' => 'Dashboard',
                                'products.php' => 'Products',
                                'orders.php' => 'Orders',
                                'categories.php' => 'Categories',
                                'users.php' => 'Users',
                                'contact-queries.php' => 'Contact Queries',
                                'admins.php' => 'Admin Users',
                                'settings.php' => 'Settings'
                            ];
                            echo $pageTitles[basename($_SERVER['PHP_SELF'])] ?? 'Admin Panel';
                            ?>
                        </span>
                        <div class="navbar-nav ms-auto align-items-center">
                            <!-- Theme Toggle -->
                            <!--<form method="POST" class="d-inline">
                                <button type="submit" name="toggle_theme" class="btn btn-sm btn-outline-secondary me-3">
                                    <i class="fas <?php echo $_SESSION['admin_theme'] === 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
                                </button>
                            </form>-->

                            <div class="dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle me-1"></i>
                                    <?php echo $_SESSION['admin_name']; ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/" target="_blank"><i class="fas fa-external-link-alt me-2"></i>View Site</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </nav>