<?php
$pageTitle = "Checkout";
require_once '../config/constants.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'checkout.php';
    header('Location: login.php');
    exit;
}

// Check if cart is empty
$cartIsEmpty = true;
if (isset($_SESSION['user_id'])) {
    // For logged-in users, check database cart
    require_once '../config/database.php';
    require_once '../helpers/cart_helper.php';

    $db = new Database();
    $conn = $db->getConnection();
    $cartHelper = new CartHelper();

    $cartItems = $cartHelper->getCartItems($_SESSION['user_id']);
    $cartIsEmpty = empty($cartItems);
} else {
    // For guest users, check session cart
    $cartIsEmpty = empty($_SESSION['cart']);
}

// Redirect to cart if empty
if ($cartIsEmpty) {
    header('Location: cart.php');
    exit;
}

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8">
            <h2 class="mb-4 brand-font">Checkout</h2>

            <form id="checkoutForm" action="payment.php" method="POST">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Shipping Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fullName" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="fullName" name="fullName" required
                                       value="<?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : ''; ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="mobile" class="form-label">Mobile Number *</label>
                                <input type="text" class="form-control" id="mobile" name="mobile" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City *</label>
                                <input type="text" class="form-control" id="city" name="city" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Complete Address *</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Payment Method</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="paymentMethod" id="jazzcash_mobile" value="jazzcash_mobile" checked>
                            <label class="form-check-label" for="jazzcash_mobile">
                                <i class="fas fa-mobile-alt me-2"></i>JazzCash Mobile Account
                                <small class="d-block text-muted">Pay using your JazzCash mobile account</small>
                            </label>
                        </div>

                        <!--<div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="paymentMethod" id="jazzcash_card" value="jazzcash_card">
                            <label class="form-check-label" for="jazzcash_card">
                                <i class="fas fa-credit-card me-2"></i>Debit/Credit Card (JazzCash)
                                <small class="d-block text-muted">Pay using your debit or credit card</small>
                            </label>
                        </div>-->

                        <!-- Card Details (Hidden by default) -->
                        <!--<div id="cardDetails" class="border rounded p-3 mt-3" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="cardNumber" class="form-label">Card Number</label>
                                    <input type="text" class="form-control" id="cardNumber" name="cardNumber" placeholder="1234 5678 9012 3456">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="cardExpiry" class="form-label">Expiry Date</label>
                                    <input type="text" class="form-control" id="cardExpiry" name="cardExpiry" placeholder="MM/YY">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="cardCVC" class="form-label">CVC</label>
                                    <input type="text" class="form-control" id="cardCVC" name="cardCVC" placeholder="123">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="cardHolderName" class="form-label">Cardholder Name</label>
                                <input type="text" class="form-control" id="cardHolderName" name="cardHolderName" placeholder="John Doe">
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="paymentMethod" id="cod" value="cod">
                            <label class="form-check-label" for="cod">
                                <i class="fas fa-money-bill-wave me-2"></i>Cash on Delivery
                                <small class="d-block text-muted">Pay when you receive your order</small>
                            </label>
                        </div>-->
                    </div>
                </div>

                <button type="submit" class="btn btn-gold btn-lg w-100">Place Order</button>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <?php
                    $subtotal = 0;
                    $cartItems = [];

                    // Get cart items based on user type
                    if (isset($_SESSION['user_id'])) {
                        $cartItems = $cartHelper->getCartItems($_SESSION['user_id']);
                    } else {
                        $cartItems = $_SESSION['cart'] ?? [];
                    }

                    foreach($cartItems as $item):
                        $item_total = $item['price'] * $item['quantity'];
                        $subtotal += $item_total;
                    ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span><?php echo htmlspecialchars($item['name']); ?> x <?php echo $item['quantity']; ?></span>
                            <span><?php echo CURRENCY . ' ' . number_format($item_total); ?></span>
                        </div>
                    <?php endforeach; ?>

                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span><?php echo CURRENCY . ' ' . number_format($subtotal); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping:</span>
                        <span><?php echo CURRENCY . ' 200'; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax (5%):</span>
                        <span><?php echo CURRENCY . ' ' . number_format($subtotal * 0.05); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <strong>Total:</strong>
                        <strong><?php echo CURRENCY . ' ' . number_format($subtotal + 200 + ($subtotal * 0.05)); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Security Notice -->
            <div class="card mt-3 border-success">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-shield-alt text-warning me-2 fs-5"></i>
                        <div>
                            <h6 class="mb-1 text-success">Secure Checkout</h6>
                            <small class="text-muted">Your personal information is protected</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethods = document.querySelectorAll('input[name="paymentMethod"]');
    const cardDetails = document.getElementById('cardDetails');

    paymentMethods.forEach(method => {
        method.addEventListener('change', function() {
            if (this.value === 'jazzcash_card') {
                cardDetails.style.display = 'block';
                // Make card fields required when card payment is selected
                document.getElementById('cardNumber').required = true;
                document.getElementById('cardExpiry').required = true;
                document.getElementById('cardCVC').required = true;
                document.getElementById('cardHolderName').required = true;
            } else {
                cardDetails.style.display = 'none';
                // Remove required attribute when other payment methods are selected
                document.getElementById('cardNumber').required = false;
                document.getElementById('cardExpiry').required = false;
                document.getElementById('cardCVC').required = false;
                document.getElementById('cardHolderName').required = false;
            }
        });
    });

    // Form validation
    const checkoutForm = document.getElementById('checkoutForm');
    checkoutForm.addEventListener('submit', function(e) {
        let isValid = true;
        const requiredFields = checkoutForm.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });

        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
});
</script>

<style>
.is-invalid {
    border-color: #dc3545;
}

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}
</style>

<?php require_once '../includes/footer.php'; ?>