<?php
require_once '../config/constants.php';
require_once '../includes/header.php';

// Initialize database connection
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$pageTitle = "Create Account";

// Redirect if already logged in using JavaScript
if (isset($_SESSION['user_id'])) {
    echo '<script>window.location.href = "profile.php";</script>';
    exit;
}

// Handle form submission
$success_message = '';
$error_message = '';
$form_data = [
    'name' => '',
    'email' => '',
    'mobile' => '',
    'address' => '',
    'city' => '',
    'country' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $agree_terms = isset($_POST['agree_terms']);

    // Store form data for repopulation
    $form_data = compact('name', 'email', 'mobile', 'address', 'city', 'country');

    // Validation
    $errors = [];

    // Name validation
    if (empty($name)) {
        $errors[] = "Full name is required";
    } elseif (strlen($name) < 2) {
        $errors[] = "Name must be at least 2 characters long";
    }

    // Email validation
    if (empty($email)) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    } else {
        // Check if email already exists
        $email_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $email_check->execute([$email]);
        if ($email_check->rowCount() > 0) {
            $errors[] = "Email address is already registered";
        }
    }

    // Mobile validation
    if (empty($mobile)) {
        $errors[] = "Mobile number is required";
    } elseif (!preg_match('/^[0-9+]{11,15}$/', $mobile)) {
        $errors[] = "Please enter a valid mobile number";
    }

    // Password validation
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }

    // Confirm password
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // Terms agreement
    if (!$agree_terms) {
        $errors[] = "You must agree to the Terms and Conditions";
    }

    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user into database
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, mobile, password, address, city, country, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$name, $email, $mobile, $hashed_password, $address, $city, $country]);

            $user_id = $conn->lastInsertId();

            // Auto-login after registration
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;

            // Set success message for display
            $success_message = "Registration successful! Redirecting to your profile...";
            $redirect_to_profile = true;

        } catch (PDOException $e) {
            $error_message = "Registration failed. Please try again.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <!-- Registration Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <!-- Header -->
                    <div class="text-center mb-5">
                        <h1 class="display-5 brand-font mb-3">Create Account</h1>
                        <p class="text-muted">Join <?php echo SITE_NAME; ?> and discover exquisite jewelry collections</p>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php if (isset($redirect_to_profile) && $redirect_to_profile): ?>
                            <script>
                                setTimeout(function() {
                                    window.location.href = 'profile.php?welcome=1';
                                }, 2000); // Redirect after 2 seconds
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Registration Form -->
                    <form method="POST" action="" id="registrationForm" novalidate autocomplete="off">
                        <div class="row">
                            <!-- Personal Information -->
                            <div class="col-md-6">
                                <h5 class="mb-4 text-gold"><i class="fas fa-user me-2"></i>Personal Information</h5>

                                <!-- Full Name -->
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="name" name="name"
                                               value="<?php echo htmlspecialchars($form_data['name']); ?>"
                                               required minlength="2" autocomplete="name">
                                        <div class="invalid-feedback">Please enter your full name (min 2 characters).</div>
                                    </div>
                                </div>

                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                               required autocomplete="off">
                                        <div class="invalid-feedback">Please enter a valid email address.</div>
                                    </div>
                                </div>

                                <!-- Mobile -->
                                <div class="mb-3">
                                    <label for="mobile" class="form-label">Mobile Number *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                                        <input type="tel" class="form-control" id="mobile" name="mobile"
                                               value="<?php echo htmlspecialchars($form_data['mobile']); ?>"
                                               required pattern="[0-9+]{11,15}" autocomplete="tel">
                                        <div class="invalid-feedback">Please enter a valid mobile number (11-15 digits).</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Address & Password -->
                            <div class="col-md-6">
                                <h5 class="mb-4 text-gold"><i class="fas fa-lock me-2"></i>Security & Address</h5>

                                <!-- Password -->
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password"
                                               required minlength="6" autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <div class="invalid-feedback">Password must be at least 6 characters with uppercase, lowercase, and number.</div>
                                    </div>
                                    <div class="password-strength mt-2">
                                        <div class="progress" style="height: 4px;">
                                            <div class="progress-bar" id="passwordStrength" style="width: 0%"></div>
                                        </div>
                                        <small class="text-muted" id="passwordFeedback">Password strength</small>
                                    </div>
                                </div>

                                <!-- Confirm Password -->
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                               required autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <div class="invalid-feedback">Passwords do not match.</div>
                                    </div>
                                </div>

                                <!-- Address -->
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"
                                              placeholder="Enter your complete address" autocomplete="street-address"><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                                </div>

                                <!-- City & Country -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city"
                                               value="<?php echo htmlspecialchars($form_data['city']); ?>"
                                               placeholder="e.g., Lahore" autocomplete="address-level2">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="country" class="form-label">Country *</label>
                                        <select class="form-select" id="country" name="country" required autocomplete="country">
                                            <option value="">Select Country</option>
                                            <option value="Pakistan" <?php echo $form_data['country'] === 'Pakistan' ? 'selected' : ''; ?>>Pakistan</option>
                                            <option value="Other" <?php echo $form_data['country'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                        <div class="invalid-feedback">Please select your country.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="agree_terms" name="agree_terms" required>
                                <label class="form-check-label" for="agree_terms">
                                    I agree to the Terms and Conditions
                                    and <a href="privacy.php" target="_blank" class="text-gold">Privacy Policy</a> *
                                </label>
                                <div class="invalid-feedback">You must agree to the terms and conditions.</div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-gold btn-lg">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                        </div>

                        <!-- Login Link -->
                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">
                                Already have an account?
                                <a href="login.php" class="text-gold fw-bold text-decoration-none">Sign In</a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Benefits Section -->
            <div class="row mt-4">
                <div class="col-md-4 text-center">
                    <div class="benefit-item">
                        <i class="fas fa-shipping-fast fa-2x text-gold mb-2"></i>
                        <h6>Free Shipping</h6>
                        <small class="text-muted">On orders over <?php echo CURRENCY; ?> 10,000</small>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="benefit-item">
                        <i class="fas fa-shield-alt fa-2x text-gold mb-2"></i>
                        <h6>Secure Shopping</h6>
                        <small class="text-muted">Your data is protected</small>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="benefit-item">
                        <i class="fas fa-gift fa-2x text-gold mb-2"></i>
                        <h6>Exclusive Offers</h6>
                        <small class="text-muted">Members-only discounts</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.registration-container {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
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

.benefit-item {
    padding: 20px;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.benefit-item:hover {
    background: rgba(212, 175, 55, 0.1);
    transform: translateY(-3px);
}

.input-group-text {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
}

.password-strength .progress {
    background-color: #e9ecef;
}

.password-strength .progress-bar {
    transition: width 0.3s ease;
}

/* Password strength colors */
.password-weak { background-color: #dc3545; }
.password-medium { background-color: #ffc107; }
.password-strong { background-color: #28a745; }

.invalid-feedback {
    display: none;
}

@media (max-width: 768px) {
    .display-5 {
        font-size: 2.5rem;
    }

    .card-body {
        padding: 2rem !important;
    }

    .benefit-item {
        margin-bottom: 1rem;
    }
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const registrationForm = document.getElementById('registrationForm');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordFeedback = document.getElementById('passwordFeedback');

    // Clear any auto-filled values on page load
    document.getElementById('email').value = '';
    document.getElementById('password').value = '';
    document.getElementById('confirm_password').value = '';

    // Password strength indicator
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let feedback = '';

        // Length check
        if (password.length >= 6) strength += 25;

        // Uppercase check
        if (/[A-Z]/.test(password)) strength += 25;

        // Lowercase check
        if (/[a-z]/.test(password)) strength += 25;

        // Number check
        if (/[0-9]/.test(password)) strength += 25;

        // Update progress bar
        passwordStrength.style.width = strength + '%';

        // Update feedback and color
        if (strength <= 25) {
            passwordStrength.className = 'progress-bar password-weak';
            feedback = 'Weak password';
        } else if (strength <= 50) {
            passwordStrength.className = 'progress-bar password-medium';
            feedback = 'Medium password';
        } else if (strength <= 75) {
            passwordStrength.className = 'progress-bar password-strong';
            feedback = 'Strong password';
        } else {
            passwordStrength.className = 'progress-bar password-strong';
            feedback = 'Very strong password';
        }

        passwordFeedback.textContent = feedback;
    });

    // Password confirmation validation
    confirmPasswordInput.addEventListener('input', function() {
        if (this.value !== passwordInput.value) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });

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

    // Real-time email validation
    const emailInput = document.getElementById('email');
    let emailTimeout;

    emailInput.addEventListener('input', function() {
        clearTimeout(emailTimeout);
        emailTimeout = setTimeout(() => {
            if (this.value.length > 3 && this.checkValidity()) {
                checkEmailAvailability(this.value);
            }
        }, 500);
    });

    function checkEmailAvailability(email) {
        fetch('../api/check-email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: email })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.available) {
                emailInput.classList.add('is-invalid');
                emailInput.setCustomValidity('Email already registered');
            } else {
                emailInput.classList.remove('is-invalid');
                emailInput.setCustomValidity('');
            }
        })
        .catch(error => {
            console.log('Email check failed:', error);
        });
    }

    // Form submission with loading state
    registrationForm.addEventListener('submit', function(e) {
        let valid = true;

        // Clear previous validation
        this.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });

        // Custom validation
        if (passwordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.classList.add('is-invalid');
            valid = false;
        }

        // Password strength validation
        const password = passwordInput.value;
        if (password.length < 6 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
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
        }
    });

    // Auto-format mobile number
    const mobileInput = document.getElementById('mobile');
    mobileInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9+]/g, '');
    });

    // Enhanced validation styling
    registrationForm.querySelectorAll('[required]').forEach(input => {
        input.addEventListener('blur', function() {
            this.classList.add('touched');
        });
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>