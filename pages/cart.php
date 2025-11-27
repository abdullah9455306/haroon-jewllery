<?php
$pageTitle = "Shopping Cart";
require_once '../config/constants.php';

// Start output buffering to prevent any output before headers
ob_start();

// Handle cart actions via AJAX FIRST, before any other includes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Set proper headers
    header('Content-Type: application/json');

    // Initialize database connection for AJAX requests
    require_once '../config/database.php';
    require_once '../helpers/cart_helper.php';

    $db = new Database();
    $conn = $db->getConnection();
    $cartHelper = new CartHelper();

    $response = ['success' => false, 'message' => '', 'cart_count' => 0];

    try {
        $action = $_POST['action'] ?? '';
        $itemId = $_POST['item_id'] ?? '';
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

        if (empty($action) || empty($itemId)) {
            throw new Exception('Missing required parameters');
        }

        if (isset($_SESSION['user_id'])) {
            // Handle logged-in user
            if ($action === 'update') {
                $success = $cartHelper->updateCartItem($_SESSION['user_id'], $itemId, $quantity);
                if (!$success) {
                    throw new Exception('Failed to update cart item');
                }
                $response['success'] = true;
                $response['message'] = 'Cart updated successfully';
            } elseif ($action === 'remove') {
                $success = $cartHelper->removeFromCart($_SESSION['user_id'], $itemId);
                if (!$success) {
                    throw new Exception('Failed to remove cart item');
                }
                $response['success'] = true;
                $response['message'] = 'Item removed successfully';
            }
        } else {
            // Handle guest user
            if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            if ($action === 'update') {
                $found = false;
                foreach ($_SESSION['cart'] as &$item) {
                    if (isset($item['id']) && $item['id'] == $itemId) {
                        $item['quantity'] = max(1, $quantity);
                        $found = true;
                        $response['success'] = true;
                        $response['message'] = 'Cart updated successfully';
                        break;
                    }
                }
                if (!$found) {
                    throw new Exception('Item not found in cart');
                }
            } elseif ($action === 'remove') {
                $initialCount = count($_SESSION['cart']);
                $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], function($item) use ($itemId) {
                    return !(isset($item['id']) && $item['id'] == $itemId);
                }));

                if (count($_SESSION['cart']) < $initialCount) {
                    $response['success'] = true;
                    $response['message'] = 'Item removed successfully';
                } else {
                    throw new Exception('Item not found in cart');
                }
            }
        }

        // Update cart count in session
        updateCartCountInSession();
        $response['cart_count'] = $_SESSION['cart_count'] ?? 0;

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    // Clear any output buffer and send JSON response
    ob_end_clean();
    echo json_encode($response);
    exit;
}

// Function to update cart count in session
function updateCartCountInSession() {
    if (isset($_SESSION['user_id'])) {
        // For logged-in users, get count from database
        global $cartHelper;
        $cartItems = $cartHelper->getCartItems($_SESSION['user_id']);
        $totalCount = 0;
        foreach ($cartItems as $item) {
            $totalCount += $item['quantity'];
        }
        $_SESSION['cart_count'] = $totalCount;
    } else {
        // For guest users, get count from session
        $totalCount = 0;
        if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $item) {
                $totalCount += $item['quantity'];
            }
        }
        $_SESSION['cart_count'] = $totalCount;
    }
}

// Continue with normal page load
require_once '../includes/header.php';

// Initialize database connection
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Include cart helper
require_once '../helpers/cart_helper.php';
$cartHelper = new CartHelper();

// Initialize cart count if not set
if (!isset($_SESSION['cart_count'])) {
    updateCartCountInSession();
}

// Pagination settings
$itemsPerPage = 5; // Number of cart items per page
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Initialize variables
$cartItems = [];
$total = 0;
$allCartItems = [];

if (isset($_SESSION['user_id'])) {
    // Get cart items from database for logged-in users
    $allCartItems = $cartHelper->getCartItems($_SESSION['user_id']);
} else {
    // Get cart items from session for guests
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $allCartItems = $_SESSION['cart'];
    }
}

// Calculate total for all items
foreach ($allCartItems as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Paginate cart items
$totalItems = count($allCartItems);
$totalPages = ceil($totalItems / $itemsPerPage);
$offset = ($currentPage - 1) * $itemsPerPage;
$cartItems = array_slice($allCartItems, $offset, $itemsPerPage);
?>

<!-- Rest of your HTML remains exactly the same -->
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8">
            <h2 class="mb-4 brand-font">Shopping Cart</h2>

            <div id="cartMessage"></div>

            <?php if(empty($allCartItems)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">Your cart is empty</h4>
                    <p class="text-muted mb-4">Browse our collection and add some items to your cart</p>
                    <a href="<?php echo SITE_URL; ?>/products" class="btn btn-gold">Continue Shopping</a>
                </div>
            <?php else: ?>
                <!-- Cart Items Count and Pagination Info -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="text-muted mb-0">
                        Showing <?php echo count($cartItems); ?> of <?php echo $totalItems; ?> item(s)
                    </p>

                    <?php if ($totalPages > 1): ?>
                    <div class="pagination-info">
                        <nav aria-label="Cart pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <!-- Previous Page -->
                                <li class="page-item <?php echo $currentPage == 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Next Page -->
                                <li class="page-item <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-body" id="cartItemsContainer">
                        <?php foreach($cartItems as $item):
                            $item_total = $item['price'] * $item['quantity'];
                            $itemId = isset($item['id']) ? $item['id'] : $item['product_id'];

                            // Get product image - handle both database and session cart
                            if (isset($_SESSION['user_id'])) {
                                // For database cart, image is already fetched in getCartItems()
                                $productImage = !empty($item['image']) ? $item['image'] : 'assets/images/placeholder.jpg';
                            } else {
                                // For session cart, use the stored image path
                                $productImage = $item['image'] ?? 'assets/images/placeholder.jpg';
                            }

                            // Ensure the image path is correct
                            if (!empty($productImage) && !filter_var($productImage, FILTER_VALIDATE_URL)) {
                                // Prepend SITE_URL if it's a relative path
                                $productImage = SITE_URL . '/' . $productImage;
                            }
                        ?>
                            <div class="row align-items-center mb-4 pb-4 border-bottom cart-item" data-item-id="<?php echo $itemId; ?>">
                                <div class="col-md-2">
                                    <img src="<?php echo htmlspecialchars($productImage); ?>"
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         class="img-fluid rounded"
                                         style="height: 80px; width: 80px; object-fit: cover;">
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                    <p class="text-muted small mb-0">SKU: <?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></p>
                                    <?php if (isset($item['stock_quantity'])): ?>
                                        <p class="small mb-0 <?php echo $item['stock_quantity'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $item['stock_quantity'] > 0 ? 'In Stock' : 'Out of Stock'; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-2">
                                    <span class="price"><?php echo CURRENCY . ' ' . number_format($item['price']); ?></span>
                                </div>
                                <div class="col-md-2">
                                    <div class="input-group input-group-sm">
                                        <button class="btn btn-outline-secondary decrease-qty"
                                                type="button"
                                                data-item-id="<?php echo $itemId; ?>">
                                            -
                                        </button>
                                        <input type="text"
                                               class="form-control text-center quantity-input"
                                               value="<?php echo $item['quantity']; ?>"
                                               data-item-id="<?php echo $itemId; ?>"
                                               readonly>
                                        <button class="btn btn-outline-secondary increase-qty"
                                                type="button"
                                                data-item-id="<?php echo $itemId; ?>"
                                                data-max-stock="<?php echo $item['stock_quantity'] ?? 999; ?>">
                                            +
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-1">Max: <?php echo $item['stock_quantity'] ?? 'N/A'; ?></small>
                                </div>
                                <div class="col-md-2">
                                    <span class="fw-bold item-total"><?php echo CURRENCY . ' ' . number_format($item_total); ?></span>
                                    <button class="btn btn-sm btn-outline-danger mt-2 remove-item"
                                            type="button"
                                            data-item-id="<?php echo $itemId; ?>"
                                            data-item-name="<?php echo htmlspecialchars($item['name']); ?>"
                                            style="display: block; width: 100%;">
                                        <i class="fas fa-trash me-1"></i>Remove
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Bottom Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <p class="text-muted mb-0">
                        Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>
                    </p>
                    <nav aria-label="Cart pagination">
                        <ul class="pagination mb-0">
                            <!-- Previous Page -->
                            <li class="page-item <?php echo $currentPage == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo; Previous</span>
                                </a>
                            </li>

                            <!-- Page Numbers (simplified for bottom) -->
                            <?php
                            // Show limited page numbers for better UX
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);

                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Page -->
                            <li class="page-item <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">Next &raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span>Subtotal (<?php echo $totalItems; ?> items):</span>
                        <span id="subtotal"><?php echo CURRENCY . ' ' . number_format($total); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Shipping:</span>
                        <span id="shipping"><?php echo $total > 0 ? CURRENCY . ' 200' : CURRENCY . ' 0'; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Tax (5%):</span>
                        <span id="tax"><?php echo CURRENCY . ' ' . number_format($total * 0.05); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-4">
                        <strong>Total:</strong>
                        <strong id="grand-total"><?php echo CURRENCY . ' ' . number_format($total + ($total > 0 ? 200 : 0) + ($total * 0.05)); ?></strong>
                    </div>

                    <?php if($total > 0): ?>
                        <a href="<?php echo SITE_URL; ?>/checkout" class="btn btn-gold w-100 mb-3">Proceed to Checkout</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/products" class="btn btn-outline-dark w-100">Continue Shopping</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Remove Item Confirmation Modal -->
<div class="modal fade" id="removeItemModal" tabindex="-1" aria-labelledby="removeItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="removeItemModalLabel">Remove Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove "<span id="removeItemName"></span>" from your cart?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmRemove">Remove Item</button>
            </div>
        </div>
    </div>
</div>

<!-- Decrease Quantity Confirmation Modal -->
<div class="modal fade" id="decreaseQtyModal" tabindex="-1" aria-labelledby="decreaseQtyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="decreaseQtyModalLabel">Remove Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Decreasing the quantity to zero will remove "<span id="decreaseItemName"></span>" from your cart. Do you want to continue?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDecreaseRemove">Remove Item</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cartMessage = document.getElementById('cartMessage');
    const removeItemModal = new bootstrap.Modal(document.getElementById('removeItemModal'));
    const decreaseQtyModal = new bootstrap.Modal(document.getElementById('decreaseQtyModal'));

    let currentItemId = null;
    let currentItemName = null;

    function showMessage(message, type = 'success') {
        cartMessage.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        setTimeout(() => {
            const alert = cartMessage.querySelector('.alert');
            if (alert) {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            }
        }, 3000);
    }

    function updateCartDisplay() {
        let subtotal = 0;

        document.querySelectorAll('.cart-item').forEach(item => {
            const quantity = parseInt(item.querySelector('.quantity-input').value);
            const price = parseFloat(item.querySelector('.price').textContent.replace(/[^\d.]/g, ''));
            const itemTotal = quantity * price;

            item.querySelector('.item-total').textContent = '<?php echo CURRENCY; ?> ' + itemTotal.toLocaleString();
            subtotal += itemTotal;
        });

        const shipping = subtotal > 0 ? 200 : 0;
        const tax = subtotal * 0.05;
        const grandTotal = subtotal + shipping + tax;

        document.getElementById('subtotal').textContent = '<?php echo CURRENCY; ?> ' + subtotal.toLocaleString();
        document.getElementById('shipping').textContent = '<?php echo CURRENCY; ?> ' + shipping.toLocaleString();
        document.getElementById('tax').textContent = '<?php echo CURRENCY; ?> ' + tax.toLocaleString();
        document.getElementById('grand-total').textContent = '<?php echo CURRENCY; ?> ' + grandTotal.toLocaleString();
    }

    function updateCartCount(count) {
        const cartBadge = document.querySelector('.navbar .badge');
        if (cartBadge) {
            cartBadge.textContent = count;
        }
    }

   function sendCartRequest(action, itemId, quantity = null) {
       const formData = new URLSearchParams();
       formData.append('ajax', 'true');
       formData.append('action', action);
       formData.append('item_id', itemId);
       if (quantity !== null) {
           formData.append('quantity', quantity);
       }

       fetch('<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>', {
           method: 'POST',
           headers: {
               'Content-Type': 'application/x-www-form-urlencoded',
           },
           body: formData
       })
       .then(response => {
           if (!response.ok) {
               throw new Error(`HTTP error! status: ${response.status}`);
           }
           return response.json();
       })
       .then(data => {
           if (data.success) {
               showMessage(data.message, 'success');
               updateCartCount(data.cart_count);

               if (action === 'remove') {
                   const itemElement = document.querySelector(`.cart-item[data-item-id="${itemId}"]`);
                   if (itemElement) {
                       itemElement.style.transition = 'all 0.3s ease';
                       itemElement.style.opacity = '0';
                       itemElement.style.transform = 'translateX(-100%)';

                       setTimeout(() => {
                           itemElement.remove();

                           if (document.querySelectorAll('.cart-item').length === 0) {
                               location.reload();
                           } else {
                               updateCartDisplay();
                           }
                       }, 300);
                   }
               } else if (action === 'update') {
                   const quantityInput = document.querySelector(`.quantity-input[data-item-id="${itemId}"]`);
                   if (quantityInput) {
                       quantityInput.value = quantity;
                   }
                   updateCartDisplay();
               }
           } else {
               showMessage(data.message, 'danger');
           }
       })
       .catch(error => {
           console.error('Fetch Error:', error);
           showMessage('An error occurred while updating cart: ' + error.message, 'danger');
       });
   }

    // Remove item functionality
    document.querySelectorAll('.remove-item').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            currentItemId = this.getAttribute('data-item-id');
            currentItemName = this.getAttribute('data-item-name');

            document.getElementById('removeItemName').textContent = currentItemName;
            removeItemModal.show();
        });
    });

    // Confirm remove item
    document.getElementById('confirmRemove').addEventListener('click', function() {
        if (currentItemId) {
            sendCartRequest('remove', currentItemId);
            removeItemModal.hide();
            currentItemId = null;
            currentItemName = null;
        }
    });

    // Quantity update functionality
    document.querySelectorAll('.increase-qty').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const itemId = this.getAttribute('data-item-id');
            const quantityInput = document.querySelector(`.quantity-input[data-item-id="${itemId}"]`);
            const currentQty = parseInt(quantityInput.value);
            const maxStock = parseInt(this.getAttribute('data-max-stock'));

            if (currentQty < maxStock) {
                sendCartRequest('update', itemId, currentQty + 1);
            } else {
                showMessage('Cannot add more than available stock.', 'warning');
            }
        });
    });

    document.querySelectorAll('.decrease-qty').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const itemId = this.getAttribute('data-item-id');
            const quantityInput = document.querySelector(`.quantity-input[data-item-id="${itemId}"]`);
            const currentQty = parseInt(quantityInput.value);
            const itemName = this.closest('.cart-item').querySelector('h6').textContent;

            if (currentQty > 1) {
                sendCartRequest('update', itemId, currentQty - 1);
            } else {
                currentItemId = itemId;
                currentItemName = itemName;

                document.getElementById('decreaseItemName').textContent = currentItemName;
                decreaseQtyModal.show();
            }
        });
    });

    // Confirm decrease quantity removal
    document.getElementById('confirmDecreaseRemove').addEventListener('click', function() {
        if (currentItemId) {
            sendCartRequest('remove', currentItemId);
            decreaseQtyModal.hide();
            currentItemId = null;
            currentItemName = null;
        }
    });
});
</script>

<style>
.quantity-input {
    max-width: 60px;
}

.btn-outline-secondary:hover {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.spinner-border.text-gold {
    color: var(--primary-color) !important;
}

.cart-item {
    transition: all 0.3s ease;
}

.cart-item.removing {
    opacity: 0;
    transform: translateX(-100%);
}

.pagination-info .pagination {
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .row.align-items-center {
        text-align: center;
    }

    .col-md-2 {
        margin-bottom: 1rem;
    }

    .input-group.input-group-sm {
        justify-content: center;
        max-width: 150px;
        margin: 0 auto;
    }

    .d-flex.justify-content-between.align-items-center.mb-3 {
        flex-direction: column;
        gap: 10px;
    }

    .d-flex.justify-content-between.align-items-center.mt-4 {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}
</style>

<?php
require_once '../includes/footer.php';
?>