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
    if (empty($input['transaction_ref']) || empty($input['client_code'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: transaction_ref and client_code']);
        exit();
    }

    $transactionRef = $input['transaction_ref'];
    $clientCode = strtoupper($input['client_code']);
    $apiVersion = $input['api_version'] ?? '1.1';

    // Initialize JazzCash Payment
    $jazzcash = new JazzCashPayment($apiVersion);

    // Perform status inquiry
    $statusResult = $jazzcash->performStatusInquiry($transactionRef);

    // Add client code to result
    $statusResult['client_code'] = $clientCode;

    // If status inquiry returned data, trigger third-party callback
    if (isset($statusResult['success']) && !empty($clientCode)) {
        $callbackHandler = new ThirdPartyCallbackHandler();

        // Send callback to specific client
        $callbackResult = $callbackHandler->sendCallback($clientCode, $statusResult);

        // Add callback result to response
        $statusResult['callback_payload'] = $callbackHandler->preparePayload($statusResult, 'json');
        $statusResult['callback_sent'] = $callbackResult['success'] ?? false;
        $statusResult['callback_response'] = $callbackResult['response'] ?? '';
        $statusResult['callback_http_code'] = $callbackResult['http_code'] ?? 0;
    }

    // Return response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'status_result' => $statusResult,
        'transaction_ref' => $transactionRef
    ]);

} catch (Exception $e) {
    error_log("Status Query API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_type' => get_class($e)
    ]);
}