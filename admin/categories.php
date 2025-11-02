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

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$parent_category = isset($_GET['parent_category']) ? $_GET['parent_category'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 5; // Number of categories per page
$offset = ($page - 1) * $limit;

// Build WHERE conditions
$whereConditions = ["1=1"];
$params = [];
$paramTypes = '';

if (!empty($search)) {
    $whereConditions[] = "(c.name LIKE ? OR c.slug LIKE ? OR c.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= 'sss';
}

if (!empty($status) && $status !== 'all') {
    $whereConditions[] = "c.status = ?";
    $params[] = $status;
    $paramTypes .= 's';
}

if (!empty($parent_category) && $parent_category !== 'all') {
    if ($parent_category === 'main') {
        $whereConditions[] = "c.parent_id IS NULL";
    } elseif ($parent_category === 'sub') {
        $whereConditions[] = "c.parent_id IS NOT NULL";
    }
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count for pagination
$countSql = "SELECT COUNT(DISTINCT c.id) as total
             FROM categories c
             LEFT JOIN categories p ON c.parent_id = p.id
             WHERE $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalCategories = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalCategories / $limit);

// Get categories for display with pagination
$sql = "SELECT c.*, p.name as parent_name,
               (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count,
               (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) as subcategory_count
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        WHERE $whereClause
        ORDER BY c.parent_id IS NULL DESC, c.sort_order, c.name
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$all_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Get all categories for JavaScript data (for edit functionality)
$all_categories_data = $conn->query("
    SELECT id, name, slug, description, parent_id, image, sort_order, status
    FROM categories
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);
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

    <!-- Filters Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Name, slug or description...">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all">All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="parent_category" class="form-label">Category Type</label>
                    <select class="form-select" id="parent_category" name="parent_category">
                        <option value="all">All Types</option>
                        <option value="main" <?php echo $parent_category === 'main' ? 'selected' : ''; ?>>Main Categories</option>
                        <option value="sub" <?php echo $parent_category === 'sub' ? 'selected' : ''; ?>>Subcategories</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-gold w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>

            <!-- Active Filters -->
            <?php if (!empty($search) || !empty($status) || !empty($parent_category)): ?>
                <div class="mt-3">
                    <small class="text-muted">Active filters:</small>
                    <?php if (!empty($search)): ?>
                        <span class="badge bg-primary me-2">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($status) && $status !== 'all'): ?>
                        <span class="badge bg-success me-2">
                            Status: <?php echo ucfirst($status); ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($parent_category) && $parent_category !== 'all'): ?>
                        <span class="badge bg-info me-2">
                            Type: <?php echo $parent_category === 'main' ? 'Main Categories' : 'Subcategories'; ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['parent_category' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <a href="categories.php" class="btn btn-sm btn-outline-secondary">Clear All</a>
                </div>
            <?php endif; ?>
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
                                            <a href="?action=toggle_status&id=<?php echo $category['id']; ?><?php echo !empty($_GET) ? '&' . http_build_query($_GET) : ''; ?>"
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
                                    <p class="text-muted mb-3">
                                        <?php if (!empty($search) || !empty($status) || !empty($parent_category)): ?>
                                            Try adjusting your search criteria
                                        <?php else: ?>
                                            Get started by creating your first category
                                        <?php endif; ?>
                                    </p>
                                    <button type="button" class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                        <i class="fas fa-plus me-2"></i>Add New Category
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="p-3 border-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">
                                Showing <?php echo min($offset + 1, $totalCategories); ?>-<?php echo min($offset + count($all_categories), $totalCategories); ?> of <?php echo $totalCategories; ?> categories
                            </span>
                        </div>
                        <nav aria-label="Categories pagination">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left me-1"></i>Previous
                                    </a>
                                </li>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        Next<i class="fas fa-chevron-right ms-1"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            <?php endif; ?>
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
                    <!-- Preserve current filters and pagination -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                    <?php endforeach; ?>
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
                    <!-- Preserve current filters and pagination -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                    <?php endforeach; ?>
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
// Store categories data for JavaScript access
const categoriesData = <?php echo json_encode($all_categories_data); ?>;

// Load category data for editing
function loadCategoryData(categoryId) {
    // Find the category in our pre-loaded data
    const category = categoriesData.find(cat => cat.id == categoryId);

    if (category) {
        document.getElementById('edit_category_id').value = category.id;
        document.getElementById('edit_name').value = category.name;
        document.getElementById('edit_slug').value = category.slug;
        document.getElementById('edit_description').value = category.description || '';
        document.getElementById('edit_parent_id').value = category.parent_id || '';
        document.getElementById('edit_sort_order').value = category.sort_order;
        document.getElementById('edit_status').value = category.status;

        // Show current image
        const currentImageDiv = document.getElementById('current_image');
        if (category.image) {
            currentImageDiv.innerHTML = `
                <small class="text-muted">Current Image:</small><br>
                <img src="../${category.image}" alt="${category.name}"
                     style="max-width: 100px; height: auto;" class="mt-1 rounded">
            `;
        } else {
            currentImageDiv.innerHTML = '<small class="text-muted">No image</small>';
        }
    } else {
        alert('Error: Category not found');
    }
}

// Delete category confirmation
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-category-btn');
    const deleteCategoryName = document.getElementById('deleteCategoryName');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const categoryId = this.getAttribute('data-category-id');
            const categoryName = this.getAttribute('data-category-name');

            deleteCategoryName.textContent = categoryName;
            confirmDeleteBtn.href = `?action=delete&id=${categoryId}<?php echo !empty($_GET) ? '&' . http_build_query($_GET) : ''; ?>`;
        });
    });
});

// Auto-generate slug from name (add form)
document.getElementById('name').addEventListener('input', function() {
    const name = this.value;
    const slug = name.toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
    document.getElementById('slug').value = slug;
});

// Auto-generate slug for edit form
document.getElementById('edit_name').addEventListener('input', function() {
    const name = this.value;
    const slug = name.toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
    document.getElementById('edit_slug').value = slug;
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>