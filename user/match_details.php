<?php
$page_title = 'Match Details - Bull PVP Platform';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';

requireLogin();

$db = new Database();
$conn = $db->connect();
$user_id = getUserId();

$error_message = '';
$success_message = '';
$match_id = intval($_GET['id'] ?? 0);

if ($match_id <= 0) {
    header('Location: ' . url('user/events.php'));
    exit();
}

// Handle bet placement
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'place_bet') {
    $bet_on = $_POST['bet_on'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    
    if (!in_array($bet_on, ['A', 'B'])) {
        $error_message = 'Invalid bet selection.';
    } elseif ($amount < 1) {
        $error_message = 'Minimum bet amount is $1.00.';
    } elseif ($amount > 10000) {
        $error_message = 'Maximum bet amount is $10,000.00.';
    } else {
        try {
            $conn->beginTransaction();
            
            // Check user's wallet balance
            $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $wallet = $stmt->fetch();
            
            if (!$wallet || $wallet['balance'] < $amount) {
                throw new Exception('Insufficient wallet balance.');
            }
            
            // Check if event is still accepting bets
            $stmt = $conn->prepare("SELECT status FROM events WHERE id = ?");
            $stmt->execute([$match_id]);
            $event_status = $stmt->fetchColumn();
            
            if ($event_status !== 'accepting_bets') {
                throw new Exception('This match is no longer accepting bets.');
            }
            
            // Note: Users can now place multiple bets on the same match/competitor
            // Removed the check that prevented multiple bets
            
            // Deduct amount from wallet
            $stmt = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
            $stmt->execute([$amount, $user_id]);
            
            // Place bet
            $stmt = $conn->prepare("
                INSERT INTO bets (event_id, user_id, bet_on, amount, status, placed_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$match_id, $user_id, $bet_on, $amount]);
            
            $bet_id = $conn->lastInsertId();
            
            // Try to match with opposing bets
            $opposing_bet_on = $bet_on === 'A' ? 'B' : 'A';
            $stmt = $conn->prepare("
                SELECT id FROM bets 
                WHERE event_id = ? AND bet_on = ? AND amount = ? AND status = 'pending' AND user_id != ?
                ORDER BY placed_at ASC 
                LIMIT 1
            ");
            $stmt->execute([$match_id, $opposing_bet_on, $amount, $user_id]);
            $opposing_bet = $stmt->fetch();
            
            if ($opposing_bet) {
                // Match found - update both bets
                $stmt = $conn->prepare("UPDATE bets SET status = 'matched', matched_at = NOW() WHERE id IN (?, ?)");
                $stmt->execute([$bet_id, $opposing_bet['id']]);
                
                $success_message = "Bet placed and matched successfully! You bet $" . number_format($amount, 2) . " on Competitor " . $bet_on . ".";
            } else {
                $success_message = "Bet placed successfully! You bet $" . number_format($amount, 2) . " on Competitor " . $bet_on . ". Waiting for a matching bet.";
            }
            
            $conn->commit();
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = $e->getMessage();
        }
    }
}

// Get match details
$stmt = $conn->prepare("
    SELECT e.*, 
           u.username as creator_name,
           COUNT(DISTINCT ba.id) as bets_on_a,
           COUNT(DISTINCT bb.id) as bets_on_b,
           COALESCE(SUM(CASE WHEN ba.status = 'matched' THEN ba.amount END), 0) as matched_amount_a,
           COALESCE(SUM(CASE WHEN bb.status = 'matched' THEN bb.amount END), 0) as matched_amount_b,
           COUNT(DISTINCT ba_pending.id) as pending_bets_a,
           COUNT(DISTINCT bb_pending.id) as pending_bets_b,
           COALESCE(SUM(CASE WHEN ba_pending.status = 'pending' THEN ba_pending.amount END), 0) as pending_amount_a,
           COALESCE(SUM(CASE WHEN bb_pending.status = 'pending' THEN bb_pending.amount END), 0) as pending_amount_b
    FROM events e 
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN bets ba ON e.id = ba.event_id AND ba.bet_on = 'A' AND ba.status = 'matched'
    LEFT JOIN bets bb ON e.id = bb.event_id AND bb.bet_on = 'B' AND bb.status = 'matched'
    LEFT JOIN bets ba_pending ON e.id = ba_pending.event_id AND ba_pending.bet_on = 'A' AND ba_pending.status = 'pending'
    LEFT JOIN bets bb_pending ON e.id = bb_pending.event_id AND bb_pending.bet_on = 'B' AND bb_pending.status = 'pending'
    WHERE e.id = ?
    GROUP BY e.id
");
$stmt->execute([$match_id]);
$match = $stmt->fetch();

if (!$match) {
    header('Location: ' . url('user/events.php'));
    exit();
}

// Get user's bets on this match
$stmt = $conn->prepare("
    SELECT * FROM bets 
    WHERE event_id = ? AND user_id = ? AND status IN ('pending', 'matched')
    ORDER BY placed_at DESC
");
$stmt->execute([$match_id, $user_id]);
$user_bets = $stmt->fetchAll();

// Get user's wallet balance
$stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch();
$user_balance = $wallet ? $wallet['balance'] : 0;

// Get recent bets on this match
$stmt = $conn->prepare("
    SELECT b.*, u.username 
    FROM bets b 
    JOIN users u ON b.user_id = u.id 
    WHERE b.event_id = ? AND b.status IN ('pending', 'matched')
    ORDER BY b.placed_at DESC 
    LIMIT 20
");
$stmt->execute([$match_id]);
$recent_bets = $stmt->fetchAll();

// Get available bet amounts (pending bets that can be matched)
$stmt = $conn->prepare("
    SELECT bet_on, amount, COUNT(*) as count
    FROM bets 
    WHERE event_id = ? AND status = 'pending' AND user_id != ?
    GROUP BY bet_on, amount
    ORDER BY bet_on, amount
");
$stmt->execute([$match_id, $user_id]);
$available_bets = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container mt-4" style="padding-top: 40px;">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo url('user/events.php'); ?>">Events</a></li>
                    <li class="breadcrumb-item active">Match Details</li>
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
    
    <!-- Match Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h2><?php echo htmlspecialchars($match['name']); ?></h2>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($match['game_type']); ?></p>
                            <?php if ($match['description']): ?>
                                <p class="mb-0"><?php echo htmlspecialchars($match['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="text-end">
                            <span class="event-status status-<?php echo str_replace('_', '-', $match['status']); ?>">
                                <?php echo strtoupper(str_replace('_', ' ', $match['status'])); ?>
                            </span>
                            <?php if ($match['match_start_time']): ?>
                                <br><small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('M j, Y g:i A', strtotime($match['match_start_time'])); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Competitors -->
    <div class="row mb-4">
        <div class="col-md-5">
            <div class="competitor-card competitor-a h-100">
                <div class="competitor-image mb-3">
                    <?php if (!empty($match['competitor_a_image'])): ?>
                        <img src="<?php echo url($match['competitor_a_image']); ?>" 
                             alt="<?php echo htmlspecialchars($match['competitor_a']); ?>" 
                             class="competitor-img img-fluid rounded">
                    <?php else: ?>
                        <div class="no-image-placeholder">
                            <i class="fas fa-user fa-4x"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="competitor-header text-center">
                    <h3 class="fw-bold"><?php echo htmlspecialchars($match['competitor_a']); ?></h3>
                    <small class="competitor-label">COMPETITOR A</small>
                </div>
                
                <div class="stats-grid mt-4">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="stat-number"><?php echo $match['bets_on_a']; ?></div>
                                <div class="stat-label">Matched Bets</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="stat-number">$<?php echo number_format($match['matched_amount_a'], 2); ?></div>
                                <div class="stat-label">Total Matched</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($match['pending_bets_a'] > 0): ?>
                    <div class="pending-info mt-3 text-center">
                        <div class="pending-badge">
                            <?php echo $match['pending_bets_a']; ?> pending 
                            ($<?php echo number_format($match['pending_amount_a'], 2); ?>)
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-2 d-flex align-items-center justify-content-center">
            <div class="vs-indicator-large text-center">
                <div class="vs-circle">
                    <strong>VS</strong>
                </div>
                <?php if ($match['status'] === 'accepting_bets'): ?>
                    <div class="status-badge mt-2">
                        <i class="fas fa-circle text-success me-1"></i>
                        <small>Betting Open</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-5">
            <div class="competitor-card competitor-b h-100">
                <div class="competitor-image mb-3">
                    <?php if (!empty($match['competitor_b_image'])): ?>
                        <img src="<?php echo url($match['competitor_b_image']); ?>" 
                             alt="<?php echo htmlspecialchars($match['competitor_b']); ?>" 
                             class="competitor-img img-fluid rounded">
                    <?php else: ?>
                        <div class="no-image-placeholder">
                            <i class="fas fa-user fa-4x"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="competitor-header text-center">
                    <h3 class="fw-bold"><?php echo htmlspecialchars($match['competitor_b']); ?></h3>
                    <small class="competitor-label">COMPETITOR B</small>
                </div>
                
                <div class="stats-grid mt-4">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="stat-number"><?php echo $match['bets_on_b']; ?></div>
                                <div class="stat-label">Matched Bets</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="stat-number">$<?php echo number_format($match['matched_amount_b'], 2); ?></div>
                                <div class="stat-label">Total Matched</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($match['pending_bets_b'] > 0): ?>
                    <div class="pending-info mt-3 text-center">
                        <div class="pending-badge">
                            <?php echo $match['pending_bets_b']; ?> pending 
                            ($<?php echo number_format($match['pending_amount_b'], 2); ?>)
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Betting Section -->
        <div class="col-md-8">
            <?php if (!empty($user_bets)): ?>
                <!-- User's Current Bets -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-ticket-alt me-2"></i>Your Bets (<?php echo count($user_bets); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $total_pending_amount = 0;
                        $total_matched_amount = 0;
                        foreach ($user_bets as $bet) {
                            if ($bet['status'] === 'pending') $total_pending_amount += $bet['amount'];
                            if ($bet['status'] === 'matched') $total_matched_amount += $bet['amount'];
                        }
                        ?>
                        
                        <!-- Summary -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="text-center p-2 bg-light rounded">
                                    <strong class="text-primary">$<?php echo number_format($total_matched_amount, 2); ?></strong>
                                    <br><small class="text-muted">Matched</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-2 bg-light rounded">
                                    <strong class="text-warning">$<?php echo number_format($total_pending_amount, 2); ?></strong>
                                    <br><small class="text-muted">Pending</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-2 bg-light rounded">
                                    <strong class="text-success">$<?php echo number_format($total_matched_amount + $total_pending_amount, 2); ?></strong>
                                    <br><small class="text-muted">Total</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Individual Bets -->
                        <div class="bet-list">
                            <?php foreach ($user_bets as $bet): ?>
                                <div class="alert alert-<?php echo $bet['status'] === 'matched' ? 'success' : 'warning'; ?> mb-2">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <strong>$<?php echo number_format($bet['amount'], 2); ?> on Competitor <?php echo $bet['bet_on']; ?></strong>
                                            <br><small class="text-muted">
                                                Placed: <?php echo date('M j, g:i A', strtotime($bet['placed_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <span class="badge bg-<?php echo $bet['status'] === 'matched' ? 'success' : 'warning'; ?>">
                                                <?php echo strtoupper($bet['status']); ?>
                                            </span>
                                            <?php if ($bet['status'] === 'matched'): ?>
                                                <br><small class="text-success">
                                                    Potential: $<?php echo number_format($bet['amount'] * 2 * (1 - $match['platform_fee_percent'] / 100), 2); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($match['status'] === 'accepting_bets'): ?>
                <!-- Betting Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-dollar-sign me-2"></i>Place Your Bet</h5>
                        <small class="text-muted">Your balance: $<?php echo number_format($user_balance, 2); ?></small>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="place_bet">
                            
                            <div class="mb-3">
                                <label class="form-label">Choose Winner</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input d-none" type="radio" name="bet_on" value="A" id="bet_a" required>
                                            <label class="form-check-label w-100" for="bet_a">
                                                <div class="competitor-select-card competitor-a-select">
                                                    <div class="text-center p-3">
                                                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($match['competitor_a']); ?></h5>
                                                        <small class="competitor-label">COMPETITOR A</small>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input d-none" type="radio" name="bet_on" value="B" id="bet_b" required>
                                            <label class="form-check-label w-100" for="bet_b">
                                                <div class="competitor-select-card competitor-b-select">
                                                    <div class="text-center p-3">
                                                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($match['competitor_b']); ?></h5>
                                                        <small class="competitor-label">COMPETITOR B</small>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="amount" class="form-label">Bet Amount ($)</label>
                                <input type="number" class="form-control" id="amount" name="amount" 
                                       min="1" max="<?php echo min(10000, $user_balance); ?>" step="0.01" required>
                                <div class="form-text">
                                    Minimum: $1.00 • Maximum: $<?php echo number_format(min(10000, $user_balance), 2); ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Quick Amounts</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php 
                                    $quick_amounts = [25, 50, 100, 250, 500];
                                    foreach ($quick_amounts as $quick_amount): 
                                        if ($quick_amount <= $user_balance):
                                    ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                onclick="document.getElementById('amount').value = <?php echo $quick_amount; ?>">
                                            $<?php echo $quick_amount; ?>
                                        </button>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>How Betting Works:</h6>
                                <ul class="mb-0">
                                    <li>Your bet will be matched with someone betting the same amount on the other competitor</li>
                                    <li>If your chosen competitor wins, you get both bet amounts minus the <?php echo $match['platform_fee_percent']; ?>% platform fee</li>
                                    <li>Unmatched bets are refunded if the match starts without a match</li>
                                    <li>Results are verified by streamers through voting</li>
                                </ul>
                            </div>
                            
                            <button type="submit" class="btn btn-place-bet btn-lg w-100">
                                <i class="fas fa-ticket-alt me-2"></i>Place Bet
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <i class="fas fa-ban fa-3x text-muted mb-3"></i>
                        <h5>Betting Closed</h5>
                        <p class="text-muted">This match is no longer accepting bets.</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Available Bets to Match -->
            <?php if (!empty($available_bets) && $match['status'] === 'accepting_bets'): ?>
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-handshake me-2"></i>Available Bets to Match</h5>
                        <small class="text-muted">Bet these amounts for instant matching</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Competitor A</h6>
                                <?php 
                                $has_a_bets = false;
                                foreach ($available_bets as $bet): 
                                    if ($bet['bet_on'] === 'A'): 
                                        $has_a_bets = true;
                                ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>$<?php echo number_format($bet['amount'], 2); ?></span>
                                        <span class="badge bg-primary"><?php echo $bet['count']; ?> available</span>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                if (!$has_a_bets): 
                                ?>
                                    <p class="text-muted">No pending bets</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6>Competitor B</h6>
                                <?php 
                                $has_b_bets = false;
                                foreach ($available_bets as $bet): 
                                    if ($bet['bet_on'] === 'B'): 
                                        $has_b_bets = true;
                                ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>$<?php echo number_format($bet['amount'], 2); ?></span>
                                        <span class="badge bg-success"><?php echo $bet['count']; ?> available</span>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                if (!$has_b_bets): 
                                ?>
                                    <p class="text-muted">No pending bets</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Match Info Sidebar -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Match Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Game Type:</strong>
                        <span class="text-muted"><?php echo htmlspecialchars($match['game_type']); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Platform Fee:</strong>
                        <span class="text-muted"><?php echo $match['platform_fee_percent']; ?>%</span>
                    </div>
                    <div class="mb-3">
                        <strong>Total Prize Pool:</strong>
                        <span class="text-success">$<?php echo number_format($match['matched_amount_a'] + $match['matched_amount_b'], 2); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Created:</strong>
                        <span class="text-muted"><?php echo date('M j, Y', strtotime($match['created_at'])); ?></span>
                    </div>
                    <?php if ($match['creator_name']): ?>
                        <div class="mb-3">
                            <strong>Created by:</strong>
                            <span class="text-muted"><?php echo htmlspecialchars($match['creator_name']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Bets -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history me-2"></i>Recent Bets</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_bets)): ?>
                        <p class="text-muted text-center">No bets placed yet</p>
                    <?php else: ?>
                        <?php foreach (array_slice($recent_bets, 0, 10) as $bet): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong><?php echo htmlspecialchars($bet['username']); ?></strong>
                                    <br><small class="text-muted">
                                        Competitor <?php echo $bet['bet_on']; ?> • 
                                        <?php echo date('M j g:i A', strtotime($bet['placed_at'])); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <strong class="text-primary">$<?php echo number_format($bet['amount'], 2); ?></strong>
                                    <br><span class="badge bg-<?php echo $bet['status'] === 'matched' ? 'success' : 'warning'; ?> small">
                                        <?php echo ucfirst($bet['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Match Details Page Styling */
.competitor-card {
    padding: 30px;
    border-radius: 20px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.competitor-a {
    background: linear-gradient(135deg, rgba(66, 133, 244, 0.15) 0%, rgba(51, 103, 214, 0.25) 100%);
    color: #1a73e8;
    border: 2px solid rgba(66, 133, 244, 0.3);
    box-shadow: 0 4px 15px rgba(66, 133, 244, 0.1);
}

.competitor-b {
    background: linear-gradient(135deg, rgba(52, 168, 83, 0.15) 0%, rgba(45, 143, 66, 0.25) 100%);
    color: #1e7b32;
    border: 2px solid rgba(52, 168, 83, 0.3);
    box-shadow: 0 4px 15px rgba(52, 168, 83, 0.1);
}

.competitor-image {
    height: 200px;
    overflow: hidden;
    border-radius: 15px;
}

.competitor-img {
    height: 200px;
    width: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.competitor-a .no-image-placeholder {
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(66, 133, 244, 0.1);
    border-radius: 15px;
}

.competitor-b .no-image-placeholder {
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(52, 168, 83, 0.1);
    border-radius: 15px;
}

.competitor-header h3 {
    font-size: 1.8rem;
    margin-bottom: 5px;
}

.competitor-label {
    font-size: 0.75rem;
    font-weight: bold;
    letter-spacing: 1.5px;
    opacity: 0.8;
    display: block;
}

.stats-grid {
    margin-top: 20px;
}

.stat-box {
    padding: 15px;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.85rem;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pending-badge {
    background: rgba(255, 193, 7, 0.2);
    color: #856404;
    padding: 8px 15px;
    border-radius: 15px;
    font-size: 0.9rem;
    font-weight: 500;
    display: inline-block;
}

.vs-indicator-large {
    position: relative;
}

.vs-circle {
    background: linear-gradient(135deg, #FF6B6B, #FF5722);
    border-radius: 50%;
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    font-weight: bold;
    color: white;
    box-shadow: 0 6px 20px rgba(255, 107, 107, 0.3);
    margin: 0 auto;
}

.status-badge {
    margin-top: 15px;
    color: #28a745;
    font-weight: 500;
}

/* Betting Form Styling */
.competitor-select-card {
    border: 2px solid transparent;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.competitor-a-select {
    background: rgba(66, 133, 244, 0.1);
    color: #1a73e8;
    border-color: rgba(66, 133, 244, 0.3);
}

.competitor-a-select:hover,
.competitor-a-select.selected {
    background: rgba(66, 133, 244, 0.2);
    border-color: rgba(66, 133, 244, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(66, 133, 244, 0.2);
}

.competitor-b-select {
    background: rgba(52, 168, 83, 0.1);
    color: #1e7b32;
    border-color: rgba(52, 168, 83, 0.3);
}

.competitor-b-select:hover,
.competitor-b-select.selected {
    background: rgba(52, 168, 83, 0.2);
    border-color: rgba(52, 168, 83, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(52, 168, 83, 0.2);
}

.btn-place-bet {
    background: linear-gradient(135deg, #007bff, #0056b3);
    border: none;
    color: white;
    font-weight: bold;
    padding: 15px 30px;
    transition: all 0.3s ease;
}

.btn-place-bet:hover {
    background: linear-gradient(135deg, #0056b3, #004085);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
    color: white;
}

/* Available Bets Section */
.card-header h5 {
    margin-bottom: 0;
}

.card-header small {
    opacity: 0.8;
}

/* Quick amount buttons */
.btn-outline-secondary:hover {
    background-color: #6c757d;
    border-color: #6c757d;
    color: white;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .competitor-card {
        margin-bottom: 20px;
        padding: 20px;
    }
    
    .competitor-image {
        height: 150px;
    }
    
    .competitor-img,
    .no-image-placeholder {
        height: 150px;
    }
    
    .competitor-header h3 {
        font-size: 1.4rem;
    }
    
    .vs-circle {
        width: 60px;
        height: 60px;
        font-size: 1.1rem;
    }
}
</style>

<script>
// Handle competitor selection visual feedback
document.addEventListener('change', function(e) {
    if (e.target.name === 'bet_on') {
        // Remove selected class from all cards
        document.querySelectorAll('.competitor-select-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Add selected class to chosen card
        if (e.target.value === 'A') {
            document.querySelector('.competitor-a-select').classList.add('selected');
        } else if (e.target.value === 'B') {
            document.querySelector('.competitor-b-select').classList.add('selected');
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>