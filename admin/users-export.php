<?php
require_once '../config/database.php';
require_once '../config/constants.php';

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Check admin permissions
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] !== 'admin' && !$_SESSION['is_super_admin'])) {
    header('Location: index.php');
    exit;
}

// Get filter parameters from GET or POST
$search = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';
$status = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';
$city = isset($_REQUEST['city']) ? $_REQUEST['city'] : '';
$country = isset($_REQUEST['country']) ? $_REQUEST['country'] : '';
$date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : 'filtered'; // filtered, all

// Build WHERE conditions (same as users.php)
$whereConditions = ["u.role = 'customer'"];
$params = [];

// If export_type is 'all', ignore filters (export all customers)
if ($export_type !== 'all') {
    if (!empty($search)) {
        $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ? OR u.address LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if (!empty($status) && $status !== 'all') {
        $whereConditions[] = "u.status = ?";
        $params[] = $status;
    }

    if (!empty($city) && $city !== 'all') {
        $whereConditions[] = "u.city = ?";
        $params[] = $city;
    }

    if (!empty($country) && $country !== 'all') {
        $whereConditions[] = "u.country = ?";
        $params[] = $country;
    }

    if (!empty($date_from)) {
        $whereConditions[] = "DATE(u.created_at) >= ?";
        $params[] = $date_from;
    }

    if (!empty($date_to)) {
        $whereConditions[] = "DATE(u.created_at) <= ?";
        $params[] = $date_to;
    }
}

$whereClause = implode(' AND ', $whereConditions);

// Get users data with comprehensive statistics
$sql = "SELECT
            u.id,
            u.name,
            u.email,
            u.mobile,
            u.address,
            u.city,
            u.country,
            u.theme_preference,
            u.status,
            u.created_at,
            u.updated_at,
            u.last_login,
            u.remember_token,
            u.token_expiry,
            -- Order statistics
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND order_status = 'pending') as pending_orders,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND order_status = 'processing') as processing_orders,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND order_status = 'delivered') as delivered_orders,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND order_status = 'cancelled') as cancelled_orders,
            -- Payment statistics
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND payment_status = 'paid') as paid_orders,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND payment_status = 'pending') as pending_payments,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND payment_status = 'failed') as failed_payments,
            -- Financial statistics
            (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND payment_status = 'paid') as total_spent,
            (SELECT AVG(total_amount) FROM orders WHERE user_id = u.id AND payment_status = 'paid') as avg_order_value,
            (SELECT MAX(total_amount) FROM orders WHERE user_id = u.id AND payment_status = 'paid') as largest_order,
            (SELECT MIN(total_amount) FROM orders WHERE user_id = u.id AND payment_status = 'paid') as smallest_order,
            -- Date statistics
            (SELECT MIN(created_at) FROM orders WHERE user_id = u.id) as first_order_date,
            (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) as last_order_date,
            -- Cart activity
            (SELECT COUNT(*) FROM cart WHERE user_id = u.id) as cart_items_count,
            (SELECT SUM(price * quantity) FROM cart WHERE user_id = u.id) as cart_total_value,
            -- Days since registration
            DATEDIFF(CURDATE(), u.created_at) as days_since_registration,
            -- Days since last login
            CASE
                WHEN u.last_login IS NOT NULL THEN DATEDIFF(CURDATE(), u.last_login)
                ELSE NULL
            END as days_since_last_login,
            -- Customer segment based on spending
            CASE
                WHEN (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND payment_status = 'paid') > 50000 THEN 'VIP'
                WHEN (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND payment_status = 'paid') > 10000 THEN 'Regular'
                WHEN (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND payment_status = 'paid') > 0 THEN 'Occasional'
                ELSE 'New/Non-Spending'
            END as customer_segment
        FROM users u
        WHERE $whereClause
        ORDER BY u.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
$filename = 'customers_export_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 compatibility with Excel
fputs($output, "\xEF\xBB\xBF");

// Comprehensive CSV headers
$headers = [
    // Basic Information
    'Customer ID',
    'Full Name',
    'Email Address',
    'Mobile Number',
    'Address',
    'City',
    'Country',
    'Theme Preference',

    // Account Status
    'Account Status',
    'Registration Date',
    'Last Updated',
    'Last Login Date',
    'Days Since Registration',
    'Days Since Last Login',

    // Order Statistics
    'Total Orders',
    'Pending Orders',
    'Processing Orders',
    'Delivered Orders',
    'Cancelled Orders',

    // Payment Statistics
    'Paid Orders',
    'Pending Payments',
    'Failed Payments',

    // Financial Statistics
    'Total Amount Spent (' . CURRENCY . ')',
    'Average Order Value (' . CURRENCY . ')',
    'Largest Order (' . CURRENCY . ')',
    'Smallest Order (' . CURRENCY . ')',

    // Order Dates
    'First Order Date',
    'Last Order Date',

    // Cart Activity
    'Items in Cart',
    'Cart Total Value (' . CURRENCY . ')',

    // Customer Segmentation
    'Customer Segment'
];

fputcsv($output, $headers);

// Add data rows
foreach ($users as $user) {
    $row = [
        // Basic Information
        $user['id'],
        $user['name'],
        $user['email'],
        $user['mobile'],
        $user['address'] ?: 'Not Provided',
        $user['city'] ?: 'Not Provided',
        $user['country'] ?: 'Not Provided',
        ucfirst($user['theme_preference']),

        // Account Status
        ucfirst($user['status']),
        $user['created_at'],
        $user['updated_at'] ?: 'Never',
        $user['last_login'] ?: 'Never',
        $user['days_since_registration'],
        $user['days_since_last_login'] ?: 'Never Logged In',

        // Order Statistics
        $user['total_orders'],
        $user['pending_orders'],
        $user['processing_orders'],
        $user['delivered_orders'],
        $user['cancelled_orders'],

        // Payment Statistics
        $user['paid_orders'],
        $user['pending_payments'],
        $user['failed_payments'],

        // Financial Statistics
        number_format($user['total_spent'] ?? 0, 2),
        number_format($user['avg_order_value'] ?? 0, 2),
        number_format($user['largest_order'] ?? 0, 2),
        number_format($user['smallest_order'] ?? 0, 2),

        // Order Dates
        $user['first_order_date'] ?: 'No Orders',
        $user['last_order_date'] ?: 'No Orders',

        // Cart Activity
        $user['cart_items_count'],
        number_format($user['cart_total_value'] ?? 0, 2),

        // Customer Segmentation
        $user['customer_segment']
    ];

    fputcsv($output, $row);
}

// Add summary section
fputcsv($output, []); // Empty row
fputcsv($output, ['EXPORT SUMMARY']);
fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
fputcsv($output, ['Total Customers Exported', count($users)]);
fputcsv($output, ['Active Customers', count(array_filter($users, function($user) { return $user['status'] === 'active'; }))]);
fputcsv($output, ['Inactive Customers', count(array_filter($users, function($user) { return $user['status'] === 'inactive'; }))]);
fputcsv($output, ['Customers with Orders', count(array_filter($users, function($user) { return $user['total_orders'] > 0; }))]);
fputcsv($output, ['New Customers (No Orders)', count(array_filter($users, function($user) { return $user['total_orders'] == 0; }))]);

// Financial Summary
$totalRevenue = array_sum(array_column($users, 'total_spent'));
$avgCustomerValue = count($users) > 0 ? $totalRevenue / count($users) : 0;
$payingCustomers = count(array_filter($users, function($user) { return $user['total_spent'] > 0; }));

fputcsv($output, []); // Empty row
fputcsv($output, ['FINANCIAL SUMMARY']);
fputcsv($output, ['Total Revenue (' . CURRENCY . ')', number_format($totalRevenue, 2)]);
fputcsv($output, ['Average Customer Value (' . CURRENCY . ')', number_format($avgCustomerValue, 2)]);
fputcsv($output, ['Paying Customers', $payingCustomers]);
fputcsv($output, ['Conversion Rate', count($users) > 0 ? round(($payingCustomers / count($users)) * 100, 2) . '%' : '0%']);

// Customer Segmentation Summary
$segments = array_count_values(array_column($users, 'customer_segment'));
fputcsv($output, []); // Empty row
fputcsv($output, ['CUSTOMER SEGMENTS']);
foreach ($segments as $segment => $count) {
    fputcsv($output, [$segment . ' Customers', $count]);
}

// Add filter information (only if filtered export)
if ($export_type !== 'all') {
    fputcsv($output, []); // Empty row
    fputcsv($output, ['FILTER CRITERIA']);
    if (!empty($search)) {
        fputcsv($output, ['Search Term', $search]);
    }
    if (!empty($status) && $status !== 'all') {
        fputcsv($output, ['Status Filter', ucfirst($status)]);
    }
    if (!empty($city) && $city !== 'all') {
        fputcsv($output, ['City Filter', $city]);
    }
    if (!empty($country) && $country !== 'all') {
        fputcsv($output, ['Country Filter', $country]);
    }
    if (!empty($date_from)) {
        fputcsv($output, ['Date From', $date_from]);
    }
    if (!empty($date_to)) {
        fputcsv($output, ['Date To', $date_to]);
    }
}

// Geographic Summary
fputcsv($output, []); // Empty row
fputcsv($output, ['GEOGRAPHIC DISTRIBUTION']);
$cities = array_count_values(array_column($users, 'city'));
$countries = array_count_values(array_column($users, 'country'));

fputcsv($output, ['TOP CITIES']);
$topCities = array_slice($cities, 0, 10, true);
foreach ($topCities as $city => $count) {
    if ($city && $city !== 'Not Provided') {
        fputcsv($output, [$city, $count]);
    }
}

fputcsv($output, []); // Empty row
fputcsv($output, ['TOP COUNTRIES']);
$topCountries = array_slice($countries, 0, 10, true);
foreach ($topCountries as $country => $count) {
    if ($country && $country !== 'Not Provided') {
        fputcsv($output, [$country, $count]);
    }
}

// Close output stream
fclose($output);
exit;
?>