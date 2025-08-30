<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET requests allowed');
    }

    $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

    if (!$event_id) {
        throw new Exception('Event ID is required');
    }

    $db = new Database();
    $conn = $db->connect();

    // Get event details
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception('Event not found');
    }

    // Determine the match start time
    $match_start_time = null;
    if (!empty($event['match_start_time_1'])) {
        $match_start_time = $event['match_start_time_1'];
    } elseif (!empty($event['event_start_time'])) {
        $match_start_time = $event['event_start_time'];
    } elseif (!empty($event['match_start_time'])) {
        $match_start_time = $event['match_start_time'];
    }

    $current_time = time();
    $current_status = $event['status'];
    $original_status = $current_status;
    $time_until_betting_closes = null;
    $time_until_match_starts = null;
    $should_update_status = false;

    if ($match_start_time) {
        $match_timestamp = strtotime($match_start_time);
        $betting_closes_timestamp = $match_timestamp - (60 * 60); // 1 hour before
        
        $time_until_betting_closes = $betting_closes_timestamp - $current_time;
        $time_until_match_starts = $match_timestamp - $current_time;

        // Auto-update status based on time
        if ($current_status === 'accepting_bets' && $current_time >= $betting_closes_timestamp) {
            $current_status = 'betting_closed';
            $should_update_status = true;
        } elseif ($current_status === 'betting_closed' && $current_time >= $match_timestamp) {
            $current_status = 'live';
            $should_update_status = true;
        }

        // Update database if status changed
        if ($should_update_status) {
            $update_stmt = $conn->prepare("UPDATE events SET status = ?, updated_at = NOW() WHERE id = ?");
            $update_stmt->execute([$current_status, $event_id]);
        }
    }

    // Format time remaining messages
    $betting_status_message = '';
    $match_status_message = '';

    if ($time_until_betting_closes !== null) {
        if ($time_until_betting_closes > 0) {
            $hours = floor($time_until_betting_closes / 3600);
            $minutes = floor(($time_until_betting_closes % 3600) / 60);
            if ($hours > 0) {
                $betting_status_message = "Betting closes in {$hours}h {$minutes}m";
            } else {
                $betting_status_message = "Betting closes in {$minutes} minutes";
            }
        } else {
            $betting_status_message = "Betting has closed";
        }
    }

    if ($time_until_match_starts !== null) {
        if ($time_until_match_starts > 0) {
            $hours = floor($time_until_match_starts / 3600);
            $minutes = floor(($time_until_match_starts % 3600) / 60);
            if ($hours > 0) {
                $match_status_message = "Match starts in {$hours}h {$minutes}m";
            } else {
                $match_status_message = "Match starts in {$minutes} minutes";
            }
        } else {
            $match_status_message = "Match has started";
        }
    }

    // Return the response
    echo json_encode([
        'success' => true,
        'event_id' => $event_id,
        'status' => $current_status,
        'original_status' => $original_status,
        'status_updated' => $should_update_status,
        'match_start_time' => $match_start_time,
        'time_until_betting_closes' => $time_until_betting_closes,
        'time_until_match_starts' => $time_until_match_starts,
        'betting_status_message' => $betting_status_message,
        'match_status_message' => $match_status_message,
        'can_place_bets' => ($current_status === 'accepting_bets' && $time_until_betting_closes > 0),
        'event_name' => $event['name'] ?? 'Unknown Event'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>