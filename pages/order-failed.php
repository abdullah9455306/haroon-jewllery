<?php
$pageTitle = "Payment Failed";
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
$orderQuery = "SELECT o.*, jt.pp_ResponseMessage, jt.pp_ResponseCode, jt.pp_TxnRefNo
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

require_once '../includes/header.php';

// Common failure reasons and solutions
$failureReasons = [
    '001' => ['title' => 'Transaction Declined', 'solution' => 'Your bank declined the transaction. Please contact your bank or try a different payment method.'],
    '002' => ['title' => 'Insufficient Funds', 'solution' => 'Your account has insufficient funds. Please add funds to your JazzCash account and try again.'],
    '003' => ['title' => 'Invalid Mobile Number', 'solution' => 'The mobile number provided is not registered with JazzCash. Please check your number or use a different account.'],
    '004' => ['title' => 'Transaction Timeout', 'solution' => 'The transaction took too long to complete. Please try again.'],
    '005' => ['title' => 'Invalid CNIC', 'solution' => 'The CNIC number provided is invalid. Please check your CNIC and try again.'],
    '006' => ['title' => 'Daily Limit Exceeded', 'solution' => 'You have exceeded your daily transaction limit. Please try again tomorrow or contact JazzCash support.'],
    '007' => ['title' => 'Invalid MPIN', 'solution' => 'The MPIN entered is incorrect. Please try again with the correct MPIN.'],
    '999' => ['title' => 'System Error', 'solution' => 'A temporary system error occurred. Please try again in a few minutes.']
];

$errorCode = $order['pp_ResponseCode'] ?? '999';
$failureReason = $failureReasons[$errorCode] ?? $failureReasons['999'];
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Failure Header -->
            <div class="text-center mb-5">
                <div class="failure-icon mb-4">
                    <i class="fas fa-times-circle text-danger" style="font-size: 4rem;"></i>
                </div>
                <h1 class="brand-font text-danger mb-3">Payment Failed</h1>
                <p class="lead text-muted">We couldn't process your payment. Please try again.</p>
                <p class="text-muted">Order Number: <strong><?php echo htmlspecialchars($order['order_number']); ?></strong></p>
            </div>

            <!-- Error Details Card -->
            <div class="card shadow-sm border-danger mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Payment Error Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Error Information</h6>
                            <p class="mb-1"><strong>Error Code:</strong> <span class="text-danger"><?php echo htmlspecialchars($errorCode); ?></span></p>
                            <p class="mb-1"><strong>Error Type:</strong> <?php echo htmlspecialchars($failureReason['title']); ?></p>
                            <p class="mb-1"><strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['pp_TxnRefNo'] ?? 'N/A'); ?></p>
                            <p class="mb-1"><strong>Order Status:</strong> <span class="badge bg-warning"><?php echo ucfirst($order['order_status']); ?></span></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Message from Payment Gateway</h6>
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php echo htmlspecialchars($order['pp_ResponseMessage'] ?? 'Payment processing failed. Please try again.'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Solution Card -->
            <div class="card shadow-sm border-warning mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Suggested Solution</h5>
                </div>
                <div class="card-body">
                    <p class="mb-3"><?php echo $failureReason['solution']; ?></p>

                    <h6 class="mt-4">Common Solutions:</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Ensure your JazzCash account has sufficient balance</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Verify your mobile number and CNIC are correct</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Check your daily transaction limits</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Ensure you have a stable internet connection</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Try again after a few minutes</li>
                    </ul>
                </div>
            </div>

            <!-- Order Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Order Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Order Number:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                            <p class="mb-1"><strong>Order Date:</strong> <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                            <p class="mb-1"><strong>Total Amount:</strong> <span class="text-danger"><?php echo CURRENCY . ' ' . number_format($order['total_amount'], 2); ?></span></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Payment Method:</strong> JazzCash Mobile</p>
                            <p class="mb-1"><strong>Mobile Number:</strong> <?php echo htmlspecialchars($order['jazzcash_mobile_number'] ?? 'N/A'); ?></p>
                            <p class="mb-0"><strong>Status:</strong> <span class="badge bg-danger">Payment Failed</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="card shadow-sm border-primary">
                <div class="card-header bg-light">
                    <h5 class="mb-0 text-primary"><i class="fas fa-redo-alt me-2"></i>Next Steps</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-grid">
                                <a href="checkout.php?retry_order=<?php echo $order_id; ?>" class="btn btn-gold btn-lg">
                                    <i class="fas fa-credit-card me-2"></i>Retry Payment
                                </a>
                                <small class="text-muted text-center mt-2 d-block">Try paying again with the same order</small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-grid">
                                <a href="cart.php" class="btn btn-outline-dark btn-lg">
                                    <i class="fas fa-shopping-cart me-2"></i>Back to Cart
                                </a>
                                <small class="text-muted text-center mt-2 d-block">Review your cart items</small>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6 mb-3">
                            <div class="d-grid">
                                <a href="products.php" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                                </a>
                                <small class="text-muted text-center mt-2 d-block">Browse more products</small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-grid">
                                <a href="orders.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-list me-2"></i>View Orders
                                </a>
                                <small class="text-muted text-center mt-2 d-block">Check your order history</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Support Information -->
            <div class="alert alert-warning mt-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-headset me-3 fs-4"></i>
                    <div>
                        <h6 class="mb-1">Need Immediate Assistance?</h6>
                        <p class="mb-2">If you continue to experience issues, our support team is here to help:</p>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><i class="fas fa-envelope me-2"></i><strong>Email:</strong> info@haroonjewellery.com</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><i class="fas fa-phone me-2"></i><strong>Phone:</strong> 0306-0000905, 0323-1441230</p>
                            </div>
                        </div>
                        <p class="mb-0 mt-2"><small>Please mention your order number: <strong><?php echo htmlspecialchars($order['order_number']); ?></strong></small></p>
                    </div>
                </div>
            </div>

            <!-- JazzCash Support -->
            <div class="alert alert-info">
                <div class="d-flex align-items-center">
                    <i class="fas fa-mobile-alt me-3 fs-4"></i>
                    <div>
                        <h6 class="mb-1">JazzCash Support</h6>
                        <p class="mb-1">If the issue is with your JazzCash account, you can contact JazzCash support:</p>
                        <p class="mb-0"><strong>Helpline:</strong> 4444 (from your Jazz number) or <strong>021-111-124-367</strong></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Store the failed order in session for retry
    sessionStorage.setItem('failed_order_id', '<?php echo $order_id; ?>');

    // Show error message if redirected from payment
    if (window.location.search.includes('error=true')) {
        const errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-danger alert-dismissible fade show';
        errorAlert.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            Payment processing failed. Please try again or contact support.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.container').insertBefore(errorAlert, document.querySelector('.container').firstChild);
    }

    // Track failed payment for analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', 'purchase_failed', {
            'transaction_id': '<?php echo $order["pp_TxnRefNo"] ?? ""; ?>',
            'value': <?php echo $order['total_amount']; ?>,
            'currency': 'PKR',
            'items': [{
                'item_id': 'ORDER_<?php echo $order_id; ?>',
                'item_name': 'Jewellery Order',
                'category': 'Jewellery',
                'quantity': 1
            }]
        });
    }
});
</script>

<style>
.failure-icon {
    animation: shake 0.5s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

.card {
    border-left: 4px solid transparent;
}

.card.border-danger {
    border-left-color: #dc3545;
}

.card.border-warning {
    border-left-color: #ffc107;
}

.card.border-primary {
    border-left-color: #0d6efd;
}

.btn-lg {
    padding: 12px 24px;
    font-size: 1.1rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>