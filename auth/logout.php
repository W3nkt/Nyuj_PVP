<?php
require_once '../config/session.php';
require_once '../config/database.php';

if (isLoggedIn()) {
    $db = new Database();
    $conn = $db->connect();
    
    // Log the logout
    try {
        $stmt = $conn->prepare("INSERT INTO audit_logs (action, actor_id, target_type, target_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'user_logout',
            getUserId(),
            'user',
            getUserId(),
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch (PDOException $e) {
        error_log('Logout logging error: ' . $e->getMessage());
    }
}

logout();
?>