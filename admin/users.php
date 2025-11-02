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

// Handle user actions
$success_message = '';
$error_message = '';

// Update user status
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    try {
        $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND role = 'customer'");
        $stmt->execute([$user_id]);
        $success_message = "User status updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating user status: " . $e->getMessage();
    }
}

// Delete user
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    try {
        // Check if user has orders
        $order_check = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $order_check->execute([$user_id]);
        $order_count = $order_check->fetchColumn();

        if ($order_count > 0) {
            $error_message = "Cannot delete user. There are {$order_count} order(s) associated with this user.";
        } else {
            // Delete user's cart items first
            $cart_stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $cart_stmt->execute([$user_id]);

            // Delete the user
            $user_stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'");
            $user_stmt->execute([$user_id]);

            if ($user_stmt->rowCount() > 0) {
                $success_message = "User deleted successfully!";
            } else {
                $error_message = "User not found or cannot be deleted.";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Error deleting user: " . $e->getMessage();
    }
}

// Bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $user_ids = $_POST['user_ids'] ?? [];
    $action = $_POST['bulk_action'];

    if (!empty($user_ids)) {
        try {
            $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';

            switch ($action) {
                case 'activate':
                    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id IN ($placeholders) AND role = 'customer'");
                    $stmt->execute($user_ids);
                    $success_message = "Selected users activated successfully!";
                    break;

                case 'deactivate':
                    $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id IN ($placeholders) AND role = 'customer'");
                    $stmt->execute($user_ids);
                    $success_message = "Selected users deactivated successfully!";
                    break;

                case 'delete':
                    // Check for orders before deletion
                    $order_check_sql = "SELECT COUNT(*) FROM orders WHERE user_id IN ($placeholders)";
                    $order_check_stmt = $conn->prepare($order_check_sql);
                    $order_check_stmt->execute($user_ids);
                    $total_orders = $order_check_stmt->fetchColumn();

                    if ($total_orders > 0) {
                        $error_message = "Cannot delete users. There are {$total_orders} order(s) associated with selected users.";
                    } else {
                        // Delete cart items
                        $cart_stmt = $conn->prepare("DELETE FROM cart WHERE user_id IN ($placeholders)");
                        $cart_stmt->execute($user_ids);

                        // Delete users
                        $user_stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role = 'customer'");
                        $user_stmt->execute($user_ids);
                        $success_message = "Selected users deleted successfully!";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Error performing bulk action: " . $e->getMessage();
        }
    } else {
        $error_message = "Please select users to perform action.";
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$city = isset($_GET['city']) ? $_GET['city'] : '';
$country = isset($_GET['country']) ? $_GET['country'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build WHERE conditions
$whereConditions = ["u.role = 'customer'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ? OR u.address LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status) && $status !== 'all') {
    $whereConditions[] = "u.status = ?";
    $params[] = $status;
}

if (!empty($city) && $city !== 'all') {
    $whereConditions[] = "u.city = ?";
    $params[] = $city;
}

if (!empty($country) && $country !== 'all') {
    $whereConditions[] = "u.country = ?";
    $params[] = $country;
}

if (!empty($date_from)) {
    $whereConditions[] = "DATE(u.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $whereConditions[] = "DATE(u.created_at) <= ?";
    $params[] = $date_to;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count for pagination
$countSql = "SELECT COUNT(DISTINCT u.id) as total FROM users u WHERE $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalUsers = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalUsers / $limit);

// Get users with pagination
$sql = "SELECT
            u.*,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
            (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND payment_status = 'paid') as total_spent,
            (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) as last_order_date
        FROM users u
        WHERE $whereClause
        ORDER BY u.created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique cities and countries for filters
$cities = $conn->query("SELECT DISTINCT city FROM users WHERE city IS NOT NULL AND city != '' AND role = 'customer' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$countries = $conn->query("SELECT DISTINCT country FROM users WHERE country IS NOT NULL AND country != '' AND role = 'customer' ORDER BY country")->fetchAll(PDO::FETCH_COLUMN);

// Get user statistics
$stats_stmt = $conn->query("
    SELECT
        COUNT(*) as total_users,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_users,
        SUM(CASE WHEN last_login IS NOT NULL THEN 1 ELSE 0 END) as logged_in_users,
        (SELECT COUNT(DISTINCT user_id) FROM orders WHERE user_id IS NOT NULL) as users_with_orders,
        (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND role = 'customer') as new_users_30d
    FROM users
    WHERE role = 'customer'
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get revenue statistics
$revenue_stmt = $conn->query("
    SELECT
        SUM(o.total_amount) as total_revenue,
        COUNT(DISTINCT o.user_id) as paying_customers
    FROM orders o
    WHERE o.payment_status = 'paid' AND o.user_id IS NOT NULL
");
$revenue_stats = $revenue_stmt->fetch(PDO::FETCH_ASSOC);

$user_details = null;
if (isset($_GET['view_user_id'])) {
    $user_id = intval($_GET['view_user_id']);

    try {
        $stmt = $conn->prepare("
            SELECT
                u.*,
                (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
                (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND payment_status = 'paid') as total_spent,
                (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) as last_order_date
            FROM users u
            WHERE u.id = ? AND u.role = 'customer'
        ");
        $stmt->execute([$user_id]);
        $user_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_details) {
            // Get user's recent orders
            $orders_stmt = $conn->prepare("
                SELECT id, order_number, total_amount, payment_status, created_at
                FROM orders
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $orders_stmt->execute([$user_id]);
            $user_details['recent_orders'] = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error_message = "Error loading user details: " . $e->getMessage();
    }
}
?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 brand-font mb-1">Customer Management</h1>
            <p class="text-muted mb-0">Manage your store customers and their information</p>
        </div>
        <div>
           <div>
               <!--<button type="button" class="btn btn-outline-secondary me-2" onclick="window.print()">
                   <i class="fas fa-print me-2"></i>Print Report
               </button>-->
               <div class="btn-group">
                   <button type="button" class="btn btn-gold dropdown-toggle" data-bs-toggle="dropdown">
                       <i class="fas fa-download me-2"></i>Export Customers
                   </button>
                   <ul class="dropdown-menu">
                       <li><a class="dropdown-item" href="users-export.php?<?php echo http_build_query($_GET); ?>">
                           <i class="fas fa-file-csv me-2"></i>Export Detailed CSV
                       </a></li>
                       <li><a class="dropdown-item" href="users-export-basic.php?<?php echo http_build_query($_GET); ?>">
                           <i class="fas fa-file-alt me-2"></i>Export Basic CSV
                       </a></li>
                       <li><hr class="dropdown-divider"></li>
                       <li><a class="dropdown-item" href="users-export.php?export_type=all">
                           <i class="fas fa-database me-2"></i>Export All Customers
                       </a></li>
                       <li><a class="dropdown-item" href="users-export.php?<?php echo http_build_query($_GET); ?>&export_type=mailing">
                           <i class="fas fa-envelope me-2"></i>Export Mailing List
                       </a></li>
                   </ul>
               </div>
           </div>
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
                    <h4 class="mb-0"><?php echo $stats['total_users']; ?></h4>
                    <small>Total Customers</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-success text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['active_users']; ?></h4>
                    <small>Active</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-warning text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['new_users_30d']; ?></h4>
                    <small>New (30d)</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-info text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['users_with_orders']; ?></h4>
                    <small>With Orders</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-danger text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['inactive_users']; ?></h4>
                    <small>Inactive</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-gold text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo CURRENCY . ' ' . number_format($revenue_stats['total_revenue'] ?? 0); ?></h4>
                    <small>Total Revenue</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <!-- Preserve view_user_id in filter form -->
                <?php if (isset($_GET['view_user_id'])): ?>
                    <input type="hidden" name="view_user_id" value="<?php echo $_GET['view_user_id']; ?>">
                <?php endif; ?>
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Name, Email, Phone, Address...">
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
                    <label for="city" class="form-label">City</label>
                    <select class="form-select" id="city" name="city">
                        <option value="all">All Cities</option>
                        <?php foreach ($cities as $city_name): ?>
                            <option value="<?php echo htmlspecialchars($city_name); ?>" <?php echo $city === $city_name ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($city_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="country" class="form-label">Country</label>
                    <select class="form-select" id="country" name="country">
                        <option value="all">All Countries</option>
                        <?php foreach ($countries as $country_name): ?>
                            <option value="<?php echo htmlspecialchars($country_name); ?>" <?php echo $country === $country_name ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($country_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from"
                                   value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-6">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to"
                                   value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-gold w-100">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </form>

            <!-- Active Filters -->
            <?php if (!empty($search) || !empty($status) || !empty($city) || !empty($country) || !empty($date_from) || !empty($date_to)): ?>
                <div class="mt-3">
                    <small class="text-muted">Active filters:</small>
                    <?php if (!empty($search)): ?>
                        <span class="badge bg-primary me-2">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($status) && $status !== 'all'): ?>
                        <span class="badge bg-info me-2">
                            Status: <?php echo ucfirst($status); ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($city) && $city !== 'all'): ?>
                        <span class="badge bg-warning me-2">
                            City: <?php echo htmlspecialchars($city); ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['city' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <a href="users.php" class="btn btn-sm btn-outline-secondary">Clear All</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Users Table -->
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
                            <option value="delete">Delete</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-gold" id="applyBulkActionBtn">Apply</button>
                        <span class="text-muted ms-2">
                            <?php echo $totalUsers; ?> user(s) found
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
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Location</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" class="user-checkbox">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center"
                                                         style="width: 40px; height: 40px;">
                                                        <i class="fas fa-user text-muted"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h6>
                                                    <small class="text-muted">ID: #<?php echo $user['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($user['mobile']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($user['city'] || $user['country']): ?>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($user['city'] ?: 'N/A'); ?>,
                                                    <?php echo htmlspecialchars($user['country'] ?: 'N/A'); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">Not provided</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['order_count'] > 0): ?>
                                                <span class="badge bg-primary"><?php echo $user['order_count']; ?> orders</span>
                                                <?php if ($user['last_order_date']): ?>
                                                    <small class="text-muted d-block">
                                                        Last: <?php echo date('M j, Y', strtotime($user['last_order_date'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No orders</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['total_spent'] > 0): ?>
                                                <strong class="text-gold"><?php echo CURRENCY . ' ' . number_format($user['total_spent']); ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['last_login']): ?>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y', strtotime($user['last_login'])); ?>
                                                    <br>
                                                    <small><?php echo date('g:i A', strtotime($user['last_login'])); ?></small>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                            <br>
                                                <small><?php echo date('g:i A', strtotime($user['created_at'])); ?></small>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <!-- View Details Button - Only includes view_user_id -->
                                                <a href="?<?php
                                                    $query_params = ['view_user_id' => $user['id']];
                                                    // Preserve only filter parameters, exclude action parameters
                                                    $preserved_params = ['search', 'status', 'city', 'country', 'date_from', 'date_to', 'page'];
                                                    foreach ($preserved_params as $param) {
                                                        if (isset($_GET[$param]) && !empty($_GET[$param])) {
                                                            $query_params[$param] = $_GET[$param];
                                                        }
                                                    }
                                                    echo http_build_query($query_params);
                                                ?>#userDetailsModal"
                                                   class="btn btn-outline-secondary"
                                                   title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <!-- Activate/Deactivate Button - Excludes view_user_id -->
                                                <a href="?action=toggle_status&id=<?php echo $user['id']; ?>&<?php
                                                    // Remove view_user_id from parameters for action buttons
                                                    $action_params = $_GET;
                                                    unset($action_params['view_user_id']);
                                                    echo http_build_query($action_params);
                                                ?>"
                                                   class="btn btn-<?php echo $user['status'] === 'active' ? 'danger' : 'success'; ?>"
                                                   title="<?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-power-off"></i>
                                                </a>

                                                <!-- Delete Button - Also excludes view_user_id -->
                                                <a href="#"
                                                   class="btn btn-outline-danger delete-user-btn"
                                                   data-user-id="<?php echo $user['id']; ?>"
                                                   data-user-name="<?php echo htmlspecialchars($user['name']); ?>"
                                                   title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No customers found</h5>
                                        <p class="text-muted mb-3">
                                            <?php if (!empty($search) || !empty($status) || !empty($city)): ?>
                                                Try adjusting your search criteria
                                            <?php else: ?>
                                                No customers have registered yet
                                            <?php endif; ?>
                                        </p>
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
                    <nav aria-label="Users pagination">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php
                                    $query_params = $_GET;
                                    $query_params['page'] = $page - 1;
                                    echo http_build_query($query_params);
                                ?>">
                                    <i class="fas fa-chevron-left me-1"></i>Previous
                                </a>
                            </li>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php
                                            $query_params = $_GET;
                                            $query_params['page'] = $i;
                                            echo http_build_query($query_params);
                                        ?>">
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
                                <a class="page-link" href="?<?php
                                    $query_params = $_GET;
                                    $query_params['page'] = $page + 1;
                                    echo http_build_query($query_params);
                                ?>">
                                    Next<i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- User Details Modal -->
<div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userDetailsModalLabel">Customer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <?php if ($user_details): ?>
                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto"
                                 style="width: 100px; height: 100px;">
                                <i class="fas fa-user fa-3x text-muted"></i>
                            </div>
                            <h4 class="mt-3"><?php echo htmlspecialchars($user_details['name']); ?></h4>
                            <span class="badge bg-<?php echo $user_details['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($user_details['status']); ?>
                            </span>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <strong>Email:</strong><br>
                                    <?php echo htmlspecialchars($user_details['email']); ?>
                                </div>
                                <div class="col-6 mb-3">
                                    <strong>Phone:</strong><br>
                                    <?php echo htmlspecialchars($user_details['mobile'] ?: 'Not provided'); ?>
                                </div>
                                <div class="col-6 mb-3">
                                    <strong>Location:</strong><br>
                                    <?php echo htmlspecialchars($user_details['city'] ?: 'N/A'); ?>,
                                    <?php echo htmlspecialchars($user_details['country'] ?: 'N/A'); ?>
                                </div>
                                <div class="col-6 mb-3">
                                    <strong>Address:</strong><br>
                                    <?php echo htmlspecialchars($user_details['address'] ?: 'Not provided'); ?>
                                </div>
                                <div class="col-6 mb-3">
                                    <strong>Total Orders:</strong><br>
                                    <span class="badge bg-primary"><?php echo $user_details['order_count']; ?></span>
                                </div>
                                <div class="col-6 mb-3">
                                    <strong>Total Spent:</strong><br>
                                    <span class="text-gold fw-bold"><?php echo CURRENCY . ' ' . number_format($user_details['total_spent'] ?? 0); ?></span>
                                </div>
                                <div class="col-6 mb-3">
                                    <strong>Last Login:</strong><br>
                                    <?php if ($user_details['last_login']): ?>
                                        <?php echo date('M j, Y g:i A', strtotime($user_details['last_login'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6 mb-3">
                                    <strong>Registered:</strong><br>
                                    <?php echo date('M j, Y g:i A', strtotime($user_details['created_at'])); ?>
                                </div>
                            </div>

                            <?php if (!empty($user_details['recent_orders'])): ?>
                                <hr>
                                <h6>Recent Orders</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Order #</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($user_details['recent_orders'] as $order): ?>
                                                <tr>
                                                    <td>#<?php echo $order['order_number']; ?></td>
                                                    <td><?php echo CURRENCY . ' ' . number_format($order['total_amount']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                                            <?php echo ucfirst($order['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-circle fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Customer not found</h5>
                        <p class="text-muted">The requested customer details could not be loaded.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <?php if ($user_details): ?>
                    <a href="?action=toggle_status&id=<?php echo $user_details['id']; ?>"
                       class="btn btn-<?php echo $user_details['status'] === 'active' ? 'warning' : 'success'; ?>">
                        <?php echo $user_details['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                    </a>
                    <a href="#"
                       class="btn btn-danger delete-user-btn"
                       data-user-id="<?php echo $user_details['id']; ?>"
                       data-user-name="<?php echo htmlspecialchars($user_details['name']); ?>">
                        Delete
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Confirmation Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteUserName"></strong>?</p>
                <p class="text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This action cannot be undone. All user data including cart items will be permanently deleted.
                </p>
                <p><strong>Note:</strong> Users with existing orders cannot be deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete User</a>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkDeleteModalLabel">Confirm Bulk Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="bulkDeleteCount"></strong> selected user(s)?</p>
                <p class="text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This action cannot be undone. All user data including cart items will be permanently deleted.
                </p>
                <p><strong>Note:</strong> Users with existing orders cannot be deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmBulkDeleteBtn" class="btn btn-danger">Delete Users</button>
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
document.addEventListener('DOMContentLoaded', function() {
    // Select All Checkbox
    const selectAll = document.getElementById('selectAll');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        });
    }

    // Delete User Modal
    const deleteUserModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    const deleteUserName = document.getElementById('deleteUserName');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteUserButtons = document.querySelectorAll('.delete-user-btn');

    deleteUserButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');

            deleteUserName.textContent = userName;
            confirmDeleteBtn.href = `?action=delete&id=${userId}`;

            deleteUserModal.show();
        });
    });

    // Bulk Delete Modal
    const bulkDeleteModal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
    const bulkDeleteCount = document.getElementById('bulkDeleteCount');
    const confirmBulkDeleteBtn = document.getElementById('confirmBulkDeleteBtn');
    const bulkActionForm = document.getElementById('bulkActionForm');
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    const applyBulkActionBtn = document.getElementById('applyBulkActionBtn');

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

    // Handle bulk action button click
    if (applyBulkActionBtn) {
        applyBulkActionBtn.addEventListener('click', function() {
            const selectedUsers = document.querySelectorAll('.user-checkbox:checked');
            const bulkAction = bulkActionSelect.value;

            if (selectedUsers.length === 0) {
                showBootstrapAlert('Please select at least one user to perform bulk action.');
                return false;
            }

            if (!bulkAction) {
                showBootstrapAlert('Please select a bulk action.');
                return false;
            }

            if (bulkAction === 'delete') {
                // Show bulk delete confirmation modal
                bulkDeleteCount.textContent = selectedUsers.length;
                bulkDeleteModal.show();
            } else {
                // For activate/deactivate, submit form directly
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

    // Auto-show user details modal if view_user_id is present
    <?php if (isset($_GET['view_user_id'])): ?>
        const userDetailsModal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
        userDetailsModal.show();
    <?php endif; ?>
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>