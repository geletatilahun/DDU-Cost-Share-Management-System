<?php
// ============================================
// LOGOUT - FIXED VERSION
// Web-Based Cost-Share Management System
// Bule Hora University
// ============================================

require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

// Initialize auth
$auth = new Auth($pdo);

// Get user info before logout (for logging)
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Unknown';

// Log the logout event if session exists
if ($user_id) {
    Security::logEvent($user_id, 'LOGOUT', "User {$username} logged out successfully");
}

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    
    // Clear token from database
    if ($user_id) {
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
}

// Clear all session variables
$_SESSION = array();

// If a session cookie exists, destroy it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Check if session is active before destroying
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Redirect to login with logout message
$redirectUrl = 'login.php?logout=1';

// Add timestamp to prevent caching
$redirectUrl .= '&t=' . time();

// Use JavaScript redirect for better UX with loading animation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .logout-container {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 90%;
            animation: fadeInScale 0.5s ease;
        }
        
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .logout-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 35px;
            color: white;
        }
        
        .logout-container h3 {
            color: #2C3E50;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .logout-container p {
            color: #7f8c8d;
            margin-bottom: 25px;
        }
        
        .spinner-container {
            margin: 20px 0;
        }
        
        .spinner-border {
            width: 50px;
            height: 50px;
            border-width: 4px;
            color: #667eea;
        }
        
        .btn-redirect {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px 35px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-redirect:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .progress-container {
            width: 100%;
            height: 4px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 20px;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 0%;
            animation: progress 3s ease forwards;
        }
        
        @keyframes progress {
            from { width: 0%; }
            to { width: 100%; }
        }
        
        .logout-details {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 14px;
            color: #6c757d;
            text-align: left;
        }
        
        .logout-details i {
            color: #667eea;
            width: 20px;
        }
        
        .logout-details .detail-item {
            padding: 5px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        
        <h3>Logging Out...</h3>
        <p>You are being securely logged out of the system.</p>
        
        <div class="logout-details">
            <div class="detail-item">
                <i class="fas fa-user"></i> 
                <strong>User:</strong> <?php echo htmlspecialchars($username); ?>
            </div>
            <div class="detail-item">
                <i class="fas fa-clock"></i> 
                <strong>Time:</strong> <?php echo date('F d, Y h:i A'); ?>
            </div>
            <div class="detail-item">
                <i class="fas fa-check-circle text-success"></i> 
                <span class="status-badge success">✓ Session Terminated</span>
            </div>
        </div>
        
        <div class="spinner-container">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Redirecting...</span>
            </div>
        </div>
        
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>
        
        <div style="margin-top: 25px;">
            <a href="login.php" class="btn-redirect">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
        
        <p class="text-muted mt-3" style="font-size: 12px;">
            <i class="fas fa-shield-alt"></i> You will be redirected automatically
        </p>
    </div>
    
    <script>
        // Auto redirect after animation
        let seconds = 3;
        
        function countdown() {
            seconds--;
            if (seconds <= 0) {
                window.location.href = 'login.php?logout=1';
            }
        }
        
        // Start countdown after 1 second
        setTimeout(() => {
            setInterval(countdown, 1000);
        }, 1000);
        
        // Also allow manual click on the back button
        document.querySelector('.btn-redirect').addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'login.php?logout=1';
        });
        
        // Prevent back button issues
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
        
        // Show notification when logout completes
        document.addEventListener('DOMContentLoaded', function() {
            console.log('User logged out successfully');
        });
    </script>
</body>
</html>