<?php
$page_title = 'Wallet - Bull PVP Platform';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';

requireLogin();

$error_message = '';
$success_message = '';
$balance = 0;
$locked_balance = 0;
$recent_transactions = [];
$wallet_stats = [
    'total_deposited' => 0,
    'total_withdrawn' => 0,
    'total_winnings' => 0,
    'total_losses' => 0
];

try {
    $db = new Database();
    $conn = $db->connect();
    $user_id = getUserId();

    // Get wallet information with error handling
    try {
        $stmt = $conn->prepare("SELECT balance, locked_balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $wallet = $stmt->fetch();
        
        if ($wallet) {
            $balance = $wallet['balance'] ?? 0;
            $locked_balance = $wallet['locked_balance'] ?? 0;
        } else {
            // Create wallet if it doesn't exist
            $stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
            $stmt->execute([$user_id]);
            $balance = 0;
            $locked_balance = 0;
        }
    } catch (PDOException $e) {
        // Try without locked_balance column if it doesn't exist
        try {
            $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $wallet = $stmt->fetch();
            if ($wallet) {
                $balance = $wallet['balance'] ?? 0;
            }
        } catch (PDOException $e2) {
            $error_message = 'Unable to load wallet. Please contact support.';
        }
    }

    // Get recent transactions
    try {
        $stmt = $conn->prepare("
            SELECT t.*, e.name as event_name 
            FROM transactions t 
            LEFT JOIN events e ON t.event_id = e.id 
            WHERE t.wallet_id = (SELECT id FROM wallets WHERE user_id = ?)
            ORDER BY t.created_at DESC 
            LIMIT 20
        ");
        $stmt->execute([$user_id]);
        $recent_transactions = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Transactions table might not exist
        $recent_transactions = [];
    }

    // Calculate wallet statistics
    try {
        foreach ($recent_transactions as $transaction) {
            switch ($transaction['type']) {
                case 'deposit':
                    $wallet_stats['total_deposited'] += abs($transaction['amount']);
                    break;
                case 'withdrawal':
                    $wallet_stats['total_withdrawn'] += abs($transaction['amount']);
                    break;
                case 'payout':
                case 'winnings':
                    $wallet_stats['total_winnings'] += abs($transaction['amount']);
                    break;
                case 'bet':
                case 'loss':
                    $wallet_stats['total_losses'] += abs($transaction['amount']);
                    break;
            }
        }
    } catch (Exception $e) {
        // Use default values if calculation fails
    }

} catch (Exception $e) {
    $error_message = 'Database connection failed. Please try again later.';
}

require_once '../includes/header.php';
?>

<style>
/* Force new color scheme - inline to override cache */
:root {
    --navy: #1E3A8A !important;
    --cyan: #06B6D4 !important;
    --magenta: #DB2777 !important;
    --lime: #84CC16 !important;
    --primary-color: var(--cyan) !important;
    --secondary-color: var(--navy) !important;
    --accent-color: var(--magenta) !important;
    --success-color: var(--lime) !important;
    --text-dark: var(--navy) !important;
    --success: var(--lime) !important;
}

/* Force button color changes */
.btn-lime { 
    background-color: #84CC16 !important; 
    color: #1E3A8A !important; 
    border: none !important;
}
.btn-success { 
    background-color: #84CC16 !important; 
    color: #1E3A8A !important; 
}
.text-lime { 
    color: #84CC16 !important; 
}
.text-cyan { 
    color: #06B6D4 !important; 
}
.text-magenta { 
    color: #DB2777 !important; 
}
.text-navy { 
    color: #1E3A8A !important; 
}
.border-lime { 
    border-color: #84CC16 !important; 
}
.border-cyan { 
    border-color: #06B6D4 !important; 
}

/* Update card headers */
.card-header {
    background: linear-gradient(45deg, #06B6D4, #1E3A8A) !important;
    color: white !important;
}

/* Update navbar */
.navbar {
    border-bottom: 3px solid #06B6D4 !important;
}

/* Force body background */
body {
    background: linear-gradient(135deg, #F8FAFC 0%, #E0F7FA 30%, #B3E5FC 70%, #81D4FA 100%) !important;
}
</style>

<div class="container mt-4">
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
                    <h2><i class="fas fa-wallet me-2"></i>My Wallet</h2>
                    <p class="text-muted mb-0">Manage your funds and view transaction history</p>
                </div>
                <div>
                    <a href="<?php echo url('user/dashboard.php'); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-dashboard me-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Wallet Balance Cards -->
    <div class="row mb-4">
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card border-lime h-100">
                <div class="card-body text-center">
                    <div class="mb-2">
                        <i class="fas fa-dollar-sign fa-2x text-lime"></i>
                    </div>
                    <h3 class="card-title text-lime">$<?php echo number_format($balance, 2); ?></h3>
                    <p class="card-text">Available Balance</p>
                    <small class="text-muted">Ready for betting</small>
                </div>
            </div>
        </div>
        
        <?php if ($locked_balance > 0): ?>
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card border-warning h-100">
                <div class="card-body text-center">
                    <div class="mb-2">
                        <i class="fas fa-lock fa-2x text-warning"></i>
                    </div>
                    <h3 class="card-title text-warning">$<?php echo number_format($locked_balance, 2); ?></h3>
                    <p class="card-text">Locked in Bets</p>
                    <small class="text-muted">Funds in active bets</small>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card border-cyan h-100">
                <div class="card-body text-center">
                    <div class="mb-2">
                        <i class="fas fa-chart-line fa-2x text-cyan"></i>
                    </div>
                    <h3 class="card-title text-cyan">$<?php echo number_format($balance + $locked_balance, 2); ?></h3>
                    <p class="card-text">Total Value</p>
                    <small class="text-muted">Available + Locked</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Wallet Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-grid">
                                <button class="btn btn-lime btn-lg" onclick="showDepositModal()">
                                    <i class="fas fa-plus me-2"></i>Deposit Funds
                                </button>
                            </div>
                            <small class="text-muted d-block text-center mt-1">Add money to your wallet</small>
                        </div>
                        <div class="col-md-6">
                            <div class="d-grid">
                                <button class="btn btn-warning btn-lg" onclick="showWithdrawModal()" 
                                        <?php echo $balance <= 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-minus me-2"></i>Withdraw Funds
                                </button>
                            </div>
                            <small class="text-muted d-block text-center mt-1">
                                <?php echo $balance <= 0 ? 'No funds available' : 'Cash out your balance'; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Wallet Statistics -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Wallet Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center">
                                <div class="h4 text-lime">$<?php echo number_format($wallet_stats['total_deposited'], 2); ?></div>
                                <div class="text-muted">Total Deposited</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center">
                                <div class="h4 text-magenta">$<?php echo number_format($wallet_stats['total_withdrawn'], 2); ?></div>
                                <div class="text-muted">Total Withdrawn</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center">
                                <div class="h4 text-cyan">$<?php echo number_format($wallet_stats['total_winnings'], 2); ?></div>
                                <div class="text-muted">Total Winnings</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center">
                                <div class="h4 text-navy">$<?php echo number_format($wallet_stats['total_losses'], 2); ?></div>
                                <div class="text-muted">Total Losses</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transaction History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                    <a href="<?php echo url('user/transactions.php'); ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt me-1"></i>View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_transactions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Transactions Yet</h5>
                            <p class="text-muted">Your transaction history will appear here once you make your first deposit or bet.</p>
                            <button class="btn btn-lime" onclick="showDepositModal()">
                                <i class="fas fa-plus me-2"></i>Make First Deposit
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($recent_transactions, 0, 10) as $transaction): ?>
                                        <tr>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($transaction['created_at'])); ?>
                                                <br><small class="text-muted"><?php echo date('g:i A', strtotime($transaction['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $type_icons = [
                                                    'deposit' => 'fa-arrow-down text-success',
                                                    'withdrawal' => 'fa-arrow-up text-danger', 
                                                    'bet' => 'fa-dice text-warning',
                                                    'payout' => 'fa-trophy text-success',
                                                    'refund' => 'fa-undo text-info',
                                                    'winnings' => 'fa-star text-warning'
                                                ];
                                                $icon = $type_icons[$transaction['type']] ?? 'fa-circle text-secondary';
                                                ?>
                                                <i class="fas <?php echo $icon; ?> me-2"></i>
                                                <?php echo ucfirst($transaction['type']); ?>
                                            </td>
                                            <td>
                                                <?php if ($transaction['event_name']): ?>
                                                    <strong><?php echo htmlspecialchars($transaction['event_name']); ?></strong>
                                                <?php else: ?>
                                                    <?php
                                                    $descriptions = [
                                                        'deposit' => 'Wallet deposit',
                                                        'withdrawal' => 'Wallet withdrawal',
                                                        'bet' => 'Bet placement',
                                                        'payout' => 'Betting winnings',
                                                        'refund' => 'Bet refund'
                                                    ];
                                                    echo $descriptions[$transaction['type']] ?? 'Transaction';
                                                    ?>
                                                <?php endif; ?>
                                                <?php if ($transaction['description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($transaction['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $amount = $transaction['amount'];
                                                $is_positive = in_array($transaction['type'], ['deposit', 'payout', 'refund', 'winnings']);
                                                $color = $is_positive ? 'text-success' : 'text-danger';
                                                $sign = $is_positive ? '+' : '-';
                                                ?>
                                                <span class="fw-bold <?php echo $color; ?>">
                                                    <?php echo $sign; ?>$<?php echo number_format(abs($amount), 2); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">Completed</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($recent_transactions) > 10): ?>
                            <div class="text-center mt-3">
                                <a href="<?php echo url('user/transactions.php'); ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-list me-2"></i>View All <?php echo count($recent_transactions); ?> Transactions
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
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