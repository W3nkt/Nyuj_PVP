-- Bull PVP Audit Chain Schema
-- Simplified hash-linked transaction logging for audit purposes

-- Audit transactions table (hash-linked chain)
CREATE TABLE audit_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_hash VARCHAR(64) UNIQUE NOT NULL,
    previous_hash VARCHAR(64),
    from_user_id INT,
    to_user_id INT,
    amount DECIMAL(12,2) NOT NULL,
    transaction_type ENUM(
        'deposit', 'withdrawal', 'transfer', 'bet_place', 'bet_match', 'bet_win', 'bet_loss', 'bet_refund', 'platform_fee',
        'user_register', 'user_login', 'user_logout', 'user_update', 'user_delete',
        'event_create', 'event_update', 'event_start', 'event_complete', 'event_cancel', 'event_status_change',
        'competitor_create', 'competitor_update', 'competitor_delete',
        'vote_submit', 'vote_change', 'voting_open', 'voting_close',
        'admin_action', 'system_config', 'file_upload', 'security_alert'
    ) NOT NULL,
    data JSON,
    timestamp BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id),
    FOREIGN KEY (to_user_id) REFERENCES users(id),
    INDEX idx_transaction_hash (transaction_hash),
    INDEX idx_from_user (from_user_id),
    INDEX idx_to_user (to_user_id),
    INDEX idx_type (transaction_type),
    INDEX idx_timestamp (timestamp),
    INDEX idx_created_at (created_at)
);

-- User balances table (replaces blockchain_balances)
CREATE TABLE user_balances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    last_transaction_hash VARCHAR(64),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_balance (balance)
);

-- Transaction audit log for security monitoring
CREATE TABLE transaction_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_hash VARCHAR(64) NOT NULL,
    user_id INT,
    action ENUM('create', 'verify', 'dispute') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_transaction_hash (transaction_hash),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- Initialize with genesis transaction
INSERT INTO audit_transactions (
    transaction_hash, 
    previous_hash, 
    from_user_id, 
    to_user_id, 
    amount, 
    transaction_type, 
    data, 
    timestamp
) VALUES (
    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
    NULL,
    NULL,
    NULL,
    0.00,
    'platform_fee',
    '{"message": "Bull PVP Audit Chain Genesis Transaction"}',
    UNIX_TIMESTAMP()
);