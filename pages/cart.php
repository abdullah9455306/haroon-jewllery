<?php
require_once '../config/constants.php';
require_once '../includes/header.php';

$pageTitle = "Shopping Cart";

// Initialize $total to 0 to prevent undefined variable warnings
$total = 0;
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8">
            <h2 class="mb-4 brand-font">Shopping Cart</h2>

            <?php if(empty($_SESSION['cart'])): ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">Your cart is empty</h4>
                    <p class="text-muted mb-4">Browse our collection and add some items to your cart</p>
                    <a href="products.php" class="btn btn-gold">Continue Shopping</a>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <?php
                        // Calculate total only when cart has items
                        foreach($_SESSION['cart'] as $item):
                            $item_total = $item['price'] * $item['quantity'];
                            $total += $item_total;
                        ?>
                            <div class="row align-items-center mb-4 pb-4 border-bottom">
                                <div class="col-md-2">
                                    <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" class="img-fluid rounded">
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-1"><?php echo $item['name']; ?></h6>
                                    <p class="text-muted small mb-0">SKU: <?php echo $item['sku']; ?></p>
                                </div>
                                <div class="col-md-2">
                                    <span class="price"><?php echo CURRENCY . ' ' . number_format($item['price']); ?></span>
                                </div>
                                <div class="col-md-2">
                                    <div class="input-group input-group-sm">
                                        <button class="btn btn-outline-secondary" type="button">-</button>
                                        <input type="text" class="form-control text-center" value="<?php echo $item['quantity']; ?>">
                                        <button class="btn btn-outline-secondary" type="button">+</button>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <span class="fw-bold"><?php echo CURRENCY . ' ' . number_format($item_total); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span>Subtotal:</span>
                        <span><?php echo CURRENCY . ' ' . number_format($total); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Shipping:</span>
                        <span><?php echo $total > 0 ? CURRENCY . ' 200' : CURRENCY . ' 0'; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Tax:</span>
                        <span><?php echo CURRENCY . ' ' . number_format($total * 0.05); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-4">
                        <strong>Total:</strong>
                        <strong><?php echo CURRENCY . ' ' . number_format($total + ($total > 0 ? 200 : 0) + ($total * 0.05)); ?></strong>
                    </div>

                    <?php if($total > 0): ?>
                        <a href="checkout.php" class="btn btn-gold w-100 mb-3">Proceed to Checkout</a>
                    <?php endif; ?>
                    <a href="products.php" class="btn btn-outline-dark w-100">Continue Shopping</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>