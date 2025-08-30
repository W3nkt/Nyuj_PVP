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

$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);
$amount = floatval($input['amount'] ?? 0);
$user_id = getUserId();

if ($amount <= 0 || $amount < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount. Minimum deposit is $1.00']);
    exit();
}

if ($amount > 10000) {
    echo json_encode(['success' => false, 'message' => 'Maximum deposit limit is $10,000.00']);
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    $conn->beginTransaction();
    
    // Debug logging
    error_log("Deposit: Starting transaction for user_id: " . $user_id . ", amount: " . $amount);
    
    // Get user's wallet or create one if it doesn't exist
    $stmt = $conn->prepare("SELECT id, balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch();
    
    error_log("Deposit: Wallet query result: " . json_encode($wallet));
    
    if (!$wallet) {
        // Create wallet if it doesn't exist
        error_log("Deposit: Creating new wallet for user_id: " . $user_id);
        $stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
        $stmt->execute([$user_id]);
        
        // Get the newly created wallet
        $stmt = $conn->prepare("SELECT id, balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $wallet = $stmt->fetch();
        
        error_log("Deposit: New wallet created: " . json_encode($wallet));
        
        if (!$wallet) {
            throw new Exception('Unable to create wallet');
        }
    }
    
    // In a real application, this would integrate with a payment processor
    // For demo purposes, we'll simulate a successful payment
    
    // Add amount to wallet
    $old_balance = $wallet['balance'];
    $new_balance = $old_balance + $amount;
    
    error_log("Deposit: Updating balance from " . $old_balance . " to " . $new_balance . " for wallet_id: " . $wallet['id']);
    
    $stmt = $conn->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
    $result = $stmt->execute([$new_balance, $wallet['id']]);
    
    error_log("Deposit: Wallet update result: " . ($result ? 'success' : 'failed'));
    
    // Create deposit transaction using correct schema
    try {
        error_log("Deposit: Attempting to insert transaction");
        $stmt = $conn->prepare("
            INSERT INTO transactions (wallet_id, type, amount, balance_before, balance_after, description, reference_id, status, processed_at) 
            VALUES (?, 'deposit', ?, ?, ?, ?, ?, 'completed', NOW())
        ");
        $transaction_result = $stmt->execute([
            $wallet['id'], 
            $amount,
            $old_balance,
            $new_balance, 
            "Deposit to wallet",
            'DEMO_' . uniqid()
        ]);
        error_log("Deposit: Transaction insert result: " . ($transaction_result ? 'success' : 'failed'));
    } catch (PDOException $trans_error) {
        error_log("Deposit: Transaction insert failed: " . $trans_error->getMessage());
        // Try simplified transaction insert if full table doesn't exist
        try {
            $stmt = $conn->prepare("
                INSERT INTO transactions (wallet_id, type, amount, description) 
                VALUES (?, 'deposit', ?, ?)
            ");
            $stmt->execute([
                $wallet['id'], 
                $amount, 
                "Deposit to wallet"
            ]);
        } catch (PDOException $trans_error2) {
            // If transactions table doesn't exist, continue without it
            // The wallet balance update is the most important part
            error_log('Transaction logging failed: ' . $trans_error2->getMessage());
        }
    }
    
    // Log the action (optional - might not exist in all setups)
    try {
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (action, actor_id, target_type, target_id, new_value, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'wallet_deposit',
            $user_id,
            'wallet',
            $wallet['id'],
            json_encode(['amount' => $amount, 'new_balance' => $new_balance]),
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch (PDOException $audit_error) {
        // Audit logging failed but transaction should continue
        error_log('Audit log failed for deposit: ' . $audit_error->getMessage());
    }
    
    $conn->commit();
    
    error_log("Deposit: Transaction committed successfully for user_id: " . $user_id . ", new balance: " . $new_balance);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Deposit successful!',
        'new_balance' => $new_balance,
        'amount' => $amount,
        'debug' => [
            'user_id' => $user_id,
            'wallet_id' => $wallet['id'],
            'old_balance' => $old_balance,
            'new_balance' => $new_balance
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log('Deposit Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'error' => 'Exception']);
} catch (PDOException $e) {
    $conn->rollBack();
    error_log('Deposit PDO error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'error' => 'PDOException']);
}
?>