<?php
header('Content-Type: application/json');
require_once '../config/session.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$user_id = getUserId();
$db = new Database();
$conn = $db->connect();

try {
    $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch();
    
    if (!$wallet) {
        echo json_encode(['success' => false, 'message' => 'Wallet not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'balance' => $wallet['balance']
    ]);
    
} catch (PDOException $e) {
    error_log('Wallet balance error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch balance']);
}
?>