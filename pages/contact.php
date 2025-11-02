<?php
require_once '../config/constants.php';
require_once '../includes/header.php';

// Initialize database connection for contact form submission
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$pageTitle = "Contact Us";

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Basic validation
    $errors = [];

    if (empty($name)) {
        $errors[] = "Name is required";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }

    if (empty($subject)) {
        $errors[] = "Subject is required";
    }

    if (empty($message)) {
        $errors[] = "Message is required";
    } elseif (strlen($message) < 10) {
        $errors[] = "Message should be at least 10 characters long";
    }

    if (empty($errors)) {
        try {
            // Insert into database
            $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, phone, subject, message, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$name, $email, $phone, $subject, $message]);

            $success_message = "Thank you for your message! We'll get back to you within 24 hours.";

            // Clear form fields
            $name = $email = $phone = $subject = $message = '';

        } catch (PDOException $e) {
            $error_message = "Sorry, there was an error sending your message. Please try again later.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<div class="container-fluid py-5">
    <!-- Hero Section -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-8 text-center">
            <h1 class="display-4 brand-font mb-4">Get In Touch</h1>
            <p class="lead text-muted">We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
        </div>
    </div>

    <div class="row">
        <!-- Contact Information -->
        <div class="col-lg-4 mb-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h3 class="brand-font mb-4">Contact Information</h3>

                    <!-- Store Address -->
                    <div class="d-flex mb-4">
                        <div class="flex-shrink-0">
                            <i class="fas fa-map-marker-alt fa-2x text-gold"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5>Store Address</h5>
                            <p class="text-muted mb-0">
                                Islamabad Capital Territory, Pakistan
                            </p>
                        </div>
                    </div>

                    <!-- Phone Numbers -->
                    <div class="d-flex mb-4">
                        <div class="flex-shrink-0">
                            <i class="fas fa-phone fa-2x text-gold"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5>Phone Numbers</h5>
                            <p class="text-muted mb-0">
                                0306-0000905<br>
                                0323-1441230
                            </p>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="d-flex mb-4">
                        <div class="flex-shrink-0">
                            <i class="fas fa-envelope fa-2x text-gold"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5>Email Address</h5>
                            <p class="text-muted mb-0">
                                info@haroonjewellery.com
                            </p>
                        </div>
                    </div>

                    <!-- Store Hours -->
                    <div class="d-flex mb-4">
                        <div class="flex-shrink-0">
                            <i class="fas fa-clock fa-2x text-gold"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5>Store Hours</h5>
                            <p class="text-muted mb-0">
                                <strong>Mon - Sat:</strong> 10:00 AM - 8:00 PM<br>
                                <strong>Sunday:</strong> 12:00 PM - 6:00 PM
                            </p>
                        </div>
                    </div>

                    <!-- Social Media -->
                    <!--<div class="d-flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-share-alt fa-2x text-gold"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5>Follow Us</h5>
                            <div class="social-links mt-2">
                                <a href="#" class="text-decoration-none me-3">
                                    <i class="fab fa-facebook fa-2x text-muted"></i>
                                </a>
                                <a href="#" class="text-decoration-none me-3">
                                    <i class="fab fa-instagram fa-2x text-muted"></i>
                                </a>
                                <a href="#" class="text-decoration-none me-3">
                                    <i class="fab fa-twitter fa-2x text-muted"></i>
                                </a>
                                <a href="#" class="text-decoration-none">
                                    <i class="fab fa-whatsapp fa-2x text-muted"></i>
                                </a>
                            </div>
                        </div>
                    </div>-->
                </div>
            </div>
        </div>

        <!-- Contact Form -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="brand-font mb-4">Send Us a Message</h3>

                    <!-- Success/Error Messages -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="contactForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?php echo htmlspecialchars($name ?? ''); ?>"
                                       required>
                                <div class="invalid-feedback">Please enter your name.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                       required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                                <div class="form-text">Optional</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="subject" class="form-label">Subject *</label>
                                <select class="form-select" id="subject" name="subject" required>
                                    <option value="">Select a subject</option>
                                    <option value="General Inquiry" <?php echo ($subject ?? '') === 'General Inquiry' ? 'selected' : ''; ?>>General Inquiry</option>
                                    <option value="Product Information" <?php echo ($subject ?? '') === 'Product Information' ? 'selected' : ''; ?>>Product Information</option>
                                    <option value="Custom Order" <?php echo ($subject ?? '') === 'Custom Order' ? 'selected' : ''; ?>>Custom Order</option>
                                    <option value="Repair Services" <?php echo ($subject ?? '') === 'Repair Services' ? 'selected' : ''; ?>>Repair Services</option>
                                    <option value="Wholesale Inquiry" <?php echo ($subject ?? '') === 'Wholesale Inquiry' ? 'selected' : ''; ?>>Wholesale Inquiry</option>
                                    <option value="Complaint" <?php echo ($subject ?? '') === 'Complaint' ? 'selected' : ''; ?>>Complaint</option>
                                    <option value="Other" <?php echo ($subject ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <div class="invalid-feedback">Please select a subject.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="6"
                                      required placeholder="Tell us how we can help you..."><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                            <div class="invalid-feedback">Please enter your message (minimum 10 characters).</div>
                            <div class="form-text">Minimum 10 characters required.</div>
                        </div>

                        <!--<div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter" checked>
                                <label class="form-check-label" for="newsletter">
                                    Subscribe to our newsletter for updates and special offers
                                </label>
                            </div>
                        </div>-->

                        <button type="submit" class="btn btn-gold btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Store Location Map -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="brand-font mb-4">Find Our Store</h3>
                    <div class="row">
                        <div class="col-lg-12">
                            <!-- Google Maps Embed -->
                            <div class="map-container rounded overflow-hidden">
                                <iframe
                                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d212749.85018394596!2d72.91326302055666!3d33.61624834897513!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x38dfbfd07891722f%3A0x6059515c3b91c979!2sIslamabad%2C%20Islamabad%20Capital%20Territory%2C%20Pakistan!5e0!3m2!1sen!2s!4v1727836147890!5m2!1sen!2s"
                                    width="100%"
                                    height="300"
                                    style="border:0;"
                                    allowfullscreen=""
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade">
                                </iframe>
                            </div>
                        </div>
                        <!--<div class="col-lg-6">
                            <div class="ps-lg-4">
                                <h5 class="mb-3">Getting Here</h5>
                                <div class="mb-4">
                                    <h6><i class="fas fa-subway text-gold me-2"></i>By Metro</h6>
                                    <p class="text-muted mb-0">Get off at Gulberg Metro Station. We're just a 5-minute walk away.</p>
                                </div>
                                <div class="mb-4">
                                    <h6><i class="fas fa-bus text-gold me-2"></i>By Bus</h6>
                                    <p class="text-muted mb-0">Multiple bus routes stop near Main Boulevard Gulberg.</p>
                                </div>
                                <div class="mb-4">
                                    <h6><i class="fas fa-car text-gold me-2"></i>By Car</h6>
                                    <p class="text-muted mb-0">Ample parking available in the building basement.</p>
                                </div>
                                <div class="mb-4">
                                    <h6><i class="fas fa-taxi text-gold me-2"></i>By Ride-hailing</h6>
                                    <p class="text-muted mb-0">Easy access via Uber, Careem, and other ride-hailing services.</p>
                                </div>
                            </div>
                        </div>-->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ Section -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="brand-font mb-4">Frequently Asked Questions</h3>
                    <div class="accordion" id="faqAccordion">
                        <!-- FAQ Item 1 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faqHeading1">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse1">
                                    Do you offer custom jewelry design services?
                                </button>
                            </h2>
                            <div id="faqCollapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes, we specialize in custom jewelry design. Our master craftsmen can bring your unique vision to life.
                                    Contact us to schedule a consultation with our design team.
                                </div>
                            </div>
                        </div>

                        <!-- FAQ Item 2 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faqHeading2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse2">
                                    What is your jewelry return policy?
                                </button>
                            </h2>
                            <div id="faqCollapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We offer a 7-day return policy for unworn jewelry in original condition with all tags and certificates.
                                    Custom pieces and engraved items cannot be returned.
                                </div>
                            </div>
                        </div>

                        <!-- FAQ Item 3 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faqHeading3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse3">
                                    Do you provide jewelry repair services?
                                </button>
                            </h2>
                            <div id="faqCollapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes, we offer comprehensive jewelry repair services including resizing, stone replacement,
                                    chain repair, and polishing. Bring your jewelry to our store for a free assessment.
                                </div>
                            </div>
                        </div>

                        <!-- FAQ Item 4 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faqHeading4">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse4">
                                    Are your diamonds and gemstones certified?
                                </button>
                            </hh2>
                            <div id="faqCollapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Absolutely. All our diamonds above 0.5 carats come with GIA certification, and other gemstones
                                    are certified by reputable gemological laboratories. We provide all certificates with your purchase.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.contact-info-card .card-body {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.social-links a:hover i {
    color: var(--primary-color) !important;
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

.map-container {
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.accordion-button {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    font-weight: 500;
}

.accordion-button:not(.collapsed) {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.accordion-item {
    border: 1px solid #dee2e6;
    margin-bottom: 10px;
    border-radius: 5px !important;
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
    padding: 12px 30px;
    font-weight: 500;
}

.btn-gold:hover {
    background-color: var(--accent-color);
    border-color: var(--accent-color);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
}

@media (max-width: 768px) {
    .display-4 {
        font-size: 2.5rem;
    }

    .map-container {
        margin-bottom: 2rem;
    }

    .social-links a {
        margin-right: 15px !important;
    }

    .social-links i {
        font-size: 1.5rem !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            let valid = true;

            // Clear previous validation
            const inputs = contactForm.querySelectorAll('.is-invalid');
            inputs.forEach(input => input.classList.remove('is-invalid'));

            // Validate name
            const name = document.getElementById('name');
            if (!name.value.trim()) {
                name.classList.add('is-invalid');
                valid = false;
            }

            // Validate email
            const email = document.getElementById('email');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email.value.trim() || !emailRegex.test(email.value)) {
                email.classList.add('is-invalid');
                valid = false;
            }

            // Validate subject
            const subject = document.getElementById('subject');
            if (!subject.value) {
                subject.classList.add('is-invalid');
                valid = false;
            }

            // Validate message
            const message = document.getElementById('message');
            if (!message.value.trim() || message.value.trim().length < 10) {
                message.classList.add('is-invalid');
                valid = false;
            }

            if (!valid) {
                e.preventDefault();

                // Scroll to first error
                const firstError = contactForm.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    firstError.focus();
                }
            }
        });
    }

    // Character count for message
    const messageTextarea = document.getElementById('message');
    if (messageTextarea) {
        messageTextarea.addEventListener('input', function() {
            const charCount = this.value.length;
            const minChars = 10;

            if (charCount < minChars) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    }

    // Smooth scrolling for FAQ accordion
    document.querySelectorAll('.accordion-button').forEach(button => {
        button.addEventListener('click', function() {
            this.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        });
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>