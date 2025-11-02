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

// Update message status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $message_id = intval($_POST['message_id']);
    $status = $_POST['status'];
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    try {
        $sql = "UPDATE contact_messages SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$status, $admin_notes, $message_id]);

        $success_message = "Message status updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating message: " . $e->getMessage();
    }
}

// Delete message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $message_id = intval($_POST['message_id']);

    try {
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$message_id]);
        $success_message = "Message deleted successfully!";
    } catch (PDOException $e) {
        $error_message = "Error deleting message: " . $e->getMessage();
    }
}

// Bulk actions
if (isset($_POST['bulk_action'])) {
    $message_ids = $_POST['message_ids'] ?? [];
    $action = $_POST['bulk_action'];

    if (!empty($message_ids)) {
        try {
            $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';

            switch ($action) {
                case 'mark_read':
                    $stmt = $conn->prepare("UPDATE contact_messages SET status = 'read' WHERE id IN ($placeholders)");
                    $stmt->execute($message_ids);
                    $success_message = "Selected messages marked as read!";
                    break;

                case 'mark_replied':
                    $stmt = $conn->prepare("UPDATE contact_messages SET status = 'replied' WHERE id IN ($placeholders)");
                    $stmt->execute($message_ids);
                    $success_message = "Selected messages marked as replied!";
                    break;

                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id IN ($placeholders)");
                    $stmt->execute($message_ids);
                    $success_message = "Selected messages deleted successfully!";
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Error performing bulk action: " . $e->getMessage();
        }
    } else {
        $error_message = "Please select messages to perform action.";
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$subject = isset($_GET['subject']) ? $_GET['subject'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Build WHERE conditions
$whereConditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR message LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status) && $status !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = $status;
}

if (!empty($subject) && $subject !== 'all') {
    $whereConditions[] = "subject = ?";
    $params[] = $subject;
}

if (!empty($date_from)) {
    $whereConditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $whereConditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM contact_messages WHERE $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalMessages = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalMessages / $limit);

// Get messages with pagination
$sql = "SELECT * FROM contact_messages
        WHERE $whereClause
        ORDER BY created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get message statistics
$stats_stmt = $conn->query("
    SELECT
        COUNT(*) as total_messages,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_messages,
        SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_messages,
        SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied_messages
    FROM contact_messages
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get today's messages
$today_stmt = $conn->query("
    SELECT COUNT(*) as today_messages
    FROM contact_messages
    WHERE DATE(created_at) = CURDATE()
");
$today_stats = $today_stmt->fetch(PDO::FETCH_ASSOC);

// Get unique subjects for filter
$subjects = $conn->query("
    SELECT DISTINCT subject
    FROM contact_messages
    WHERE subject IS NOT NULL AND subject != ''
    ORDER BY subject
")->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 brand-font mb-1">Contact Queries</h1>
            <p class="text-muted mb-0">Manage customer inquiries and messages</p>
        </div>
        <div class="d-flex align-items-center">
            <span class="text-muted me-3">
                <?php echo $totalMessages; ?> message(s) found
            </span>
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
                    <h4 class="mb-0"><?php echo $stats['total_messages']; ?></h4>
                    <small>Total Messages</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 bg-warning text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['pending_messages']; ?></h4>
                    <small>Pending</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 bg-info text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['read_messages']; ?></h4>
                    <small>Read</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 bg-success text-white">
                <div class="card-body text-center p-3">
                    <h4 class="mb-0"><?php echo $stats['replied_messages']; ?></h4>
                    <small>Replied</small>
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
                           placeholder="Name, Email, Phone, Message...">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all">All Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="read" <?php echo $status === 'read' ? 'selected' : ''; ?>>Read</option>
                        <option value="replied" <?php echo $status === 'replied' ? 'selected' : ''; ?>>Replied</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="subject" class="form-label">Subject</label>
                    <select class="form-select" id="subject" name="subject">
                        <option value="all">All Subjects</option>
                        <?php foreach ($subjects as $subj): ?>
                            <option value="<?php echo htmlspecialchars($subj); ?>" <?php echo $subject === $subj ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subj); ?>
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
                <div class="col-md-2">
                    <button type="submit" class="btn btn-gold w-100">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                </div>
            </form>

            <!-- Active Filters -->
            <?php if (!empty($search) || !empty($status) || !empty($subject) || !empty($date_from) || !empty($date_to)): ?>
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
                    <?php if (!empty($subject) && $subject !== 'all'): ?>
                        <span class="badge bg-warning me-2">
                            Subject: <?php echo htmlspecialchars($subject); ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['subject' => ''])); ?>" class="text-white ms-1">×</a>
                        </span>
                    <?php endif; ?>
                    <a href="contact-queries.php" class="btn btn-sm btn-outline-secondary">Clear All</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Messages Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <!-- Bulk Actions -->
            <form method="POST" action="" id="bulkActionForm">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                    <div>
                        <select class="form-select form-select-sm" name="bulk_action" id="bulkActionSelect" style="width: 200px;">
                            <option value="">Bulk Actions</option>
                            <option value="mark_read">Mark as Read</option>
                            <option value="mark_replied">Mark as Replied</option>
                            <option value="delete">Delete</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" id="applyBulkAction" class="btn btn-sm btn-gold">Apply</button>
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
                                <th>Subject</th>
                                <th>Message Preview</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($messages)): ?>
                                <?php foreach ($messages as $message): ?>
                                    <tr class="<?php echo $message['status'] === 'pending' ? 'table-warning' : ''; ?>">
                                        <td>
                                            <input type="checkbox" name="message_ids[]" value="<?php echo $message['id']; ?>" class="message-checkbox">
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($message['name']); ?></strong>
                                                <div class="text-muted small">
                                                    <?php echo htmlspecialchars($message['email']); ?>
                                                    <?php if (!empty($message['phone'])): ?>
                                                        <br><?php echo htmlspecialchars($message['phone']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($message['subject']); ?></span>
                                        </td>
                                        <td>
                                          <div class="message-preview" data-full-message="<?php echo htmlspecialchars($message['message']); ?>">
                                              <?php
                                              $preview = strip_tags($message['message']);
                                              echo strlen($preview) > 100 ?
                                                  substr($preview, 0, 100) . '...' :
                                                  $preview;
                                              ?>
                                          </div>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'pending' => ['bg-warning', 'Pending'],
                                                'read' => ['bg-info', 'Read'],
                                                'replied' => ['bg-success', 'Replied']
                                            ];
                                            $badge = $status_badges[$message['status']] ?? ['bg-secondary', 'Unknown'];
                                            ?>
                                            <span class="badge <?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($message['created_at'])); ?>
                                                <br>
                                                <small><?php echo date('g:i A', strtotime($message['created_at'])); ?></small>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button"
                                                        class="btn btn-outline-secondary view-message-btn"
                                                        data-message-id="<?php echo $message['id']; ?>"
                                                        data-admin-notes="<?php echo htmlspecialchars($message['admin_notes'] ?? ''); ?>"
                                                        title="View Message">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button"
                                                        class="btn btn-outline-danger delete-message-btn"
                                                        data-message-id="<?php echo $message['id']; ?>"
                                                        data-customer-name="<?php echo htmlspecialchars($message['name']); ?>"
                                                        data-subject="<?php echo htmlspecialchars($message['subject']); ?>"
                                                        title="Delete Message">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No messages found</h5>
                                        <p class="text-muted mb-3">
                                            <?php if (!empty($search) || !empty($status) || !empty($subject)): ?>
                                                Try adjusting your search criteria
                                            <?php else: ?>
                                                No contact messages received yet
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
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted">
                            Showing <?php echo min($offset + 1, $totalMessages); ?>-<?php echo min($offset + count($messages), $totalMessages); ?> of <?php echo $totalMessages; ?> messages
                        </span>
                    </div>
                    <nav aria-label="Messages pagination">
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

<!-- View Message Modal -->
<div class="modal fade" id="viewMessageModal" tabindex="-1" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header text-dark">
                <h5 class="modal-title" id="viewMessageModalLabel">
                    <i class="fas fa-envelope me-2"></i>Message Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="messageStatusForm" method="POST" action="">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="message_id" id="messageId">

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Customer Information</h6>
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td><strong>Name:</strong></td>
                                            <td id="customerName"></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td id="customerEmail"></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Phone:</strong></td>
                                            <td id="customerPhone"></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Subject:</strong></td>
                                            <td id="messageSubject"></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Date:</strong></td>
                                            <td id="messageDate"></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Message Status</h6>
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="pending">Pending</option>
                                            <option value="read">Read</option>
                                            <option value="replied">Replied</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="admin_notes" class="form-label">Admin Notes</label>
                                        <textarea class="form-control" id="admin_notes" name="admin_notes"
                                                  rows="3" placeholder="Add internal notes about this inquiry..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="card-title">Message Content</h6>
                            <div class="message-content p-3 bg-white rounded border" id="messageContent">
                                <!-- Message content will be loaded here -->
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="messageStatusForm" class="btn btn-gold">Update Status</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Message Modal -->
<div class="modal fade" id="deleteMessageModal" tabindex="-1" aria-labelledby="deleteMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteMessageModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. The message will be permanently deleted.
                </div>
                <p class="mb-2">Are you sure you want to delete the following message?</p>
                <div class="card border-danger mb-3">
                    <div class="card-body">
                        <h6 class="card-title text-danger" id="deleteCustomerName"></h6>
                        <p class="card-text mb-1" id="deleteMessageSubject"></p>
                        <small class="text-muted">Message ID: <span id="deleteMessageId"></span></small>
                    </div>
                </div>
                <form id="deleteMessageForm" method="POST" action="contact-queries.php">
                    <input type="hidden" name="delete_message" value="1">
                    <input type="hidden" name="message_id" id="deleteMessageIdInput">
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
                <button type="submit" form="deleteMessageForm" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Delete Message
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

.message-preview {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.message-content {
    max-height: 300px;
    overflow-y: auto;
    white-space: pre-wrap;
    font-family: inherit;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }

    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
    }

    .col-xl-3 {
        margin-bottom: 1rem;
    }

    .message-preview {
        max-width: 150px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bulk actions select all
    const selectAll = document.getElementById('selectAll');
    const messageCheckboxes = document.querySelectorAll('.message-checkbox');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            messageCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // Bulk action form handling
    const bulkActionForm = document.getElementById('bulkActionForm');
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    const applyBulkActionBtn = document.getElementById('applyBulkAction');

    if (applyBulkActionBtn) {
        applyBulkActionBtn.addEventListener('click', function() {
            const bulkAction = bulkActionSelect.value;
            const checkedBoxes = document.querySelectorAll('.message-checkbox:checked');

            if (!bulkAction) {
                alert('Please select a bulk action.');
                return;
            }

            if (checkedBoxes.length === 0) {
                alert('Please select at least one message.');
                return;
            }

            bulkActionForm.submit();
        });
    }

// View message modal
const viewMessageModal = new bootstrap.Modal(document.getElementById('viewMessageModal'));
const viewMessageBtns = document.querySelectorAll('.view-message-btn');

viewMessageBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        const messageId = this.getAttribute('data-message-id');
        const adminNotes = this.getAttribute('data-admin-notes') || '';

        // Find the message row to extract data
        const messageRow = this.closest('tr');

        // Extract data from the table row
        const customerName = messageRow.querySelector('td:nth-child(2) strong').textContent;
        const customerEmail = messageRow.querySelector('td:nth-child(2) .text-muted').textContent.split('\n')[0].trim();
        const customerPhoneElement = messageRow.querySelector('td:nth-child(2) .text-muted br');
        const customerPhone = customerPhoneElement ? customerPhoneElement.nextSibling.textContent.trim() : 'N/A';
        const messageSubject = messageRow.querySelector('td:nth-child(3) .badge').textContent;
        const messageDate = messageRow.querySelector('td:nth-child(6) small').textContent;

        // Get the full message from data attribute
        const messagePreview = messageRow.querySelector('td:nth-child(4) .message-preview');
        const fullMessage = messagePreview.getAttribute('data-full-message');

        // Get status from the badge
        const statusBadge = messageRow.querySelector('td:nth-child(5) .badge');
        let currentStatus = 'pending';
        if (statusBadge.classList.contains('bg-info')) currentStatus = 'read';
        else if (statusBadge.classList.contains('bg-success')) currentStatus = 'replied';

        // Populate modal with message data
        document.getElementById('customerName').textContent = customerName;
        document.getElementById('customerEmail').textContent = customerEmail;
        document.getElementById('customerPhone').textContent = customerPhone;
        document.getElementById('messageSubject').textContent = messageSubject;
        document.getElementById('messageDate').textContent = messageDate;
        document.getElementById('messageContent').textContent = fullMessage;

        // Set form values
        document.getElementById('messageId').value = messageId;
        document.getElementById('status').value = currentStatus;
        document.getElementById('admin_notes').value = adminNotes;

        // Show modal
        viewMessageModal.show();
    });
});


    // Delete message modal
    const deleteMessageModal = new bootstrap.Modal(document.getElementById('deleteMessageModal'));
    const deleteMessageBtns = document.querySelectorAll('.delete-message-btn');

    deleteMessageBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const messageId = this.getAttribute('data-message-id');
            const customerName = this.getAttribute('data-customer-name');
            const subject = this.getAttribute('data-subject');

            // Set modal content
            document.getElementById('deleteCustomerName').textContent = `From: ${customerName}`;
            document.getElementById('deleteMessageSubject').textContent = `Subject: ${subject}`;
            document.getElementById('deleteMessageId').textContent = messageId;
            document.getElementById('deleteMessageIdInput').value = messageId;

            // Show modal
            deleteMessageModal.show();
        });
    });

    // Auto-submit filters on some changes
    const autoSubmitFilters = ['status', 'subject'];
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
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>