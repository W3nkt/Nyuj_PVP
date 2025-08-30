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
$amount = floatval($input['amount'] ?? 0);
$user_id = getUserId();

if ($amount <= 0 || $amount < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount. Minimum withdrawal is $1.00']);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    $conn->beginTransaction();
    
    // Get user's wallet
    $stmt = $conn->prepare("SELECT id, balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch();
    
    if (!$wallet) {
        throw new Exception('Wallet not found');
    }
    
    if ($wallet['balance'] < $amount) {
        throw new Exception('Insufficient balance');
    }
    
    // Check held funds (simplified - just check basic balance for now)
    $held_amount = 0;
    try {
        // Try to get locked balance if column exists
        $stmt = $conn->prepare("SELECT locked_balance FROM wallets WHERE id = ?");
        $stmt->execute([$wallet['id']]);
        $result = $stmt->fetch();
        $held_amount = $result['locked_balance'] ?? 0;
    } catch (PDOException $e) {
        // locked_balance column doesn't exist, use basic check
    }
    
    $available_balance = $wallet['balance'] - $held_amount;
    
    if ($available_balance < $amount) {
        throw new Exception('Insufficient available balance. Available: $' . number_format($available_balance, 2));
    }
    
    // Deduct amount from wallet
    $new_balance = $wallet['balance'] - $amount;
    $stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
    $stmt->execute([$new_balance, $wallet['id']]);
    
    // Create withdrawal transaction
    $stmt = $conn->prepare("
        INSERT INTO transactions (wallet_id, type, amount, description, status, reference_id) 
        VALUES (?, 'withdrawal', ?, ?, 'completed', ?)
    ");
    $stmt->execute([
        $wallet['id'], 
        -$amount, 
        "Withdrawal from wallet",
        'DEMO_WD_' . uniqid()
    ]);
    
    // Log the action (optional - might not exist in all setups)
    try {
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (action, actor_id, target_type, target_id, new_value, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'wallet_withdrawal',
            $user_id,
            'wallet',
            $wallet['id'],
            json_encode(['amount' => $amount, 'new_balance' => $new_balance]),
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch (PDOException $audit_error) {
        // Audit logging failed but transaction should continue
        error_log('Audit log failed for withdrawal: ' . $audit_error->getMessage());
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Withdrawal successful! Funds will be processed within 24 hours.',
        'new_balance' => $new_balance,
        'amount' => $amount
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $conn->rollBack();
    error_log('Withdrawal error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Transaction failed. Please try again.']);
}
?>