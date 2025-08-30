<?php
$page_title = 'Streamer Voting Panel - Bull PVP Platform';

try {
    require_once '../config/session.php';
    require_once '../config/database.php';
    require_once '../config/paths.php';

    requireRole('streamer');

    $db = new Database();
    $conn = $db->connect();
    $streamer_id = getUserId();

    // Initialize variables
    $voting_events = [];
    $total_votes = 0;
    $correct_votes = 0;
    $reputation_score = 100;

    // Get ALL events with comprehensive information for streamers
    try {
        $stmt = $conn->prepare("
            SELECT e.*, 
                   COUNT(DISTINCT ep.id) as participant_count,
                   COUNT(DISTINCT es.streamer_id) as total_streamers,
                   COUNT(DISTINCT v.streamer_id) as voted_streamers,
                   CASE WHEN v_streamer.id IS NOT NULL THEN 1 ELSE 0 END as has_voted,
                   v_streamer.voted_winner_id as streamer_vote_choice,
                   v_streamer.created_at as vote_time,
                   winner_user.username as winner_name,
                   CASE WHEN es.streamer_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned
            FROM events e
            LEFT JOIN event_participants ep ON e.id = ep.event_id AND ep.status = 'active'
            LEFT JOIN event_streamers es ON e.id = es.event_id
            LEFT JOIN votes v ON e.id = v.event_id
            LEFT JOIN votes v_streamer ON e.id = v_streamer.event_id AND v_streamer.streamer_id = ?
            LEFT JOIN users winner_user ON e.winner_id = winner_user.id
            GROUP BY e.id
            ORDER BY 
                CASE 
                    WHEN e.status = 'live' THEN 1
                    WHEN e.status = 'ready' THEN 2
                    WHEN e.status = 'event_end' THEN 3
                    WHEN e.status = 'streamer_voting' THEN 4
                    WHEN e.status = 'waiting_for_players' THEN 5
                    WHEN e.status = 'admin_review' THEN 6
                    WHEN e.status = 'final_result' THEN 7
                    ELSE 8
                END,
                e.event_start_time DESC,
                e.created_at DESC
        ");
        $stmt->execute([$streamer_id]);
        $voting_events = $stmt->fetchAll();
        
        // Calculate event properties for each event
        foreach ($voting_events as &$event) {
            $event['voting_progress'] = $event['total_streamers'] > 0 
                ? round(($event['voted_streamers'] / $event['total_streamers']) * 100) 
                : 0;
            
            // Determine event actions based on status
            $event['can_stream'] = in_array($event['status'], ['live', 'ready']) && $event['is_assigned'];
            $event['needs_vote'] = in_array($event['status'], ['event_end', 'streamer_voting']) 
                && $event['has_voted'] == 0 && $event['is_assigned'];
            $event['can_view_result'] = in_array($event['status'], ['final_result', 'settlement', 'closed']);
            $event['is_upcoming'] = in_array($event['status'], ['created', 'waiting_for_players']);
            $event['awaiting_start'] = ($event['status'] === 'ready');
            
            // Calculate time-related properties
            $now = time();
            if ($event['event_start_time']) {
                $start_time = strtotime($event['event_start_time']);
                $event['starts_soon'] = ($start_time - $now) <= 3600 && ($start_time - $now) > 0; // Within 1 hour
                $event['is_overdue'] = ($start_time < $now) && in_array($event['status'], ['created', 'waiting_for_players', 'ready']);
            } else {
                $event['starts_soon'] = false;
                $event['is_overdue'] = false;
            }
        }
        
    } catch (Exception $e) {
        // Fallback to basic events query if advanced query fails
        try {
            $stmt = $conn->prepare("
                SELECT e.*, 
                       0 as participant_count,
                       0 as total_streamers,
                       0 as voted_streamers,
                       0 as has_voted,
                       0 as voting_progress,
                       0 as needs_vote,
                       0 as can_view_result,
                       0 as can_stream,
                       0 as is_upcoming,
                       0 as awaiting_start,
                       0 as starts_soon,
                       0 as is_overdue,
                       0 as is_assigned
                FROM events e 
                ORDER BY 
                    CASE 
                        WHEN e.status = 'live' THEN 1
                        WHEN e.status = 'ready' THEN 2
                        WHEN e.status = 'event_end' THEN 3
                        ELSE 4
                    END,
                    e.created_at DESC
            ");
            $stmt->execute();
            $voting_events = $stmt->fetchAll();
        } catch (Exception $e2) {
            $voting_events = [];
        }
    }

    // Get comprehensive streamer statistics
    try {
        // Get total votes by this streamer
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM votes WHERE streamer_id = ?");
        $stmt->execute([$streamer_id]);
        $total_votes = $stmt->fetch()['total'] ?? 0;
        
        // Get streamer reputation if exists
        $stmt = $conn->prepare("SELECT * FROM streamer_reputation WHERE streamer_id = ?");
        $stmt->execute([$streamer_id]);
        $reputation_data = $stmt->fetch();
        
        if ($reputation_data) {
            $correct_votes = $reputation_data['correct_votes'];
            $reputation_score = $reputation_data['reputation_score'];
        } else {
            // Calculate basic reputation from votes
            $stmt = $conn->prepare("
                SELECT COUNT(*) as correct_count 
                FROM votes v 
                JOIN events e ON v.event_id = e.id 
                WHERE v.streamer_id = ? AND v.voted_winner_id = e.winner_id
            ");
            $stmt->execute([$streamer_id]);
            $correct_votes = $stmt->fetch()['correct_count'] ?? 0;
            
            // Calculate reputation score (accuracy-based)
            $reputation_score = $total_votes > 0 ? (($correct_votes / $total_votes) * 100) : 100;
        }
        
        // Count events awaiting this streamer's vote
        $stmt = $conn->prepare("
            SELECT COUNT(*) as pending_votes
            FROM events e
            JOIN event_streamers es ON e.id = es.event_id AND es.streamer_id = ?
            LEFT JOIN votes v ON e.id = v.event_id AND v.streamer_id = ?
            WHERE e.status IN ('event_end', 'streamer_voting') AND v.id IS NULL
        ");
        $stmt->execute([$streamer_id, $streamer_id]);
        $pending_votes = $stmt->fetch()['pending_votes'] ?? 0;
        
    } catch (Exception $e) {
        $total_votes = 0;
        $correct_votes = 0;
        $reputation_score = 100;
        $pending_votes = 0;
    }

} catch (Exception $e) {
    // Log the error and show a basic page
    error_log("Voting page error: " . $e->getMessage());
    $voting_events = [];
    $total_votes = 0;
    $correct_votes = 0;
    $reputation_score = 100;
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12 mb-4">
            <h2><i class="fas fa-vote-yea me-2"></i>Streamer Voting Panel</h2>
            <p class="text-muted">Vote on event results to maintain platform integrity</p>
        </div>
    </div>
    
    <!-- Streamer Statistics -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stats-card">
                <div class="stats-number text-primary"><?php echo $pending_votes ?? 0; ?></div>
                <div class="stats-label">Pending Votes</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card">
                <div class="stats-number"><?php echo $total_votes; ?></div>
                <div class="stats-label">Total Votes</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card">
                <div class="stats-number text-success"><?php echo $correct_votes; ?></div>
                <div class="stats-label">Correct Votes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-number"><?php echo $total_votes > 0 ? round(($correct_votes / $total_votes) * 100, 1) : 0; ?>%</div>
                <div class="stats-label">Voting Accuracy</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-number <?php echo $reputation_score >= 80 ? 'text-success' : ($reputation_score >= 60 ? 'text-warning' : 'text-danger'); ?>">
                    <?php echo number_format($reputation_score, 1); ?>
                </div>
                <div class="stats-label">Reputation Score</div>
            </div>
        </div>
    </div>
    
    <!-- Voting Events -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-gavel me-2"></i>All Events Available for Voting</h5>
                    <small class="text-muted">Vote on event outcomes - select the winning team/player</small>
                </div>
                <div class="card-body">
                    <?php if (empty($voting_events)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5>No Events Available</h5>
                            <p class="text-muted">No events are currently available for voting. Events will appear here when they need streamer votes.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        // Group events by status and action required
                        $event_groups = [];
                        foreach ($voting_events as $event) {
                            $group = '';
                            if ($event['can_stream']) {
                                $group = 'live_streaming';
                            } elseif ($event['needs_vote']) {
                                $group = 'pending_votes';
                            } elseif ($event['awaiting_start'] || $event['starts_soon']) {
                                $group = 'ready_to_start';
                            } elseif ($event['is_upcoming']) {
                                $group = 'upcoming';
                            } elseif ($event['has_voted'] && in_array($event['status'], ['streamer_voting', 'admin_review'])) {
                                $group = 'awaiting_result';
                            } elseif ($event['can_view_result']) {
                                $group = 'completed';
                            } else {
                                $group = 'other';
                            }
                            $event_groups[$group][] = $event;
                        }
                        
                        $group_titles = [
                            'live_streaming' => ['title' => 'Live Events - Stream Now', 'icon' => 'fa-broadcast-tower', 'class' => 'text-danger'],
                            'ready_to_start' => ['title' => 'Events Ready to Start', 'icon' => 'fa-play-circle', 'class' => 'text-primary'],
                            'pending_votes' => ['title' => 'Events Awaiting Your Vote', 'icon' => 'fa-gavel', 'class' => 'text-warning'],
                            'upcoming' => ['title' => 'Upcoming Events', 'icon' => 'fa-calendar-plus', 'class' => 'text-info'],
                            'awaiting_result' => ['title' => 'Voted - Awaiting Final Results', 'icon' => 'fa-hourglass-half', 'class' => 'text-muted'],
                            'completed' => ['title' => 'Completed Events with Final Results', 'icon' => 'fa-check-circle', 'class' => 'text-success'],
                            'other' => ['title' => 'Other Events', 'icon' => 'fa-list', 'class' => 'text-secondary']
                        ];
                        ?>
                        
                        <?php foreach ($event_groups as $group_key => $events): ?>
                            <?php if (!empty($events)): ?>
                                <div class="mb-4">
                                    <h6 class="<?php echo $group_titles[$group_key]['class']; ?> mb-3">
                                        <i class="fas <?php echo $group_titles[$group_key]['icon']; ?> me-2"></i>
                                        <?php echo $group_titles[$group_key]['title']; ?>
                                        <span class="badge bg-secondary ms-2"><?php echo count($events); ?></span>
                                    </h6>
                                    
                                    <?php foreach ($events as $event): ?>
                                        <div class="vote-panel mb-3 <?php echo $event['needs_vote'] ? 'border-warning' : ''; ?>" data-event-id="<?php echo $event['id']; ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <h5 class="mb-0"><?php echo htmlspecialchars($event['name']); ?></h5>
                                                        
                                                        <?php if ($event['can_stream']): ?>
                                                            <span class="badge bg-danger text-white ms-2 pulse-animation">
                                                                <i class="fas fa-video me-1"></i>LIVE NOW
                                                            </span>
                                                        <?php elseif ($event['needs_vote']): ?>
                                                            <span class="badge bg-warning text-dark ms-2 pulse-animation">
                                                                <i class="fas fa-gavel me-1"></i>VOTE NEEDED
                                                            </span>
                                                        <?php elseif ($event['awaiting_start']): ?>
                                                            <span class="badge bg-primary text-white ms-2">
                                                                <i class="fas fa-play me-1"></i>READY TO START
                                                            </span>
                                                        <?php elseif ($event['starts_soon']): ?>
                                                            <span class="badge bg-info text-white ms-2">
                                                                <i class="fas fa-clock me-1"></i>STARTING SOON
                                                            </span>
                                                        <?php elseif ($event['is_overdue']): ?>
                                                            <span class="badge bg-secondary text-white ms-2">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>OVERDUE
                                                            </span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!$event['is_assigned'] && in_array($event['status'], ['live', 'ready', 'event_end', 'streamer_voting'])): ?>
                                                            <span class="badge bg-light text-dark ms-2">
                                                                <i class="fas fa-user-slash me-1"></i>NOT ASSIGNED
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($event['description']); ?></p>
                                                    <div class="row">
                                                        <div class="col-md-8">
                                                            <small class="text-muted">
                                                                <strong>Game:</strong> <?php echo htmlspecialchars($event['game_type']); ?> |
                                                                <strong>Stake:</strong> $<?php echo number_format($event['stake_amount'], 2); ?> |
                                                                <strong>Prize Pool:</strong> $<?php echo number_format($event['total_pool'], 2); ?> |
                                                                <strong>Players:</strong> <?php echo $event['participant_count']; ?>
                                                            </small>
                                                        </div>
                                                        <div class="col-md-4 text-end">
                                                            <?php if ($event['event_start_time']): ?>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-calendar me-1"></i>
                                                                    Start: <?php echo date('M j, Y g:i A', strtotime($event['event_start_time'])); ?>
                                                                </small>
                                                                <br>
                                                            <?php endif; ?>
                                                            <?php if ($event['event_end_time']): ?>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-flag-checkered me-1"></i>
                                                                    End: <?php echo date('M j, Y g:i A', strtotime($event['event_end_time'])); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="event-status status-<?php echo str_replace('_', '-', $event['status']); ?>">
                                                        <?php echo strtoupper(str_replace('_', ' ', $event['status'])); ?>
                                                    </span>
                                                    <?php if ($event['total_streamers'] > 0): ?>
                                                        <br>
                                                        <div class="progress mt-2" style="height: 8px; width: 100px;">
                                                            <div class="progress-bar" role="progressbar" 
                                                                 style="width: <?php echo $event['voting_progress']; ?>%" 
                                                                 aria-valuenow="<?php echo $event['voting_progress']; ?>" 
                                                                 aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo $event['voted_streamers']; ?>/<?php echo $event['total_streamers']; ?> votes
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if ($event['can_view_result'] && $event['winner_name']): ?>
                                                <div class="alert alert-success">
                                                    <i class="fas fa-trophy me-2"></i>
                                                    <strong>Final Result:</strong> Winner is <strong><?php echo htmlspecialchars($event['winner_name']); ?></strong>
                                                    <?php if ($event['has_voted'] && $event['streamer_vote_choice']): ?>
                                                        <?php
                                                        $vote_correct = ($event['streamer_vote_choice'] == $event['winner_id']);
                                                        ?>
                                                        <br>
                                                        <small class="<?php echo $vote_correct ? 'text-success' : 'text-danger'; ?>">
                                                            <i class="fas fa-<?php echo $vote_correct ? 'check' : 'times'; ?> me-1"></i>
                                                            Your vote: <?php echo $vote_correct ? 'Correct!' : 'Incorrect'; ?>
                                                            (Voted: <?php echo date('M j, Y g:i A', strtotime($event['vote_time'])); ?>)
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Streaming Controls for Live Events -->
                                            <?php if ($event['can_stream']): ?>
                                                <div class="alert alert-danger">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <i class="fas fa-video me-2"></i>
                                                            <strong>Live Event - Stream Active</strong>
                                                            <br><small>Cast this battle live and vote for the winner when finished</small>
                                                        </div>
                                                        <div>
                                                            <button class="btn btn-danger me-2" onclick="startStreaming(<?php echo $event['id']; ?>)">
                                                                <i class="fas fa-broadcast-tower me-1"></i>Start Stream
                                                            </button>
                                                            <button class="btn btn-outline-danger" onclick="endEvent(<?php echo $event['id']; ?>)">
                                                                <i class="fas fa-stop-circle me-1"></i>End Event
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php elseif ($event['awaiting_start'] && $event['is_assigned']): ?>
                                                <div class="alert alert-primary">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <i class="fas fa-play-circle me-2"></i>
                                                            <strong>Ready to Start</strong>
                                                            <br><small>This event is ready to begin. Click start when participants are ready.</small>
                                                        </div>
                                                        <div>
                                                            <button class="btn btn-primary" onclick="startEvent(<?php echo $event['id']; ?>)">
                                                                <i class="fas fa-play me-1"></i>Start Event
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php elseif ($event['is_upcoming']): ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-calendar-plus me-2"></i>
                                                    <strong>Upcoming Event</strong>
                                                    <br><small>This event is scheduled but not yet ready to start. Waiting for participants.</small>
                                                </div>
                                            <?php endif; ?>
                                
                                            <?php if ($event['has_voted'] > 0 && !$event['can_view_result']): ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-check me-2"></i>
                                                    You have voted on this event. Waiting for other streamers to complete voting.
                                                    <?php if ($event['vote_time']): ?>
                                                        <br><small>Voted on: <?php echo date('M j, Y g:i A', strtotime($event['vote_time'])); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif ($event['needs_vote']): ?>
                                                <div class="mb-3">
                                                    <h6><i class="fas fa-trophy me-2"></i>Vote for the Winner:</h6>
                                                    <div class="alert alert-warning">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        <strong>Instructions:</strong> Select the team/player who won this event. The other participants will be marked as losers.
                                                    </div>
                                                    <div id="participants_<?php echo $event['id']; ?>">
                                                        <!-- Participants will be loaded via AJAX -->
                                                        <div class="text-center">
                                                            <div class="loading-spinner"></div>
                                                            <p class="mt-2">Loading participants...</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="vote_notes_<?php echo $event['id']; ?>" class="form-label">
                                                        Voting Notes (Optional)
                                                    </label>
                                                    <textarea class="form-control" id="vote_notes_<?php echo $event['id']; ?>" 
                                                              rows="2" placeholder="Add any observations about the match..."></textarea>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <small class="text-muted">
                                                            <i class="fas fa-info-circle me-1"></i>
                                                            Vote carefully - this affects your reputation score
                                                        </small>
                                                    </div>
                                                    <button class="btn btn-warning submit-vote-btn" 
                                                            data-event-id="<?php echo $event['id']; ?>" disabled>
                                                        <i class="fas fa-gavel me-2"></i>Submit Vote
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Voting Guidelines -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-shield-alt me-2"></i>Voting Guidelines</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Best Practices:</h6>
                            <ul>
                                <li>Review all available evidence before voting</li>
                                <li>Vote based on objective game results</li>
                                <li>Consider skill demonstration and fair play</li>
                                <li>Document your reasoning in notes</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Reputation Impact:</h6>
                            <ul>
                                <li>Accurate votes increase your reputation</li>
                                <li>Consistently wrong votes may affect future assignments</li>
                                <li>Fair voting ensures platform integrity</li>
                                <li>High reputation unlocks better incentives</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load participants for events that need voting
    <?php if (isset($event_groups['pending_votes'])): ?>
        <?php foreach ($event_groups['pending_votes'] as $event): ?>
            <?php if ($event['needs_vote']): ?>
                loadEventParticipants(<?php echo $event['id']; ?>);
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    
    // Add pulse animation CSS
    const style = document.createElement('style');
    style.textContent = `
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .vote-panel.border-warning {
            border: 2px solid #ffc107 !important;
            box-shadow: 0 0 10px rgba(255, 193, 7, 0.3);
        }
        .vote-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            transition: all 0.3s ease;
        }
        .vote-panel:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .stats-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            text-align: center;
            height: 100%;
        }
        .stats-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stats-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    `;
    document.head.appendChild(style);
});

function loadEventParticipants(eventId) {
    fetch(`/Bull_PVP/api/event_participants.php?event_id=${eventId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayParticipants(eventId, data.participants);
            } else {
                document.getElementById(`participants_${eventId}`).innerHTML = 
                    '<div class="alert alert-danger">Failed to load participants</div>';
            }
        })
        .catch(error => {
            console.error('Error loading participants:', error);
            document.getElementById(`participants_${eventId}`).innerHTML = 
                '<div class="alert alert-danger">Error loading participants</div>';
        });
}

function displayParticipants(eventId, participants) {
    const container = document.getElementById(`participants_${eventId}`);
    
    if (!participants || participants.length === 0) {
        container.innerHTML = '<div class="alert alert-warning">No participants found for this event.</div>';
        return;
    }
    
    let html = '<div class="row">';
    
    participants.forEach(participant => {
        html += `
            <div class="col-md-6 mb-2">
                <div class="participant-card" onclick="selectWinner(${eventId}, ${participant.user_id}, this)" 
                     style="cursor: pointer; border: 2px solid #e9ecef; transition: all 0.3s;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><i class="fas fa-user me-2"></i>${participant.username}</strong>
                            <br><small class="text-muted">Stake: $${parseFloat(participant.stake || 0).toFixed(2)}</small>
                        </div>
                        <div class="text-center">
                            <i class="fas fa-trophy text-muted winner-icon" style="display: none;"></i>
                            <i class="fas fa-hand-pointer text-primary select-icon"></i>
                            <br><small class="text-muted">Click to select as winner</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    html += '<div class="mt-2"><small class="text-muted"><i class="fas fa-lightbulb me-1"></i>Click on a participant to mark them as the winner. All other participants will be marked as losers.</small></div>';
    
    container.innerHTML = html;
}

let selectedWinners = {};

function selectWinner(eventId, userId, element) {
    // Remove previous selection for this event
    const eventContainer = document.querySelector(`[data-event-id="${eventId}"]`);
    const allCards = eventContainer.querySelectorAll('.participant-card');
    
    allCards.forEach(card => {
        card.classList.remove('selected');
        card.style.border = '2px solid #e9ecef';
        card.style.backgroundColor = '#ffffff';
        
        // Hide winner icons and show select icons
        const winnerIcon = card.querySelector('.winner-icon');
        const selectIcon = card.querySelector('.select-icon');
        if (winnerIcon) winnerIcon.style.display = 'none';
        if (selectIcon) selectIcon.style.display = 'inline';
    });
    
    // Select new winner
    element.classList.add('selected');
    element.style.border = '2px solid #28a745';
    element.style.backgroundColor = '#f8fff9';
    
    // Show winner icon, hide select icon
    const winnerIcon = element.querySelector('.winner-icon');
    const selectIcon = element.querySelector('.select-icon');
    if (winnerIcon) {
        winnerIcon.style.display = 'inline';
        winnerIcon.className = 'fas fa-trophy text-success winner-icon';
    }
    if (selectIcon) selectIcon.style.display = 'none';
    
    // Update text to show winner/loser status
    const usernameElement = element.querySelector('strong');
    if (usernameElement && !usernameElement.innerHTML.includes('WINNER')) {
        usernameElement.innerHTML = usernameElement.innerHTML.replace('</i>', '</i>👑 ') + ' <span class="badge bg-success">WINNER</span>';
    }
    
    selectedWinners[eventId] = userId;
    
    // Enable submit button
    const submitBtn = document.querySelector(`[data-event-id="${eventId}"].submit-vote-btn`);
    submitBtn.disabled = false;
}

// Handle vote submission
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('submit-vote-btn')) {
        const eventId = e.target.dataset.eventId;
        const winnerId = selectedWinners[eventId];
        const notes = document.getElementById(`vote_notes_${eventId}`).value;
        
        if (!winnerId) {
            showNotification('Please select a winner before submitting your vote', 'warning');
            return;
        }
        
        if (!confirm('Are you sure you want to submit this vote? This action cannot be undone.')) {
            return;
        }
        
        submitVote(eventId, winnerId, notes);
    }
});

function submitVote(eventId, winnerId, notes) {
    showLoading();
    
    fetch('/Bull_PVP/api/submit_vote.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            event_id: eventId, 
            winner_id: winnerId,
            notes: notes
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showNotification('Vote submitted successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to submit vote', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('An error occurred while submitting vote', 'error');
        console.error('Error:', error);
    });
}

// Streaming control functions
function startEvent(eventId) {
    if (!confirm('Are you sure you want to start this event? This will begin the live battle.')) {
        return;
    }
    
    showLoading();
    
    fetch('/Bull_PVP/api/start_event.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ event_id: eventId })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showNotification('Event started successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to start event', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('An error occurred while starting event', 'error');
        console.error('Error:', error);
    });
}

function startStreaming(eventId) {
    showNotification('Stream started! You can now cast this battle live.', 'success');
    // In a real implementation, this would integrate with streaming software
    // For now, just provide feedback to the streamer
}

function endEvent(eventId) {
    if (!confirm('Are you sure you want to end this event? This will stop the battle and prepare it for voting.')) {
        return;
    }
    
    showLoading();
    
    fetch('/Bull_PVP/api/end_event.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ event_id: eventId })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showNotification('Event ended successfully! Ready for voting.', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to end event', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('An error occurred while ending event', 'error');
        console.error('Error:', error);
    });
}

// Utility functions for notifications and loading
function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'warning'} alert-dismissible fade show`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

function showLoading() {
    if (!document.getElementById('loadingOverlay')) {
        const overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        `;
        overlay.innerHTML = '<div class="spinner-border text-light" role="status"><span class="visually-hidden">Loading...</span></div>';
        document.body.appendChild(overlay);
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>