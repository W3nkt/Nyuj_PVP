<?php
// Bull PVP Platform Setup Script
// This script initializes the database and creates sample data

require_once 'config/database.php';

$setup_complete = false;
$error_message = '';
$success_message = '';

if ($_POST) {
    $admin_username = trim($_POST['admin_username'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    
    if (empty($admin_username) || empty($admin_email) || empty($admin_password)) {
        $error_message = 'Please fill in all fields.';
    } else {
        try {
            $db = new Database();
            $conn = $db->connect();
            
            // Read and execute database schema
            $sql = file_get_contents('database_schema_fixed.sql');
            $statements = explode(';', $sql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $conn->exec($statement);
                }
            }
            
            // Create admin user
            $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO users (username, email, password_hash, role, kyc_tier, status) 
                VALUES (?, ?, ?, 'admin', 2, 'active')
            ");
            $stmt->execute([$admin_username, $admin_email, $password_hash]);
            $admin_id = $conn->lastInsertId();
            
            // Create admin wallet
            $stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 1000.00)");
            $stmt->execute([$admin_id]);
            
            // Create sample streamer accounts
            $streamers = [
                ['streamer1', 'streamer1@example.com'],
                ['streamer2', 'streamer2@example.com'],
                ['streamer3', 'streamer3@example.com'],
                ['streamer4', 'streamer4@example.com']
            ];
            
            foreach ($streamers as $streamer) {
                $stmt = $conn->prepare("
                    INSERT INTO users (username, email, password_hash, role, kyc_tier, status) 
                    VALUES (?, ?, ?, 'streamer', 1, 'active')
                ");
                $stmt->execute([$streamer[0], $streamer[1], password_hash('password123', PASSWORD_DEFAULT)]);
                $streamer_id = $conn->lastInsertId();
                
                // Create streamer wallet
                $stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
                $stmt->execute([$streamer_id]);
                
                // Create streamer reputation record
                $stmt = $conn->prepare("INSERT INTO streamer_reputation (streamer_id) VALUES (?)");
                $stmt->execute([$streamer_id]);
            }
            
            // Create sample regular users
            $users = [
                ['testuser1', 'user1@example.com'],
                ['testuser2', 'user2@example.com'],
                ['testuser3', 'user3@example.com']
            ];
            
            foreach ($users as $user) {
                $stmt = $conn->prepare("
                    INSERT INTO users (username, email, password_hash, role, kyc_tier, status) 
                    VALUES (?, ?, ?, 'user', 0, 'active')
                ");
                $stmt->execute([$user[0], $user[1], password_hash('password123', PASSWORD_DEFAULT)]);
                $user_id = $conn->lastInsertId();
                
                // Create user wallet with some balance
                $stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 50.00)");
                $stmt->execute([$user_id]);
            }
            
            $success_message = 'Setup completed successfully! You can now login with your admin credentials.';
            $setup_complete = true;
            
        } catch (Exception $e) {
            $error_message = 'Setup failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Bull PVP Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header text-center">
                        <h2><i class="fas fa-cogs me-2"></i>Bull PVP Platform Setup</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!$setup_complete): ?>
                            <?php if ($error_message): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle me-2"></i>Setup Instructions</h5>
                                <p>This setup will:</p>
                                <ul>
                                    <li>Create the database tables and schema</li>
                                    <li>Set up your admin account</li>
                                    <li>Create sample streamer and user accounts for testing</li>
                                    <li>Initialize system settings</li>
                                </ul>
                                <p class="mb-0"><strong>Note:</strong> Make sure your database connection is configured in <code>config/database.php</code></p>
                            </div>
                            
                            <form method="POST">
                                <h5 class="mb-3">Admin Account Setup</h5>
                                
                                <div class="mb-3">
                                    <label for="admin_username" class="form-label">Admin Username</label>
                                    <input type="text" class="form-control" id="admin_username" name="admin_username" 
                                           value="<?php echo htmlspecialchars($_POST['admin_username'] ?? 'admin'); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="admin_email" class="form-label">Admin Email</label>
                                    <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                           value="<?php echo htmlspecialchars($_POST['admin_email'] ?? 'admin@example.com'); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="admin_password" class="form-label">Admin Password</label>
                                    <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                                    <small class="form-text text-muted">Minimum 8 characters recommended</small>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Sample Accounts</h6>
                                    <p>The following test accounts will be created:</p>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Streamers:</strong>
                                            <ul class="small">
                                                <li>streamer1@example.com</li>
                                                <li>streamer2@example.com</li>
                                                <li>streamer3@example.com</li>
                                                <li>streamer4@example.com</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Users:</strong>
                                            <ul class="small">
                                                <li>user1@example.com</li>
                                                <li>user2@example.com</li>
                                                <li>user3@example.com</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <p class="mb-0"><small>All test accounts use password: <code>password123</code></small></p>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-play me-2"></i>Run Setup
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check me-2"></i><?php echo htmlspecialchars($success_message); ?>
                            </div>
                            
                            <div class="alert alert-info">
                                <h5><i class="fas fa-rocket me-2"></i>Setup Complete!</h5>
                                <p>Your Bull PVP Platform is now ready to use. Here's what you can do next:</p>
                                <ol>
                                    <li><strong>Login as Admin:</strong> Use your admin credentials to access the admin panel</li>
                                    <li><strong>Create Events:</strong> Set up competitions and assign streamers</li>
                                    <li><strong>Test the Platform:</strong> Use the sample accounts to test functionality</li>
                                    <li><strong>Configure Settings:</strong> Adjust platform fees and other settings</li>
                                </ol>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="index.php" class="btn btn-primary">
                                    <i class="fas fa-home me-2"></i>Go to Platform
                                </a>
                                <a href="auth/login.php" class="btn btn-outline-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Admin Login
                                </a>
                            </div>
                            
                            <div class="mt-4 p-3 bg-light border-start border-warning border-5">
                                <h6><i class="fas fa-security me-2"></i>Security Notice</h6>
                                <p class="mb-0">For production use, make sure to:</p>
                                <ul class="mb-0">
                                    <li>Delete or disable the sample accounts</li>
                                    <li>Change default passwords</li>
                                    <li>Configure proper database credentials</li>
                                    <li>Set up SSL/HTTPS</li>
                                    <li>Delete this setup.php file</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>