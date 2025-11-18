<!-- Footer -->
<footer class="footer bg-dark" style="bottom: -38px;width: 100%;position: relative;">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4">
                <h5 class="brand-font mb-4"><img src="<?php echo SITE_URL; ?>/assets/images/logo.webp" style="width: 130px;"/></h5>
                <p class="text-light">Crafting exquisite jewelry pieces. We bring you the finest quality gold, diamonds, and precious stones with timeless designs.</p>
                <div class="social-icons mt-4">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>

            <div class="col-lg-2 col-md-6 mb-4">
                <h5 class="mb-4">Quick Links</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="index.php">Home</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/pages/products.php">Products</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/pages/about.php">About Us</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/pages/contact.php">Contact</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/pages/privacy.php">Privacy Policy</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="mb-4">Categories</h5>
                <ul class="list-unstyled">
                    <?php
                    // Get popular categories for footer
                    $popularCategories = getPopularCategories(5);
                    if (!empty($popularCategories)):
                        foreach ($popularCategories as $category):
                    ?>
                    <li class="mb-2">
                        <a href="<?php echo SITE_URL; ?>/pages/products.php?category=<?php echo urlencode($category['slug']); ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                    </li>
                    <?php
                        endforeach;
                    else:
                    ?>
                    <!-- Fallback if no categories found -->
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/pages/products.php?category=rings">Rings</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/pages/products.php?category=necklaces">Necklaces</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/pages/products.php?category=earrings">Earrings</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/pages/products.php?category=bracelets">Bracelets</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/pages/products.php?category=bangles">Bangles</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="mb-4">Contact Info</h5>
                <ul class="list-unstyled text-light">
                    <li class="mb-3">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        Islamabad Capital Territory, Pakistan
                    </li>
                    <li class="mb-3">
                        <i class="fas fa-phone me-2"></i>
                        0306-0000905, 0323-1441230
                    </li>
                    <li class="mb-3">
                        <i class="fas fa-envelope me-2"></i>
                        info@haroonjewellery.com
                    </li>
                    <li class="mb-3">
                        <i class="fas fa-clock me-2"></i>
                        Mon - Sat: 10AM - 8PM
                    </li>
                </ul>
            </div>
        </div>

        <hr class="my-4 bg-light">

        <div class="row align-items-center">
            <div class="col-md-12 text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Haroon Jewellery. All rights reserved.</p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script>
    // Cart functionality
    function updateCartQuantity(productId, change) {
        // AJAX call to update cart
    }

    // Search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchForm = document.getElementById('searchForm');
        if(searchForm) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const searchTerm = document.getElementById('searchInput').value;
                window.location.href = `products.php?search=${encodeURIComponent(searchTerm)}`;
            });
        }
    });
</script>
</body>
</html>