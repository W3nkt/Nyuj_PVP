<?php
$page_title = 'Events - Bull PVP Platform';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';
require_once '../config/AuditChain.php';

requireLogin();

$db = new Database();
$conn = $db->connect();
$user_id = getUserId();

// Get user balance from audit chain
$balance = 0;
try {
    $auditChain = new AuditChain();
    $balance = $auditChain->getUserBalance($user_id);
    error_log("Events.php: Successfully got audit chain balance for user $user_id: $balance");
} catch (Exception $e) {
    error_log('Events.php: Audit chain balance error for user ' . $user_id . ': ' . $e->getMessage());
    // Fallback to wallet table if audit chain fails
    try {
        $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $balance = $result ? $result['balance'] : 0;
        error_log("Events.php: Using fallback wallet balance for user $user_id: $balance");
    } catch (Exception $e2) {
        error_log('Events.php: Fallback balance error for user ' . $user_id . ': ' . $e2->getMessage());
    }
}

// Get events
$events = [];
try {
    $stmt = $conn->prepare("SELECT * FROM events ORDER BY created_at DESC");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log('Found ' . count($events) . ' events');
} catch (Exception $e) {
    error_log('Events error: ' . $e->getMessage());
    $events = [];
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-trophy me-2"></i>Events</h2>
                    <p class="text-muted mb-0">Browse and bet on live competitive events</p>
                </div>
                <div class="text-end">
                    <div class="badge bg-primary fs-6">
                        <?php echo count($events); ?> Active Events
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Wallet Balance Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="card-title mb-2">
                                <i class="fas fa-wallet me-2 text-primary"></i>Your Wallet Balance
                            </h5>
                            <div class="display-6 text-primary">$<?php echo number_format($balance, 2); ?></div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <button class="btn btn-primary me-2" onclick="showDepositModal()">
                                <i class="fas fa-plus me-1"></i>Add Funds
                            </button>
                            <button class="btn btn-outline-primary" onclick="showWithdrawModal()" 
                                        <?php echo $balance <= 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-minus me-1"></i>Withdraw
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Events List -->
        <div class="col-lg-8">

            <?php if (empty($events)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted">No Events Available</h4>
                        <p class="text-muted">There are currently no betting events available. New events will appear here when they are created.</p>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-refresh me-2"></i>Refresh Page
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($events as $event): ?>
                        <div class="col-12 mb-4">
                            <div class="card h-100 shadow-sm">
                                <?php
                                    $status = $event['status'] ?? 'unknown';
                                    $header_color = 'secondary';
                                    switch($status) {
                                        case 'live': 
                                            $header_color = 'danger'; 
                                            break;
                                        case 'accepting_bets': 
                                            $header_color = 'success'; 
                                            break;
                                        case 'ready': 
                                            $header_color = 'warning'; 
                                            break;
                                        case 'event_end': 
                                            $header_color = 'info'; 
                                            break;
                                        case 'completed':
                                            $header_color = 'dark';
                                            break;
                                    }
                                ?>
                                <div class="card-header bg-<?php echo $header_color; ?> text-white">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1 text-white"><?php echo htmlspecialchars($event['name'] ?? 'Unnamed Event'); ?></h5>
                                            <?php if (!empty($event['game_type'])): ?>
                                                <p class="mb-0 text-white-50">
                                                    <i class="fas fa-gamepad me-1"></i>
                                                    <?php echo htmlspecialchars($event['game_type']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <?php
                                                $status_icon = 'fas fa-clock';
                                                switch($status) {
                                                    case 'live': 
                                                        $status_icon = 'fas fa-broadcast-tower';
                                                        break;
                                                    case 'accepting_bets': 
                                                        $status_icon = 'fas fa-dollar-sign';
                                                        break;
                                                    case 'ready': 
                                                        $status_icon = 'fas fa-hourglass-half';
                                                        break;
                                                    case 'event_end': 
                                                        $status_icon = 'fas fa-flag-checkered';
                                                        break;
                                                    case 'completed':
                                                        $status_icon = 'fas fa-trophy';
                                                        break;
                                                }
                                            ?>
                                            <div class="badge bg-light text-dark fs-6 p-2">
                                                <i class="<?php echo $status_icon; ?> me-1"></i>
                                                <?php echo strtoupper(str_replace('_', ' ', $status)); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    <!-- Match Start Time Info -->
                                    <?php 
                                    $start_time = '';
                                    if (!empty($event['match_start_time_1'])) {
                                        $start_time = $event['match_start_time_1'];
                                    } elseif (!empty($event['event_start_time'])) {
                                        $start_time = $event['event_start_time'];
                                    }
                                    
                                    if ($start_time): ?>
                                        <div class="text-end mb-3">
                                            <div class="text-muted small">
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                <?php echo date('M j, Y g:i A', strtotime($start_time)); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Competitors Section -->
                                    <div class="row mb-4">
                                        <!-- Competitor A Card -->
                                        <div class="col-md-6">
                                            <div class="competitor-card competitor-a-card" onclick="selectCompetitor(<?php echo $event['id']; ?>, 'A')">
                                                <div class="competitor-card-body">
                                                    <?php if (!empty($event['competitor_a_image'])): ?>
                                                        <div class="competitor-img-rect" style="background-image: url('<?php echo htmlspecialchars('../' . $event['competitor_a_image']); ?>');">
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="competitor-img-rect competitor-placeholder-rect">
                                                            <i class="fas fa-user fa-3x text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="competitor-info">
                                                        <h6 class="competitor-name mb-1"><?php echo htmlspecialchars($event['competitor_a'] ?? 'TBD'); ?></h6>
                                                        <small class="competitor-label">Competitor A</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Competitor B Card -->
                                        <div class="col-md-6">
                                            <div class="competitor-card competitor-b-card" onclick="selectCompetitor(<?php echo $event['id']; ?>, 'B')">
                                                <div class="competitor-card-body">
                                                    <?php if (!empty($event['competitor_b_image'])): ?>
                                                        <div class="competitor-img-rect" style="background-image: url('<?php echo htmlspecialchars('../' . $event['competitor_b_image']); ?>');">
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="competitor-img-rect competitor-placeholder-rect">
                                                            <i class="fas fa-user fa-3x text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="competitor-info">
                                                        <h6 class="competitor-name mb-1"><?php echo htmlspecialchars($event['competitor_b'] ?? 'TBD'); ?></h6>
                                                        <small class="competitor-label">Competitor B</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Event Details & Betting Section -->
                                    <div class="row">
                                        <div class="col-md-6">
                                            <!-- Stake Amount -->
                                            <?php if (!empty($event['stake_amount']) && $event['stake_amount'] > 0): ?>
                                                <div class="card border-success mb-3">
                                                    <div class="card-body text-center py-3">
                                                        <h6 class="text-success mb-1">
                                                            <i class="fas fa-coins me-2"></i>Prize Pool
                                                        </h6>
                                                        <h4 class="text-success mb-0">$<?php echo number_format($event['stake_amount'], 2); ?></h4>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                    </div>

                                    <!-- Event Footer -->
                                    <hr class="my-4">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <small class="text-muted">
                                                <i class="fas fa-plus-circle me-1"></i><strong>Created:</strong><br>
                                                <?php echo !empty($event['created_at']) ? date('M j, Y g:i A', strtotime($event['created_at'])) : 'Unknown'; ?>
                                            </small>
                                        </div>
                                        <div class="col-md-6 text-md-end">
                                            <small class="text-muted">
                                                <i class="fas fa-play-circle me-1"></i><strong>Start at:</strong><br>
                                                <?php echo !empty($event['match_start_time']) ? date('M j, Y g:i A', strtotime($event['match_start_time'])) : 'Unknown'; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Stats -->
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <div class="display-6 text-primary"><?php echo count($events); ?></div>
                            <div class="text-muted small">Total Events</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <div class="display-6 text-success">
                                <?php 
                                $accepting_bets = 0;
                                foreach ($events as $event) {
                                    if (($event['status'] ?? '') === 'accepting_bets') {
                                        $accepting_bets++;
                                    }
                                }
                                echo $accepting_bets;
                                ?>
                            </div>
                            <div class="text-muted small">Open for Bets</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- How to Bet Guide -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>How to Place Bets</h6>
                </div>
                <div class="card-body">
                    <div class="step-guide">
                        <div class="step-item d-flex align-items-center mb-3">
                            <div class="step-number">1</div>
                            <div class="step-text">
                                Find an event with <span class="badge bg-success">ACCEPTING BETS</span> status
                            </div>
                        </div>
                        <div class="step-item d-flex align-items-center mb-3">
                            <div class="step-number">2</div>
                            <div class="step-text">Choose your competitor and click their bet button</div>
                        </div>
                        <div class="step-item d-flex align-items-center mb-3">
                            <div class="step-number">3</div>
                            <div class="step-text">Enter your bet amount when prompted</div>
                        </div>
                        <div class="step-item d-flex align-items-center mb-3">
                            <div class="step-number">4</div>
                            <div class="step-text">Confirm your bet and wait for results</div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Bet Limits:</strong> $1.00 - $10,000.00
                        </small>
                    </div>
                </div>
            </div>

            <!-- Platform Rules -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Platform Rules</h6>
                </div>
                <div class="card-body">
                    <div class="rule-list">
                        <div class="rule-item mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <small>All transactions are blockchain-verified</small>
                        </div>
                        <div class="rule-item mb-2">
                            <i class="fas fa-users text-primary me-2"></i>
                            <small>Bets are matched with other users</small>
                        </div>
                        <div class="rule-item mb-2">
                            <i class="fas fa-trophy text-warning me-2"></i>
                            <small>Winner takes the pot (minus platform fees)</small>
                        </div>
                        <div class="rule-item mb-2">
                            <i class="fas fa-clock text-info me-2"></i>
                            <small>Betting closes before match start</small>
                        </div>
                        <div class="rule-item mb-0">
                            <i class="fas fa-gavel text-danger me-2"></i>
                            <small>Disputes resolved by admins</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Competitor Details Modal -->
<div class="modal fade" id="competitorModal" tabindex="-1" aria-labelledby="competitorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" id="modalHeader">
                <div class="d-flex align-items-center">
                    <div class="modal-competitor-img me-3" id="modalCompetitorImg">
                        <i class="fas fa-user fa-2x text-muted"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-1" id="competitorModalLabel">Competitor Details</h5>
                        <small class="text-muted" id="modalCompetitorType">Competitor A</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Competitor Information -->
                    <div class="col-md-7">
                        <!-- Large Competitor Image -->
                        <div class="competitor-large-image mb-4" id="competitorLargeImage">
                            <div class="competitor-large-img-placeholder">
                                <i class="fas fa-user fa-4x text-muted"></i>
                            </div>
                        </div>
                        
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-info-circle me-2"></i>Competitor Information
                        </h6>
                        <div class="competitor-stats">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="stat-card">
                                        <div class="stat-label">Height</div>
                                        <div class="stat-value" id="modalHeight">6'2"</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-card">
                                        <div class="stat-label">Weight</div>
                                        <div class="stat-value" id="modalWeight">185 lbs</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-card">
                                        <div class="stat-label">Win Rate</div>
                                        <div class="stat-value text-success" id="modalWinRate">78%</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-card">
                                        <div class="stat-label">Total Fights</div>
                                        <div class="stat-value" id="modalTotalFights">24</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h6 class="text-info mb-3">
                            <i class="fas fa-history me-2"></i>Recent History
                        </h6>
                        <div class="history-list" id="modalHistory">
                            <div class="history-item">
                                <div class="history-result win">W</div>
                                <div class="history-details">
                                    <div class="history-opponent">vs John Smith</div>
                                    <div class="history-date">Dec 15, 2024</div>
                                </div>
                            </div>
                            <div class="history-item">
                                <div class="history-result win">W</div>
                                <div class="history-details">
                                    <div class="history-opponent">vs Mike Johnson</div>
                                    <div class="history-date">Nov 28, 2024</div>
                                </div>
                            </div>
                            <div class="history-item">
                                <div class="history-result loss">L</div>
                                <div class="history-details">
                                    <div class="history-opponent">vs Alex Brown</div>
                                    <div class="history-date">Nov 10, 2024</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Betting Information & Form -->
                    <div class="col-md-5">
                        <div class="betting-section">
                            <h6 class="text-warning mb-3">
                                <i class="fas fa-chart-line me-2"></i>Betting Stats
                            </h6>
                            
                            <div class="betting-stats mb-4">
                                <div class="bet-stat-item">
                                    <div class="bet-stat-label">Total Bets Placed</div>
                                    <div class="bet-stat-value" id="modalTotalBets">147</div>
                                </div>
                                <div class="bet-stat-item">
                                    <div class="bet-stat-label">Total Amount Bet</div>
                                    <div class="bet-stat-value text-success" id="modalTotalAmount">$12,450</div>
                                </div>
                                <div class="bet-stat-item">
                                    <div class="bet-stat-label">Average Bet</div>
                                    <div class="bet-stat-value" id="modalAvgBet">$84.70</div>
                                </div>
                                <div class="bet-stat-item">
                                    <div class="bet-stat-label">Current Odds</div>
                                    <div class="bet-stat-value text-primary" id="modalOdds">2.1:1</div>
                                </div>
                            </div>
                            
                            <!-- Betting Status Info -->
                            <div class="betting-status-info mb-3" id="bettingStatusInfo">
                                <div class="alert alert-info">
                                    <i class="fas fa-clock me-2"></i>
                                    <span id="bettingStatusMessage">Loading betting status...</span>
                                </div>
                            </div>
                            
                            <!-- Betting Form -->
                            <div class="betting-form" id="bettingForm">
                                <div class="card border-2" id="bettingCard">
                                    <div class="card-header text-white" id="bettingHeader">
                                        <h6 class="mb-0">
                                            <i class="fas fa-dollar-sign me-2"></i>Place Your Bet
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="betAmount" class="form-label">Bet Amount</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="betAmount" placeholder="0.00" min="1" max="10000" step="0.01">
                                            </div>
                                            <small class="form-text text-muted">Min: $1.00 | Max: $10,000.00</small>
                                        </div>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between">
                                                <span>Your Balance:</span>
                                                <strong class="text-success">$<?php echo number_format($balance, 2); ?></strong>
                                            </div>
                                        </div>
                                        <div class="d-grid">
                                            <button type="button" class="btn btn-lg" id="placeBetBtn" onclick="confirmBet()">
                                                <i class="fas fa-check me-2"></i>Confirm Bet
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Not Available Message -->
                            <div class="betting-unavailable d-none" id="bettingUnavailable">
                                <div class="alert alert-warning text-center">
                                    <i class="fas fa-lock fa-2x mb-2"></i>
                                    <h6 id="bettingUnavailableTitle">Betting Not Available</h6>
                                    <p class="mb-0" id="bettingUnavailableMessage">This event is not currently accepting bets.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
                        <input type="number" class="form-control" id="depositAmount" min="1" step="0.01" required>
                        <small class="form-text text-muted">Minimum deposit: $1.00</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quick Amounts</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAmount(25)">$25</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAmount(50)">$50</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAmount(100)">$100</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAmount(250)">$250</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAmount(500)">$500</button>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This is a demo. In production, this would integrate with a real payment processor.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-lime" onclick="processDeposit()">
                    <i class="fas fa-credit-card me-2"></i>Add Funds
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Withdraw Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-minus me-2 text-warning"></i>Withdraw Funds
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="withdrawForm">
                    <div class="mb-3">
                        <label for="withdrawAmount" class="form-label">Withdrawal Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="withdrawAmount" 
                                   min="1" max="<?php echo $balance; ?>" step="0.01" placeholder="0.00" required>
                        </div>
                        <div class="form-text">
                            Available balance: $<?php echo number_format($balance, 2); ?> • Minimum: $1.00
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quick Amounts</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($balance >= 25): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setWithdrawAmount(25)">$25</button>
                            <?php endif; ?>
                            <?php if ($balance >= 50): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setWithdrawAmount(50)">$50</button>
                            <?php endif; ?>
                            <?php if ($balance >= 100): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setWithdrawAmount(100)">$100</button>
                            <?php endif; ?>
                            <?php if ($balance > 0): ?>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setWithdrawAmount(<?php echo $balance; ?>)">All ($<?php echo number_format($balance, 2); ?>)</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-clock me-2"></i>
                        <strong>Processing Time:</strong> Withdrawals are typically processed within 1-3 business days.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="processWithdraw()">
                    <i class="fas fa-money-bill me-2"></i>Withdraw Now
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showDepositModal() {
    new bootstrap.Modal(document.getElementById('depositModal')).show();
}

function showWithdrawModal() {
    new bootstrap.Modal(document.getElementById('withdrawModal')).show();
}

function setAmount(amount) {
    document.getElementById('depositAmount').value = amount;
}

function setWithdrawAmount(amount) {
    document.getElementById('withdrawAmount').value = amount;
}

function processDeposit() {
    const amountInput = document.getElementById('depositAmount');
    const amount = parseFloat(amountInput.value);
    
    if (!amount || amount < 1) {
        showNotification('Please enter a valid amount (minimum $1.00)', 'warning');
        return;
    }
    
    if (amount > 10000) {
        showNotification('Maximum deposit amount is $10,000.00', 'warning');
        return;
    }
    
    showLoading();
    
    fetch(getBasePath() + '/api/deposit.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ amount: amount })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showNotification('Deposit successful!', 'success');
            updateWalletBalance();
            bootstrap.Modal.getInstance(document.getElementById('depositModal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || 'Deposit failed', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('An error occurred during deposit', 'error');
        console.error('Error:', error);
    });
}

async function processWithdraw() {
    const amount = parseFloat(document.getElementById('withdrawAmount').value);
    
    if (!amount || amount < 1) {
        alert('Please enter a valid amount (minimum $1.00)');
        return;
    }
    
    if (amount > <?php echo $balance; ?>) {
        alert('Insufficient balance. Available: $<?php echo number_format($balance, 2); ?>');
        return;
    }
    
    if (!confirm('Are you sure you want to withdraw $' + amount.toFixed(2) + '?')) {
        return;
    }
    
    try {
        const response = await fetch('../api/withdraw.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ amount: amount })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Withdrawal request submitted! $' + amount.toFixed(2) + ' will be processed within 1-3 business days.');
            location.reload();
        } else {
            alert('Withdrawal failed: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        alert('Network error. Please try again.');
        console.error('Withdraw error:', error);
    }
}

let currentEventId = null;
let currentCompetitor = null;

function selectCompetitor(eventId, competitor) {
    console.log('selectCompetitor called:', eventId, competitor);
    
    currentEventId = eventId;
    currentCompetitor = competitor;
    
    // Find all cards and locate the one containing this event
    const eventCards = document.querySelectorAll('.card');
    let eventCard = null;
    let competitorName = '';
    let competitorImg = null;
    
    // Find the correct event card by looking for the onclick attribute with matching eventId
    eventCards.forEach(card => {
        const competitorACard = card.querySelector('.competitor-a-card');
        const competitorBCard = card.querySelector('.competitor-b-card');
        
        if (competitorACard || competitorBCard) {
            // Check if this card has the matching eventId in its onclick attributes
            if ((competitorACard && competitorACard.getAttribute('onclick') && competitorACard.getAttribute('onclick').includes(`selectCompetitor(${eventId}`)) ||
                (competitorBCard && competitorBCard.getAttribute('onclick') && competitorBCard.getAttribute('onclick').includes(`selectCompetitor(${eventId}`))) {
                eventCard = card;
            }
        }
    });
    
    if (eventCard) {
        // Get competitor name and image from the correct card
        if (competitor === 'A') {
            const nameElement = eventCard.querySelector('.competitor-a-card .competitor-name');
            const imgElement = eventCard.querySelector('.competitor-a-card .competitor-img-rect');
            competitorName = nameElement ? nameElement.textContent : 'Unknown';
            competitorImg = imgElement;
        } else {
            const nameElement = eventCard.querySelector('.competitor-b-card .competitor-name');
            const imgElement = eventCard.querySelector('.competitor-b-card .competitor-img-rect');
            competitorName = nameElement ? nameElement.textContent : 'Unknown';
            competitorImg = imgElement;
        }
        
        console.log('Found competitor:', competitorName, competitor);
        
        // Check current event status and show modal
        checkEventStatusAndShowModal(competitorName, competitor, competitorImg);
    } else {
        console.error('Could not find event card for eventId:', eventId);
        // Fallback - just show modal with basic info
        checkEventStatusAndShowModal('Unknown Competitor', competitor, null);
    }
}

function checkEventStatusAndShowModal(competitorName, competitor, competitorImg) {
    // First check the current event status from database
    fetch(`/Bull_PVP/api/get_event_status.php?event_id=${currentEventId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const isBettingAvailable = data.status === 'accepting_bets';
                
                // Update the event card status if it has changed
                updateEventCardStatus(data.status, data.time_until_betting_closes);
                
                // Populate modal with competitor data
                populateModal(competitorName, competitor, competitorImg, isBettingAvailable, data);
            } else {
                // Fall back to default betting availability
                populateModal(competitorName, competitor, competitorImg, true);
            }
        })
        .catch(error => {
            console.error('Error checking event status:', error);
            // Fall back to default betting availability
            populateModal(competitorName, competitor, competitorImg, true);
        })
        .finally(() => {
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('competitorModal'));
            modal.show();
        });
}

function updateEventCardStatus(newStatus, timeUntilClose) {
    // Find the event card by eventId and update its status
    const eventCards = document.querySelectorAll('.card');
    let eventCard = null;
    
    // Find the correct event card by looking for the onclick attribute with matching eventId
    eventCards.forEach(card => {
        const competitorACard = card.querySelector('.competitor-a-card');
        const competitorBCard = card.querySelector('.competitor-b-card');
        
        if (competitorACard || competitorBCard) {
            // Check if this card has the matching eventId in its onclick attributes
            if ((competitorACard && competitorACard.getAttribute('onclick') && competitorACard.getAttribute('onclick').includes(`selectCompetitor(${currentEventId}`)) ||
                (competitorBCard && competitorBCard.getAttribute('onclick') && competitorBCard.getAttribute('onclick').includes(`selectCompetitor(${currentEventId}`))) {
                eventCard = card;
            }
        }
    });
    
    if (!eventCard) return; // Exit if card not found
    
    const statusBadge = eventCard.querySelector('.badge');
    
    if (statusBadge) {
        // Update status badge
        const statusColors = {
            'accepting_bets': 'success',
            'betting_closed': 'warning', 
            'live': 'danger',
            'ready': 'info',
            'completed': 'secondary'
        };
        
        const statusIcons = {
            'accepting_bets': 'fas fa-dollar-sign',
            'betting_closed': 'fas fa-lock',
            'live': 'fas fa-broadcast-tower',
            'ready': 'fas fa-hourglass-half',
            'completed': 'fas fa-flag-checkered'
        };
        
        statusBadge.className = `badge bg-${statusColors[newStatus] || 'secondary'} fs-6 p-2 mb-2`;
        statusBadge.innerHTML = `<i class="${statusIcons[newStatus] || 'fas fa-clock'} me-1"></i>${newStatus.toUpperCase().replace('_', ' ')}`;
        
        // Hide/show betting buttons based on status
        const bettingButtons = eventCard.querySelector('.betting-buttons');
        const bettingUnavailable = eventCard.querySelector('.card.border-secondary');
        
        if (newStatus === 'accepting_bets') {
            if (bettingButtons) bettingButtons.style.display = 'flex';
            if (bettingUnavailable) bettingUnavailable.style.display = 'none';
        } else {
            if (bettingButtons) bettingButtons.style.display = 'none';
            if (bettingUnavailable) bettingUnavailable.style.display = 'block';
        }
    }
}

function populateModal(name, competitor, imgElement, bettingAvailable, eventStatusData = null) {
    console.log('populateModal called:', name, competitor, bettingAvailable);
    
    // Set initial competitor name and type (will be updated when data is fetched)
    document.getElementById('competitorModalLabel').textContent = name || 'Loading...';
    document.getElementById('modalCompetitorType').textContent = `Competitor ${competitor}`;
    
    // Set competitor images (both small and large) - initial state
    setCompetitorImages(imgElement);
    
    // Set header color based on competitor
    setModalColors(competitor);
    
    // Update betting status information
    updateBettingStatus(eventStatusData, bettingAvailable);
    
    // Show/hide betting form based on availability
    const bettingForm = document.getElementById('bettingForm');
    const bettingUnavailable = document.getElementById('bettingUnavailable');
    const bettingStatusInfo = document.getElementById('bettingStatusInfo');
    
    if (bettingAvailable) {
        bettingForm.classList.remove('d-none');
        bettingUnavailable.classList.add('d-none');
        bettingStatusInfo.classList.remove('d-none');
    } else {
        bettingForm.classList.add('d-none');
        bettingUnavailable.classList.remove('d-none');
        bettingStatusInfo.classList.add('d-none');
        
        // Update unavailable message based on status
        updateUnavailableMessage(eventStatusData);
    }
    
    // Fetch real data from database and update modal
    fetchCompetitorData(currentEventId, competitor);
    
    // Clear bet amount input
    document.getElementById('betAmount').value = '';
}

function updateBettingStatus(eventStatusData, bettingAvailable) {
    const statusMessage = document.getElementById('bettingStatusMessage');
    
    if (eventStatusData && eventStatusData.betting_status_message) {
        statusMessage.textContent = eventStatusData.betting_status_message;
        
        // Change alert color based on time remaining
        const alertDiv = statusMessage.parentElement;
        if (eventStatusData.time_until_betting_closes < 3600) { // Less than 1 hour
            alertDiv.className = 'alert alert-warning';
        } else if (eventStatusData.time_until_betting_closes < 1800) { // Less than 30 minutes
            alertDiv.className = 'alert alert-danger';
        } else {
            alertDiv.className = 'alert alert-info';
        }
    } else if (bettingAvailable) {
        statusMessage.textContent = 'Betting is currently open for this event';
    } else {
        statusMessage.textContent = 'Betting status unknown';
    }
}

function updateUnavailableMessage(eventStatusData) {
    const title = document.getElementById('bettingUnavailableTitle');
    const message = document.getElementById('bettingUnavailableMessage');
    
    if (eventStatusData) {
        switch (eventStatusData.status) {
            case 'betting_closed':
                title.textContent = 'Betting Closed';
                message.textContent = 'Betting has closed for this event. The match will start soon.';
                break;
            case 'live':
                title.textContent = 'Match in Progress';
                message.textContent = 'This match is currently live. Betting is no longer available.';
                break;
            case 'completed':
                title.textContent = 'Match Completed';
                message.textContent = 'This match has been completed. Results should be available.';
                break;
            default:
                title.textContent = 'Betting Not Available';
                message.textContent = 'This event is not currently accepting bets.';
        }
        
        // Add match status message if available
        if (eventStatusData.match_status_message) {
            message.textContent += ` ${eventStatusData.match_status_message}`;
        }
    } else {
        title.textContent = 'Betting Not Available';
        message.textContent = 'This event is not currently accepting bets.';
    }
}

function setCompetitorImages(imgElement) {
    const modalImg = document.getElementById('modalCompetitorImg');
    const largeImg = document.getElementById('competitorLargeImage');
    
    if (imgElement && imgElement.style.backgroundImage) {
        // Small header image
        modalImg.innerHTML = '';
        modalImg.style.backgroundImage = imgElement.style.backgroundImage;
        modalImg.style.backgroundSize = 'cover';
        modalImg.style.backgroundPosition = 'center';
        
        // Large image in content area
        largeImg.innerHTML = '';
        largeImg.style.backgroundImage = imgElement.style.backgroundImage;
        largeImg.style.backgroundSize = 'cover';
        largeImg.style.backgroundPosition = 'center';
    } else {
        // Default placeholder images
        modalImg.innerHTML = '<i class="fas fa-user fa-2x text-muted"></i>';
        modalImg.style.backgroundImage = 'none';
        
        largeImg.innerHTML = '<div class="competitor-large-img-placeholder"><i class="fas fa-user fa-4x text-muted"></i></div>';
        largeImg.style.backgroundImage = 'none';
    }
}

function updateCompetitorImagesFromData(imagePath) {
    const modalImg = document.getElementById('modalCompetitorImg');
    const largeImg = document.getElementById('competitorLargeImage');
    
    if (imagePath) {
        const imageUrl = `url('../${imagePath}')`;
        
        // Update small header image
        modalImg.innerHTML = '';
        modalImg.style.backgroundImage = imageUrl;
        modalImg.style.backgroundSize = 'cover';
        modalImg.style.backgroundPosition = 'center';
        modalImg.style.backgroundRepeat = 'no-repeat';
        
        // Update large image in content area
        largeImg.innerHTML = '';
        largeImg.style.backgroundImage = imageUrl;
        largeImg.style.backgroundSize = 'cover';
        largeImg.style.backgroundPosition = 'center';
        largeImg.style.backgroundRepeat = 'no-repeat';
    } else {
        // Default placeholder images if no image path
        modalImg.innerHTML = '<i class="fas fa-user fa-2x text-muted"></i>';
        modalImg.style.backgroundImage = 'none';
        
        largeImg.innerHTML = '<div class="competitor-large-img-placeholder"><i class="fas fa-user fa-4x text-muted"></i></div>';
        largeImg.style.backgroundImage = 'none';
    }
}

function setModalColors(competitor) {
    console.log('setModalColors called for competitor:', competitor);
    
    const modalHeader = document.getElementById('modalHeader');
    const bettingHeader = document.getElementById('bettingHeader');
    const bettingCard = document.getElementById('bettingCard');
    const placeBetBtn = document.getElementById('placeBetBtn');
    
    console.log('Modal elements found:', {
        modalHeader: !!modalHeader,
        bettingHeader: !!bettingHeader,
        bettingCard: !!bettingCard,
        placeBetBtn: !!placeBetBtn
    });
    
    if (competitor === 'A') {
        if (modalHeader) modalHeader.className = 'modal-header bg-primary text-dark';
        if (bettingHeader) bettingHeader.className = 'card-header text-white bg-primary';
        if (bettingCard) bettingCard.className = 'card border-2 border-primary';
        if (placeBetBtn) placeBetBtn.className = 'btn btn-primary btn-lg';
    } else {
        if (modalHeader) modalHeader.className = 'modal-header bg-success text-dark';
        if (bettingHeader) bettingHeader.className = 'card-header text-white bg-success';
        if (bettingCard) bettingCard.className = 'card border-2 border-success';
        if (placeBetBtn) placeBetBtn.className = 'btn btn-success btn-lg';
    }
}

function fetchCompetitorData(eventId, competitor) {
    console.log('fetchCompetitorData called:', eventId, competitor);
    
    // Show loading state
    showLoadingState();
    
    const apiUrl = `/Bull_PVP/api/get_competitor_data.php?event_id=${eventId}&competitor=${competitor}`;
    console.log('Fetching from:', apiUrl);
    
    fetch(apiUrl)
        .then(response => {
            console.log('API response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('API response data:', data);
            if (data.success) {
                populateWithRealData(data.data);
            } else {
                console.error('Error fetching competitor data:', data.message);
                // Fall back to sample data
                populateCompetitorStats('Unknown', competitor);
            }
        })
        .catch(error => {
            console.error('Error fetching competitor data:', error);
            // Fall back to sample data
            populateCompetitorStats('Unknown', competitor);
        })
        .finally(() => {
            hideLoadingState();
        });
}

function showLoadingState() {
    // Add loading spinners to various sections
    document.getElementById('modalHeight').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    document.getElementById('modalWeight').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    document.getElementById('modalWinRate').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    document.getElementById('modalTotalFights').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    document.getElementById('modalTotalBets').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    document.getElementById('modalTotalAmount').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    document.getElementById('modalAvgBet').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    document.getElementById('modalOdds').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    document.getElementById('modalHistory').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i></div>';
}

function hideLoadingState() {
    // Loading state will be replaced by actual data
}

function populateWithRealData(data) {
    console.log('populateWithRealData called with:', data);
    
    // Update modal header with actual competitor name from database
    document.getElementById('competitorModalLabel').textContent = data.name || 'Unknown Competitor';
    document.getElementById('modalCompetitorType').textContent = `Competitor ${data.competitor_type}`;
    
    // Update competitor images with actual data from database
    updateCompetitorImagesFromData(data.image);
    
    // Populate competitor information
    document.getElementById('modalHeight').textContent = data.competitor_info.height;
    document.getElementById('modalWeight').textContent = data.competitor_info.weight;
    document.getElementById('modalWinRate').textContent = data.competitor_info.win_rate;
    document.getElementById('modalTotalFights').textContent = data.competitor_info.total_fights;
    
    // Populate betting statistics
    document.getElementById('modalTotalBets').textContent = data.betting_stats.total_bets;
    document.getElementById('modalTotalAmount').textContent = '$' + data.betting_stats.total_amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('modalAvgBet').textContent = data.betting_stats.avg_bet > 0 ? 
        '$' + data.betting_stats.avg_bet.toFixed(2) : '$0.00';
    document.getElementById('modalOdds').textContent = data.betting_stats.odds;
    
    // Populate match history
    let historyHTML = '';
    if (data.competitor_info.history && data.competitor_info.history.length > 0) {
        data.competitor_info.history.forEach(match => {
            const resultClass = match.result === 'W' ? 'win' : 'loss';
            historyHTML += `
                <div class="history-item">
                    <div class="history-result ${resultClass}">${match.result}</div>
                    <div class="history-details">
                        <div class="history-opponent">vs ${match.opponent}</div>
                        <div class="history-date">${match.date}</div>
                    </div>
                </div>
            `;
        });
    } else {
        historyHTML = '<div class="text-center text-muted"><i class="fas fa-info-circle me-2"></i>No recent match history available</div>';
    }
    document.getElementById('modalHistory').innerHTML = historyHTML;
}

function populateCompetitorStats(name, competitor) {
    // Update modal header with the name (fallback when database fails)
    if (name && name !== 'Unknown') {
        document.getElementById('competitorModalLabel').textContent = name;
        document.getElementById('modalCompetitorType').textContent = `Competitor ${competitor}`;
    }
    
    // Generate sample stats (in real app, fetch from database)
    const sampleStats = {
        height: competitor === 'A' ? "6'2\"" : "5'11\"",
        weight: competitor === 'A' ? "185 lbs" : "175 lbs",
        winRate: competitor === 'A' ? "78%" : "82%",
        totalFights: competitor === 'A' ? "24" : "31",
        totalBets: Math.floor(Math.random() * 200) + 50,
        totalAmount: '$' + (Math.floor(Math.random() * 15000) + 5000).toLocaleString(),
        avgBet: '$' + (Math.random() * 100 + 20).toFixed(2),
        odds: (Math.random() * 2 + 1.2).toFixed(1) + ':1'
    };
    
    document.getElementById('modalHeight').textContent = sampleStats.height;
    document.getElementById('modalWeight').textContent = sampleStats.weight;
    document.getElementById('modalWinRate').textContent = sampleStats.winRate;
    document.getElementById('modalTotalFights').textContent = sampleStats.totalFights;
    document.getElementById('modalTotalBets').textContent = sampleStats.totalBets;
    document.getElementById('modalTotalAmount').textContent = sampleStats.totalAmount;
    document.getElementById('modalAvgBet').textContent = sampleStats.avgBet;
    document.getElementById('modalOdds').textContent = sampleStats.odds;
    
    // Generate sample history
    const opponents = ['John Smith', 'Mike Johnson', 'Alex Brown', 'David Wilson', 'Chris Lee'];
    const results = ['W', 'W', 'L', 'W', 'W'];
    const dates = ['Dec 15, 2024', 'Nov 28, 2024', 'Nov 10, 2024', 'Oct 22, 2024', 'Oct 5, 2024'];
    
    let historyHTML = '';
    for (let i = 0; i < 3; i++) {
        const resultClass = results[i] === 'W' ? 'win' : 'loss';
        historyHTML += `
            <div class="history-item">
                <div class="history-result ${resultClass}">${results[i]}</div>
                <div class="history-details">
                    <div class="history-opponent">vs ${opponents[i]}</div>
                    <div class="history-date">${dates[i]}</div>
                </div>
            </div>
        `;
    }
    document.getElementById('modalHistory').innerHTML = historyHTML;
}

function confirmBet() {
    const amount = document.getElementById('betAmount').value;
    
    // Debug: Check if required variables are set
    if (!currentEventId || !currentCompetitor) {
        alert("Error: Please select a competitor first");
        console.error('Missing variables:', { currentEventId, currentCompetitor });
        return;
    }
    
    if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
        alert("Please enter a valid bet amount");
        return;
    }
    
    if (parseFloat(amount) < 1) {
        alert("Minimum bet amount is $1.00");
        return;
    }
    
    if (parseFloat(amount) > 10000) {
        alert("Maximum bet amount is $10,000.00");
        return;
    }
    
    if (parseFloat(amount) > <?php echo $balance; ?>) {
        alert("Insufficient balance");
        return;
    }
    
    // Place the bet
    placeBet(currentEventId, currentCompetitor, parseFloat(amount));
}

function placeBet(eventId, competitor, amount) {
    // Debug: Log what we're sending
    console.log('Placing bet:', { eventId, competitor, amount });
    
    // Disable the button to prevent double submission
    const btn = document.getElementById('placeBetBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Placing Bet...';
    
    fetch('/Bull_PVP/api/place_bet.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            match_id: eventId,
            competitor: competitor,
            amount: amount
        })
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            alert('Bet placed successfully!');
            // Close modal and reload page
            bootstrap.Modal.getInstance(document.getElementById('competitorModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to place bet'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while placing the bet');
    })
    .finally(() => {
        // Re-enable the button
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm Bet';
    });
}
</script>

<style>
/* Card Animations */
.card {
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.shadow-sm {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
}

/* Competitor Cards */
.competitor-card {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    transition: all 0.3s ease;
    cursor: pointer;
    height: 120px;
    overflow: hidden;
}

.competitor-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.competitor-a-card {
    border-color: #0d6efd;
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.05), rgba(13, 110, 253, 0.02));
}

.competitor-a-card:hover {
    border-color: #5a95eeff;
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(13, 110, 253, 0.05));
    box-shadow: 0 8px 20px rgba(13, 110, 253, 0.15);
}

.competitor-b-card {
    border-color: #198754;
    background: linear-gradient(135deg, rgba(25, 135, 84, 0.05), rgba(25, 135, 84, 0.02));
}

.competitor-b-card:hover {
    border-color: #157347;
    background: linear-gradient(135deg, rgba(25, 135, 84, 0.1), rgba(25, 135, 84, 0.05));
    box-shadow: 0 8px 20px rgba(25, 135, 84, 0.15);
}

.competitor-card-body {
    display: flex;
    align-items: center;
    height: 100%;
    padding: 15px;
}

.competitor-img-rect {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    margin-right: 15px;
    flex-shrink: 0;
    border: 2px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.competitor-placeholder-rect {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    display: flex;
    align-items: center;
    justify-content: center;
}

.competitor-info {
    flex-grow: 1;
}

.competitor-name {
    font-weight: 600;
    color: #212529;
}

.competitor-label {
    color: #6c757d;
    font-weight: 500;
}

/* Step Guide */
.step-number {
    background: linear-gradient(135deg, #0d6efd, #0b5ed7);
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 12px;
    margin-right: 12px;
    flex-shrink: 0;
}

.step-text {
    font-size: 14px;
    line-height: 1.4;
}

.step-guide .step-item:not(:last-child) {
    position: relative;
}

.step-guide .step-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 13px;
    top: 28px;
    width: 2px;
    height: 20px;
    background: #dee2e6;
}

/* Rule Items */
.rule-item {
    display: flex;
    align-items: flex-start;
    line-height: 1.4;
}

.rule-item i {
    margin-top: 2px;
    flex-shrink: 0;
}

/* Button Enhancements */
.btn {
    transition: all 0.2s ease;
    border-radius: 8px;
    font-weight: 500;
}

.btn:hover {
    transform: translateY(-1px);
}

/* Betting Buttons */
.betting-buttons {
    display: flex;
    gap: 10px;
}

.btn-competitor-a {
    background: linear-gradient(135deg, #0d6efd, #0b5ed7);
    border: none;
    color: white;
    padding: 12px 20px;
    font-size: 14px;
    border-radius: 8px;
}

.btn-competitor-a:hover {
    background: linear-gradient(135deg, #0b5ed7, #0a58ca);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(13, 110, 253, 0.3);
}

.btn-competitor-b {
    background: linear-gradient(135deg, #198754, #157347);
    border: none;
    color: white;
    padding: 12px 20px;
    font-size: 14px;
    border-radius: 8px;
}

.btn-competitor-b:hover {
    background: linear-gradient(135deg, #157347, #146c43);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(25, 135, 84, 0.3);
}

/* Badge Enhancements */
.badge {
    font-weight: 500;
    letter-spacing: 0.5px;
}

/* Card Header Gradients */
.card-header.bg-primary {
    background: linear-gradient(135deg, #0d6efd, #0b5ed7) !important;
    border: none;
}

.card-header.bg-success {
    background: linear-gradient(135deg, #198754, #157347) !important;
    border: none;
}

.card-header.bg-danger {
    background: linear-gradient(135deg, #dc3545, #bb2d3b) !important;
    border: none;
}

.card-header.bg-warning {
    background: linear-gradient(135deg, #ffc107, #ffca2c) !important;
    border: none;
    color: #212529 !important;
}

.card-header.bg-info {
    background: linear-gradient(135deg, #0dcaf0, #3dd5f3) !important;
    border: none;
    color: #000 !important;
}

.card-header.bg-dark {
    background: linear-gradient(135deg, #212529, #343a40) !important;
    border: none;
}

.card-header.bg-secondary {
    background: linear-gradient(135deg, #6c757d, #5a6268) !important;
    border: none;
}

/* Event Card Header Enhancements */
.card-header h5 {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.card-header .badge {
    background: rgba(255, 255, 255, 0.2) !important;
    color: inherit;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.card-header.bg-warning .badge {
    background: rgba(0, 0, 0, 0.1) !important;
    color: #212529;
    border: 1px solid rgba(0, 0, 0, 0.2);
}

.card-header.bg-info .badge {
    background: rgba(0, 0, 0, 0.1) !important;
    color: #000;
    border: 1px solid rgba(0, 0, 0, 0.2);
}

/* Border Cards */
.card.border-primary {
    border-color: #0d6efd !important;
    border-width: 2px;
}

.card.border-success {
    border-color: #198754 !important;
    border-width: 2px;
}

.card.border-secondary {
    border-color: #6c757d !important;
    border-width: 2px;
}

/* Modal Styles */
.modal-competitor-img {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}

/* Large Competitor Image */
.competitor-large-image {
    width: 100%;
    height: 200px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fa;
    border: 2px solid #e9ecef;
}

.competitor-large-img-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    color: #6c757d;
}

.modal-header {
    border: none;
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.3), rgba(11, 94, 215, 0.3)) !important;
}

.modal-header.bg-primary {
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.3), rgba(11, 94, 215, 0.3)) !important;
}

.modal-header.bg-success {
    background: linear-gradient(135deg, rgba(25, 135, 84, 0.3), rgba(21, 115, 71, 0.3)) !important;
}

/* Competitor Stats Cards */
.stat-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    transition: all 0.2s ease;
}

.stat-card:hover {
    background: #e9ecef;
    transform: translateY(-1px);
}

.stat-label {
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 20px;
    font-weight: bold;
    color: #212529;
}

/* History List */
.history-list {
    max-height: 200px;
    overflow-y: auto;
}

.history-item {
    display: flex;
    align-items: center;
    padding: 12px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 8px;
    background: #fff;
    transition: all 0.2s ease;
}

.history-item:hover {
    background: #f8f9fa;
    transform: translateX(2px);
}

.history-result {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
    margin-right: 12px;
}

.history-result.win {
    background: #198754;
    color: white;
}

.history-result.loss {
    background: #dc3545;
    color: white;
}

.history-details {
    flex-grow: 1;
}

.history-opponent {
    font-weight: 600;
    color: #212529;
    font-size: 14px;
}

.history-date {
    font-size: 12px;
    color: #6c757d;
}

/* Betting Stats */
.betting-stats {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
}

.bet-stat-item {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e9ecef;
}

.bet-stat-item:last-child {
    margin-bottom: 0;
    border-bottom: none;
    padding-bottom: 0;
}

.bet-stat-label {
    font-size: 13px;
    color: #6c757d;
    font-weight: 500;
    flex-grow: 1;
}

.bet-stat-value {
    font-weight: bold;
    color: #212529;
    font-size: 14px;
}

/* Betting Form */
.betting-form .input-group-text {
    background: #e9ecef;
    border-color: #e9ecef;
    color: #495057;
    font-weight: 600;
}

.betting-form .form-control {
    border-color: #e9ecef;
    font-size: 16px;
    font-weight: 600;
}

.betting-form .form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.betting-section .card {
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.betting-section .card-header {
    border: none;
    font-weight: 600;
}

/* Modal Animations */
.modal.fade .modal-dialog {
    transform: scale(0.8);
    transition: transform 0.3s ease-in-out;
}

.modal.show .modal-dialog {
    transform: scale(1);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .competitor-card {
        height: 100px;
        margin-bottom: 15px;
    }
    
    .competitor-img-rect {
        width: 60px;
        height: 60px;
    }
    
    .competitor-card-body {
        padding: 10px;
    }
    
    .betting-buttons {
        flex-direction: column;
        gap: 8px;
    }
    
    .btn-competitor-a,
    .btn-competitor-b {
        padding: 10px 15px;
        font-size: 12px;
    }
    
    .display-6 {
        font-size: 1.5rem;
    }
    
    .competitor-name {
        font-size: 14px;
    }
    
    .competitor-label {
        font-size: 11px;
    }
    
    /* Modal responsive adjustments */
    .modal-dialog {
        margin: 10px;
    }
    
    .modal-competitor-img {
        width: 50px;
        height: 50px;
    }
    
    .stat-card {
        padding: 10px;
    }
    
    .stat-value {
        font-size: 16px;
    }
    
    .history-item {
        padding: 8px;
    }
    
    .betting-stats {
        padding: 10px;
    }
    
    .bet-stat-item {
        margin-bottom: 8px;
    }
    
    /* Large image responsive */
    .competitor-large-image {
        height: 150px;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>