<?php
$page_title = 'System Settings - Admin Panel';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';

requireRole('admin');

$db = new Database();
$conn = $db->connect();

$error_message = '';
$success_message = '';

// Create settings table if it doesn't exist
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('text', 'number', 'boolean', 'email', 'url', 'textarea', 'select') DEFAULT 'text',
        setting_category VARCHAR(50) DEFAULT 'general',
        setting_description TEXT,
        is_public BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    error_log('Settings table creation error: ' . $e->getMessage());
}

// Default settings configuration
$default_settings = [
    // Platform Settings
    'platform_name' => [
        'value' => 'Bull_PVP',
        'type' => 'text',
        'category' => 'platform',
        'description' => 'Name of the platform displayed throughout the site',
        'public' => true
    ],
    'platform_description' => [
        'value' => 'Bull PVP Platform - Compete in skill-based battles with real stakes',
        'type' => 'textarea',
        'category' => 'platform',
        'description' => 'Description of the platform for SEO and marketing',
        'public' => true
    ],
    'platform_email' => [
        'value' => 'admin@bullpvp.com',
        'type' => 'email',
        'category' => 'platform',
        'description' => 'Main contact email for the platform',
        'public' => true
    ],
    'platform_logo' => [
        'value' => '',
        'type' => 'url',
        'category' => 'platform',
        'description' => 'URL to platform logo image',
        'public' => true
    ],
    
    // User Registration Settings
    'registration_enabled' => [
        'value' => '1',
        'type' => 'boolean',
        'category' => 'users',
        'description' => 'Allow new user registrations',
        'public' => false
    ],
    'email_verification_required' => [
        'value' => '0',
        'type' => 'boolean',
        'category' => 'users',
        'description' => 'Require email verification for new accounts',
        'public' => false
    ],
    'default_user_balance' => [
        'value' => '100.00',
        'type' => 'number',
        'category' => 'users',
        'description' => 'Default wallet balance for new users (in dollars)',
        'public' => false
    ],
    'min_password_length' => [
        'value' => '6',
        'type' => 'number',
        'category' => 'users',
        'description' => 'Minimum password length for user accounts',
        'public' => false
    ],
    
    // Betting Settings
    'min_bet_amount' => [
        'value' => '1.00',
        'type' => 'number',
        'category' => 'betting',
        'description' => 'Minimum bet amount in dollars',
        'public' => true
    ],
    'max_bet_amount' => [
        'value' => '1000.00',
        'type' => 'number',
        'category' => 'betting',
        'description' => 'Maximum bet amount in dollars',
        'public' => true
    ],
    'house_commission' => [
        'value' => '5.00',
        'type' => 'number',
        'category' => 'betting',
        'description' => 'House commission percentage on winning bets',
        'public' => false
    ],
    'betting_enabled' => [
        'value' => '1',
        'type' => 'boolean',
        'category' => 'betting',
        'description' => 'Enable betting functionality platform-wide',
        'public' => false
    ],
    
    // Payment Settings
    'deposit_enabled' => [
        'value' => '1',
        'type' => 'boolean',
        'category' => 'payments',
        'description' => 'Allow users to deposit funds',
        'public' => false
    ],
    'withdrawal_enabled' => [
        'value' => '1',
        'type' => 'boolean',
        'category' => 'payments',
        'description' => 'Allow users to withdraw funds',
        'public' => false
    ],
    'min_deposit_amount' => [
        'value' => '1.00',
        'type' => 'number',
        'category' => 'payments',
        'description' => 'Minimum deposit amount in dollars',
        'public' => false
    ],
    'min_withdrawal_amount' => [
        'value' => '10.00',
        'type' => 'number',
        'category' => 'payments',
        'description' => 'Minimum withdrawal amount in dollars',
        'public' => false
    ],
    'withdrawal_fee' => [
        'value' => '2.00',
        'type' => 'number',
        'category' => 'payments',
        'description' => 'Withdrawal processing fee in dollars',
        'public' => false
    ],
    
    // Security Settings
    'two_factor_required' => [
        'value' => '0',
        'type' => 'boolean',
        'category' => 'security',
        'description' => 'Require 2FA for all user accounts',
        'public' => false
    ],
    'login_attempts_limit' => [
        'value' => '5',
        'type' => 'number',
        'category' => 'security',
        'description' => 'Maximum login attempts before account lockout',
        'public' => false
    ],
    'account_lockout_duration' => [
        'value' => '30',
        'type' => 'number',
        'category' => 'security',
        'description' => 'Account lockout duration in minutes',
        'public' => false
    ],
    'session_timeout' => [
        'value' => '24',
        'type' => 'number',
        'category' => 'security',
        'description' => 'User session timeout in hours',
        'public' => false
    ],
    
    // Event Settings
    'max_events_per_day' => [
        'value' => '10',
        'type' => 'number',
        'category' => 'events',
        'description' => 'Maximum events that can be created per day',
        'public' => false
    ],
    'event_auto_close_hours' => [
        'value' => '24',
        'type' => 'number',
        'category' => 'events',
        'description' => 'Auto-close events after X hours of inactivity',
        'public' => false
    ],
    'voting_duration_minutes' => [
        'value' => '15',
        'type' => 'number',
        'category' => 'events',
        'description' => 'Duration for streamer voting in minutes',
        'public' => false
    ],
    
    // Maintenance Settings
    'maintenance_mode' => [
        'value' => '0',
        'type' => 'boolean',
        'category' => 'maintenance',
        'description' => 'Enable maintenance mode (disable public access)',
        'public' => false
    ],
    'maintenance_message' => [
        'value' => 'We are currently performing maintenance. Please check back later.',
        'type' => 'textarea',
        'category' => 'maintenance',
        'description' => 'Message displayed during maintenance mode',
        'public' => false
    ]
];

// Initialize default settings
try {
    foreach ($default_settings as $key => $setting) {
        $stmt = $conn->prepare("INSERT IGNORE INTO system_settings 
            (setting_key, setting_value, setting_type, setting_category, setting_description, is_public) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $key,
            $setting['value'],
            $setting['type'],
            $setting['category'],
            $setting['description'],
            $setting['public'] ? 1 : 0
        ]);
    }
} catch (PDOException $e) {
    error_log('Settings initialization error: ' . $e->getMessage());
}

// Handle settings update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    try {
        $conn->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            if ($key === 'action') continue;
            
            // Handle boolean values
            if (!isset($_POST[$key]) && isset($default_settings[$key]) && $default_settings[$key]['type'] === 'boolean') {
                $value = '0';
            }
            
            // Validate based on type
            $setting_info = $default_settings[$key] ?? null;
            if ($setting_info) {
                switch ($setting_info['type']) {
                    case 'number':
                        if (!is_numeric($value) || $value < 0) {
                            throw new Exception("Invalid number value for {$key}");
                        }
                        break;
                    case 'email':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception("Invalid email format for {$key}");
                        }
                        break;
                    case 'url':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                            throw new Exception("Invalid URL format for {$key}");
                        }
                        break;
                    case 'boolean':
                        $value = isset($_POST[$key]) ? '1' : '0';
                        break;
                }
            }
            
            // Update setting
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }
        
        $conn->commit();
        
        // Log the action
        try {
            $stmt = $conn->prepare("
                INSERT INTO audit_logs (action, actor_id, target_type, target_id, new_value, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'settings_updated',
                getUserId(),
                'system_settings',
                null,
                json_encode($_POST),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (PDOException $e) {
            // Audit logging failed but continue
            error_log('Audit log failed for settings update: ' . $e->getMessage());
        }
        
        $success_message = 'System settings updated successfully!';
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = $e->getMessage();
        error_log('Settings update error: ' . $e->getMessage());
    }
}

// Get current settings
$current_settings = [];
try {
    $stmt = $conn->query("SELECT setting_key, setting_value, setting_type, setting_category, setting_description FROM system_settings ORDER BY setting_category, setting_key");
    while ($row = $stmt->fetch()) {
        $current_settings[$row['setting_key']] = $row;
    }
} catch (PDOException $e) {
    error_log('Settings retrieval error: ' . $e->getMessage());
    $current_settings = [];
}

// Group settings by category
$settings_by_category = [];
foreach ($current_settings as $key => $setting) {
    $category = $setting['setting_category'];
    if (!isset($settings_by_category[$category])) {
        $settings_by_category[$category] = [];
    }
    $settings_by_category[$category][$key] = $setting;
}

require_once '../includes/header.php';
?>

<div class="container mt-4" style="padding-top: 40px;">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/index.php'); ?>">Admin</a></li>
                    <li class="breadcrumb-item active">System Settings</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <?php require_once 'includes/admin_nav.php'; ?>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-cogs me-2"></i>System Settings</h4>
                    <small class="text-muted">Configure platform behavior and features</small>
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
                    
                    <form method="POST" id="settingsForm">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <!-- Settings Tabs -->
                        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                            <?php 
                            $category_labels = [
                                'platform' => 'Platform',
                                'users' => 'Users',
                                'betting' => 'Betting',
                                'payments' => 'Payments',
                                'security' => 'Security',
                                'events' => 'Events',
                                'maintenance' => 'Maintenance'
                            ];
                            $first_tab = true;
                            foreach ($category_labels as $category => $label): 
                                if (isset($settings_by_category[$category])):
                            ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $first_tab ? 'active' : ''; ?>" 
                                            id="<?php echo $category; ?>-tab" 
                                            data-bs-toggle="tab" 
                                            data-bs-target="#<?php echo $category; ?>" 
                                            type="button" 
                                            role="tab">
                                        <?php echo $label; ?>
                                    </button>
                                </li>
                            <?php 
                                    $first_tab = false;
                                endif;
                            endforeach; 
                            ?>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content mt-3" id="settingsTabContent">
                            <?php 
                            $first_pane = true;
                            foreach ($category_labels as $category => $label): 
                                if (isset($settings_by_category[$category])):
                            ?>
                                <div class="tab-pane fade <?php echo $first_pane ? 'show active' : ''; ?>" 
                                     id="<?php echo $category; ?>" 
                                     role="tabpanel">
                                    <div class="row">
                                        <?php foreach ($settings_by_category[$category] as $key => $setting): ?>
                                            <div class="col-md-6 mb-3">
                                                <label for="<?php echo $key; ?>" class="form-label">
                                                    <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                        <span class="badge bg-info ms-1">Boolean</span>
                                                    <?php elseif ($setting['setting_type'] === 'number'): ?>
                                                        <span class="badge bg-warning ms-1">Number</span>
                                                    <?php elseif ($setting['setting_type'] === 'email'): ?>
                                                        <span class="badge bg-success ms-1">Email</span>
                                                    <?php elseif ($setting['setting_type'] === 'url'): ?>
                                                        <span class="badge bg-primary ms-1">URL</span>
                                                    <?php endif; ?>
                                                </label>
                                                
                                                <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                    <div class="form-check form-switch">
                                                        <input type="checkbox" 
                                                               class="form-check-input" 
                                                               id="<?php echo $key; ?>" 
                                                               name="<?php echo $key; ?>"
                                                               <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="<?php echo $key; ?>">
                                                            <?php echo $setting['setting_value'] == '1' ? 'Enabled' : 'Disabled'; ?>
                                                        </label>
                                                    </div>
                                                <?php elseif ($setting['setting_type'] === 'textarea'): ?>
                                                    <textarea class="form-control" 
                                                              id="<?php echo $key; ?>" 
                                                              name="<?php echo $key; ?>" 
                                                              rows="3"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                                <?php else: ?>
                                                    <input type="<?php echo $setting['setting_type'] === 'number' ? 'number' : ($setting['setting_type'] === 'email' ? 'email' : ($setting['setting_type'] === 'url' ? 'url' : 'text')); ?>" 
                                                           class="form-control" 
                                                           id="<?php echo $key; ?>" 
                                                           name="<?php echo $key; ?>" 
                                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                           <?php if ($setting['setting_type'] === 'number'): ?>step="0.01" min="0"<?php endif; ?>>
                                                <?php endif; ?>
                                                
                                                <?php if ($setting['setting_description']): ?>
                                                    <small class="form-text text-muted">
                                                        <?php echo htmlspecialchars($setting['setting_description']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php 
                                    $first_pane = false;
                                endif;
                            endforeach; 
                            ?>
                        </div>
                        
                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary" id="saveSettingsBtn">
                                <i class="fas fa-save me-2"></i>Save All Settings
                            </button>
                            <button type="button" class="btn btn-outline-secondary ms-2" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset Changes
                            </button>
                            <button type="button" class="btn btn-outline-info ms-2" onclick="exportSettings()">
                                <i class="fas fa-download me-2"></i>Export Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Information Card -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>System Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="stat-item">
                                <h6>PHP Version</h6>
                                <p class="text-primary"><?php echo phpversion(); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-item">
                                <h6>Server Software</h6>
                                <p class="text-info"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-item">
                                <h6>Database</h6>
                                <p class="text-success">
                                    <?php 
                                    try {
                                        $version = $conn->getAttribute(PDO::ATTR_SERVER_VERSION);
                                        echo 'MySQL ' . $version;
                                    } catch (Exception $e) {
                                        echo 'Unknown';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-item">
                                <h6>Platform Version</h6>
                                <p class="text-warning">v1.0.0</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form handling
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('saveSettingsBtn');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
    
    // Re-enable button after form submission
    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }, 3000);
});

// Reset form function
function resetForm() {
    if (confirm('Are you sure you want to reset all changes? This will reload the page and lose any unsaved changes.')) {
        location.reload();
    }
}

// Export settings function
function exportSettings() {
    const form = document.getElementById('settingsForm');
    const formData = new FormData(form);
    const settings = {};
    
    for (let [key, value] of formData.entries()) {
        if (key !== 'action') {
            settings[key] = value;
        }
    }
    
    const dataStr = JSON.stringify(settings, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'bull_pvp_settings_' + new Date().toISOString().split('T')[0] + '.json';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

// Switch label updates
document.querySelectorAll('.form-check-input[type="checkbox"]').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        const label = this.nextElementSibling;
        label.textContent = this.checked ? 'Enabled' : 'Disabled';
    });
});

// Auto-hide success/error messages
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('alert-success') || alert.classList.contains('alert-danger')) {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    });
}, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>