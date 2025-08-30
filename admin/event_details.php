<?php
$page_title = 'Event Details - Admin Panel';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';

requireRole('admin');

$event_id = intval($_GET['id'] ?? 0);
if (!$event_id) {
    header('Location: ' . url('admin/events.php'));
    exit();
}

$error_message = '';
$success_message = '';

try {
    $db = new Database();
    $conn = $db->connect();

    // Handle form submissions
    if ($_POST) {
        if (isset($_POST['update_status'])) {
            $new_status = $_POST['status'];
            $stmt = $conn->prepare("UPDATE events SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $event_id]);
            $success_message = 'Event status updated successfully!';
        }
        
        if (isset($_POST['update_winner'])) {
            $winner = $_POST['winner'];
            $stmt = $conn->prepare("UPDATE events SET winner = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$winner, $event_id]);
            $success_message = 'Event winner updated successfully!';
        }
    }

    // Get basic event details
    $stmt = $conn->prepare("
        SELECT e.*, u.username as creator_name
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        header('Location: ' . url('admin/events.php'));
        exit();
    }

    // Get bet statistics separately
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_bets,
            COUNT(CASE WHEN bet_on = 'A' THEN 1 END) as bets_on_a,
            COUNT(CASE WHEN bet_on = 'B' THEN 1 END) as bets_on_b,
            COALESCE(SUM(CASE WHEN bet_on = 'A' THEN amount END), 0) as total_amount_a,
            COALESCE(SUM(CASE WHEN bet_on = 'B' THEN amount END), 0) as total_amount_b
        FROM bets 
        WHERE event_id = ?
    ");
    $stmt->execute([$event_id]);
    $bet_stats = $stmt->fetch();

    // Get all bets for this event
    $stmt = $conn->prepare("
        SELECT b.*, u.username, u.email
        FROM bets b
        JOIN users u ON b.user_id = u.id
        WHERE b.event_id = ?
        ORDER BY b.placed_at DESC
    ");
    $stmt->execute([$event_id]);
    $bets = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    $event = null;
    $bet_stats = null;
    $bets = [];
}

require_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Event Details</h1>
                <a href="<?php echo url('admin/events.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Events
                </a>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($event): ?>
            <div class="row">
                <!-- Event Information Card -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Event Information</h5>
                            <span class="event-status status-<?php echo str_replace('_', '-', $event['status']); ?>">
                                <?php echo strtoupper(str_replace('_', ' ', $event['status'])); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Event Name:</strong><br>
                                    <?php echo htmlspecialchars($event['name']); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Game Type:</strong><br>
                                    <?php echo htmlspecialchars($event['game_type']); ?>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Competitor A:</strong><br>
                                    <?php echo htmlspecialchars($event['competitor_a']); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Competitor B:</strong><br>
                                    <?php echo htmlspecialchars($event['competitor_b']); ?>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Match Start Time:</strong><br>
                                    <?php echo $event['match_start_time'] ? date('M j, Y g:i A', strtotime($event['match_start_time'])) : 'Not set'; ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Platform Fee:</strong><br>
                                    <?php echo $event['platform_fee_percent']; ?>%
                                </div>
                            </div>

                            <?php if ($event['description']): ?>
                            <div class="mb-3">
                                <strong>Description:</strong><br>
                                <?php echo htmlspecialchars($event['description']); ?>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <strong>Created by:</strong> <?php echo htmlspecialchars($event['creator_name']); ?><br>
                                <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($event['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Management Card -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Event Management</h5>
                        </div>
                        <div class="card-body">
                            <!-- Status Update -->
                            <form method="POST" class="mb-3">
                                <label for="status" class="form-label">Event Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="created" <?php echo $event['status'] === 'created' ? 'selected' : ''; ?>>Created</option>
                                    <option value="accepting_bets" <?php echo $event['status'] === 'accepting_bets' ? 'selected' : ''; ?>>Accepting Bets</option>
                                    <option value="closed" <?php echo $event['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    <option value="live" <?php echo $event['status'] === 'live' ? 'selected' : ''; ?>>Live</option>
                                    <option value="paused" <?php echo $event['status'] === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                    <option value="streamer_voting" <?php echo $event['status'] === 'streamer_voting' ? 'selected' : ''; ?>>Streamer Voting</option>
                                    <option value="completed" <?php echo $event['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $event['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-warning btn-sm mt-2 w-100">
                                    Update Status
                                </button>
                            </form>

                            <!-- Winner Selection -->
                            <form method="POST">
                                <label for="winner" class="form-label">Winner</label>
                                <select class="form-select" id="winner" name="winner">
                                    <option value="">No Winner Set</option>
                                    <option value="A" <?php echo $event['winner'] === 'A' ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['competitor_a']); ?>
                                    </option>
                                    <option value="B" <?php echo $event['winner'] === 'B' ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['competitor_b']); ?>
                                    </option>
                                    <option value="DRAW" <?php echo $event['winner'] === 'DRAW' ? 'selected' : ''; ?>>Draw</option>
                                </select>
                                <button type="submit" name="update_winner" class="btn btn-success btn-sm mt-2 w-100">
                                    Update Winner
                                </button>
                            </form>

                            <!-- Event Stats -->
                            <?php if ($bet_stats): ?>
                            <div class="mt-4">
                                <h6>Event Statistics</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Total Bets:</strong> <?php echo $bet_stats['total_bets']; ?></li>
                                    <li><strong>Bets on A:</strong> <?php echo $bet_stats['bets_on_a']; ?> ($<?php echo number_format($bet_stats['total_amount_a'], 2); ?>)</li>
                                    <li><strong>Bets on B:</strong> <?php echo $bet_stats['bets_on_b']; ?> ($<?php echo number_format($bet_stats['total_amount_b'], 2); ?>)</li>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bets Table -->
            <?php if (!empty($bets)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">All Bets (<?php echo count($bets); ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Bet On</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Placed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bets as $bet): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($bet['username']); ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($bet['email']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $bet['bet_on'] === 'A' ? 'primary' : 'secondary'; ?>">
                                            <?php echo $bet['bet_on'] === 'A' ? htmlspecialchars($event['competitor_a']) : htmlspecialchars($event['competitor_b']); ?>
                                        </span>
                                    </td>
                                    <td>$<?php echo number_format($bet['amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            $status_class = 'light';
                                            switch($bet['status']) {
                                                case 'pending': $status_class = 'warning'; break;
                                                case 'matched': $status_class = 'info'; break;
                                                case 'won': $status_class = 'success'; break;
                                                case 'lost': $status_class = 'danger'; break;
                                                case 'refunded': $status_class = 'secondary'; break;
                                                case 'cancelled': $status_class = 'dark'; break;
                                            }
                                            echo $status_class;
                                        ?>">
                                            <?php echo ucfirst($bet['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($bet['placed_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="alert alert-warning">
                Event not found or access denied.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>