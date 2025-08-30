<?php
header('Content-Type: application/json');
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/AuditChain.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$match_id = intval($input['match_id'] ?? 0);
$competitor = trim($input['competitor'] ?? '');
$amount = floatval($input['amount'] ?? 0);
$user_id = getUserId();

if (!$match_id || !in_array($competitor, ['A', 'B']) || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->connect();
    $auditChain = new AuditChain();
    
    // Get match details
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch();
    
    if (!$match) {
        throw new Exception('Match not found');
    }
    
    // Check if betting is still allowed (must close 1 hour before start)
    $now = new DateTime();
    $betting_cutoff = null;
    
    if ($match['match_start_time_1']) {
        $match_start = new DateTime($match['match_start_time_1']);
        $betting_cutoff = clone $match_start;
        $betting_cutoff->sub(new DateInterval('PT1H')); // Subtract 1 hour
        
        if ($now >= $betting_cutoff) {
            $time_until_start = $match_start->diff($now);
            if ($now >= $match_start) {
                throw new Exception('Betting is closed. The match has already started.');
            } else {
                $minutes_left = $time_until_start->i + ($time_until_start->h * 60);
                throw new Exception("Betting is closed. Match starts in {$minutes_left} minutes. Betting closes 1 hour before start time.");
            }
        }
    }
    
    // Check if event status allows betting
    if (!in_array($match['status'], ['created', 'waiting_for_players', 'accepting_bets'])) {
        throw new Exception('Event is not accepting bets at this time');
    }
    
    // Validate bet limits
    if ($amount < 1.00) {
        throw new Exception('Minimum bet amount is $1.00');
    }
    
    if ($amount > 10000.00) {
        throw new Exception('Maximum bet amount is $10,000.00');
    }
    
    // Check balance
    $balance = $auditChain->getUserBalance($user_id);
    if ($balance < $amount) {
        throw new Exception('Insufficient balance: ' . number_format($balance, 2));
    }
    
    // Create bet transaction data
    $bet_data = [
        'match_id' => $match_id,
        'competitor' => $competitor,
        'competitor_name' => $match['competitor_' . strtolower($competitor)],
        'match_name' => $match['name'],
        'user_id' => $user_id,
        'transaction_type' => 'bet_place'
    ];
    
    // Create bet transaction in audit chain
    $transaction_hash = $auditChain->createTransaction(
        $user_id,
        null, // Platform
        $amount,
        'bet_place',
        $bet_data
    );
    
    // Create bet record with audit chain transaction hash
    $stmt = $conn->prepare("
        INSERT INTO bets (event_id, user_id, bet_on, amount, status, placed_at, transaction_hash) 
        VALUES (?, ?, ?, ?, 'pending', NOW(), ?)
    ");
    $stmt->execute([$match_id, $user_id, $competitor, $amount, $transaction_hash]);
    
    $bet_id = $conn->lastInsertId();
    
    // Transaction is immediately confirmed in audit chain, try to match bet
    $opposing_competitor = $competitor === 'A' ? 'B' : 'A';
    
    // Look for matching bet
    $stmt = $conn->prepare("
        SELECT * FROM bets 
        WHERE event_id = ? 
        AND bet_on = ? 
        AND amount = ? 
        AND status = 'pending' 
        AND user_id != ?
        ORDER BY placed_at ASC 
        LIMIT 1
    ");
    $stmt->execute([$match_id, $opposing_competitor, $amount, $user_id]);
    $matching_bet = $stmt->fetch();
    
    if ($matching_bet) {
            
        // Match found - create match transaction
        $match_data = [
            'bet_id_1' => $bet_id,
            'bet_id_2' => $matching_bet['id'],
            'match_id' => $match_id,
            'amount' => $amount
        ];
        
        $match_transaction_hash = $auditChain->createTransaction(
            null,
            null,
            0, // No transfer, just logging
            'bet_match',
            $match_data
        );
        
        // Update both bets to matched status
        $stmt = $conn->prepare("
            UPDATE bets 
            SET status = 'matched', matched_at = NOW(), match_transaction_hash = ?
            WHERE id IN (?, ?)
        ");
        $stmt->execute([$match_transaction_hash, $bet_id, $matching_bet['id']]);
        
        $message = "Bet placed and matched! Transaction hash: " . substr($transaction_hash, 0, 16) . "...";
    } else {
        $message = "Bet placed! Waiting for matching bet. Transaction hash: " . substr($transaction_hash, 0, 16) . "...";
    }
    
    // Get updated balance
    $new_balance = $auditChain->getUserBalance($user_id);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'bet_id' => $bet_id,
        'transaction_hash' => $transaction_hash,
        'new_balance' => $new_balance,
        'confirmed' => true,
        'matched' => isset($matching_bet) && $matching_bet !== false
    ]);
    
} catch (Exception $e) {
    error_log('Bet error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>