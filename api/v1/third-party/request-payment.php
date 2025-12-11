<?php
require_once '../../../config/constants.php';
require_once '../../jazzcash-payment.php';
require_once '../../third-party-callback-handler.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required parameters
    $required = ['amount', 'mobile_number', 'description', 'client_code'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            exit();
        }
    }

    $clientCode = strtoupper($input['client_code']);
    $amount = floatval($input['amount']);
    $mobileNumber = $input['mobile_number'];
    $description = $input['description'];
    $cnicNumber = $input['cnic_number'] ?? '';
    $apiVersion = $input['api_version'] ?? '1.1';

    // Validate amount
    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid amount']);
        exit();
    }

    // Initialize JazzCash Payment
    $jazzcash = new JazzCashPayment($apiVersion);

    // Prepare order data
    $orderData = [
        'amount' => $amount,
        'mobile_number' => $mobileNumber,
        'cnic_number' => $cnicNumber,
        'description' => $description
    ];

    // Initiate payment
    $paymentInit = $jazzcash->initiatePayment($orderData);

    // Call JazzCash API
    $apiResponse = $jazzcash->callAPI($paymentInit);

    // Parse JazzCash response
    $responseArray = json_decode($apiResponse, true);

    if (!$responseArray) {
        parse_str($apiResponse, $responseArray);
    }

    // Verify response hash
    $hashVerified = $jazzcash->verifyResponseHash($responseArray);

    $paymentResult = $jazzcash->verifyResponse($responseArray);
    $paymentResult['hash_verified'] = $hashVerified;
    $paymentResult['client_code'] = $clientCode;

    // If payment was successful or pending, trigger third-party callbacks
//     if ($paymentResult['success'] || ($paymentResult['pending'] ?? false)) {
        $callbackHandler = new ThirdPartyCallbackHandler();

        // Send callback to specific client
        $callbackResult = $callbackHandler->sendCallback($clientCode, $paymentResult);

        // Add callback result to response
        $paymentResult['callback_payload'] = $callbackHandler->preparePayload($paymentResult, 'json');
        $paymentResult['callback_sent'] = $callbackResult['success'] ?? false;
        $paymentResult['callback_response'] = $callbackResult['response'] ?? '';
        $paymentResult['callback_http_code'] = $callbackResult['http_code'] ?? 0;
//     }

    // Return response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'payment_result' => $paymentResult,
        'transaction_ref' => $paymentInit['txn_ref_no'],
        'payment_url' => $paymentInit['payment_url']
    ]);


} catch (Exception $e) {
    error_log("Payment API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_type' => get_class($e)
    ]);
}