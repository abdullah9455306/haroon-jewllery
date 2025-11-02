<?php
require_once '../config/constants.php';
require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = $input['order_id'] ?? '';
    $reason = $input['reason'] ?? '';

    $db = new Database();
    $conn = $db->getConnection();

    // Verify order belongs to user and is cancellable
    $check_stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND order_status = 'pending'");
    $check_stmt->execute([$order_id, $_SESSION['user_id']]);

    if ($check_stmt->rowCount() > 0) {
        $update_stmt = $conn->prepare("UPDATE orders SET order_status = 'cancelled', notes = CONCAT(COALESCE(notes, ''), ' Cancellation reason: ', ?) WHERE id = ?");
        $update_stmt->execute([$reason, $order_id]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Order cannot be cancelled']);
    }
}
?>