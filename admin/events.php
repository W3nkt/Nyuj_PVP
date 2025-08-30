<?php
$page_title = 'Manage Events - Admin Panel';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';

requireRole('admin');

$error_message = '';
$success_message = '';
$events = [];

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Handle event status updates
    if ($_POST && isset($_POST['action']) && isset($_POST['event_id'])) {
        $event_id = intval($_POST['event_id']);
        $action = $_POST['action'];
        
        if ($event_id > 0) {
            try {
                switch ($action) {
                    case 'activate':
                        $stmt = $conn->prepare("UPDATE events SET status = 'accepting_bets' WHERE id = ?");
                        $stmt->execute([$event_id]);
                        $success_message = 'Event activated successfully!';
                        break;
                        
                    case 'pause':
                        $stmt = $conn->prepare("UPDATE events SET status = 'paused' WHERE id = ?");
                        $stmt->execute([$event_id]);
                        $success_message = 'Event paused successfully!';
                        break;
                        
                    case 'resume':
                        $stmt = $conn->prepare("UPDATE events SET status = 'accepting_bets' WHERE id = ?");
                        $stmt->execute([$event_id]);
                        $success_message = 'Event resumed successfully!';
                        break;
                        
                    case 'cancel':
                        $stmt = $conn->prepare("UPDATE events SET status = 'cancelled' WHERE id = ?");
                        $stmt->execute([$event_id]);
                        $success_message = 'Event cancelled successfully!';
                        break;
                        
                    case 'delete':
                        // Simple delete without bet checking for now
                        $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
                        $stmt->execute([$event_id]);
                        $success_message = 'Event deleted successfully!';
                        break;
                }
            } catch (Exception $e) {
                $error_message = 'Action failed: ' . $e->getMessage();
            }
        }
    }
    
    // Get events with basic info
    try {
        $stmt = $conn->prepare("
            SELECT e.*, u.username as creator_name
            FROM events e 
            LEFT JOIN users u ON e.created_by = u.id
            ORDER BY 
                CASE e.status 
                    WHEN 'accepting_bets' THEN 1
                    WHEN 'live' THEN 2
                    WHEN 'paused' THEN 3
                    WHEN 'created' THEN 4
                    WHEN 'closed' THEN 5
                    WHEN 'streamer_voting' THEN 6
                    WHEN 'admin_review' THEN 7
                    WHEN 'cancelled' THEN 8
                    WHEN 'completed' THEN 9
                    ELSE 10
                END ASC,
                e.created_at DESC 
            LIMIT 50
        ");
        $stmt->execute();
        $events = $stmt->fetchAll();
    } catch (Exception $e) {
        $error_message = 'Could not load events: ' . $e->getMessage();
    }

} catch (Exception $e) {
    $error_message = 'Database connection failed: ' . $e->getMessage();
}

require_once '../includes/header.php';
?>

<div class="container-fluid mt-4">
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
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-calendar-alt me-2"></i>Manage Events</h2>
                    <p class="text-muted mb-0">Monitor and manage all platform events</p>
                </div>
                <div>
                    <a href="<?php echo url('admin/create_event.php'); ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Create New Event
                    </a>
                    <a href="<?php echo url('admin/index.php'); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-dashboard me-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Events Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Events (<?php echo count($events); ?> found)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($events)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Events Found</h5>
                            <p class="text-muted">
                                <?php if ($error_message): ?>
                                    Please check database connection or run setup.
                                <?php else: ?>
                                    No events have been created yet.
                                <?php endif; ?>
                            </p>
                            <a href="<?php echo url('admin/create_event.php'); ?>" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Create First Event
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Event Name</th>
                                        <th>Game Type</th>
                                        <th>Competitors</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <tr class="clickable-row" data-href="<?php echo url('admin/event_details.php?id=' . $event['id']); ?>" style="cursor: pointer;">
                                            <td><?php echo $event['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($event['name']); ?></strong>
                                                <?php if ($event['creator_name']): ?>
                                                    <br><small class="text-muted">by <?php echo htmlspecialchars($event['creator_name']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($event['game_type']); ?>
                                                <br><small class="text-muted">Fee: <?php echo number_format($event['platform_fee_percent'], 1); ?>%</small>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <div class="fw-bold text-primary">
                                                        <?php echo htmlspecialchars($event['competitor_a']); ?>
                                                    </div>
                                                    <div class="text-muted">vs</div>
                                                    <div class="fw-bold text-danger">
                                                        <?php echo htmlspecialchars($event['competitor_b']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $status = $event['status'];
                                                $badge_class = 'secondary';
                                                if ($status === 'accepting_bets') $badge_class = 'success';
                                                elseif ($status === 'live') $badge_class = 'warning';
                                                elseif ($status === 'completed') $badge_class = 'info';
                                                elseif ($status === 'cancelled') $badge_class = 'danger';
                                                elseif ($status === 'paused') $badge_class = 'dark';
                                                elseif ($status === 'closed') $badge_class = 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <?php echo strtoupper(str_replace('_', ' ', $status)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($event['created_at'])); ?>
                                                <?php if ($event['match_start_time']): ?>
                                                    <br><small class="text-muted">
                                                        Start: <?php echo date('M j, g:i A', strtotime($event['match_start_time'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1 justify-content-center flex-wrap" role="group">
                                                    <!-- View Details Button -->
                                                    <a href="<?php echo url('admin/event_details.php?id=' . $event['id']); ?>" 
                                                       class="btn btn-outline-primary btn-square" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($event['status'] === 'created'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                            <input type="hidden" name="action" value="activate">
                                                            <button type="submit" class="btn btn-success btn-square" title="Activate Event">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($event['status'], ['accepting_bets', 'live'])): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                            <input type="hidden" name="action" value="pause">
                                                            <button type="submit" class="btn btn-warning btn-square" title="Pause Event">
                                                                <i class="fas fa-pause"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($event['status'] === 'paused'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                            <input type="hidden" name="action" value="resume">
                                                            <button type="submit" class="btn btn-info btn-square" title="Resume Event">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!in_array($event['status'], ['cancelled', 'completed'])): ?>
                                                        <form method="POST" style="display: inline;"
                                                              onsubmit="return confirm('Are you sure you want to cancel this event?')">
                                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                            <input type="hidden" name="action" value="cancel">
                                                            <button type="submit" class="btn btn-danger btn-square" title="Cancel Event">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <form method="POST" style="display: inline;" 
                                                          onsubmit="return confirm('Are you sure you want to delete this event? This cannot be undone.')">
                                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-outline-danger btn-square" title="Delete Event">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
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
    </div>
</div>

<script>
// Make table rows clickable
document.addEventListener('DOMContentLoaded', function() {
    const clickableRows = document.querySelectorAll('.clickable-row');
    
    clickableRows.forEach(function(row) {
        row.addEventListener('click', function(e) {
            // Don't navigate if clicking on buttons or form elements
            if (e.target.closest('.btn') || e.target.closest('form') || e.target.closest('button')) {
                return;
            }
            
            const href = this.dataset.href;
            if (href) {
                window.location.href = href;
            }
        });
        
        // Add hover effect
        row.addEventListener('mouseenter', function() {
            if (!this.style.backgroundColor) {
                this.style.backgroundColor = 'rgba(0,123,255,0.1)';
            }
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>