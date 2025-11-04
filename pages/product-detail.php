<?php
$pageTitle = "Product Details";
require_once '../config/constants.php';
require_once '../includes/header.php';

// Initialize database connection
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header('Location: products.php');
    exit;
}

// Get product details
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ? AND p.status = 'active'
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: products.php');
    exit;
}

// Get product images
$image_stmt = $conn->prepare("
    SELECT * FROM product_images
    WHERE product_id = ?
    ORDER BY sort_order ASC
");
$image_stmt->execute([$product_id]);
$product_images = $image_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get related products
$related_stmt = $conn->prepare("
    SELECT p.*,
           (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as gallery_image
    FROM products p
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
    ORDER BY p.featured DESC, p.created_at DESC
    LIMIT 4
");
$related_stmt->execute([$product['category_id'], $product_id]);
$related_products = $related_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate final price
$final_price = $product['sale_price'] ?? $product['price'];
$has_sale = !empty($product['sale_price']) && $product['sale_price'] < $product['price'];
$discount_percent = $has_sale ? round((($product['price'] - $product['sale_price']) / $product['price']) * 100) : 0;

// Set main image
$main_image = !empty($product_images) ? $product_images[0]['image_path'] : ($product['image'] ?? 'assets/images/placeholder.jpg');
?>

<div class="container-fluid py-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="products.php">Products</a></li>
            <?php if (!empty($product['category_name'])): ?>
                <li class="breadcrumb-item">
                    <a href="products.php?category=<?php echo urlencode($product['category_name']); ?>">
                        <?php echo htmlspecialchars($product['category_name']); ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['name']); ?></li>
        </ol>
    </nav>

    <div class="row">
        <!-- Product Images -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0">
                <div class="card-body p-0">
                    <!-- Main Image -->
                    <div class="text-center mb-3">
                        <img src="<?php echo SITE_URL . '/' . htmlspecialchars($main_image); ?>"
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="img-fluid rounded main-product-image"
                             id="mainProductImage"
                             style="max-height: 500px; object-fit: contain;">
                    </div>

                    <!-- Thumbnail Images -->
                    <?php if (!empty($product_images)): ?>
                        <div class="row g-2">
                            <?php foreach ($product_images as $key => $image): ?>
                                <div class="col-3">
                                    <img src="<?php echo SITE_URL . '/' . htmlspecialchars($image['image_path']); ?>"
                                         alt="<?php echo htmlspecialchars($image['alt_text'] ?? $product['name']); ?>"
                                         class="img-fluid rounded thumbnail-image cursor-pointer"
                                         style="height: 80px; object-fit: cover;"
                                         data-main-image="<?php echo SITE_URL . '/' . htmlspecialchars($image['image_path']); ?>"
                                         <?php echo $key === 0 ? 'data-bs-toggle="tooltip" data-bs-title="Main Image"' : ''; ?>>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Product Details -->
        <div class="col-lg-6">
            <div class="card border-0">
                <div class="card-body">
                    <!-- Product Header -->
                    <div class="mb-3">
                        <?php if ($has_sale): ?>
                            <span class="badge bg-danger mb-2">-<?php echo $discount_percent; ?>% OFF</span>
                        <?php endif; ?>
                        <?php if ($product['featured']): ?>
                            <span class="badge bg-warning mb-2">Featured</span>
                        <?php endif; ?>
                        <h1 class="h2 brand-font mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>
                        <div class="d-flex align-items-center mb-2">
                            <div class="text-warning me-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star"></i>
                                <?php endfor; ?>
                            </div>
                            <small class="text-muted">(4.8) • 124 Reviews</small>
                        </div>
                    </div>

                    <!-- Price -->
                    <div class="mb-4">
                        <?php if ($has_sale): ?>
                            <div class="d-flex align-items-center">
                                <h3 class="text-gold mb-0 me-3"><?php echo CURRENCY . ' ' . number_format($final_price); ?></h3>
                                <span class="text-muted text-decoration-line-through"><?php echo CURRENCY . ' ' . number_format($product['price']); ?></span>
                            </div>
                        <?php else: ?>
                            <h3 class="text-gold mb-0"><?php echo CURRENCY . ' ' . number_format($final_price); ?></h3>
                        <?php endif; ?>
                        <small class="text-muted">Inclusive of all taxes</small>
                    </div>

                    <!-- Product Meta -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-tag text-gold me-2"></i>
                                <span class="text-muted">Category:</span>
                                <strong class="ms-2"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></strong>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-cube text-gold me-2"></i>
                                <span class="text-muted">SKU:</span>
                                <strong class="ms-2"><?php echo htmlspecialchars($product['sku']); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-weight text-gold me-2"></i>
                                <span class="text-muted">Weight:</span>
                                <strong class="ms-2"><?php echo htmlspecialchars($product['weight'] ?? 'N/A'); ?> g</strong>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-gem text-gold me-2"></i>
                                <span class="text-muted">Material:</span>
                                <strong class="ms-2"><?php echo htmlspecialchars($product['material'] ?? 'N/A'); ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Status -->
                    <div class="mb-4">
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check-circle me-1"></i>
                                In Stock (<?php echo $product['stock_quantity']; ?> available)
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger">
                                <i class="fas fa-times-circle me-1"></i>
                                Out of Stock
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Add to Cart Section -->
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <div class="card bg-light border-0 mb-4">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-4 mb-3 mb-md-0">
                                        <label for="quantity" class="form-label fw-bold">Quantity:</label>
                                        <div class="input-group input-group-lg">
                                            <button class="btn btn-outline-secondary" type="button" id="decreaseQty">-</button>
                                            <input type="number" class="form-control text-center" id="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                                            <button class="btn btn-outline-secondary" type="button" id="increaseQty">+</button>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <button class="btn btn-gold btn-lg w-100 add-to-cart-detail"
                                                data-product-id="<?php echo $product['id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                data-product-price="<?php echo $final_price; ?>"
                                                data-product-image="<?php echo htmlspecialchars($main_image); ?>"
                                                data-product-sku="<?php echo htmlspecialchars($product['sku']); ?>"
                                                style="margin-bottom: -30px;">
                                            <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                                        </button>
                                        <!--<button class="btn btn-outline-dark btn-lg w-100">
                                            <i class="fas fa-heart me-2"></i>Add to Wishlist
                                        </button>-->
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This product is currently out of stock. Please check back later.
                        </div>
                    <?php endif; ?>

                    <!-- Product Features -->
                    <div class="row text-center mb-4">
                        <div class="col-4">
                            <i class="fas fa-shipping-fast fa-2x text-gold mb-2"></i>
                            <h6>Free Shipping</h6>
                            <small class="text-muted">Across Pakistan</small>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-undo-alt fa-2x text-gold mb-2"></i>
                            <h6>Easy Returns</h6>
                            <small class="text-muted">7-Day Policy</small>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-award fa-2x text-gold mb-2"></i>
                            <h6>Quality</h6>
                            <small class="text-muted">Certified</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Description & Details -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0">
                <div class="card-body">
                    <ul class="nav nav-tabs" id="productTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab">
                                Description
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">
                                Product Details
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">
                                Reviews (124)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="shipping-tab" data-bs-toggle="tab" data-bs-target="#shipping" type="button" role="tab">
                                Shipping & Returns
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-3" id="productTabsContent">
                        <!-- Description Tab -->
                        <div class="tab-pane fade show active" id="description" role="tabpanel">
                            <?php if (!empty($product['description'])): ?>
                                <div class="product-description">
                                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No description available for this product.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Details Tab -->
                        <div class="tab-pane fade" id="details" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="text-muted" style="width: 40%;">Material:</td>
                                            <td><strong><?php echo htmlspecialchars($product['material'] ?? 'N/A'); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Gemstone:</td>
                                            <td><strong><?php echo htmlspecialchars($product['gemstone'] ?? 'N/A'); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Weight:</td>
                                            <td><strong><?php echo htmlspecialchars($product['weight'] ?? 'N/A'); ?> grams</strong></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="text-muted" style="width: 40%;">Dimensions:</td>
                                            <td><strong><?php echo htmlspecialchars($product['dimensions'] ?? 'N/A'); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">SKU:</td>
                                            <td><strong><?php echo htmlspecialchars($product['sku']); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Category:</td>
                                            <td><strong><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Reviews Tab -->
                        <div class="tab-pane fade" id="reviews" role="tabpanel">
                            <div class="row">
                                <div class="col-md-4 text-center mb-4">
                                    <div class="bg-light p-4 rounded">
                                        <h2 class="text-gold mb-1">4.8</h2>
                                        <div class="text-warning mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="text-muted">Based on 124 reviews</small>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <!-- Sample Reviews -->
                                    <div class="review-item mb-4 pb-4 border-bottom">
                                        <div class="d-flex justify-content-between mb-2">
                                            <strong>Sarah Khan</strong>
                                            <small class="text-muted">2 days ago</small>
                                        </div>
                                        <div class="text-warning mb-2">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <p class="mb-0">Beautiful craftsmanship! The ring looks even better in person than in the photos. Excellent quality.</p>
                                    </div>

                                    <div class="review-item mb-4 pb-4 border-bottom">
                                        <div class="d-flex justify-content-between mb-2">
                                            <strong>Ahmed Raza</strong>
                                            <small class="text-muted">1 week ago</small>
                                        </div>
                                        <div class="text-warning mb-2">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star-half-alt"></i>
                                        </div>
                                        <p class="mb-0">Fast delivery and great packaging. The product quality is outstanding. Will definitely shop again!</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shipping Tab -->
                        <div class="tab-pane fade" id="shipping" role="tabpanel">
                            <h6 class="text-gold mb-3">Shipping Information</h6>
                            <ul class="list-unstyled mb-4">
                                <li class="mb-2"><i class="fas fa-shipping-fast text-gold me-2"></i>Free shipping across Pakistan</li>
                                <li class="mb-2"><i class="fas fa-clock text-gold me-2"></i>Delivery within 3-5 business days</li>
                                <li class="mb-2"><i class="fas fa-map-marker-alt text-gold me-2"></i>Cash on delivery available</li>
                                <li><i class="fas fa-box text-gold me-2"></i>Secure and insured packaging</li>
                            </ul>

                            <h6 class="text-gold mb-3">Return Policy</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-undo-alt text-gold me-2"></i>7-day easy return policy</li>
                                <li class="mb-2"><i class="fas fa-exchange-alt text-gold me-2"></i>Items must be in original condition</li>
                                <li><i class="fas fa-credit-card text-gold me-2"></i>Full refund processed within 5 business days</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="brand-font mb-4">Related Products</h3>
                <div class="row">
                    <?php foreach ($related_products as $related):
                        $related_image = !empty($related['gallery_image']) ? $related['gallery_image'] : 'assets/images/placeholder.jpg';
                        $related_final_price = $related['sale_price'] ?? $related['price'];
                        $related_has_sale = !empty($related['sale_price']) && $related['sale_price'] < $related['price'];
                    ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                            <div class="card product-card h-100">
                                <div class="position-relative">
                                    <img src="<?php echo SITE_URL . '/' . htmlspecialchars($related_image); ?>"
                                         class="card-img-top product-image"
                                         alt="<?php echo htmlspecialchars($related['name']); ?>"
                                         style="height: 250px; object-fit: cover;">

                                    <?php if ($related_has_sale): ?>
                                        <span class="position-absolute top-0 start-0 bg-danger text-white px-2 py-1 m-2 small">Sale</span>
                                    <?php endif; ?>

                                    <div class="card-img-overlay d-flex align-items-end justify-content-center" style="opacity: 0;">
                                        <div class="w-100 text-center">
                                            <a href="product-detail.php?id=<?php echo $related['id']; ?>" class="btn btn-gold btn-sm w-75 mb-2">
                                                Quick View
                                            </a>
                                            <?php if ($related['stock_quantity'] > 0): ?>
                                                <button class="btn btn-dark btn-sm w-75 add-to-cart"
                                                        data-product-id="<?php echo $related['id']; ?>"
                                                        data-product-name="<?php echo htmlspecialchars($related['name']); ?>"
                                                        data-product-price="<?php echo $related_final_price; ?>"
                                                        data-product-image="<?php echo htmlspecialchars($related_image); ?>"
                                                        data-product-sku="<?php echo htmlspecialchars($related['sku']); ?>">
                                                    Add to Cart
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-outline-light btn-sm w-75" disabled>
                                                    Out of Stock
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body text-center">
                                    <h6 class="card-title"><?php echo htmlspecialchars($related['name']); ?></h6>
                                    <div class="price">
                                        <?php if ($related_has_sale): ?>
                                            <span class="old-price me-2"><?php echo CURRENCY . ' ' . number_format($related['price']); ?></span>
                                            <span class="current-price text-gold"><?php echo CURRENCY . ' ' . number_format($related_final_price); ?></span>
                                        <?php else: ?>
                                            <span class="current-price text-gold"><?php echo CURRENCY . ' ' . number_format($related_final_price); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Success Message Container -->
<div id="cartMessageContainer" style="position: fixed; top: 100px; right: 20px; z-index: 9999;"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image gallery functionality
    const mainImage = document.getElementById('mainProductImage');
    const thumbnailImages = document.querySelectorAll('.thumbnail-image');

    thumbnailImages.forEach(thumb => {
        thumb.addEventListener('click', function() {
            const newImage = this.getAttribute('data-main-image');
            mainImage.src = newImage;

            // Update active state
            thumbnailImages.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            this.style.border = '2px solid var(--primary-color)';
        });
    });

    // Quantity controls
    const quantityInput = document.getElementById('quantity');
    const decreaseBtn = document.getElementById('decreaseQty');
    const increaseBtn = document.getElementById('increaseQty');

    if (decreaseBtn && increaseBtn) {
        decreaseBtn.addEventListener('click', function() {
            let currentVal = parseInt(quantityInput.value);
            if (currentVal > 1) {
                quantityInput.value = currentVal - 1;
            }
        });

        increaseBtn.addEventListener('click', function() {
            let currentVal = parseInt(quantityInput.value);
            let maxVal = parseInt(quantityInput.getAttribute('max'));
            if (currentVal < maxVal) {
                quantityInput.value = currentVal + 1;
            }
        });
    }

    // Show message function
    function showMessage(message, type = 'success') {
        const container = document.getElementById('cartMessageContainer');
        const alertId = 'alert-' + Date.now();

        container.innerHTML = `
            <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert" style="min-width: 300px;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        // Auto hide after 3 seconds
        setTimeout(() => {
            const alert = document.getElementById(alertId);
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 3000);
    }

    // Add to cart functionality for main product
    const addToCartBtn = document.querySelector('.add-to-cart-detail');

    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productPrice = parseFloat(this.getAttribute('data-product-price'));
            const productImage = this.getAttribute('data-product-image');
            const productSku = this.getAttribute('data-product-sku');
            const quantity = parseInt(quantityInput.value);

            // Show loading state
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            this.disabled = true;

            // Use the global addToCart function from header
            if (typeof window.addToCart === 'function') {
                window.addToCart(productId, productName, productPrice, productImage, quantity, productSku)
                this.innerHTML = originalText;
                this.disabled = false;
            } else {
                // Fallback if global function is not available
                this.innerHTML = originalText;
                this.disabled = false;
                showMessage('Please refresh the page and try again.', 'danger');
                console.error('addToCart function not found');
            }
        });
    }

    // Add to cart functionality for related products
    const relatedAddToCartBtns = document.querySelectorAll('.product-card .add-to-cart');

    relatedAddToCartBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productPrice = parseFloat(this.getAttribute('data-product-price'));
            const productImage = this.getAttribute('data-product-image');
            const productSku = this.getAttribute('data-product-sku');

            // Show loading state
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            this.disabled = true;

            // Use the global addToCart function from header
            if (typeof window.addToCart === 'function') {
                window.addToCart(productId, productName, productPrice, productImage, 1, productSku)
                this.innerHTML = originalText;
                this.disabled = false;
            } else {
                // Fallback if global function is not available
                this.innerHTML = originalText;
                this.disabled = false;
                showMessage('Please refresh the page and try again.', 'danger');
                console.error('addToCart function not found');
            }
        });
    });

    // Product card hover effects for related products
    const productCards = document.querySelectorAll('.product-card');
    productCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            const overlay = this.querySelector('.card-img-overlay');
            if (overlay) {
                overlay.style.opacity = '1';
            }
        });
        card.addEventListener('mouseleave', function() {
            const overlay = this.querySelector('.card-img-overlay');
            if (overlay) {
                overlay.style.opacity = '0';
            }
        });
    });

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<style>
.product-card {
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.card-img-overlay {
    transition: opacity 0.3s ease;
    background: rgba(0,0,0,0.1);
}

.thumbnail-image {
    transition: all 0.3s ease;
    cursor: pointer;
}

.thumbnail-image:hover {
    transform: scale(1.05);
}

.thumbnail-image.active {
    border: 2px solid var(--primary-color) !important;
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

.nav-tabs .nav-link.active {
    color: var(--primary-color);
    border-bottom: 2px solid var(--primary-color);
}

.nav-tabs .nav-link {
    color: var(--text-color);
}

.price .old-price {
    font-size: 0.9em;
    color: #6c757d;
}

.price .current-price {
    font-size: 1.1em;
}

.cursor-pointer {
    cursor: pointer;
}

/* Loading state for buttons */
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.fa-spin {
    animation: spin 1s linear infinite;
}

@media (max-width: 768px) {
    .main-product-image {
        max-height: 300px !important;
    }

    .btn-lg {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
}
</style>

<?php
require_once '../includes/footer.php';
?>