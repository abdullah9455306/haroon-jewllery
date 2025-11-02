<?php
session_start();
require_once '../config/constants.php';
require_once '../config/database.php';

// Redirect if already logged in as admin
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password";
    } else {
        try {
            // Check if user exists and is admin
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role IN ('admin', 'manager') AND status = 'active'");
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password'])) {
                // Login successful
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['is_super_admin'] = $admin['is_super_admin'];

                // Update last login
                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->execute([$admin['id']]);

                // Redirect to admin dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $error_message = "Invalid admin credentials or insufficient privileges";
            }
        } catch (PDOException $e) {
            $error_message = "Login failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #d4af37;
            --secondary-color: #2c3e50;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .admin-login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }

        .admin-header {
            color: white;
            padding: 30px;
            text-align: center;
        }

        .admin-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 300;
        }

        .admin-header .brand {
            color: var(--primary-color);
            font-weight: 700;
        }

        .admin-body {
            padding: 30px;
        }

        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
        }

        .btn-admin {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-admin:hover {
            background: #b8941f;
            transform: translateY(-1px);
        }



        .back-to-site {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .security-notice {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.875rem;
            color: #6c757d;
        }

        .bg-dark{
            background-color: #000000 !important;
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <!-- Header -->
        <div class="admin-header bg-dark">
            <img src="<?php echo SITE_URL; ?>/assets/images/logo.webp" style="width: 150px" />
            <small style="display: block;margin-top: 10px;">Secure Admin Portal</small>
        </div>

        <!-- Login Form -->
        <div class="admin-body">
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Admin Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email"
                               required placeholder="admin@haroonjewellery.com">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               required placeholder="Enter your password">
                    </div>
                </div>

                <button type="submit" class="btn btn-admin w-100 btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Access Admin Panel
                </button>
            </form>

            <!-- Security Notice -->
            <div class="security-notice">
                <i class="fas fa-shield-alt me-2"></i>
                <strong>Security Notice:</strong> This area is restricted to authorized personnel only.
            </div>

            <!-- Back to Site -->
            <div class="back-to-site">
                <a href="../index.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-2"></i>Back to Main Site
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus on email field
            document.getElementById('email').focus();

            // Add demo credentials in development
//             if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
//                 const demoInfo = document.createElement('div');
//                 demoInfo.className = 'alert alert-info mt-3';
//                 demoInfo.innerHTML = `
//                     <strong>Demo Admin Credentials:</strong><br>
//                     Email: admin@haroonjewellery.com<br>
//                     Password: password
//                 `;
//                 document.querySelector('form').appendChild(demoInfo);
//             }
        });
    </script>
</body>
</html>