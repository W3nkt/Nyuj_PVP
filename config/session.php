<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Use the BASE_PATH from paths.php for consistent routing
        require_once __DIR__ . '/paths.php';
        header('Location: ' . url('auth/login.php'));
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        // Use the BASE_PATH from paths.php for consistent routing
        require_once __DIR__ . '/paths.php';
        header('Location: ' . url('index.php'));
        exit();
    }
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function logout() {
    session_destroy();
    // Use the BASE_PATH from paths.php for consistent routing
    require_once __DIR__ . '/paths.php';
    header('Location: ' . url('index.php'));
    exit();
}
?>