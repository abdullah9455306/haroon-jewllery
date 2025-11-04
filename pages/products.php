<?php
$pageTitle = "Products";
require_once '../config/constants.php';
require_once '../includes/header.php';

// Initialize database connection
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 1000000;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 8;
$offset = ($page - 1) * $limit;

// Build WHERE conditions
$whereConditions = ["p.status = 'active'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($category)) {
    $whereConditions[] = "(c.name = ? OR c.slug = ?)";
    $params[] = $category;
    $params[] = $category;
}

if ($min_price > 0) {
    $whereConditions[] = "(p.sale_price IS NOT NULL AND p.sale_price >= ? OR p.sale_price IS NULL AND p.price >= ?)";
    $params[] = $min_price;
    $params[] = $min_price;
}

if ($max_price < 1000000) {
    $whereConditions[] = "(p.sale_price IS NOT NULL AND p.sale_price <= ? OR p.sale_price IS NULL AND p.price <= ?)";
    $params[] = $max_price;
    $params[] = $max_price;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Build ORDER BY clause
$orderBy = '';
switch ($sort) {
    case 'price_low':
        $orderBy = 'ORDER BY COALESCE(p.sale_price, p.price) ASC';
        break;
    case 'price_high':
        $orderBy = 'ORDER BY COALESCE(p.sale_price, p.price) DESC';
        break;
    case 'name':
        $orderBy = 'ORDER BY p.name ASC';
        break;
    case 'featured':
        $orderBy = 'ORDER BY p.featured DESC, p.created_at DESC';
        break;
    default:
        $orderBy = 'ORDER BY p.created_at DESC';
        break;
}

// Get total count for pagination
$countSql = "SELECT COUNT(DISTINCT p.id) as total
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             $whereClause";
$countStmt = $conn->prepare($countSql);

// Bind parameters for count query
if (!empty($params)) {
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key + 1, $value);
    }
}

$countStmt->execute();
$totalProducts = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalProducts / $limit);

// Get products with pagination - FIXED: Use only positional parameters
$sql = "SELECT p.*, c.name as category_name, c.slug as category_slug,
               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as gallery_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        $whereClause
        $orderBy
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

// Bind all parameters including LIMIT and OFFSET
$paramIndex = 1;
if (!empty($params)) {
    foreach ($params as $value) {
        $stmt->bindValue($paramIndex, $value);
        $paramIndex++;
    }
}

// Bind LIMIT and OFFSET as integers
$stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
$stmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categories = $conn->query("SELECT name, slug FROM categories WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get price range for filter
$priceRange = $conn->query("SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE status = 'active'")->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-5">
    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3">
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
                </div>
                <div class="card-body">
                    <!-- Search -->
                    <div class="mb-4">
                        <h6 class="mb-3">Search</h6>
                        <form method="GET" action="">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-gold" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Categories -->
                    <div class="mb-4">
                        <h6 class="mb-3">Categories</h6>
                        <div class="list-group list-group-flush products-categories">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => ''])); ?>"
                               class="list-group-item list-group-item-action <?php echo empty($category) ? 'active' : ''; ?>">
                                All Categories
                            </a>
                            <?php foreach ($categories as $cat): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => $cat['name']])); ?>"
                                   class="list-group-item list-group-item-action <?php echo $category === $cat['name'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Price Range -->
                    <div class="mb-4">
                        <h6 class="mb-3">Price Range</h6>
                        <form method="GET" action="" id="priceFilterForm">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">

                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" class="form-control" name="min_price" placeholder="Min"
                                           value="<?php echo $min_price > 0 ? $min_price : ''; ?>" min="0">
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control" name="max_price" placeholder="Max"
                                           value="<?php echo $max_price < 1000000 ? $max_price : ''; ?>" min="0">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-gold btn-sm w-100 mt-2">Apply</button>
                            <?php if ($min_price > 0 || $max_price < 1000000): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['min_price' => '', 'max_price' => ''])); ?>"
                                   class="btn btn-outline-secondary btn-sm w-100 mt-1">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Clear All Filters -->
                    <?php if (!empty($search) || !empty($category) || $min_price > 0 || $max_price < 1000000): ?>
                        <div class="mb-3">
                            <a href="products.php" class="btn btn-outline-danger btn-sm w-100">
                                <i class="fas fa-times me-1"></i>Clear All Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Featured Products -->
            <div class="card">
                <div class="card-header bg-gold text-white">
                    <h6 class="mb-0"><i class="fas fa-star me-2"></i>Featured Products</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Get featured products for sidebar - UPDATED QUERY
                    $featuredStmt = $conn->query("
                        SELECT p.*, c.name as category_name,
                               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as gallery_image
                        FROM products p
                        LEFT JOIN categories c ON p.category_id = c.id
                        WHERE p.featured = 1 AND p.status = 'active'
                        ORDER BY p.created_at DESC
                        LIMIT 3
                    ");
                    $featuredProducts = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($featuredProducts as $featured):
                        // Use gallery_image from product_images table, fallback to placeholder
                        $featuredImage = !empty($featured['gallery_image']) ? $featured['gallery_image'] : 'assets/images/placeholder.jpg';
                        $finalPrice = $featured['sale_price'] ?? $featured['price'];
                    ?>
                        <div class="d-flex mb-3 pb-3 border-bottom">
                            <img src="<?php echo SITE_URL; ?>/<?php echo htmlspecialchars($featuredImage); ?>"
                                 alt="<?php echo htmlspecialchars($featured['name']); ?>"
                                 class="flex-shrink-0 me-3" style="width: 60px; height: 60px; object-fit: cover;">
                            <div class="flex-grow-1">
                                <h6 class="mb-1 small">
                                    <a href="product-detail.php?id=<?php echo $featured['id']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($featured['name']); ?>
                                    </a>
                                </h6>
                                <div class="price text-gold fw-bold">
                                    <?php echo CURRENCY . ' ' . number_format($finalPrice); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($featuredProducts)): ?>
                        <p class="text-muted small mb-0">No featured products available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Products Listing -->
        <div class="col-lg-9">
            <!-- Header with Sort and Results -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="brand-font mb-1">
                        <?php
                        if (!empty($category)) {
                            echo htmlspecialchars($category);
                        } elseif (!empty($search)) {
                            echo 'Search: "' . htmlspecialchars($search) . '"';
                        } else {
                            echo 'All Products';
                        }
                        ?>
                    </h2>
                    <p class="text-muted mb-0">
                        Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalProducts); ?> of <?php echo $totalProducts; ?> products
                    </p>
                </div>

                <div class="d-flex align-items-center">
                    <label for="sort" class="me-2 mb-0">Sort by:</label>
                    <form method="GET" action="" class="mb-0">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                        <input type="hidden" name="min_price" value="<?php echo $min_price; ?>">
                        <input type="hidden" name="max_price" value="<?php echo $max_price; ?>">

                        <select name="sort" id="sort" class="form-select" onchange="this.form.submit()">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="featured" <?php echo $sort === 'featured' ? 'selected' : ''; ?>>Featured</option>
                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                            <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Products Grid -->
            <?php if (!empty($products)): ?>
                <div class="row">
                    <?php foreach ($products as $product):
                        $productImage = !empty($product['gallery_image']) ? $product['gallery_image'] : ($product['image'] ?? 'assets/images/placeholder.jpg');
                        $finalPrice = $product['sale_price'] ?? $product['price'];
                        $hasSale = !empty($product['sale_price']) && $product['sale_price'] < $product['price'];
                        $discountPercent = $hasSale ? round((($product['price'] - $product['sale_price']) / $product['price']) * 100) : 0;
                    ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                            <div class="card product-card h-100">
                                <div class="position-relative">
                                    <img src="<?php echo SITE_URL; ?>/<?php echo htmlspecialchars($productImage); ?>"
                                         class="card-img-top product-image"
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         style="height: 250px; object-fit: cover;">

                                    <!-- Badges -->
                                    <div class="position-absolute top-0 start-0 p-2">
                                        <?php if ($hasSale): ?>
                                            <span class="badge bg-danger">-<?php echo $discountPercent; ?>%</span>
                                        <?php endif; ?>
                                        <?php if ($product['featured']): ?>
                                            <span class="badge bg-white text-dark">Featured</span>
                                        <?php endif; ?>
                                        <?php if ($product['stock_quantity'] <= $product['low_stock_threshold'] && $product['stock_quantity'] > 0): ?>
                                            <span class="badge bg-danger">Low Stock</span>
                                        <?php elseif ($product['stock_quantity'] == 0): ?>
                                            <span class="badge bg-secondary">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Quick Actions -->
                                    <div class="card-img-overlay d-flex align-items-end justify-content-center"
                                         style="opacity: 0; transition: opacity 0.3s; background: rgba(0,0,0,0.1);">
                                        <div class="w-100 text-center">
                                            <a href="product-detail.php?id=<?php echo $product['id']; ?>"
                                               class="btn btn-gold btn-sm w-75 mb-2">
                                                <i class="fas fa-eye me-1"></i>Quick View
                                            </a>
                                            <?php if ($product['stock_quantity'] > 0): ?>
                                                <button class="btn btn-dark btn-sm w-75 add-to-cart"
                                                        data-product-id="<?php echo $product['id']; ?>"
                                                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                        data-product-price="<?php echo $finalPrice; ?>"
                                                        data-product-image="<?php echo htmlspecialchars($productImage); ?>">
                                                    <i class="fas fa-shopping-cart me-1"></i>Add to Cart
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-outline-light btn-sm w-75" disabled>
                                                    <i class="fas fa-times me-1"></i>Out of Stock
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title">
                                        <a href="product-detail.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h6>

                                    <div class="category small text-muted mb-2">
                                        <i class="fas fa-tag me-1"></i>
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                    </div>

                                    <div class="price mb-2">
                                        <?php if ($hasSale): ?>
                                            <span class="old-price text-muted me-2">
                                                <?php echo CURRENCY . ' ' . number_format($product['price']); ?>
                                            </span>
                                            <span class="current-price text-gold fw-bold">
                                                <?php echo CURRENCY . ' ' . number_format($finalPrice); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="current-price text-gold fw-bold">
                                                <?php echo CURRENCY . ' ' . number_format($finalPrice); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="product-meta small text-muted mt-auto">
                                        <?php if ($product['stock_quantity'] > 0): ?>
                                            <span class="text-success">
                                                <i class="fas fa-check-circle me-1"></i>In Stock
                                            </span>
                                        <?php else: ?>
                                            <span class="text-danger">
                                                <i class="fas fa-times-circle me-1"></i>Out of Stock
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted">
                            Showing <?php echo min($offset + 1, $totalProducts); ?>-<?php echo min($offset + count($products), $totalProducts); ?> of <?php echo $totalProducts; ?> products
                        </span>
                    </div>
                    <nav aria-label="Products pagination">
                        <ul class="pagination justify-content-center">
                            <!-- Previous Page -->
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link"
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                   aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>

                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link"
                                           href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Next Page -->
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link"
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                   aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- No Products Found -->
                <div class="text-center py-5">
                    <i class="fas fa-search fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">No products found</h4>
                    <p class="text-muted mb-4">
                        <?php if (!empty($search) || !empty($category) || $min_price > 0 || $max_price < 1000000): ?>
                            Try adjusting your search or filter criteria.
                        <?php else: ?>
                            No products are currently available.
                        <?php endif; ?>
                    </p>
                    <a href="products.php" class="btn btn-gold">View All Products</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effect to product cards
    const productCards = document.querySelectorAll('.product-card');
    productCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.querySelector('.card-img-overlay').style.opacity = '1';
        });
        card.addEventListener('mouseleave', function() {
            this.querySelector('.card-img-overlay').style.opacity = '0';
        });
    });

    // Add to cart functionality using the global function from header
    const addToCartButtons = document.querySelectorAll('.add-to-cart');

    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productPrice = parseFloat(this.getAttribute('data-product-price'));
            const productImage = this.getAttribute('data-product-image');

            // Use the global addToCart function from header
            if (typeof window.addToCart === 'function') {
                window.addToCart(productId, productName, productPrice, productImage, 1);
            } else {
                // Fallback if global function is not available
                console.error('addToCart function not found');
                alert('Please refresh the page and try again.');
            }
        });
    });

    // Price range form submission with validation
    const priceForm = document.getElementById('priceFilterForm');
    if (priceForm) {
        priceForm.addEventListener('submit', function(e) {
            const minPrice = parseFloat(this.querySelector('[name="min_price"]').value) || 0;
            const maxPrice = parseFloat(this.querySelector('[name="max_price"]').value) || 1000000;

            if (minPrice > maxPrice) {
                e.preventDefault();
                alert('Minimum price cannot be greater than maximum price.');
            }
        });
    }

    // Quick view functionality
    const quickViewButtons = document.querySelectorAll('.btn-gold[href*="product-detail.php"]');
    quickViewButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // For demo purposes, we'll let the link work normally
            // In a real implementation, you might want to use AJAX to load product details
        });
    });

    // Add loading states to add to cart buttons
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Adding...';
            this.disabled = true;

            // Reset button after 3 seconds (in case of error)
            setTimeout(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            }, 3000);
        });
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
}

.bg-gold {
    background-color: var(--primary-color) !important;
}

.text-gold {
    color: var(--primary-color) !important;
}

.price .old-price {
    font-size: 0.9em;
}

.price .current-price {
    font-size: 1.1em;
}

.page-item.active .page-link {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.page-link {
    color: var(--primary-color);
}

.page-link:hover {
    color: var(--accent-color);
}

.badge {
    font-size: 0.7em;
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
    .col-lg-3 {
        margin-bottom: 2rem;
    }

    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
    }

    .d-flex.justify-content-between > div {
        text-align: center;
    }
}
</style>

<?php
require_once '../includes/footer.php';
?>