<?php
require_once dirname(__DIR__) . '/config/constants.php';

class ThirdPartyCallbackHandler {
    public $db;

    public function __construct() {
        $this->connectDB();
    }

    public function connectDB() {
        try {
            $this->db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection error");
        }
    }

    /**
     * Get all active third-party callback configurations
     */
    public function getAllActiveCallbacks() {
        $stmt = $this->db->prepare("
            SELECT * FROM third_party_callbacks
            WHERE active = TRUE
            ORDER BY client_name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get callback configuration by client code
     */
    public function getCallbackByCode($clientCode) {
        $stmt = $this->db->prepare("
            SELECT * FROM third_party_callbacks
            WHERE client_code = :code AND active = TRUE
        ");
        $stmt->execute([':code' => strtoupper($clientCode)]);
        return $stmt->fetch();
    }

    /**
     * Send callback to third-party URL
     */
    public function sendCallback($clientCode, $paymentData) {
        $callbackConfig = $this->getCallbackByCode($clientCode);

        if (!$callbackConfig) {
            error_log("Callback configuration not found for client: " . $clientCode);
            return [
                'success' => false,
                'error' => 'Callback configuration not found'
            ];
        }

        return $this->makeCallbackRequest($callbackConfig, $paymentData);
    }

    /**
     * Make HTTP request to third-party URL
     */
    public function makeCallbackRequest($callbackConfig, $paymentData) {
        $url = $callbackConfig['callback_url'];
        $method = $callbackConfig['http_method'] ?? 'POST';
        $contentType = $callbackConfig['content_type'] ?? 'json';

        // Prepare data based on content type
        $payload = $this->preparePayload($paymentData, $contentType);
        $headers = $this->prepareHeaders($callbackConfig, $contentType);

        $curl = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $payload;
        } elseif ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $options[CURLOPT_POSTFIELDS] = $payload;
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        $result = [
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'response' => $response,
            'client_code' => $callbackConfig['client_code'],
            'callback_url' => $url
        ];

        if ($error) {
            $result['error'] = $error;
            $result['success'] = false;
        }

        // Log the callback attempt
        $this->logCallback($callbackConfig['id'], $paymentData, $result);

        return $result;
    }

    /**
     * Prepare payload based on content type
     */
    public function preparePayload($paymentData, $contentType) {
        $standardizedData = $this->standardizePaymentData($paymentData);

        switch ($contentType) {
            case 'json':
                return json_encode($standardizedData);

            case 'form':
                return http_build_query($standardizedData);

            case 'xml':
                return $this->arrayToXml($standardizedData);

            default:
                return json_encode($standardizedData);
        }
    }

    /**
     * Standardize payment data for third-party
     */
    public function standardizePaymentData($paymentData) {
        $standardData = [
            'transaction_id' => $paymentData['txn_ref_no'] ?? '',
            'merchant_transaction_id' => $paymentData['order_id'] ?? '',
            'amount' => $paymentData['amount'] ?? 0,
            'currency' => 'PKR',
            'status' => $paymentData['success'] ? 'SUCCESS' : ($paymentData['pending'] ?? false ? 'PENDING' : 'FAILED'),
            'response_code' => $paymentData['response_code'] ?? '',
            'response_message' => $paymentData['response_message'] ?? '',
            'payment_method' => 'JAZZCASH',
            'api_version' => $paymentData['api_version'] ?? '1.1',
            'customer_mobile' => $paymentData['mobile_number'] ?? '',
            'customer_cnic' => $paymentData['cnic_number'] ?? '',
            'timestamp' => date('Y-m-d H:i:s'),
            'hash_verified' => $paymentData['hash_verified'] ?? false
        ];

        // Add any additional data
        if (isset($paymentData['raw_response'])) {
            $standardData['raw_response'] = $paymentData['raw_response'];
        }

        return $standardData;
    }

    /**
     * Prepare headers for the request
     */
    public function prepareHeaders($callbackConfig, $contentType) {
        $headers = [];

        // Set Content-Type
        switch ($contentType) {
            case 'json':
                $headers[] = 'Content-Type: application/json';
                break;
            case 'form':
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                break;
            case 'xml':
                $headers[] = 'Content-Type: application/xml';
                break;
        }

        // Add Authorization if token exists
        if (!empty($callbackConfig['auth_token'])) {
            $headers[] = 'Authorization: Bearer ' . $callbackConfig['auth_token'];
        }

        // Add custom headers if needed
        if (!empty($callbackConfig['secret_key'])) {
            $headers[] = 'X-Secret-Key: ' . $callbackConfig['secret_key'];
        }

        return $headers;
    }

    /**
     * Convert array to XML (basic implementation)
     */
    public function arrayToXml($array, $rootElement = 'payment') {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><' . $rootElement . '></' . $rootElement . '>');

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->addArrayToXml($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }

        return $xml->asXML();
    }

    public function addArrayToXml($array, $xml) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->addArrayToXml($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }

    /**
     * Log callback attempt to database
     */
    public function logCallback($callbackId, $requestData, $responseData) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO callback_logs (
                    callback_id, request_data, response_data,
                    http_code, success, created_at
                ) VALUES (
                    :callback_id, :request_data, :response_data,
                    :http_code, :success, NOW()
                )
            ");

            $stmt->execute([
                ':callback_id' => $callbackId,
                ':request_data' => json_encode($requestData),
                ':response_data' => json_encode($responseData),
                ':http_code' => $responseData['http_code'] ?? 0,
                ':success' => $responseData['success'] ? 1 : 0
            ]);

            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to log callback: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process callbacks for all active clients
     */
    public function processCallbacksForAll($paymentData) {
        $callbacks = $this->getAllActiveCallbacks();
        $results = [];

        foreach ($callbacks as $callback) {
            $result = $this->sendCallback($callback['client_code'], $paymentData);
            $results[$callback['client_code']] = $result;
        }

        return $results;
    }
}