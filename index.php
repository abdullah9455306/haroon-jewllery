<?php
require_once 'config/constants.php';
require_once 'includes/header.php';

// Initialize database connection
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Get featured categories for the slider
$categories_sql = "SELECT c.id, c.name, c.slug, c.image, c.description
                   FROM categories c
                   WHERE c.status = 'active' AND c.parent_id IS NULL
                   ORDER BY c.sort_order ASC, c.name ASC
                   LIMIT 8";
$categories_stmt = $conn->prepare($categories_sql);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get featured products from database
$featured_sql = "SELECT p.*, c.name as category_name,
                        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as gallery_image
                 FROM products p
                 LEFT JOIN categories c ON p.category_id = c.id
                 WHERE p.featured = 1 AND p.status = 'active'
                 ORDER BY p.created_at DESC
                 LIMIT 4";
$featured_stmt = $conn->prepare($featured_sql);
$featured_stmt->execute();
$featured_products = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sliders from database
$sliders_sql = "SELECT title, description, image, button_text, button_link
                FROM sliders
                WHERE status = 'active'
                ORDER BY sort_order ASC
                LIMIT 3";
$sliders_stmt = $conn->prepare($sliders_sql);
$sliders_stmt->execute();
$sliders = $sliders_stmt->fetchAll(PDO::FETCH_ASSOC);

// If no sliders in database, use default ones
if (empty($sliders)) {
    $sliders = [
        [
            'title' => 'Exquisite Rings Collection',
            'description' => 'Discover our stunning rings jewelry crafted with precision and passion',
            'image' => 'assets/images/slider1.png',
            'button_text' => 'Shop Now',
            'button_link' => SITE_URL . '/category/rings'
        ],
        [
            'title' => 'Traditional Gold Jewelry',
            'description' => 'Beautiful traditional designs that celebrate our heritage',
            'image' => 'assets/images/slider2.png',
            'button_text' => 'Shop Now',
            'button_link' => SITE_URL . '/products'
        ]
    ];
}
?>

<!-- Hero Slider -->
<!-- Hero Slider -->
<div id="heroSlider" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <?php foreach($sliders as $key => $slider):?>
            <button type="button" data-bs-target="#heroSlider" data-bs-slide-to="<?php echo $key; ?>"
                    class="<?php echo $key === 0 ? 'active' : ''; ?>"></button>
        <?php endforeach; ?>
    </div>

    <div class="carousel-inner">
        <?php foreach($sliders as $key => $slider):
           $slider_image = !empty($slider['image']) ? $slider['image'] : 'assets/images/category-placeholder.jpg';
        ?>
            <div class="carousel-item <?php echo $key === 0 ? 'active' : ''; ?>">
                <!-- Background Image -->
                <div class="hero-bg-image">
                    <?php if($key === 0): ?>
                    <!-- First Slider - Diamond Theme -->
                        <img src="<?php echo $slider_image; ?>"
                             alt="Diamond Collection Background"
                             class="hero-background">
                    <?php else: ?>
                        <!-- Second Slider - Gold Theme -->
                        <img src="<?php echo $slider_image; ?>"
                             alt="Gold Jewelry Background"
                             class="hero-background">
                    <?php endif; ?>
                </div>

                <!-- Content Overlay -->
                <div class="hero-section text-center">
                    <div class="container">
                        <h1 class="display-4 fw-medium mb-4 brand-font"><?php echo $slider['title']; ?></h1>
                        <p class="lead mb-4"><?php echo $slider['description']; ?></p>
                        <a href="<?php echo $slider['button_link']; ?>" class="btn btn-gold btn-lg">
                            <?php echo $slider['button_text']; ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <button class="carousel-control-prev" type="button" data-bs-target="#heroSlider" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroSlider" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
</div>

<!-- Featured Categories Slider -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="section-title brand-font">Shop By Category</h2>

        <?php if (!empty($categories)): ?>
            <div class="categories-slider position-relative">
                <div class="swiper-container" style="overflow-y: scroll; overflow: hidden">
                    <div class="swiper-wrapper" style="height: auto !important">
                        <?php foreach($categories as $category):
                            $category_image = !empty($category['image']) ? $category['image'] : 'assets/images/category-placeholder.jpg';
                            $category_description = !empty($category['description']) ? $category['description'] : 'Explore our beautiful ' . $category['name'] . ' collection';
                        ?>
                            <div class="swiper-slide">
                                <div class="category-slide-card text-center">
                                    <a href="<?php echo SITE_URL; ?>/category/<?php echo urlencode($category['slug']); ?>" class="text-decoration-none">
                                        <div class="category-image-container">
                                            <img src="<?php echo $category_image; ?>"
                                                 alt="<?php echo htmlspecialchars($category['name']); ?>"
                                                 class="category-image">
                                            <div class="category-overlay">
                                                <div class="category-content">
                                                    <h5 class="category-name"><?php echo htmlspecialchars($category['name']); ?></h5>
                                                    <p class="category-desc"><?php echo htmlspecialchars($category_description); ?></p>
                                                    <span class="btn btn-outline-light btn-sm">Shop Now</span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>

                <!-- Pagination -->
                <div class="swiper-pagination"></div>
            </div>
        <?php else: ?>
            <!-- Fallback static categories if no categories in database -->
            <div class="row">
                <div class="col-md-3 col-6 mb-4">
                    <a href="<?php echo SITE_URL; ?>/category/rings" class="text-decoration-none">
                        <div class="card category-card text-center border-0">
                            <div class="card-body">
                                <i class="fas fa-ring fa-3x text-gold mb-3"></i>
                                <h5 class="card-title">Rings</h5>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <a href="<?php echo SITE_URL; ?>/category/necklaces" class="text-decoration-none">
                        <div class="card category-card text-center border-0">
                            <div class="card-body">
                                <i class="fas fa-gem fa-3x text-gold mb-3"></i>
                                <h5 class="card-title">Necklaces</h5>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <a href="<?php echo SITE_URL; ?>/category/earrings" class="text-decoration-none">
                        <div class="card category-card text-center border-0">
                            <div class="card-body">
                                <i class="fas fa-star fa-3x text-gold mb-3"></i>
                                <h5 class="card-title">Earrings</h5>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <a href="<?php echo SITE_URL; ?>/category/bracelets" class="text-decoration-none">
                        <div class="card category-card text-center border-0">
                            <div class="card-body">
                                <i class="fas fa-circle fa-3x text-gold mb-3"></i>
                                <h5 class="card-title">Bracelets</h5>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Featured Products -->
<section class="py-5">
    <div class="container">
        <h2 class="section-title brand-font">Featured Products</h2>
        <div class="row">
            <?php if (!empty($featured_products)): ?>
                <?php foreach($featured_products as $product):
                    $productImage = !empty($product['gallery_image']) ? $product['gallery_image'] : ($product['image'] ?? 'assets/images/placeholder.jpg');
                    $finalPrice = $product['sale_price'] ?? $product['price'];
                    $hasSale = !empty($product['sale_price']) && $product['sale_price'] < $product['price'];
                ?>
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card product-card h-100">
                            <div class="position-relative">
                                <img src="<?php echo $productImage; ?>"
                                     class="card-img-top product-image"
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php if($hasSale): ?>
                                    <span class="position-absolute top-0 start-0 bg-danger text-white px-2 py-1 m-2 small">Sale</span>
                                <?php endif; ?>
                                <?php if($product['featured']): ?>
                                    <span class="position-absolute top-0 end-0 bg-warning text-dark px-2 py-1 m-2 small">Featured</span>
                                <?php endif; ?>
                                <div class="card-img-overlay d-flex align-items-end justify-content-center" style="transition: opacity 0.3s;">
                                    <div class="w-100 text-center">
                                        <a href="<?php echo SITE_URL; ?>/product-<?php echo $product['id']; ?>" class="btn btn-gold btn-sm w-75 mb-2">
                                            Quick View
                                        </a>
                                        <!--<?php if($product['stock_quantity'] > 0): ?>
                                            <button class="btn btn-dark btn-sm w-75 add-to-cart"
                                                    data-product-id="<?php echo $product['id']; ?>">
                                                Add to Cart
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary btn-sm w-75" disabled>
                                                Out of Stock
                                            </button>
                                        <?php endif; ?>-->
                                    </div>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                <div class="price">
                                    <?php if($hasSale): ?>
                                        <span class="old-price me-2"><?php echo CURRENCY . ' ' . number_format($product['price']); ?></span>
                                        <span class="current-price text-gold fw-bold"><?php echo CURRENCY . ' ' . number_format($finalPrice); ?></span>
                                    <?php else: ?>
                                        <span class="current-price text-gold fw-bold"><?php echo CURRENCY . ' ' . number_format($finalPrice); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback if no featured products -->
                <div class="col-12 text-center">
                    <p class="text-muted">No featured products available at the moment.</p>
                    <a href="<?php echo SITE_URL; ?>/products" class="btn btn-gold">Browse All Products</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="section-title brand-font">Why Choose Haroon Jewellery</h2>
        <div class="row">
            <div class="col-md-3 text-center mb-4">
                <i class="fas fa-award fa-3x text-gold mb-3"></i>
                <h5>Quality Certified</h5>
                <p class="text-muted">Hallmarked gold and certified diamonds</p>
            </div>
            <div class="col-md-3 text-center mb-4">
                <i class="fas fa-shipping-fast fa-3x text-gold mb-3"></i>
                <h5>Free Shipping</h5>
                <p class="text-muted">Free delivery across Pakistan</p>
            </div>
            <div class="col-md-3 text-center mb-4">
                <i class="fas fa-undo-alt fa-3x text-gold mb-3"></i>
                <h5>Easy Returns</h5>
                <p class="text-muted">7-day return policy</p>
            </div>
            <div class="col-md-3 text-center mb-4">
                <i class="fas fa-headset fa-3x text-gold mb-3"></i>
                <h5>24/7 Support</h5>
                <p class="text-muted">Dedicated customer support</p>
            </div>
        </div>
    </div>
</section>

<!-- Add Swiper JS and CSS for animated slider -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<style>
.categories-slider {
    padding: 20px 0;
}

.swiper-container {
    width: 100%;
    height: 350px;
    padding: 20px 0;
}

.swiper-slide {
    text-align: center;
    background: #fff;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    width: 200px;
    height: auto;
}

.swiper-slide:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.2);
}

.category-slide-card {
    height: 100%;
}

.category-image-container {
    position: relative;
    height: 250px;
    overflow: hidden;
}

.category-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.category-slide-card:hover .category-image {
    transform: scale(1.1);
}

.category-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.7) 100%);
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding: 20px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.category-slide-card:hover .category-overlay {
    opacity: 1;
}

.category-content {
    color: white;
    text-align: center;
}

.category-name {
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 8px;
    font-family: 'Playfair Display', serif;
}

.category-desc {
    font-size: 0.9rem;
    margin-bottom: 15px;
    opacity: 0.9;
}

.swiper-button-next,
.swiper-button-prev {
    color: var(--primary-color);
    background: rgba(255,255,255,0.9);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.swiper-button-next:after,
.swiper-button-prev:after {
    font-size: 1.2rem;
}

.swiper-pagination-bullet-active {
    background: var(--primary-color);
}

/* Responsive design */
@media (max-width: 768px) {
    .swiper-container {
        height: 300px;
    }

    .category-image-container {
        height: 200px;
    }

    .category-name {
        font-size: 1rem;
    }

    .category-desc {
        font-size: 0.8rem;
    }
}

.hero-section {
    color: white;
    padding: 150px 0;
    min-height: 600px;
    display: flex;
    align-items: center;
    position: relative;
    z-index: 2;
}

.hero-bg-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

.hero-background {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.carousel-item {
    position: relative;
}

.carousel-item::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
/*     background: rgba(0, 0, 0, 0.6); */
    z-index: 1;
}

.carousel-item .container {
    position: relative;
    z-index: 3;
}

/* Custom carousel indicator styles */
.carousel-indicators button {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin: 0 5px;
    background-color: rgba(255,255,255,0.5);
    border: 2px solid transparent;
    display: none
}

.carousel-indicators button.active {
    background-color: var(--primary-color);
    border-color: white;
}

/* Custom carousel control styles */
.carousel-control-prev,
.carousel-control-next {
    width: 60px;
    height: 60px;
    background: rgba(212, 175, 55, 0.8);
    border-radius: 50%;
    top: 50%;
    transform: translateY(-50%);
    margin: 0 20px;
    opacity: 0.8;
    transition: all 0.3s ease;
    z-index: 4;
}

.carousel-control-prev:hover,
.carousel-control-next:hover {
    opacity: 1;
    background: var(--primary-color);
}

.carousel-control-prev {
    left: 20px;
}

.carousel-control-next {
    right: 20px;
}

.carousel-control-prev-icon,
.carousel-control-next-icon {
    width: 25px;
    height: 25px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .hero-section {
        padding: 100px 0;
        min-height: 500px;
    }

    .hero-section h1 {
        font-size: 2.5rem;
    }

    .carousel-control-prev,
    .carousel-control-next {
        width: 50px;
        height: 50px;
        margin: 0 10px;
    }

    .carousel-control-prev-icon,
    .carousel-control-next-icon {
        width: 20px;
        height: 20px;
    }
}

@media (max-width: 576px) {
    .hero-section {
        padding: 80px 0;
        min-height: 400px;
    }

    .hero-section h1 {
        font-size: 2rem;
    }

    .hero-section .lead {
        font-size: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Swiper slider
    const swiper = new Swiper('.swiper-container', {
        slidesPerView: 1,
        spaceBetween: 20,
        loop: true,
//         autoplay: {
//             delay: 3000,
//             disableOnInteraction: false,
//         },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        breakpoints: {
            640: {
                slidesPerView: 2,
            },
            768: {
                slidesPerView: 3,
            },
            1024: {
                slidesPerView: 4,
            },
        }
    });

    // Add to cart functionality for featured products
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            // Add your cart functionality here
            console.log('Add to cart:', productId);
            // You can integrate with your existing cart system
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>