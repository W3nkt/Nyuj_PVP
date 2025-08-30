<?php
// Base path configuration that works on both Windows and Linux

// Auto-detect base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = dirname($_SERVER['SCRIPT_NAME']);

// Handle different directory structures
if (strpos($script_dir, 'Bull_PVP') !== false) {
    // We're inside the Bull_PVP directory or subdirectory
    $base_path_parts = explode('/', $script_dir);
    $bull_pvp_index = array_search('Bull_PVP', $base_path_parts);
    if ($bull_pvp_index !== false) {
        $base_path_parts = array_slice($base_path_parts, 0, $bull_pvp_index + 1);
        $base_path = implode('/', $base_path_parts);
    } else {
        $base_path = '/Bull_PVP';
    }
} else {
    $base_path = '/Bull_PVP';
}

// Define constants for use throughout the application
define('BASE_URL', $protocol . '://' . $host . $base_path);
define('BASE_PATH', $base_path);

// Helper function to create proper URLs
function url($path = '') {
    return BASE_PATH . '/' . ltrim($path, '/');
}

// Helper function to create asset URLs
function asset($path) {
    return BASE_PATH . '/assets/' . ltrim($path, '/');
}
?>