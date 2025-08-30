<?php
/**
 * Automatic Event Status Update Script
 * This script updates event statuses based on match start times
 * - Changes "accepting_bets" to "betting_closed" 1 hour before match start
 * - Can be run as a cron job or called periodically
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Get current time
    $current_time = date('Y-m-d H:i:s');
    $one_hour_from_now = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    echo "Event Status Update Script - " . $current_time . "\n";
    echo "========================================\n";
    
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
    
    if ($betting_closed_count > 0) {
        echo "✅ Updated {$betting_closed_count} events to 'closed' status\n";
        
        // Get the updated events for logging
        $stmt = $conn->prepare("
            SELECT id, name, status, match_start_time as start_time
            FROM events 
            WHERE status = 'closed' 
            AND updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stmt->execute();
        $updated_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($updated_events as $event) {
            echo "  - Event #{$event['id']}: {$event['name']} (Start: {$event['start_time']})\n";
        }
    } else {
        echo "ℹ️  No events needed status update to 'closed'\n";
    }
    
    // Optional: Update events to 'live' status when match starts
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
    
    if ($live_count > 0) {
        echo "🔴 Updated {$live_count} events to 'live' status\n";
    }
    
    // Show current status summary
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as count 
        FROM events 
        GROUP BY status 
        ORDER BY status
    ");
    $stmt->execute();
    $status_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nCurrent Event Status Summary:\n";
    echo "-----------------------------\n";
    foreach ($status_summary as $summary) {
        echo "  {$summary['status']}: {$summary['count']} events\n";
    }
    
    echo "\n✅ Event status update completed successfully!\n";
    
    // Log the activity (if logging is enabled)
    if ($betting_closed_count > 0 || $live_count > 0) {
        error_log("Event Status Update: {$betting_closed_count} closed, {$live_count} live");
    }
    
} catch (Exception $e) {
    echo "❌ Error updating event statuses: " . $e->getMessage() . "\n";
    error_log("Event Status Update Error: " . $e->getMessage());
    exit(1);
}
?>