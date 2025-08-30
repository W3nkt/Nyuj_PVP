-- Bull PVP Platform Database Schema (Fixed Version)
-- Removed DELIMITER commands and stored procedures for PDO compatibility

DROP DATABASE IF EXISTS battle_bidding;
CREATE DATABASE battle_bidding;
USE battle_bidding;

-- Users table with KYC tiers
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin', 'streamer') DEFAULT 'user',
    kyc_tier INT DEFAULT 0,
    phone VARCHAR(20),
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    date_of_birth DATE,
    status ENUM('active', 'suspended', 'banned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Wallets for users
CREATE TABLE wallets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    balance DECIMAL(12,2) DEFAULT 0.00,
    locked_balance DECIMAL(12,2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'USD',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_wallet (user_id)
);

-- Events/matches between two competitors
CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    game_type VARCHAR(100) NOT NULL,
    competitor_a VARCHAR(100) NOT NULL,
    competitor_b VARCHAR(100) NOT NULL,
    competitor_a_image VARCHAR(255),
    competitor_b_image VARCHAR(255),
    platform_fee_percent DECIMAL(5,2) DEFAULT 7.00,
    status ENUM('created', 'accepting_bets', 'live', 'completed', 'streamer_voting', 'admin_review', 'final_result', 'settlement', 'closed', 'cancelled') DEFAULT 'created',
    match_start_time DATETIME,
    match_end_time DATETIME,
    betting_closes_at DATETIME,
    winner ENUM('A', 'B', 'DRAW') NULL,
    total_bets_a INT DEFAULT 0,
    total_bets_b INT DEFAULT 0,
    total_amount_a DECIMAL(12,2) DEFAULT 0.00,
    total_amount_b DECIMAL(12,2) DEFAULT 0.00,
    matched_pairs INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Individual bets placed by users
CREATE TABLE bets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    bet_on ENUM('A', 'B') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'matched', 'won', 'lost', 'refunded', 'cancelled') DEFAULT 'pending',
    matched_bet_id INT NULL,
    potential_winnings DECIMAL(12,2) NULL,
    actual_payout DECIMAL(12,2) NULL,
    placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    matched_at TIMESTAMP NULL,
    settled_at TIMESTAMP NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (matched_bet_id) REFERENCES bets(id) ON DELETE SET NULL,
    INDEX idx_event_status (event_id, status),
    INDEX idx_matching (event_id, bet_on, amount, status),
    INDEX idx_user_bets (user_id, placed_at)
);

-- Streamer assignments to events
CREATE TABLE event_streamers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    streamer_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (streamer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_streamer (event_id, streamer_id)
);

-- Streamer votes on event results
CREATE TABLE votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    streamer_id INT NOT NULL,
    voted_winner ENUM('A', 'B', 'DRAW') NOT NULL,
    vote_notes TEXT,
    vote_hash VARCHAR(64),
    is_paid BOOLEAN DEFAULT FALSE,
    payment_amount DECIMAL(8,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (streamer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_streamer_vote (event_id, streamer_id)
);

-- Transaction ledger for accounting system
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    wallet_id INT NOT NULL,
    event_id INT NULL,
    bet_id INT NULL,
    type ENUM('deposit', 'withdrawal', 'bet_hold', 'bet_payout', 'bet_refund', 'platform_fee', 'streamer_payment') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_before DECIMAL(12,2),
    balance_after DECIMAL(12,2),
    description TEXT,
    reference_id VARCHAR(100),
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    FOREIGN KEY (bet_id) REFERENCES bets(id) ON DELETE SET NULL,
    INDEX idx_wallet_created (wallet_id, created_at),
    INDEX idx_type_status (type, status)
);

-- Audit logs for transparency and compliance
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action VARCHAR(100) NOT NULL,
    actor_id INT,
    target_type VARCHAR(50),
    target_id INT,
    old_value JSON,
    new_value JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created_at (created_at),
    INDEX idx_actor_action (actor_id, action)
);

-- Streamer reputation tracking
CREATE TABLE streamer_reputation (
    id INT PRIMARY KEY AUTO_INCREMENT,
    streamer_id INT NOT NULL,
    total_votes INT DEFAULT 0,
    correct_votes INT DEFAULT 0,
    disputed_votes INT DEFAULT 0,
    reputation_score DECIMAL(5,2) DEFAULT 100.00,
    total_earnings DECIMAL(10,2) DEFAULT 0.00,
    last_calculated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (streamer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_streamer (streamer_id)
);

-- System settings
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    data_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Bet matching queue (for optimization of matching process)
CREATE TABLE bet_matching_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    bet_on ENUM('A', 'B') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    user_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_bet_amount (event_id, bet_on, amount),
    INDEX idx_matching_lookup (event_id, amount)
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description, data_type) VALUES
('platform_fee_percent', '7.00', 'Default platform fee percentage', 'number'),
('streamer_payment_per_vote', '0.20', 'Payment amount per valid vote for streamers', 'number'),
('min_streamers_per_event', '3', 'Minimum number of streamers required per event', 'number'),
('kyc_tier_0_max_bet', '10.00', 'Maximum bet for KYC tier 0 users', 'number'),
('kyc_tier_1_max_bet', '100.00', 'Maximum bet for KYC tier 1 users', 'number'),
('kyc_tier_2_max_bet', '1000.00', 'Maximum bet for KYC tier 2 users', 'number'),
('min_bet_amount', '1.00', 'Minimum bet amount', 'number'),
('max_bet_amount', '1000.00', 'Maximum bet amount per bet', 'number'),
('betting_close_minutes', '30', 'Minutes before match start when betting closes', 'number'),
('auto_refund_unmatched', 'true', 'Automatically refund unmatched bets when match starts', 'boolean'),
('max_pending_bets_per_user', '5', 'Maximum pending bets a user can have', 'number'),
('platform_name', 'Bull PVP Platform', 'Platform display name', 'string'),
('support_email', 'support@battlebidding.com', 'Support contact email', 'string'),
('maintenance_mode', 'false', 'Enable maintenance mode', 'boolean');

-- Create performance indexes
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_events_status ON events(status);
CREATE INDEX idx_events_match_time ON events(match_start_time);
CREATE INDEX idx_events_game_type ON events(game_type);
CREATE INDEX idx_bets_matching ON bets(event_id, bet_on, amount, status);
CREATE INDEX idx_bets_user ON bets(user_id, status);
CREATE INDEX idx_bets_event_placed ON bets(event_id, placed_at);
CREATE INDEX idx_transactions_wallet_id ON transactions(wallet_id);
CREATE INDEX idx_transactions_type ON transactions(type);
CREATE INDEX idx_transactions_created_at ON transactions(created_at);
CREATE INDEX idx_votes_event_id ON votes(event_id);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);

-- Create views for common queries
CREATE VIEW active_matches AS
SELECT 
    e.*,
    COUNT(DISTINCT ba.id) as bets_on_a,
    COUNT(DISTINCT bb.id) as bets_on_b,
    COALESCE(SUM(CASE WHEN ba.status = 'matched' THEN ba.amount END), 0) as matched_amount_a,
    COALESCE(SUM(CASE WHEN bb.status = 'matched' THEN bb.amount END), 0) as matched_amount_b,
    COUNT(DISTINCT es.streamer_id) as assigned_streamers
FROM events e 
LEFT JOIN bets ba ON e.id = ba.event_id AND ba.bet_on = 'A' AND ba.status IN ('pending', 'matched')
LEFT JOIN bets bb ON e.id = bb.event_id AND bb.bet_on = 'B' AND bb.status IN ('pending', 'matched')
LEFT JOIN event_streamers es ON e.id = es.event_id
WHERE e.status IN ('accepting_bets', 'live', 'streamer_voting')
GROUP BY e.id;

-- Create view for user betting statistics  
CREATE VIEW user_betting_stats AS
SELECT 
    u.id as user_id,
    u.username,
    COUNT(b.id) as total_bets,
    COUNT(CASE WHEN b.status = 'won' THEN 1 END) as wins,
    COUNT(CASE WHEN b.status = 'lost' THEN 1 END) as losses,
    COUNT(CASE WHEN b.status = 'matched' THEN 1 END) as active_bets,
    COUNT(CASE WHEN b.status = 'pending' THEN 1 END) as pending_bets,
    COALESCE(SUM(b.amount), 0) as total_wagered,
    COALESCE(SUM(CASE WHEN b.status = 'won' THEN b.actual_payout END), 0) as total_winnings,
    CASE 
        WHEN COUNT(CASE WHEN b.status IN ('won', 'lost') THEN 1 END) > 0
        THEN (COUNT(CASE WHEN b.status = 'won' THEN 1 END) * 100.0 / COUNT(CASE WHEN b.status IN ('won', 'lost') THEN 1 END))
        ELSE 0
    END as win_percentage
FROM users u
LEFT JOIN bets b ON u.id = b.user_id
WHERE u.role = 'user'
GROUP BY u.id, u.username;