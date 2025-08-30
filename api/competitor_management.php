<?php
header('Content-Type: application/json');
require_once '../config/session.php';
require_once '../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET requests allowed');
    }

    $action = isset($_GET['action']) ? $_GET['action'] : '';

    switch ($action) {
        case 'search':
            handleSearchCompetitors();
            break;
        case 'get_competitor':
            handleGetCompetitor();
            break;
        case 'list_all':
            handleListAllCompetitors();
            break;
        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleSearchCompetitors() {
    global $conn;
    
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    $status = isset($_GET['status']) ? $_GET['status'] : 'active';
    
    if (empty($query)) {
        throw new Exception('Search query is required');
    }
    
    $db = new Database();
    $conn = $db->connect();
    
    $stmt = $conn->prepare("
        SELECT id, name, nickname, fighting_style, nationality, profile_image, 
               total_fights, wins, losses, draws, win_rate, status
        FROM competitor_info 
        WHERE (name LIKE ? OR nickname LIKE ? OR fighting_style LIKE ? OR nationality LIKE ?) 
              AND status = ?
        ORDER BY name ASC
        LIMIT 20
    ");
    
    $searchTerm = "%{$query}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $status]);
    $competitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $competitors,
        'count' => count($competitors)
    ]);
}

function handleGetCompetitor() {
    global $conn;
    
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $name = isset($_GET['name']) ? trim($_GET['name']) : '';
    
    if (!$id && !$name) {
        throw new Exception('Competitor ID or name is required');
    }
    
    $db = new Database();
    $conn = $db->connect();
    
    if ($id) {
        $stmt = $conn->prepare("SELECT * FROM competitor_info WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM competitor_info WHERE name = ?");
        $stmt->execute([$name]);
    }
    
    $competitor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$competitor) {
        throw new Exception('Competitor not found');
    }
    
    // Get recent match history
    $stmt = $conn->prepare("
        SELECT 
            e.name as event_name,
            e.competitor_a,
            e.competitor_b,
            e.winner,
            e.event_end_time as match_date,
            e.status as event_status
        FROM events e
        WHERE (e.competitor_a = ? OR e.competitor_b = ?) 
            AND e.status IN ('completed', 'live')
        ORDER BY e.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$competitor['name'], $competitor['name']]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process match history
    $history = [];
    foreach ($matches as $match) {
        $opponent = $match['competitor_a'] === $competitor['name'] ? 
                   $match['competitor_b'] : $match['competitor_a'];
        
        $result = 'L'; // Default to loss
        if ($match['winner'] === 'A' && $match['competitor_a'] === $competitor['name']) {
            $result = 'W';
        } elseif ($match['winner'] === 'B' && $match['competitor_b'] === $competitor['name']) {
            $result = 'W';
        } elseif ($match['winner'] === 'draw') {
            $result = 'D';
        }
        
        $history[] = [
            'result' => $result,
            'opponent' => $opponent,
            'event_name' => $match['event_name'],
            'date' => $match['match_date'] ? date('M j, Y', strtotime($match['match_date'])) : 'TBD',
            'status' => $match['event_status']
        ];
    }
    
    $competitor['match_history'] = $history;
    
    echo json_encode([
        'success' => true,
        'data' => $competitor
    ]);
}

function handleListAllCompetitors() {
    global $conn;
    
    $status = isset($_GET['status']) ? $_GET['status'] : 'active';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    $db = new Database();
    $conn = $db->connect();
    
    $stmt = $conn->prepare("
        SELECT id, name, nickname, fighting_style, nationality, profile_image, 
               age, height, weight, total_fights, wins, losses, draws, win_rate, status
        FROM competitor_info 
        WHERE status = ?
        ORDER BY name ASC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$status, $limit, $offset]);
    $competitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM competitor_info WHERE status = ?");
    $countStmt->execute([$status]);
    $total = $countStmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => $competitors,
        'total' => intval($total),
        'limit' => $limit,
        'offset' => $offset
    ]);
}
?>