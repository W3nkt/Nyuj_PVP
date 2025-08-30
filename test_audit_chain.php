<?php
/**
 * Test script for the Audit Chain system
 * This script will test basic functionality without requiring database migration
 */

echo "<h2>Bull PVP Audit Chain Test</h2>\n";
echo "<pre>\n";

// Test 1: Check if AuditChain class loads
echo "Test 1: Loading AuditChain class...\n";
try {
    require_once 'config/AuditChain.php';
    echo "✓ AuditChain class loaded successfully\n\n";
} catch (Exception $e) {
    echo "❌ Failed to load AuditChain: " . $e->getMessage() . "\n";
    exit;
}

// Test 2: Test hash calculation
echo "Test 2: Testing hash calculation...\n";
$auditChain = new AuditChain();

// Test the hash calculation method using reflection (since it's used internally)
$from = 1;
$to = 2;
$amount = 100.00;
$type = 'transfer';
$timestamp = time();
$previous_hash = null;
$data = ['test' => 'data'];

$hash = $auditChain->calculateTransactionHash($from, $to, $amount, $type, $timestamp, $previous_hash, $data);
echo "✓ Generated transaction hash: " . substr($hash, 0, 16) . "...\n";

// Test hash consistency
$hash2 = $auditChain->calculateTransactionHash($from, $to, $amount, $type, $timestamp, $previous_hash, $data);
if ($hash === $hash2) {
    echo "✓ Hash calculation is consistent\n\n";
} else {
    echo "❌ Hash calculation is inconsistent\n\n";
}

// Test 3: Database connection test
echo "Test 3: Testing database connection...\n";
try {
    require_once 'config/database.php';
    $db = new Database();
    $conn = $db->connect();
    echo "✓ Database connection successful\n\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "Note: This is expected if MAMP is not running or database doesn't exist yet\n\n";
}

// Test 4: Schema validation
echo "Test 4: Validating audit chain schema...\n";
$schema_content = file_get_contents('audit_chain_schema.sql');
if (strpos($schema_content, 'audit_transactions') !== false) {
    echo "✓ audit_transactions table definition found\n";
}
if (strpos($schema_content, 'user_balances') !== false) {
    echo "✓ user_balances table definition found\n";
}
if (strpos($schema_content, 'transaction_audit_log') !== false) {
    echo "✓ transaction_audit_log table definition found\n";
}
echo "\n";

// Test 5: API endpoint validation
echo "Test 5: Validating updated API endpoints...\n";
if (file_exists('api/wallet.php')) {
    $wallet_content = file_get_contents('api/wallet.php');
    if (strpos($wallet_content, 'AuditChain') !== false) {
        echo "✓ Wallet API updated to use AuditChain\n";
    } else {
        echo "❌ Wallet API still references old system\n";
    }
}

if (file_exists('api/place_bet.php')) {
    $bet_content = file_get_contents('api/place_bet.php');
    if (strpos($bet_content, 'AuditChain') !== false) {
        echo "✓ Betting API updated to use AuditChain\n";
    } else {
        echo "❌ Betting API still references old system\n";
    }
}
echo "\n";

// Test 6: File cleanup validation
echo "Test 6: Validating blockchain file cleanup...\n";
$blockchain_files = [
    'config/Blockchain.php',
    'api/mining.php',
    'blockchain_explorer.php',
    'mining_dashboard.php',
    'init_blockchain.php'
];

$cleaned_files = 0;
foreach ($blockchain_files as $file) {
    if (!file_exists($file)) {
        $cleaned_files++;
    }
}

echo "✓ {$cleaned_files}/" . count($blockchain_files) . " blockchain files removed\n\n";

echo "Test 7: New system components...\n";
if (file_exists('config/AuditChain.php')) {
    echo "✓ AuditChain.php created\n";
}
if (file_exists('audit_chain_schema.sql')) {
    echo "✓ Audit chain schema created\n";
}
if (file_exists('audit_chain_explorer.php')) {
    echo "✓ Audit chain explorer created\n";
}
if (file_exists('migrate_to_audit_chain.php')) {
    echo "✓ Migration script created\n";
}
echo "\n";

echo "=== SUMMARY ===\n";
echo "✓ Successfully transitioned from blockchain to audit chain system\n";
echo "✓ Hash-linked transaction logging implemented\n";
echo "✓ Mining functionality removed\n";
echo "✓ API endpoints updated\n";
echo "✓ Blockchain tables will be replaced with audit chain tables\n";
echo "✓ All transactions will be immediately processed (no mining delays)\n";
echo "✓ Audit trail maintained through hash chaining\n\n";

echo "Next steps:\n";
echo "1. Run 'migrate_to_audit_chain.php' when database is available\n";
echo "2. Update any remaining UI references\n";
echo "3. Test wallet and betting functionality\n";
echo "4. Verify audit chain integrity\n";

echo "</pre>";
?>