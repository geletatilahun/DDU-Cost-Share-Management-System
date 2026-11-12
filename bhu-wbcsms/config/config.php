<?php
// ============================================
// CONFIGURATION FILE
// Web-Based Cost-Share Management System
// ============================================

// Error Reporting - Turn off in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// Timezone
date_default_timezone_set('Africa/Addis_Ababa');

// Site Configuration
define('SITE_NAME', 'WBCSMS - Bule Hora University');
define('SITE_URL', 'http://localhost/wbcsms/');
define('ADMIN_EMAIL', 'admin@bulehora.edu.et');

// File Upload Configuration
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('PASSWORD_MIN_LENGTH', 8);
define('ENCRYPTION_KEY', 'your-secret-key-here-change-this');

// Payment Configuration
define('DEFAULT_CURRENCY', 'ETB');
define('MIN_COST_SHARE', 1000);
define('MAX_COST_SHARE', 100000);

// SMTP Configuration (Email)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@bulehora.edu.et');
define('SMTP_FROM_NAME', 'WBCSMS System');

// Database Configuration - Load from database.php
require_once __DIR__ . '/database.php';

// Include Constants
require_once __DIR__ . '/constants.php';

// Start Session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>