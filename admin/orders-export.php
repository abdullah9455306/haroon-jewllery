<?php
require_once '../config/database.php';
require_once '../config/constants.php';

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Check admin permissions
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] !== 'admin' && !$_SESSION['is_super_admin'])) {
    header('Location: login.php');
    exit;
}

// Get filter parameters from GET or POST
$search = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';
$order_status = isset($_REQUEST['order_status']) ? $_REQUEST['order_status'] : '';
$payment_status = isset($_REQUEST['payment_status']) ? $_REQUEST['payment_status'] : '';
$payment_method = isset($_REQUEST['payment_method']) ? $_REQUEST['payment_method'] : '';
$date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : 'all'; // all, filtered

// Build WHERE conditions (same as orders.php)
$whereConditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_mobile LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($order_status) && $order_status !== 'all') {
    $whereConditions[] = "o.order_status = ?";
    $params[] = $order_status;
}

if (!empty($payment_status) && $payment_status !== 'all') {
    $whereConditions[] = "o.payment_status = ?";
    $params[] = $payment_status;
}

if (!empty($payment_method) && $payment_method !== 'all') {
    $whereConditions[] = "o.payment_method = ?";
    $params[] = $payment_method;
}

if (!empty($date_from)) {
    $whereConditions[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $whereConditions[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

$whereClause = implode(' AND ', $whereConditions);

// Get orders data
$sql = "SELECT
            o.id,
            o.order_number,
            o.customer_name,
            o.customer_email,
            o.customer_mobile,
            o.customer_address,
            o.customer_city,
            o.customer_country,
            o.subtotal,
            o.shipping,
            o.tax,
            o.total_amount,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.jazzcash_transaction_id,
            o.jazzcash_mobile_number,
            o.notes,
            o.created_at,
            o.updated_at,
            u.name as user_name,
            u.email as user_email,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
            (SELECT GROUP_CONCAT(CONCAT(product_name, ' (Qty: ', quantity, ')') SEPARATOR '; ')
             FROM order_items WHERE order_id = o.id) as items_summary
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE $whereClause
        ORDER BY o.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order items for detailed export
$orderIds = array_column($orders, 'id');
$orderItems = [];
if (!empty($orderIds)) {
    $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
    $itemsSql = "SELECT oi.*, o.order_number
                 FROM order_items oi
                 JOIN orders o ON oi.order_id = o.id
                 WHERE oi.order_id IN ($placeholders)
                 ORDER BY oi.order_id, oi.id";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->execute($orderIds);
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Set headers for CSV download
$filename = 'orders_export_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 compatibility with Excel
fputs($output, "\xEF\xBB\xBF");

// CSV headers
$headers = [
    'Order ID',
    'Order Number',
    'Customer Name',
    'Customer Email',
    'Customer Phone',
    'Customer Address',
    'City',
    'Country',
    'User Account',
    'User Email',
    'Subtotal (' . CURRENCY . ')',
    'Shipping (' . CURRENCY . ')',
    'Tax (' . CURRENCY . ')',
    'Total Amount (' . CURRENCY . ')',
    'Payment Method',
    'Payment Status',
    'Order Status',
    'JazzCash Transaction ID',
    'JazzCash Mobile Number',
    'Items Count',
    'Items Summary',
    'Order Notes',
    'Created Date',
    'Updated Date'
];

fputcsv($output, $headers);

// Add data rows
foreach ($orders as $order) {
    $row = [
        $order['id'],
        $order['order_number'],
        $order['customer_name'],
        $order['customer_email'],
        $order['customer_mobile'],
        $order['customer_address'],
        $order['customer_city'],
        $order['customer_country'],
        $order['user_name'] ?: 'Guest',
        $order['user_email'] ?: 'N/A',
        number_format($order['subtotal'], 2),
        number_format($order['shipping'], 2),
        number_format($order['tax'], 2),
        number_format($order['total_amount'], 2),
        formatPaymentMethod($order['payment_method']),
        ucfirst($order['payment_status']),
        ucfirst($order['order_status']),
        $order['jazzcash_transaction_id'] ?: 'N/A',
        $order['jazzcash_mobile_number'] ?: 'N/A',
        $order['item_count'],
        $order['items_summary'] ?: 'N/A',
        $order['notes'] ?: 'N/A',
        $order['created_at'],
        $order['updated_at']
    ];

    fputcsv($output, $row);
}

// Add summary section
fputcsv($output, []); // Empty row
fputcsv($output, ['EXPORT SUMMARY']);
fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
fputcsv($output, ['Total Orders', count($orders)]);
fputcsv($output, ['Total Revenue', CURRENCY . ' ' . number_format(array_sum(array_column($orders, 'total_amount')), 2)]);

// Add filter information
fputcsv($output, []); // Empty row
fputcsv($output, ['FILTER CRITERIA']);
if (!empty($search)) {
    fputcsv($output, ['Search Term', $search]);
}
if (!empty($order_status) && $order_status !== 'all') {
    fputcsv($output, ['Order Status', ucfirst($order_status)]);
}
if (!empty($payment_status) && $payment_status !== 'all') {
    fputcsv($output, ['Payment Status', ucfirst($payment_status)]);
}
if (!empty($payment_method) && $payment_method !== 'all') {
    fputcsv($output, ['Payment Method', formatPaymentMethod($payment_method)]);
}
if (!empty($date_from)) {
    fputcsv($output, ['Date From', $date_from]);
}
if (!empty($date_to)) {
    fputcsv($output, ['Date To', $date_to]);
}

// Close output stream
fclose($output);
exit;

/**
 * Format payment method for display
 */
function formatPaymentMethod($method) {
    $methods = [
        'jazzcash_mobile' => 'JazzCash Mobile',
        'jazzcash_card' => 'JazzCash Card',
        'cod' => 'Cash on Delivery',
        'bank' => 'Bank Transfer'
    ];
    return $methods[$method] ?? ucfirst($method);
}
?>