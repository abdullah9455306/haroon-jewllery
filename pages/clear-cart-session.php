<?php
require_once '../config/constants.php';

session_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear cart session data
if (isset($_SESSION['user_id'])) {
    // For logged-in users, cart is managed in database
    // No need to clear session cart
} else {
    // For guest users, clear session cart
    unset($_SESSION['cart']);
    unset($_SESSION['cart_count']);
}

// Return success response
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>