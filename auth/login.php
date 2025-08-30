<?php
$page_title = 'Login - Bull PVP Platform';

// Add error handling for missing files
if (!file_exists('../config/database.php') || !file_exists('../config/session.php')) {
    die('Configuration error: Required files not found. Please check your installation.');
}

require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';
require_once '../config/AuditChain.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . url('user/dashboard.php'));
    exit();
}

$error_message = '';
$success_message = '';

if ($_POST) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } else {
        $db = new Database();
        $conn = $db->connect();
        
        try {
            $stmt = $conn->prepare("SELECT id, username, email, password_hash, role, status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'active') {
                    $error_message = 'Your account has been suspended. Please contact support.';
                } else {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Log the login in audit chain
                    $auditChain = new AuditChain();
                    $auditChain->logUserActivity($user['id'], 'user_login', [
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                        'login_method' => 'web_form'
                    ]);
                    
                    // Redirect based on role
                    $redirect = url('user/dashboard.php');
                    if ($user['role'] === 'admin') {
                        $redirect = url('admin/index.php');
                    } elseif ($user['role'] === 'streamer') {
                        $redirect = url('streamer/voting.php');
                    }
                    
                    header('Location: ' . $redirect);
                    exit();
                }
            } else {
                $error_message = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error_message = 'An error occurred. Please try again.';
            error_log('Login error: ' . $e->getMessage());
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card">
                <div class="card-header text-center">
                    <h4><i class="fas fa-sign-in-alt me-2"></i>Login</h4>
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
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="#" class="text-muted">Forgot password?</a>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <small>
                        Don't have an account? 
                        <a href="/Bull_PVP/auth/register.php">Register here</a>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>