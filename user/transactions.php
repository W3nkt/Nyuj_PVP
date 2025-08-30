<?php
$page_title = 'Transaction History - Bull PVP Platform';
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/paths.php';

requireLogin();

$db = new Database();
$conn = $db->connect();
$user_id = getUserId();

// Get user's wallet ID
$stmt = $conn->prepare("SELECT id FROM wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch();

if (!$wallet) {
    header('Location: ' . url('user/dashboard.php'));
    exit();
}

// Pagination
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filter options
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Build WHERE clause
$where_conditions = ['t.wallet_id = ?'];
$params = [$wallet['id']];

if ($filter_type) {
    $where_conditions[] = 't.type = ?';
    $params[] = $filter_type;
}

if ($filter_status) {
    $where_conditions[] = 't.status = ?';
    $params[] = $filter_status;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM transactions t WHERE {$where_clause}");
$stmt->execute($params);
$total_transactions = $stmt->fetch()['total'];
$total_pages = ceil($total_transactions / $per_page);

// Get transactions
$stmt = $conn->prepare("
    SELECT t.*, e.name as event_name 
    FROM transactions t 
    LEFT JOIN events e ON t.event_id = e.id 
    WHERE {$where_clause}
    ORDER BY t.created_at DESC 
    LIMIT {$per_page} OFFSET {$offset}
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/Bull_PVP/user/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Transaction History</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-history me-2"></i>Transaction History</h2>
            <p class="text-muted">View all your wallet transactions and event activities</p>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="type" class="form-label">Transaction Type</label>
                            <select class="form-control" id="type" name="type">
                                <option value="">All Types</option>
                                <option value="deposit" <?php echo $filter_type === 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                                <option value="withdrawal" <?php echo $filter_type === 'withdrawal' ? 'selected' : ''; ?>>Withdrawal</option>
                                <option value="hold" <?php echo $filter_type === 'hold' ? 'selected' : ''; ?>>Event Join</option>
                                <option value="payout" <?php echo $filter_type === 'payout' ? 'selected' : ''; ?>>Winnings</option>
                                <option value="refund" <?php echo $filter_type === 'refund' ? 'selected' : ''; ?>>Refund</option>
                                <option value="streamer_payment" <?php echo $filter_type === 'streamer_payment' ? 'selected' : ''; ?>>Streamer Payment</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                            <a href="/Bull_PVP/user/transactions.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transactions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-list me-2"></i>Transactions</h5>
                    <small class="text-muted">
                        Showing <?php echo min($offset + 1, $total_transactions); ?>-<?php echo min($offset + $per_page, $total_transactions); ?> 
                        of <?php echo $total_transactions; ?> transactions
                    </small>
                </div>
                <div class="card-body">
                    <?php if (empty($transactions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5>No Transactions Found</h5>
                            <p class="text-muted">
                                <?php if ($filter_type || $filter_status): ?>
                                    No transactions match your current filters.
                                <?php else: ?>
                                    You haven't made any transactions yet.
                                <?php endif; ?>
                            </p>
                            <?php if ($filter_type || $filter_status): ?>
                                <a href="/Bull_PVP/user/transactions.php" class="btn btn-outline-primary">
                                    <i class="fas fa-times me-2"></i>Clear Filters
                                </a>
                            <?php else: ?>
                                <a href="/Bull_PVP/user/dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></strong><br>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($transaction['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $type_icons = [
                                                    'deposit' => 'fas fa-plus-circle text-success',
                                                    'withdrawal' => 'fas fa-minus-circle text-warning',
                                                    'hold' => 'fas fa-lock text-info',
                                                    'payout' => 'fas fa-trophy text-success',
                                                    'refund' => 'fas fa-undo text-primary',
                                                    'streamer_payment' => 'fas fa-vote-yea text-info'
                                                ];
                                                
                                                $type_labels = [
                                                    'deposit' => 'Deposit',
                                                    'withdrawal' => 'Withdrawal',
                                                    'hold' => 'Event Join',
                                                    'payout' => 'Winnings',
                                                    'refund' => 'Refund',
                                                    'streamer_payment' => 'Streamer Payment'
                                                ];
                                                
                                                $icon = $type_icons[$transaction['type']] ?? 'fas fa-circle';
                                                $label = $type_labels[$transaction['type']] ?? ucfirst($transaction['type']);
                                                ?>
                                                <i class="<?php echo $icon; ?> me-2"></i><?php echo $label; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($transaction['description']); ?>
                                                <?php if ($transaction['event_name']): ?>
                                                    <br><small class="text-muted">Event: <?php echo htmlspecialchars($transaction['event_name']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="fw-bold <?php echo $transaction['amount'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $transaction['amount'] >= 0 ? '+' : ''; ?>$<?php echo number_format($transaction['amount'], 2); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_classes = [
                                                    'pending' => 'bg-warning',
                                                    'completed' => 'bg-success',
                                                    'failed' => 'bg-danger',
                                                    'cancelled' => 'bg-secondary'
                                                ];
                                                $status_class = $status_classes[$transaction['status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($transaction['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($transaction['reference_id']): ?>
                                                    <small class="text-muted font-monospace">
                                                        <?php echo htmlspecialchars($transaction['reference_id']); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-muted">-</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Transaction pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $filter_type ? '&type=' . urlencode($filter_type) : ''; ?><?php echo $filter_status ? '&status=' . urlencode($filter_status) : ''; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $filter_type ? '&type=' . urlencode($filter_type) : ''; ?><?php echo $filter_status ? '&status=' . urlencode($filter_status) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $filter_type ? '&type=' . urlencode($filter_type) : ''; ?><?php echo $filter_status ? '&status=' . urlencode($filter_status) : ''; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary Card -->
    <?php if (!empty($transactions)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar me-2"></i>Transaction Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Calculate summary statistics
                        $total_deposits = 0;
                        $total_withdrawals = 0;
                        $total_winnings = 0;
                        $total_spent = 0;
                        
                        foreach ($transactions as $t) {
                            switch ($t['type']) {
                                case 'deposit':
                                    $total_deposits += $t['amount'];
                                    break;
                                case 'withdrawal':
                                    $total_withdrawals += abs($t['amount']);
                                    break;
                                case 'payout':
                                    $total_winnings += $t['amount'];
                                    break;
                                case 'hold':
                                    $total_spent += abs($t['amount']);
                                    break;
                            }
                        }
                        ?>
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <h6 class="text-success">Total Deposits</h6>
                                <h4 class="text-success">$<?php echo number_format($total_deposits, 2); ?></h4>
                            </div>
                            <div class="col-md-3 text-center">
                                <h6 class="text-warning">Total Withdrawals</h6>
                                <h4 class="text-warning">$<?php echo number_format($total_withdrawals, 2); ?></h4>
                            </div>
                            <div class="col-md-3 text-center">
                                <h6 class="text-info">Total Winnings</h6>
                                <h4 class="text-info">$<?php echo number_format($total_winnings, 2); ?></h4>
                            </div>
                            <div class="col-md-3 text-center">
                                <h6 class="text-danger">Total Spent</h6>
                                <h4 class="text-danger">$<?php echo number_format($total_spent, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>