<?php
$page_title = 'Register - Bull PVP Platform';
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
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (strlen($username) < 3) {
        $error_message = 'Username must be at least 3 characters long.';
    } else {
        $db = new Database();
        $conn = $db->connect();
        
        try {
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error_message = 'Username or email already exists.';
            } else {
                // Create user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $conn->beginTransaction();
                
                $stmt = $conn->prepare("
                    INSERT INTO users (username, email, password_hash, first_name, last_name, phone, date_of_birth, kyc_tier) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $username, 
                    $email, 
                    $password_hash, 
                    $first_name, 
                    $last_name, 
                    $phone, 
                    $date_of_birth ?: null,
                    0 // Start with KYC tier 0
                ]);
                
                $user_id = $conn->lastInsertId();
                
                // Create wallet for user (or use audit chain balance system)
                $stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
                $stmt->execute([$user_id]);
                
                // Log the registration in audit chain
                $auditChain = new AuditChain();
                $auditChain->logUserActivity($user_id, 'user_register', [
                    'username' => $username,
                    'email' => $email,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    'registration_method' => 'web_form'
                ]);
                
                $conn->commit();
                
                $success_message = 'Registration successful! You can now login.';
                
                // Clear form data
                $_POST = [];
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $error_message = 'An error occurred during registration. Please try again.';
            error_log('Registration error: ' . $e->getMessage());
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-header text-center">
                    <h4><i class="fas fa-user-plus me-2"></i>Create Account</h4>
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
                                <a href="/Bull_PVP/auth/login.php" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-sign-in-alt me-1"></i>Login Now
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                            <small class="form-text text-muted">Minimum 3 characters, alphanumeric only</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            <small class="form-text text-muted">Optional - helps with KYC verification</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                   value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                            <small class="form-text text-muted">Required for higher stake limits</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="form-text text-muted">Minimum 8 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="age_confirm" required>
                            <label class="form-check-label" for="age_confirm">
                                I confirm that I am at least 18 years old
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <small>
                        Already have an account? 
                        <a href="/Bull_PVP/auth/login.php">Login here</a>
                    </small>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-body">
                    <h6><i class="fas fa-shield-alt me-2"></i>KYC Verification Levels</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Tier 0 (Email Only)</strong><br>
                            <small class="text-muted">Max stake: $10</small>
                        </div>
                        <div class="col-md-4">
                            <strong>Tier 1 (Phone Verified)</strong><br>
                            <small class="text-muted">Max stake: $100</small>
                        </div>
                        <div class="col-md-4">
                            <strong>Tier 2 (ID Verified)</strong><br>
                            <small class="text-muted">Max stake: $1,000</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>