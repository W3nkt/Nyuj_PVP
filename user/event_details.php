<?php
$page_title = 'Event Details - Bull PVP Platform';
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();

$event_id = intval($_GET['id'] ?? 0);
if (!$event_id) {
    header('Location: ' . url('user/events.php'));
    exit();
}

$db = new Database();
$conn = $db->connect();
$user_id = getUserId();

// Get event details with participants and streamers
$stmt = $conn->prepare("
    SELECT e.*, 
           u.username as creator_name,
           COUNT(DISTINCT ep.id) as participant_count,
           (SELECT COUNT(*) FROM event_participants ep2 WHERE ep2.event_id = e.id AND ep2.user_id = ? AND ep2.status = 'active') as user_joined
    FROM events e 
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN event_participants ep ON e.id = ep.event_id AND ep.status = 'active'
    WHERE e.id = ?
    GROUP BY e.id
");
$stmt->execute([$user_id, $event_id]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: ' . url('user/events.php'));
    exit();
}

// Get participants
$stmt = $conn->prepare("
    SELECT ep.*, u.username, u.id as user_id, ep.joined_at
    FROM event_participants ep
    JOIN users u ON ep.user_id = u.id
    WHERE ep.event_id = ? AND ep.status = 'active'
    ORDER BY ep.joined_at ASC
");
$stmt->execute([$event_id]);
$participants = $stmt->fetchAll();

// Get assigned streamers
$stmt = $conn->prepare("
    SELECT u.username, es.assigned_at
    FROM event_streamers es
    JOIN users u ON es.streamer_id = u.id
    WHERE es.event_id = ?
    ORDER BY es.assigned_at ASC
");
$stmt->execute([$event_id]);
$streamers = $stmt->fetchAll();

// Get voting results if available
$voting_results = [];
if (in_array($event['status'], ['final_result', 'settlement', 'closed'])) {
    $stmt = $conn->prepare("
        SELECT u.username, COUNT(v.id) as vote_count,
               (u.id = e.winner_id) as is_winner
        FROM votes v
        JOIN users u ON v.voted_winner_id = u.id
        JOIN events e ON v.event_id = e.id
        WHERE v.event_id = ?
        GROUP BY v.voted_winner_id, u.username, u.id, e.winner_id
        ORDER BY vote_count DESC
    ");
    $stmt->execute([$event_id]);
    $voting_results = $stmt->fetchAll();
}

// Get user's wallet balance
$stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch();
$balance = $wallet['balance'] ?? 0;

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo url('user/dashboard.php'); ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo url('user/events.php'); ?>">Events</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($event['name']); ?></li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <!-- Main Event Info -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><?php echo htmlspecialchars($event['name']); ?></h4>
                    <span class="event-status status-<?php echo str_replace('_', '-', $event['status']); ?>">
                        <?php echo strtoupper(str_replace('_', ' ', $event['status'])); ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($event['description']): ?>
                        <div class="mb-4">
                            <h6>Description</h6>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Event Details</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Game Type:</strong></td>
                                    <td><?php echo htmlspecialchars($event['game_type']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Stake Amount:</strong></td>
                                    <td class="text-primary fw-bold">$<?php echo number_format($event['stake_amount'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Max Participants:</strong></td>
                                    <td><?php echo $event['max_participants']; ?> players</td>
                                </tr>
                                <tr>
                                    <td><strong>Platform Fee:</strong></td>
                                    <td><?php echo $event['platform_fee_percent']; ?>%</td>
                                </tr>
                                <tr>
                                    <td><strong>Created By:</strong></td>
                                    <td><?php echo htmlspecialchars($event['creator_name'] ?? 'Admin'); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Prize Information</h6>
                            <?php
                            $total_pool = max($event['total_pool'], $event['stake_amount'] * $event['max_participants']);
                            $platform_fee = $total_pool * ($event['platform_fee_percent'] / 100);
                            $winner_payout = $total_pool - $platform_fee;
                            ?>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Total Pool:</strong></td>
                                    <td class="text-info">$<?php echo number_format($total_pool, 2); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Platform Fee:</strong></td>
                                    <td class="text-warning">-$<?php echo number_format($platform_fee, 2); ?></td>
                                </tr>
                                <tr class="table-success">
                                    <td><strong>Winner Gets:</strong></td>
                                    <td class="text-success fw-bold">$<?php echo number_format($winner_payout, 2); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($event['event_start_time'] || $event['event_end_time']): ?>
                        <div class="mb-4">
                            <h6>Timeline</h6>
                            <div class="row">
                                <?php if ($event['event_start_time']): ?>
                                    <div class="col-sm-6">
                                        <small class="text-muted">Start Time</small>
                                        <div><?php echo date('M j, Y g:i A', strtotime($event['event_start_time'])); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($event['event_end_time']): ?>
                                    <div class="col-sm-6">
                                        <small class="text-muted">End Time</small>
                                        <div><?php echo date('M j, Y g:i A', strtotime($event['event_end_time'])); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="text-center">
                        <?php if ($event['user_joined'] > 0): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                You have joined this event! Good luck in the competition.
                            </div>
                        <?php elseif ($event['status'] === 'waiting_for_players' && $event['participant_count'] < $event['max_participants']): ?>
                            <?php if ($balance >= $event['stake_amount']): ?>
                                <button class="btn btn-primary btn-lg join-event-btn" 
                                        data-event-id="<?php echo $event['id']; ?>"
                                        data-stake="<?php echo $event['stake_amount']; ?>">
                                    <i class="fas fa-plus me-2"></i>Join This Event
                                </button>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        Your stake of $<?php echo number_format($event['stake_amount'], 2); ?> will be held in escrow
                                    </small>
                                </div>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-lg" disabled>
                                    <i class="fas fa-wallet me-2"></i>Insufficient Balance
                                </button>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        You need $<?php echo number_format($event['stake_amount'] - $balance, 2); ?> more to join this event
                                    </small>
                                    <br>
                                    <button class="btn btn-sm btn-outline-primary mt-2 deposit-btn">
                                        <i class="fas fa-plus me-1"></i>Add Funds
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php
                            $message = '';
                            switch ($event['status']) {
                                case 'ready':
                                    $message = 'Event is full and ready to start.';
                                    break;
                                case 'live':
                                    $message = 'Event is currently in progress.';
                                    break;
                                case 'streamer_voting':
                                    $message = 'Event has ended. Streamers are voting on the results.';
                                    break;
                                case 'final_result':
                                    $message = 'Results have been finalized. Payouts are being processed.';
                                    break;
                                case 'closed':
                                    $message = 'Event has been completed.';
                                    break;
                                default:
                                    $message = 'Event is not currently accepting participants.';
                            }
                            ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i><?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Voting Results (if available) -->
            <?php if (!empty($voting_results)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-vote-yea me-2"></i>Voting Results</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Participant</th>
                                    <th>Votes Received</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($voting_results as $result): ?>
                                    <tr class="<?php echo $result['is_winner'] ? 'table-success' : ''; ?>">
                                        <td><?php echo htmlspecialchars($result['username']); ?></td>
                                        <td><?php echo $result['vote_count']; ?></td>
                                        <td>
                                            <?php if ($result['is_winner']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-trophy me-1"></i>Winner
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Participants -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-users me-2"></i>Participants</h5>
                    <span class="badge bg-primary"><?php echo count($participants); ?>/<?php echo $event['max_participants']; ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($participants)): ?>
                        <p class="text-muted text-center py-3">
                            <i class="fas fa-user-plus fa-2x mb-2 d-block"></i>
                            No participants yet. Be the first to join!
                        </p>
                    <?php else: ?>
                        <?php foreach ($participants as $index => $participant): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="fw-bold">
                                        <?php echo htmlspecialchars($participant['username']); ?>
                                        <?php if ($participant['user_id'] == $user_id): ?>
                                            <span class="badge bg-success ms-1">You</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        Joined <?php echo date('M j, g:i A', strtotime($participant['joined_at'])); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-outline-primary">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($participants) < $event['max_participants']): ?>
                            <div class="text-center mt-3 pt-3 border-top">
                                <small class="text-muted">
                                    <?php echo $event['max_participants'] - count($participants); ?> more spots available
                                </small>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Assigned Streamers -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-video me-2"></i>Assigned Streamers</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($streamers)): ?>
                        <p class="text-muted">No streamers assigned yet.</p>
                    <?php else: ?>
                        <?php foreach ($streamers as $streamer): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($streamer['username']); ?></div>
                                </div>
                                <div>
                                    <i class="fas fa-shield-alt text-success" title="Verified Streamer"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <hr>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Results will be verified by majority vote from assigned streamers.
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Event Progress -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line me-2"></i>Event Progress</h5>
                </div>
                <div class="card-body">
                    <?php
                    $progress_steps = [
                        'created' => 'Created',
                        'waiting_for_players' => 'Accepting Players',
                        'ready' => 'Ready to Start',
                        'live' => 'In Progress',
                        'event_end' => 'Completed',
                        'streamer_voting' => 'Voting Phase',
                        'final_result' => 'Results Final',
                        'settlement' => 'Processing Payouts',
                        'closed' => 'Finished'
                    ];
                    
                    $current_step = array_search($event['status'], array_keys($progress_steps));
                    ?>
                    
                    <div class="progress mb-3" style="height: 8px;">
                        <div class="progress-bar" style="width: <?php echo (($current_step + 1) / count($progress_steps)) * 100; ?>%"></div>
                    </div>
                    
                    <ul class="list-unstyled">
                        <?php foreach ($progress_steps as $step => $label): ?>
                            <?php
                            $step_index = array_search($step, array_keys($progress_steps));
                            $is_current = $step === $event['status'];
                            $is_completed = $step_index < $current_step;
                            $icon = $is_completed ? 'fas fa-check-circle text-success' : 
                                   ($is_current ? 'fas fa-dot-circle text-primary' : 'far fa-circle text-muted');
                            ?>
                            <li class="mb-2">
                                <i class="<?php echo $icon; ?> me-2"></i>
                                <span class="<?php echo $is_current ? 'fw-bold text-primary' : ($is_completed ? 'text-success' : 'text-muted'); ?>">
                                    <?php echo $label; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <small class="text-muted">
                        Created <?php echo date('M j, Y g:i A', strtotime($event['created_at'])); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Join Event Modal -->
<div class="modal fade" id="joinEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Join Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to join <strong><?php echo htmlspecialchars($event['name']); ?></strong>?</p>
                <div class="alert alert-info">
                    <strong>Stake Amount:</strong> <span id="modalStakeAmount"></span><br>
                    <small>This amount will be deducted from your wallet and held in escrow until the event is completed.</small>
                </div>
                <div class="alert alert-warning">
                    <small><strong>Important:</strong> Once you join, you cannot cancel or withdraw your stake until the event is completed and results are finalized.</small>
                </div>
                <input type="hidden" id="modalEventId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="joinEvent(document.getElementById('modalEventId').value)">
                    <i class="fas fa-check me-2"></i>Join Event
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Deposit Modal -->
<div class="modal fade" id="depositModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Funds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="depositForm">
                    <div class="mb-3">
                        <label for="depositAmount" class="form-label">Amount ($)</label>
                        <input type="number" class="form-control" id="depositAmount" min="1" step="0.01" 
                               value="<?php echo ceil($event['stake_amount'] - $balance); ?>" required>
                        <small class="form-text text-muted">
                            You need $<?php echo number_format($event['stake_amount'] - $balance, 2); ?> to join this event
                        </small>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This is a demo. In production, this would integrate with a real payment processor.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="processDeposit(document.getElementById('depositAmount').value)">
                    <i class="fas fa-credit-card me-2"></i>Add Funds
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>