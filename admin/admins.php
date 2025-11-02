<?php
require_once 'includes/admin-header.php';

// Initialize database connection
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Check if user is super admin (only super admins can manage other admins)
if (!$_SESSION['is_super_admin']) {
    header('Location: dashboard.php');
    exit;
}

// Handle admin actions
$success_message = '';
$error_message = '';

// Add new admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'admin';
    $status = $_POST['status'] === 'active' ? 'active' : 'inactive';

    // Validation
    $errors = [];

    if (empty($name)) {
        $errors[] = "Admin name is required.";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email address is required.";
    }

    if (empty($mobile)) {
        $errors[] = "Mobile number is required.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Email address is already registered.";
    }

    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (name, email, mobile, password, role, status, is_super_admin)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            // Only set as super admin if explicitly selected and current user is super admin
            $is_super_admin = ($role === 'super_admin' && $_SESSION['is_super_admin']) ? 1 : 0;
            // If setting as super admin, force role to be 'admin'
            $final_role = $is_super_admin ? 'admin' : $role;

            $stmt->execute([$name, $email, $mobile, $hashed_password, $final_role, $status, $is_super_admin]);

            $success_message = "Admin user added successfully!";
        } catch (PDOException $e) {
            $error_message = "Error adding admin user: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Update admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
    $admin_id = intval($_POST['admin_id']);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $role = $_POST['role'] ?? 'admin';
    $status = $_POST['status'] === 'active' ? 'active' : 'inactive';

    // Prevent self-modification of role/status
    if ($admin_id == $_SESSION['admin_id']) {
        $error_message = "You cannot modify your own account through this interface.";
    } else {
        $errors = [];

        if (empty($name)) {
            $errors[] = "Admin name is required.";
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email address is required.";
        }

        if (empty($mobile)) {
            $errors[] = "Mobile number is required.";
        }

        // Check if email already exists (excluding current admin)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $admin_id]);
        if ($stmt->fetch()) {
            $errors[] = "Email address is already registered.";
        }

        if (empty($errors)) {
            try {
                // Only set as super admin if explicitly selected and current user is super admin
                $is_super_admin = ($role === 'super_admin' && $_SESSION['is_super_admin']) ? 1 : 0;
                $final_role = $is_super_admin ? 'admin' : $role;

                $sql = "UPDATE users SET
                        name = ?, email = ?, mobile = ?, role = ?, status = ?, is_super_admin = ?
                        WHERE id = ? AND role = 'admin'";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$name, $email, $mobile, $final_role, $status, $is_super_admin, $admin_id]);

                $success_message = "Admin user updated successfully!";
            } catch (PDOException $e) {
                $error_message = "Error updating admin user: " . $e->getMessage();
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }
}

// Update admin password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $admin_id = intval($_POST['admin_id']);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];

    if (empty($new_password)) {
        $errors[] = "New password is required.";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $errors[] = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
    }

    if ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'admin'");
            $stmt->execute([$hashed_password, $admin_id]);

            $success_message = "Admin password updated successfully!";
        } catch (PDOException $e) {
            $error_message = "Error updating password: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Delete admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    $admin_id = intval($_POST['admin_id']);

    // Prevent self-deletion
    if ($admin_id == $_SESSION['admin_id']) {
        $error_message = "You cannot delete your own account.";
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin' AND id != ?");
            $stmt->execute([$admin_id, $_SESSION['admin_id']]);

            if ($stmt->rowCount() > 0) {
                $success_message = "Admin user deleted successfully!";
            } else {
                $error_message = "Admin user not found or cannot be deleted.";
            }
        } catch (PDOException $e) {
            $error_message = "Error deleting admin user: " . $e->getMessage();
        }
    }
}

// Toggle admin status
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $admin_id = intval($_GET['id']);

    // Prevent self-status change
    if ($admin_id == $_SESSION['admin_id']) {
        $error_message = "You cannot change your own status.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND role = 'admin'");
            $stmt->execute([$admin_id]);
            $success_message = "Admin status updated successfully!";
        } catch (PDOException $e) {
            $error_message = "Error updating admin status: " . $e->getMessage();
        }
    }
}

// Get admin data for editing
$edit_admin = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$edit_id]);
    $edit_admin = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Build WHERE conditions
$whereConditions = ["u.role = 'admin'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($role) && $role !== 'all') {
    if ($role === 'super_admin') {
        $whereConditions[] = "u.is_super_admin = 1";
    } else {
        $whereConditions[] = "u.role = ?";
        $params[] = $role;
    }
}

if (!empty($status) && $status !== 'all') {
    $whereConditions[] = "u.status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count for pagination
$countSql = "SELECT COUNT(DISTINCT u.id) as total FROM users u WHERE $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalAdmins = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalAdmins / $limit);

// Get admins with pagination
$sql = "SELECT
            u.*,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as managed_orders,
            (SELECT COUNT(*) FROM products) as total_products,
            DATEDIFF(CURDATE(), u.created_at) as days_since_join
        FROM users u
        WHERE $whereClause
        ORDER BY u.is_super_admin DESC, u.created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get admin statistics
$stats_stmt = $conn->query("
    SELECT
        COUNT(*) as total_admins,
        SUM(CASE WHEN is_super_admin = 1 THEN 1 ELSE 0 END) as super_admins,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_admins,
        SUM(CASE WHEN last_login IS NOT NULL THEN 1 ELSE 0 END) as logged_in_admins
    FROM users
    WHERE role = 'admin'
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 brand-font mb-1">Admin Users Management</h1>
            <p class="text-muted mb-0">Manage administrative users</p>
        </div>
        <div>
            <button type="button" class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                <i class="fas fa-plus me-2"></i>Add New Admin
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
        <div class="col-xl-3 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-primary text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['total_admins']; ?></h4>
                    <small>Total Admins</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-warning text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['super_admins']; ?></h4>
                    <small>Super Admins</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-danger text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['active_admins']; ?></h4>
                    <small>Active</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-4 col-6 mb-3">
            <div class="card border-0 bg-gold text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['logged_in_admins']; ?></h4>
                    <small>Logged In</small>
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
                           placeholder="Name, Email, Phone...">
                </div>
                <div class="col-md-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="all">All Roles</option>
                        <option value="super_admin" <?php echo $role === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                        <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all">All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-gold w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>

            <!-- Active Filters -->
            <?php if (!empty($search) || !empty($role) || !empty($status)): ?>
                <div class="mt-3">
                    <small class="text-muted">Active filters:</small>
                    <?php if (!empty($search)): ?>
                        <span class="badge bg-primary me-2">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($role) && $role !== 'all'): ?>
                        <span class="badge bg-info me-2">
                            Role: <?php echo ucfirst(str_replace('_', ' ', $role)); ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['role' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($status) && $status !== 'all'): ?>
                        <span class="badge bg-warning me-2">
                            Status: <?php echo ucfirst($status); ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <a href="admins.php" class="btn btn-sm btn-outline-secondary">Clear All</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Admins Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Admin User</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Activity</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($admins)): ?>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center"
                                                     style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user-shield text-muted"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <?php echo htmlspecialchars($admin['name']); ?>
                                                    <?php if ($admin['id'] == $_SESSION['admin_id']): ?>
                                                        <span class="badge bg-primary ms-1">You</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted">ID: #<?php echo $admin['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($admin['email']); ?></strong>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($admin['mobile']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <?php if ($admin['is_super_admin']): ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-crown me-1"></i>Super Admin
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-info">
                                                    Admin
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($admin['last_login']): ?>
                                            <small class="text-muted">
                                                Last Login: <?php echo date('M j, Y', strtotime($admin['last_login'])); ?>
                                                <br>
                                                <small><?php echo date('g:i A', strtotime($admin['last_login'])); ?></small>
                                            </small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Never Logged In</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $admin['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($admin['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($admin['created_at'])); ?>
                                            <br>
                                            <small><?php echo $admin['days_since_join']; ?> days ago</small>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editAdminModal"
                                                    onclick="loadAdminData(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['name']); ?>', '<?php echo htmlspecialchars($admin['email']); ?>', '<?php echo htmlspecialchars($admin['mobile']); ?>', '<?php echo $admin['status']; ?>', <?php echo $admin['is_super_admin'] ? 1 : 0; ?>)"
                                                    title="Edit Admin"
                                                    <?php echo ($admin['id'] == $_SESSION['admin_id']) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-info"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#changePasswordModal"
                                                    onclick="setAdminForPasswordChange(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['name']); ?>')"
                                                    title="Change Password"
                                                    <?php echo ($admin['id'] == $_SESSION['admin_id']) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <a href="?action=toggle_status&id=<?php echo $admin['id']; ?>"
                                               class="btn btn-<?php echo $admin['status'] === 'active' ? 'danger' : 'success'; ?>"
                                               title="<?php echo $admin['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>"
                                               <?php echo ($admin['id'] == $_SESSION['admin_id']) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-power-off"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteAdminModal"
                                                    onclick="setAdminForDeletion(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['name']); ?>')"
                                                    title="Delete"
                                                    <?php echo ($admin['id'] == $_SESSION['admin_id']) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-user-shield fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No admin users found</h5>
                                    <p class="text-muted mb-3">
                                        <?php if (!empty($search) || !empty($role) || !empty($status)): ?>
                                            Try adjusting your search criteria
                                        <?php else: ?>
                                            Get started by adding your first admin user
                                        <?php endif; ?>
                                    </p>
                                    <button type="button" class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                                        <i class="fas fa-plus me-2"></i>Add New Admin
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
                                Showing <?php echo min($offset + 1, $totalAdmins); ?>-<?php echo min($offset + count($admins), $totalAdmins); ?> of <?php echo $totalAdmins; ?> admins
                            </span>
                        </div>
                        <nav aria-label="Admins pagination">
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

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAdminModalLabel">Add New Admin User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="addAdminForm">
                <div class="modal-body">
                    <input type="hidden" name="add_admin" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="mobile" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="mobile" name="mobile" required>
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="admin">Admin</option>
                                <?php if ($_SESSION['is_super_admin']): ?>
                                    <option value="super_admin">Super Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="8">
                            <div class="form-text">Minimum 8 characters with uppercase, lowercase, and number</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold">Add Admin User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1" aria-labelledby="editAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAdminModalLabel">Edit Admin User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="editAdminForm">
                <div class="modal-body">
                    <input type="hidden" name="update_admin" value="1">
                    <input type="hidden" name="admin_id" id="edit_admin_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_mobile" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="edit_mobile" name="mobile" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="admin">Admin</option>
                                <?php if ($_SESSION['is_super_admin']): ?>
                                    <option value="super_admin">Super Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold">Update Admin User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">Change Admin Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="changePasswordForm">
                <div class="modal-body">
                    <input type="hidden" name="update_password" value="1">
                    <input type="hidden" name="admin_id" id="password_admin_id">

                    <div class="mb-3">
                        <label for="admin_name_display" class="form-label">Admin User</label>
                        <input type="text" class="form-control" id="admin_name_display" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                        <div class="form-text">Minimum 8 characters with uppercase, lowercase, and number</div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteAdminModal" tabindex="-1" aria-labelledby="deleteAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteAdminModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="deleteAdminForm">
                <div class="modal-body">
                    <input type="hidden" name="delete_admin" value="1">
                    <input type="hidden" name="admin_id" id="delete_admin_id">

                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h5>Are you sure you want to delete this admin?</h5>
                        <p class="text-muted">
                            You are about to delete admin: <strong id="delete_admin_name"></strong>
                        </p>
                        <p class="text-danger">
                            <small>
                                <i class="fas fa-exclamation-circle me-1"></i>
                                This action cannot be undone. All data associated with this admin will be permanently removed.
                            </small>
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Admin</button>
                </div>
            </form>
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

.bg-gold {
    background-color: var(--primary-color) !important;
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

    .col-xl-2 {
        margin-bottom: 1rem;
    }
}

/* Password strength indicator */
.password-strength {
    margin-top: 5px;
}

.strength-weak { color: #dc3545; }
.strength-medium { color: #ffc107; }
.strength-strong { color: #28a745; }
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
    // Password strength indicator for add form
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');

    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            validatePasswordMatch();
        });
    }

    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);
    }

    // Password strength indicator for change password form
    const newPasswordInput = document.getElementById('new_password');
    const confirmNewPasswordInput = document.getElementById('confirm_new_password');

    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value, 'change');
            validateNewPasswordMatch();
        });
    }

    if (confirmNewPasswordInput) {
        confirmNewPasswordInput.addEventListener('input', validateNewPasswordMatch);
    }

    // Auto-submit filters
    const autoSubmitFilters = ['role', 'status'];
    autoSubmitFilters.forEach(filter => {
        const element = document.getElementById(filter);
        if (element) {
            element.addEventListener('change', function() {
                this.form.submit();
            });
        }
    });

    // Quick search with debounce
    let searchTimeout;
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    }

    // Form validation
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            let valid = true;
            let errorMessages = [];

            // Common validations for add and edit forms
            if (this.id === 'addAdminForm' || this.id === 'editAdminForm') {
                const name = this.querySelector('[name="name"]');
                const email = this.querySelector('[name="email"]');
                const mobile = this.querySelector('[name="mobile"]');

                if (name && !name.value.trim()) {
                    valid = false;
                    name.classList.add('is-invalid');
                    errorMessages.push('Full name is required');
                }

                if (email && (!email.value.trim() || !email.validity.valid)) {
                    valid = false;
                    email.classList.add('is-invalid');
                    errorMessages.push('Valid email address is required');
                }

                if (mobile && !mobile.value.trim()) {
                    valid = false;
                    mobile.classList.add('is-invalid');
                    errorMessages.push('Mobile number is required');
                }
            }

            // Password validation for add form
            if (this.id === 'addAdminForm') {
                const password = this.querySelector('[name="password"]');
                const confirmPassword = this.querySelector('[name="confirm_password"]');

                if (password && password.value.length < 8) {
                    valid = false;
                    password.classList.add('is-invalid');
                    errorMessages.push('Password must be at least 8 characters long');
                }

                if (confirmPassword && password && password.value !== confirmPassword.value) {
                    valid = false;
                    confirmPassword.classList.add('is-invalid');
                    errorMessages.push('Passwords do not match');
                }
            }

            // Password validation for change password form
            if (this.id === 'changePasswordForm') {
                const newPassword = this.querySelector('[name="new_password"]');
                const confirmPassword = this.querySelector('[name="confirm_password"]');

                if (newPassword && newPassword.value.length < 8) {
                    valid = false;
                    newPassword.classList.add('is-invalid');
                    errorMessages.push('New password must be at least 8 characters long');
                }

                if (confirmPassword && newPassword && newPassword.value !== confirmPassword.value) {
                    valid = false;
                    confirmPassword.classList.add('is-invalid');
                    errorMessages.push('Passwords do not match');
                }
            }

            if (!valid) {
                e.preventDefault();
                const errorMessage = errorMessages.length > 0 ? errorMessages.join('<br>') : 'Please fill in all required fields correctly.';
                showBootstrapAlert(errorMessage, 'danger');
            }
        });
    });

    // Clear validation on input
    document.querySelectorAll('.form-control').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
});

function checkPasswordStrength(password, formType = 'add') {
    let strengthText = '';
    let strengthClass = '';

    if (password.length === 0) {
        strengthText = '';
    } else if (password.length < 8) {
        strengthText = 'Weak - too short';
        strengthClass = 'strength-weak';
    } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])/.test(password)) {
        strengthText = 'Medium - add uppercase, lowercase, and number';
        strengthClass = 'strength-medium';
    } else {
        strengthText = 'Strong';
        strengthClass = 'strength-strong';
    }

    const strengthElement = document.getElementById(formType + '_password_strength');
    if (strengthElement) {
        strengthElement.textContent = strengthText;
        strengthElement.className = 'form-text ' + strengthClass;
    }
}

function validatePasswordMatch() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchElement = document.getElementById('password_match');

    if (!matchElement) {
        const matchDiv = document.createElement('div');
        matchDiv.id = 'password_match';
        matchDiv.className = 'form-text';
        document.getElementById('confirm_password').parentNode.appendChild(matchDiv);
    }

    if (confirmPassword.length === 0) {
        matchElement.textContent = '';
    } else if (password !== confirmPassword) {
        matchElement.textContent = 'Passwords do not match';
        matchElement.className = 'form-text strength-weak';
    } else {
        matchElement.textContent = 'Passwords match';
        matchElement.className = 'form-text strength-strong';
    }
}

function validateNewPasswordMatch() {
    const newPassword = document.getElementById('new_password').value;
    const confirmNewPassword = document.getElementById('confirm_new_password').value;
    const matchElement = document.getElementById('new_password_match');

    if (!matchElement) {
        const matchDiv = document.createElement('div');
        matchDiv.id = 'new_password_match';
        matchDiv.className = 'form-text';
        document.getElementById('confirm_new_password').parentNode.appendChild(matchDiv);
    }

    if (confirmNewPassword.length === 0) {
        matchElement.textContent = '';
    } else if (newPassword !== confirmNewPassword) {
        matchElement.textContent = 'Passwords do not match';
        matchElement.className = 'form-text strength-weak';
    } else {
        matchElement.textContent = 'Passwords match';
        matchElement.className = 'form-text strength-strong';
    }
}

// Load admin data for editing
function loadAdminData(adminId, name, email, mobile, status, isSuperAdmin) {
    document.getElementById('edit_admin_id').value = adminId;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_mobile').value = mobile;
    document.getElementById('edit_status').value = status;

    // Set role - if super admin, show as super_admin option
    if (isSuperAdmin) {
        document.getElementById('edit_role').value = 'super_admin';
    } else {
        document.getElementById('edit_role').value = 'admin';
    }
}

// Set admin for password change
function setAdminForPasswordChange(adminId, adminName) {
    document.getElementById('password_admin_id').value = adminId;
    document.getElementById('admin_name_display').value = adminName;
}

// Set admin for deletion
function setAdminForDeletion(adminId, adminName) {
    document.getElementById('delete_admin_id').value = adminId;
    document.getElementById('delete_admin_name').textContent = adminName;
}
</script>

<?php require_once 'includes/admin-footer.php'; ?>