<?php
/**
 * API Endpoint for Event Status Updates
 * Can be called via HTTP request or included in other scripts
 */

header('Content-Type: application/json');
require_once '../config/database.php';

// Allow both GET and POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET and POST requests allowed']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Get current time
    $current_time = date('Y-m-d H:i:s');
    $one_hour_from_now = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $updates = [];
    
    // Update events that should have betting closed (1 hour before match start)
    $stmt = $conn->prepare("
        UPDATE events 
        SET status = 'closed', 
            updated_at = NOW() 
        WHERE status = 'accepting_bets' 
        AND match_start_time IS NOT NULL 
        AND match_start_time <= ?
    ");
    
    $stmt->execute([$one_hour_from_now]);
    $betting_closed_count = $stmt->rowCount();
    $updates['betting_closed'] = $betting_closed_count;
    
    // Update events to 'live' status when match starts
    $stmt = $conn->prepare("
        UPDATE events 
        SET status = 'live', 
            updated_at = NOW() 
        WHERE status IN ('closed', 'ready') 
        AND match_start_time IS NOT NULL 
        AND match_start_time <= ?
    ");
    
    $stmt->execute([$current_time]);
    $live_count = $stmt->rowCount();
    $updates['live'] = $live_count;
    
    // Get current status summary
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as count 
        FROM events 
        GROUP BY status 
        ORDER BY status
    ");
    $stmt->execute();
    $status_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recently updated events
    $updated_events = [];
    if ($betting_closed_count > 0) {
        $stmt = $conn->prepare("
            SELECT id, name, status, match_start_time as start_time
            FROM events 
            WHERE status = 'closed' 
            AND updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stmt->execute();
        $updated_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'timestamp' => $current_time,
        'updates' => $updates,
        'summary' => $status_summary,
        'updated_events' => $updated_events,
        'message' => "Updated {$betting_closed_count} events to closed, {$live_count} events to live"
    ]);
    
    // Log significant updates
    if ($betting_closed_count > 0 || $live_count > 0) {
        error_log("Event Status Update API: {$betting_closed_count} betting closed, {$live_count} live");
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    error_log("Event Status Update API Error: " . $e->getMessage());
}
?>