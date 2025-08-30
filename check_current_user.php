<?php
/**
 * Check current logged in user and their balance
 */

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'config/AuditChain.php';

echo "<h2>Current User Check</h2>\n";
echo "<pre>\n";

if (isLoggedIn()) {
    $user_id = getUserId();
    $username = $_SESSION['username'] ?? 'Unknown';
    $role = $_SESSION['role'] ?? 'Unknown';
    
    echo "✅ Logged in as:\n";
    echo "  User ID: $user_id\n";
    echo "  Username: $username\n";
    echo "  Role: $role\n\n";
    
    // Get balances
    $db = new Database();
    $conn = $db->connect();
    
    // Wallet balance
    $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wallet_result = $stmt->fetch();
    $wallet_balance = $wallet_result ? $wallet_result['balance'] : 0;
    
    // Audit chain balance
    $auditChain = new AuditChain();
    $audit_balance = $auditChain->getUserBalance($user_id);
    
    echo "💰 Balances:\n";
    echo "  Wallet table: $" . number_format($wallet_balance, 2) . "\n";
    echo "  Audit chain: $" . number_format($audit_balance, 2) . "\n";
    
    if ($wallet_balance != $audit_balance) {
        echo "  ⚠️  Balances don't match!\n";
    } else {
        echo "  ✅ Balances match\n";
    }
    
    echo "\n";
    echo "This is the balance that should show on events.php: $" . number_format($audit_balance, 2) . "\n";
    
} else {
    echo "❌ Not logged in\n";
    echo "Please login at: http://localhost/Bull_PVP/auth/login.php\n";
}

echo "</pre>";
?>