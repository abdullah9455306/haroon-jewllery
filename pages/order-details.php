<?php
// order-details.php
require_once '../config/constants.php';
require_once '../includes/header.php';

// Initialize database connection
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: orders.php');
    exit;
}

$pageTitle = "Order Details";
$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Get order details
$order_sql = "SELECT o.*,
                     COUNT(oi.id) as item_count,
                     SUM(oi.quantity) as total_quantity
              FROM orders o
              LEFT JOIN order_items oi ON o.id = oi.order_id
              WHERE o.id = ? AND o.user_id = ?
              GROUP BY o.id";
$order_stmt = $conn->prepare($order_sql);
$order_stmt->execute([$order_id, $user_id]);
$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

// If order not found or doesn't belong to user, redirect
if (!$order) {
    header('Location: orders.php');
    exit;
}

// Get order items
$items_sql = "SELECT oi.*, p.image, p.slug
              FROM order_items oi
              LEFT JOIN products p ON oi.product_id = p.id
              WHERE oi.order_id = ?
              ORDER BY oi.id";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get JazzCash transaction details if available
$transaction = null;
if (!empty($order['jazzcash_transaction_id'])) {
    $txn_sql = "SELECT * FROM jazzcash_transactions
                WHERE order_id = ?
                ORDER BY created_at DESC
                LIMIT 1";
    $txn_stmt = $conn->prepare($txn_sql);
    $txn_stmt->execute([$order_id]);
    $transaction = $txn_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get card payment details if available
$card_payment = null;
if ($order['payment_method'] === 'jazzcash_card') {
    $card_sql = "SELECT * FROM card_payments
                 WHERE order_id = ?
                 ORDER BY created_at DESC
                 LIMIT 1";
    $card_stmt = $conn->prepare($card_sql);
    $card_stmt->execute([$order_id]);
    $card_payment = $card_stmt->fetch(PDO::FETCH_ASSOC);
}

// Status configuration
$status_config = [
    'pending' => ['class' => 'bg-warning', 'icon' => 'fas fa-clock', 'description' => 'Order received, awaiting processing'],
    'processing' => ['class' => 'bg-info', 'icon' => 'fas fa-cog', 'description' => 'Order being prepared for shipment'],
    'shipped' => ['class' => 'bg-primary', 'icon' => 'fas fa-shipping-fast', 'description' => 'Order dispatched for delivery'],
    'delivered' => ['class' => 'bg-success', 'icon' => 'fas fa-check-circle', 'description' => 'Order successfully delivered'],
    'cancelled' => ['class' => 'bg-danger', 'icon' => 'fas fa-times-circle', 'description' => 'Order has been cancelled']
];

$current_status = $status_config[$order['order_status']] ?? ['class' => 'bg-secondary', 'icon' => 'fas fa-question', 'description' => 'Unknown status'];
?>

<div class="container-fluid py-5">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 brand-font mb-1">Order Details</h1>
                    <p class="text-muted mb-0">Order #<?php echo $order['order_number']; ?></p>
                </div>
                <div class="btn-group">
                    <a href="orders.php" class="btn btn-outline-dark">
                        <i class="fas fa-arrow-left me-2"></i>Back to Orders
                    </a>
                    <?php if ($order['order_status'] === 'pending'): ?>
                        <button type="button" class="btn btn-outline-danger cancel-order" data-order-id="<?php echo $order['id']; ?>">
                            <i class="fas fa-times me-2"></i>Cancel Order
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Order Summary</h5>
                            <span class="badge <?php echo $current_status['class']; ?> fs-6">
                                <i class="<?php echo $current_status['icon']; ?> me-1"></i>
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-3">Order Information</h6>
                                    <div class="mb-2">
                                        <strong>Order Number:</strong> #<?php echo $order['order_number']; ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Order Date:</strong>
                                        <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Items:</strong>
                                        <?php echo $order['item_count']; ?> items (<?php echo $order['total_quantity']; ?> total quantity)
                                    </div>
                                    <div class="mb-2">
                                        <strong>Payment Method:</strong>
                                        <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                                    </div>
                                    <?php if (!empty($order['jazzcash_transaction_id'])): ?>
                                        <div class="mb-2">
                                            <strong>Transaction ID:</strong>
                                            <?php echo $order['jazzcash_transaction_id']; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-3">Customer Information</h6>
                                    <div class="mb-2">
                                        <strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_mobile']); ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Address:</strong>
                                        <?php echo htmlspecialchars($order['customer_address'] . ', ' . $order['customer_city'] . ', ' . $order['customer_country']); ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($order['notes'])): ?>
                                <div class="mt-4">
                                    <h6 class="text-muted mb-2">Order Notes</h6>
                                    <div class="alert alert-light border">
                                        <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Order Total</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Subtotal:</span>
                                <strong><?php echo CURRENCY . ' ' . number_format($order['subtotal'], 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Shipping:</span>
                                <strong><?php echo CURRENCY . ' ' . number_format($order['shipping'], 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Tax:</span>
                                <strong><?php echo CURRENCY . ' ' . number_format($order['tax'], 2); ?></strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-dark fw-bold">Total Amount:</span>
                                <strong class="text-gold fs-5"><?php echo CURRENCY . ' ' . number_format($order['total_amount'], 2); ?></strong>
                            </div>

                            <?php if ($order['payment_status'] === 'paid'): ?>
                                <div class="alert alert-success mt-3 mb-0">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Payment Completed
                                </div>
                            <?php elseif ($order['payment_status'] === 'pending'): ?>
                                <div class="alert alert-warning mt-3 mb-0">
                                    <i class="fas fa-clock me-2"></i>
                                    Payment Pending
                                </div>
                            <?php elseif ($order['payment_status'] === 'failed'): ?>
                                <div class="alert alert-danger mt-3 mb-0">
                                    <i class="fas fa-times-circle me-2"></i>
                                    Payment Failed
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Payment Details -->
                    <?php if ($transaction || $card_payment): ?>
                        <div class="card border-0 shadow-sm mt-4">
                            <div class="card-header bg-light border-0">
                                <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Details</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($transaction): ?>
                                    <div class="mb-2">
                                        <strong>Transaction Ref:</strong>
                                        <?php echo $transaction['pp_TxnRefNo']; ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Amount:</strong>
                                        <?php echo CURRENCY . ' ' . number_format($transaction['pp_Amount'], 2); ?>
                                    </div>
                                    <?php if (!empty($transaction['pp_MobileNumber'])): ?>
                                        <div class="mb-2">
                                            <strong>Mobile Number:</strong>
                                            <?php echo $transaction['pp_MobileNumber']; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mb-2">
                                        <strong>Status:</strong>
                                        <span class="badge <?php echo $transaction['status'] === 'completed' ? 'bg-success' : ($transaction['status'] === 'pending' ? 'bg-warning' : 'bg-danger'); ?>">
                                            <?php echo ucfirst($transaction['status']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($card_payment): ?>
                                    <div class="mb-2">
                                        <strong>Card Transaction Ref:</strong>
                                        <?php echo $card_payment['pp_TxnRefNo']; ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Amount:</strong>
                                        <?php echo CURRENCY . ' ' . number_format($card_payment['pp_Amount'], 2); ?>
                                    </div>
                                    <?php if (!empty($card_payment['pp_CardHolderName'])): ?>
                                        <div class="mb-2">
                                            <strong>Card Holder:</strong>
                                            <?php echo $card_payment['pp_CardHolderName']; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mb-2">
                                        <strong>Status:</strong>
                                        <span class="badge <?php echo $card_payment['status'] === 'completed' ? 'bg-success' : ($card_payment['status'] === 'pending' ? 'bg-warning' : 'bg-danger'); ?>">
                                            <?php echo ucfirst($card_payment['status']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light border-0">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Order Items</h5>
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
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-start">
                                                <?php if (!empty($item['image'])): ?>
                                                    <div class="flex-shrink-0 me-3">
                                                        <img src="../uploads/products/<?php echo $item['image']; ?>"
                                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                             class="rounded" width="60" height="60">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="flex-shrink-0 me-3">
                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                                            <i class="fas fa-gem text-muted"></i>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                    <?php if (!empty($item['slug'])): ?>
                                                        <small class="text-muted">
                                                            SKU: <?php echo $item['product_id']; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo CURRENCY . ' ' . number_format($item['product_price'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $item['quantity']; ?></span>
                                        </td>
                                        <td>
                                            <strong class="text-gold"><?php echo CURRENCY . ' ' . number_format($item['total_price'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['slug'])): ?>
                                                <a href="product-detail.php?id=<?php echo $item['product_id']; ?>"
                                                   class="btn btn-sm btn-gold">
                                                    <i class="fas fa-eye me-1"></i>View Product
                                                </a>
                                            <?php endif; ?>
                                            <!--<?php if ($order['order_status'] === 'delivered'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success mt-1 reorder-item"
                                                        data-product-id="<?php echo $item['product_id']; ?>"
                                                        data-product-name="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                    <i class="fas fa-redo me-1"></i>Reorder
                                                </button>
                                            <?php endif; ?>-->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Order Timeline -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Order Status Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="steps">
                                <?php
                                $statuses = ['pending', 'processing', 'shipped', 'delivered'];
                                $current_index = array_search($order['order_status'], $statuses);
                                if ($current_index === false && $order['order_status'] === 'cancelled') {
                                    $current_index = -1; // Special case for cancelled orders
                                }

                                foreach ($statuses as $index => $status):
                                    $status_info = $status_config[$status];
                                    $is_completed = $index <= $current_index;
                                    $is_current = $index === $current_index;
                                    $is_cancelled = $order['order_status'] === 'cancelled';
                                ?>
                                    <div class="step <?php echo $is_completed ? 'completed' : ''; ?> <?php echo $is_current ? 'current' : ''; ?>">
                                        <div class="step-icon">
                                            <i class="<?php echo $status_info['icon']; ?>"></i>
                                        </div>
                                        <div class="step-content">
                                            <h6 class="step-title"><?php echo ucfirst($status); ?></h6>
                                            <p class="step-description text-muted"><?php echo $status_info['description']; ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <?php if ($is_cancelled): ?>
                                    <div class="step cancelled">
                                        <div class="step-icon">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <div class="step-content">
                                            <h6 class="step-title">Cancelled</h6>
                                            <p class="step-description text-muted">Order has been cancelled</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Support Section -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-light border-0">
                    <h5 class="mb-0"><i class="fas fa-headset me-2"></i>Need Help?</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Contact Support</h6>
                            <p class="text-muted mb-3">If you have any questions about your order, our support team is here to help.</p>
                            <a href="contact.php" class="btn btn-gold">
                                <i class="fas fa-envelope me-2"></i>Contact Support
                            </a>
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
                <p>Are you sure you want to cancel order #<?php echo $order['order_number']; ?>? This action cannot be undone.</p>
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
    /* font-family: 'Playfair Display', serif; */
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

/* Steps Timeline */
.steps {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin: 2rem 0;
}

.steps::before {
    content: '';
    position: absolute;
    top: 30px;
    left: 0;
    right: 0;
    height: 2px;
    background: #e9ecef;
    z-index: 1;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
    flex: 1;
}

.step.completed .step-icon {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.step.current .step-icon {
    background-color: white;
    color: var(--primary-color);
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
}

.step.cancelled .step-icon {
    background-color: #dc3545;
    color: white;
    border-color: #dc3545;
}

.step-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: white;
    border: 2px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
    font-size: 1.25rem;
}

.step-content {
    text-align: center;
    max-width: 150px;
}

.step-title {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.step-description {
    font-size: 0.75rem;
    line-height: 1.3;
}

@media (max-width: 768px) {
    .steps {
        flex-direction: column;
        align-items: flex-start;
    }

    .steps::before {
        display: none;
    }

    .step {
        flex-direction: row;
        margin-bottom: 1.5rem;
        width: 100%;
    }

    .step-icon {
        margin-right: 1rem;
        margin-bottom: 0;
        width: 50px;
        height: 50px;
        font-size: 1rem;
    }

    .step-content {
        text-align: left;
        max-width: none;
    }

    .table-responsive {
        font-size: 0.875rem;
    }

    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.775rem;
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

    // Print order functionality
    const printButtons = document.querySelectorAll('.print-order');
    printButtons.forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            window.open(`order-invoice.php?id=${orderId}`, '_blank');
        });
    });

    // Reorder item functionality
    const reorderButtons = document.querySelectorAll('.reorder-item');
    reorderButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');

            if (confirm(`Add "${productName}" to cart?`)) {
                fetch('../api/add-to-cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: 1
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Product added to cart successfully!');
                    } else {
                        alert('Error adding product to cart: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('Error adding product to cart. Please try again.');
                });
            }
        });
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>