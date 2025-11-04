<?php
require_once 'includes/admin-header.php';

// Initialize database connection
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$admin_id = $_SESSION['admin_id'];

// Get admin data
$admin_stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role IN ('admin', 'manager')");
$admin_stmt->execute([$admin_id]);
$admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header('Location: index.php');
    exit;
}

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');

        // Validation
        $errors = [];

        if (empty($name)) {
            $errors[] = "Name is required";
        }

        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address";
        } else {
            // Check if email exists for other users
            $email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $email_check->execute([$email, $admin_id]);
            if ($email_check->rowCount() > 0) {
                $errors[] = "Email address is already registered";
            }
        }

        if (empty($mobile)) {
            $errors[] = "Mobile number is required";
        }

        if (empty($errors)) {
            try {
                $update_stmt = $conn->prepare("
                    UPDATE users
                    SET name = ?, email = ?, mobile = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([$name, $email, $mobile, $admin_id]);

                // Update session
                $_SESSION['admin_name'] = $name;
                $_SESSION['admin_email'] = $email;

                $success_message = "Profile updated successfully!";

                // Refresh admin data
                $admin_stmt->execute([$admin_id]);
                $admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);

            } catch (PDOException $e) {
                $error_message = "Error updating profile. Please try again.";
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }

    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $errors = [];

        if (empty($current_password)) {
            $errors[] = "Current password is required";
        }

        if (empty($new_password)) {
            $errors[] = "New password is required";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters long";
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $errors[] = "Password must contain at least one number";
        } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
            $errors[] = "Password must contain at least one special character";
        }

        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }

        if (empty($errors)) {
            // Verify current password
            if (password_verify($current_password, $admin['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $password_stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $password_stmt->execute([$hashed_password, $admin_id]);

                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Current password is incorrect";
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }
}

// Get admin activity stats
$activity_stmt = $conn->prepare("
    SELECT
        COUNT(*) as total_logins,
        MAX(last_login) as last_login,
        (SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_orders,
        (SELECT COUNT(*) FROM products WHERE status = 'active') as total_products
    FROM users
    WHERE id = ?
");
$activity_stmt->execute([$admin_id]);
$activity_stats = $activity_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 brand-font mb-1">Admin Profile</h1>
            <p class="text-muted mb-0">Manage your account settings and preferences</p>
        </div>
        <div class="text-end">
            <span class="badge bg-<?php echo $admin['role'] === 'admin' ? 'warning' : 'info'; ?> me-2">
                <?php echo ucfirst($admin['role']); ?>
                <?php if ($admin['is_super_admin']): ?>
                    <i class="fas fa-crown ms-1"></i>
                <?php endif; ?>
            </span>
            <small class="text-muted">Member since <?php echo date('M Y', strtotime($admin['created_at'])); ?></small>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column - Profile Info & Stats -->
        <div class="col-lg-4 mb-4">
            <!-- Admin Card -->
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="admin-avatar mb-3">
                        <div class="avatar-placeholder">
                            <i class="fas fa-user-shield fa-3x text-gold"></i>
                        </div>
                    </div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($admin['name']); ?></h4>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($admin['email']); ?></p>
                    <p class="text-muted mb-3">
                        <i class="fas fa-mobile-alt me-1"></i>
                        <?php echo htmlspecialchars($admin['mobile']); ?>
                    </p>

                    <div class="admin-stats">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="stat-item">
                                    <div class="h5 text-gold mb-1"><?php echo $activity_stats['total_logins']; ?></div>
                                    <small class="text-muted">Total Logins</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="stat-item">
                                    <div class="h5 text-gold mb-1"><?php echo $activity_stats['recent_orders']; ?></div>
                                    <small class="text-muted">Recent Orders</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="last-login">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            Last login:
                            <?php echo $activity_stats['last_login'] ? date('M j, Y g:i A', strtotime($activity_stats['last_login'])) : 'Never'; ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <!--<div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-light border-0">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="settings.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-cog me-2"></i>System Settings
                        </a>
                        <?php if ($_SESSION['is_super_admin']): ?>
                            <a href="admins.php" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-user-shield me-2"></i>Manage Admins
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>-->
        </div>

        <!-- Right Column - Forms -->
        <div class="col-lg-8">
            <!-- Profile Information Form -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light border-0">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Profile Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="mobile" class="form-label">Mobile Number *</label>
                                <input type="tel" class="form-control" id="mobile" name="mobile"
                                       value="<?php echo htmlspecialchars($admin['mobile']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Role</label>
                                <input type="text" class="form-control" value="<?php echo ucfirst($admin['role']); ?>" readonly>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Member Since</label>
                                <input type="text" class="form-control"
                                       value="<?php echo date('F j, Y', strtotime($admin['created_at'])); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Updated</label>
                                <input type="text" class="form-control"
                                       value="<?php echo $admin['updated_at'] ? date('F j, Y g:i A', strtotime($admin['updated_at'])) : 'Never'; ?>" readonly>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-gold">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>

            <!-- Change Password Form -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light border-0">
                    <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="passwordForm">
                        <input type="hidden" name="change_password" value="1">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="current_password" class="form-label">Current Password *</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">New Password *</label>
                                <input type="password" class="form-control" id="new_password" name="new_password"
                                       required minlength="8">
                                <div class="form-text">
                                    Must be at least 8 characters with uppercase, lowercase, number, and special character.
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>

                        <div class="password-strength mt-3 mb-3">
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" id="passwordStrength" style="width: 0%"></div>
                            </div>
                            <small class="text-muted" id="passwordFeedback">Password strength</small>
                        </div>

                        <button type="submit" class="btn btn-gold">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Security Settings -->
            <!--<div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0">
                    <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-1">Two-Factor Authentication</h6>
                                    <p class="text-muted small mb-0">Add an extra layer of security</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="twoFactorAuth">
                                    <label class="form-check-label" for="twoFactorAuth"></label>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-1">Login Notifications</h6>
                                    <p class="text-muted small mb-0">Get alerts for new logins</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="loginNotifications" checked>
                                    <label class="form-check-label" for="loginNotifications"></label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-1">Session Timeout</h6>
                                    <p class="text-muted small mb-0">Auto-logout after inactivity</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sessionTimeout" checked>
                                    <label class="form-check-label" for="sessionTimeout"></label>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Activity Logging</h6>
                                    <p class="text-muted small mb-0">Record admin activities</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="activityLogging" checked>
                                    <label class="form-check-label" for="activityLogging"></label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button class="btn btn-outline-gold me-2">
                            <i class="fas fa-save me-2"></i>Save Preferences
                        </button>
                        <a href="login-history.php" class="btn btn-outline-secondary">
                            <i class="fas fa-history me-2"></i>View Login History
                        </a>
                    </div>
                </div>
            </div>-->
        </div>
    </div>
</div>

<style>
.admin-avatar .avatar-placeholder {
    width: 100px;
    height: 100px;
    background: rgba(212, 175, 55, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.stat-item {
    padding: 10px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.stat-item:hover {
    background: rgba(212, 175, 55, 0.1);
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
}

.btn-gold:hover {
    background-color: var(--accent-color);
    border-color: var(--accent-color);
}

.btn-outline-gold {
    color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-outline-gold:hover {
    background-color: var(--primary-color);
    color: white;
}

.card {
    border: 1px solid var(--admin-border);
    transition: all 0.3s ease;
}

.card:hover {
    border-color: var(--primary-color);
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
.password-very-strong { background-color: #20c997; }

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

@media (max-width: 768px) {
    .col-lg-4 {
        margin-bottom: 2rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordForm = document.getElementById('passwordForm');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordFeedback = document.getElementById('passwordFeedback');

    // Password strength indicator
    newPasswordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let feedback = '';

        // Length check
        if (password.length >= 8) strength += 25;

        // Uppercase check
        if (/[A-Z]/.test(password)) strength += 25;

        // Lowercase check
        if (/[a-z]/.test(password)) strength += 25;

        // Number check
        if (/[0-9]/.test(password)) strength += 15;

        // Special character check
        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength += 10;

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
            passwordStrength.className = 'progress-bar password-very-strong';
            feedback = 'Very strong password';
        }

        passwordFeedback.textContent = feedback;
    });

    // Password confirmation validation
    confirmPasswordInput.addEventListener('input', function() {
        if (this.value !== newPasswordInput.value) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });

    // Form validation
    passwordForm.addEventListener('submit', function(e) {
        let valid = true;

        // Clear previous validation
        this.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });

        // Password strength validation
        const password = newPasswordInput.value;
        if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password) || !/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            newPasswordInput.classList.add('is-invalid');
            valid = false;
        }

        // Confirm password
        if (password !== confirmPasswordInput.value) {
            confirmPasswordInput.classList.add('is-invalid');
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
        }
    });

    // Security settings toggle handlers
    document.querySelectorAll('.form-check-input').forEach(switchEl => {
        switchEl.addEventListener('change', function() {
            console.log('Setting changed:', this.id, this.checked);
        });
    });
});
</script>

<?php
require_once 'includes/admin-footer.php';
?>