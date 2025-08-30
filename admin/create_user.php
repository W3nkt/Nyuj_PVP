<?php
$page_title = 'Create User - Admin Panel';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';

requireRole('admin');

$db = new Database();
$conn = $db->connect();

$error_message = '';
$success_message = '';

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (!in_array($role, ['user', 'streamer', 'admin'])) {
        $error_message = 'Invalid role selected.';
    } elseif (!in_array($status, ['active', 'pending', 'banned'])) {
        $error_message = 'Invalid status selected.';
    } else {
        try {
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error_message = 'Username or email already exists.';
            } else {
                $conn->beginTransaction();
                
                // Create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    INSERT INTO users (username, email, password, role, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$username, $email, $hashed_password, $role, $status]);
                
                $user_id = $conn->lastInsertId();
                
                // Create wallet for the user
                try {
                    $stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
                    $stmt->execute([$user_id]);
                } catch (PDOException $e) {
                    // Wallet creation failed, but continue - wallet can be created later
                    error_log('Wallet creation failed for new user: ' . $e->getMessage());
                }
                
                // Log the action
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO audit_logs (action, actor_id, target_type, target_id, new_value, ip_address, user_agent) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        'user_created',
                        getUserId(),
                        'user',
                        $user_id,
                        json_encode(['username' => $username, 'email' => $email, 'role' => $role]),
                        $_SERVER['REMOTE_ADDR'],
                        $_SERVER['HTTP_USER_AGENT']
                    ]);
                } catch (PDOException $e) {
                    // Audit logging failed but continue
                    error_log('Audit log failed for user creation: ' . $e->getMessage());
                }
                
                $conn->commit();
                
                $success_message = "User '{$username}' has been created successfully with the role '{$role}'.";
                
                // Clear form
                $_POST = [];
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $error_message = 'An error occurred while creating the user. Please try again.';
            error_log('User creation error: ' . $e->getMessage());
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container mt-4" style="padding-top: 40px;">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/index.php'); ?>">Admin</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/users.php'); ?>">Users</a></li>
                    <li class="breadcrumb-item active">Create User</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-user-plus me-2"></i>Create New User</h4>
                    <small class="text-muted">Add a new user account to the system</small>
                </div>
                <div class="card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check me-2"></i><?php echo htmlspecialchars($success_message); ?>
                            <div class="mt-2">
                                <a href="<?php echo url('admin/users.php'); ?>" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-list me-1"></i>View All Users
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="location.reload()">
                                    <i class="fas fa-plus me-1"></i>Create Another
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                       placeholder="Enter username" required>
                                <small class="form-text text-muted">Unique username for login</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       placeholder="user@example.com" required>
                                <small class="form-text text-muted">User's email address</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter password" required>
                                <small class="form-text text-muted">Minimum 6 characters</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm password" required>
                                <small class="form-text text-muted">Must match the password</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">User Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="user" <?php echo ($_POST['role'] ?? 'user') === 'user' ? 'selected' : ''; ?>>
                                        User - Can place bets and participate
                                    </option>
                                    <option value="streamer" <?php echo ($_POST['role'] ?? '') === 'streamer' ? 'selected' : ''; ?>>
                                        Streamer - Can vote on results
                                    </option>
                                    <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>
                                        Admin - Full system access
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Account Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>
                                        Active - Can use the platform
                                    </option>
                                    <option value="pending" <?php echo ($_POST['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>
                                        Pending - Awaiting activation
                                    </option>
                                    <option value="banned" <?php echo ($_POST['status'] ?? '') === 'banned' ? 'selected' : ''; ?>>
                                        Banned - Cannot access platform
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>User Account Information:</h6>
                            <ul class="mb-0">
                                <li><strong>User:</strong> Can place bets, manage wallet, and participate in events</li>
                                <li><strong>Streamer:</strong> All user permissions plus ability to vote on event outcomes</li>
                                <li><strong>Admin:</strong> Full system access including user management and event creation</li>
                                <li>A wallet will be automatically created for the new user with $0.00 balance</li>
                                <li>Users can change their password after logging in</li>
                            </ul>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('admin/users.php'); ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Users
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i>Create User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword.value) {
        confirmPassword.dispatchEvent(new Event('input'));
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>