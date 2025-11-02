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

// Handle actions
$success_message = '';
$error_message = '';

// Bulk actions
if (isset($_POST['bulk_action'])) {
    $product_ids = $_POST['product_ids'] ?? [];
    $action = $_POST['bulk_action'];

    if (!empty($product_ids)) {
        try {
            $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';

            switch ($action) {
                case 'activate':
                    $stmt = $conn->prepare("UPDATE products SET status = 'active' WHERE id IN ($placeholders)");
                    $stmt->execute($product_ids);
                    $success_message = "Selected products activated successfully!";
                    break;

                case 'deactivate':
                    $stmt = $conn->prepare("UPDATE products SET status = 'inactive' WHERE id IN ($placeholders)");
                    $stmt->execute($product_ids);
                    $success_message = "Selected products deactivated successfully!";
                    break;

                case 'delete':
                    // First, delete product images from server and database
                    $stmt = $conn->prepare("SELECT image_path FROM product_images WHERE product_id IN ($placeholders)");
                    $stmt->execute($product_ids);
                    $images_to_delete = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    // Delete physical image files
                    foreach ($images_to_delete as $image_path) {
                        if (file_exists("../$image_path") && is_file("../$image_path")) {
                            unlink("../$image_path");
                        }
                    }

                    // Delete from database
                    $stmt = $conn->prepare("DELETE FROM product_images WHERE product_id IN ($placeholders)");
                    $stmt->execute($product_ids);

                    $stmt = $conn->prepare("DELETE FROM products WHERE id IN ($placeholders)");
                    $stmt->execute($product_ids);
                    $success_message = "Selected products deleted successfully!";
                    break;

                case 'featured':
                    $stmt = $conn->prepare("UPDATE products SET featured = 1 WHERE id IN ($placeholders)");
                    $stmt->execute($product_ids);
                    $success_message = "Selected products marked as featured!";
                    break;

                case 'unfeatured':
                    $stmt = $conn->prepare("UPDATE products SET featured = 0 WHERE id IN ($placeholders)");
                    $stmt->execute($product_ids);
                    $success_message = "Selected products removed from featured!";
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Error performing bulk action: " . $e->getMessage();
        }
    } else {
        $error_message = "Please select products to perform action.";
    }
}

// Single product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = intval($_POST['product_id']);

    try {
        // Delete product images first
        $stmt = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $images_to_delete = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Delete physical image files
        foreach ($images_to_delete as $image_path) {
            if (file_exists("../$image_path") && is_file("../$image_path")) {
                unlink("../$image_path");
            }
        }

        // Delete from database
        $stmt = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
        $stmt->execute([$product_id]);

        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $success_message = "Product deleted successfully!";
    } catch (PDOException $e) {
        $error_message = "Error deleting product: " . $e->getMessage();
    }
}

// Toggle actions (still using GET for these)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    $action = $_GET['action'];

    try {
        switch ($action) {
            case 'toggle_status':
                $stmt = $conn->prepare("UPDATE products SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
                $stmt->execute([$product_id]);
                $success_message = "Product status updated successfully!";
                break;

            case 'toggle_featured':
                $stmt = $conn->prepare("UPDATE products SET featured = NOT featured WHERE id = ?");
                $stmt->execute([$product_id]);
                $success_message = "Product featured status updated!";
                break;
        }
    } catch (PDOException $e) {
        $error_message = "Error performing action: " . $e->getMessage();
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$featured = isset($_GET['featured']) ? $_GET['featured'] : '';
$stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Build WHERE conditions
$whereConditions = ["1=1"];
$params = [];
$paramTypes = '';

if (!empty($search)) {
    $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= 'sss';
}

if (!empty($category) && $category !== 'all') {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $category;
    $paramTypes .= 'i';
}

if (!empty($status) && $status !== 'all') {
    $whereConditions[] = "p.status = ?";
    $params[] = $status;
    $paramTypes .= 's';
}

if (!empty($featured) && $featured !== 'all') {
    $whereConditions[] = "p.featured = ?";
    $params[] = ($featured === 'featured' ? 1 : 0);
    $paramTypes .= 'i';
}

if (!empty($stock_status) && $stock_status !== 'all') {
    if ($stock_status === 'in_stock') {
        $whereConditions[] = "p.stock_quantity > 0";
    } elseif ($stock_status === 'out_of_stock') {
        $whereConditions[] = "p.stock_quantity = 0";
    } elseif ($stock_status === 'low_stock') {
        $whereConditions[] = "p.stock_quantity <= p.low_stock_threshold AND p.stock_quantity > 0";
    }
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count for pagination
$countSql = "SELECT COUNT(DISTINCT p.id) as total
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalProducts = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalProducts / $limit);

// FIXED: Create separate SQL query for paginated results without binding LIMIT/OFFSET
$sql = "SELECT p.*, c.name as category_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE $whereClause
        ORDER BY p.created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categories = $conn->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get product statistics
$stats_stmt = $conn->query("
    SELECT
        COUNT(*) as total_products,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products,
        SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) as featured_products,
        SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN stock_quantity <= low_stock_threshold AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock
    FROM products
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 brand-font mb-1">Product Management</h1>
            <p class="text-muted mb-0">Manage your jewelry products and inventory</p>
        </div>
        <div>
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

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-primary text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['total_products']; ?></h4>
                    <small>Total Products</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-success text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['active_products']; ?></h4>
                    <small>Active</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-warning text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['featured_products']; ?></h4>
                    <small>Featured</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-danger text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['out_of_stock']; ?></h4>
                    <small>Out of Stock</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-info text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['low_stock']; ?></h4>
                    <small>Low Stock</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Name, SKU or description...">
                </div>
                <div class="col-md-2">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all">All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="featured" class="form-label">Featured</label>
                    <select class="form-select" id="featured" name="featured">
                        <option value="all">All</option>
                        <option value="featured" <?php echo $featured === 'featured' ? 'selected' : ''; ?>>Featured</option>
                        <option value="not_featured" <?php echo $featured === 'not_featured' ? 'selected' : ''; ?>>Not Featured</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="stock_status" class="form-label">Stock</label>
                    <select class="form-select" id="stock_status" name="stock_status">
                        <option value="all">All Stock</option>
                        <option value="in_stock" <?php echo $stock_status === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                        <option value="out_of_stock" <?php echo $stock_status === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        <option value="low_stock" <?php echo $stock_status === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-gold w-100">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </form>

            <!-- Active Filters -->
            <?php if (!empty($search) || !empty($category) || !empty($status) || !empty($featured) || !empty($stock_status)): ?>
                <div class="mt-3">
                    <small class="text-muted">Active filters:</small>
                    <?php if (!empty($search)): ?>
                        <span class="badge bg-primary me-2">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($category) && $category !== 'all'): ?>
                        <span class="badge bg-info me-2">
                            Category: <?php
                                $cat_name = 'Unknown';
                                foreach ($categories as $cat) {
                                    if ($cat['id'] == $category) {
                                        $cat_name = $cat['name'];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($cat_name);
                            ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($status) && $status !== 'all'): ?>
                        <span class="badge bg-success me-2">
                            Status: <?php echo ucfirst($status); ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <a href="products.php" class="btn btn-sm btn-outline-secondary">Clear All</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Products Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <!-- Bulk Actions -->
            <form method="POST" action="" id="bulkActionForm">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                    <div>
                        <select class="form-select form-select-sm" name="bulk_action" id="bulkActionSelect" style="width: 200px;">
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="featured">Mark as Featured</option>
                            <option value="unfeatured">Remove Featured</option>
                            <option value="delete">Delete</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" id="applyBulkAction" class="btn btn-sm btn-gold">Apply</button>
                        <span class="text-muted ms-2">
                            <?php echo $totalProducts; ?> product(s) found
                        </span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="30">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>SKU</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Featured</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="product_ids[]" value="<?php echo $product['id']; ?>" class="product-checkbox">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <img src="../<?php echo htmlspecialchars($product['main_image'] ?? 'assets/images/placeholder.jpg'); ?>"
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                         class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo strlen($product['description']) > 50 ?
                                                            substr(htmlspecialchars($product['description']), 0, 50) . '...' :
                                                            htmlspecialchars($product['description']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($product['sku']); ?></code>
                                        </td>
                                        <td>
                                            <div>
                                                <strong class="text-gold"><?php echo CURRENCY . ' ' . number_format($product['price']); ?></strong>
                                                <?php if ($product['sale_price']): ?>
                                                    <br>
                                                    <small class="text-success">
                                                        Sale: <?php echo CURRENCY . ' ' . number_format($product['sale_price']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($product['stock_quantity'] > 0): ?>
                                                <?php if ($product['stock_quantity'] <= $product['low_stock_threshold']): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                                        <?php echo $product['stock_quantity']; ?>
                                                    </span>
                                                    <small class="text-muted d-block">Low Stock</small>
                                                <?php else: ?>
                                                    <span class="badge bg-success">
                                                        <?php echo $product['stock_quantity']; ?>
                                                    </span>
                                                    <small class="text-muted d-block">In Stock</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-times me-1"></i>
                                                    0
                                                </span>
                                                <small class="text-muted d-block">Out of Stock</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $product['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($product['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($product['featured']): ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-star me-1"></i>Featured
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">
                                                    <i class="far fa-star me-1"></i>Regular
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($product['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="product-edit.php?id=<?php echo $product['id']; ?>"
                                                   class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?action=toggle_status&id=<?php echo $product['id']; ?>"
                                                   class="btn btn-<?php echo $product['status'] === 'active' ? 'danger' : 'success'; ?>"
                                                   title="<?php echo $product['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-power-off"></i>
                                                </a>
                                                <a href="?action=toggle_featured&id=<?php echo $product['id']; ?>"
                                                   class="btn btn-<?php echo $product['featured'] ? 'dark' : 'warning'; ?>"
                                                   title="<?php echo $product['featured'] ? 'Remove Featured' : 'Mark Featured'; ?>">
                                                    <i class="fas fa-star"></i>
                                                </a>
                                                <button type="button"
                                                        class="btn btn-outline-danger delete-product-btn"
                                                        data-product-id="<?php echo $product['id']; ?>"
                                                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                        data-product-sku="<?php echo htmlspecialchars($product['sku']); ?>"
                                                        title="Delete Product">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No products found</h5>
                                        <p class="text-muted mb-3">
                                            <?php if (!empty($search) || !empty($category) || !empty($status)): ?>
                                                Try adjusting your search criteria
                                            <?php else: ?>
                                                Get started by adding your first product
                                            <?php endif; ?>
                                        </p>
                                        <a href="product-add.php" class="btn btn-gold">
                                            <i class="fas fa-plus me-2"></i>Add New Product
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="p-3 border-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">
                                Showing <?php echo min($offset + 1, $totalProducts); ?>-<?php echo min($offset + count($products), $totalProducts); ?> of <?php echo $totalProducts; ?> products
                            </span>
                        </div>
                        <nav aria-label="Products pagination">
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

<!-- Delete Product Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteProductModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Product Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All product data including product images will be permanently deleted.
                </div>
                <p class="mb-2">Are you sure you want to delete the following product?</p>
                <div class="card border-danger mb-3">
                    <div class="card-body">
                        <h6 class="card-title text-danger" id="deleteProductName"></h6>
                        <p class="card-text mb-1" id="deleteProductSku"></p>
                        <small class="text-muted">Product ID: <span id="deleteProductId"></span></small>
                    </div>
                </div>
                <form id="deleteProductForm" method="POST" action="products.php">
                    <input type="hidden" name="delete_product" value="1">
                    <input type="hidden" name="product_id" id="deleteProductIdInput">
                    <!-- Preserve current filters and pagination -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if ($key !== 'action' && $key !== 'id'): ?>
                            <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="submit" form="deleteProductForm" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Delete Product
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="bulkDeleteModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Bulk Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All selected products including their images will be permanently deleted.
                </div>
                <p class="mb-2">Are you sure you want to delete <span id="selectedProductsCount" class="fw-bold">0</span> product(s)?</p>
                <div class="card border-danger mb-3">
                    <div class="card-body">
                        <h6 class="card-title text-danger">Selected Products for Deletion:</h6>
                        <ul id="selectedProductsList" class="mb-0">
                            <!-- Selected products will be listed here -->
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" id="confirmBulkDelete" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Delete Products
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border: 1px solid var(--admin-border);
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

.product-image {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 4px;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }

    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
    }

    .col-xl-2 {
        margin-bottom: 1rem;
    }
}

/* Checkbox styling */
.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bulk actions select all
    const selectAll = document.getElementById('selectAll');
    const productCheckboxes = document.querySelectorAll('.product-checkbox');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            productCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // Bulk action form handling
    const bulkActionForm = document.getElementById('bulkActionForm');
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    const applyBulkActionBtn = document.getElementById('applyBulkAction');
    const bulkDeleteModal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
    const confirmBulkDeleteBtn = document.getElementById('confirmBulkDelete');

    if (applyBulkActionBtn) {
        applyBulkActionBtn.addEventListener('click', function() {
            const bulkAction = bulkActionSelect.value;
            const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');

            if (!bulkAction) {
                alert('Please select a bulk action.');
                return;
            }

            if (checkedBoxes.length === 0) {
                alert('Please select at least one product.');
                return;
            }

            if (bulkAction === 'delete') {
                // Show bulk delete confirmation modal
                const selectedProductsCount = document.getElementById('selectedProductsCount');
                const selectedProductsList = document.getElementById('selectedProductsList');

                selectedProductsCount.textContent = checkedBoxes.length;
                selectedProductsList.innerHTML = '';

                // Get selected product names (limited to first 5 for display)
                let displayedCount = 0;
                checkedBoxes.forEach(checkbox => {
                    if (displayedCount < 5) {
                        const productRow = checkbox.closest('tr');
                        const productName = productRow.querySelector('h6').textContent;
                        const productSku = productRow.querySelector('code').textContent;

                        const listItem = document.createElement('li');
                        listItem.innerHTML = `<strong>${productName}</strong> (${productSku})`;
                        selectedProductsList.appendChild(listItem);
                        displayedCount++;
                    }
                });

                if (checkedBoxes.length > 5) {
                    const remainingItem = document.createElement('li');
                    remainingItem.innerHTML = `<em>... and ${checkedBoxes.length - 5} more products</em>`;
                    selectedProductsList.appendChild(remainingItem);
                }

                bulkDeleteModal.show();
            } else {
                // For non-delete actions, submit form directly
                bulkActionForm.submit();
            }
        });
    }

    // Handle bulk delete confirmation
    if (confirmBulkDeleteBtn) {
        confirmBulkDeleteBtn.addEventListener('click', function() {
            bulkActionForm.submit();
        });
    }

    // Individual product deletion
    const deleteProductModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
    const deleteProductBtns = document.querySelectorAll('.delete-product-btn');

    deleteProductBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productSku = this.getAttribute('data-product-sku');

            document.getElementById('deleteProductName').textContent = productName;
            document.getElementById('deleteProductSku').textContent = `SKU: ${productSku}`;
            document.getElementById('deleteProductId').textContent = productId;
            document.getElementById('deleteProductIdInput').value = productId;

            deleteProductModal.show();
        });
    });

    // Update bulk action select when checkboxes change
    productCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
            if (checkedBoxes.length === 0) {
                bulkActionSelect.value = '';
            }
        });
    });
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>