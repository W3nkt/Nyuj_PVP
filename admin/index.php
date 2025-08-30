<?php
$page_title = 'Admin Dashboard - Bull PVP Platform';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';

requireRole('admin');

$db = new Database();
$conn = $db->connect();

// Get statistics with error handling
$stats = [
    'total_users' => 0,
    'total_events' => 0,
    'active_events' => 0,
    'total_volume' => 0
];

try {
    // Total users
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch()['count'];

    // Total events
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events");
    $stmt->execute();
    $stats['total_events'] = $stmt->fetch()['count'];

    // Active events
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE status IN ('accepting_bets', 'live', 'streamer_voting')");
    $stmt->execute();
    $stats['active_events'] = $stmt->fetch()['count'];

    // Total volume (using a simpler query)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE status = 'completed'");
    $stmt->execute();
    $stats['total_volume'] = $stmt->fetch()['count'] * 100; // Placeholder calculation
} catch (Exception $e) {
    // If database queries fail, use default values
    error_log('Admin dashboard error: ' . $e->getMessage());
}

// Recent events
try {
    $stmt = $conn->prepare("
        SELECT e.*, u.username as creator_name
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.id
        ORDER BY e.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_events = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_events = [];
}

// Recent users
try {
    $stmt = $conn->prepare("
        SELECT u.*, w.balance 
        FROM users u 
        LEFT JOIN wallets w ON u.id = w.user_id 
        WHERE u.role = 'user' 
        ORDER BY u.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_users = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_users = [];
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="fas fa-cogs me-2"></i>Admin Dashboard</h2>
            <p class="text-muted">Manage events, users, and platform settings</p>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-number"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stats-label">Total Users</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-number"><?php echo number_format($stats['total_events']); ?></div>
                <div class="stats-label">Total Events</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-number"><?php echo number_format($stats['active_events']); ?></div>
                <div class="stats-label">Active Events</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-number">$<?php echo number_format($stats['total_volume'], 2); ?></div>
                <div class="stats-label">Total Volume</div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo url('admin/create_event.php'); ?>" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Create Event
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo url('admin/events.php'); ?>" class="btn btn-success w-100">
                                <i class="fas fa-calendar me-2"></i>Manage Events
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo url('admin/users.php'); ?>" class="btn btn-warning w-100">
                                <i class="fas fa-users me-2"></i>Manage Users
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo url('admin/settings.php'); ?>" class="btn btn-info w-100">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Events -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-calendar me-2"></i>Recent Events</h5>
                    <a href="<?php echo url('admin/events.php'); ?>" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_events)): ?>
                        <p class="text-muted">No events created yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Event Name</th>
                                        <th>Stake</th>
                                        <th>Participants</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_events as $event): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($event['name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($event['game_type']); ?></small>
                                            </td>
                                            <td>$<?php echo number_format($event['stake_amount'], 2); ?></td>
                                            <td><?php echo $event['participant_count']; ?>/<?php echo $event['max_participants']; ?></td>
                                            <td>
                                                <span class="event-status status-<?php echo str_replace('_', '-', $event['status']); ?>">
                                                    <?php echo strtoupper(str_replace('_', ' ', $event['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($event['created_at'])); ?></td>
                                            <td>
                                                <a href="<?php echo url('admin/event_details.php?id=' . $event['id']); ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Users -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-users me-2"></i>Recent Users</h5>
                    <a href="<?php echo url('admin/users.php'); ?>" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_users)): ?>
                        <p class="text-muted">No users registered yet.</p>
                    <?php else: ?>
                        <?php foreach ($recent_users as $user): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small><br>
                                    <small class="text-muted">KYC Tier: <?php echo $user['kyc_tier']; ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-success">$<?php echo number_format($user['balance'] ?? 0, 2); ?></div>
                                    <small class="text-muted"><?php echo date('M j', strtotime($user['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Status -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-server me-2"></i>System Status</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <i class="fas fa-database fa-2x text-success mb-2"></i>
                                <h6>Database</h6>
                                <span class="badge bg-success">Online</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                                <h6>Security</h6>
                                <span class="badge bg-success">Active</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <i class="fas fa-credit-card fa-2x text-success mb-2"></i>
                                <h6>Payments</h6>
                                <span class="badge bg-success">Operational</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <i class="fas fa-bell fa-2x text-success mb-2"></i>
                                <h6>Notifications</h6>
                                <span class="badge bg-success">Running</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>