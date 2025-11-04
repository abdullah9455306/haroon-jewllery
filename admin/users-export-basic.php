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

// Get filter parameters
$search = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';
$status = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';
$city = isset($_REQUEST['city']) ? $_REQUEST['city'] : '';
$country = isset($_REQUEST['country']) ? $_REQUEST['country'] : '';
$date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';

// Build WHERE conditions
$whereConditions = ["u.role = 'customer'"];
$params = [];

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

$whereClause = implode(' AND ', $whereConditions);

// Get basic users data
$sql = "SELECT
            u.id,
            u.name,
            u.email,
            u.mobile,
            u.address,
            u.city,
            u.country,
            u.status,
            u.created_at,
            u.last_login,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
            (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND payment_status = 'paid') as total_spent
        FROM users u
        WHERE $whereClause
        ORDER BY u.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
$filename = 'customers_basic_export_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 compatibility with Excel
fputs($output, "\xEF\xBB\xBF");

// Basic CSV headers
$headers = [
    'Customer ID',
    'Full Name',
    'Email Address',
    'Mobile Number',
    'Address',
    'City',
    'Country',
    'Status',
    'Registration Date',
    'Last Login',
    'Total Orders',
    'Total Spent (' . CURRENCY . ')',
    'Customer Type'
];

fputcsv($output, $headers);

// Add data rows
foreach ($users as $user) {
    $customerType = 'New';
    if ($user['total_spent'] > 50000) {
        $customerType = 'VIP';
    } elseif ($user['total_spent'] > 10000) {
        $customerType = 'Regular';
    } elseif ($user['total_spent'] > 0) {
        $customerType = 'Occasional';
    } elseif ($user['order_count'] > 0) {
        $customerType = 'Non-Paying';
    }

    $row = [
        $user['id'],
        $user['name'],
        $user['email'],
        $user['mobile'],
        $user['address'] ?: 'Not Provided',
        $user['city'] ?: 'Not Provided',
        $user['country'] ?: 'Not Provided',
        ucfirst($user['status']),
        $user['created_at'],
        $user['last_login'] ?: 'Never',
        $user['order_count'],
        number_format($user['total_spent'] ?? 0, 2),
        $customerType
    ];

    fputcsv($output, $row);
}

// Add simple summary
fputcsv($output, []); // Empty row
fputcsv($output, ['SUMMARY']);
fputcsv($output, ['Total Customers', count($users)]);
fputcsv($output, ['Active Customers', count(array_filter($users, function($user) { return $user['status'] === 'active'; }))]);
fputcsv($output, ['Total Revenue', CURRENCY . ' ' . number_format(array_sum(array_column($users, 'total_spent')), 2)]);

fclose($output);
exit;
?>