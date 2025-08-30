<?php
header('Content-Type: application/json');
require_once '../config/session.php';
require_once '../config/database.php';

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
$event_id = intval($input['event_id'] ?? 0);
$user_id = getUserId();

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    $conn->beginTransaction();
    
    // Get event details
    $stmt = $conn->prepare("
        SELECT e.*, COUNT(ep.id) as participant_count 
        FROM events e 
        LEFT JOIN event_participants ep ON e.id = ep.event_id AND ep.status = 'active'
        WHERE e.id = ? AND e.status = 'waiting_for_players'
        GROUP BY e.id
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        throw new Exception('Event not found or not available for joining');
    }
    
    // Check if event is full
    if ($event['participant_count'] >= $event['max_participants']) {
        throw new Exception('Event is full');
    }
    
    // Check if user already joined
    $stmt = $conn->prepare("SELECT id FROM event_participants WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    if ($stmt->fetch()) {
        throw new Exception('You have already joined this event');
    }
    
    // Get user's wallet
    $stmt = $conn->prepare("SELECT id, balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch();
    
    if (!$wallet || $wallet['balance'] < $event['stake_amount']) {
        throw new Exception('Insufficient balance');
    }
    
    // Deduct stake from wallet
    $new_balance = $wallet['balance'] - $event['stake_amount'];
    $stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
    $stmt->execute([$new_balance, $wallet['id']]);
    
    // Create hold transaction
    $stmt = $conn->prepare("
        INSERT INTO transactions (wallet_id, event_id, type, amount, description, status) 
        VALUES (?, ?, 'hold', ?, ?, 'completed')
    ");
    $stmt->execute([
        $wallet['id'], 
        $event_id, 
        -$event['stake_amount'], 
        "Stake hold for event: {$event['name']}"
    ]);
    
    // Add user to event
    $stmt = $conn->prepare("
        INSERT INTO event_participants (event_id, user_id, stake, status) 
        VALUES (?, ?, ?, 'active')
    ");
    $stmt->execute([$event_id, $user_id, $event['stake_amount']]);
    
    // Update event total pool
    $new_pool = $event['total_pool'] + $event['stake_amount'];
    $stmt = $conn->prepare("UPDATE events SET total_pool = ? WHERE id = ?");
    $stmt->execute([$new_pool, $event_id]);
    
    // Check if event is now full and update status
    $new_participant_count = $event['participant_count'] + 1;
    if ($new_participant_count >= $event['max_participants']) {
        $stmt = $conn->prepare("UPDATE events SET status = 'ready' WHERE id = ?");
        $stmt->execute([$event_id]);
    }
    
    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (action, actor_id, target_type, target_id, new_value, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'event_joined',
        $user_id,
        'event',
        $event_id,
        json_encode(['stake_amount' => $event['stake_amount']]),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Successfully joined the event!',
        'new_balance' => $new_balance
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $conn->rollBack();
    error_log('Join event error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>