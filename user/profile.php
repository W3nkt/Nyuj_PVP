<?php
$page_title = 'My Profile - Bull PVP Platform';
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';

requireLogin();

$db = new Database();
$conn = $db->connect();
$user_id = getUserId();

$error_message = '';
$success_message = '';

// First, let's ensure the required columns exist
try {
    $stmt = $conn->query("DESCRIBE users");
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $required_columns = [
        'first_name' => "ALTER TABLE users ADD COLUMN first_name VARCHAR(50) NULL",
        'last_name' => "ALTER TABLE users ADD COLUMN last_name VARCHAR(50) NULL", 
        'phone' => "ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL",
        'profile_picture' => "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL",
        'bio' => "ALTER TABLE users ADD COLUMN bio TEXT NULL",
        'two_factor_enabled' => "ALTER TABLE users ADD COLUMN two_factor_enabled BOOLEAN DEFAULT FALSE",
        'two_factor_secret' => "ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(32) NULL",
        'backup_codes' => "ALTER TABLE users ADD COLUMN backup_codes TEXT NULL",
        'updated_at' => "ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];
    
    foreach ($required_columns as $column_name => $sql) {
        if (!in_array($column_name, $existing_columns)) {
            try {
                $conn->exec($sql);
            } catch (PDOException $e) {
                // Column might already exist or have permission issues
                error_log("Column creation error for $column_name: " . $e->getMessage());
            }
        }
    }
} catch (Exception $e) {
    error_log("Database structure check error: " . $e->getMessage());
}

// Handle profile update
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        // Handle profile picture upload
        $profile_picture = null;
        $upload_error = '';
        
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                // Create upload directory if it doesn't exist
                $upload_dir = '../assets/uploads/profile_pictures/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = $_FILES['profile_picture']['type'];
                
                if (!in_array($file_type, $allowed_types)) {
                    $upload_error = 'Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.';
                } elseif ($_FILES['profile_picture']['size'] > 2 * 1024 * 1024) { // 2MB limit
                    $upload_error = 'Image too large. Maximum size is 2MB.';
                } else {
                    // Generate unique filename
                    $extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                    $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
                    $upload_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                        $profile_picture = 'assets/uploads/profile_pictures/' . $filename;
                        
                        // Delete old profile picture if it exists
                        try {
                            $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                            $stmt->execute([$user_id]);
                            $current_user = $stmt->fetch();
                            
                            if ($current_user && $current_user['profile_picture']) {
                                $old_file = '../' . $current_user['profile_picture'];
                                if (file_exists($old_file)) {
                                    unlink($old_file);
                                }
                            }
                        } catch (PDOException $e) {
                            // Column might not exist yet
                        }
                    } else {
                        $upload_error = 'Failed to upload profile picture.';
                    }
                }
            } else {
                $upload_error = 'Profile picture upload error.';
            }
        }
        
        // Validation
        if (!empty($upload_error)) {
            $error_message = $upload_error;
        } elseif (empty($username) || empty($email)) {
            $error_message = 'Username and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address.';
        } elseif ($phone && !preg_match('/^\+856[-\s]?[2][0-9][-\s]?[0-9]{8}$/', $phone)) {
            $error_message = 'Please enter a valid Lao phone number in format: +856-20-29862982';
        } else {
            try {
                // Check if username or email is taken by another user
                $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $user_id]);
                
                if ($stmt->fetch()) {
                    $error_message = 'Username or email is already taken by another user.';
                } else {
                    // Update profile with error handling for missing columns
                    try {
                        // Build update query dynamically based on available columns
                        $stmt = $conn->query("DESCRIBE users");
                        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                        
                        $updateFields = ['username = ?', 'email = ?'];
                        $params = [$username, $email];
                        
                        if (in_array('first_name', $columns)) {
                            $updateFields[] = 'first_name = ?';
                            $params[] = $first_name;
                        }
                        if (in_array('last_name', $columns)) {
                            $updateFields[] = 'last_name = ?';
                            $params[] = $last_name;
                        }
                        if (in_array('phone', $columns)) {
                            $updateFields[] = 'phone = ?';
                            $params[] = $phone;
                        }
                        if (in_array('bio', $columns)) {
                            $updateFields[] = 'bio = ?';
                            $params[] = $bio;
                        }
                        if ($profile_picture && in_array('profile_picture', $columns)) {
                            $updateFields[] = 'profile_picture = ?';
                            $params[] = $profile_picture;
                        }
                        if (in_array('updated_at', $columns)) {
                            $updateFields[] = 'updated_at = NOW()';
                        }
                        
                        $params[] = $user_id;
                        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                        
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        
                    } catch (PDOException $e) {
                        error_log('Profile update error: ' . $e->getMessage());
                        // Try basic update
                        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                        $stmt->execute([$username, $email, $user_id]);
                    }
                    
                    // Update session username
                    $_SESSION['username'] = $username;
                    
                    $success_message = 'Profile updated successfully!';
                }
            } catch (PDOException $e) {
                $error_message = 'An error occurred while updating your profile. Please try again.';
                error_log('Profile update error: ' . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'All password fields are required.';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'New password must be at least 6 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New passwords do not match.';
        } else {
            try {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_check = $stmt->fetch();
                
                if (!$user_check || !password_verify($current_password, $user_check['password'])) {
                    $error_message = 'Current password is incorrect.';
                } else {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    try {
                        $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$hashed_password, $user_id]);
                    } catch (PDOException $e) {
                        // Try without updated_at if column doesn't exist
                        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $user_id]);
                    }
                    
                    $success_message = 'Password changed successfully!';
                }
            } catch (PDOException $e) {
                $error_message = 'An error occurred while changing your password. Please try again.';
                error_log('Password change error: ' . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'enable_2fa') {
        try {
            // Generate a new secret for 2FA
            $secret = '';
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
            for ($i = 0; $i < 32; $i++) {
                // Use mt_rand as fallback for better compatibility
                $secret .= $chars[function_exists('random_int') ? random_int(0, 31) : mt_rand(0, 31)];
            }
            
            // Generate backup codes
            $backup_codes = [];
            for ($i = 0; $i < 10; $i++) {
                $backup_codes[] = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            }
            $backup_codes_json = json_encode($backup_codes);
            
            // Update database
            try {
                $stmt = $conn->prepare("UPDATE users SET two_factor_secret = ?, backup_codes = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$secret, $backup_codes_json, $user_id]);
            } catch (PDOException $e) {
                // Try without updated_at if column doesn't exist
                $stmt = $conn->prepare("UPDATE users SET two_factor_secret = ?, backup_codes = ? WHERE id = ?");
                $stmt->execute([$secret, $backup_codes_json, $user_id]);
            }
            
            $_SESSION['2fa_setup_secret'] = $secret;
            $_SESSION['2fa_setup_codes'] = $backup_codes;
            $success_message = '2FA setup initiated. Please scan the QR code with your authenticator app.';
            
        } catch (Exception $e) {
            $error_message = 'An error occurred while setting up 2FA. Please try again.';
            error_log('2FA setup error: ' . $e->getMessage());
        }
    } elseif ($_POST['action'] === 'verify_2fa') {
        $verification_code = $_POST['verification_code'] ?? '';
        $secret = $_SESSION['2fa_setup_secret'] ?? '';
        
        if (empty($verification_code)) {
            $error_message = 'Please enter the verification code from your authenticator app.';
        } elseif (empty($secret)) {
            $error_message = '2FA setup session expired. Please start setup again.';
        } else {
            // Simple TOTP verification (basic implementation)
            $time = floor(time() / 30);
            $valid_codes = [];
            
            // Check current and previous/next time windows
            for ($i = -1; $i <= 1; $i++) {
                try {
                    $timeCounter = pack('N*', 0) . pack('N*', $time + $i);
                    $decoded_secret = base32_decode($secret);
                    if ($decoded_secret === false || empty($decoded_secret)) {
                        continue; // Skip if decoding fails
                    }
                    
                    $hash = hash_hmac('sha1', $timeCounter, $decoded_secret, true);
                    if (strlen($hash) < 20) {
                        continue; // Skip if hash is too short
                    }
                    
                    $offset = ord($hash[19]) & 0xf;
                    $code = (
                        ((ord($hash[$offset+0]) & 0x7f) << 24) |
                        ((ord($hash[$offset+1]) & 0xff) << 16) |
                        ((ord($hash[$offset+2]) & 0xff) << 8) |
                        (ord($hash[$offset+3]) & 0xff)
                    ) % 1000000;
                    $valid_codes[] = sprintf('%06d', $code);
                } catch (Exception $e) {
                    // Skip this time window if there's an error
                    error_log('TOTP generation error: ' . $e->getMessage());
                    continue;
                }
            }
            
            if (in_array($verification_code, $valid_codes)) {
                // Enable 2FA
                try {
                    $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = TRUE, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$user_id]);
                } catch (PDOException $e) {
                    $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = TRUE WHERE id = ?");
                    $stmt->execute([$user_id]);
                }
                
                unset($_SESSION['2fa_setup_secret']);
                $backup_codes = $_SESSION['2fa_setup_codes'] ?? [];
                unset($_SESSION['2fa_setup_codes']);
                
                $success_message = '2FA enabled successfully! Please save your backup codes in a secure location.';
                $_SESSION['2fa_backup_codes_display'] = $backup_codes;
            } else {
                $error_message = 'Invalid verification code. Please try again.';
            }
        }
    } elseif ($_POST['action'] === 'disable_2fa') {
        $password = $_POST['disable_2fa_password'] ?? '';
        
        if (empty($password)) {
            $error_message = 'Please enter your password to disable 2FA.';
        } else {
            try {
                // Verify password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_check = $stmt->fetch();
                
                if (!$user_check || !password_verify($password, $user_check['password'])) {
                    $error_message = 'Incorrect password.';
                } else {
                    // Disable 2FA
                    try {
                        $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = FALSE, two_factor_secret = NULL, backup_codes = NULL, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$user_id]);
                    } catch (PDOException $e) {
                        $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = FALSE, two_factor_secret = NULL, backup_codes = NULL WHERE id = ?");
                        $stmt->execute([$user_id]);
                    }
                    
                    $success_message = '2FA has been disabled successfully.';
                }
            } catch (Exception $e) {
                $error_message = 'An error occurred while disabling 2FA. Please try again.';
                error_log('2FA disable error: ' . $e->getMessage());
            }
        }
    }
}

// Base32 decode function for TOTP
function base32_decode($data) {
    if (empty($data)) {
        return false;
    }
    
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0, $j = strlen($data); $i < $j; $i++) {
        $char_pos = strpos($alphabet, strtoupper($data[$i]));
        if ($char_pos === false) {
            return false; // Invalid character
        }
        
        $v <<= 5;
        $v += $char_pos;
        $vbits += 5;
        
        if ($vbits >= 8) {
            $output .= chr($v >> ($vbits - 8));
            $vbits -= 8;
        }
    }
    
    return $output;
}

// Get user profile information with simple fallback
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Set default values for missing columns
        $user['wallet_balance'] = 0;
        $user['total_bets'] = 0;
        $user['matched_bets'] = 0;
        $user['total_bet_amount'] = 0;
        $user['events_created'] = 0;
        $user['first_name'] = $user['first_name'] ?? '';
        $user['last_name'] = $user['last_name'] ?? '';
        $user['phone'] = $user['phone'] ?? '';
        $user['bio'] = $user['bio'] ?? '';
        $user['profile_picture'] = $user['profile_picture'] ?? '';
        $user['two_factor_enabled'] = $user['two_factor_enabled'] ?? false;
        $user['two_factor_secret'] = $user['two_factor_secret'] ?? '';
        $user['backup_codes'] = $user['backup_codes'] ?? '';
    }
} catch (PDOException $e) {
    error_log('Profile query error: ' . $e->getMessage());
    header('Location: ' . url('auth/logout.php'));
    exit();
}

if (!$user) {
    header('Location: ' . url('auth/logout.php'));
    exit();
}

require_once '../includes/header.php';
?>

<div class="container mt-4" style="padding-top: 40px;">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo url('user/dashboard.php'); ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">My Profile</li>
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
    
    <div class="row">
        <!-- Profile Overview -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="profile-avatar mb-3">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <img src="<?php echo url($user['profile_picture']); ?>" 
                                 alt="Profile Picture" 
                                 class="rounded-circle profile-picture"
                                 style="width: 150px; height: 150px; object-fit: cover; border: 4px solid var(--primary-color);">
                        <?php else: ?>
                            <i class="fas fa-user-circle fa-5x text-primary"></i>
                        <?php endif; ?>
                    </div>
                    <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <?php if ($user['first_name'] || $user['last_name']): ?>
                        <p class="mb-2">
                            <strong><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?></strong>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($user['phone']): ?>
                        <p class="mb-2 text-muted">
                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($user['phone']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'streamer' ? 'warning' : 'primary'); ?> fs-6">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                        </small>
                    </div>
                    
                    <?php if ($user['bio']): ?>
                        <div class="mt-3">
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Profile Settings -->
        <div class="col-md-8">
            <!-- Edit Profile -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-user-edit me-2"></i>Edit Profile</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="profileForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <!-- Profile Picture Upload -->
                        <div class="mb-4">
                            <label for="profile_picture" class="form-label">Profile Picture</label>
                            <div class="d-flex align-items-center">
                                <div class="profile-preview me-3">
                                    <?php if (!empty($user['profile_picture'])): ?>
                                        <img src="<?php echo url($user['profile_picture']); ?>" 
                                             alt="Current Profile Picture" 
                                             class="rounded-circle" 
                                             style="width: 80px; height: 80px; object-fit: cover; border: 2px solid var(--primary-color);"
                                             id="currentProfilePicture">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle fa-4x text-muted" id="currentProfilePicture"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                                           accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewProfilePicture(this)">
                                    <small class="form-text text-muted">Optional. JPEG, PNG, GIF, or WebP. Max 2MB.</small>
                                </div>
                            </div>
                            <div id="profilePicturePreview" class="mt-2"></div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                   placeholder="+856-20-29862982" 
                                   pattern="^\+856[-\s]?[2][0-9][-\s]?[0-9]{8}$">
                            <small class="form-text text-muted">Optional. Lao phone number format: +856-20-29862982</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3" 
                                      placeholder="Tell others about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">Brief description about yourself (optional)</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-2" onclick="resetProfileForm()">
                            <i class="fas fa-undo me-2"></i>Reset
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-lock me-2"></i>Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       minlength="6" required>
                                <small class="form-text text-muted">Minimum 6 characters</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       minlength="6" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Two-Factor Authentication -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5><i class="fas fa-shield-alt me-2"></i>Two-Factor Authentication</h5>
                        <small class="text-muted">Add an extra layer of security to your account</small>
                    </div>
                    <span class="badge bg-<?php echo $user['two_factor_enabled'] ? 'success' : 'secondary'; ?> fs-6">
                        <?php echo $user['two_factor_enabled'] ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if (!$user['two_factor_enabled']): ?>
                        <?php if (!isset($_SESSION['2fa_setup_secret'])): ?>
                            <!-- Setup 2FA -->
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Enhance your account security</strong><br>
                                Two-factor authentication adds an extra layer of protection by requiring a code from your mobile device.
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="enable_2fa">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-shield-alt me-2"></i>Enable Two-Factor Authentication
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- Complete 2FA Setup -->
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Complete your 2FA setup</strong><br>
                                Scan the QR code below with your authenticator app, then enter the 6-digit code to complete setup.
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Scan QR Code:</h6>
                                    <div class="text-center mb-3">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=otpauth://totp/Bull_PVP%3A<?php echo urlencode($user['username']); ?>%3Fsecret%3D<?php echo $_SESSION['2fa_setup_secret']; ?>%26issuer%3DBull_PVP" 
                                             alt="2FA QR Code" class="img-fluid">
                                    </div>
                                    <p><small class="text-muted">Or manually enter this secret: <code><?php echo $_SESSION['2fa_setup_secret']; ?></code></small></p>
                                </div>
                                <div class="col-md-6">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="verify_2fa">
                                        <div class="mb-3">
                                            <label for="verification_code" class="form-label">Verification Code</label>
                                            <input type="text" class="form-control" id="verification_code" name="verification_code" 
                                                   placeholder="000000" pattern="[0-9]{6}" maxlength="6" required>
                                            <small class="form-text text-muted">Enter the 6-digit code from your authenticator app</small>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-check me-2"></i>Verify & Enable 2FA
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- 2FA is enabled -->
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Two-factor authentication is enabled</strong><br>
                            Your account is protected with an additional security layer.
                        </div>
                        
                        <!-- Disable 2FA -->
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#disable2faForm">
                                <i class="fas fa-times me-2"></i>Disable Two-Factor Authentication
                            </button>
                        </div>
                        
                        <div class="collapse mt-3" id="disable2faForm">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Warning:</strong> Disabling 2FA will make your account less secure.
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="disable_2fa">
                                        <div class="mb-3">
                                            <label for="disable_2fa_password" class="form-label">Enter your password to confirm</label>
                                            <input type="password" class="form-control" id="disable_2fa_password" 
                                                   name="disable_2fa_password" required>
                                        </div>
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-times me-2"></i>Disable 2FA
                                        </button>
                                        <button type="button" class="btn btn-secondary ms-2" data-bs-toggle="collapse" data-bs-target="#disable2faForm">
                                            Cancel
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Backup Codes Modal -->
    <?php if (isset($_SESSION['2fa_backup_codes_display'])): ?>
        <div class="modal fade" id="backupCodesModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-key me-2"></i>Your Backup Codes</h5>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Save these backup codes in a secure location. You can use them to access your account if you lose your authenticator device.
                        </div>
                        
                        <div class="row">
                            <?php foreach ($_SESSION['2fa_backup_codes_display'] as $code): ?>
                                <div class="col-6 mb-2">
                                    <code class="fs-6"><?php echo $code; ?></code>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <small><i class="fas fa-info-circle me-1"></i>Each backup code can only be used once.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" onclick="downloadBackupCodes()">
                            <i class="fas fa-download me-2"></i>Download Codes
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="confirmBackupCodesSaved()">
                            <i class="fas fa-check me-2"></i>I've Saved These Codes
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['2fa_backup_codes_display']); ?>
    <?php endif; ?>
</div>

<script>
// Profile picture preview function
function previewProfilePicture(input) {
    const preview = document.getElementById('profilePicturePreview');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        const fileType = file.type.toLowerCase();
        
        if (!allowedTypes.includes(fileType)) {
            alert('Please select a valid image file (JPEG, PNG, GIF, or WebP)');
            input.value = '';
            preview.innerHTML = '';
            return;
        }
        
        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('Image too large. Maximum size is 2MB.');
            input.value = '';
            preview.innerHTML = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div class="mt-2">
                    <label class="form-label">Preview:</label>
                    <br>
                    <img src="${e.target.result}" 
                         alt="Preview" 
                         class="rounded-circle"
                         style="width: 80px; height: 80px; object-fit: cover; border: 2px solid var(--primary-color);">
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="clearProfilePicturePreview()">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
}

function clearProfilePicturePreview() {
    document.getElementById('profile_picture').value = '';
    document.getElementById('profilePicturePreview').innerHTML = '';
}

function resetProfileForm() {
    document.getElementById('profileForm').reset();
    document.getElementById('profilePicturePreview').innerHTML = '';
}

// Phone number validation for Lao format
document.getElementById('phone').addEventListener('input', function() {
    const phone = this.value.trim();
    const laoPhonePattern = /^\+856[-\s]?[2][0-9][-\s]?[0-9]{8}$/;
    
    if (phone && !laoPhonePattern.test(phone)) {
        this.setCustomValidity('Please enter a valid Lao phone number in format: +856-20-29862982');
    } else {
        this.setCustomValidity('');
    }
});

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword.value) {
        confirmPassword.dispatchEvent(new Event('input'));
    }
});

// Password form handling
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Changing...';
    
    // Re-enable button after form submission
    setTimeout(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }, 3000);
});

// Verification code formatting
const verificationInput = document.getElementById('verification_code');
if (verificationInput) {
    verificationInput.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').substring(0, 6);
    });
}

// Backup codes modal handling
<?php if (isset($_SESSION['2fa_backup_codes_display'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('backupCodesModal'));
    modal.show();
});
<?php endif; ?>

// Download backup codes function
function downloadBackupCodes() {
    const codes = [
        <?php if (isset($_SESSION['2fa_backup_codes_display'])): ?>
            <?php foreach ($_SESSION['2fa_backup_codes_display'] as $code): ?>
                '<?php echo $code; ?>',
            <?php endforeach; ?>
        <?php endif; ?>
    ];
    
    const content = 'Bull_PVP Two-Factor Authentication Backup Codes\n' +
                    'Generated: ' + new Date().toLocaleString() + '\n\n' +
                    'IMPORTANT: Keep these codes secure and private.\n' +
                    'Each code can only be used once.\n\n' +
                    codes.join('\n');
    
    const blob = new Blob([content], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'bull_pvp_backup_codes.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function confirmBackupCodesSaved() {
    if (confirm('Have you saved these backup codes in a secure location? You will not be able to see them again.')) {
        bootstrap.Modal.getInstance(document.getElementById('backupCodesModal')).hide();
    }
}

// Clear password form after successful submission
<?php if ($success_message && strpos($success_message, 'Password') !== false): ?>
document.getElementById('passwordForm').reset();
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>