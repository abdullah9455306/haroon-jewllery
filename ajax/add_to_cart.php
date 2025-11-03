<?php
session_start();
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../helpers/cart_helper.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'redirect' => false];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Please login to add items to cart';
    $response['redirect'] = 'login.php';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;

    if ($product_id <= 0) {
        $response['message'] = 'Invalid product';
        echo json_encode($response);
        exit;
    }

    try {
        // Get product details
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id, name, price, sale_price, stock_quantity FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $response['message'] = 'Product not found';
            echo json_encode($response);
            exit;
        }

        if ($product['stock_quantity'] <= 0) {
            $response['message'] = 'Product is out of stock';
            echo json_encode($response);
            exit;
        }

        // Use sale price if available
        $price = $product['sale_price'] ?? $product['price'];

        // Add to cart
        $cartHelper = new CartHelper();
        $result = $cartHelper->addToCart($_SESSION['user_id'], $product_id, $quantity, $price);

        if ($result) {
            $response['success'] = true;
            $response['message'] = 'Product added to cart successfully';
            $response['cart_count'] = $cartHelper->getCartCount($_SESSION['user_id']);
        } else {
            $response['message'] = 'Failed to add product to cart';
        }

    } catch (Exception $e) {
        $response['message'] = 'An error occurred';
    }
}

echo json_encode($response);
?>