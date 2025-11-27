<?php
require_once '../config/constants.php';
require_once '../config/database.php';

// Check admin permissions BEFORE any output
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] !== 'admin' && !$_SESSION['is_super_admin'])) {
    header('Location: dashboard.php');
    exit;
}

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Handle Status Inquiry FIRST - before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status_inquiry'])) {
    $transactionRef = $_POST['transaction_ref'] ?? '';
    $order_id = $_POST['order_id'] ?? '';

    if (!empty($transactionRef)) {
        try {
            require_once '../api/jazzcash-payment.php';
            $jazzcash = new JazzCashPayment();

            $inquiryResult = $jazzcash->performStatusInquiry($transactionRef);

            if ($inquiryResult['success']) {
                $_SESSION['success_message'] = "Status Inquiry Successful: " . $inquiryResult['status'];
            } else {
                $_SESSION['error_message'] = "Status Inquiry Failed: " . $inquiryResult['response_message'];
            }

            // Store inquiry result in session
            $_SESSION['status_inquiry_result'] = $inquiryResult;

            // Redirect back to the same order view
            if (!empty($order_id)) {
                header('Location: orders.php?view_order=1&order_id=' . $order_id);
                exit;
            } else {
                header('Location: orders.php');
                exit;
            }

        } catch (Exception $e) {
            $_SESSION['error_message'] = "Status Inquiry Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Transaction reference is required for status inquiry.";
    }
}

// Now include the admin header (after all potential redirects)
require_once 'includes/admin-header.php';

// Handle other POST actions (update, delete) after header inclusion
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id = intval($_POST['order_id']);
    $order_status = $_POST['order_status'];
    $payment_status = $_POST['payment_status'];
    $notes = trim($_POST['notes'] ?? '');

    try {
        $sql = "UPDATE orders SET order_status = ?, payment_status = ?, notes = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$order_status, $payment_status, $notes, $order_id]);

        $success_message = "Order status updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating order: " . $e->getMessage();
    }
}

// Delete order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $order_id = intval($_POST['order_id']);

    try {
        $conn->beginTransaction();

        // Delete related records first
        $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);

        $stmt = $conn->prepare("DELETE FROM jazzcash_transactions WHERE order_id = ?");
        $stmt->execute([$order_id]);

        $stmt = $conn->prepare("DELETE FROM card_payments WHERE order_id = ?");
        $stmt->execute([$order_id]);

        // Delete the order
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);

        $conn->commit();
        $success_message = "Order deleted successfully!";
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = "Error deleting order: " . $e->getMessage();
    }
}

// Display status inquiry result if available
$statusInquiryResult = $_SESSION['status_inquiry_result'] ?? null;
if ($statusInquiryResult && !isset($_POST['status_inquiry'])) {
    // Only clear if not from current POST to avoid flash
    unset($_SESSION['status_inquiry_result']);
}

// Handle order details view
$order_details = null;
$order_items = [];
$payment_details = null;

if (isset($_GET['view_order']) && isset($_GET['order_id'])) {
    $view_order_id = intval($_GET['order_id']);

    try {
        // Get complete order details
        $order_sql = "SELECT o.*, u.name as user_name
                     FROM orders o
                     LEFT JOIN users u ON o.user_id = u.id
                     WHERE o.id = ?";
        $order_stmt = $conn->prepare($order_sql);
        $order_stmt->execute([$view_order_id]);
        $order_details = $order_stmt->fetch(PDO::FETCH_ASSOC);

        if ($order_details) {
            // Get order items
            $items_sql = "SELECT oi.*, p.slug as product_slug, p.image as product_image
                         FROM order_items oi
                         LEFT JOIN products p ON oi.product_id = p.id
                         WHERE oi.order_id = ?";
            $items_stmt = $conn->prepare($items_sql);
            $items_stmt->execute([$view_order_id]);
            $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get payment details based on payment method
            if ($order_details['payment_method'] === 'jazzcash_mobile') {
                $payment_sql = "SELECT * FROM jazzcash_transactions WHERE order_id = ?";
                $payment_stmt = $conn->prepare($payment_sql);
                $payment_stmt->execute([$view_order_id]);
                $payment_details = $payment_stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($order_details['payment_method'] === 'jazzcash_card') {
                $payment_sql = "SELECT * FROM card_payments WHERE order_id = ?";
                $payment_stmt = $conn->prepare($payment_sql);
                $payment_stmt->execute([$view_order_id]);
                $payment_details = $payment_stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $payment_details = null;
            }
        } else {
            $error_message = "Order not found!";
        }
    } catch (PDOException $e) {
        $error_message = "Error loading order details: " . $e->getMessage();
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$order_status = isset($_GET['order_status']) ? $_GET['order_status'] : '';
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

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

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM orders o WHERE $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalOrders = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalOrders / $limit);

// Get orders with pagination
$sql = "SELECT o.*,
               u.name as user_name,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE $whereClause
        ORDER BY o.created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order statistics
$stats_stmt = $conn->query("
    SELECT
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
        SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
        SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
        SUM(total_amount) as total_revenue
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get today's orders and revenue
$today_stmt = $conn->query("
    SELECT
        COUNT(*) as today_orders,
        SUM(total_amount) as today_revenue
    FROM orders
    WHERE DATE(created_at) = CURDATE()
");
$today_stats = $today_stmt->fetch(PDO::FETCH_ASSOC);

// Function to display order details
function displayOrderDetailsHTML($order, $items, $payment, $statusInquiryResult = null) {
    // Format currency
    $formatCurrency = function($amount) {
        return CURRENCY . ' ' . number_format($amount, 2);
    };

    // Format date
    $formatDate = function($dateString) {
        return date('M j, Y g:i A', strtotime($dateString));
    };

    // Get status badge
    $getStatusBadge = function($status, $type = 'order') {
        $badges = [
            'order' => [
                'pending' => 'bg-warning',
                'processing' => 'bg-info',
                'shipped' => 'bg-primary',
                'delivered' => 'bg-success',
                'cancelled' => 'bg-danger'
            ],
            'payment' => [
                'pending' => 'bg-warning',
                'paid' => 'bg-success',
                'failed' => 'bg-danger',
                'refunded' => 'bg-info'
            ]
        ];
        return $badges[$type][$status] ?? 'bg-secondary';
    };

    // Get payment method name
    $getPaymentMethodName = function($method) {
        $methods = [
            'jazzcash_mobile' => 'JazzCash Mobile',
            'jazzcash_card' => 'JazzCash Card',
            'cod' => 'Cash on Delivery',
            'bank' => 'Bank Transfer'
        ];
        return $methods[$method] ?? $method;
    };

    ob_start();
    ?>
    <div class="row">
        <!-- Order Summary -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-receipt me-2"></i>Order Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-5"><strong>Order Number:</strong></div>
                        <div class="col-7"><?php echo htmlspecialchars($order['order_number']); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Order Date:</strong></div>
                        <div class="col-7"><?php echo $formatDate($order['created_at']); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Order Status:</strong></div>
                        <div class="col-7">
                            <span class="badge <?php echo $getStatusBadge($order['order_status']); ?>">
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Payment Status:</strong></div>
                        <div class="col-7">
                            <span class="badge <?php echo $getStatusBadge($order['payment_status'], 'payment'); ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Payment Method:</strong></div>
                        <div class="col-7"><?php echo $getPaymentMethodName($order['payment_method']); ?></div>
                    </div>
                    <?php if ($order['jazzcash_transaction_id']): ?>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Transaction ID:</strong></div>
                        <div class="col-7"><code><?php echo htmlspecialchars($order['jazzcash_transaction_id']); ?></code></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['notes']): ?>
                    <div class="row">
                        <div class="col-12">
                            <strong>Admin Notes:</strong>
                            <p class="mt-1 mb-0 text-muted"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Customer Information -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-user me-2"></i>Customer Information</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-5"><strong>Name:</strong></div>
                        <div class="col-7"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Email:</strong></div>
                        <div class="col-7"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Mobile:</strong></div>
                        <div class="col-7"><?php echo htmlspecialchars($order['customer_mobile']); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Address:</strong></div>
                        <div class="col-7"><?php echo nl2br(htmlspecialchars($order['customer_address'])); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>City:</strong></div>
                        <div class="col-7"><?php echo htmlspecialchars($order['customer_city']); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Country:</strong></div>
                        <div class="col-7"><?php echo htmlspecialchars($order['customer_country']); ?></div>
                    </div>
                    <?php if ($order['user_id']): ?>
                    <div class="row">
                        <div class="col-12">
                            <small class="text-muted">
                                <i class="fas fa-user-check me-1"></i>Registered User: <?php echo htmlspecialchars($order['user_name']); ?>
                            </small>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <div class="col-12">
                            <small class="text-muted">
                                <i class="fas fa-user-clock me-1"></i>Guest Checkout
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Items -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Order Items (<?php echo count($items); ?>)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if ($item['product_image']): ?>
                                    <div class="flex-shrink-0 me-3">
                                        <img src="../<?php echo htmlspecialchars($item['product_image']); ?>"
                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                             class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                        <?php if ($item['product_slug']): ?>
                                        <small class="text-muted">
                                            <a href="../product-<?php echo $item['product_id']; ?>" target="_blank">
                                                View Product
                                            </a>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $formatCurrency($item['product_price']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><strong><?php echo $formatCurrency($item['total_price']); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Order Totals -->
    <div class="row">
        <div class="col-md-6 offset-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-calculator me-2"></i>Order Totals</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-6"><strong>Subtotal:</strong></div>
                        <div class="col-6 text-end"><?php echo $formatCurrency($order['subtotal']); ?></div>
                    </div>
                    <?php if ($order['shipping'] > 0): ?>
                    <div class="row mb-2">
                        <div class="col-6"><strong>Shipping:</strong></div>
                        <div class="col-6 text-end">+ <?php echo $formatCurrency($order['shipping']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['tax'] > 0): ?>
                    <div class="row mb-2">
                        <div class="col-6"><strong>Tax:</strong></div>
                        <div class="col-6 text-end">+ <?php echo $formatCurrency($order['tax']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="row mb-0 pt-2 border-top">
                        <div class="col-6"><h5 class="mb-0">Total Amount:</h5></div>
                        <div class="col-6 text-end"><h5 class="mb-0 text-gold"><?php echo $formatCurrency($order['total_amount']); ?></h5></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Details -->
    <?php if ($payment): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Details</h6>
            <?php if (!empty($payment['pp_TxnRefNo'])): ?>
            <form method="POST" action="orders.php" class="d-inline">
                <input type="hidden" name="status_inquiry" value="1">
                <input type="hidden" name="transaction_ref" value="<?php echo htmlspecialchars($payment['pp_TxnRefNo']); ?>">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">

                <img
                                                        src="<?php echo SITE_URL; ?>/assets/images/jazz-logo.png"
                                                        style="width: 42px;border-radius: 10px;"
                                                    />
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-sync-alt me-1"></i>Status Inquiry
                </button>
            </form>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="row">
                <?php if ($order['payment_method'] === 'jazzcash_mobile'): ?>
                <div class="col-md-4">
                    <strong>Transaction Ref:</strong><br>
                    <code><?php echo htmlspecialchars($payment['pp_TxnRefNo']); ?></code>
                </div>
                <div class="col-md-4">
                    <strong>Amount:</strong><br>
                    <?php echo $formatCurrency($payment['pp_Amount']); ?>
                </div>
                <div class="col-md-4">
                    <strong>Mobile Number:</strong><br>
                    <?php echo htmlspecialchars($payment['pp_MobileNumber'] ?? 'N/A'); ?>
                </div>
                <?php elseif ($order['payment_method'] === 'jazzcash_card'): ?>
                <div class="col-md-4">
                    <strong>Transaction Ref:</strong><br>
                    <code><?php echo htmlspecialchars($payment['pp_TxnRefNo']); ?></code>
                </div>
                <div class="col-md-4">
                    <strong>Amount:</strong><br>
                    <?php echo $formatCurrency($payment['pp_Amount']); ?>
                </div>
                <div class="col-md-4">
                    <strong>Card Holder:</strong><br>
                    <?php echo htmlspecialchars($payment['pp_CardHolderName'] ?? 'N/A'); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Status Inquiry Results -->
            <?php if ($statusInquiryResult):?>
            <div class="mt-3">
                <div class="alert alert-<?php echo $statusInquiryResult['success'] ? 'success' : 'danger'; ?>">
                    <h6 class="alert-heading">
                        <i class="fas fa-<?php echo $statusInquiryResult['success'] ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        Status Inquiry Result
                    </h6>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <strong>Response Code:</strong> <?php echo $statusInquiryResult['response_code']; ?><br>
                            <strong>Status:</strong> <?php echo $statusInquiryResult['status']; ?><br>
                            <strong>Message:</strong> <?php echo $statusInquiryResult['response_message']; ?>
                        </div>
                        <?php if ($statusInquiryResult['success']): ?>
                        <div class="col-md-6">
                            <?php if (!empty($statusInquiryResult['amount'])): ?>
                            <strong>Amount:</strong> <?php echo CURRENCY . ' ' . number_format($statusInquiryResult['amount'], 2); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($statusInquiryResult['transaction_date'])): ?>
                            <strong>Transaction Date:</strong> <?php echo date('M j, Y g:i A', strtotime($statusInquiryResult['transaction_date'])); ?><br>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="mt-3">
              <strong>Status Inquiry API response:</strong><br>
                <?php

                    echo '<pre>',print_r($statusInquiryResult['raw_response'],1),'</pre>';

                 ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 brand-font mb-1">Order Management</h1>
            <p class="text-muted mb-0">Manage customer orders and track order status</p>
        </div>
        <div>
            <div class="btn-group">
                <button type="button" class="btn btn-gold dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download me-2"></i>Export Orders
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="orders-export.php?<?php echo http_build_query($_GET); ?>">
                        <i class="fas fa-file-csv me-2"></i>Export Summary CSV
                    </a></li>
                    <li><a class="dropdown-item" href="orders-export-detailed.php?<?php echo http_build_query($_GET); ?>">
                        <i class="fas fa-file-alt me-2"></i>Export Detailed CSV
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="orders-export.php?export_type=all">
                        <i class="fas fa-database me-2"></i>Export All Orders
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-primary text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['total_orders']; ?></h4>
                    <small>Total Orders (30d)</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-warning text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['pending_orders']; ?></h4>
                    <small>Pending</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-info text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['processing_orders']; ?></h4>
                    <small>Processing</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-success text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['delivered_orders']; ?></h4>
                    <small>Delivered</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-danger text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['pending_payments']; ?></h4>
                    <small>Pending Payments</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-gold text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo CURRENCY . ' ' . number_format($stats['total_revenue'] ?? 0); ?></h4>
                    <small>Revenue (30d)</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Order #, Customer, Email, Phone...">
                </div>
                <div class="col-md-2">
                    <label for="order_status" class="form-label">Order Status</label>
                    <select class="form-select" id="order_status" name="order_status">
                        <option value="all">All Status</option>
                        <option value="pending" <?php echo $order_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo $order_status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="shipped" <?php echo $order_status === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $order_status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $order_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="payment_status" class="form-label">Payment Status</label>
                    <select class="form-select" id="payment_status" name="payment_status">
                        <option value="all">All Payments</option>
                        <option value="pending" <?php echo $payment_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $payment_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="failed" <?php echo $payment_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="refunded" <?php echo $payment_status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="payment_method" class="form-label">Payment Method</label>
                    <select class="form-select" id="payment_method" name="payment_method">
                        <option value="all">All Methods</option>
                        <option value="jazzcash_mobile" <?php echo $payment_method === 'jazzcash_mobile' ? 'selected' : ''; ?>>JazzCash Mobile</option>
                        <option value="jazzcash_card" <?php echo $payment_method === 'jazzcash_card' ? 'selected' : ''; ?>>JazzCash Card</option>
                        <option value="cod" <?php echo $payment_method === 'cod' ? 'selected' : ''; ?>>Cash on Delivery</option>
                        <option value="bank" <?php echo $payment_method === 'bank' ? 'selected' : ''; ?>>Bank Transfer</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from"
                                   value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-6">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to"
                                   value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-gold w-100">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </form>

            <!-- Active Filters -->
            <?php if (!empty($search) || !empty($order_status) || !empty($payment_status) || !empty($payment_method) || !empty($date_from) || !empty($date_to)): ?>
                <div class="mt-3">
                    <small class="text-muted">Active filters:</small>
                    <?php if (!empty($search)): ?>
                        <span class="badge bg-primary me-2">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($order_status) && $order_status !== 'all'): ?>
                        <span class="badge bg-info me-2">
                            Status: <?php echo ucfirst($order_status); ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['order_status' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($payment_status) && $payment_status !== 'all'): ?>
                        <span class="badge bg-warning me-2">
                            Payment: <?php echo ucfirst($payment_status); ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['payment_status' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <a href="orders.php" class="btn btn-sm btn-outline-secondary">Clear All</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Items</th>
                            <th>Payment Method</th>
                            <th>Payment Status</th>
                            <th>Order Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                        <?php if ($order['user_id']): ?>
                                            <small class="text-muted d-block">Registered User</small>
                                        <?php else: ?>
                                            <small class="text-muted d-block">Guest Checkout</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['customer_mobile']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-gold"><?php echo CURRENCY . ' ' . number_format($order['total_amount']); ?></strong>
                                        <div class="text-muted small">
                                            Sub: <?php echo CURRENCY . ' ' . number_format($order['subtotal']); ?>
                                        </div>
                                        <?php if ($order['shipping'] > 0): ?>
                                            <div class="text-muted small">
                                                Shipping: +<?php echo CURRENCY . ' ' . number_format($order['shipping']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $order['item_count']; ?> items</span>
                                    </td>
                                    <td>
                                        <?php
                                        $payment_badges = [
                                            'jazzcash_mobile' => ['bg-info', 'JazzCash Mobile'],
                                            'jazzcash_card' => ['bg-warning', 'JazzCash Card'],
                                            'cod' => ['bg-secondary', 'Cash on Delivery'],
                                            'bank' => ['bg-dark', 'Bank Transfer']
                                        ];
                                        $badge = $payment_badges[$order['payment_method']] ?? ['bg-secondary', 'Unknown'];
                                        ?>
                                        <span class="badge <?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $payment_status_badges = [
                                            'pending' => ['bg-warning', 'Pending'],
                                            'paid' => ['bg-success', 'Paid'],
                                            'failed' => ['bg-danger', 'Failed'],
                                            'refunded' => ['bg-info', 'Refunded']
                                        ];
                                        $status_badge = $payment_status_badges[$order['payment_status']] ?? ['bg-secondary', 'Unknown'];
                                        ?>
                                        <span class="badge <?php echo $status_badge[0]; ?>"><?php echo $status_badge[1]; ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $order_status_badges = [
                                            'pending' => ['bg-warning', 'Pending'],
                                            'processing' => ['bg-info', 'Processing'],
                                            'shipped' => ['bg-primary', 'Shipped'],
                                            'delivered' => ['bg-success', 'Delivered'],
                                            'cancelled' => ['bg-danger', 'Cancelled']
                                        ];
                                        $order_badge = $order_status_badges[$order['order_status']] ?? ['bg-secondary', 'Unknown'];
                                        ?>
                                        <span class="badge <?php echo $order_badge[0]; ?>"><?php echo $order_badge[1]; ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                            <br>
                                            <small><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button"
                                                    class="btn btn-outline-secondary view-order-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#viewOrderModal"
                                                    data-order-id="<?php echo $order['id']; ?>"
                                                    data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button"
                                                    class="btn btn-outline-primary edit-order-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editOrderModal"
                                                    data-order-id="<?php echo $order['id']; ?>"
                                                    data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>"
                                                    data-customer-name="<?php echo htmlspecialchars($order['customer_name']); ?>"
                                                    data-order-status="<?php echo $order['order_status']; ?>"
                                                    data-payment-status="<?php echo $order['payment_status']; ?>"
                                                    data-notes="<?php echo htmlspecialchars($order['notes'] ?? ''); ?>"
                                                    title="Update Status">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button"
                                                    class="btn btn-outline-danger delete-order-btn"
                                                    data-order-id="<?php echo $order['id']; ?>"
                                                    data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>"
                                                    data-customer-name="<?php echo htmlspecialchars($order['customer_name']); ?>"
                                                    title="Delete Order">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No orders found</h5>
                                    <p class="text-muted mb-3">
                                        <?php if (!empty($search) || !empty($order_status) || !empty($payment_status)): ?>
                                            Try adjusting your search criteria
                                        <?php else: ?>
                                            No orders have been placed yet
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="p-3 border-top">
                    <nav aria-label="Orders pagination">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left me-1"></i>Previous
                                </a>
                            </li>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    Next<i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Order Modal -->
<div class="modal fade" id="viewOrderModal" tabindex="-1" aria-labelledby="viewOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewOrderModalLabel">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <?php if ($order_details): ?>
                    <?php echo displayOrderDetailsHTML($order_details, $order_items, $payment_details, $statusInquiryResult); ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <div class="spinner-border text-gold" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading order details...</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-gold" onclick="printOrder()">
                    <i class="fas fa-print me-2"></i>Print Invoice
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Order Modal -->
<div class="modal fade" id="editOrderModal" tabindex="-1" aria-labelledby="editOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editOrderModalLabel">Update Order Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="editOrderForm">
                <div class="modal-body">
                    <input type="hidden" name="update_order_status" value="1">
                    <input type="hidden" name="order_id" id="edit_order_id">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_order_status" class="form-label">Order Status</label>
                                <select class="form-select" id="edit_order_status" name="order_status" required>
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_payment_status" class="form-label">Payment Status</label>
                                <select class="form-select" id="edit_payment_status" name="payment_status" required>
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="failed">Failed</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">Admin Notes</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="3" placeholder="Add any notes about this order..."></textarea>
                    </div>

                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="card-title">Order Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">Order #:</small>
                                    <div id="edit_order_number_display" class="fw-bold">-</div>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">Customer:</small>
                                    <div id="edit_customer_name_display" class="fw-bold">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Order Modal -->
<div class="modal fade" id="deleteOrderModal" tabindex="-1" aria-labelledby="deleteOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteOrderModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Order Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All order data including order items and payment records will be permanently deleted.
                </div>
                <p class="mb-2">Are you sure you want to delete the following order?</p>
                <div class="card border-danger mb-3">
                    <div class="card-body">
                        <h6 class="card-title text-danger" id="deleteOrderNumber"></h6>
                        <p class="card-text mb-1" id="deleteCustomerName"></p>
                        <small class="text-muted">Order ID: <span id="deleteOrderId"></span></small>
                    </div>
                </div>
                <form id="deleteOrderForm" method="POST" action="orders.php">
                    <input type="hidden" name="delete_order" value="1">
                    <input type="hidden" name="order_id" id="deleteOrderIdInput">
                    <!-- Preserve current filters and pagination -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if ($key !== 'action' && $key !== 'id'): ?>
                            <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="submit" form="deleteOrderForm" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Delete Order
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border: 1px solid var(--admin-border);
}

.text-gold {
    color: var(--primary-color) !important;
}

.brand-font {
/*     font-family: 'Playfair Display', serif; */
}

.btn-gold {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.btn-gold:hover {
    background-color: var(--accent-color);
    border-color: var(--accent-color);
}

.bg-gold {
    background-color: var(--primary-color) !important;
}

.table-hover tbody tr:hover {
    background-color: rgba(212, 175, 55, 0.05);
}

.badge {
    font-size: 0.75em;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }

    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
    }

    .col-xl-2 {
        margin-bottom: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit filters on some changes
    const autoSubmitFilters = ['order_status', 'payment_status', 'payment_method'];
    autoSubmitFilters.forEach(filter => {
        const element = document.getElementById(filter);
        if (element) {
            element.addEventListener('change', function() {
                this.form.submit();
            });
        }
    });

    // Quick search with debounce
    let searchTimeout;
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    }

    // View order functionality
    const viewOrderBtns = document.querySelectorAll('.view-order-btn');
    viewOrderBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            const orderNumber = this.getAttribute('data-order-number');
            loadOrderDetails(orderId, orderNumber);
        });
    });

    // Edit order functionality
    const editOrderBtns = document.querySelectorAll('.edit-order-btn');
    editOrderBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            const orderNumber = this.getAttribute('data-order-number');
            const customerName = this.getAttribute('data-customer-name');
            const orderStatus = this.getAttribute('data-order-status');
            const paymentStatus = this.getAttribute('data-payment-status');
            const notes = this.getAttribute('data-notes');

            loadOrderForEdit(orderId, orderNumber, customerName, orderStatus, paymentStatus, notes);
        });
    });

    // Delete order modal functionality
    const deleteOrderModal = new bootstrap.Modal(document.getElementById('deleteOrderModal'));
    const deleteOrderBtns = document.querySelectorAll('.delete-order-btn');

    deleteOrderBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            const orderNumber = this.getAttribute('data-order-number');
            const customerName = this.getAttribute('data-customer-name');

            // Set modal content
            document.getElementById('deleteOrderNumber').textContent = `Order #${orderNumber}`;
            document.getElementById('deleteCustomerName').textContent = `Customer: ${customerName}`;
            document.getElementById('deleteOrderId').textContent = orderId;
            document.getElementById('deleteOrderIdInput').value = orderId;

            // Show modal
            deleteOrderModal.show();
        });
    });

    // Handle modal form submission
    const deleteOrderForm = document.getElementById('deleteOrderForm');
    if (deleteOrderForm) {
        deleteOrderForm.addEventListener('submit', function() {
            // Show loading state on delete button
            const deleteBtn = this.querySelector('button[type="submit"]');
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
            deleteBtn.disabled = true;
        });
    }

    // Status inquiry button loading state
    const statusInquiryBtns = document.querySelectorAll('.status-inquiry-btn');
    statusInquiryBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Checking...';
            this.disabled = true;

            // Re-enable after 5 seconds if still disabled (fallback)
            setTimeout(() => {
                if (this.disabled) {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }
            }, 5000);
        });
    });

    // Reset modals when closed
    const viewOrderModal = document.getElementById('viewOrderModal');
    const editOrderModal = document.getElementById('editOrderModal');

    if (viewOrderModal) {
        viewOrderModal.addEventListener('hidden.bs.modal', function() {
            // Clear the order details when modal is closed
            document.getElementById('orderDetailsContent').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-gold" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading order details...</p>
                </div>
            `;
        });
    }

    if (editOrderModal) {
        editOrderModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('editOrderModalLabel').textContent = 'Update Order Status';
            document.getElementById('edit_order_id').value = '';
            document.getElementById('edit_order_status').value = 'pending';
            document.getElementById('edit_payment_status').value = 'pending';
            document.getElementById('edit_notes').value = '';
            document.getElementById('edit_order_number_display').textContent = '-';
            document.getElementById('edit_customer_name_display').textContent = '-';
        });
    }

    <?php if ($order_details): ?>
    // Auto-show the view modal if order details are loaded
    const viewModal = new bootstrap.Modal(document.getElementById('viewOrderModal'));
    viewModal.show();
    <?php endif; ?>
});

// Load order details by submitting form
function loadOrderDetails(orderId, orderNumber) {
    const form = document.createElement('form');
    form.method = 'GET';
    form.action = 'orders.php';

    // Preserve existing GET parameters
    <?php
    $currentParams = $_GET;
    unset($currentParams['view_order']);
    unset($currentParams['order_id']);
    foreach ($currentParams as $key => $value) {
        if (!empty($value)) {
            echo "const input".htmlspecialchars($key)." = document.createElement('input');";
            echo "input".htmlspecialchars($key).".type = 'hidden';";
            echo "input".htmlspecialchars($key).".name = '".htmlspecialchars($key)."';";
            echo "input".htmlspecialchars($key).".value = '".htmlspecialchars($value)."';";
            echo "form.appendChild(input".htmlspecialchars($key).");";
        }
    }
    ?>

    const viewOrderInput = document.createElement('input');
    viewOrderInput.type = 'hidden';
    viewOrderInput.name = 'view_order';
    viewOrderInput.value = '1';
    form.appendChild(viewOrderInput);

    const orderIdInput = document.createElement('input');
    orderIdInput.type = 'hidden';
    orderIdInput.name = 'order_id';
    orderIdInput.value = orderId;
    form.appendChild(orderIdInput);

    document.body.appendChild(form);
    form.submit();
}

// Load order data for editing
function loadOrderForEdit(orderId, orderNumber, customerName, orderStatus, paymentStatus, notes) {
    document.getElementById('editOrderModalLabel').textContent = `Update Order Status - ${orderNumber}`;
    document.getElementById('edit_order_id').value = orderId;
    document.getElementById('edit_order_status').value = orderStatus;
    document.getElementById('edit_payment_status').value = paymentStatus;
    document.getElementById('edit_notes').value = notes;
    document.getElementById('edit_order_number_display').textContent = orderNumber;
    document.getElementById('edit_customer_name_display').textContent = customerName;
}

// Print order function
function printOrder() {
    const orderContent = document.querySelector('#viewOrderModal .modal-body').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Order Invoice - <?php echo SITE_NAME; ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
            <style>
                body { font-family: Arial, sans-serif; }
                .text-gold { color: #d4af37 !important; }
                @media print {
                    .no-print { display: none !important; }
                    .modal-footer { display: none !important; }
                    .card { border: 1px solid #ddd !important; }
                }
            </style>
        </head>
        <body>
            <div class="container-fluid py-4">
                <div class="text-center mb-4">
                    <h2 class="text-gold"><?php echo SITE_NAME; ?></h2>
                    <h4>Order Invoice</h4>
                </div>
                ${orderContent}
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        // printWindow.close();
    }, 500);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + F to focus on search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.getElementById('search');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>