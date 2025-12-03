<?php
$pageTitle = "Refund Policy";
require_once '../config/constants.php';
require_once '../includes/header.php';

$effectiveDate = "05-09-2025";
?>

<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <!-- Header Section -->
            <div class="text-center mb-5">
                <h1 class="display-4 brand-font mb-3">Refund Policy</h1>
                <p class="lead text-muted">Our commitment to your satisfaction</p>
                <div class="d-flex justify-content-center align-items-center text-muted">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <span>Effective Date: <?php echo $effectiveDate; ?></span>
                </div>
            </div>

            <!-- Introduction -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <p class="mb-0">
                        At Haroon Jewellery, we are committed to delivering high-quality artificial jewellery and ensuring your complete satisfaction. This Refund Policy outlines the terms and conditions for returns, refunds, and exchanges for purchases made on <strong>www.haroonjewellery.com</strong>.
                    </p>
                    <p class="mb-0 mt-3">
                        By making a purchase from our website, you agree to the terms outlined in this policy.
                    </p>
                </div>
            </div>

            <!-- 1. General Refund & Return Conditions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light border-0">
                    <h3 class="brand-font mb-0">1. General Refund & Return Conditions</h3>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex align-items-start mb-4">
                        <i class="fas fa-undo fa-2x text-gold me-3 mt-1"></i>
                        <div>
                            <p class="mb-3">To be eligible for a return and refund, the following conditions must be met:</p>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Returns must be initiated within <strong>14 days</strong> from the date of delivery.
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    The item must be unused, in its original packaging, and in the same condition as received.
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    All tags, labels, and protective coverings must be intact.
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Proof of purchase (order number or invoice) must be provided.
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Custom-made or personalized jewellery, unless defective, cannot be returned or refunded.
                    </div>
                </div>
            </div>

            <!-- 2. Non-Refundable Items -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light border-0">
                    <h3 class="brand-font mb-0">2. Non-Refundable Items</h3>
                </div>
                <div class="card-body p-4">
                    <p class="mb-4">The following items are not eligible for refunds or returns:</p>
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-times-circle fa-2x text-danger me-3"></i>
                                <h5 class="mb-0">Defective Exceptions</h5>
                            </div>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-gem text-gold me-2"></i>
                                    Items damaged due to customer misuse
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-gem text-gold me-2"></i>
                                    Jewellery worn, altered, or repaired by a third party
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-gem text-gold me-2"></i>
                                    Items missing parts or accessories not reported within 48 hours
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-ban fa-2x text-warning me-3"></i>
                                <h5 class="mb-0">Other Exclusions</h5>
                            </div>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-gem text-gold me-2"></i>
                                    Gift cards or promotional items
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-gem text-gold me-2"></i>
                                    Items purchased during clearance or final sale
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-gem text-gold me-2"></i>
                                    Products not purchased through our official website
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Refund Process -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light border-0">
                    <h3 class="brand-font mb-0">3. Refund Process</h3>
                </div>
                <div class="card-body p-4">
                    <p class="mb-4">Once your return is received and inspected, we will notify you of the approval or rejection of your refund. If approved, your refund will be processed as follows:</p>

                    <div class="row text-center mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="process-step">
                                <div class="step-number">1</div>
                                <i class="fas fa-box-open fa-2x text-gold mb-2"></i>
                                <p class="mb-0 small">Initiate Return</p>
                                <small class="text-muted">Contact support within 14 days</small>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="process-step">
                                <div class="step-number">2</div>
                                <i class="fas fa-shipping-fast fa-2x text-gold mb-2"></i>
                                <p class="mb-0 small">Ship Item Back</p>
                                <small class="text-muted">Use provided return label</small>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="process-step">
                                <div class="step-number">3</div>
                                <i class="fas fa-search fa-2x text-gold mb-2"></i>
                                <p class="mb-0 small">Quality Inspection</p>
                                <small class="text-muted">3-5 business days</small>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="process-step">
                                <div class="step-number">4</div>
                                <i class="fas fa-money-check-alt fa-2x text-gold mb-2"></i>
                                <p class="mb-0 small">Refund Issued</p>
                                <small class="text-muted">5-10 business days</small>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-success">
                        <i class="fas fa-clock me-2"></i>
                        <strong>Processing Time:</strong> Refunds are typically processed within 5-10 business days after we receive and approve your return. The time it takes for the refund to reflect in your account depends on your payment method and financial institution.
                    </div>
                </div>
            </div>

            <!-- 4. Refund Methods -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light border-0">
                    <h3 class="brand-font mb-0">4. Refund Methods</h3>
                </div>
                <div class="card-body p-4">
                    <p class="mb-4">Refunds will be issued using the original payment method:</p>

                    <div class="row">
                        <div class="col-md-6 text-center mb-4">
                            <div class="payment-method">
                                <i class="fas fa-mobile-alt fa-3x text-gold mb-3"></i>
                                <h5>JazzCash</h5>
                                <p class="small mb-0">Refund to original JazzCash account</p>
                                <small class="text-muted">3-5 business days</small>
                            </div>
                        </div>
                        <div class="col-md-6 text-center mb-4">
                            <div class="payment-method">
                                <i class="fas fa-university fa-3x text-gold mb-3"></i>
                                <h5>Bank Transfer</h5>
                                <p class="small mb-0">Direct bank account transfer</p>
                                <small class="text-muted">5-10 business days</small>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Shipping and handling charges are non-refundable unless the return is due to our error or a defective product.
                    </div>
                </div>
            </div>

            <!-- 5. Exchanges -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light border-0">
                    <h3 class="brand-font mb-0">5. Exchanges</h3>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex align-items-start mb-3">
                        <i class="fas fa-exchange-alt fa-2x text-gold me-3 mt-1"></i>
                        <div>
                            <p class="mb-3">We currently only replace items if they are defective or damaged upon delivery. If you need to exchange an item for the same product in a different size or color, please contact us at <strong>info@haroonjewellery.com</strong>.</p>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-shipping-fast text-gold me-2"></i>
                                    You will be responsible for return shipping costs for exchanges unless the item was defective.
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-box text-gold me-2"></i>
                                    The replacement item will be shipped once the returned item is received and inspected.
                                </li>
                                <li>
                                    <i class="fas fa-calendar-alt text-gold me-2"></i>
                                    Exchange requests must be made within 14 days of delivery.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. Damaged or Defective Items -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light border-0">
                    <h3 class="brand-font mb-0">6. Damaged or Defective Items</h3>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-tools fa-2x text-gold me-3 mt-1"></i>
                        <div>
                            <p class="mb-3">If you receive a damaged or defective item, please contact us within <strong>48 hours</strong> of delivery. We will arrange a return and provide a full refund or replacement at no additional cost to you.</p>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Required:</strong> Please include clear photographs of the damaged/defective item and its packaging when contacting us.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 7. Contact Information -->
            <div class="card border-primary mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-headset me-2"></i>Need Help with Returns or Refunds?</h4>
                </div>
                <div class="card-body">
                    <p class="mb-4">For any questions regarding our refund policy or to initiate a return, please contact our customer support team:</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-envelope fa-lg text-gold me-3"></i>
                                <div>
                                    <h6 class="mb-1">Email Support</h6>
                                    <p class="mb-0">info@haroonjewellery.com</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-phone fa-lg text-gold me-3"></i>
                                <div>
                                    <h6 class="mb-1">Phone Support</h6>
                                    <p class="mb-0">0306-0000905</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-clock fa-lg text-gold me-3"></i>
                                <div>
                                    <h6 class="mb-1">Response Time</h6>
                                    <p class="mb-0">24-48 hours (Mon-Sat, 9 AM - 6 PM PST)</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-alt fa-lg text-gold me-3"></i>
                                <div>
                                    <h6 class="mb-1">Required Information</h6>
                                    <p class="mb-0">Order number, product details, reason for return</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Last Updated -->
            <div class="text-center text-muted">
                <small>
                    <i class="fas fa-clock me-1"></i>
                    This policy was last updated on <?php echo $effectiveDate; ?>
                </small>
            </div>
        </div>
    </div>
</div>

<style>
.refund-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.process-step {
    padding: 20px;
    border-radius: 10px;
    background: #f8f9fa;
    transition: all 0.3s ease;
    position: relative;
    height: 100%;
}

.process-step:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-5px);
}

.process-step:hover i,
.process-step:hover .step-number {
    color: white !important;
    border-color: white;
}

.step-number {
    position: absolute;
    top: -12px;
    left: 50%;
    transform: translateX(-50%);
    width: 30px;
    height: 30px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    border: 3px solid white;
}

.payment-method {
    padding: 25px 15px;
    border-radius: 10px;
    background: #f8f9fa;
    transition: all 0.3s ease;
    height: 100%;
}

.payment-method:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-3px);
}

.payment-method:hover i,
.payment-method:hover h5 {
    color: white !important;
}

.text-gold {
    color: var(--primary-color) !important;
}

.brand-font {
/*     font-family: 'Playfair Display', serif; */
}

.card {
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.card:hover {
    border-color: var(--primary-color);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.alert {
    border: none;
    border-radius: 8px;
}

.alert-info {
    background: rgba(23, 162, 184, 0.1);
    color: #0c5460;
}

.alert-warning {
    background: rgba(255, 193, 7, 0.1);
    color: #856404;
}

.alert-success {
    background: rgba(40, 167, 69, 0.1);
    color: #155724;
}

.alert-danger {
    background: rgba(220, 53, 69, 0.1);
    color: #721c24;
}

@media (max-width: 768px) {
    .display-4 {
        font-size: 2.5rem;
    }

    .process-step, .payment-method {
        margin-bottom: 1.5rem;
    }
}

/* Smooth scrolling for anchor links */
html {
    scroll-behavior: smooth;
}

/* Print styles */
@media print {
    .card {
        border: 1px solid #000 !important;
        break-inside: avoid;
    }

    .btn, .alert {
        display: none !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scrolling for navigation
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

    // Add print functionality
    const printButton = document.createElement('button');
    printButton.innerHTML = '<i class="fas fa-print me-2"></i>Print Policy';
    printButton.className = 'btn btn-outline-primary position-fixed';
    printButton.style.bottom = '20px';
    printButton.style.right = '20px';
    printButton.style.zIndex = '1000';

    printButton.addEventListener('click', function() {
        window.print();
    });

    document.body.appendChild(printButton);

    // Add section highlighting on scroll
    const sections = document.querySelectorAll('.card');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.borderColor = 'var(--primary-color)';
                entry.target.style.boxShadow = '0 5px 15px rgba(212, 175, 55, 0.2)';
            } else {
                entry.target.style.borderColor = '#e9ecef';
                entry.target.style.boxShadow = 'none';
            }
        });
    }, {
        threshold: 0.1
    });

    sections.forEach(section => observer.observe(section));

    // Add process step hover effects
    const processSteps = document.querySelectorAll('.process-step');
    processSteps.forEach(step => {
        step.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });

        step.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>