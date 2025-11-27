<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../helpers/cart_helper.php';
require_once '../api/jazzcash-payment.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please login to complete your order.';
    header('Location: login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout');
    exit;
}

// Function to process payment response
function processPaymentResponse($postData, $orderId, $conn) {
    $jazzcash = new JazzCashPayment();

    try {
        // Verify JazzCash response
        $response = $jazzcash->verifyResponse($postData);

        if (!$response['success']) {
            throw new Exception('Invalid payment response: ' . $response['error']);
        }

        // Update transaction status
        $updateTransactionQuery = "UPDATE jazzcash_transactions SET
            pp_ResponseCode = ?,
            pp_ResponseMessage = ?,
            status = ?,
            updated_at = NOW()
            WHERE pp_TxnRefNo = ?";

        $transactionStatus = $jazzcash->isSuccessResponse($response['response_code']) ? 'completed' : 'failed';
        $updateTransactionStmt = $conn->prepare($updateTransactionQuery);
        $updateTransactionStmt->execute([
            $response['response_code'],
            $response['response_message'],
            $transactionStatus,
            $response['txn_ref_no']
        ]);

        // Update order status - REMOVED jazzcash_transaction_ref column
        $orderStatus = $jazzcash->isSuccessResponse($response['response_code']) ? 'processing' : 'pending';
        $paymentStatus = $jazzcash->isSuccessResponse($response['response_code']) ? 'paid' : 'failed';

        $updateOrderQuery = "UPDATE orders SET
            payment_status = ?,
            order_status = ?,
            jazzcash_mobile_number = ?,
            updated_at = NOW()
            WHERE id = ?";

        $updateOrderStmt = $conn->prepare($updateOrderQuery);
        $updateOrderStmt->execute([
            $paymentStatus,
            $orderStatus,
            $response['mobile_number'],
            $orderId
        ]);

        return [
            'success' => $jazzcash->isSuccessResponse($response['response_code']),
            'message' => $response['response_message'],
            'order_id' => $orderId,
            'transaction_ref' => $response['txn_ref_no']
        ];

    } catch (Exception $e) {
        error_log('Payment Processing Error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
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

    // Validate mobile format
    if (!preg_match('/^03[0-9]{9}$/', $jazzcashMobile)) {
        throw new Exception('Please enter a valid JazzCash mobile number (03XXXXXXXXX).');
    }

    // Validate CNIC for v2.0
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

    // Call JazzCash API and process response
    $make_call = $jazzcash->callAPI($paymentInit);
    $make_call = json_decode($make_call, true);
    // Process the payment response
    if (isset($make_call['pp_ResponseCode'])) {
        $paymentResult = processPaymentResponse($make_call, $orderId, $conn);

        if ($paymentResult['success']) {
            $_SESSION['success'] = 'Payment completed successfully! Your order is being processed.';
            header('Location: ' . SITE_URL . '/order-success/' . $orderId);
        } else {
            $_SESSION['error'] = 'Payment failed: ' . $paymentResult['message'];
            header('Location: ' . SITE_URL . '/order-failed/' . $orderId);
        }
        exit;
    } else {
        $_SESSION['error'] = 'No response received from payment gateway.';
        header('Location: checkout');
        exit;
    }

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $_SESSION['error'] = $e->getMessage();
    header('Location: checkout');
    exit;
}
?>