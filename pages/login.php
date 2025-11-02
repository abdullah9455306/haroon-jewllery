<?php
// Start session and check login status FIRST - before any includes
session_start();

// Redirect if already logged in - MUST be before any output
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit;
}

// Now include other files
require_once '../config/constants.php';

// Initialize database connection
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$pageTitle = "Login";

// Handle form submission
$error_message = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    // Validation
    $errors = [];

    if (empty($email)) {
        $errors[] = "Email address is required";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    }

    if (empty($errors)) {
        try {
            // Check if user exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];

                // Set remember me cookie if requested
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + (30 * 24 * 60 * 60); // 30 days

                    setcookie('remember_token', $token, $expiry, '/');

                    // Store token in database
                    $token_stmt = $conn->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?");
                    $token_stmt->execute([$token, date('Y-m-d H:i:s', $expiry), $user['id']]);
                }

                // Redirect to intended page or profile
                $redirect_url = $_SESSION['redirect_url'] ?? 'profile.php';
                unset($_SESSION['redirect_url']);

                // Use header redirect for successful login
                header("Location: " . $redirect_url);
                exit;

            } else {
                $error_message = "Invalid email or password";
            }

        } catch (PDOException $e) {
            $error_message = "Login failed. Please try again.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Check for redirect URL
if (!isset($_SESSION['redirect_url']) && isset($_SERVER['HTTP_REFERER'])) {
    $referer = parse_url($_SERVER['HTTP_REFERER']);
    $current = parse_url(SITE_URL);

    // Only store external referrers
    if ($referer['host'] !== $current['host']) {
        $_SESSION['redirect_url'] = $_SERVER['HTTP_REFERER'];
    }
}

// Include header AFTER all processing and potential redirects
require_once '../includes/header.php';
?>

<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-xl-5">
            <!-- Login Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <!-- Header -->
                    <div class="text-center mb-5">
                        <h1 class="display-5 brand-font mb-3">Welcome Back</h1>
                        <p class="text-muted">Sign in to your <?php echo SITE_NAME; ?> account</p>
                    </div>

                    <!-- Error Message -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form method="POST" action="" id="loginForm" novalidate>
                        <!-- Email -->
                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($email); ?>"
                                       required placeholder="Enter your email address">
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-4">
                            <label for="password" class="form-label">Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password"
                                       required placeholder="Enter your password">
                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="password">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="invalid-feedback">Please enter your password.</div>
                            </div>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">
                                    Remember me
                                </label>
                            </div>
                            <!--<a href="forgot-password.php" class="text-gold text-decoration-none">
                                <i class="fas fa-key me-1"></i>Forgot Password?
                            </a>-->
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid gap-2 mb-4">
                            <button type="submit" class="btn btn-gold btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </div>

                        <!-- Divider -->
                        <!--<div class="position-relative text-center mb-4">
                            <hr>
                            <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted">
                                or continue with
                            </span>
                        </div>-->

                        <!-- Social Login Buttons -->
                        <!--<div class="row g-2 mb-4">
                            <div class="col-md-6">
                                <button type="button" class="btn btn-outline-dark w-100 social-login-btn" data-provider="google">
                                    <i class="fab fa-google me-2"></i>Google
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button type="button" class="btn btn-outline-primary w-100 social-login-btn" data-provider="facebook">
                                    <i class="fab fa-facebook-f me-2"></i>Facebook
                                </button>
                            </div>
                        </div>-->

                        <!-- Registration Link -->
                        <div class="text-center">
                            <p class="text-muted mb-0">
                                Don't have an account?
                                <a href="register.php" class="text-gold fw-bold text-decoration-none">Create Account</a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Features -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center py-3">
                            <div class="row">
                                <div class="col-md-4 mb-2 mb-md-0">
                                    <i class="fas fa-shield-alt text-gold me-2"></i>
                                    <small class="text-muted">Secure Login</small>
                                </div>
                                <div class="col-md-4 mb-2 mb-md-0">
                                    <i class="fas fa-lock text-gold me-2"></i>
                                    <small class="text-muted">Encrypted Data</small>
                                </div>
                                <div class="col-md-4">
                                    <i class="fas fa-user-shield text-gold me-2"></i>
                                    <small class="text-muted">Privacy Protected</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Benefits Sidebar -->
        <div class="col-lg-4 col-xl-4 mt-5 mt-lg-0">
            <div class="ps-xl-4">
                <h4 class="brand-font mb-4">Benefits of Your Account</h4>

                <!-- Benefit 1 -->
                <div class="d-flex mb-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-shopping-bag fa-2x text-gold"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6>Quick Checkout</h6>
                        <p class="text-muted small mb-0">Save your details for faster purchases</p>
                    </div>
                </div>

                <!-- Benefit 2 -->
                <div class="d-flex mb-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-heart fa-2x text-gold"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6>Wishlist</h6>
                        <p class="text-muted small mb-0">Save your favorite jewelry pieces</p>
                    </div>
                </div>

                <!-- Benefit 3 -->
                <div class="d-flex mb-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-box fa-2x text-gold"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6>Order Tracking</h6>
                        <p class="text-muted small mb-0">Track your orders in real-time</p>
                    </div>
                </div>

                <!-- Benefit 4 -->
                <div class="d-flex mb-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-tags fa-2x text-gold"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6>Exclusive Offers</h6>
                        <p class="text-muted small mb-0">Get members-only discounts and early access</p>
                    </div>
                </div>

                <!-- Benefit 5 -->
                <div class="d-flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-history fa-2x text-gold"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6>Order History</h6>
                        <p class="text-muted small mb-0">Easy access to your purchase history</p>
                    </div>
                </div>

                <!-- Trust Badges -->
                <div class="mt-5 pt-4 border-top">
                    <h6 class="mb-3">Trusted by Thousands</h6>
                    <div class="row text-center">
                        <div class="col-4 mb-3">
                            <div class="trust-stat">
                                <div class="h5 text-gold mb-1">10K+</div>
                                <small class="text-muted">Happy Customers</small>
                            </div>
                        </div>
                        <div class="col-4 mb-3">
                            <div class="trust-stat">
                                <div class="h5 text-gold mb-1">35+</div>
                                <small class="text-muted">Years Experience</small>
                            </div>
                        </div>
                        <div class="col-4 mb-3">
                            <div class="trust-stat">
                                <div class="h5 text-gold mb-1">4.9</div>
                                <small class="text-muted">Customer Rating</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.login-container {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    min-height: 100vh;
}

.card {
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.card:hover {
    border-color: var(--primary-color);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.text-gold {
    color: var(--primary-color) !important;
}

.brand-font {
/*     font-family: 'Playfair Display', serif; */
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

.social-login-btn {
    padding: 10px;
    border: 2px solid;
    font-weight: 500;
    transition: all 0.3s ease;
}

.social-login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.input-group-text {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
}

.trust-stat {
    padding: 10px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.trust-stat:hover {
    background: rgba(212, 175, 55, 0.1);
}

/* Loading animation */
.btn-loading {
    position: relative;
    color: transparent;
}

.btn-loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: -10px;
    margin-top: -10px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-right-color: transparent;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Password toggle animation */
.toggle-password {
    transition: all 0.3s ease;
}

.toggle-password:hover {
    background-color: var(--primary-color);
    color: white;
}

@media (max-width: 768px) {
    .display-5 {
        font-size: 2.5rem;
    }

    .card-body {
        padding: 2rem !important;
    }

    .ps-xl-4 {
        padding-left: 0 !important;
        margin-top: 2rem;
    }
}

/* Shake animation for invalid fields */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.is-invalid {
    animation: shake 0.5s ease-in-out;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                targetInput.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
    });

    // Social login handlers
    document.querySelectorAll('.social-login-btn').forEach(button => {
        button.addEventListener('click', function() {
            const provider = this.getAttribute('data-provider');
            handleSocialLogin(provider);
        });
    });

    function handleSocialLogin(provider) {
        // Show loading state
        const button = document.querySelector(`[data-provider="${provider}"]`);
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Connecting...';
        button.disabled = true;

        // Simulate social login process
        setTimeout(() => {
            // In a real implementation, this would redirect to OAuth endpoint
            alert(`${provider.charAt(0).toUpperCase() + provider.slice(1)} login would be implemented here.`);
            button.innerHTML = originalText;
            button.disabled = false;
        }, 1500);
    }

    // Form submission with validation
    loginForm.addEventListener('submit', function(e) {
        let valid = true;

        // Clear previous validation
        this.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });

        // Email validation
        if (!emailInput.value.trim() || !isValidEmail(emailInput.value)) {
            emailInput.classList.add('is-invalid');
            valid = false;
        }

        // Password validation
        if (!passwordInput.value.trim()) {
            passwordInput.classList.add('is-invalid');
            valid = false;
        }

        if (!valid) {
            e.preventDefault();

            // Scroll to first error
            const firstError = this.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                firstError.focus();
            }
        } else {
            // Add loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;

            // Simulate API call delay
            setTimeout(() => {
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
            }, 2000);
        }
    });

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Auto-focus on email field
    emailInput.focus();

    // Demo credentials helper (remove in production)
//     const demoCredentials = document.createElement('div');
//     demoCredentials.className = 'alert alert-info mt-3';
//     demoCredentials.innerHTML = `
//         <strong>Demo Credentials:</strong><br>
//         Email: demo@haroonjewellery.com<br>
//         Password: Demo123
//     `;

    // Only show in development environment
//     if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
//         loginForm.parentNode.insertBefore(demoCredentials, loginForm.nextSibling);
//     }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + / to focus on email
        if (e.ctrlKey && e.key === '/') {
            e.preventDefault();
            emailInput.focus();
        }

        // Ctrl + . to focus on password
        if (e.ctrlKey && e.key === '.') {
            e.preventDefault();
            passwordInput.focus();
        }

        // Enter to submit form when both fields are filled
        if (e.key === 'Enter' && emailInput.value && passwordInput.value) {
            loginForm.requestSubmit();
        }
    });

    // Remember me functionality
    const rememberCheckbox = document.getElementById('remember_me');

    // Check if user previously selected remember me
    if (localStorage.getItem('remember_email')) {
        emailInput.value = localStorage.getItem('remember_email');
        rememberCheckbox.checked = true;
    }

    rememberCheckbox.addEventListener('change', function() {
        if (this.checked && emailInput.value) {
            localStorage.setItem('remember_email', emailInput.value);
        } else {
            localStorage.removeItem('remember_email');
        }
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>