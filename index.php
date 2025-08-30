<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Nyuj PVP - Home';

// Add error handling for missing files
if (!file_exists('config/database.php')) {
    die('Configuration error: Database configuration file not found. Please check your installation.');
}

require_once 'includes/header.php';
require_once 'config/database.php';

// Get latest events for home page with error handling
try {
    $db = new Database();
    $conn = $db->connect();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    // If database connection fails, show a maintenance message
    echo '<div class="container mt-5"><div class="alert alert-warning"><h4>Database Connection Issue</h4><p>The platform is temporarily unavailable. Please try again later or contact support.</p><p><a href="test_connection.php" class="btn btn-outline-primary">Test Connection</a> <a href="setup.php" class="btn btn-outline-secondary">Run Setup</a></p></div></div>';
    require_once 'includes/footer.php';
    exit();
}

$stmt = $conn->prepare("
    SELECT e.*, 
           u.username as creator_name,
           COUNT(DISTINCT ba.id) as bets_on_a,
           COUNT(DISTINCT bb.id) as bets_on_b,
           COALESCE(SUM(CASE WHEN ba.status = 'matched' THEN ba.amount END), 0) as matched_amount_a,
           COALESCE(SUM(CASE WHEN bb.status = 'matched' THEN bb.amount END), 0) as matched_amount_b
    FROM events e 
    LEFT JOIN bets ba ON e.id = ba.event_id AND ba.bet_on = 'A' AND ba.status IN ('pending', 'matched')
    LEFT JOIN bets bb ON e.id = bb.event_id AND bb.bet_on = 'B' AND bb.status IN ('pending', 'matched')
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.status IN ('accepting_bets', 'live', 'streamer_voting')
    GROUP BY e.id 
    ORDER BY e.created_at DESC 
    LIMIT 6
");
$stmt->execute();
$featured_events = $stmt->fetchAll();
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <h1 class="hero-title">
            <img src="assets/logo/Bull_PVP_Trans.png" alt="Bull PVP Logo" class="hero-logo me-3">Nyuj PVP
        </h1>
        <p class="hero-subtitle">
            Compete in skill-based battles with real stakes. Fair, transparent, and secure.
        </p>
        
        <?php if (!isLoggedIn()): ?>
            <div class="mt-4">
                <a href="auth/register.php" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-user-plus me-2"></i>Get Started
                </a>
                <a href="auth/login.php" class="btn btn-outline-dark btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </a>
            </div>
        <?php else: ?>
            <div class="mt-4">
                <a href="user/events.php" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-calendar me-2"></i>View Matches
                </a>
                <a href="user/dashboard.php" class="btn btn-navy btn-lg">
                    <i class="fas fa-dashboard me-2"></i>Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Features Section -->
<section class="py-5">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-12">
                <h2 class="mb-4">How It Works</h2>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-gamepad fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">1. Join Competition</h5>
                        <p class="card-text">Choose an event and place your stake to join skill-based competitions.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-play fa-3x text-success mb-3"></i>
                        <h5 class="card-title">2. Compete</h5>
                        <p class="card-text">Battle against other players in fair, skill-based competitions.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-award fa-3x text-warning mb-3"></i>
                        <h5 class="card-title">3. Win Rewards</h5>
                        <p class="card-text">Winners receive the pooled stakes, verified by trusted streamers.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Events -->
<?php if (!empty($featured_events)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center mb-5">
                <h2>Featured Matches</h2>
                <p class="lead">Place your bets on these exciting competitions</p>
            </div>
        </div>
         
        <div class="row">
                    <?php foreach ($featured_events as $event): ?>
                    <div class="col-md-4 mb-4">
                            <div class="event-card" data-event-id="<?php echo $event['id']; ?>">
                        <div class="event-card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo htmlspecialchars($event['name']); ?></h5>
                                <span class="event-status status-<?php echo str_replace('_', '-', $event['status']); ?>">
                                    <?php echo strtoupper(str_replace('_', ' ', $event['status'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="event-card-body">
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">Game Type</small>
                                <div class="fw-bold"><?php echo htmlspecialchars($event['game_type']); ?></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Platform Fee</small>
                                <div class="fw-bold text-primary"><?php echo number_format($event['platform_fee_percent'], 1); ?>%</div>
                            </div>
                        </div>
                        
                        <div class="competitors-row mb-3">
                            <div class="row">
                                <div class="col-6">
                                    <div class="competitor-card text-center">
                                        <div class="d-flex align-items-center justify-content-center mb-2">
                                            <?php if (!empty($event['competitor_a_image'])): ?>
                                                <img src="<?php echo url($event['competitor_a_image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($event['competitor_a']); ?>" 
                                                     class="competitor-image-small">
                                            <?php else: ?>
                                                <div class="competitor-image-small bg-light d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-user text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="fw-bold"><?php echo htmlspecialchars($event['competitor_a']); ?></div>
                                        </div>
                                        <small class="text-muted"><?php echo $event['bets_on_a']; ?> bets</small>
                                        <div class="small text-success">$<?php echo number_format($event['matched_amount_a'], 2); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="competitor-card text-center">
                                        <div class="d-flex align-items-center justify-content-center mb-2">
                                            <?php if (!empty($event['competitor_b_image'])): ?>
                                                <img src="<?php echo url($event['competitor_b_image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($event['competitor_b']); ?>" 
                                                     class="competitor-image-small">
                                            <?php else: ?>
                                                <div class="competitor-image-small bg-light d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-user text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="fw-bold"><?php echo htmlspecialchars($event['competitor_b']); ?></div>
                                        </div>
                                        <small class="text-muted"><?php echo $event['bets_on_b']; ?> bets</small>
                                        <div class="small text-success">$<?php echo number_format($event['matched_amount_b'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($event['match_start_time']): ?>
                            <div class="mb-3">
                                <small class="text-muted">Match Start</small>
                                <div><?php echo date('M j, Y g:i A', strtotime($event['match_start_time'])); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isLoggedIn() && $event['status'] === 'accepting_bets'): ?>
                            <a href="user/events.php" class="btn btn-primary w-100">
                                <i class="fas fa-coins me-2"></i>Place Bet
                            </a>
                        <?php elseif (!isLoggedIn()): ?>
                            <a href="auth/login.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Bet
                            </a>
                        <?php elseif ($event['status'] === 'live'): ?>
                            <button class="btn btn-success w-100" disabled>
                                <i class="fas fa-play me-2"></i>Live Match
                            </button>
                        <?php else: ?>
                            <button class="btn btn-secondary w-100" disabled>
                                <?php echo ucwords(str_replace('_', ' ', $event['status'])); ?>
                            </button>
                        <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="user/events.php" class="btn btn-primary">
                <i class="fas fa-calendar me-2"></i>View All Matches
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Security & Trust Section -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center mb-5">
                <h2>Why Choose Nyuj PVP?</h2>
            </div>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-2 col-md-3 col-sm-6 mb-4">
                <div class="text-center">
                    <div class="icon-square bg-primary text-white mb-3 mx-auto d-flex align-items-center justify-content-center">
                        <i class="fas fa-shield-alt fa-2x"></i>
                    </div>
                    <h5>Secure</h5>
                    <p>All transactions are secured and funds are held in escrow until results are finalized.</p>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-3 col-sm-6 mb-4">
                <div class="text-center">
                    <div class="icon-square bg-success text-white mb-3 mx-auto d-flex align-items-center justify-content-center">
                        <i class="fas fa-eye fa-2x"></i>
                    </div>
                    <h5>Transparent</h5>
                    <p>Results are verified by trusted streamers with public voting records.</p>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-3 col-sm-6 mb-4">
                <div class="text-center">
                    <div class="icon-square bg-warning text-white mb-3 mx-auto d-flex align-items-center justify-content-center">
                        <i class="fas fa-balance-scale fa-2x"></i>
                    </div>
                    <h5>Fair</h5>
                    <p>Skill-based competitions with anti-collusion measures and reputation tracking.</p>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-3 col-sm-6 mb-4">
                <div class="text-center">
                    <div class="icon-square bg-danger text-white mb-3 mx-auto d-flex align-items-center justify-content-center">
                        <i class="fas fa-bolt fa-2x"></i>
                    </div>
                    <h5>Fast</h5>
                    <p>Quick payouts and instant settlement when results are confirmed.</p>
                </div>
            </div>
        </div>
    </div>
</section>


<?php require_once 'includes/footer.php'; ?>