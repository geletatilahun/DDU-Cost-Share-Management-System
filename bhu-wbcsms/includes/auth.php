<?php
// ============================================
// AUTHENTICATION CLASS
// ============================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/security.php';

class Auth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Login User
    public function login($username, $password) {
        // Rate limiting check
        if (!Security::checkRateLimit('login_' . $username)) {
            return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
        }
        
        // Get user
        $stmt = $this->pdo->prepare("
            SELECT u.*, r.role_name 
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.username = ? OR u.email = ?
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        // Check if user is active
        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Your account has been deactivated. Contact administrator.'];
        }
        
        // Verify password
        if (!Security::verifyPassword($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        // Check if user is verified
        if (!$user['is_verified']) {
            return ['success' => false, 'message' => 'Your account is not verified. Please verify your email.'];
        }
        
        // Update last login
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        
        // Set session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['dept_id'] = $user['dept_id'];
        $_SESSION['student_id'] = $user['student_id'];
        $_SESSION['login_time'] = time();
        
        // Log success
        Security::logEvent($user['user_id'], 'LOGIN', 'User logged in successfully');
        
        return ['success' => true, 'user' => $user];
    }
    
    // Logout
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            Security::logEvent($_SESSION['user_id'], 'LOGOUT', 'User logged out');
        }
        
        $_SESSION = [];
        session_destroy();
        return true;
    }
    
    // Check if logged in
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    // Check if session is expired
    public static function isSessionExpired() {
        if (!isset($_SESSION['login_time'])) {
            return true;
        }
        return (time() - $_SESSION['login_time']) > SESSION_TIMEOUT;
    }
    
    // Check user role
    public static function hasRole($roleId) {
        return isset($_SESSION['role_id']) && $_SESSION['role_id'] == $roleId;
    }
    
    // Check if user has permission
    public static function hasPermission($permissionName) {
        global $pdo;
        
        if (!self::isLoggedIn()) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.permission_id
            WHERE rp.role_id = ? AND p.permission_name = ?
        ");
        $stmt->execute([$_SESSION['role_id'], $permissionName]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    // Require login
    public static function requireLogin($redirect = 'login.php') {
        if (!self::isLoggedIn()) {
            header('Location: ' . $redirect);
            exit;
        }
        
        if (self::isSessionExpired()) {
            self::logout();
            header('Location: ' . $redirect . '?expired=1');
            exit;
        }
    }
    
    // Require specific role
    public static function requireRole($roleId, $redirect = 'dashboard.php') {
        self::requireLogin($redirect);
        
        if (!self::hasRole($roleId)) {
            header('Location: ' . $redirect);
            exit;
        }
    }
    
    // Require permission
    public static function requirePermission($permissionName, $redirect = 'dashboard.php') {
        self::requireLogin($redirect);
        
        if (!self::hasPermission($permissionName)) {
            header('Location: ' . $redirect . '?error=permission_denied');
            exit;
        }
    }
    
    // Get current user
    public static function getCurrentUser() {
        global $pdo;
        
        if (!self::isLoggedIn()) {
            return null;
        }
        
        $stmt = $pdo->prepare("
            SELECT u.*, r.role_name, d.dept_name 
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            LEFT JOIN departments d ON u.dept_id = d.dept_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
}
?>