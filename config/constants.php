<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'haroon_jewellery');
define('DB_USER', 'root');
define('DB_PASS', '');

// JazzCash Configuration
define('JAZZCASH_API_VERSION', '2.0'); // Set to '1.1' or '2.0'
define('JAZZCASH_ENVIRONMENT', 'sandbox'); // 'sandbox' or 'production'

// JazzCash Mobile Configuration v1.1
define('JAZZCASH_MERCHANT_ID', 'MC408931');
define('JAZZCASH_PASSWORD', 't15va48u3u');
define('JAZZCASH_SALT', '40x15uy069');

// JazzCash Configuration v2.0
define('JAZZCASH_V2_MERCHANT_ID', 'MC408931');
define('JAZZCASH_V2_INTEGRITY_SALT', '40x15uy069');
define('JAZZCASH_V2_PASSWORD', 't15va48u3u');

define('JAZZCASH_RETURN_URL', 'http://localhost:8080/haroon-jewellery/api/payment-callback.php');
define('JAZZCASH_CALLBACK_URL', 'http://localhost:8080/haroon-jewellery/api/payment-callback.php');

// Site configuration
define('SITE_NAME', 'Haroon Jewellery');
define('SITE_URL', 'http://localhost:8080/haroon-jewellery');
define('CURRENCY', 'PKR');

// Path configuration
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('IMAGE_PATH', 'assets/uploads/');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Theme configuration
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}
?>