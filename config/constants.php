<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'haroon_jewellery');
define('DB_USER', 'root');
define('DB_PASS', '');

// JazzCash Mobile Configuration
define('JAZZCASH_MERCHANT_ID', 'MC408931');
define('JAZZCASH_PASSWORD', 't15va48u3u');
define('JAZZCASH_SALT', '40x15uy069');

// JazzCash Card Configuration
define('JAZZCASH_CARD_MERCHANT_ID', 'MC408931');
define('JAZZCASH_CARD_PASSWORD', 't15va48u3u');
define('JAZZCASH_CARD_SALT', '40x15uy069');

define('JAZZCASH_RETURN_URL', 'http://localhost/ecommerce/payment-callback.php');
define('JAZZCASH_API_VERSION', '1.1');

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