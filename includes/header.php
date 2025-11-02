<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and database connection
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

// Initialize database connection
$db = new Database();
$pdo = $db->getConnection();

// Include functions
require_once __DIR__ . '/../includes/functions.php';
?>


<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #d4af37;
            --secondary-color: #2c3e50;
            --accent-color: #c0a062;
            --text-dark: #333;
            --text-light: #666;
            --bg-light: #f8f9fa;
            --border-color: #dee2e6;
        }

        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --text-color: #ffffff;
            --card-bg: #2d2d2d;
            --border-color: #444;
            --bg-light: #2d2d2d;
            --text-dark: #ffffff;
            --text-light: #cccccc;
        }

        body {
            font-family: 'Roboto', sans-serif;
            color: var(--text-dark);
            background-color: var(--bg-color, white);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] body {
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        .brand-font {
/*             font-family: 'inter', serif; */
        }

        .navbar-brand {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color) !important;
        }

        .nav-link {
            font-weight: 500;
            color: var(--secondary-color) !important;
            transition: color 0.3s ease;
        }

        [data-theme="dark"] .nav-link {
            color: var(--text-color) !important;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
        }

        .hero-section {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('assets/images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
        }

        .product-card {
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 30px;
            background-color: var(--card-bg, white);
        }

        [data-theme="dark"] .product-card {
            background-color: var(--card-bg);
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .product-image {
            height: 250px;
            object-fit: cover;
        }

        .price {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.2rem;
        }

        .old-price {
            text-decoration: line-through;
            color: var(--text-light);
            font-size: 0.9rem;
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

        .section-title {
            position: relative;
            margin-bottom: 50px;
            text-align: center;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--primary-color);
        }

        .footer {
            background-color: var(--secondary-color);
            color: white;
            padding: 50px 0 20px;
        }

        .footer a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer a:hover {
            color: var(--primary-color);
        }

        .social-icons a {
            display: inline-block;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            background: var(--primary-color);
            transform: translateY(-3px);
        }

        /* Theme Toggle */
        .theme-toggle {
            background: none;
            border: none;
            color: var(--text-dark);
            font-size: 1.2rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        [data-theme="dark"] .theme-toggle {
            color: var(--text-color);
        }

        .theme-toggle:hover {
            color: var(--primary-color);
        }

        .container-fluid{
            padding-left: 50px;
            padding-right: 50px;
        }

        .bg-dark{
            background-color: #000000 !important;
        }

        #navbarNav .nav-link{
            color: white !important;
        }

        #navbarNav .btn-outline-dark{
            color: white !important;
        }

        .breadcrumb .breadcrumb-item a{
            color: var(--primary-color);
        }

        .list-group-item.active
        {
            background-color:var(--primary-color) !important;
            border-top: 1px solid var(--primary-color);
            border-bottom: 1px solid var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
   <!-- Navigation -->
   <nav class="navbar navbar-expand-lg navbar-light bg-dark shadow-sm sticky-top">
       <div class="container-fluid">
           <a class="navbar-brand brand-font" href="<?php echo SITE_URL; ?>/index.php">
               <img src="<?php echo SITE_URL; ?>/assets/images/logo.webp" style="width: 130px;"/>
           </a>

           <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
               <span class="navbar-toggler-icon"></span>
           </button>

           <div class="collapse navbar-collapse" id="navbarNav">
               <ul class="navbar-nav mx-auto">
                   <li class="nav-item">
                       <a class="nav-link" href="<?php echo SITE_URL; ?>/index.php">Home</a>
                   </li>
                   <li class="nav-item dropdown">
                       <a class="nav-link dropdown-toggle" href="#" id="categoriesDropdown" role="button" data-bs-toggle="dropdown">
                           Categories
                       </a>
                       <ul class="dropdown-menu">
                           <?php
                           $mainCategories = getMainCategories();
                           if (!empty($mainCategories)):
                               foreach ($mainCategories as $category):
                           ?>
                           <li>
                               <a class="dropdown-item" href="<?php echo SITE_URL; ?>/pages/products.php?category=<?php echo urlencode($category['slug']); ?>">
                                   <?php echo htmlspecialchars($category['name']); ?>
                               </a>
                           </li>
                           <?php
                               endforeach;
                           else:
                           ?>
                           <li><a class="dropdown-item" href="#">No categories found</a></li>
                           <?php endif; ?>
                       </ul>
                   </li>
                   <li class="nav-item">
                       <a class="nav-link" href="<?php echo SITE_URL; ?>/pages/products.php">All Products</a>
                   </li>
                   <li class="nav-item">
                       <a class="nav-link" href="<?php echo SITE_URL; ?>/pages/about.php">About Us</a>
                   </li>
                   <li class="nav-item">
                       <a class="nav-link" href="<?php echo SITE_URL; ?>/pages/contact.php">Contact</a>
                   </li>
               </ul>

               <!-- Rest of the header code remains the same -->
               <div class="d-flex align-items-center">
                   <!-- Theme Toggle -->
                   <!--<button class="theme-toggle me-3" id="themeToggle">
                       <i class="fas fa-moon"></i>
                   </button>-->

                   <!-- User Account Section -->
                   <?php if(isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])): ?>
                       <!-- Logged in user menu -->
                       <div class="dropdown me-3">
                           <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                               <i class="fas fa-user me-1"></i> My Account
                           </a>
                           <ul class="dropdown-menu">
                               <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/pages/profile.php">
                                   <i class="fas fa-user-circle me-2"></i>Profile
                               </a></li>
                               <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/pages/orders.php">
                                   <i class="fas fa-shopping-bag me-2"></i>My Orders
                               </a></li>
                               <li><hr class="dropdown-divider"></li>
                               <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/pages/logout.php">
                                   <i class="fas fa-sign-out-alt me-2"></i>Logout
                               </a></li>
                           </ul>
                       </div>
                   <?php else: ?>
                       <!-- Guest user menu -->
                       <div class="d-flex align-items-center">
                           <a href="<?php echo SITE_URL; ?>/pages/login.php" class="nav-link me-3">
                               <i class="fas fa-sign-in-alt me-1"></i> Login
                           </a>
                           <a href="<?php echo SITE_URL; ?>/pages/register.php" class="nav-link">
                               <i class="fas fa-user-plus me-1"></i> Register
                           </a>
                       </div>
                   <?php endif; ?>

                   <!-- Shopping Cart -->
                   <a href="<?php echo SITE_URL; ?>/pages/cart.php" class="btn btn-outline-dark position-relative ms-3">
                       <i class="fas fa-shopping-cart"></i>
                       <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                           <?php
                           $cart_count = 0;
                           if(isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                               $cart_count = count($_SESSION['cart']);
                           }
                           echo $cart_count;
                           ?>
                       </span>
                   </a>
               </div>
           </div>
       </div>
   </nav>

    <!-- Theme Toggle Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = themeToggle.querySelector('i');

            // Initialize theme
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            updateThemeIcon(currentTheme);

            themeToggle.addEventListener('click', function() {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';

                // Update theme
                document.documentElement.setAttribute('data-theme', newTheme);
                updateThemeIcon(newTheme);

                // Save theme preference
                fetch('api/update-theme.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ theme: newTheme })
                });
            });

            function updateThemeIcon(theme) {
                if (theme === 'dark') {
                    themeIcon.className = 'fas fa-sun';
                } else {
                    themeIcon.className = 'fas fa-moon';
                }
            }
        });
    </script>