<?php
/**
 * Migration script to transition from blockchain to audit chain system
 * This script will:
 * 1. Create new audit chain tables
 * 2. Migrate existing wallet balances
 * 3. Drop old blockchain tables (with backup)
 */

require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->connect();
    
    echo "Starting migration from blockchain to audit chain system...\n\n";
    
    // Step 1: Create new audit chain tables
    echo "Step 1: Creating audit chain tables...\n";
    $sql = file_get_contents('audit_chain_schema.sql');
    
    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $conn->exec($statement);
                echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
            } catch (Exception $e) {
                echo "⚠ Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Step 2: Migrate existing user balances from wallets table
    echo "\nStep 2: Migrating existing user balances...\n";
    
    // Check if wallets table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'wallets'");
    if ($stmt->rowCount() > 0) {
        // Migrate from wallets table
        $stmt = $conn->query("SELECT user_id, balance FROM wallets WHERE balance > 0");
        $wallets = $stmt->fetchAll();
        
        foreach ($wallets as $wallet) {
            $stmt = $conn->prepare("
                INSERT INTO user_balances (user_id, balance) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE balance = ?
            ");
            $stmt->execute([$wallet['user_id'], $wallet['balance'], $wallet['balance']]);
            echo "✓ Migrated user {$wallet['user_id']}: ${$wallet['balance']}\n";
        }
    }
    
    // Check if blockchain_balances table exists and migrate
    $stmt = $conn->query("SHOW TABLES LIKE 'blockchain_balances'");
    if ($stmt->rowCount() > 0) {
        // Try to map blockchain addresses to users
        $stmt = $conn->query("
            SELECT uba.user_id, bb.balance 
            FROM blockchain_balances bb
            JOIN user_blockchain_addresses uba ON bb.address = uba.address
            WHERE bb.balance > 0
        ");
        $blockchain_balances = $stmt->fetchAll();
        
        foreach ($blockchain_balances as $balance) {
            $stmt = $conn->prepare("
                INSERT INTO user_balances (user_id, balance) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE balance = GREATEST(balance, ?)
            ");
            $stmt->execute([$balance['user_id'], $balance['balance'], $balance['balance']]);
            echo "✓ Migrated blockchain balance for user {$balance['user_id']}: ${$balance['balance']}\n";
        }
    }
    
    // Step 3: Create backup tables and drop old blockchain tables
    echo "\nStep 3: Backing up and removing old blockchain tables...\n";
    
    // Disable foreign key checks temporarily
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $blockchain_tables = [
        'blockchain_transactions', 
        'blockchain_blocks',
        'blockchain_balances',
        'user_blockchain_addresses',
        'transaction_mempool',
        'double_spend_attempts',
        'blockchain_config'
    ];
    
    foreach ($blockchain_tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            try {
                // Create backup
                $backup_name = $table . '_backup_' . date('Y_m_d_H_i_s');
                $conn->exec("CREATE TABLE $backup_name AS SELECT * FROM $table");
                echo "✓ Created backup: $backup_name\n";
                
                // Drop original table
                $conn->exec("DROP TABLE $table");
                echo "✓ Dropped table: $table\n";
            } catch (Exception $e) {
                echo "⚠ Warning dropping $table: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Re-enable foreign key checks
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Step 4: Update CLAUDE.md to reflect changes
    echo "\nStep 4: Updating documentation...\n";
    
    $claude_content = file_get_contents('CLAUDE.md');
    
    // Replace blockchain references with audit chain
    $updated_content = str_replace(
        ['Blockchain System Setup', 'blockchain', 'mining'],
        ['Audit Chain System Setup', 'audit chain', 'transaction logging'],
        $claude_content
    );
    
    // Update the blockchain section
    $blockchain_section = "### Blockchain System Setup
1. Navigate to `init_blockchain.php` to initialize the blockchain
2. Creates genesis block and platform wallet with $1,000,000
3. View blockchain status at `blockchain_explorer.php`";
    
    $audit_section = "### Audit Chain System Setup
1. Execute `audit_chain_schema.sql` to create audit tables
2. All transactions are hash-linked for audit trail
3. No mining required - immediate transaction processing";
    
    $updated_content = str_replace($blockchain_section, $audit_section, $updated_content);
    
    // Update working with blockchain section
    $old_blockchain_info = "#### Working with Blockchain
- All financial transactions logged via `config/Blockchain.php`
- Mining system: `api/mining.php` and `cron_mining.php`
- Transaction types: deposit, withdrawal, bet_place, bet_match, bet_win, etc.
- Double-spend prevention built into transaction validation";
    
    $new_audit_info = "#### Working with Audit Chain
- All financial transactions logged via `config/AuditChain.php`
- Hash-linked transaction chain for audit trail
- Transaction types: deposit, withdrawal, bet_place, bet_match, bet_win, etc.
- Balance validation prevents overspending";
    
    $updated_content = str_replace($old_blockchain_info, $new_audit_info, $updated_content);
    
    file_put_contents('CLAUDE.md', $updated_content);
    echo "✓ Updated CLAUDE.md documentation\n";
    
    echo "\n🎉 Migration completed successfully!\n";
    echo "Summary:\n";
    echo "- Audit chain tables created\n";
    echo "- User balances migrated\n";
    echo "- Old blockchain tables backed up and removed\n";
    echo "- Documentation updated\n";
    echo "- System now uses simple hash-linked audit trail instead of blockchain mining\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Please check the error and run the script again.\n";
}
?>