<?php
require_once '../../../config/constants.php';
require_once '../../jazzcash-payment.php';
require_once '../../third-party-callback-handler.php';

// This endpoint handles JazzCash callback and forwards to third parties
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Get POST data from JazzCash
    $postData = $_POST;

    // If no POST data, try to get from raw input
    if (empty($postData)) {
        $rawInput = file_get_contents('php://input');
        parse_str($rawInput, $postData);
    }

    if (empty($postData)) {
        http_response_code(400);
        echo json_encode(['error' => 'No data received']);
        exit();
    }

    // Get client code from request (passed in URL or header)
    $clientCode = '';

    // Check URL parameter first
    if (isset($_GET['client'])) {
        $clientCode = strtoupper($_GET['client']);
    }
    // Check header
    elseif (isset($_SERVER['HTTP_X_CLIENT_CODE'])) {
        $clientCode = strtoupper($_SERVER['HTTP_X_CLIENT_CODE']);
    }
    // Check POST data
    elseif (isset($postData['client_code'])) {
        $clientCode = strtoupper($postData['client_code']);
    }

    if (empty($clientCode)) {
        http_response_code(400);
        echo json_encode(['error' => 'Client code not specified']);
        exit();
    }

    // Initialize JazzCash Payment with version from response
    $apiVersion = $postData['pp_Version'] ?? '1.1';
    $jazzcash = new JazzCashPayment($apiVersion);

    // Verify and parse JazzCash response
    $paymentResult = $jazzcash->verifyResponse($postData);

    // Add original POST data and hash verification
    $paymentResult['hash_verified'] = $jazzcash->verifyResponseHash($postData);
    $paymentResult['raw_post_data'] = $postData;
    $paymentResult['client_code'] = $clientCode;

    // Process callbacks for all active clients or specific client
    $callbackHandler = new ThirdPartyCallbackHandler();

    if ($clientCode === 'ALL') {
        // Send to all active clients
        $callbackResults = $callbackHandler->processCallbacksForAll($paymentResult);
    } else {
        // Send to specific client
        $callbackResults = [$clientCode => $callbackHandler->sendCallback($clientCode, $paymentResult)];
    }

    // Return response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Callback processed successfully',
        'payment_result' => $paymentResult,
        'callback_results' => $callbackResults,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    // Log the callback
    error_log("Callback processed for client: $clientCode, Status: " .
              ($paymentResult['success'] ? 'SUCCESS' : 'FAILED'));

} catch (Exception $e) {
    error_log("Callback Respond API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}