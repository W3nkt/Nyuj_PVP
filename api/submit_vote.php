<?php
header('Content-Type: application/json');
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/AuditChain.php';

if (!isLoggedIn() || getUserRole() !== 'streamer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Streamer authentication required']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$event_id = intval($input['event_id'] ?? 0);
$winner_id = intval($input['winner_id'] ?? 0);
$notes = trim($input['notes'] ?? '');
$streamer_id = getUserId();

if (!$event_id || !$winner_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid event or winner ID']);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    $conn->beginTransaction();
    
    // Verify streamer is assigned to this event
    $stmt = $conn->prepare("SELECT id FROM event_streamers WHERE event_id = ? AND streamer_id = ?");
    $stmt->execute([$event_id, $streamer_id]);
    if (!$stmt->fetch()) {
        throw new Exception('You are not assigned to vote on this event');
    }
    
    // Verify event is in voting status
    $stmt = $conn->prepare("SELECT status FROM events WHERE id = ? AND status IN ('event_end', 'streamer_voting')");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    if (!$event) {
        throw new Exception('Event is not available for voting');
    }
    
    // Check if streamer already voted
    $stmt = $conn->prepare("SELECT id FROM votes WHERE event_id = ? AND streamer_id = ?");
    $stmt->execute([$event_id, $streamer_id]);
    if ($stmt->fetch()) {
        throw new Exception('You have already voted on this event');
    }
    
    // Verify winner is a participant in the event
    $stmt = $conn->prepare("SELECT id FROM event_participants WHERE event_id = ? AND user_id = ? AND status = 'active'");
    $stmt->execute([$event_id, $winner_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Selected winner is not a valid participant');
    }
    
    // Create vote hash for anonymity
    $vote_hash = hash('sha256', $streamer_id . $event_id . $winner_id . time());
    
    // Submit vote
    $stmt = $conn->prepare("
        INSERT INTO votes (event_id, streamer_id, voted_winner_id, vote_hash, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$event_id, $streamer_id, $winner_id, $vote_hash]);
    
    // Log the vote in audit chain
    $auditChain = new AuditChain();
    $auditChain->logVoteActivity($streamer_id, $event_id, [
        'voted_winner_id' => $winner_id,
        'vote_hash' => $vote_hash,
        'notes' => $notes,
        'competitor_a' => $event['competitor_a'],
        'competitor_b' => $event['competitor_b'],
        'previous_status' => $event['status']
    ]);
    
    // Update event status to voting if not already
    if ($event['status'] === 'event_end') {
        $stmt = $conn->prepare("UPDATE events SET status = 'streamer_voting' WHERE id = ?");
        $stmt->execute([$event_id]);
        
        // Log status change
        $auditChain->logEventActivity($streamer_id, $event_id, 'event_status_change', [
            'old_status' => 'event_end',
            'new_status' => 'streamer_voting',
            'trigger' => 'first_vote_submitted'
        ]);
    }
    
    // Check if all streamers have voted
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT es.streamer_id) as total_streamers,
            COUNT(DISTINCT v.streamer_id) as voted_streamers
        FROM event_streamers es 
        LEFT JOIN votes v ON es.event_id = v.event_id AND es.streamer_id = v.streamer_id
        WHERE es.event_id = ?
    ");
    $stmt->execute([$event_id]);
    $vote_status = $stmt->fetch();
    
    // If all streamers voted, determine winner
    if ($vote_status['voted_streamers'] >= $vote_status['total_streamers']) {
        // Get vote counts for each participant
        $stmt = $conn->prepare("
            SELECT voted_winner_id, COUNT(*) as vote_count 
            FROM votes 
            WHERE event_id = ? 
            GROUP BY voted_winner_id 
            ORDER BY vote_count DESC
        ");
        $stmt->execute([$event_id]);
        $vote_results = $stmt->fetchAll();
        
        if (!empty($vote_results)) {
            $winner_candidate = $vote_results[0];
            $majority_votes = $winner_candidate['vote_count'];
            $total_votes = $vote_status['voted_streamers'];
            
            // Check for tie (if top vote count is not majority)
            $tie_check = array_filter($vote_results, function($result) use ($majority_votes) {
                return $result['vote_count'] === $majority_votes;
            });
            
            if (count($tie_check) > 1) {
                // Tie situation - escalate to admin review
                $stmt = $conn->prepare("UPDATE events SET status = 'admin_review' WHERE id = ?");
                $stmt->execute([$event_id]);
            } else {
                // Clear winner - finalize event
                $final_winner_id = $winner_candidate['voted_winner_id'];
                
                // Update event with winner and status
                $stmt = $conn->prepare("UPDATE events SET winner_id = ?, status = 'final_result' WHERE id = ?");
                $stmt->execute([$final_winner_id, $event_id]);
                
                // Update participant statuses
                $stmt = $conn->prepare("UPDATE event_participants SET status = 'lost' WHERE event_id = ? AND user_id != ?");
                $stmt->execute([$event_id, $final_winner_id]);
                
                $stmt = $conn->prepare("UPDATE event_participants SET status = 'won' WHERE event_id = ? AND user_id = ?");
                $stmt->execute([$event_id, $final_winner_id]);
                
                // Process settlement
                processEventSettlement($conn, $event_id, $final_winner_id);
            }
        }
    }
    
    // Log the vote
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (action, actor_id, target_type, target_id, new_value, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'vote_submitted',
        $streamer_id,
        'event',
        $event_id,
        json_encode(['winner_id' => $winner_id, 'vote_hash' => $vote_hash]),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Vote submitted successfully!',
        'vote_count' => $vote_status['voted_streamers'] + 1,
        'total_streamers' => $vote_status['total_streamers']
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $conn->rollBack();
    error_log('Vote submission error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to submit vote. Please try again.']);
}

function processEventSettlement($conn, $event_id, $winner_id) {
    // Get event details
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) return;
    
    // Get winner's wallet
    $stmt = $conn->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $stmt->execute([$winner_id]);
    $winner_wallet = $stmt->fetch();
    
    if (!$winner_wallet) return;
    
    // Calculate winnings (total pool minus platform fee)
    $platform_fee = $event['total_pool'] * ($event['platform_fee_percent'] / 100);
    $winnings = $event['total_pool'] - $platform_fee;
    
    // Add winnings to winner's wallet
    $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE id = ?");
    $stmt->execute([$winnings, $winner_wallet['id']]);
    
    // Create payout transaction
    $stmt = $conn->prepare("
        INSERT INTO transactions (wallet_id, event_id, type, amount, description, status) 
        VALUES (?, ?, 'payout', ?, ?, 'completed')
    ");
    $stmt->execute([
        $winner_wallet['id'], 
        $event_id, 
        $winnings, 
        "Event winnings: {$event['name']}"
    ]);
    
    // Pay streamers their incentives
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'streamer_payment_per_vote'");
    $stmt->execute();
    $streamer_payment = floatval($stmt->fetch()['setting_value'] ?? 0.20);
    
    $stmt = $conn->prepare("
        SELECT DISTINCT v.streamer_id, u.username 
        FROM votes v 
        JOIN users u ON v.streamer_id = u.id 
        WHERE v.event_id = ?
    ");
    $stmt->execute([$event_id]);
    $voting_streamers = $stmt->fetchAll();
    
    foreach ($voting_streamers as $streamer) {
        // Get streamer wallet
        $stmt = $conn->prepare("SELECT id FROM wallets WHERE user_id = ?");
        $stmt->execute([$streamer['streamer_id']]);
        $streamer_wallet = $stmt->fetch();
        
        if ($streamer_wallet) {
            // Add payment to streamer wallet
            $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$streamer_payment, $streamer_wallet['id']]);
            
            // Create streamer payment transaction
            $stmt = $conn->prepare("
                INSERT INTO transactions (wallet_id, event_id, type, amount, description, status) 
                VALUES (?, ?, 'streamer_payment', ?, ?, 'completed')
            ");
            $stmt->execute([
                $streamer_wallet['id'], 
                $event_id, 
                $streamer_payment, 
                "Vote incentive for event: {$event['name']}"
            ]);
            
            // Mark vote as paid
            $stmt = $conn->prepare("UPDATE votes SET is_paid = TRUE WHERE event_id = ? AND streamer_id = ?");
            $stmt->execute([$event_id, $streamer['streamer_id']]);
        }
    }
    
    // Update event status to settlement
    $stmt = $conn->prepare("UPDATE events SET status = 'settlement' WHERE id = ?");
    $stmt->execute([$event_id]);
    
    // Finally close the event
    $stmt = $conn->prepare("UPDATE events SET status = 'closed' WHERE id = ?");
    $stmt->execute([$event_id]);
}
?>