<?php
require_once 'includes/admin-header.php';

// Initialize database connection
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Check admin permissions
if ($_SESSION['admin_role'] !== 'admin' && !$_SESSION['is_super_admin']) {
    header('Location: dashboard.php');
    exit;
}

// Handle settings update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // General Settings
        if (isset($_POST['store_name'])) {
            updateSetting($conn, 'store_name', $_POST['store_name']);
            updateSetting($conn, 'store_email', $_POST['store_email']);
            updateSetting($conn, 'store_phone', $_POST['store_phone']);
            updateSetting($conn, 'store_address', $_POST['store_address']);
            updateSetting($conn, 'currency', $_POST['currency']);
            updateSetting($conn, 'shipping_cost', $_POST['shipping_cost']);
//             updateSetting($conn, 'default_theme', $_POST['default_theme']);
        }

        // JazzCash Mobile Settings
        if (isset($_POST['jazzcash_merchant_id'])) {
            updateSetting($conn, 'jazzcash_merchant_id', $_POST['jazzcash_merchant_id']);
            updateSetting($conn, 'jazzcash_password', $_POST['jazzcash_password']);
            updateSetting($conn, 'jazzcash_salt', $_POST['jazzcash_salt']);
            updateSetting($conn, 'jazzcash_return_url', $_POST['jazzcash_return_url']);
        }

        // JazzCash Card Settings
        if (isset($_POST['jazzcash_card_merchant_id'])) {
            updateSetting($conn, 'jazzcash_card_merchant_id', $_POST['jazzcash_card_merchant_id']);
            updateSetting($conn, 'jazzcash_card_password', $_POST['jazzcash_card_password']);
            updateSetting($conn, 'jazzcash_card_salt', $_POST['jazzcash_card_salt']);
        }

        // Email Settings
        if (isset($_POST['smtp_host'])) {
            updateSetting($conn, 'smtp_host', $_POST['smtp_host']);
            updateSetting($conn, 'smtp_port', $_POST['smtp_port']);
            updateSetting($conn, 'smtp_username', $_POST['smtp_username']);
            updateSetting($conn, 'smtp_password', $_POST['smtp_password']);
            updateSetting($conn, 'smtp_encryption', $_POST['smtp_encryption']);
            updateSetting($conn, 'email_from_name', $_POST['email_from_name']);
            updateSetting($conn, 'email_from_address', $_POST['email_from_address']);
        }

        // Social Media Settings
        if (isset($_POST['facebook_url'])) {
            updateSetting($conn, 'facebook_url', $_POST['facebook_url']);
            updateSetting($conn, 'instagram_url', $_POST['instagram_url']);
            updateSetting($conn, 'twitter_url', $_POST['twitter_url']);
            updateSetting($conn, 'youtube_url', $_POST['youtube_url']);
            updateSetting($conn, 'whatsapp_number', $_POST['whatsapp_number']);
        }

        // Maintenance Settings
        if (isset($_POST['maintenance_mode'])) {
            updateSetting($conn, 'maintenance_mode', $_POST['maintenance_mode']);
            updateSetting($conn, 'maintenance_message', $_POST['maintenance_message']);
        }

        // SEO Settings
        if (isset($_POST['meta_title'])) {
            updateSetting($conn, 'meta_title', $_POST['meta_title']);
            updateSetting($conn, 'meta_description', $_POST['meta_description']);
            updateSetting($conn, 'meta_keywords', $_POST['meta_keywords']);
            updateSetting($conn, 'google_analytics_id', $_POST['google_analytics_id']);
        }

        $conn->commit();
        $success_message = "Settings updated successfully!";

        // Clear settings cache
        clearSettingsCache();

    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

// Handle cache clearance
if (isset($_GET['action']) && $_GET['action'] === 'clear_cache') {
    if (clearSettingsCache()) {
        $success_message = "Cache cleared successfully!";
    } else {
        $error_message = "Error clearing cache.";
    }
}

// Handle backup settings
if (isset($_GET['action']) && $_GET['action'] === 'backup_settings') {
    if (backupSettings($conn)) {
        $success_message = "Settings backup created successfully!";
    } else {
        $error_message = "Error creating settings backup.";
    }
}

// Function to update setting
function updateSetting($conn, $key, $value) {
    $stmt = $conn->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
    $stmt->execute([$value, $key]);
}

// Function to clear settings cache
function clearSettingsCache() {
    $cache_file = '../cache/settings.cache';
    if (file_exists($cache_file)) {
        return unlink($cache_file);
    }
    return true;
}

// Function to backup settings
function backupSettings($conn) {
    $stmt = $conn->query("SELECT setting_key, setting_value, setting_type FROM settings");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $backup_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'settings' => $settings
    ];

    $backup_dir = '../backups/settings/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    $filename = $backup_dir . 'settings_backup_' . date('Y-m-d_H-i-s') . '.json';
    return file_put_contents($filename, json_encode($backup_data, JSON_PRETTY_PRINT));
}

// Get all settings
$settings_stmt = $conn->query("SELECT setting_key, setting_value, setting_type FROM settings");
$settings_data = $settings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to associative array for easy access
$settings = [];
foreach ($settings_data as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Get available currencies
$currencies = [
    'PKR' => 'Pakistani Rupee (PKR)',
//     'USD' => 'US Dollar (USD)',
//     'EUR' => 'Euro (EUR)',
//     'GBP' => 'British Pound (GBP)',
//     'AED' => 'UAE Dirham (AED)',
//     'SAR' => 'Saudi Riyal (SAR)'
];

// Get available themes
$themes = [
    'light' => 'Light Theme',
    'dark' => 'Dark Theme',
    'auto' => 'Auto (System Preference)'
];

// Get SMTP encryption options
$smtp_encryption = [
    '' => 'None',
    'ssl' => 'SSL',
    'tls' => 'TLS'
];
?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 brand-font mb-1">System Settings</h1>
            <p class="text-muted mb-0">Configure your store settings and preferences</p>
        </div>
        <div>
            <div class="btn-group">
                <!--<button type="button" class="btn btn-outline-secondary me-2" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print
                </button>-->
                <!--<button type="button" class="btn btn-outline-dark me-2" data-bs-toggle="modal" data-bs-target="#backupSettingsModal">
                    <i class="fas fa-download me-2"></i>Backup
                </button>-->
                <!--<a href="?action=clear_cache" class="btn btn-outline-warning">
                    <i class="fas fa-broom me-2"></i>Clear Cache
                </a>-->
            </div>
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

    <!-- Dynamic Alert Container -->
    <div id="dynamicAlertContainer"></div>

    <!-- Settings Navigation -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <ul class="nav nav-pills" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="pill" data-bs-target="#general" type="button" role="tab">
                        <i class="fas fa-store me-2"></i>General
                    </button>
                </li>
                <!--<li class="nav-item" role="presentation">
                    <button class="nav-link" id="payment-tab" data-bs-toggle="pill" data-bs-target="#payment" type="button" role="tab">
                        <i class="fas fa-credit-card me-2"></i>Payment
                    </button>
                </li>-->
                <!--<li class="nav-item" role="presentation">
                    <button class="nav-link" id="email-tab" data-bs-toggle="pill" data-bs-target="#email" type="button" role="tab">
                        <i class="fas fa-envelope me-2"></i>Email
                    </button>
                </li>-->
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="social-tab" data-bs-toggle="pill" data-bs-target="#social" type="button" role="tab">
                        <i class="fas fa-share-alt me-2"></i>Social Media
                    </button>
                </li>
                <!--<li class="nav-item" role="presentation">
                    <button class="nav-link" id="seo-tab" data-bs-toggle="pill" data-bs-target="#seo" type="button" role="tab">
                        <i class="fas fa-search me-2"></i>SEO
                    </button>
                </li>-->
                <!--<li class="nav-item" role="presentation">
                    <button class="nav-link" id="maintenance-tab" data-bs-toggle="pill" data-bs-target="#maintenance" type="button" role="tab">
                        <i class="fas fa-tools me-2"></i>Maintenance
                    </button>
                </li>-->
            </ul>
        </div>
    </div>

    <!-- Settings Forms -->
    <form method="POST" action="" id="settingsForm">
        <div class="tab-content" id="settingsTabsContent">

            <!-- General Settings -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0"><i class="fas fa-store me-2"></i>General Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="store_name" class="form-label">Store Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="store_name" name="store_name"
                                       value="<?php echo htmlspecialchars($settings['store_name'] ?? ''); ?>" required disabled>
                            </div>
                            <div class="col-md-6">
                                <label for="store_email" class="form-label">Store Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="store_email" name="store_email"
                                       value="<?php echo htmlspecialchars($settings['store_email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="store_phone" class="form-label">Store Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="store_phone" name="store_phone"
                                       value="<?php echo htmlspecialchars($settings['store_phone'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="currency" class="form-label">Currency <span class="text-danger">*</span></label>
                                <select class="form-select" id="currency" name="currency" required>
                                    <?php foreach ($currencies as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo ($settings['currency'] ?? 'PKR') === $code ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="store_address" class="form-label">Store Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="store_address" name="store_address" rows="3" required><?php echo htmlspecialchars($settings['store_address'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="shipping_cost" class="form-label">Shipping Cost (<?php echo $settings['currency'] ?? 'PKR'; ?>)</label>
                                <input type="number" class="form-control" id="shipping_cost" name="shipping_cost"
                                       value="<?php echo htmlspecialchars($settings['shipping_cost'] ?? '200'); ?>" step="0.01" min="0">
                            </div>
                            <!--<div class="col-md-6">
                                <label for="default_theme" class="form-label">Default Theme</label>
                                <select class="form-select" id="default_theme" name="default_theme">
                                    <?php foreach ($themes as $key => $name): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($settings['default_theme'] ?? 'light') === $key ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>-->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Settings -->
            <div class="tab-pane fade" id="payment" role="tabpanel">
                <div class="row">
                    <!-- JazzCash Mobile -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-light border-0">
                                <h5 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>JazzCash Mobile Account</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="jazzcash_merchant_id" class="form-label">Merchant ID</label>
                                    <input type="text" class="form-control" id="jazzcash_merchant_id" name="jazzcash_merchant_id"
                                           value="<?php echo htmlspecialchars($settings['jazzcash_merchant_id'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="jazzcash_password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="jazzcash_password" name="jazzcash_password"
                                           value="<?php echo htmlspecialchars($settings['jazzcash_password'] ?? ''); ?>">
                                    <div class="form-text">
                                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="togglePassword('jazzcash_password')">
                                            <i class="fas fa-eye me-1"></i>Show Password
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="jazzcash_salt" class="form-label">Salt Key</label>
                                    <input type="password" class="form-control" id="jazzcash_salt" name="jazzcash_salt"
                                           value="<?php echo htmlspecialchars($settings['jazzcash_salt'] ?? ''); ?>">
                                    <div class="form-text">
                                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="togglePassword('jazzcash_salt')">
                                            <i class="fas fa-eye me-1"></i>Show Salt
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="jazzcash_return_url" class="form-label">Return URL</label>
                                    <input type="url" class="form-control" id="jazzcash_return_url" name="jazzcash_return_url"
                                           value="<?php echo htmlspecialchars($settings['jazzcash_return_url'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- JazzCash Card -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-light border-0">
                                <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>JazzCash Card Payments</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="jazzcash_card_merchant_id" class="form-label">Card Merchant ID</label>
                                    <input type="text" class="form-control" id="jazzcash_card_merchant_id" name="jazzcash_card_merchant_id"
                                           value="<?php echo htmlspecialchars($settings['jazzcash_card_merchant_id'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="jazzcash_card_password" class="form-label">Card Password</label>
                                    <input type="password" class="form-control" id="jazzcash_card_password" name="jazzcash_card_password"
                                           value="<?php echo htmlspecialchars($settings['jazzcash_card_password'] ?? ''); ?>">
                                    <div class="form-text">
                                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="togglePassword('jazzcash_card_password')">
                                            <i class="fas fa-eye me-1"></i>Show Password
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="jazzcash_card_salt" class="form-label">Card Salt Key</label>
                                    <input type="password" class="form-control" id="jazzcash_card_salt" name="jazzcash_card_salt"
                                           value="<?php echo htmlspecialchars($settings['jazzcash_card_salt'] ?? ''); ?>">
                                    <div class="form-text">
                                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="togglePassword('jazzcash_card_salt')">
                                            <i class="fas fa-eye me-1"></i>Show Salt
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods Info -->
                <!--<div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Payment Methods Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-mobile-alt me-2"></i>JazzCash Mobile Account</h6>
                            <p class="mb-2">Allow customers to pay using their JazzCash mobile accounts. Customers will be redirected to JazzCash payment gateway.</p>

                            <h6 class="mt-3"><i class="fas fa-credit-card me-2"></i>JazzCash Card Payments</h6>
                            <p class="mb-2">Accept credit/debit card payments through JazzCash. Supports all major Pakistani banks.</p>

                            <h6 class="mt-3"><i class="fas fa-money-bill-wave me-2"></i>Cash on Delivery</h6>
                            <p class="mb-0">Always available. Customers can pay when they receive their order.</p>
                        </div>
                    </div>
                </div>-->
            </div>

            <!-- Email Settings -->
            <div class="tab-pane fade" id="email" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Email Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="smtp_host" class="form-label">SMTP Host</label>
                                <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                       value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-md-6">
                                <label for="smtp_port" class="form-label">SMTP Port</label>
                                <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                       value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>" placeholder="587">
                            </div>
                            <div class="col-md-6">
                                <label for="smtp_username" class="form-label">SMTP Username</label>
                                <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                                       value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" placeholder="your-email@gmail.com">
                            </div>
                            <div class="col-md-6">
                                <label for="smtp_password" class="form-label">SMTP Password</label>
                                <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                       value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>">
                                <div class="form-text">
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="togglePassword('smtp_password')">
                                        <i class="fas fa-eye me-1"></i>Show Password
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="smtp_encryption" class="form-label">Encryption</label>
                                <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                    <?php foreach ($smtp_encryption as $key => $name): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($settings['smtp_encryption'] ?? 'tls') === $key ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="email_from_name" class="form-label">From Name</label>
                                <input type="text" class="form-control" id="email_from_name" name="email_from_name"
                                       value="<?php echo htmlspecialchars($settings['email_from_name'] ?? $settings['store_name'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label for="email_from_address" class="form-label">From Email Address</label>
                                <input type="email" class="form-control" id="email_from_address" name="email_from_address"
                                       value="<?php echo htmlspecialchars($settings['email_from_address'] ?? $settings['store_email'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Test Email Button -->
                        <!--<div class="mt-4 p-3 bg-light rounded">
                            <h6><i class="fas fa-paper-plane me-2"></i>Test Email Configuration</h6>
                            <p class="mb-3">Send a test email to verify your SMTP settings are working correctly.</p>
                            <button type="button" class="btn btn-outline-primary" onclick="testEmailConfiguration()">
                                <i class="fas fa-paper-plane me-2"></i>Send Test Email
                            </button>
                        </div>-->
                    </div>
                </div>
            </div>

            <!-- Social Media Settings -->
            <div class="tab-pane fade" id="social" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0"><i class="fas fa-share-alt me-2"></i>Social Media Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="facebook_url" class="form-label">Facebook URL</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-facebook text-primary"></i></span>
                                    <input type="url" class="form-control" id="facebook_url" name="facebook_url"
                                           value="<?php echo htmlspecialchars($settings['facebook_url'] ?? ''); ?>" placeholder="https://facebook.com/yourpage">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="instagram_url" class="form-label">Instagram URL</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-instagram text-danger"></i></span>
                                    <input type="url" class="form-control" id="instagram_url" name="instagram_url"
                                           value="<?php echo htmlspecialchars($settings['instagram_url'] ?? ''); ?>" placeholder="https://instagram.com/yourprofile">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="twitter_url" class="form-label">Twitter URL</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-twitter text-info"></i></span>
                                    <input type="url" class="form-control" id="twitter_url" name="twitter_url"
                                           value="<?php echo htmlspecialchars($settings['twitter_url'] ?? ''); ?>" placeholder="https://twitter.com/yourprofile">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="youtube_url" class="form-label">YouTube URL</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-youtube text-danger"></i></span>
                                    <input type="url" class="form-control" id="youtube_url" name="youtube_url"
                                           value="<?php echo htmlspecialchars($settings['youtube_url'] ?? ''); ?>" placeholder="https://youtube.com/yourchannel">
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-whatsapp text-success"></i></span>
                                    <input type="text" class="form-control" id="whatsapp_number" name="whatsapp_number"
                                           value="<?php echo htmlspecialchars($settings['whatsapp_number'] ?? ''); ?>" placeholder="923001234567">
                                    <span class="input-group-text">+</span>
                                </div>
                                <small class="form-text text-muted">Format: 923001234567 (without + sign)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEO Settings -->
            <div class="tab-pane fade" id="seo" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0"><i class="fas fa-search me-2"></i>SEO Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="meta_title" class="form-label">Meta Title</label>
                                <input type="text" class="form-control" id="meta_title" name="meta_title"
                                       value="<?php echo htmlspecialchars($settings['meta_title'] ?? $settings['store_name'] ?? ''); ?>">
                                <small class="form-text text-muted">Recommended: 50-60 characters. Current: <span id="meta_title_count">0</span></small>
                            </div>
                            <div class="col-12">
                                <label for="meta_description" class="form-label">Meta Description</label>
                                <textarea class="form-control" id="meta_description" name="meta_description" rows="3"><?php echo htmlspecialchars($settings['meta_description'] ?? ''); ?></textarea>
                                <small class="form-text text-muted">Recommended: 150-160 characters. Current: <span id="meta_description_count">0</span></small>
                            </div>
                            <div class="col-12">
                                <label for="meta_keywords" class="form-label">Meta Keywords</label>
                                <input type="text" class="form-control" id="meta_keywords" name="meta_keywords"
                                       value="<?php echo htmlspecialchars($settings['meta_keywords'] ?? ''); ?>">
                                <small class="form-text text-muted">Comma-separated keywords relevant to your store</small>
                            </div>
                            <div class="col-12">
                                <label for="google_analytics_id" class="form-label">Google Analytics ID</label>
                                <input type="text" class="form-control" id="google_analytics_id" name="google_analytics_id"
                                       value="<?php echo htmlspecialchars($settings['google_analytics_id'] ?? ''); ?>" placeholder="G-XXXXXXXXXX">
                                <small class="form-text text-muted">Your Google Analytics 4 Measurement ID (starts with G-)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Maintenance Settings -->
            <div class="tab-pane fade" id="maintenance" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Maintenance Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> Enabling maintenance mode will restrict access to the store for all visitors except administrators.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="maintenance_mode" class="form-label">Maintenance Mode</label>
                                <select class="form-select" id="maintenance_mode" name="maintenance_mode">
                                    <option value="0" <?php echo ($settings['maintenance_mode'] ?? '0') === '0' ? 'selected' : ''; ?>>Disabled</option>
                                    <option value="1" <?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'selected' : ''; ?>>Enabled</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="maintenance_message" class="form-label">Maintenance Message</label>
                                <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="4"><?php echo htmlspecialchars($settings['maintenance_message'] ?? 'Our store is currently undergoing maintenance. We apologize for any inconvenience and will be back online shortly.'); ?></textarea>
                                <small class="form-text text-muted">This message will be displayed to visitors when maintenance mode is enabled.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <button type="submit" class="btn btn-gold btn-lg" style="float:right">
                    <i class="fas fa-save me-2"></i>Save All Settings
                </button>
                <!--<button type="reset" class="btn btn-outline-secondary btn-lg ms-2">
                    <i class="fas fa-undo me-2"></i>Reset Changes
                </button>-->
            </div>
        </div>
    </form>
</div>

<!-- Backup Settings Confirmation Modal -->
<div class="modal fade" id="backupSettingsModal" tabindex="-1" aria-labelledby="backupSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="backupSettingsModalLabel">Create Settings Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to create a backup of your current settings?</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> This will create a JSON file containing all your current settings in the backups folder.
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Store Settings
                        <i class="fas fa-check text-success"></i>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Payment Settings
                        <i class="fas fa-check text-success"></i>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Email Settings
                        <i class="fas fa-check text-success"></i>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Social Media Settings
                        <i class="fas fa-check text-success"></i>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        SEO Settings
                        <i class="fas fa-check text-success"></i>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Maintenance Settings
                        <i class="fas fa-check text-success"></i>
                    </li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="?action=backup_settings" class="btn btn-info">
                    <i class="fas fa-download me-2"></i>Create Backup
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border: 1px solid var(--admin-border);
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

.nav-pills .nav-link {
    color: var(--admin-text);
    border: 1px solid var(--admin-border);
    margin-right: 5px;
    margin-bottom: 5px;
}

.nav-pills .nav-link.active {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.input-group-text {
    background-color: var(--admin-bg);
    border-color: var(--admin-border);
}

/* Character count colors */
.char-count-good {
    color: #28a745;
}

.char-count-warning {
    color: #ffc107;
}

.char-count-danger {
    color: #dc3545;
}
</style>

<script>
// Function to toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = event.target;

    if (input.type === 'password') {
        input.type = 'text';
        button.innerHTML = '<i class="fas fa-eye-slash me-1"></i>Hide Password';
    } else {
        input.type = 'password';
        button.innerHTML = '<i class="fas fa-eye me-1"></i>Show Password';
    }
}

// Function to test email configuration
function testEmailConfiguration() {
    const smtpHost = document.getElementById('smtp_host').value;
    const smtpPort = document.getElementById('smtp_port').value;
    const smtpUsername = document.getElementById('smtp_username').value;

    if (!smtpHost || !smtpPort || !smtpUsername) {
        showAlert('Please fill in all required SMTP settings before testing.', 'warning');
        return;
    }

    showAlert('Sending test email... Please wait.', 'info');

    // Simulate email test (you would replace this with actual AJAX call)
    setTimeout(() => {
        showAlert('Test email sent successfully! Please check your inbox.', 'success');
    }, 2000);
}

// Function to show Bootstrap alert
function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('dynamicAlertContainer');

    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show mb-4`;
    alert.innerHTML = `
        <i class="fas ${getAlertIcon(type)} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    alertContainer.appendChild(alert);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

// Helper function to get appropriate icon for alert type
function getAlertIcon(type) {
    switch (type) {
        case 'success': return 'fa-check-circle';
        case 'warning': return 'fa-exclamation-triangle';
        case 'danger': return 'fa-exclamation-circle';
        case 'info':
        default: return 'fa-info-circle';
    }
}

// Character counters for SEO fields
function updateCharacterCount(fieldId, counterId) {
    const field = document.getElementById(fieldId);
    const counter = document.getElementById(counterId);

    if (field && counter) {
        field.addEventListener('input', function() {
            counter.textContent = this.value.length;

            // Add warning color if over recommended length
            const maxLength = fieldId === 'meta_title' ? 60 : 160;
            if (this.value.length > maxLength) {
                counter.classList.add('text-danger');
            } else {
                counter.classList.remove('text-danger');
            }
        });

        // Initialize count
        counter.textContent = field.value.length;
    }
}

// Initialize character counters
document.addEventListener('DOMContentLoaded', function() {
    updateCharacterCount('meta_title', 'meta_title_count');
    updateCharacterCount('meta_description', 'meta_description_count');

    // Form validation
    const form = document.getElementById('settingsForm');
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
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
            showAlert('Please fill in all required fields marked with *.', 'warning');
        }
    });

    // Tab persistence
    const settingsTabs = document.getElementById('settingsTabs');
    if (settingsTabs) {
        settingsTabs.addEventListener('shown.bs.tab', function(event) {
            const activeTab = event.target.getAttribute('id');
            localStorage.setItem('activeSettingsTab', activeTab);
        });

        // Restore active tab
        const activeTab = localStorage.getItem('activeSettingsTab');
        if (activeTab) {
            const tab = document.querySelector(`#${activeTab}`);
            if (tab) {
                new bootstrap.Tab(tab).show();
            }
        }
    }
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>