<?php
header('Content-Type: application/json');
require_once '../config/session.php';
require_once '../config/database.php';

$event_id = intval($_GET['event_id'] ?? 0);

if (!$event_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Event ID required']);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    // Get event details
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        throw new Exception('Event not found');
    }
    
    $now = new DateTime();
    $betting_allowed = false;
    $betting_closes_at = null;
    $time_until_betting_closes = null;
    $time_until_event_starts = null;
    $betting_status_message = '';
    
    if ($event['match_start_time_1']) {
        $match_start = new DateTime($event['match_start_time_1']);
        $betting_cutoff = clone $match_start;
        $betting_cutoff->sub(new DateInterval('PT1H')); // 1 hour before start
        
        $betting_closes_at = $betting_cutoff->format('Y-m-d H:i:s');
        
        // Calculate time differences
        if ($now < $betting_cutoff) {
            $betting_allowed = true;
            $time_diff = $betting_cutoff->diff($now);
            $total_minutes = ($time_diff->days * 24 * 60) + ($time_diff->h * 60) + $time_diff->i;
            $time_until_betting_closes = [
                'total_minutes' => $total_minutes,
                'hours' => floor($total_minutes / 60),
                'minutes' => $total_minutes % 60,
                'formatted' => $total_minutes >= 60 
                    ? floor($total_minutes / 60) . 'h ' . ($total_minutes % 60) . 'm'
                    : $total_minutes . 'm'
            ];
            
            if ($total_minutes <= 60) {
                $betting_status_message = "Betting closes in {$time_until_betting_closes['formatted']}!";
            } else {
                $betting_status_message = "Betting open - closes {$time_until_betting_closes['formatted']} before match starts";
            }
        } else {
            $betting_allowed = false;
            if ($now >= $match_start) {
                $betting_status_message = "Betting is closed - Match has started";
            } else {
                $time_until_start = $match_start->diff($now);
                $minutes_until_start = ($time_until_start->h * 60) + $time_until_start->i;
                $betting_status_message = "Betting is closed - Match starts in {$minutes_until_start} minutes";
            }
        }
        
        // Time until match starts
        if ($now < $match_start) {
            $time_diff = $match_start->diff($now);
            $total_minutes = ($time_diff->days * 24 * 60) + ($time_diff->h * 60) + $time_diff->i;
            $time_until_event_starts = [
                'total_minutes' => $total_minutes,
                'hours' => floor($total_minutes / 60),
                'minutes' => $total_minutes % 60,
                'formatted' => $total_minutes >= 60 
                    ? floor($total_minutes / 60) . 'h ' . ($total_minutes % 60) . 'm'
                    : $total_minutes . 'm'
            ];
        }
    } else {
        // No start time set
        $betting_allowed = in_array($event['status'], ['created', 'waiting_for_players', 'accepting_bets']);
        $betting_status_message = $betting_allowed 
            ? "Betting open - Match start time TBD"
            : "Betting not available for this match";
    }
    
    // Check if event status allows betting
    if ($betting_allowed && !in_array($event['status'], ['created', 'waiting_for_players', 'accepting_bets'])) {
        $betting_allowed = false;
        $betting_status_message = "Betting closed - Event status: " . ucwords(str_replace('_', ' ', $event['status']));
    }
    
    // Get current bet count and amounts for this event if betting is active
    $betting_stats = null;
    if (in_array($event['status'], ['created', 'waiting_for_players', 'accepting_bets', 'ready', 'live'])) {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_bets,
                SUM(amount) as total_amount,
                bet_on,
                COUNT(*) as bet_count,
                SUM(amount) as bet_amount
            FROM bets 
            WHERE event_id = ? 
            GROUP BY bet_on
        ");
        $stmt->execute([$event_id]);
        $bet_results = $stmt->fetchAll();
        
        $betting_stats = [
            'competitor_a' => ['count' => 0, 'amount' => 0],
            'competitor_b' => ['count' => 0, 'amount' => 0]
        ];
        
        foreach ($bet_results as $result) {
            if ($result['bet_on'] === 'A') {
                $betting_stats['competitor_a'] = [
                    'count' => (int)$result['bet_count'],
                    'amount' => (float)$result['bet_amount']
                ];
            } elseif ($result['bet_on'] === 'B') {
                $betting_stats['competitor_b'] = [
                    'count' => (int)$result['bet_count'],
                    'amount' => (float)$result['bet_amount']
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'betting_allowed' => $betting_allowed,
        'betting_closes_at' => $betting_closes_at,
        'time_until_betting_closes' => $time_until_betting_closes,
        'time_until_event_starts' => $time_until_event_starts,
        'betting_status_message' => $betting_status_message,
        'event_status' => $event['status'],
        'event_start_time' => $event['event_start_time'],
        'match_start_time_1' => $event['match_start_time_1'],
        'betting_stats' => $betting_stats
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    error_log('Betting status error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>