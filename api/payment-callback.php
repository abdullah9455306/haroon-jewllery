<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once 'jazzcash-payment.php';


define('DB_HOST', 'sql311.infinityfree.com');
define('DB_NAME', 'if0_40317799_haroon_jewellery');
define('DB_USER', 'if0_40317799');
define('DB_PASS', 'Ny7eUO0z2vbqZP');

session_start();

try {
    $db = new Database();
    $conn = $db->getConnection();
    $jazzcash = new JazzCashPayment();

    // Verify JazzCash response
    $response = $jazzcash->verifyResponse($_POST);

    if (!$response['success']) {
        throw new Exception('Invalid payment response: ' . $response['error']);
    }

    // Find transaction
    $transactionQuery = "SELECT * FROM jazzcash_transactions WHERE pp_TxnRefNo = ?";
    $transactionStmt = $conn->prepare($transactionQuery);
    $transactionStmt->execute([$response['txn_ref_no']]);
    $transaction = $transactionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception('Transaction not found.');
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

    // Update order status
    $orderStatus = $jazzcash->isSuccessResponse($response['response_code']) ? 'processing' : 'pending';
    $paymentStatus = $jazzcash->isSuccessResponse($response['response_code']) ? 'paid' : 'failed';

    $updateOrderQuery = "UPDATE orders SET
        payment_status = ?,
        order_status = ?,
        jazzcash_transaction_id = ?,
        jazzcash_mobile_number = ?,
        updated_at = NOW()
        WHERE id = ?";

    $updateOrderStmt = $conn->prepare($updateOrderQuery);
    $updateOrderStmt->execute([
        $paymentStatus,
        $orderStatus,
        $response['txn_ref_no'],
        $response['mobile_number'],
        $transaction['order_id']
    ]);

    // Redirect to appropriate page
    if ($jazzcash->isSuccessResponse($response['response_code'])) {
        $_SESSION['success'] = 'Payment completed successfully! Your order is being processed.';
        print_r($_SESSION['success']);
//         header('Location: ../pages/order-success.php?order_id=' . $transaction['order_id']);
    } else {
        $_SESSION['error'] = 'Payment failed: ' . $response['response_message'];
        print_r($_SESSION['error']);
//         header('Location: ../pages/order-failed.php?order_id=' . $transaction['order_id']);
    }

} catch (Exception $e) {
    error_log('JazzCash Callback Error: ' . $e->getMessage());
    $_SESSION['error'] = 'An error occurred while processing your payment.';
    header('Location: ../pages/checkout.php');
}
exit;