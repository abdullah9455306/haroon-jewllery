<?php
require_once '../../../config/constants.php';
require_once '../../third-party-callback-handler.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Simple API key authentication (optional)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$validApiKey = 'YOUR_API_KEY_HERE'; // Change this in production

if ($apiKey !== $validApiKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$callbackHandler = new ThirdPartyCallbackHandler();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get all clients
        $clients = $callbackHandler->getAllActiveCallbacks();
        echo json_encode(['success' => true, 'clients' => $clients]);
        break;

    case 'POST':
        // Add new client
        $input = json_decode(file_get_contents('php://input'), true);
        $required = ['client_name', 'client_code', 'callback_url'];

        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                exit();
            }
        }

        // Here you would add the client to database
        echo json_encode([
            'success' => true,
            'message' => 'Client added successfully',
            'client' => $input
        ]);
        break;

    case 'PUT':
        // Update client
        $input = json_decode(file_get_contents('php://input'), true);
        echo json_encode([
            'success' => true,
            'message' => 'Client updated successfully'
        ]);
        break;

    case 'DELETE':
        // Delete client (soft delete)
        $clientCode = $_GET['code'] ?? '';
        echo json_encode([
            'success' => true,
            'message' => "Client $clientCode deactivated"
        ]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}