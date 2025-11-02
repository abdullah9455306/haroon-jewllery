<?php
session_start();
require_once '../config/constants.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if(isset($input['theme']) && in_array($input['theme'], ['light', 'dark'])) {
        $_SESSION['theme'] = $input['theme'];

        // If user is logged in, update in database
        if(isset($_SESSION['user_id'])) {
            $db = new Database();
            $conn = $db->getConnection();

            $stmt = $conn->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
            $stmt->execute([$input['theme'], $_SESSION['user_id']]);
        }

        echo json_encode(['success' => true, 'theme' => $input['theme']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid theme']);
    }
}
?>