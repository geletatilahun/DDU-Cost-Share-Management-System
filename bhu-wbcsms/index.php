<?php
// ============================================
// WBCSMS - Entry Point (Front Controller)
// Bule Hora University Cost Share Management System
// ============================================

// 1. Turn off error reporting for production (enable if you are debugging)
error_reporting(0);
ini_set('display_errors', 0);

// 2. Start the Session (Crucial for user login)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Include Database Configuration
// Make sure you have a separate 'config/database.php' file with your DB credentials
require_once __DIR__ . '/config/database.php';

// 4. Include Core Functions
// This file should contain your routing logic and user authentication functions
require_once __DIR__ . '/includes/functions.php';

// ============================================
// ROUTING LOGIC
// ============================================

// Get the requested URL path (e.g., localhost/wbcsms/login or localhost/wbcsms/dashboard)
$request_uri = $_SERVER['REQUEST_URI'];
// Remove query strings (e.g., ?id=1) to get clean path
$path = parse_url($request_uri, PHP_URL_PATH);
// Get the base path (if your project is in a subfolder, e.g., /wbcsms/)
$base_path = '/wbcsms/'; // CHANGE THIS to '/' if it's in the root domain!

// Remove the base path from the request to get the specific page request
if (strpos($path, $base_path) === 0) {
    $path = substr($path, strlen($base_path));
}
$path = trim($path, '/');

// If the path is empty, default to 'login' or 'dashboard'
if (empty($path)) {
    $path = 'login';
}

// ============================================
// AUTHENTICATION CHECK
// ============================================

// Define which pages are public (don't require login)
$public_pages = ['login', 'register', 'forgot_password'];

// Check if user is logged in (customize this based on your $_SESSION keys)
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// 1. If user is NOT logged in and tries to access a restricted page -> Redirect to Login
if (!$is_logged_in && !in_array($path, $public_pages)) {
    header('Location: ' . $base_path . 'login');
    exit();
}

// 2. If user IS logged in and tries to access public pages (like login) -> Redirect to Dashboard
if ($is_logged_in && in_array($path, $public_pages)) {
    header('Location: ' . $base_path . 'dashboard');
    exit();
}

// ============================================
// PAGE LOADER
// ============================================

// Map the URL path to a physical .php file on your server
$file_path = __DIR__ . '/pages/' . $path . '.php';

// Check if the page file actually exists
if (file_exists($file_path)) {
    include $file_path;
} else {
    // If page doesn't exist, show a 404 error
    header("HTTP/1.0 404 Not Found");
    include __DIR__ . '/pages/404.php';
    exit();
}
?>