<?php
$pageTitle = "Order Confirmed";
require_once '../config/constants.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get order ID from URL
$order_id = $_GET['order_id'] ?? 0;

if (!$order_id) {
    header('Location: orders.php');
    exit;
}

// Initialize database
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Fetch order details
$orderQuery = "SELECT o.*, jt.pp_ResponseMessage, jt.pp_TxnRefNo
               FROM orders o
               LEFT JOIN jazzcash_transactions jt ON o.id = jt.order_id
               WHERE o.id = ? AND o.user_id = ?";
$orderStmt = $conn->prepare($orderQuery);
$orderStmt->execute([$order_id, $_SESSION['user_id']]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Fetch order items
$itemsQuery = "SELECT * FROM order_items WHERE order_id = ?";
$itemsStmt = $conn->prepare($itemsQuery);
$itemsStmt->execute([$order_id]);
$orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Success Header -->
            <div class="text-center mb-5">
                <div class="success-icon mb-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                </div>
                <h1 class="brand-font text-success mb-3">Order Confirmed!</h1>
                <p class="lead text-muted">Thank you for your purchase. Your order has been successfully placed.</p>
                <p class="text-muted">Order Number: <strong><?php echo htmlspecialchars($order['order_number']); ?></strong></p>
            </div>

            <!-- Order Summary Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Order Details</h6>
                            <p class="mb-1"><strong>Order Number:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                            <p class="mb-1"><strong>Order Date:</strong> <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                            <p class="mb-1"><strong>Status:</strong> <span class="badge bg-success"><?php echo ucfirst($order['order_status']); ?></span></p>
                            <p class="mb-1"><strong>Payment:</strong> <span class="badge bg-success"><?php echo ucfirst($order['payment_status']); ?></span></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Payment Information</h6>
                            <p class="mb-1"><strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['pp_TxnRefNo'] ?? 'N/A'); ?></p>
                            <p class="mb-1"><strong>Payment Method:</strong> JazzCash Mobile</p>
                            <p class="mb-1"><strong>Mobile Number:</strong> <?php echo htmlspecialchars($order['jazzcash_mobile_number'] ?? 'N/A'); ?></p>
                            <p class="mb-0"><strong>Message:</strong> <?php echo htmlspecialchars($order['pp_ResponseMessage'] ?? 'Payment completed successfully'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shipping Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Shipping Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                            <p class="mb-1"><strong>Mobile:</strong> <?php echo htmlspecialchars($order['customer_mobile']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($order['customer_address']); ?></p>
                            <p class="mb-1"><strong>City:</strong> <?php echo htmlspecialchars($order['customer_city']); ?></p>
                            <p class="mb-1"><strong>Country:</strong> <?php echo htmlspecialchars($order['customer_country']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Order Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                    </td>
                                    <td><?php echo CURRENCY . ' ' . number_format($item['product_price'], 2); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><strong><?php echo CURRENCY . ' ' . number_format($item['total_price'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                    <td><strong><?php echo CURRENCY . ' ' . number_format($order['subtotal'], 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                                    <td><strong><?php echo CURRENCY . ' ' . number_format($order['shipping'], 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Tax (5%):</strong></td>
                                    <td><strong><?php echo CURRENCY . ' ' . number_format($order['tax'], 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                                    <td><strong class="text-success"><?php echo CURRENCY . ' ' . number_format($order['total_amount'], 2); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="card shadow-sm border-success">
                <div class="card-header bg-light">
                    <h5 class="mb-0 text-success"><i class="fas fa-info-circle me-2"></i>What's Next?</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="p-3">
                                <i class="fas fa-envelope text-primary mb-3" style="font-size: 2rem;"></i>
                                <h6>Order Confirmation</h6>
                                <p class="small text-muted">You will receive an email confirmation shortly with your order details.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="p-3">
                                <i class="fas fa-truck text-warning mb-3" style="font-size: 2rem;"></i>
                                <h6>Order Processing</h6>
                                <p class="small text-muted">We'll start processing your order and update you on the shipping status.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="p-3">
                                <i class="fas fa-home text-success mb-3" style="font-size: 2rem;"></i>
                                <h6>Delivery</h6>
                                <p class="small text-muted">Your order will be delivered within 3-5 business days.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="text-center mt-5">
                <a href="orders.php" class="btn btn-gold me-3">
                    <i class="fas fa-list me-2"></i>View All Orders
                </a>
                <a href="products.php" class="btn btn-outline-dark me-3">
                    <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                </a>
                <button onclick="window.print()" class="btn btn-outline-secondary">
                    <i class="fas fa-print me-2"></i>Print Receipt
                </button>
            </div>

            <!-- Support Information -->
            <div class="alert alert-info mt-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-question-circle me-3 fs-4"></i>
                    <div>
                        <h6 class="mb-1">Need Help?</h6>
                        <p class="mb-0">If you have any questions about your order, please contact our customer support at <strong>support@haroonjewellery.com</strong> or call us at <strong>+92 300 1234567</strong>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style>
@media print {
    .navbar, .footer, .btn, .alert, .text-center .btn {
        display: none !important;
    }

    .container {
        max-width: 100% !important;
        padding: 0 !important;
    }

    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }

    .bg-success, .bg-dark {
        background-color: #000 !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact;
    }

    .text-success {
        color: #000 !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Clear any cart items from session
    fetch('clear-cart-session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    });

    // Show success message if redirected from payment
    if (window.location.search.includes('success=true')) {
        const successAlert = document.createElement('div');
        successAlert.className = 'alert alert-success alert-dismissible fade show';
        successAlert.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>
            Payment completed successfully! Your order has been confirmed.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.container').insertBefore(successAlert, document.querySelector('.container').firstChild);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>