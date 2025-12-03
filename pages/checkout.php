<?php
$pageTitle = "Checkout";
require_once '../config/constants.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'checkout';
    header('Location: login');
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
    header('Location: cart');
    exit;
}

require_once '../includes/header.php';

// Initialize JazzCash Payment
require_once '../api/jazzcash-payment.php';
$jazzcash = new JazzCashPayment();
$apiVersion = $jazzcash->getApiVersion();
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8">
            <h2 class="mb-4 brand-font">Checkout</h2>

            <form id="checkoutForm" action="<?php echo SITE_URL; ?>/process-order" method="POST">
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
                                <input type="text" class="form-control" id="mobile" name="mobile" required
                                       pattern="03[0-9]{9}"
                                       placeholder="03XXXXXXXXX"
                                       value="<?php echo isset($_SESSION['user_mobile']) ? htmlspecialchars($_SESSION['user_mobile']) : ''; ?>">
                                <small class="form-text text-muted">Format: 03XX-XXXXXXX</small>
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

                        <!-- JazzCash Payment Details -->
                        <div id="jazzcashDetails" class="border rounded p-3 mt-3">
                            <h6 class="mb-3">JazzCash Payment Details</h6>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <img
                                        src="<?php echo SITE_URL; ?>/assets/images/jazzcash-logo.png"
                                        style="width: 230px;border-radius: 10px;"
                                    />
                                </div>

                                <div class="col-md-6 mb-3">
                                  <?php if ($apiVersion === '2.0'): ?>
                                    <div class="form-group mb-3">
                                         <label for="jazzcash_mobile_number" class="form-label">JazzCash Mobile Number *</label>
                                        <input type="text" class="form-control" id="jazzcash_mobile_number" name="jazzcash_mobile_number"
                                               pattern="03[0-9]{9}"
                                               placeholder="03XXXXXXXXX"
                                               required>
                                         <small class="form-text text-muted">Your registered JazzCash mobile number</small>
                                     </div>
                                     <div class="form-group">
                                         <label for="jazzcash_cnic" class="form-label">Last 6 Digits of CNIC *</label>
                                         <input type="text" class="form-control" id="jazzcash_cnic" name="jazzcash_cnic"
                                                pattern="[0-9]{6}"
                                                placeholder="XXXXXX"
                                                required>
                                         <small class="form-text text-muted">Format: XXXXXX</small>
                                     </div>
                                     <?php else: ?>
                                        <div class="form-group">
                                            <label for="jazzcash_mobile_number" class="form-label">JazzCash Mobile Number *</label>
                                            <input type="text" class="form-control" id="jazzcash_mobile_number" name="jazzcash_mobile_number"
                                                   pattern="03[0-9]{9}"
                                                   placeholder="03XXXXXXXXX"
                                                   required>
                                            <small class="form-text text-muted">Your registered JazzCash mobile number</small>
                                        </div>
                                     <?php endif; ?>
                                    </div>
                            </div>

                            <div class="alert alert-info">
                                <small>
                                    <i class="fas fa-info-circle me-2"></i>
                                    You will be redirected to JazzCash secure payment page to complete your transaction.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="api_version" value="<?php echo $apiVersion; ?>">
                <button type="submit" class="btn btn-gold btn-lg w-100" id="placeOrderBtn">
                    <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                    Place Order & Pay with JazzCash
                </button>
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
                        <span><?php echo CURRENCY . ' 0'; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax:</span>
                        <span><?php echo CURRENCY . ' ' . number_format($subtotal * 0.00); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <strong>Total:</strong>
                        <strong><?php echo CURRENCY . ' ' . number_format($subtotal + 0 + ($subtotal * 0.00)); ?></strong>
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
    const checkoutForm = document.getElementById('checkoutForm');
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    const spinner = placeOrderBtn.querySelector('.spinner-border');

    // Mobile number formatting
    const mobileInputs = document.querySelectorAll('input[type="text"][pattern*="03"]');
    mobileInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            // Remove all non-digit characters and limit to 11 digits
            let value = e.target.value.replace(/\D/g, '');
            value = value.substring(0, 11);
            e.target.value = value;
        });
    });

    // CNIC formatting
    const cnicInput = document.getElementById('jazzcash_cnic');
    if (cnicInput) {
        cnicInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            // Limit to 6 digits maximum
            value = value.substring(0, 6);
            e.target.value = value;
        });
    }

    // Form validation
    checkoutForm.addEventListener('submit', function(e) {
        e.preventDefault();

        let isValid = true;
        const requiredFields = checkoutForm.querySelectorAll('[required]');

        // Clear previous errors
        requiredFields.forEach(field => {
            field.classList.remove('is-invalid');
        });

        // Validate required fields
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
            }
        });

        // Validate mobile format
        const mobileFields = checkoutForm.querySelectorAll('input[pattern*="03"]');
        mobileFields.forEach(field => {
            const pattern = new RegExp(field.pattern);
            if (field.value && !pattern.test(field.value)) {
                isValid = false;
                field.classList.add('is-invalid');
            }
        });

        // Validate CNIC if exists
        if (cnicInput && cnicInput.value) {
            const cnicPattern = new RegExp(cnicInput.pattern);
            if (!cnicPattern.test(cnicInput.value)) {
                isValid = false;
                cnicInput.classList.add('is-invalid');
            }
        }

        if (!isValid) {
            alert('Please fill in all required fields with valid information.');
            return;
        }

        // Show loading spinner
        spinner.classList.remove('d-none');
        placeOrderBtn.disabled = true;

        // Submit form
        this.submit();
    });

    // Real-time validation
    const inputs = checkoutForm.querySelectorAll('input, textarea');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.classList.add('is-invalid');
            } else if (this.hasAttribute('pattern')) {
                const pattern = new RegExp(this.pattern);
                if (this.value && !pattern.test(this.value)) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            } else {
                this.classList.remove('is-invalid');
            }
        });
    });
});
</script>

<style>
.is-invalid {
    border-color: #dc3545;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6.4.4.4-.4'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}

@media (max-width: 768px) {
    .card-body .row {
        margin-bottom: 0;
    }

    .mb-3 {
        margin-bottom: 1rem !important;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>