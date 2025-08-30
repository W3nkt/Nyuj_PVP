<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Check if user is admin
if (!isLoggedIn() || getUserRole() !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid competitor ID']);
    exit;
}

$competitor_id = intval($_GET['id']);

try {
    $db = new Database();
    $conn = $db->connect();
    
    $stmt = $conn->prepare("SELECT * FROM competitor_info WHERE id = ?");
    $stmt->execute([$competitor_id]);
    $competitor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$competitor) {
        http_response_code(404);
        echo json_encode(['error' => 'Competitor not found']);
        exit;
    }
    
    // Return competitor data
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $competitor]);
    
} catch (Exception $e) {
    error_log('Get competitor error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
?>