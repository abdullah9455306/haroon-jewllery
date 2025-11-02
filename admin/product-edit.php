<?php
// Start output buffering at the very beginning
ob_start();

require_once 'includes/admin-header.php';

// Initialize database connection
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Check admin permissions
if ($_SESSION['admin_role'] !== 'admin' && !$_SESSION['is_super_admin']) {
    header('Location: dashboard.php');
    exit;
}

// Get product ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: products.php');
    exit;
}

$product_id = intval($_GET['id']);

// Get product data
$product_stmt = $conn->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
");
$product_stmt->execute([$product_id]);
$product = $product_stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: products.php');
    exit;
}

// Get product images
$images_stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
$images_stmt->execute([$product_id]);
$product_images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for dropdown
$categories = $conn->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$success_message = '';
$error_message = '';
$form_data = $product; // Pre-populate with existing data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input data
    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'sku' => trim($_POST['sku'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'short_description' => trim($_POST['short_description'] ?? ''),
        'price' => floatval($_POST['price'] ?? 0),
        'sale_price' => !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null,
        'category_id' => intval($_POST['category_id'] ?? 0),
        'stock_quantity' => intval($_POST['stock_quantity'] ?? 0),
        'low_stock_threshold' => intval($_POST['low_stock_threshold'] ?? 5),
        'weight' => !empty($_POST['weight']) ? floatval($_POST['weight']) : null,
        'dimensions' => trim($_POST['dimensions'] ?? ''),
        'meta_title' => trim($_POST['meta_title'] ?? ''),
        'meta_description' => trim($_POST['meta_description'] ?? ''),
        'meta_keywords' => trim($_POST['meta_keywords'] ?? ''),
        'status' => $_POST['status'] === 'active' ? 'active' : 'inactive',
        'featured' => isset($_POST['featured']) ? 1 : 0
    ];

    // Validate required fields
    $errors = [];

    if (empty($form_data['name'])) {
        $errors[] = "Product name is required.";
    }

    if (empty($form_data['sku'])) {
        $errors[] = "SKU is required.";
    }

    if ($form_data['price'] <= 0) {
        $errors[] = "Price must be greater than 0.";
    }

    if ($form_data['category_id'] <= 0) {
        $errors[] = "Please select a category.";
    }

    if ($form_data['stock_quantity'] < 0) {
        $errors[] = "Stock quantity cannot be negative.";
    }

    // Check if SKU already exists (excluding current product)
    $stmt = $conn->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
    $stmt->execute([$form_data['sku'], $product_id]);
    if ($stmt->fetch()) {
        $errors[] = "SKU already exists. Please use a unique SKU.";
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Update product
            $sql = "UPDATE products SET
                name = ?, sku = ?, description = ?, short_description = ?, price = ?, sale_price = ?,
                category_id = ?, stock_quantity = ?, low_stock_threshold = ?, weight = ?,
                dimensions = ?, meta_title = ?, meta_description = ?, meta_keywords = ?,
                status = ?, featured = ?, updated_at = NOW()
                WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $form_data['name'],
                $form_data['sku'],
                $form_data['description'],
                $form_data['short_description'],
                $form_data['price'],
                $form_data['sale_price'],
                $form_data['category_id'],
                $form_data['stock_quantity'],
                $form_data['low_stock_threshold'],
                $form_data['weight'],
                $form_data['dimensions'],
                $form_data['meta_title'],
                $form_data['meta_description'],
                $form_data['meta_keywords'],
                $form_data['status'],
                $form_data['featured'],
                $product_id
            ]);

            // Handle new image uploads
            if (!empty($_FILES['images']['name'][0])) {
                $uploaded_images = handleImageUploads($_FILES['images'], $product_id);

                if (!empty($uploaded_images)) {
                    // Insert image records into database
                    $image_sql = "INSERT INTO product_images (product_id, image_path, sort_order, alt_text) VALUES (?, ?, ?, ?)";
                    $image_stmt = $conn->prepare($image_sql);

                    // Get current max sort order
                    $max_order_stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) as max_order FROM product_images WHERE product_id = ?");
                    $max_order_stmt->execute([$product_id]);
                    $max_order = $max_order_stmt->fetch(PDO::FETCH_ASSOC)['max_order'];

                    foreach ($uploaded_images as $index => $image_data) {
                        $image_stmt->execute([
                            $product_id,
                            $image_data['path'],
                            $max_order + $index + 1,
                            $form_data['name'] // Use product name as default alt text
                        ]);
                    }
                }
            }

            // Handle image deletions
            if (!empty($_POST['delete_images'])) {
                $delete_images = $_POST['delete_images'];
                $placeholders = str_repeat('?,', count($delete_images) - 1) . '?';

                // Get image paths for physical deletion
                $delete_stmt = $conn->prepare("SELECT image_path FROM product_images WHERE id IN ($placeholders)");
                $delete_stmt->execute($delete_images);
                $images_to_delete = $delete_stmt->fetchAll(PDO::FETCH_COLUMN);

                // Delete physical files
                foreach ($images_to_delete as $image_path) {
                    if (file_exists("../$image_path") && is_file("../$image_path")) {
                        unlink("../$image_path");
                    }
                }

                // Delete from database
                $delete_stmt = $conn->prepare("DELETE FROM product_images WHERE id IN ($placeholders)");
                $delete_stmt->execute($delete_images);
            }

            $conn->commit();
            $success_message = "Product updated successfully!";

            // Refresh product data
            $product_stmt->execute([$product_id]);
            $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
            $form_data = $product;

            // Refresh product images
            $images_stmt->execute([$product_id]);
            $product_images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $conn->rollBack();
            $error_message = "Error updating product: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

/**
 * Handle multiple image uploads without compression
 */
function handleImageUploads($files, $product_id) {
    $uploaded_images = [];
    $upload_dir = "../assets/uploads/products/";

    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Create product-specific directory
    $product_dir = $upload_dir . $product_id . '/';
    if (!file_exists($product_dir)) {
        mkdir($product_dir, 0755, true);
    }

    foreach ($files['name'] as $index => $name) {
        if ($files['error'][$index] === UPLOAD_ERR_OK) {
            $tmp_name = $files['tmp_name'][$index];
            $file_extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $file_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $name);
            $file_path = $product_dir . $file_name;

            // Validate file type
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($file_extension, $allowed_extensions)) {
                continue; // Skip invalid file types
            }

            // Validate file size (max 5MB)
            if ($files['size'][$index] > 5 * 1024 * 1024) {
                continue; // Skip files that are too large
            }

            // Simply move the uploaded file without compression
            if (move_uploaded_file($tmp_name, $file_path)) {
                $uploaded_images[] = [
                    'path' => 'assets/uploads/products/' . $product_id . '/' . $file_name,
                    'name' => $name
                ];
            }
        }
    }

    return $uploaded_images;
}
?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 brand-font mb-1">Edit Product</h1>
            <p class="text-muted mb-0">Update product information and images</p>
        </div>
        <div>
            <a href="products.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Products
            </a>
            <a href="product-add.php" class="btn btn-gold">
                <i class="fas fa-plus me-2"></i>Add New Product
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Product Form -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data" id="productForm">
                <div class="row">
                    <!-- Basic Information -->
                    <div class="col-lg-8">
                        <div class="card border-0 bg-light mb-4">
                            <div class="card-header bg-transparent border-0">
                                <h5 class="mb-0">Basic Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name"
                                               value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>"
                                               required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="sku" name="sku"
                                               value="<?php echo htmlspecialchars($form_data['sku'] ?? ''); ?>"
                                               required>
                                        <small class="text-muted">Unique product identifier</small>
                                    </div>
                                    <div class="col-12">
                                        <label for="short_description" class="form-label">Short Description</label>
                                        <textarea class="form-control" id="short_description" name="short_description"
                                                  rows="2"><?php echo htmlspecialchars($form_data['short_description'] ?? ''); ?></textarea>
                                        <small class="text-muted">Brief description displayed in product listings</small>
                                    </div>
                                    <div class="col-12">
                                        <label for="description" class="form-label">Full Description</label>
                                        <textarea class="form-control" id="description" name="description"
                                                  rows="5"><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing & Inventory -->
                        <div class="card border-0 bg-light mb-4">
                            <div class="card-header bg-transparent border-0">
                                <h5 class="mb-0">Pricing & Inventory</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="price" class="form-label">Regular Price <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><?php echo CURRENCY; ?></span>
                                            <input type="number" class="form-control" id="price" name="price"
                                                   step="0.01" min="0.01"
                                                   value="<?php echo htmlspecialchars($form_data['price'] ?? ''); ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="sale_price" class="form-label">Sale Price</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><?php echo CURRENCY; ?></span>
                                            <input type="number" class="form-control" id="sale_price" name="sale_price"
                                                   step="0.01" min="0"
                                                   value="<?php echo htmlspecialchars($form_data['sale_price'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="stock_quantity" class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="stock_quantity" name="stock_quantity"
                                               min="0"
                                               value="<?php echo htmlspecialchars($form_data['stock_quantity'] ?? 0); ?>"
                                               required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                                        <input type="number" class="form-control" id="low_stock_threshold" name="low_stock_threshold"
                                               min="1"
                                               value="<?php echo htmlspecialchars($form_data['low_stock_threshold'] ?? 5); ?>">
                                        <small class="text-muted">Get notified when stock reaches this level</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Product Images -->
                        <div class="card border-0 bg-light mb-4">
                            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Product Images</h5>
                                <small class="text-muted"><?php echo count($product_images); ?> image(s)</small>
                            </div>
                            <div class="card-body">
                                <!-- Existing Images -->
                                <?php if (!empty($product_images)): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Current Images</label>
                                        <div class="row g-2" id="existingImages">
                                            <?php foreach ($product_images as $image): ?>
                                                <div class="col-md-3 col-6">
                                                    <div class="image-preview position-relative">
                                                        <img src="../<?php echo htmlspecialchars($image['image_path']); ?>"
                                                             alt="Product Image"
                                                             class="img-thumbnail w-100"
                                                             style="height: 120px; object-fit: cover;">
                                                        <div class="position-absolute top-0 end-0 p-1">
                                                            <input type="checkbox"
                                                                   name="delete_images[]"
                                                                   value="<?php echo $image['id']; ?>"
                                                                   class="form-check-input delete-checkbox"
                                                                   id="delete_<?php echo $image['id']; ?>">
                                                            <label class="form-check-label text-danger ms-1" for="delete_<?php echo $image['id']; ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <small class="text-muted">Check the trash icon to delete images</small>
                                    </div>
                                <?php endif; ?>

                                <!-- New Image Upload -->
                                <div class="mb-3">
                                    <label for="images" class="form-label">Add New Images</label>
                                    <input type="file" class="form-control" id="images" name="images[]"
                                           multiple accept="image/*">
                                    <small class="text-muted">
                                        Upload multiple images (JPG, PNG, GIF, WEBP). Max file size: 5MB each.
                                    </small>
                                </div>
                                <div id="imagePreview" class="row g-2 mt-2"></div>
                                <div id="imageActions" class="mt-2"></div>
                            </div>
                        </div>

                        <!-- SEO Information -->
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-transparent border-0">
                                <h5 class="mb-0">SEO Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="meta_title" class="form-label">Meta Title</label>
                                        <input type="text" class="form-control" id="meta_title" name="meta_title"
                                               value="<?php echo htmlspecialchars($form_data['meta_title'] ?? ''); ?>">
                                        <small class="text-muted">Recommended: 50-60 characters</small>
                                    </div>
                                    <div class="col-12">
                                        <label for="meta_description" class="form-label">Meta Description</label>
                                        <textarea class="form-control" id="meta_description" name="meta_description"
                                                  rows="3"><?php echo htmlspecialchars($form_data['meta_description'] ?? ''); ?></textarea>
                                        <small class="text-muted">Recommended: 150-160 characters</small>
                                    </div>
                                    <div class="col-12">
                                        <label for="meta_keywords" class="form-label">Meta Keywords</label>
                                        <input type="text" class="form-control" id="meta_keywords" name="meta_keywords"
                                               value="<?php echo htmlspecialchars($form_data['meta_keywords'] ?? ''); ?>">
                                        <small class="text-muted">Comma-separated keywords</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Product Status -->
                        <div class="card border-0 bg-light mb-4">
                            <div class="card-header bg-transparent border-0">
                                <h5 class="mb-0">Product Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?php echo ($form_data['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($form_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="featured" name="featured"
                                           value="1" <?php echo ($form_data['featured'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="featured">Featured Product</label>
                                </div>
                            </div>
                        </div>

                        <!-- Product Organization -->
                        <div class="card border-0 bg-light mb-4">
                            <div class="card-header bg-transparent border-0">
                                <h5 class="mb-0">Product Organization</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"
                                                    <?php echo ($form_data['category_id'] ?? 0) == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Product Specifications -->
                        <div class="card border-0 bg-light mb-4">
                            <div class="card-header bg-transparent border-0">
                                <h5 class="mb-0">Specifications</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="weight" class="form-label">Weight (grams)</label>
                                    <input type="number" class="form-control" id="weight" name="weight"
                                           step="0.01" min="0"
                                           value="<?php echo htmlspecialchars($form_data['weight'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="dimensions" class="form-label">Dimensions (L×W×H)</label>
                                    <input type="text" class="form-control" id="dimensions" name="dimensions"
                                           placeholder="e.g., 10×5×2 cm"
                                           value="<?php echo htmlspecialchars($form_data['dimensions'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Product Meta Information -->
                        <div class="card border-0 bg-light mb-4">
                            <div class="card-header bg-transparent border-0">
                                <h5 class="mb-0">Product Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <small class="text-muted">Product ID</small>
                                    <div><strong>#<?php echo $product_id; ?></strong></div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Created</small>
                                    <div><?php echo date('M j, Y g:i A', strtotime($form_data['created_at'])); ?></div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Last Updated</small>
                                    <div><?php echo $form_data['updated_at'] ? date('M j, Y g:i A', strtotime($form_data['updated_at'])) : 'Never'; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <button type="submit" class="btn btn-gold w-100 mb-2">
                                    <i class="fas fa-save me-2"></i>Update Product
                                </button>
                                <a href="products.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Images Confirmation Modal -->
<div class="modal fade" id="deleteImagesModal" tabindex="-1" aria-labelledby="deleteImagesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="deleteImagesModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Image Deletion
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Warning:</strong> You are about to delete <span id="deleteImagesCount" class="fw-bold">0</span> image(s). This action cannot be undone.
                </div>
                <p class="mb-0">Are you sure you want to proceed with deleting the selected images?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" id="confirmDeleteImages" class="btn btn-warning">
                    <i class="fas fa-trash me-2"></i>Delete Images
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border: 1px solid var(--admin-border);
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

.image-preview {
    position: relative;
    display: inline-block;
    margin: 25px;
    width: 100px;
    height: 100px;
}

.image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 4px;
}

.remove-image {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #dc3545;
    color: white;
    border: 2px solid white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    z-index: 10;
}

.remove-image:hover {
    background: #c82333;
    transform: scale(1.1);
}

/* Better grid layout for image previews */
#imagePreview {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

#imagePreview .image-preview {
    flex: 0 0 auto;
}

.delete-checkbox {
    transform: scale(1.2);
}

.delete-checkbox:checked + label {
    color: #dc3545 !important;
}
</style>

<script>
// Global variable to track files
let currentFiles = [];

// Function to show Bootstrap alert
function showBootstrapAlert(message, type = 'warning') {
    // Remove any existing custom alerts first
    const existingAlerts = document.querySelectorAll('.custom-bootstrap-alert');
    existingAlerts.forEach(alert => alert.remove());

    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show custom-bootstrap-alert position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';

    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    // Add to body
    document.body.appendChild(alertDiv);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

document.addEventListener('DOMContentLoaded', function() {
    // Image preview functionality
    const imageInput = document.getElementById('images');
    const imagePreview = document.getElementById('imagePreview');
    const imageActions = document.getElementById('imageActions');

    if (imageInput && imagePreview) {
        // Reset file input when clicked to allow re-uploading same files
        imageInput.addEventListener('click', function() {
            // This allows the same file to be selected again
            this.value = '';
        });

        imageInput.addEventListener('change', function(e) {
            const newFiles = Array.from(e.target.files);

            // Clear existing previews
            imagePreview.innerHTML = '';

            // Replace current files with new selection
            currentFiles = newFiles;

            // Create previews for all files
            createImagePreviews(newFiles);

            // Add clear all button if there are files
            addClearAllButton();
        });
    }

    // Form validation
    const form = document.getElementById('productForm');
    const deleteImagesModal = new bootstrap.Modal(document.getElementById('deleteImagesModal'));
    const confirmDeleteImagesBtn = document.getElementById('confirmDeleteImages');

    if (form) {
        form.addEventListener('submit', function(e) {
            let valid = true;
            const requiredFields = form.querySelectorAll('[required]');
            let errorMessages = [];

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('is-invalid');
                    const fieldName = field.previousElementSibling?.textContent || 'This field';
                    errorMessages.push(`${fieldName} is required.`);
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            // Validate price
            const price = document.getElementById('price');
            if (price && parseFloat(price.value) <= 0) {
                valid = false;
                price.classList.add('is-invalid');
                errorMessages.push('Price must be greater than 0.');
            }

            // Validate sale price
            const salePrice = document.getElementById('sale_price');
            if (salePrice && salePrice.value && parseFloat(salePrice.value) >= parseFloat(price.value)) {
                valid = false;
                salePrice.classList.add('is-invalid');
                errorMessages.push('Sale price must be less than regular price.');
            }

            // Check for image deletions and show modal instead of confirm
            const deleteCheckboxes = form.querySelectorAll('.delete-checkbox:checked');
            if (deleteCheckboxes.length > 0) {
                e.preventDefault(); // Prevent form submission

                // Set the count in the modal
                document.getElementById('deleteImagesCount').textContent = deleteCheckboxes.length;

                // Show the modal
                deleteImagesModal.show();

                return false;
            }

            if (!valid) {
                e.preventDefault();
                const errorMessage = errorMessages.length > 0 ? errorMessages.join('<br>') : 'Please fill in all required fields correctly.';
                showBootstrapAlert(errorMessage, 'danger');
            }
        });
    }

    // Handle image deletion confirmation
    if (confirmDeleteImagesBtn) {
        confirmDeleteImagesBtn.addEventListener('click', function() {
            // Submit the form when user confirms deletion
            document.getElementById('productForm').submit();
        });
    }

    // Auto-generate meta tags if empty
    const nameField = document.getElementById('name');
    const descriptionField = document.getElementById('description');
    const metaTitleField = document.getElementById('meta_title');
    const metaDescriptionField = document.getElementById('meta_description');

    if (nameField && metaTitleField) {
        nameField.addEventListener('blur', function() {
            if (!metaTitleField.value) {
                metaTitleField.value = this.value + ' | Your Store Name';
            }
        });
    }

    if (descriptionField && metaDescriptionField) {
        descriptionField.addEventListener('blur', function() {
            if (!metaDescriptionField.value && this.value) {
                // Truncate description for meta description
                const truncated = this.value.substring(0, 150);
                metaDescriptionField.value = truncated + (this.value.length > 150 ? '...' : '');
            }
        });
    }
});

// Create image previews
function createImagePreviews(files) {
    const imagePreview = document.getElementById('imagePreview');

    files.forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();

            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'image-preview';
                div.setAttribute('data-file-index', index);
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Preview" class="img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                    <button type="button" class="remove-image" onclick="removeImagePreview(this)">×</button>
                `;
                imagePreview.appendChild(div);
            }

            reader.readAsDataURL(file);
        }
    });
}

// Add clear all button
function addClearAllButton() {
    const imageActions = document.getElementById('imageActions');
    const existingClearBtn = document.getElementById('clearAllImages');

    if (!existingClearBtn && currentFiles.length > 0) {
        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.id = 'clearAllImages';
        clearBtn.className = 'btn btn-sm btn-outline-danger mt-2';
        clearBtn.innerHTML = '<i class="fas fa-trash me-1"></i>Clear All New Images';
        clearBtn.onclick = clearAllImages;
        imageActions.appendChild(clearBtn);
    }
}

// Remove single image preview
function removeImagePreview(button) {
    const previewContainer = button.closest('.image-preview');
    const fileIndex = parseInt(previewContainer.getAttribute('data-file-index'));

    if (previewContainer) {
        previewContainer.remove();
    }

    // Remove file from array
    if (currentFiles && currentFiles.length > fileIndex) {
        currentFiles.splice(fileIndex, 1);
        updateFileInput();
        reindexImagePreviews();
    }

    // Remove clear all button if no files left
    if (currentFiles.length === 0) {
        removeClearAllButton();
    }
}

// Clear all images
function clearAllImages() {
    const imagePreview = document.getElementById('imagePreview');
    imagePreview.innerHTML = '';

    currentFiles = [];
    updateFileInput();
    removeClearAllButton();

    // Also clear the file input
    const imageInput = document.getElementById('images');
    if (imageInput) {
        imageInput.value = '';
    }
}

// Remove clear all button
function removeClearAllButton() {
    const clearBtn = document.getElementById('clearAllImages');
    if (clearBtn) {
        clearBtn.remove();
    }
}

// Update file input with current files
function updateFileInput() {
    const imageInput = document.getElementById('images');
    if (imageInput) {
        const dt = new DataTransfer();
        currentFiles.forEach(file => dt.items.add(file));
        imageInput.files = dt.files;
    }
}

// Re-index image previews after removal
function reindexImagePreviews() {
    const previews = document.querySelectorAll('.image-preview');
    previews.forEach((preview, newIndex) => {
        preview.setAttribute('data-file-index', newIndex);
    });
}
</script>

<?php require_once 'includes/admin-footer.php'; ?>