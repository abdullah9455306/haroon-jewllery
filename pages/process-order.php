<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../helpers/cart_helper.php';
require_once '../api/jazzcash-payment.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please login to complete your order.';
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $cartHelper = new CartHelper();
    $jazzcash = new JazzCashPayment();

    // Start transaction
    $conn->beginTransaction();

    // Get cart items
    $cartItems = $cartHelper->getCartItems($_SESSION['user_id']);
    if (empty($cartItems)) {
        throw new Exception('Your cart is empty.');
    }

    // Calculate totals
    $subtotal = 0;
    foreach ($cartItems as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    $shipping = 200;
    $tax = $subtotal * 0.05;
    $total = $subtotal + $shipping + $tax;

    // Validate form data
    $fullName = trim($_POST['fullName']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);
    $city = trim($_POST['city']);
    $address = trim($_POST['address']);
    $jazzcashMobile = trim($_POST['jazzcash_mobile_number']);
    $jazzcashCnic = $_POST['jazzcash_cnic'] ?? '';
    $apiVersion = $_POST['api_version'] ?? JAZZCASH_API_VERSION;

    // Basic validation
    if (empty($fullName) || empty($email) || empty($mobile) || empty($city) || empty($address) || empty($jazzcashMobile)) {
        throw new Exception('All required fields must be filled.');
    }

    // Validate mobile format (updated pattern - no dashes)
    if (!preg_match('/^03[0-9]{9}$/', $jazzcashMobile)) {
        throw new Exception('Please enter a valid JazzCash mobile number (03XXXXXXXXX).');
    }

    // Validate CNIC for v2.0 (updated pattern - 6 digits maximum)
    if ($apiVersion === '2.0' && empty($jazzcashCnic)) {
        throw new Exception('CNIC number is required for JazzCash v2.0.');
    }

    if ($apiVersion === '2.0' && !preg_match('/^[0-9]{6}$/', $jazzcashCnic)) {
        throw new Exception('Please enter a valid 6-digit CNIC number.');
    }

    // Generate order number
    $orderNumber = 'ORD' . date('Ymd') . strtoupper(uniqid());

    // Create order
    $orderQuery = "INSERT INTO orders (
        order_number, user_id, customer_name, customer_email, customer_mobile,
        customer_address, customer_city, customer_country, subtotal, shipping,
        tax, total_amount, payment_method, payment_status, order_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $orderStmt = $conn->prepare($orderQuery);
    $orderStmt->execute([
        $orderNumber,
        $_SESSION['user_id'],
        $fullName,
        $email,
        $mobile,
        $address,
        $city,
        'Pakistan',
        $subtotal,
        $shipping,
        $tax,
        $total,
        'jazzcash_mobile',
        'pending',
        'pending'
    ]);

    $orderId = $conn->lastInsertId();

    // Create order items
    $orderItemQuery = "INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, total_price) VALUES (?, ?, ?, ?, ?, ?)";
    $orderItemStmt = $conn->prepare($orderItemQuery);

    foreach ($cartItems as $item) {
        $itemTotal = $item['price'] * $item['quantity'];
        $orderItemStmt->execute([
            $orderId,
            $item['product_id'],
            $item['name'],
            $item['price'],
            $item['quantity'],
            $itemTotal
        ]);
    }

    // Prepare JazzCash payment data
    $paymentData = [
        'amount' => $total,
        'bill_reference' => $orderNumber,
        'description' => 'Payment for Order #' . $orderNumber,
        'mobile_number' => $jazzcashMobile
    ];

    if ($apiVersion === '2.0') {
        $paymentData['cnic_number'] = $jazzcashCnic;
    }

    // Initiate JazzCash payment
    $paymentInit = $jazzcash->initiatePayment($paymentData);

    // Store transaction record
    $transactionQuery = "INSERT INTO jazzcash_transactions (
        order_id, pp_TxnRefNo, pp_MerchantID, pp_Amount, pp_MobileNumber, pp_CNIC,
        pp_Version, api_version, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $transactionStmt = $conn->prepare($transactionQuery);
    $transactionStmt->execute([
        $orderId,
        $paymentInit['txn_ref_no'],
        $jazzcash->merchantId,
        $total,
        $jazzcashMobile,
        $jazzcashCnic,
        $apiVersion,
        $apiVersion,
        'pending'
    ]);

    // Clear user's cart
    $cartHelper->clearCart($_SESSION['user_id']);

    // Commit transaction
    $conn->commit();

    // Store order ID in session for callback
    $_SESSION['current_order_id'] = $orderId;

    // Create auto-submit form for JazzCash
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Redirecting to JazzCash...</title>
    </head>
    <body>
        <form id="jazzcashForm" action="' . $paymentInit['payment_url'] . '" method="POST">';

    foreach ($paymentInit['data'] as $key => $value) {
        echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
    }

    echo '</form>
        <script>
            document.getElementById("jazzcashForm").submit();
        </script>
        <div style="text-align: center; margin-top: 50px;">
            <h3>Redirecting to JazzCash Payment Gateway...</h3>
            <p>Please wait while we redirect you to the secure payment page.</p>
        </div>
    </body>
    </html>';

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $_SESSION['error'] = $e->getMessage();
    header('Location: checkout.php');
    exit;
}
?>