<?php
/**
 * Bull PVP Audit Chain Implementation
 * Provides hash-linked transaction logging for audit purposes without blockchain mining
 */

class AuditChain {
    private $db;
    private $conn;
    
    // Platform addresses
    const PLATFORM_ADDRESS = 'BullPVP_Platform_000000000000000000000000';
    
    public function __construct() {
        require_once 'database.php';
        $this->db = new Database();
        $this->conn = $this->db->connect();
    }
    
    /**
     * Calculate transaction hash with link to previous transaction
     */
    public function calculateTransactionHash($from, $to, $amount, $type, $timestamp, $previous_hash, $data = null) {
        $input = $from . $to . $amount . $type . $timestamp . $previous_hash . json_encode($data);
        return hash('sha256', $input);
    }
    
    /**
     * Get the last transaction hash for chain linking
     */
    private function getLastTransactionHash() {
        $stmt = $this->conn->prepare("
            SELECT transaction_hash FROM audit_transactions 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['transaction_hash'] : null;
    }
    
    /**
     * Create a new transaction in the audit chain
     * For non-financial transactions, amount should be 0
     */
    public function createTransaction($from_user_id, $to_user_id, $amount, $type, $data = null) {
        try {
            $this->conn->beginTransaction();
            
            // Validate balance if not a deposit
            if ($type !== 'deposit' && $from_user_id) {
                $balance = $this->getUserBalance($from_user_id);
                if ($balance < $amount) {
                    throw new Exception('Insufficient balance. Available: ' . number_format($balance, 2));
                }
            }
            
            $timestamp = time();
            $previous_hash = $this->getLastTransactionHash();
            $transaction_hash = $this->calculateTransactionHash(
                $from_user_id, $to_user_id, $amount, $type, $timestamp, $previous_hash, $data
            );
            
            // Insert transaction
            $stmt = $this->conn->prepare("
                INSERT INTO audit_transactions (
                    transaction_hash, previous_hash, from_user_id, to_user_id, 
                    amount, transaction_type, data, timestamp
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $transaction_hash, $previous_hash, $from_user_id, $to_user_id,
                $amount, $type, json_encode($data), $timestamp
            ]);
            
            // Update balances
            $this->updateBalances($from_user_id, $to_user_id, $amount, $type);
            
            $this->conn->commit();
            return $transaction_hash;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    /**
     * Update user balances after transaction
     */
    private function updateBalances($from_user_id, $to_user_id, $amount, $type) {
        // Handle different transaction types
        switch ($type) {
            case 'deposit':
                if ($to_user_id) {
                    $this->updateUserBalance($to_user_id, $amount);
                }
                break;
                
            case 'withdrawal':
                if ($from_user_id) {
                    $this->updateUserBalance($from_user_id, -$amount);
                }
                break;
                
            case 'transfer':
            case 'bet_place':
            case 'bet_win':
            case 'bet_refund':
                if ($from_user_id) {
                    $this->updateUserBalance($from_user_id, -$amount);
                }
                if ($to_user_id) {
                    $this->updateUserBalance($to_user_id, $amount);
                }
                break;
        }
    }
    
    /**
     * Update individual user balance
     */
    private function updateUserBalance($user_id, $amount_change) {
        $stmt = $this->conn->prepare("
            INSERT INTO user_balances (user_id, balance) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE balance = balance + ?
        ");
        $stmt->execute([$user_id, $amount_change, $amount_change]);
    }
    
    /**
     * Get user balance
     */
    public function getUserBalance($user_id) {
        $stmt = $this->conn->prepare("
            SELECT balance FROM user_balances WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ? floatval($result['balance']) : 0.00;
    }
    
    /**
     * Transfer funds between users
     */
    public function transfer($from_user_id, $to_user_id, $amount, $description = '') {
        $data = ['description' => $description];
        return $this->createTransaction($from_user_id, $to_user_id, $amount, 'transfer', $data);
    }
    
    /**
     * Deposit funds to user
     */
    public function deposit($user_id, $amount, $payment_method = '') {
        $data = ['payment_method' => $payment_method];
        return $this->createTransaction(null, $user_id, $amount, 'deposit', $data);
    }
    
    /**
     * Withdraw funds from user
     */
    public function withdraw($user_id, $amount, $payment_method = '') {
        $data = ['payment_method' => $payment_method];
        return $this->createTransaction($user_id, null, $amount, 'withdrawal', $data);
    }
    
    /**
     * Get transaction history for user
     */
    public function getTransactionHistory($user_id, $limit = 50) {
        $stmt = $this->conn->prepare("
            SELECT t.*, 
                   fu.username as from_username, 
                   tu.username as to_username
            FROM audit_transactions t
            LEFT JOIN users fu ON t.from_user_id = fu.id
            LEFT JOIN users tu ON t.to_user_id = tu.id
            WHERE t.from_user_id = ? OR t.to_user_id = ?
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $user_id, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Verify audit chain integrity
     */
    public function verifyChainIntegrity() {
        $stmt = $this->conn->prepare("
            SELECT * FROM audit_transactions ORDER BY id ASC
        ");
        $stmt->execute();
        $transactions = $stmt->fetchAll();
        
        $previous_hash = null;
        foreach ($transactions as $tx) {
            // Verify hash chain
            if ($tx['previous_hash'] !== $previous_hash) {
                return [
                    'valid' => false,
                    'error' => 'Hash chain broken at transaction ' . $tx['transaction_hash']
                ];
            }
            
            // Verify transaction hash
            $calculated_hash = $this->calculateTransactionHash(
                $tx['from_user_id'], $tx['to_user_id'], $tx['amount'], 
                $tx['transaction_type'], $tx['timestamp'], $tx['previous_hash'], 
                json_decode($tx['data'], true)
            );
            
            if ($calculated_hash !== $tx['transaction_hash']) {
                return [
                    'valid' => false,
                    'error' => 'Transaction hash mismatch at ' . $tx['transaction_hash']
                ];
            }
            
            $previous_hash = $tx['transaction_hash'];
        }
        
        return ['valid' => true, 'total_transactions' => count($transactions)];
    }
    
    /**
     * Log user activity (registration, login, profile updates, etc.)
     */
    public function logUserActivity($user_id, $activity_type, $data = null) {
        return $this->createTransaction($user_id, null, 0, $activity_type, $data);
    }
    
    /**
     * Log event activity (creation, updates, status changes)
     */
    public function logEventActivity($user_id, $event_id, $activity_type, $data = null) {
        $event_data = array_merge(['event_id' => $event_id], $data ?: []);
        return $this->createTransaction($user_id, null, 0, $activity_type, $event_data);
    }
    
    /**
     * Log competitor activity
     */
    public function logCompetitorActivity($user_id, $competitor_id, $activity_type, $data = null) {
        $competitor_data = array_merge(['competitor_id' => $competitor_id], $data ?: []);
        return $this->createTransaction($user_id, null, 0, $activity_type, $competitor_data);
    }
    
    /**
     * Log voting activity
     */
    public function logVoteActivity($user_id, $event_id, $vote_data) {
        $vote_info = array_merge(['event_id' => $event_id], $vote_data);
        return $this->createTransaction($user_id, null, 0, 'vote_submit', $vote_info);
    }
    
    /**
     * Log admin actions
     */
    public function logAdminAction($admin_user_id, $action, $target_data = null) {
        return $this->createTransaction($admin_user_id, null, 0, 'admin_action', 
                                       array_merge(['action' => $action], $target_data ?: []));
    }
    
    /**
     * Log security alerts
     */
    public function logSecurityAlert($user_id, $alert_type, $details = null) {
        return $this->createTransaction($user_id, null, 0, 'security_alert', 
                                       array_merge(['alert_type' => $alert_type], $details ?: []));
    }
    
    /**
     * Get all transactions for admin audit
     */
    public function getAllTransactions($limit = 100, $offset = 0) {
        $stmt = $this->conn->prepare("
            SELECT t.*, 
                   fu.username as from_username, 
                   tu.username as to_username
            FROM audit_transactions t
            LEFT JOIN users fu ON t.from_user_id = fu.id
            LEFT JOIN users tu ON t.to_user_id = tu.id
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get activity statistics by type
     */
    public function getActivityStats($days = 30) {
        $stmt = $this->conn->prepare("
            SELECT 
                transaction_type,
                COUNT(*) as count,
                DATE(FROM_UNIXTIME(timestamp)) as date
            FROM audit_transactions 
            WHERE FROM_UNIXTIME(timestamp) >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY transaction_type, DATE(FROM_UNIXTIME(timestamp))
            ORDER BY date DESC, count DESC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }
}
?>