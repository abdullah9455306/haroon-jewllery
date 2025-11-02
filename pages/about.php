<?php
require_once '../config/constants.php';
require_once '../includes/header.php';

$pageTitle = "About Us";
?>

<div class="container-fluid py-5">
    <!-- Hero Section -->
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-5">
                <h1 class="display-4 brand-font mb-4">About Us</h1>
            </div>
        </div>
    </div>

    <!-- Story Section -->
    <div class="row align-items-center mb-5">
        <div class="col-lg-4">
            <img src="../assets/images/about-us.jpg" alt="Our Story" class="img-fluid rounded shadow" style="height: 400px; width: 100%; object-fit: none;">
        </div>
        <div class="col-lg-8">
            <div class="ps-lg-5">
                <p class="mb-4">At Haroon Jewellery, we believe that every piece tells a story. With a passion for timeless beauty and fine craftsmanship, we create jewellery that blends tradition with modern elegance. From intricately designed gold sets to contemporary diamond pieces, our mission is to bring you jewellery that celebrates life’s most precious moments. Trust, quality, and excellence are the values we are built on, making us more than just a jeweller – we are part of your celebrations.</p>
                <!--<div class="d-flex align-items-center">
                    <div class="me-4">
                        <h3 class="text-gold mb-0">35+</h3>
                        <small class="text-muted">Years of Excellence</small>
                    </div>
                    <div class="me-4">
                        <h3 class="text-gold mb-0">50K+</h3>
                        <small class="text-muted">Happy Customers</small>
                    </div>
                    <div>
                        <h3 class="text-gold mb-0">100+</h3>
                        <small class="text-muted">Awards Won</small>
                    </div>
                </div>-->
            </div>
        </div>
    </div>

    <!-- Mission & Vision -->
    <div class="row mb-5">
        <div class="col-lg-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="mb-4">
                        <i class="fas fa-bullseye fa-3x text-gold"></i>
                    </div>
                    <h4 class="brand-font mb-3">Our Mission</h4>
                    <p class="text-muted">To create exceptional jewelry that celebrates life's precious moments while maintaining the highest standards of quality, craftsmanship, and ethical practices.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="mb-4">
                        <i class="fas fa-eye fa-3x text-gold"></i>
                    </div>
                    <h4 class="brand-font mb-3">Our Vision</h4>
                    <p class="text-muted">To be the most trusted jewelry brand in Pakistan, known for our innovative designs, uncompromising quality, and exceptional customer experiences.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Values Section -->
    <div class="row mb-5">
        <div class="col-12 text-center mb-5">
            <h2 class="brand-font">Our Values</h2>
            <p class="text-muted">The principles that guide everything we do</p>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="text-center">
                <div class="value-icon mb-3">
                    <i class="fas fa-gem fa-2x text-gold"></i>
                </div>
                <h5>Quality Craftsmanship</h5>
                <p class="text-muted small">Every piece is meticulously crafted with attention to detail and precision.</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="text-center">
                <div class="value-icon mb-3">
                    <i class="fas fa-award fa-2x text-gold"></i>
                </div>
                <h5>Authenticity</h5>
                <p class="text-muted small">We use only certified materials and provide complete transparency.</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="text-center">
                <div class="value-icon mb-3">
                    <i class="fas fa-heart fa-2x text-gold"></i>
                </div>
                <h5>Customer First</h5>
                <p class="text-muted small">Your satisfaction is our priority. We're here to make your experience exceptional.</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="text-center">
                <div class="value-icon mb-3">
                    <i class="fas fa-leaf fa-2x text-gold"></i>
                </div>
                <h5>Sustainability</h5>
                <p class="text-muted small">We're committed to ethical sourcing and environmentally responsible practices.</p>
            </div>
        </div>
    </div>

    <!-- Craftsmanship Section -->
    <div class="row mb-5">
        <div class="col-12 text-center mb-5">
            <h2 class="brand-font">The Art of Craftsmanship</h2>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card border-0 h-100">
                <div class="card-body text-center">
                    <div class="process-step mb-3">
                        <span class="step-number">1</span>
                    </div>
                    <h5>Design & Concept</h5>
                    <p class="text-muted">Our designers create unique concepts inspired by tradition and modern aesthetics.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card border-0 h-100">
                <div class="card-body text-center">
                    <div class="process-step mb-3">
                        <span class="step-number">2</span>
                    </div>
                    <h5>Precision Crafting</h5>
                    <p class="text-muted">Master artisans bring designs to life with exceptional skill and attention to detail.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card border-0 h-100">
                <div class="card-body text-center">
                    <div class="process-step mb-3">
                        <span class="step-number">3</span>
                    </div>
                    <h5>Quality Assurance</h5>
                    <p class="text-muted">Every piece undergoes rigorous quality checks before reaching our customers.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Section -->
    <!--<div class="row mb-5">
        <div class="col-12 text-center mb-5">
            <h2 class="brand-font">Meet Our Team</h2>
            <p class="text-muted">The talented people behind our beautiful creations</p>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card team-card border-0 text-center">
                <div class="card-body">
                    <div class="team-image mb-3 mx-auto">
                        <img src="../assets/images/team-1.jpg" alt="Haroon Ahmed" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                    </div>
                    <h5 class="mb-1">Haroon Ahmed</h5>
                    <p class="text-muted small mb-2">Founder & Master Craftsman</p>
                    <p class="small text-muted">35+ years of experience in traditional jewelry making</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card team-card border-0 text-center">
                <div class="card-body">
                    <div class="team-image mb-3 mx-auto">
                        <img src="../assets/images/team-2.jpg" alt="Ayesha Khan" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                    </div>
                    <h5 class="mb-1">Ayesha Khan</h5>
                    <p class="text-muted small mb-2">Head Designer</p>
                    <p class="small text-muted">Blending traditional motifs with contemporary designs</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card team-card border-0 text-center">
                <div class="card-body">
                    <div class="team-image mb-3 mx-auto">
                        <img src="../assets/images/team-3.jpg" alt="Ali Raza" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                    </div>
                    <h5 class="mb-1">Ali Raza</h5>
                    <p class="text-muted small mb-2">Gemologist</p>
                    <p class="small text-muted">Certified expert in diamond and precious stone evaluation</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card team-card border-0 text-center">
                <div class="card-body">
                    <div class="team-image mb-3 mx-auto">
                        <img src="../assets/images/team-4.jpg" alt="Fatima Noor" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                    </div>
                    <h5 class="mb-1">Fatima Noor</h5>
                    <p class="text-muted small mb-2">Customer Experience Manager</p>
                    <p class="small text-muted">Ensuring every customer feels valued and special</p>
                </div>
            </div>
        </div>
    </div>-->

    <!-- Certifications & Awards -->
    <!--<div class="row mb-5">
        <div class="col-12 text-center mb-5">
            <h2 class="brand-font">Certifications & Awards</h2>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-4 text-center">
            <div class="certification-item">
                <i class="fas fa-certificate fa-3x text-gold mb-3"></i>
                <h6 class="mb-1">Hallmarked Gold</h6>
                <small class="text-muted">Certified Quality</small>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-4 text-center">
            <div class="certification-item">
                <i class="fas fa-gem fa-3x text-gold mb-3"></i>
                <h6 class="mb-1">GIA Certified</h6>
                <small class="text-muted">Diamond Quality</small>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-4 text-center">
            <div class="certification-item">
                <i class="fas fa-award fa-3x text-gold mb-3"></i>
                <h6 class="mb-1">Best Jewelry 2023</h6>
                <small class="text-muted">Pakistan Awards</small>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-4 text-center">
            <div class="certification-item">
                <i class="fas fa-star fa-3x text-gold mb-3"></i>
                <h6 class="mb-1">Customer Choice</h6>
                <small class="text-muted">5-Star Rating</small>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-4 text-center">
            <div class="certification-item">
                <i class="fas fa-shield-alt fa-3x text-gold mb-3"></i>
                <h6 class="mb-1">ISO Certified</h6>
                <small class="text-muted">Quality Management</small>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-4 text-center">
            <div class="certification-item">
                <i class="fas fa-handshake fa-3x text-gold mb-3"></i>
                <h6 class="mb-1">Trusted Partner</h6>
                <small class="text-muted">Since 1985</small>
            </div>
        </div>
    </div>-->

    <!-- Visit Us Section -->
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card bg-light border-0">
                <div class="card-body text-center p-5">
                    <h3 class="brand-font mb-3">Visit Our Store</h3>
                    <p class="text-muted mb-4">Experience the beauty of our collections in person at our flagship store in Lahore.</p>
                    <div class="row text-start">
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-map-marker-alt text-gold me-2"></i>Address:</strong>
                            <p class="mb-0">Islamabad Capital Territory, Pakistan</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-clock text-gold me-2"></i>Store Hours:</strong>
                            <p class="mb-0">Mon - Sat: 10:00 AM - 8:00 PM<br>Sunday: 12:00 PM - 6:00 PM</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-phone text-gold me-2"></i>Phone:</strong>
                            <p class="mb-0">0306-0000905</p>
                            <p class="mb-0">0323-1441230</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-envelope text-gold me-2"></i>Email:</strong>
                            <p class="mb-0">info@haroonjewellery.com</p>
                        </div>
                    </div>
                    <a href="contact.php" class="btn btn-gold mt-3">Get Directions</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hero-section {
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(44, 62, 80, 0.1) 100%);
}

.value-icon {
    width: 80px;
    height: 80px;
    background: rgba(212, 175, 55, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.process-step {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 0 auto;
}

.step-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    font-size: 1.5rem;
    font-weight: bold;
    margin: 0 auto;
}

.team-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.team-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.certification-item {
    padding: 20px;
    transition: transform 0.3s ease;
}

.certification-item:hover {
    transform: translateY(-3px);
}

.text-gold {
    color: var(--primary-color) !important;
}

.brand-font {
/*     font-family: 'Playfair Display', serif; */
}

.card {
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.card:hover {
    border-color: var(--primary-color);
}

@media (max-width: 768px) {
    .display-4 {
        font-size: 2.5rem;
    }

    .team-image img {
        width: 100px !important;
        height: 100px !important;
    }
}

/* Animation for numbers */
@keyframes countUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-numbers {
    animation: countUp 1s ease-out;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate numbers when they come into view
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-numbers');
            }
        });
    });

    const numberElements = document.querySelectorAll('h3.text-gold');
    numberElements.forEach(el => observer.observe(el));

    // Add smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>