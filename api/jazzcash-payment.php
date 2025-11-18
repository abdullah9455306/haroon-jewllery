<?php
require_once '../config/constants.php';

class JazzCashPayment {
    public $merchantId;
    public $password;
    public $salt;
    public $returnUrl;
    public $apiVersion;
    public $environment;

    public function __construct($apiVersion = null) {
        $this->apiVersion = $apiVersion ?: JAZZCASH_API_VERSION;
        $this->environment = JAZZCASH_ENVIRONMENT;

        if($this->apiVersion === '2.0') {
            $this->merchantId = JAZZCASH_V2_MERCHANT_ID;
            $this->password = JAZZCASH_V2_PASSWORD;
            $this->salt = JAZZCASH_V2_INTEGRITY_SALT;
        } else {
            $this->merchantId = JAZZCASH_MERCHANT_ID;
            $this->password = JAZZCASH_PASSWORD;
            $this->salt = JAZZCASH_SALT;
        }

        $this->returnUrl = JAZZCASH_RETURN_URL;
    }

    public function getApiVersion() {
        return $this->apiVersion;
    }

    public function getBaseUrl() {
        if ($this->environment === 'production') {
            return 'https://jazzcash.com.pk/';
        } else {
            return 'https://sandbox.jazzcash.com.pk/';
        }
    }

    public function generateHash($data_array) {
        ksort($data_array);

        $str = '';
        foreach($data_array as $key => $value)
         {
            if(!empty($value)){
                $str = $str . '&' . $value;
            }
         }

         $str = $this->salt.$str;
         $pp_SecureHash = hash_hmac('sha256', $str, $this->salt);

         return $pp_SecureHash;
    }

        public function verifyResponseHash($postData) {
            if (!isset($postData['pp_SecureHash'])) {
                return false;
            }

            $receivedHash = $postData['pp_SecureHash'];

            unset($postData['pp_SecureHash']);

            ksort($postData);

            $str = '';
            foreach($postData as $key => $value) {
                if(!empty($value)){
                    $str = $str . '&' . $value;
                }
            }

            $str = $this->salt.$str;
            $calculatedHash = hash_hmac('sha256', $str, $this->salt);

            $calculatedHash = strtolower($calculatedHash);
            $receivedHash = strtolower($receivedHash);

            $result = hash_equals($calculatedHash, $receivedHash);
            return $result;
        }

    public function initiatePaymentV1($orderData) {
        date_default_timezone_set('Asia/Karachi');

        $dateTime = new DateTime();
        $pp_TxnDateTime = $dateTime->format('YmdHis');

        $expiryDateTime = $dateTime;
        $expiryDateTime->modify('+' . 1 . ' hours');
        $pp_TxnExpiryDateTime = $expiryDateTime->format('YmdHis');
        $pp_TxnRefNo = 'T'.$pp_TxnDateTime;

        $pp_Amount = $orderData['amount'];

        $data = [
            'pp_Version' => '1.1',
            'pp_TxnType' => 'MWALLET',
            'pp_Language' => 'EN',
            'pp_MerchantID' => $this->merchantId,
            'pp_SubMerchantID' => '',
            'pp_Password' => $this->password,
            'pp_BankID' => '',
            'pp_ProductID' => '',
            'pp_TxnRefNo' => $pp_TxnRefNo,
            'pp_Amount' => $pp_Amount,
            'pp_TxnCurrency' => 'PKR',
            'pp_TxnDateTime' => $pp_TxnDateTime,
            'pp_BillReference' => 'billref',
            'pp_Description' => $orderData['description'],
            'pp_TxnExpiryDateTime' => $pp_TxnExpiryDateTime,
            'pp_SecureHash' => '',
            'ppmpf_1' => $orderData['mobile_number'],
            'ppmpf_2' => '',
            'ppmpf_3' => '',
            'ppmpf_4' => '',
            'ppmpf_5' => ''
        ];

        $data['pp_SecureHash'] = $this->generateHash($data);

        return [
            'data' => $data,
            'txn_ref_no' => $pp_TxnRefNo,
            'payment_url' => $this->getBaseUrl() . 'ApplicationAPI/API/2.0/Purchase/DoMWalletTransaction'
        ];
    }

    public function initiatePaymentV2($orderData) {
        date_default_timezone_set('Asia/Karachi');

        $dateTime = new DateTime();
        $pp_TxnDateTime = $dateTime->format('YmdHis');

        $expiryDateTime = $dateTime;
        $expiryDateTime->modify('+' . 1 . ' hours');
        $pp_TxnExpiryDateTime = $expiryDateTime->format('YmdHis');
        $pp_TxnRefNo = 'T'.$pp_TxnDateTime;

        $pp_Amount = $orderData['amount'];

        $data = [
            'pp_Language' => 'EN',
            'pp_MerchantID' => $this->merchantId,
            'pp_SubMerchantID' => '',
            'pp_Password' => $this->password,
            'pp_TxnRefNo' => $pp_TxnRefNo,
            'pp_MobileNumber' => $orderData['mobile_number'],
            'pp_CNIC' => $orderData['cnic_number'],
            'pp_Amount' => $pp_Amount,
            'pp_DiscountedAmount' => '',
            'pp_TxnCurrency' => 'PKR',
            'pp_TxnDateTime' => $pp_TxnDateTime,
            'pp_BillReference' => 'billref',
            'pp_Description' => $orderData['description'],
            'pp_TxnExpiryDateTime' => $pp_TxnExpiryDateTime,
            'pp_SecureHash' => '',
            'ppmpf_1' => '',
            'ppmpf_2' => '',
            'ppmpf_3' => '',
            'ppmpf_4' => '',
            'ppmpf_5' => ''
        ];

        // Generate secure hash
        $data['pp_SecureHash'] = $this->generateHash($data);

        return [
            'data' => $data,
            'txn_ref_no' => $pp_TxnRefNo,
            'payment_url' => $this->getBaseUrl() . 'ApplicationAPI/API/2.0/Purchase/domwallettransaction'
        ];
    }

    public function initiatePayment($orderData) {
        if ($this->apiVersion === '2.0') {
            return $this->initiatePaymentV2($orderData);
        } else {
            return $this->initiatePaymentV1($orderData);
        }
    }

    public function callAPI($allData) {
        $curl = curl_init();

        // JazzCash expects form data, not JSON - use http_build_query instead of json_encode
        $data = http_build_query($allData['data']);
        $postUrl = $allData['payment_url'];

        curl_setopt_array($curl, [
            CURLOPT_URL => $postUrl,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 0,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);

        // EXECUTE
        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        if (!$result) {
            throw new Exception("API Connection Failure: " . $error);
        }

        return $result;
    }

    public function verifyResponse($postData) {
      if (!$this->verifyResponseHash($postData)) {
                return [
                    'success' => false,
                    'error' => 'Response integrity check failed',
                    'response_code' => 'HASH_MISMATCH',
                    'response_message' => 'Secure hash verification failed for status inquiry'
                ];
      }

       $responseCode = $postData['pp_ResponseCode'] ?? 'UNKNOWN';
       $isSuccess = $this->isSuccessResponse($responseCode);
       $isPending = $this->isPendingResponse($responseCode);

       if ($isSuccess) {
           return [
               'success' => true,
               'response_code' => $responseCode,
               'response_message' => $postData['pp_ResponseMessage'],
               'txn_ref_no' => $postData['pp_TxnRefNo'],
               'amount' => $postData['pp_Amount'] / 100,
               'mobile_number' => $postData['ppmpf_1'] ?? '',
               'cnic_number' => $postData['ppmpf_2'] ?? '',
               'api_version' => $postData['pp_Version'] ?? '1.1',
               'status' => 'success',
           ];
       } elseif ($isPending) {
           return [
               'success' => false,
               'pending' => true,
               'response_code' => $responseCode,
               'response_message' => $postData['pp_ResponseMessage'],
               'txn_ref_no' => $postData['pp_TxnRefNo'],
               'amount' => $postData['pp_Amount'] / 100,
               'mobile_number' => $postData['ppmpf_1'] ?? '',
               'cnic_number' => $postData['ppmpf_2'] ?? '',
               'api_version' => $postData['pp_Version'] ?? '1.1',
               'status' => 'pending'
           ];
       } else {
           return [
               'success' => false,
               'response_code' => $responseCode,
               'response_message' => $postData['pp_ResponseMessage'],
               'error' => $postData['pp_ResponseMessage'],
               'txn_ref_no' => $postData['pp_TxnRefNo'],
               'amount' => $postData['pp_Amount'] / 100,
               'mobile_number' => $postData['ppmpf_1'] ?? '',
               'cnic_number' => $postData['ppmpf_2'] ?? '',
               'api_version' => $postData['pp_Version'] ?? '1.1',
               'status' => 'failed'
           ];
       }
    }

    public function isSuccessResponse($responseCode) {
        $successCodes = ['000', '00', '121'];
        return in_array($responseCode, $successCodes);
    }

    public function isPendingResponse($responseCode) {
        $pendingCodes = ['124', '157', '001'];
        return in_array($responseCode, $pendingCodes);
    }

    // Status Inquiry Methods
    public function statusInquiryV1($transactionRefNo) {
        date_default_timezone_set('Asia/Karachi');

        $data = [
            'pp_TxnRefNo' => $transactionRefNo,
            'pp_MerchantID' => $this->merchantId,
            'pp_Password' => $this->password,
        ];

        $data['pp_SecureHash'] = $this->generateHash($data);

        return [
            'data' => $data,
            'inquiry_url' => $this->getBaseUrl() . 'ApplicationAPI/API/PaymentInquiry/Inquire'
        ];
    }

    public function statusInquiryV2($transactionRefNo) {
        date_default_timezone_set('Asia/Karachi');

        $data = [
            'pp_TxnRefNo' => $transactionRefNo,
            'pp_MerchantID' => $this->merchantId,
            'pp_Password' => $this->password,
        ];

        $data['pp_SecureHash'] = $this->generateHash($data);

        return [
            'data' => $data,
            'inquiry_url' => $this->getBaseUrl() . 'ApplicationAPI/API/PaymentInquiry/Inquire'
        ];
    }

    public function statusInquiry($transactionRefNo) {
        if ($this->apiVersion === '2.0') {
            return $this->statusInquiryV2($transactionRefNo);
        } else {
            return $this->statusInquiryV1($transactionRefNo);
        }
    }

public function performStatusInquiry($transactionRefNo) {
    try {
        $inquiryData = $this->statusInquiry($transactionRefNo);

        $curl = curl_init();

        $data = http_build_query($inquiryData['data']);
        $inquiryUrl = $inquiryData['inquiry_url'];

        curl_setopt_array($curl, [
            CURLOPT_URL => $inquiryUrl,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 0,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);

        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        if (!$result) {
            throw new Exception("Status Inquiry API Connection Failure: " . $error);
        }

        // Debug: Log the raw response
        error_log("Raw JazzCash Response: " . $result);

        // Try to parse as JSON first
        $responseArray = json_decode($result, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            // Successfully decoded as JSON
            return $this->parseStatusInquiryResponse($responseArray);
        }

        // If JSON decoding fails, try form data parsing
        parse_str($result, $formDataArray);

        if (!empty($formDataArray)) {
            return $this->parseStatusInquiryResponse($formDataArray);
        }

        // If both fail, check if it's a JSON string as array key (your current issue)
        if (is_array($result) && count($result) === 1) {
            $keys = array_keys($result);
            $firstKey = $keys[0];

            // Try to decode the key as JSON
            $jsonFromKey = json_decode($firstKey, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->parseStatusInquiryResponse($jsonFromKey);
            }
        }

        throw new Exception("Invalid response format from JazzCash API: " . $result);

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'response_code' => 'ERROR',
            'response_message' => $e->getMessage()
        ];
    }
}

private function parseStatusInquiryResponse($responseData) {
   if (!$this->verifyResponseHash($responseData)) {
        return [
            'success' => false,
            'error' => 'Response integrity check failed',
            'response_code' => 'HASH_MISMATCH',
            'response_message' => 'Secure hash verification failed for status inquiry'
        ];
    }

    $responseCode = $responseData['pp_ResponseCode'] ?? 'UNKNOWN';
    $responseMessage = $responseData['pp_ResponseMessage'] ?? 'No response message';

    $isSuccess = $this->isSuccessResponse($responseCode);
    $isPending = $this->isPendingResponse($responseCode);

    $status = 'failed';
    if ($isSuccess) {
        $status = 'success';
    } elseif ($isPending) {
        $status = 'pending';
    }

    $status = $statusMap[$responseCode] ?? $responseMessage;

    return [
            'success' => $isSuccess,
            'pending' => $isPending,
            'response_code' => $responseCode,
            'response_message' => $responseMessage,
            'status' => $status,
            'transaction_ref_no' => $responseData['pp_RetrievalReferenceNo'] ?? $responseData['pp_TxnRefNo'] ?? '',
            'amount' => isset($responseData['pp_Amount']) ? $responseData['pp_Amount'] / 100 : 0,
            'transaction_date' => $responseData['pp_SettlementDate'] ?? $responseData['pp_TxnDateTime'] ?? '',
            'bank_id' => $responseData['pp_BankID'] ?? '',
            'product_id' => $responseData['pp_ProductID'] ?? '',
            'payment_response_code' => $responseData['pp_PaymentResponseCode'] ?? '',
            'payment_response_message' => $responseData['pp_PaymentResponseMessage'] ?? '',
            'auth_code' => $responseData['pp_AuthCode'] ?? '',
            'settlement_date' => $responseData['pp_SettlementDate'] ?? '',
            'raw_response' => $responseData
    ];
}
}