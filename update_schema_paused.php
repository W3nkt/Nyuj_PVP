<?php
/**
 * Fix the genesis transaction hash to match the audit chain calculation
 */

require_once 'config/database.php';
require_once 'config/AuditChain.php';

try {
    $db = new Database();
    $conn = $db->connect();
    $auditChain = new AuditChain();
    
    echo "Fixing genesis transaction hash...\n";
    
    // Calculate the correct genesis transaction hash
    $genesis_hash = $auditChain->calculateTransactionHash(
        null, null, 0.00, 'platform_fee', 
        time(), null, 
        ['message' => 'Bull PVP Audit Chain Genesis Transaction']
    );
    
    // Update the genesis transaction
    $stmt = $conn->prepare("
        UPDATE audit_transactions 
        SET transaction_hash = ?, timestamp = ?
        WHERE previous_hash IS NULL
    ");
    $stmt->execute([$genesis_hash, time()]);
    
    echo "✓ Genesis transaction hash updated: " . substr($genesis_hash, 0, 16) . "...\n";
    
    // Verify chain integrity now
    $integrity = $auditChain->verifyChainIntegrity();
    if ($integrity['valid']) {
        echo "✅ Audit chain integrity is now VALID\n";
    } else {
        echo "❌ Still invalid: " . $integrity['error'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>