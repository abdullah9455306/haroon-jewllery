<?php
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

// Handle form submissions
$success_message = '';
$error_message = '';

// Add new category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
    $sort_order = intval($_POST['sort_order'] ?? 0);

    // Validation
    $errors = [];

    if (empty($name)) {
        $errors[] = "Category name is required.";
    }

    if (empty($slug)) {
        $errors[] = "Category slug is required.";
    } elseif (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        $errors[] = "Slug can only contain lowercase letters, numbers, and hyphens.";
    } else {
        // Check if slug already exists
        $stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $errors[] = "Slug already exists. Please use a unique slug.";
        }
    }

    if (empty($errors)) {
        try {
            // Handle image upload
            $image_path = null;
            if (!empty($_FILES['image']['name'])) {
                $image_path = handleCategoryImageUpload($_FILES['image']);
            }

            $sql = "INSERT INTO categories (name, slug, description, parent_id, image, sort_order, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$name, $slug, $description, $parent_id, $image_path, $sort_order, $status]);

            $success_message = "Category added successfully!";
        } catch (PDOException $e) {
            $error_message = "Error adding category: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Update category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $category_id = intval($_POST['category_id']);
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
    $sort_order = intval($_POST['sort_order'] ?? 0);

    // Validation
    $errors = [];

    if (empty($name)) {
        $errors[] = "Category name is required.";
    }

    if (empty($slug)) {
        $errors[] = "Category slug is required.";
    } elseif (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        $errors[] = "Slug can only contain lowercase letters, numbers, and hyphens.";
    } else {
        // Check if slug already exists (excluding current category)
        $stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $category_id]);
        if ($stmt->fetch()) {
            $errors[] = "Slug already exists. Please use a unique slug.";
        }
    }

    // Prevent category from being its own parent
    if ($parent_id == $category_id) {
        $errors[] = "A category cannot be its own parent.";
    }

    if (empty($errors)) {
        try {
            // Handle image upload
            $image_sql = "";
            $params = [$name, $slug, $description, $parent_id, $sort_order, $status];

            if (!empty($_FILES['image']['name'])) {
                $image_path = handleCategoryImageUpload($_FILES['image']);
                $image_sql = ", image = ?";
                $params[] = $image_path;
            }

            $params[] = $category_id;

            $sql = "UPDATE categories SET
                    name = ?, slug = ?, description = ?, parent_id = ?, sort_order = ?, status = ?
                    $image_sql
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            $success_message = "Category updated successfully!";
        } catch (PDOException $e) {
            $error_message = "Error updating category: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Delete category
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $category_id = intval($_GET['id']);

    try {
        // Check if category has products
        $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $product_count = $stmt->fetchColumn();

        if ($product_count > 0) {
            $error_message = "Cannot delete category. There are {$product_count} product(s) associated with this category.";
        } else {
            // Check if category has subcategories
            $stmt = $conn->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
            $stmt->execute([$category_id]);
            $subcategory_count = $stmt->fetchColumn();

            if ($subcategory_count > 0) {
                $error_message = "Cannot delete category. There are {$subcategory_count} subcategory(s) under this category.";
            } else {
                // Delete category image if exists
                $stmt = $conn->prepare("SELECT image FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);
                $category = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($category['image'] && file_exists("../{$category['image']}")) {
                    unlink("../{$category['image']}");
                }

                // Delete category
                $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);

                $success_message = "Category deleted successfully!";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Error deleting category: " . $e->getMessage();
    }
}

// Toggle category status
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $category_id = intval($_GET['id']);

    try {
        $stmt = $conn->prepare("UPDATE categories SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
        $stmt->execute([$category_id]);
        $success_message = "Category status updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating category status: " . $e->getMessage();
    }
}

// Handle category image upload
function handleCategoryImageUpload($file) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../assets/uploads/categories/";

        // Create upload directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception("Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.");
        }

        if ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
            throw new Exception("File size too large. Maximum size is 2MB.");
        }

        $file_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $file['name']);
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            return 'assets/uploads/categories/' . $file_name;
        } else {
            throw new Exception("Failed to upload image.");
        }
    }
    return null;
}

// Get all categories for dropdown
$categories_stmt = $conn->query("
    SELECT c.*, p.name as parent_name,
           (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count,
           (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) as subcategory_count
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    ORDER BY c.parent_id IS NULL DESC, c.sort_order, c.name
");
$all_categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for parent dropdown (excluding current category if editing)
$parent_categories = $conn->query("
    SELECT id, name FROM categories
    WHERE parent_id IS NULL
    ORDER BY sort_order, name
")->fetchAll(PDO::FETCH_ASSOC);

// Get category statistics
$stats_stmt = $conn->query("
    SELECT
        COUNT(*) as total_categories,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_categories,
        SUM(CASE WHEN parent_id IS NULL THEN 1 ELSE 0 END) as main_categories,
        SUM(CASE WHEN parent_id IS NOT NULL THEN 1 ELSE 0 END) as subcategories
    FROM categories
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 brand-font mb-1">Category Management</h1>
            <p class="text-muted mb-0">Organize your products with categories and subcategories</p>
        </div>
        <div>
            <button type="button" class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-2"></i>Add New Category
            </button>
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

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 bg-primary text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['total_categories']; ?></h4>
                    <small>Total Categories</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 bg-success text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['active_categories']; ?></h4>
                    <small>Active</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 bg-warning text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['main_categories']; ?></h4>
                    <small>Main Categories</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 bg-info text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['subcategories']; ?></h4>
                    <small>Subcategories</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Category</th>
                            <th>Slug</th>
                            <th>Parent</th>
                            <th>Products</th>
                            <th>Subcategories</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($all_categories)): ?>
                            <?php foreach ($all_categories as $category): ?>
                                <tr>
                                    <td>
                                        <?php if ($category['image']): ?>
                                            <img src="../<?php echo htmlspecialchars($category['image']); ?>"
                                                 alt="<?php echo htmlspecialchars($category['name']); ?>"
                                                 class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                                 style="width: 40px; height: 40px;">
                                                <i class="fas fa-folder text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                            <?php if ($category['parent_id']): ?>
                                                <small class="text-muted d-block">Subcategory</small>
                                            <?php else: ?>
                                                <small class="text-muted d-block">Main Category</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($category['slug']); ?></code>
                                    </td>
                                    <td>
                                        <?php if ($category['parent_name']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($category['parent_name']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $category['product_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning"><?php echo $category['subcategory_count']; ?></span>
                                    </td>
                                    <td>
                                        <?php echo $category['sort_order']; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $category['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($category['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editCategoryModal"
                                                    onclick="loadCategoryData(<?php echo $category['id']; ?>)"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?action=toggle_status&id=<?php echo $category['id']; ?>"
                                               class="btn btn-<?php echo $category['status'] === 'active' ? 'danger' : 'success'; ?>"
                                               title="<?php echo $category['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-power-off"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger delete-category-btn"
                                                    data-category-id="<?php echo $category['id']; ?>"
                                                    data-category-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteCategoryModal"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No categories found</h5>
                                    <p class="text-muted mb-3">Get started by creating your first category</p>
                                    <button type="button" class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                        <i class="fas fa-plus me-2"></i>Add New Category
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="add_category" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="slug" name="slug" required>
                            <small class="text-muted">URL-friendly version of the name (lowercase, hyphens)</small>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="parent_id" class="form-label">Parent Category</label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">None (Main Category)</option>
                                <?php foreach ($parent_categories as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="sort_order" class="form-label">Sort Order</label>
                            <input type="number" class="form-control" id="sort_order" name="sort_order" value="0" min="0">
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="image" class="form-label">Category Image</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <small class="text-muted">JPG, PNG, GIF, WEBP (Max: 2MB)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="editCategoryForm">
                <div class="modal-body">
                    <input type="hidden" name="update_category" value="1">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_slug" class="form-label">Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_slug" name="slug" required>
                            <small class="text-muted">URL-friendly version of the name (lowercase, hyphens)</small>
                        </div>
                        <div class="col-12">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_parent_id" class="form-label">Parent Category</label>
                            <select class="form-select" id="edit_parent_id" name="parent_id">
                                <option value="">None (Main Category)</option>
                                <?php foreach ($parent_categories as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_sort_order" class="form-label">Sort Order</label>
                            <input type="number" class="form-control" id="edit_sort_order" name="sort_order" value="0" min="0">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_image" class="form-label">Category Image</label>
                            <input type="file" class="form-control" id="edit_image" name="image" accept="image/*">
                            <small class="text-muted">JPG, PNG, GIF, WEBP (Max: 2MB)</small>
                            <div id="current_image" class="mt-2"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Confirmation Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCategoryModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the category "<strong id="deleteCategoryName"></strong>"?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" class="btn btn-danger" id="confirmDeleteBtn">Delete Category</a>
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

.table-hover tbody tr:hover {
    background-color: rgba(212, 175, 55, 0.05);
}

.badge {
    font-size: 0.75em;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }

    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
    }
}
</style>

<script>
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
    // Auto-generate slug from name
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');

    if (nameInput && slugInput) {
        nameInput.addEventListener('blur', function() {
            if (!slugInput.value) {
                const slug = this.value
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9 -]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
                slugInput.value = slug;
            }
        });
    }

    // Same for edit form
    const editNameInput = document.getElementById('edit_name');
    const editSlugInput = document.getElementById('edit_slug');

    if (editNameInput && editSlugInput) {
        editNameInput.addEventListener('blur', function() {
            if (!editSlugInput.value) {
                const slug = this.value
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9 -]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
                editSlugInput.value = slug;
            }
        });
    }

    // Handle delete category modal
    const deleteCategoryModal = document.getElementById('deleteCategoryModal');
    if (deleteCategoryModal) {
        deleteCategoryModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const categoryId = button.getAttribute('data-category-id');
            const categoryName = button.getAttribute('data-category-name');

            const modalTitle = deleteCategoryModal.querySelector('.modal-title');
            const categoryNameElement = deleteCategoryModal.querySelector('#deleteCategoryName');
            const confirmDeleteBtn = deleteCategoryModal.querySelector('#confirmDeleteBtn');

            categoryNameElement.textContent = categoryName;
            confirmDeleteBtn.href = `?action=delete&id=${categoryId}`;
        });
    }

    // Form validation
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const name = this.querySelector('[name="name"]');
            const slug = this.querySelector('[name="slug"]');
            let errorMessage = '';

            if (name && !name.value.trim()) {
                errorMessage = 'Category name is required';
                name.focus();
            } else if (slug && !slug.value.trim()) {
                errorMessage = 'Category slug is required';
                slug.focus();
            } else if (slug && !/^[a-z0-9-]+$/.test(slug.value)) {
                errorMessage = 'Slug can only contain lowercase letters, numbers, and hyphens';
                slug.focus();
            }

            if (errorMessage) {
                e.preventDefault();
                showBootstrapAlert(errorMessage, 'danger');
                return;
            }
        });
    });
});

// Load category data for editing
function loadCategoryData(categoryId) {
    // Get all categories data that was loaded in PHP
    const categories = <?php echo json_encode($all_categories); ?>;

    // Find the category with the matching ID
    const category = categories.find(cat => cat.id == categoryId);

    if (category) {
        document.getElementById('edit_category_id').value = category.id;
        document.getElementById('edit_name').value = category.name;
        document.getElementById('edit_slug').value = category.slug;
        document.getElementById('edit_description').value = category.description || '';
        document.getElementById('edit_parent_id').value = category.parent_id || '';
        document.getElementById('edit_sort_order').value = category.sort_order;
        document.getElementById('edit_status').value = category.status;

        // Show current image if exists
        const currentImageDiv = document.getElementById('current_image');
        if (category.image) {
            currentImageDiv.innerHTML = `
                <small class="text-muted">Current Image:</small><br>
                <img src="../${category.image}" alt="${category.name}"
                     class="img-thumbnail mt-1" style="max-width: 100px; max-height: 100px;">
            `;
        } else {
            currentImageDiv.innerHTML = '<small class="text-muted">No image uploaded</small>';
        }
    } else {
        showBootstrapAlert('Error: Category not found', 'danger');
    }
}
</script>

<?php require_once 'includes/admin-footer.php'; ?>