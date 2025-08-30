<?php
header('Content-Type: application/json');
require_once '../config/session.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$event_id = intval($_GET['event_id'] ?? 0);

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    // Verify user has access to view participants (streamers assigned to event or admin)
    $user_id = getUserId();
    $user_role = getUserRole();
    
    if ($user_role === 'streamer') {
        $stmt = $conn->prepare("SELECT id FROM event_streamers WHERE event_id = ? AND streamer_id = ?");
        $stmt->execute([$event_id, $user_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Access denied');
        }
    } elseif ($user_role !== 'admin') {
        throw new Exception('Access denied');
    }
    
    // Get event participants
    $stmt = $conn->prepare("
        SELECT ep.*, u.username, u.id as user_id
        FROM event_participants ep
        JOIN users u ON ep.user_id = u.id
        WHERE ep.event_id = ? AND ep.status = 'active'
        ORDER BY ep.joined_at ASC
    ");
    $stmt->execute([$event_id]);
    $participants = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'participants' => $participants
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    error_log('Event participants error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch participants']);
}
?>