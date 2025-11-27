<?php
require_once '../config/constants.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Store some session data for feedback message if needed
$user_name = $_SESSION['user_name'] ?? 'User';
$is_admin = isset($_SESSION['admin_logged_in']);

// Clear all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Clear any client-side storage (via JavaScript)
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out - <?php echo SITE_NAME; ?></title>

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
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .logout-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 50px;
            text-align: center;
            max-width: 500px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .logout-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }

        .brand-font {
/*             font-family: 'Playfair Display', serif; */
        }

        .logout-icon {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }

        .success-animation {
            display: inline-block;
            animation: successPulse 2s ease-in-out;
        }

        .btn-gold {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-gold:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        .btn-outline-gold {
            color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-gold:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .countdown {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-top: 20px;
        }

        .security-tips {
            background: rgba(212, 175, 55, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }

        .security-tips h6 {
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .security-tips ul {
            margin: 0;
            padding-left: 20px;
        }

        .security-tips li {
            margin-bottom: 8px;
            color: var(--text-light);
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        @keyframes successPulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .progress-bar {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin: 20px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            border-radius: 2px;
            animation: progress 5s linear;
        }

        @keyframes progress {
            0% {
                width: 100%;
            }
            100% {
                width: 0%;
            }
        }

        @media (max-width: 576px) {
            .logout-container {
                padding: 30px 20px;
            }

            .logout-icon {
                font-size: 3rem;
            }

            .btn-gold, .btn-outline-gold {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <!-- Animated Icon -->
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt success-animation"></i>
        </div>

        <!-- Main Content -->
        <h1 class="brand-font text-gold mb-3">Successfully Logged Out</h1>

        <p class="text-muted mb-4">
            You have been successfully logged out of your account.
            <?php if ($is_admin): ?>
                Thank you for managing the store.
            <?php else: ?>
                Thank you for shopping with <?php echo SITE_NAME; ?>!
            <?php endif; ?>
        </p>

        <!-- Progress Bar for Auto-redirect -->
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>

        <!-- Action Buttons -->
        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
            <a href="<?php echo SITE_URL; ?>/" class="btn btn-gold me-md-2">
                <i class="fas fa-home me-2"></i>Back to Home
            </a>
            <a href="<?php echo SITE_URL; ?>/login" class="btn btn-outline-gold">
                <i class="fas fa-sign-in-alt me-2"></i>Login Again
            </a>
        </div>

        <!-- Countdown Timer -->
        <div class="countdown" id="countdown">
            Redirecting to home page in <span id="countdown-timer">5</span> seconds...
        </div>

        <!-- Security Tips -->
        <div class="security-tips">
            <h6><i class="fas fa-shield-alt me-2"></i>Security Tips</h6>
            <ul>
                <li>Always log out from shared devices</li>
                <li>Use strong, unique passwords</li>
                <li>Enable two-factor authentication if available</li>
                <li>Keep your contact information updated</li>
            </ul>
        </div>

        <!-- Quick Links -->
        <div class="mt-4">
            <small class="text-muted">
                Need help?
                <a href="<?php echo SITE_URL; ?>/contact" class="text-gold text-decoration-none">Contact Support</a>
                or
                <a href="<?php echo SITE_URL; ?>/privacy-policy" class="text-gold text-decoration-none">View Privacy Policy</a>
            </small>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Countdown timer for auto-redirect
            let countdown = 5;
            const countdownElement = document.getElementById('countdown-timer');
            const countdownContainer = document.getElementById('countdown');

            const countdownInterval = setInterval(function() {
                countdown--;
                countdownElement.textContent = countdown;

                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = '<?php echo SITE_URL; ?>/';
                }
            }, 1000);

            // Clear client-side storage
            function clearClientStorage() {
                try {
                    // Clear sessionStorage
                    sessionStorage.clear();

                    // Clear localStorage items related to the app
                    const keysToRemove = [];
                    for (let i = 0; i < localStorage.length; i++) {
                        const key = localStorage.key(i);
                        if (key.includes('cart') || key.includes('user') || key.includes('theme')) {
                            keysToRemove.push(key);
                        }
                    }

                    keysToRemove.forEach(key => {
                        localStorage.removeItem(key);
                    });
                } catch (error) {
                    console.log('Client storage cleared successfully');
                }
            }

            // Clear storage on page load
            clearClientStorage();

            // Add smooth entrance animation
            const container = document.querySelector('.logout-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(20px)';

            setTimeout(() => {
                container.style.transition = 'all 0.5s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);

            // Add click event to cancel auto-redirect
            countdownContainer.addEventListener('click', function() {
                clearInterval(countdownInterval);
                this.innerHTML = '<i class="fas fa-times me-1"></i>Auto-redirect cancelled';
                this.style.cursor = 'default';
                this.style.color = 'var(--text-light)';
            });

            // Add keyboard event to cancel redirect
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    clearInterval(countdownInterval);
                    countdownContainer.innerHTML = '<i class="fas fa-times me-1"></i>Auto-redirect cancelled (Press ESC)';
                    countdownContainer.style.cursor = 'default';
                    countdownContainer.style.color = 'var(--text-light)';
                }
            });

            // Progressive enhancement: If JavaScript is disabled, hide countdown
            countdownContainer.style.display = 'block';
        });

        // Service Worker cleanup (if used)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                for (let registration of registrations) {
                    registration.unregister();
                }
            });
        }
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>