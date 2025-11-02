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

// Get filter parameters
$search = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';
$order_status = isset($_REQUEST['order_status']) ? $_REQUEST['order_status'] : '';
$payment_status = isset($_REQUEST['payment_status']) ? $_REQUEST['payment_status'] : '';
$payment_method = isset($_REQUEST['payment_method']) ? $_REQUEST['payment_method'] : '';
$date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';

// Build WHERE conditions
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

// Get orders with their items
$sql = "SELECT
            o.id as order_id,
            o.order_number,
            o.customer_name,
            o.customer_email,
            o.customer_mobile,
            o.subtotal,
            o.shipping,
            o.tax,
            o.total_amount,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.created_at,
            oi.product_name,
            oi.product_price,
            oi.quantity,
            oi.total_price as item_total,
            p.sku as product_sku
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE $whereClause
        ORDER BY o.created_at DESC, oi.id";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$orderData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
$filename = 'orders_detailed_export_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 compatibility with Excel
fputs($output, "\xEF\xBB\xBF");

// CSV headers for detailed export
$headers = [
    'Order Number',
    'Customer Name',
    'Customer Email',
    'Customer Phone',
    'Product SKU',
    'Product Name',
    'Unit Price (' . CURRENCY . ')',
    'Quantity',
    'Item Total (' . CURRENCY . ')',
    'Order Subtotal (' . CURRENCY . ')',
    'Shipping (' . CURRENCY . ')',
    'Tax (' . CURRENCY . ')',
    'Order Total (' . CURRENCY . ')',
    'Payment Method',
    'Payment Status',
    'Order Status',
    'Order Date'
];

fputcsv($output, $headers);

// Track current order to avoid repeating order data
$currentOrder = null;

foreach ($orderData as $row) {
    // If this is a new order, add a separator
    if ($currentOrder !== $row['order_number']) {
        if ($currentOrder !== null) {
            fputcsv($output, []); // Empty row between orders
        }
        $currentOrder = $row['order_number'];
    }

    $csvRow = [
        $row['order_number'],
        $row['customer_name'],
        $row['customer_email'],
        $row['customer_mobile'],
        $row['product_sku'] ?: 'N/A',
        $row['product_name'],
        number_format($row['product_price'], 2),
        $row['quantity'],
        number_format($row['item_total'], 2),
        number_format($row['subtotal'], 2),
        number_format($row['shipping'], 2),
        number_format($row['tax'], 2),
        number_format($row['total_amount'], 2),
        formatPaymentMethod($row['payment_method']),
        ucfirst($row['payment_status']),
        ucfirst($row['order_status']),
        $row['created_at']
    ];

    fputcsv($output, $csvRow);
}

// Add summary
fputcsv($output, []); // Empty row
fputcsv($output, ['EXPORT SUMMARY']);
fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);

// Calculate summary statistics
$uniqueOrders = count(array_unique(array_column($orderData, 'order_number')));
$totalItems = count($orderData);
$totalRevenue = array_sum(array_column($orderData, 'total_amount')) / max($uniqueOrders, 1); // Prevent division by zero

fputcsv($output, ['Total Orders', $uniqueOrders]);
fputcsv($output, ['Total Order Items', $totalItems]);
fputcsv($output, ['Average Revenue per Order', CURRENCY . ' ' . number_format($totalRevenue, 2)]);

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