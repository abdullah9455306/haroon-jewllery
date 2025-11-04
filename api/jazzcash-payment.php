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

    public function verifyResponse($postData) {
        $responseHash = $postData['pp_SecureHash'];
        unset($postData['pp_SecureHash']);

        $calculatedHash = $this->generateHash($postData);

        if($responseHash === $calculatedHash) {
            return [
                'success' => true,
                'response_code' => $postData['pp_ResponseCode'],
                'response_message' => $postData['pp_ResponseMessage'],
                'txn_ref_no' => $postData['pp_TxnRefNo'],
                'amount' => $postData['pp_Amount'] / 100,
                'mobile_number' => $postData['ppmpf_1'] ?? '',
                'cnic_number' => $postData['ppmpf_2'] ?? '',
                'api_version' => $postData['pp_Version'] ?? '1.1'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Hash verification failed'
            ];
        }
    }

    public function isSuccessResponse($responseCode) {
        return $responseCode === '000' || $responseCode === '00';
    }
}