<?php
// ============================================
// SECURITY HELPER FUNCTIONS
// ============================================

class Security {
    
    // Prevent XSS
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    // Generate CSRF Token
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    // Verify CSRF Token
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // Generate CSRF Field for Forms
    public static function csrfField() {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
    
    // Password Hashing
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    // Verify Password
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    // Generate Random Password
    public static function generatePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        return substr(str_shuffle($chars), 0, $length);
    }
    
    // Validate Password Strength
    public static function validatePassword($password) {
        $errors = [];
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        if (!preg_match('/[!@#$%^&*()]/', $password)) {
            $errors[] = 'Password must contain at least one special character (!@#$%^&*())';
        }
        return $errors;
    }
    
    // Rate Limiting
    public static function checkRateLimit($key, $maxAttempts = 5, $timeWindow = 300) {
        $rateLimits = $_SESSION['rate_limits'] ?? [];
        $currentTime = time();
        
        // Clean old attempts
        foreach ($rateLimits as $k => $attempt) {
            if ($currentTime - $attempt['time'] > $timeWindow) {
                unset($rateLimits[$k]);
            }
        }
        
        // Check attempts
        if (isset($rateLimits[$key]) && $rateLimits[$key]['count'] >= $maxAttempts) {
            return false;
        }
        
        // Add attempt
        if (!isset($rateLimits[$key])) {
            $rateLimits[$key] = ['count' => 0, 'time' => $currentTime];
        }
        $rateLimits[$key]['count']++;
        $rateLimits[$key]['time'] = $currentTime;
        
        $_SESSION['rate_limits'] = $rateLimits;
        return true;
    }
    
    // Encrypt Data
    public static function encrypt($data) {
        $key = hash('sha256', ENCRYPTION_KEY);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    // Decrypt Data
    public static function decrypt($data) {
        $key = hash('sha256', ENCRYPTION_KEY);
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    // Log Security Event
    public static function logEvent($user_id, $action, $description, $status = 'success') {
        global $pdo;
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action_type, action_description, ip_address, user_agent, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$user_id, $action, $description, $ip, $userAgent, $status]);
    }
}
?>