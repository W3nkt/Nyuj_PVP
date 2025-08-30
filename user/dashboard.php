<?php
$page_title = 'Dashboard - Bull PVP Platform';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';

requireLogin();

$error_message = '';
$balance = 0;
$locked_balance = 0;
$user_stats = [
    'total_bets' => 0,
    'wins' => 0, 
    'losses' => 0,
    'active_bets' => 0,
    'pending_bets' => 0,
    'total_wagered' => 0,
    'total_winnings' => 0,
    'win_percentage' => 0
];
$active_bets = [];
$recent_bets = [];
$available_events = [];
$recent_transactions = [];
$user_events = [];

try {
    $db = new Database();
    $conn = $db->connect();
    $user_id = getUserId();

    // Get user's wallet information with fallback
    try {
        $stmt = $conn->prepare("SELECT balance, locked_balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $wallet = $stmt->fetch();
        if ($wallet) {
            $balance = $wallet['balance'] ?? 0;
            $locked_balance = $wallet['locked_balance'] ?? 0;
        }
    } catch (PDOException $e) {
        // Column might not exist, try without locked_balance
        try {
            $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $wallet = $stmt->fetch();
            if ($wallet) {
                $balance = $wallet['balance'] ?? 0;
            }
        } catch (PDOException $e2) {
            // Table might not exist - this is OK, we'll use defaults
        }
    }

    // Get basic events if table exists
    try {
        $stmt = $conn->prepare("SELECT * FROM events WHERE status = 'accepting_bets' ORDER BY created_at DESC LIMIT 5");
        $stmt->execute();
        $available_events = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Events table might not exist yet
    }

    // Get basic user stats if bets table exists
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total_bets FROM bets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        if ($result) {
            $user_stats['total_bets'] = $result['total_bets'] ?? 0;
        }
    } catch (PDOException $e) {
        // Bets table might not exist yet
    }

} catch (Exception $e) {
    $error_message = 'Database connection issue. Please run setup or check configuration.';
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <?php if ($error_message): ?>
        <div class="alert alert-warning">
            <h4><i class="fas fa-exclamation-triangle me-2"></i>Database Setup Required</h4>
            <p><?php echo htmlspecialchars($error_message); ?></p>
            <div class="mt-3">
                <a href="<?php echo url('setup.php'); ?>" class="btn btn-primary">
                    <i class="fas fa-cogs me-2"></i>Run Setup
                </a>
                <a href="<?php echo url('test_connection.php'); ?>" class="btn btn-outline-primary">
                    <i class="fas fa-check-circle me-2"></i>Test Connection
                </a>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-12 mb-4">
            <h2><i class="fas fa-dashboard me-2"></i>Dashboard</h2>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</p>
        </div>
    </div>
    
    <!-- Wallet Balance -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h5 class="card-title">
                        <i class="fas fa-wallet me-2 text-primary"></i>Wallet Balance
                    </h5>
                    <div class="display-6 text-primary mb-2">$<?php echo number_format($balance, 2); ?></div>
                    <?php if ($locked_balance > 0): ?>
                        <div class="text-muted mb-3">
                            <small>$<?php echo number_format($locked_balance, 2); ?> locked in bets</small>
                        </div>
                    <?php endif; ?>
                    <div class="d-grid gap-2 d-md-block">
                        <button class="btn btn-primary btn-sm" onclick="showDepositModal()">
                            <i class="fas fa-plus me-1"></i>Deposit
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="showWithdrawModal()" 
                                        <?php echo $balance <= 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-minus me-1"></i>Withdraw
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="col-md-8">
            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <div class="display-6 text-info"><?php echo $user_stats['total_bets']; ?></div>
                            <div class="text-muted">Total Bets</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <div class="display-6 text-success"><?php echo $user_stats['wins']; ?></div>
                            <div class="text-muted">Wins</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <div class="display-6 text-warning"><?php echo number_format($user_stats['win_percentage'], 1); ?>%</div>
                            <div class="text-muted">Win Rate</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <div class="display-6 text-success">$<?php echo number_format($user_stats['total_winnings'], 2); ?></div>
                            <div class="text-muted">Winnings</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="row">
        <!-- Available Events -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-calendar me-2"></i>Available Events</h5>
                    <a href="<?php echo url('user/events.php'); ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt me-1"></i>View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($available_events)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Events Available</h5>
                            <p class="text-muted">
                                <?php if ($error_message): ?>
                                    Please run the setup to initialize the database.
                                <?php else: ?>
                                    Check back later for new betting opportunities.
                                <?php endif; ?>
                            </p>
                            <div class="mt-3">
                                <a href="<?php echo url('user/events.php'); ?>" class="btn btn-primary">
                                    <i class="fas fa-refresh me-2"></i>Browse Events
                                </a>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <a href="<?php echo url('admin/create_event.php'); ?>" class="btn btn-success ms-2">
                                        <i class="fas fa-plus me-2"></i>Create Event
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach (array_slice($available_events, 0, 3) as $event): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($event['name']); ?></h6>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($event['game_type']); ?>
                                                </small><br>
                                                <strong><?php echo htmlspecialchars($event['competitor_a']); ?></strong>
                                                vs
                                                <strong><?php echo htmlspecialchars($event['competitor_b']); ?></strong>
                                            </p>
                                            <div class="d-grid">
                                                <a href="<?php echo url('user/events.php'); ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-coins me-1"></i>Place Bet
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions & Info -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?php echo url('user/events.php'); ?>" class="btn btn-primary">
                            <i class="fas fa-calendar me-2"></i>Browse Events
                        </a>
                        <a href="<?php echo url('user/transactions.php'); ?>" class="btn btn-outline-primary">
                            <i class="fas fa-history me-2"></i>Transaction History
                        </a>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="<?php echo url('admin/index.php'); ?>" class="btn btn-success">
                                <i class="fas fa-cogs me-2"></i>Admin Panel
                            </a>
                        <?php endif; ?>
                        <?php if ($_SESSION['role'] === 'streamer'): ?>
                            <a href="<?php echo url('streamer/voting.php'); ?>" class="btn btn-warning">
                                <i class="fas fa-vote-yea me-2"></i>Voting Panel
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="text-center">
                        <h6 class="text-muted">Account Info</h6>
                        <p class="mb-1">
                            <strong>Role:</strong> 
                            <span class="badge bg-secondary">
                                <?php echo ucfirst($_SESSION['role'] ?? 'User'); ?>
                            </span>
                        </p>
                        <p class="mb-0">
                            <strong>Joined:</strong> 
                            <small class="text-muted">Welcome!</small>
                        </p>
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
</script>
<?php require_once '../includes/footer.php'; ?>