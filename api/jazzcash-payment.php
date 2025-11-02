<?php
require_once '../config/constants.php';

class JazzCashPayment {
    private $merchantId;
    private $password;
    private $salt;
    private $returnUrl;
    private $type; // 'mobile' or 'card'

    public function __construct($type = 'mobile') {
        if($type === 'card') {
            $this->merchantId = JAZZCASH_CARD_MERCHANT_ID;
            $this->password = JAZZCASH_CARD_PASSWORD;
            $this->salt = JAZZCASH_CARD_SALT;
        } else {
            $this->merchantId = JAZZCASH_MERCHANT_ID;
            $this->password = JAZZCASH_PASSWORD;
            $this->salt = JAZZCASH_SALT;
        }

        $this->returnUrl = JAZZCASH_RETURN_URL;
        $this->type = $type;
    }

    public function generateHash($data) {
        $string = '';
        foreach($data as $key => $value) {
            if($value != '') {
                $string .= $value . '&';
            }
        }
        $string = rtrim($string, '&');
        $string = $string . $this->salt;
        return hash('sha256', $string);
    }

    public function initiateMobilePayment($orderData) {
        $pp_TxnRefNo = 'TXN' . time() . rand(1000, 9999);
        $pp_Amount = $orderData['amount'] * 100; // Convert to paisa

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
            'pp_TxnDateTime' => date('YmdHis'),
            'pp_BillReference' => $orderData['bill_reference'],
            'pp_Description' => $orderData['description'],
            'pp_TxnExpiryDateTime' => date('YmdHis', strtotime('+1 hour')),
            'pp_ReturnURL' => $this->returnUrl,
            'pp_SecureHash' => '',
            'ppmpf_1' => $orderData['mobile_number'],
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
            'payment_url' => 'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/'
        ];
    }

    public function initiateCardPayment($orderData) {
        $pp_TxnRefNo = 'TXN' . time() . rand(1000, 9999);
        $pp_Amount = $orderData['amount'] * 100; // Convert to paisa

        $data = [
            'pp_Version' => '1.1',
            'pp_TxnType' => 'CRDC',
            'pp_Language' => 'EN',
            'pp_MerchantID' => $this->merchantId,
            'pp_SubMerchantID' => '',
            'pp_Password' => $this->password,
            'pp_BankID' => '',
            'pp_ProductID' => '',
            'pp_TxnRefNo' => $pp_TxnRefNo,
            'pp_Amount' => $pp_Amount,
            'pp_TxnCurrency' => 'PKR',
            'pp_TxnDateTime' => date('YmdHis'),
            'pp_BillReference' => $orderData['bill_reference'],
            'pp_Description' => $orderData['description'],
            'pp_TxnExpiryDateTime' => date('YmdHis', strtotime('+1 hour')),
            'pp_ReturnURL' => $this->returnUrl,
            'pp_SecureHash' => '',
            'ppmpf_1' => $orderData['card_number'] ?? '',
            'ppmpf_2' => $orderData['card_expiry'] ?? '',
            'ppmpf_3' => $orderData['card_holder_name'] ?? '',
            'ppmpf_4' => '',
            'ppmpf_5' => ''
        ];

        // Generate secure hash
        $data['pp_SecureHash'] = $this->generateHash($data);

        return [
            'data' => $data,
            'txn_ref_no' => $pp_TxnRefNo,
            'payment_url' => 'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/'
        ];
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
                'payment_type' => $this->type
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Hash verification failed'
            ];
        }
    }
}