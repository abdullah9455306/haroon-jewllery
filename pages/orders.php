<?php
require_once '../config/constants.php';
require_once '../includes/header.php';

// Initialize database connection
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$pageTitle = "My Orders";
$user_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE conditions
$whereConditions = ["o.user_id = ?"];
$params = [$user_id];

if (!empty($status_filter)) {
    $whereConditions[] = "o.order_status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $whereConditions[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $whereConditions[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(DISTINCT o.id) as total
             FROM orders o
             $whereClause";
$countStmt = $conn->prepare($countSql);

// Remove limit and offset from count params
$countParams = [$user_id];
if (!empty($status_filter)) {
    $countParams[] = $status_filter;
}
if (!empty($date_from)) {
    $countParams[] = $date_from;
}
if (!empty($date_to)) {
    $countParams[] = $date_to;
}

$countStmt->execute($countParams);
$totalOrders = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalOrders / $limit);

// Get orders with items count
$sql = "SELECT o.*,
               COUNT(oi.id) as item_count,
               SUM(oi.quantity) as total_quantity
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        $whereClause
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?";

// Convert to integers explicitly
$params[] = (int)$limit;
$params[] = (int)$offset;

$stmt = $conn->prepare($sql);

// Bind parameters with explicit types
foreach ($params as $key => $value) {
    $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key + 1, $value, $paramType);
}

$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order statistics
$stats_stmt = $conn->prepare("
    SELECT
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
        SUM(total_amount) as total_spent
    FROM orders
    WHERE user_id = ?
");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-5">
    <div class="row">
        <!-- Sidebar Stats -->
        <div class="col-lg-3 mb-4">
            <!-- Order Statistics -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light border-0">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Order Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Total Orders:</span>
                        <strong class="text-gold"><?php echo $stats['total_orders']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Pending:</span>
                        <strong class="text-warning"><?php echo $stats['pending_orders']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Delivered:</span>
                        <strong class="text-success"><?php echo $stats['delivered_orders']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Total Spent:</span>
                        <strong class="text-gold"><?php echo CURRENCY . ' ' . number_format($stats['total_spent'] ?? 0); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Quick Filters -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Quick Filters</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="?status=" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo empty($status_filter) ? 'active' : ''; ?>">
                            All Orders
                            <span class="badge text-dark rounded-pill"><?php echo $stats['total_orders']; ?></span>
                        </a>
                        <a href="?status=pending" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                            Pending
                            <span class="badge bg-warning rounded-pill"><?php echo $stats['pending_orders']; ?></span>
                        </a>
                        <a href="?status=processing" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $status_filter === 'processing' ? 'active' : ''; ?>">
                            Processing
                            <span class="badge bg-info rounded-pill">
                                <?php
                                $processing_count = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND order_status = 'processing'");
                                $processing_count->execute([$user_id]);
                                echo $processing_count->fetchColumn();
                                ?>
                            </span>
                        </a>
                        <a href="?status=shipped" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $status_filter === 'shipped' ? 'active' : ''; ?>">
                            Shipped
                            <span class="badge bg-primary rounded-pill">
                                <?php
                                $shipped_count = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND order_status = 'shipped'");
                                $shipped_count->execute([$user_id]);
                                echo $shipped_count->fetchColumn();
                                ?>
                            </span>
                        </a>
                        <a href="?status=delivered" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $status_filter === 'delivered' ? 'active' : ''; ?>">
                            Delivered
                            <span class="badge bg-success rounded-pill"><?php echo $stats['delivered_orders']; ?></span>
                        </a>
                        <a href="?status=cancelled" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                            Cancelled
                            <span class="badge bg-danger rounded-pill">
                                <?php
                                $cancelled_count = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND order_status = 'cancelled'");
                                $cancelled_count->execute([$user_id]);
                                echo $cancelled_count->fetchColumn();
                                ?>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 brand-font mb-1">My Orders</h1>
                    <p class="text-muted mb-0">Track and manage your orders</p>
                </div>
                <a href="<?php echo SITE_URL; ?>/products" class="btn btn-gold">
                    <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                </a>
            </div>

            <!-- Filters Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-gold w-100">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                        </div>
                    </form>

                    <!-- Active Filters -->
                    <?php if (!empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                        <div class="mt-3">
                            <small class="text-muted">Active filters:</small>
                            <?php if (!empty($status_filter)): ?>
                                <span class="badge bg-primary me-2">
                                    Status: <?php echo ucfirst($status_filter); ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" class="text-white ms-1">×</a>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($date_from)): ?>
                                <span class="badge bg-info me-2">
                                    From: <?php echo $date_from; ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['date_from' => ''])); ?>" class="text-white ms-1">×</a>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($date_to)): ?>
                                <span class="badge bg-info me-2">
                                    To: <?php echo $date_to; ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['date_to' => ''])); ?>" class="text-white ms-1">×</a>
                                </span>
                            <?php endif; ?>
                            <a href="<?php echo SITE_URL; ?>/orders" class="btn btn-sm btn-outline-secondary">Clear All</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Orders List -->
            <?php if (!empty($orders)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order Details</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-start">
                                                    <div class="flex-shrink-0">
                                                        <i class="fas fa-receipt fa-2x text-gold"></i>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <h6 class="mb-1">#<?php echo $order['order_number']; ?></h6>
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                                                        </small>
                                                        <div class="mt-1">
                                                            <small class="text-muted">
                                                                <i class="fas fa-truck me-1"></i>
                                                                <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo $order['item_count']; ?> items</strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo $order['total_quantity']; ?> total qty</small>
                                                </div>
                                            </td>
                                            <td>
                                                <strong class="text-gold"><?php echo CURRENCY . ' ' . number_format($order['total_amount']); ?></strong>
                                            </td>
                                            <td>
                                                <?php
                                                $status_config = [
                                                    'pending' => ['class' => 'bg-warning', 'icon' => 'fas fa-clock'],
                                                    'processing' => ['class' => 'bg-info', 'icon' => 'fas fa-cog'],
                                                    'shipped' => ['class' => 'bg-primary', 'icon' => 'fas fa-shipping-fast'],
                                                    'delivered' => ['class' => 'bg-success', 'icon' => 'fas fa-check-circle'],
                                                    'cancelled' => ['class' => 'bg-danger', 'icon' => 'fas fa-times-circle']
                                                ];
                                                $status_info = $status_config[$order['order_status']] ?? ['class' => 'bg-secondary', 'icon' => 'fas fa-question'];
                                                ?>
                                                <span class="badge <?php echo $status_info['class']; ?>">
                                                    <i class="<?php echo $status_info['icon']; ?> me-1"></i>
                                                    <?php echo ucfirst($order['order_status']); ?>
                                                </span>
                                                <?php if ($order['order_status'] === 'shipped' && !empty($order['jazzcash_transaction_id'])): ?>
                                                    <div class="mt-1">
                                                        <small class="text-muted">
                                                            <i class="fas fa-receipt me-1"></i>
                                                            Txn: <?php echo substr($order['jazzcash_transaction_id'], 0, 8) . '...'; ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?php echo SITE_URL; ?>/order-details/<?php echo $order['id']; ?>" class="btn btn-gold">
                                                        <i class="fas fa-eye me-1"></i>View
                                                    </a>
                                                    <?php if ($order['order_status'] === 'pending'): ?>
                                                        <button type="button" class="btn btn-outline-danger cancel-order" data-order-id="<?php echo $order['id']; ?>">
                                                            <i class="fas fa-times me-1"></i>Cancel
                                                        </button>
                                                    <?php endif; ?>
                                                   <!-- <?php if ($order['order_status'] === 'delivered'): ?>
                                                        <button type="button" class="btn btn-outline-success">
                                                            <i class="fas fa-redo me-1"></i>Reorder
                                                        </button>
                                                    <?php endif; ?>-->
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Orders pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
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
                <?php endif; ?>

            <?php else: ?>
                <!-- No Orders Found -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-shopping-bag fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted">No orders found</h4>
                        <p class="text-muted mb-4">
                            <?php if (!empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                                Try adjusting your search criteria or
                                <a href="<?php echo SITE_URL; ?>/orders" class="text-gold">clear all filters</a>.
                            <?php else: ?>
                                You haven't placed any orders with us yet.
                            <?php endif; ?>
                        </p>
                        <a href="<?php echo SITE_URL; ?>/products" class="btn btn-gold btn-lg">
                            <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Order Status Guide -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-light border-0">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Order Status Guide</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-2 col-6 mb-3">
                            <div class="status-guide-item">
                                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                <h6>Pending</h6>
                                <small class="text-muted">Order received, awaiting processing</small>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="status-guide-item">
                                <i class="fas fa-cog fa-2x text-info mb-2"></i>
                                <h6>Processing</h6>
                                <small class="text-muted">Order being prepared for shipment</small>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="status-guide-item">
                                <i class="fas fa-shipping-fast fa-2x text-primary mb-2"></i>
                                <h6>Shipped</h6>
                                <small class="text-muted">Order dispatched for delivery</small>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="status-guide-item">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <h6>Delivered</h6>
                                <small class="text-muted">Order successfully delivered</small>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="status-guide-item">
                                <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                                <h6>Cancelled</h6>
                                <small class="text-muted">Order has been cancelled</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Order Modal -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this order? This action cannot be undone.</p>
                <div class="form-group">
                    <label for="cancelReason" class="form-label">Reason for cancellation:</label>
                    <select class="form-select" id="cancelReason">
                        <option value="">Select a reason</option>
                        <option value="changed_mind">Changed my mind</option>
                        <option value="found_cheaper">Found better price elsewhere</option>
                        <option value="delivery_time">Delivery time too long</option>
                        <option value="personal_reasons">Personal reasons</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Order</button>
                <button type="button" class="btn btn-danger" id="confirmCancel">Cancel Order</button>
            </div>
        </div>
    </div>
</div>

<style>
.status-guide-item {
    padding: 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.status-guide-item:hover {
    background: rgba(212, 175, 55, 0.1);
    transform: translateY(-2px);
}

.table-hover tbody tr:hover {
    background-color: rgba(212, 175, 55, 0.05);
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

.card {
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.card:hover {
    border-color: var(--primary-color);
}

.list-group-item.active {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
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
        font-size: 0.775rem;
    }

    .status-guide-item {
        margin-bottom: 1rem;
    }
}

/* Order status animation */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.badge.bg-warning {
    animation: pulse 2s infinite;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cancel order functionality
    const cancelButtons = document.querySelectorAll('.cancel-order');
    const cancelModal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
    let currentOrderId = null;

    cancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            currentOrderId = this.getAttribute('data-order-id');
            cancelModal.show();
        });
    });

    document.getElementById('confirmCancel').addEventListener('click', function() {
        if (currentOrderId) {
            const reason = document.getElementById('cancelReason').value;
            if (!reason) {
                alert('Please select a reason for cancellation.');
                return;
            }

            // Send cancellation request
            fetch('../api/cancel-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: currentOrderId,
                    reason: reason
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error cancelling order: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error cancelling order. Please try again.');
            });
        }
    });

    // Date validation
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');

    if (dateFrom && dateTo) {
        dateFrom.addEventListener('change', function() {
            if (dateTo.value && this.value > dateTo.value) {
                dateTo.value = this.value;
            }
        });

        dateTo.addEventListener('change', function() {
            if (dateFrom.value && this.value < dateFrom.value) {
                dateFrom.value = this.value;
            }
        });
    }

    // Print order functionality
    const printButtons = document.querySelectorAll('.print-order');
    printButtons.forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            window.open(`order-invoice.php?id=${orderId}`, '_blank');
        });
    });

    // Auto-refresh for pending orders
    const hasPendingOrders = document.querySelector('.badge.bg-warning');
    if (hasPendingOrders) {
        setInterval(() => {
            // Check for status updates every 30 seconds
            fetch('../api/check-order-updates.php')
                .then(response => response.json())
                .then(data => {
                    if (data.updated) {
                        location.reload();
                    }
                });
        }, 30000);
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>