<?php
// Start session first
require_once '../config/constants.php';

// Check if user is logged in BEFORE including header
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

// Now include header and other files
require_once '../includes/header.php';

// Initialize database connection
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$pageTitle = "My Profile";
$user_id = $_SESSION['user_id'];

// Get user data with error handling
$user = null;
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
if ($user_stmt->execute([$user_id])) {
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
}

// If user not found, redirect to login
if (!$user) {
    echo '<script>window.location.href = "login";</script>';
    exit;
}

// Get user orders
$orders = [];
$orders_stmt = $conn->prepare("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 10
");
if ($orders_stmt->execute([$user_id])) {
    $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');

        // Validation
        $errors = [];

        if (empty($name)) {
            $errors[] = "Name is required";
        }

        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address";
        }

        if (empty($mobile)) {
            $errors[] = "Mobile number is required";
        }

        if (empty($errors)) {
            try {
                // Check if email already exists for another user
                $email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $email_check->execute([$email, $user_id]);

                if ($email_check->rowCount() > 0) {
                    $error_message = "Email address is already registered with another account.";
                } else {
                    // Update user profile
                    $update_stmt = $conn->prepare("
                        UPDATE users
                        SET name = ?, email = ?, mobile = ?, address = ?, city = ?, country = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    if ($update_stmt->execute([$name, $email, $mobile, $address, $city, $country, $user_id])) {
                        $success_message = "Profile updated successfully!";

                        // Refresh user data
                        $user_stmt->execute([$user_id]);
                        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error_message = "Error updating profile. Please try again.";
                    }
                }
            } catch (PDOException $e) {
                error_log("Profile update error: " . $e->getMessage());
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
        } elseif (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters long";
        }

        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }

        if (empty($errors)) {
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $password_stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                if ($password_stmt->execute([$hashed_password, $user_id])) {
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Error changing password. Please try again.";
                }
            } else {
                $error_message = "Current password is incorrect";
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }
}
?>

<div class="container-fluid py-5">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-lg-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="user-avatar mb-3">
                        <div class="avatar-placeholder">
                            <i class="fas fa-user-circle fa-4x text-gold"></i>
                        </div>
                    </div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></h5>
                    <p class="text-muted small mb-3">Member since <?php echo date('M Y', strtotime($user['created_at'] ?? 'now')); ?></p>

                    <div class="list-group list-group-flush">
                        <a href="#profile" class="list-group-item list-group-item-action active">
                            <i class="fas fa-user me-2"></i>Profile Information
                        </a>
                        <a href="#orders" class="list-group-item list-group-item-action">
                            <i class="fas fa-shopping-bag me-2"></i>Order History
                        </a>
                        <a href="#password" class="list-group-item list-group-item-action">
                            <i class="fas fa-lock me-2"></i>Change Password
                        </a>
                        <a href="<?php echo SITE_URL; ?>/logout" class="list-group-item list-group-item-action text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h6 class="card-title mb-3">Account Overview</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Orders:</span>
                        <strong><?php echo count($orders); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Pending Orders:</span>
                        <strong>
                            <?php
                            $pending_count = 0;
                            foreach ($orders as $order) {
                                if (isset($order['order_status']) && $order['order_status'] === 'pending') $pending_count++;
                            }
                            echo $pending_count;
                            ?>
                        </strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Member Since:</span>
                        <strong><?php echo date('M Y', strtotime($user['created_at'] ?? 'now')); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
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

            <!-- Profile Information -->
            <div class="card border-0 shadow-sm mb-4" id="profile">
                <div class="card-header bg-light border-0">
                    <h4 class="mb-0"><i class="fas fa-user me-2"></i>Profile Information</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="mobile" class="form-label">Mobile Number *</label>
                                <input type="tel" class="form-control" id="mobile" name="mobile"
                                       value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city"
                                       value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="country" name="country"
                                       value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-gold">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>

            <!-- Order History -->
            <div class="card border-0 shadow-sm mb-4" id="orders">
                <div class="card-header bg-light border-0">
                    <h4 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Order History</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($orders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo htmlspecialchars($order['order_number'] ?? 'N/A'); ?></strong>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($order['created_at'] ?? 'now')); ?></td>
                                            <td><?php echo htmlspecialchars($order['item_count'] ?? 0); ?> item(s)</td>
                                            <td class="fw-bold text-gold">
                                                <?php echo CURRENCY . ' ' . number_format($order['total_amount'] ?? 0); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_badge = [
                                                    'pending' => 'bg-warning',
                                                    'processing' => 'bg-info',
                                                    'shipped' => 'bg-primary',
                                                    'delivered' => 'bg-success',
                                                    'cancelled' => 'bg-danger'
                                                ];
                                                $order_status = $order['order_status'] ?? 'unknown';
                                                ?>
                                                <span class="badge <?php echo $status_badge[$order_status] ?? 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($order_status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo SITE_URL; ?>/order-details/<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-center mt-3">
                            <a href="<?php echo SITE_URL; ?>/orders" class="btn btn-outline-gold">View All Orders</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No orders yet</h5>
                            <p class="text-muted mb-3">You haven't placed any orders with us yet.</p>
                            <a href="<?php echo SITE_URL; ?>/products" class="btn btn-gold">Start Shopping</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card border-0 shadow-sm mb-4" id="password">
                <div class="card-header bg-light border-0">
                    <h4 class="mb-0"><i class="fas fa-lock me-2"></i>Change Password</h4>
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
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-gold">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account Preferences -->
            <!--<div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0">
                    <h4 class="mb-0"><i class="fas fa-cog me-2"></i>Account Preferences</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-1">Email Notifications</h6>
                                    <p class="text-muted small mb-0">Receive order updates and promotions</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="emailNotifications"
                                           <?php echo ($user['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="emailNotifications"></label>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-1">SMS Notifications</h6>
                                    <p class="text-muted small mb-0">Receive order updates via SMS</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="smsNotifications"
                                           <?php echo ($user['sms_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="smsNotifications"></label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-1">Newsletter</h6>
                                    <p class="text-muted small mb-0">Get latest offers and new arrivals</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="newsletter"
                                           <?php echo ($user['newsletter'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="newsletter"></label>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Theme Preference</h6>
                                    <p class="text-muted small mb-0">Light or dark mode</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="themeToggle"
                                           <?php echo ($user['theme_preference'] ?? 'light') === 'dark' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="themeToggle"></label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="button" class="btn btn-outline-gold me-2" id="savePreferences">
                            <i class="fas fa-save me-2"></i>Save Preferences
                        </button>
                        <a href="#" class="btn btn-outline-danger">
                            <i class="fas fa-user-slash me-2"></i>Delete Account
                        </a>
                    </div>
                </div>-->
            </div>
        </div>
    </div>
</div>

<style>
.user-avatar .avatar-placeholder {
    width: 100px;
    height: 100px;
    background: rgba(212, 175, 55, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.list-group-item.active {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.card {
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.card:hover {
    border-color: var(--primary-color);
}

.text-gold {
    color: var(--primary-color) !important;
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

.table-hover tbody tr:hover {
    background-color: rgba(212, 175, 55, 0.05);
}

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.badge {
    font-size: 0.75em;
}

@media (max-width: 768px) {
    .col-lg-3 {
        margin-bottom: 2rem;
    }

    .table-responsive {
        font-size: 0.875rem;
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.775rem;
    }
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}

/* Section active state */
.card {
    scroll-margin-top: 20px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for sidebar navigation
    document.querySelectorAll('.list-group-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (this.getAttribute('href').startsWith('#')) {
                e.preventDefault();

                // Remove active class from all items
                document.querySelectorAll('.list-group-item').forEach(i => {
                    i.classList.remove('active');
                });

                // Add active class to clicked item
                this.classList.add('active');

                // Scroll to section
                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // Password validation
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                document.getElementById('confirm_password').focus();
            }

            if (newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long!');
                document.getElementById('new_password').focus();
            }
        });
    }

    // Theme toggle functionality
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('change', function() {
            const newTheme = this.checked ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
        });
    }

    // Save preferences functionality
    const savePreferencesBtn = document.getElementById('savePreferences');
    if (savePreferencesBtn) {
        savePreferencesBtn.addEventListener('click', function() {
            const preferences = {
                emailNotifications: document.getElementById('emailNotifications').checked,
                smsNotifications: document.getElementById('smsNotifications').checked,
                newsletter: document.getElementById('newsletter').checked,
                themePreference: document.getElementById('themeToggle').checked ? 'dark' : 'light'
            };

            // Send AJAX request to save preferences
            fetch('../api/save-preferences.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(preferences)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Preferences saved successfully!');
                } else {
                    alert('Error saving preferences. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving preferences. Please try again.');
            });
        });
    }

    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let valid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!valid) {
                e.preventDefault();
                const firstInvalid = this.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    firstInvalid.focus();
                }
            }
        });
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>