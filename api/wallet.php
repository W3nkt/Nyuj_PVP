<?php
header('Content-Type: application/json');
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/AuditChain.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$action = $_GET['action'] ?? '';
$user_id = getUserId();

try {
    $auditChain = new AuditChain();
    
    switch ($action) {
        case 'balance':
            $balance = $auditChain->getUserBalance($user_id);
            
            echo json_encode([
                'success' => true,
                'user_id' => $user_id,
                'balance' => $balance,
                'formatted_balance' => number_format($balance, 2)
            ]);
            break;
            
        case 'deposit':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $amount = floatval($input['amount'] ?? 0);
            
            if ($amount < 1) {
                throw new Exception('Minimum deposit is $1.00');
            }
            
            if ($amount > 10000) {
                throw new Exception('Maximum deposit is $10,000.00');
            }
            
            // Create deposit transaction
            $deposit_data = [
                'user_id' => $user_id,
                'deposit_method' => 'platform_credit'
            ];
            
            $transaction_hash = $auditChain->deposit($user_id, $amount, 'platform_credit');
            $new_balance = $auditChain->getUserBalance($user_id);
            
            echo json_encode([
                'success' => true,
                'message' => 'Deposit successful',
                'transaction_hash' => $transaction_hash,
                'amount' => $amount,
                'new_balance' => $new_balance
            ]);
            break;
            
        case 'withdraw':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $amount = floatval($input['amount'] ?? 0);
            
            if ($amount < 10) {
                throw new Exception('Minimum withdrawal is $10.00');
            }
            
            $balance = $auditChain->getUserBalance($user_id);
            if ($balance < $amount) {
                throw new Exception('Insufficient balance');
            }
            
            // Create withdrawal transaction
            $transaction_hash = $auditChain->withdraw($user_id, $amount, 'platform_withdrawal');
            $new_balance = $auditChain->getUserBalance($user_id);
            
            echo json_encode([
                'success' => true,
                'message' => 'Withdrawal processed',
                'transaction_hash' => $transaction_hash,
                'amount' => $amount,
                'fee' => 2.00,
                'new_balance' => $new_balance
            ]);
            break;
            
        case 'history':
            $limit = intval($_GET['limit'] ?? 50);
            $transactions = $auditChain->getTransactionHistory($user_id, $limit);
            
            // Format transactions for display
            $formatted_transactions = array_map(function($tx) use ($user_id) {
                $is_incoming = ($tx['to_user_id'] == $user_id);
                $is_outgoing = ($tx['from_user_id'] == $user_id);
                
                return [
                    'hash' => $tx['transaction_hash'],
                    'type' => $tx['transaction_type'],
                    'amount' => floatval($tx['amount']),
                    'direction' => $is_incoming ? 'incoming' : 'outgoing',
                    'from_user' => $tx['from_username'],
                    'to_user' => $tx['to_username'],
                    'timestamp' => $tx['timestamp'],
                    'created_at' => $tx['created_at'],
                    'data' => json_decode($tx['data'], true)
                ];
            }, $transactions);
            
            echo json_encode([
                'success' => true,
                'user_id' => $user_id,
                'transactions' => $formatted_transactions,
                'count' => count($formatted_transactions)
            ]);
            break;
            
        case 'transfer':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $to_user_id = intval($input['to_user_id'] ?? 0);
            $amount = floatval($input['amount'] ?? 0);
            $message = trim($input['message'] ?? '');
            
            if (!$to_user_id || $amount <= 0) {
                throw new Exception('Invalid transfer parameters');
            }
            
            if ($user_id == $to_user_id) {
                throw new Exception('Cannot transfer to yourself');
            }
            
            $transaction_hash = $auditChain->transfer($user_id, $to_user_id, $amount, $message);
            $new_balance = $auditChain->getUserBalance($user_id);
            
            echo json_encode([
                'success' => true,
                'message' => 'Transfer completed',
                'transaction_hash' => $transaction_hash,
                'amount' => $amount,
                'new_balance' => $new_balance
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log('Audit chain wallet error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>