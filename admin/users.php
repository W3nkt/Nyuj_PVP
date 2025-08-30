<?php
$page_title = 'User Management - Admin Panel';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';

requireRole('admin');

$db = new Database();
$conn = $db->connect();

$error_message = '';
$success_message = '';

// Handle user actions (ban, unban, delete, role change)
if ($_POST && isset($_POST['action'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($user_id > 0) {
        try {
            switch ($action) {
                case 'ban':
                    $stmt = $conn->prepare("UPDATE users SET status = 'banned' WHERE id = ? AND role != 'admin'");
                    $stmt->execute([$user_id]);
                    $success_message = 'User has been banned successfully.';
                    break;
                    
                case 'unban':
                    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role != 'admin'");
                    $stmt->execute([$user_id]);
                    $success_message = 'User has been unbanned successfully.';
                    break;
                    
                case 'delete':
                    // Don't allow deleting admins
                    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if ($user && $user['role'] !== 'admin') {
                        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $success_message = 'User has been deleted successfully.';
                    } else {
                        $error_message = 'Cannot delete admin users.';
                    }
                    break;
                    
                case 'change_role':
                    $new_role = $_POST['new_role'] ?? '';
                    $valid_roles = ['user', 'streamer', 'admin'];
                    
                    if (in_array($new_role, $valid_roles)) {
                        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                        $stmt->execute([$new_role, $user_id]);
                        $success_message = "User role has been changed to {$new_role}.";
                    } else {
                        $error_message = 'Invalid role specified.';
                    }
                    break;
            }
        } catch (PDOException $e) {
            $error_message = 'An error occurred while processing the request.';
            error_log('User management error: ' . $e->getMessage());
        }
    }
}

// Get filter parameters
$filter_role = $_GET['role'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search_query = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE clause for filters
$where_conditions = ['1=1'];
$params = [];

if ($filter_role) {
    $where_conditions[] = 'role = ?';
    $params[] = $filter_role;
}

if ($filter_status) {
    $where_conditions[] = 'status = ?';
    $params[] = $filter_status;
}

if ($search_query) {
    $where_conditions[] = '(username LIKE ? OR email LIKE ?)';
    $params[] = "%{$search_query}%";
    $params[] = "%{$search_query}%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE {$where_clause}");
$stmt->execute($params);
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Get users with pagination
$stmt = $conn->prepare("
    SELECT u.*, 
           COALESCE(w.balance, 0) as wallet_balance,
           COUNT(DISTINCT b.id) as total_bets,
           COUNT(DISTINCT e.id) as events_created
    FROM users u 
    LEFT JOIN wallets w ON u.id = w.user_id
    LEFT JOIN bets b ON u.id = b.user_id
    LEFT JOIN events e ON u.id = e.created_by
    WHERE {$where_clause}
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
$stmt = $conn->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$stmt->execute();
$role_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM users GROUP BY status");
$stmt->execute();
$status_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

require_once '../includes/header.php';
?>

<div class="container mt-4" style="padding-top: 40px;">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/index.php'); ?>">Admin</a></li>
                    <li class="breadcrumb-item active">User Management</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check me-2"></i><?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-2x text-primary mb-2"></i>
                    <h3 class="text-primary"><?php echo $total_users; ?></h3>
                    <p class="card-text">Total Users</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <i class="fas fa-user-check fa-2x text-success mb-2"></i>
                    <h3 class="text-success"><?php echo $status_stats['active'] ?? 0; ?></h3>
                    <p class="card-text">Active Users</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <i class="fas fa-video fa-2x text-warning mb-2"></i>
                    <h3 class="text-warning"><?php echo $role_stats['streamer'] ?? 0; ?></h3>
                    <p class="card-text">Streamers</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <i class="fas fa-user-slash fa-2x text-danger mb-2"></i>
                    <h3 class="text-danger"><?php echo $status_stats['banned'] ?? 0; ?></h3>
                    <p class="card-text">Banned Users</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search Users</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search_query); ?>" 
                                   placeholder="Username or email">
                        </div>
                        <div class="col-md-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role">
                                <option value="">All Roles</option>
                                <option value="user" <?php echo $filter_role === 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="streamer" <?php echo $filter_role === 'streamer' ? 'selected' : ''; ?>>Streamer</option>
                                <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="banned" <?php echo $filter_status === 'banned' ? 'selected' : ''; ?>>Banned</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i>Search
                            </button>
                            <a href="<?php echo url('admin/users.php'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-users me-2"></i>Users</h5>
                    <div>
                        <a href="<?php echo url('admin/create_user.php'); ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-plus me-1"></i>Add User
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-user-times fa-3x text-muted mb-3"></i>
                            <h5>No Users Found</h5>
                            <p class="text-muted">No users match your current search criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Stats</th>
                                        <th>Wallet</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar me-3">
                                                        <i class="fas fa-user-circle fa-2x text-muted"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'streamer' ? 'warning' : 'primary'); ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : ($user['status'] === 'banned' ? 'danger' : 'warning'); ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo $user['total_bets']; ?> bets<br>
                                                    <?php echo $user['events_created']; ?> events
                                                </small>
                                            </td>
                                            <td>
                                                <strong class="text-success">$<?php echo number_format($user['wallet_balance'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($user['role'] !== 'admin'): ?>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-primary dropdown-toggle" 
                                                                data-bs-toggle="dropdown">
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                    <input type="hidden" name="action" value="<?php echo $user['status'] === 'banned' ? 'unban' : 'ban'; ?>">
                                                                    <button type="submit" class="dropdown-item" 
                                                                            onclick="return confirm('Are you sure?')">
                                                                        <i class="fas fa-<?php echo $user['status'] === 'banned' ? 'user-check' : 'ban'; ?> me-2"></i>
                                                                        <?php echo $user['status'] === 'banned' ? 'Unban' : 'Ban'; ?> User
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <button type="button" class="dropdown-item" 
                                                                        onclick="showRoleModal(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>', '<?php echo $user['role']; ?>')">
                                                                    <i class="fas fa-user-tag me-2"></i>Change Role
                                                                </button>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <button type="submit" class="dropdown-item text-danger" 
                                                                            onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                                        <i class="fas fa-trash me-2"></i>Delete User
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                <?php else: ?>
                                                    <small class="text-muted">Admin</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav>
                                <ul class="pagination justify-content-center mt-4">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(['role' => $filter_role, 'status' => $filter_status, 'search' => $search_query]); ?>">
                                                Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(['role' => $filter_role, 'status' => $filter_status, 'search' => $search_query]); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(['role' => $filter_role, 'status' => $filter_status, 'search' => $search_query]); ?>">
                                                Next
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Role Change Modal -->
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change User Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="roleForm">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="roleUserId">
                    <input type="hidden" name="action" value="change_role">
                    
                    <p>Change role for user: <strong id="roleUsername"></strong></p>
                    
                    <div class="mb-3">
                        <label for="new_role" class="form-label">New Role</label>
                        <select class="form-select" name="new_role" id="new_role" required>
                            <option value="user">User</option>
                            <option value="streamer">Streamer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Role Permissions:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>User:</strong> Can place bets and participate in events</li>
                            <li><strong>Streamer:</strong> Can vote on event outcomes</li>
                            <li><strong>Admin:</strong> Full system access</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showRoleModal(userId, username, currentRole) {
    document.getElementById('roleUserId').value = userId;
    document.getElementById('roleUsername').textContent = username;
    document.getElementById('new_role').value = currentRole;
    
    new bootstrap.Modal(document.getElementById('roleModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>