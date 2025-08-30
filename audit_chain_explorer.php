<?php
$page_title = 'Audit Chain Explorer - Bull PVP';
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'config/AuditChain.php';

requireRole('admin'); // Only admins can view audit chain

try {
    $db = new Database();
    $conn = $db->connect();
    $auditChain = new AuditChain();
    
    // Pagination
    $page = intval($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Get total transaction count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM audit_transactions");
    $stmt->execute();
    $total_transactions = $stmt->fetch()['total'];
    
    // Get transactions
    $transactions = $auditChain->getAllTransactions($limit, $offset);
    
    // Get chain integrity status
    $integrity_check = $auditChain->verifyChainIntegrity();
    
    // Get some statistics
    $stmt = $conn->prepare("
        SELECT 
            transaction_type,
            COUNT(*) as count,
            SUM(amount) as total_amount
        FROM audit_transactions 
        GROUP BY transaction_type
    ");
    $stmt->execute();
    $type_stats = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = 'Error loading audit chain data: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-chain"></i> Audit Chain Explorer</h2>
                <div class="btn-group">
                    <a href="admin/index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Admin
                    </a>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php else: ?>

            <!-- Chain Status -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5>Chain Status</h5>
                            <p class="mb-0">
                                <?php if ($integrity_check['valid']): ?>
                                    <i class="fas fa-check-circle"></i> Valid
                                <?php else: ?>
                                    <i class="fas fa-exclamation-triangle"></i> Invalid
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5>Total Transactions</h5>
                            <p class="mb-0"><?= number_format($total_transactions) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5>Total Users</h5>
                            <p class="mb-0">
                                <?php
                                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_balances WHERE balance > 0");
                                $stmt->execute();
                                echo number_format($stmt->fetch()['count']);
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5>Total Volume</h5>
                            <p class="mb-0">
                                $<?php
                                $stmt = $conn->prepare("SELECT SUM(amount) as total FROM audit_transactions WHERE transaction_type IN ('deposit', 'withdrawal', 'bet_place')");
                                $stmt->execute();
                                echo number_format($stmt->fetch()['total'] ?? 0, 2);
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Type Statistics -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Transaction Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($type_stats as $stat): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="text-center">
                                        <h6><?= ucfirst(str_replace('_', ' ', $stat['transaction_type'])) ?></h6>
                                        <p class="mb-1"><?= number_format($stat['count']) ?> transactions</p>
                                        <p class="text-muted">$<?= number_format($stat['total_amount'], 2) ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Transaction History</h5>
                    <small class="text-muted">
                        Page <?= $page ?> of <?= ceil($total_transactions / $limit) ?>
                    </small>
                </div>
                <div class="card-body">
                    <?php if (!$integrity_check['valid']): ?>
                        <div class="alert alert-danger">
                            <strong>Chain Integrity Error:</strong> <?= htmlspecialchars($integrity_check['error']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Hash</th>
                                    <th>Type</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Amount</th>
                                    <th>Details</th>
                                    <th>Date</th>
                                    <th>Previous Hash</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td>
                                        <code class="small" title="<?= htmlspecialchars($tx['transaction_hash']) ?>">
                                            <?= substr(htmlspecialchars($tx['transaction_hash']), 0, 12) ?>...
                                        </code>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php
                                            $badge_color = 'secondary';
                                            switch($tx['transaction_type']) {
                                                case 'deposit':
                                                case 'bet_win':
                                                    $badge_color = 'success';
                                                    break;
                                                case 'withdrawal':
                                                    $badge_color = 'warning';
                                                    break;
                                                case 'bet_place':
                                                case 'bet_match':
                                                    $badge_color = 'primary';
                                                    break;
                                                case 'transfer':
                                                case 'user_login':
                                                case 'user_register':
                                                    $badge_color = 'info';
                                                    break;
                                                case 'event_create':
                                                case 'event_update':
                                                case 'event_start':
                                                    $badge_color = 'secondary';
                                                    break;
                                                case 'vote_submit':
                                                case 'voting_open':
                                                    $badge_color = 'dark';
                                                    break;
                                                case 'event_complete':
                                                case 'event_status_change':
                                                    $badge_color = 'light text-dark';
                                                    break;
                                                case 'admin_action':
                                                    $badge_color = 'danger';
                                                    break;
                                                case 'security_alert':
                                                    $badge_color = 'warning';
                                                    break;
                                                case 'competitor_create':
                                                case 'competitor_update':
                                                    $badge_color = 'success';
                                                    break;
                                            }
                                            echo $badge_color;
                                        ?>">
                                            <?= ucfirst(str_replace('_', ' ', $tx['transaction_type'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $tx['from_username'] ? htmlspecialchars($tx['from_username']) : 
                                           ($tx['from_user_id'] ? 'User #' . $tx['from_user_id'] : 'System') ?>
                                    </td>
                                    <td>
                                        <?= $tx['to_username'] ? htmlspecialchars($tx['to_username']) : 
                                           ($tx['to_user_id'] ? 'User #' . $tx['to_user_id'] : 'System') ?>
                                    </td>
                                    <td>
                                        <?php if ($tx['amount'] > 0): ?>
                                            $<?= number_format($tx['amount'], 2) ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php 
                                            $data = json_decode($tx['data'], true);
                                            if ($data) {
                                                switch($tx['transaction_type']) {
                                                    case 'user_register':
                                                        echo "User: " . htmlspecialchars($data['username'] ?? 'Unknown');
                                                        break;
                                                    case 'user_login':
                                                        echo "Login from " . htmlspecialchars($data['ip_address'] ?? 'Unknown IP');
                                                        break;
                                                    case 'event_create':
                                                        echo "Event: " . htmlspecialchars($data['name'] ?? 'Unknown Event');
                                                        break;
                                                    case 'event_status_change':
                                                        echo htmlspecialchars($data['old_status'] ?? '') . " → " . htmlspecialchars($data['new_status'] ?? '');
                                                        break;
                                                    case 'vote_submit':
                                                        echo "Vote for competitor " . htmlspecialchars($data['voted_winner_id'] ?? 'Unknown');
                                                        break;
                                                    case 'admin_action':
                                                        echo "Action: " . htmlspecialchars($data['action'] ?? 'Unknown');
                                                        break;
                                                    case 'bet_place':
                                                        echo "Bet on " . htmlspecialchars($data['competitor'] ?? 'Unknown');
                                                        break;
                                                    default:
                                                        echo substr(json_encode($data), 0, 50) . "...";
                                                }
                                            }
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small><?= date('M j, Y H:i', $tx['timestamp']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($tx['previous_hash']): ?>
                                            <code class="small" title="<?= htmlspecialchars($tx['previous_hash']) ?>">
                                                <?= substr(htmlspecialchars($tx['previous_hash']), 0, 8) ?>...
                                            </code>
                                        <?php else: ?>
                                            <span class="text-muted">Genesis</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_transactions > $limit): ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php 
                            $total_pages = ceil($total_transactions / $limit);
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.table td {
    vertical-align: middle;
}
.badge {
    font-size: 0.75em;
}
code {
    font-size: 0.8em;
}
</style>

<?php include 'includes/footer.php'; ?>