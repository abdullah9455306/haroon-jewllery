<?php
require_once '../config/constants.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';

    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $db = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        echo json_encode(['available' => $stmt->rowCount() === 0]);
    } else {
        echo json_encode(['available' => false]);
    }
}
?>