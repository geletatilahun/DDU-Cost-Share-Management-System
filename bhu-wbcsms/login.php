<?php
// ============================================
// LOGIN PAGE - ENHANCED VERSION
// Web-Based Cost-Share Management System
// Bule Hora University
// ============================================

require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    // Redirect based on role
    $role_id = $_SESSION['role_id'] ?? 0;
    switch($role_id) {
        case ROLE_ADMIN:
            redirect('modules/admin/dashboard.php');
            break;
        case ROLE_OFFICER:
            redirect('modules/officer/dashboard.php');
            break;
        case ROLE_HOD:
            redirect('modules/hod/dashboard.php');
            break;
        case ROLE_FINANCE:
            redirect('modules/finance/dashboard.php');
            break;
        case ROLE_STUDENT:
            redirect('modules/student/dashboard.php');
            break;
        default:
            redirect('dashboard.php');
    }
}

$error = '';
$success = '';
$username = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed. Please try again.';
    } else {
        // Rate limiting check
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!Security::checkRateLimit('login_' . $ip, 5, 300)) {
            $error = 'Too many login attempts. Please try again after 5 minutes.';
        } else {
            $username = Security::sanitizeInput($_POST['username']);
            $password = $_POST['password'];
            $remember = isset($_POST['remember']) ? true : false;
            
            // Validate inputs
            if (empty($username) || empty($password)) {
                $error = 'Please enter both username and password.';
            } else {
                $auth = new Auth($pdo);
                $result = $auth->login($username, $password);
                
                if ($result['success']) {
                    // Handle remember me
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        setcookie('remember_token', $token, time() + 86400 * 30, '/', '', false, true);
                        
                        // Store token in database
                        $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE user_id = ?");
                        $stmt->execute([password_hash($token, PASSWORD_DEFAULT), $_SESSION['user_id']]);
                    }
                    
                    // Redirect based on role
                    $role_id = $_SESSION['role_id'];
                    switch($role_id) {
                        case ROLE_ADMIN:
                            redirect('modules/admin/dashboard.php');
                            break;
                        case ROLE_OFFICER:
                            redirect('modules/officer/dashboard.php');
                            break;
                        case ROLE_HOD:
                            redirect('modules/hod/dashboard.php');
                            break;
                        case ROLE_FINANCE:
                            redirect('modules/finance/dashboard.php');
                            break;
                        case ROLE_STUDENT:
                            redirect('modules/student/dashboard.php');
                            break;
                        default:
                            redirect('dashboard.php');
                    }
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

// Check for logout message
if (isset($_GET['logout'])) {
    $success = 'You have been logged out successfully.';
}

// Check for session expired
if (isset($_GET['expired'])) {
    $error = 'Your session has expired. Please login again.';
}

// Check for registration success
if (isset($_GET['registered'])) {
    $success = 'Registration successful! Please login with your credentials.';
}

// Get system settings for login page
$siteName = SITE_NAME;
$loginLogo = 'assets/images/logo.png';
$loginBgImage = 'assets/images/login-bg.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            position: relative;
        }
        
        /* Background pattern overlay */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(102, 126, 234, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 50%, rgba(118, 75, 162, 0.3) 0%, transparent 50%);
            z-index: 0;
        }
        
        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px 35px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            animation: slideUp 0.6s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header .logo-wrapper {
            width: 90px;
            height: 90px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .login-header .logo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }
        
        .login-header .logo-wrapper i {
            font-size: 40px;
            color: white;
        }
        
        .login-header h3 {
            font-weight: 800;
            color: #1a1a2e;
            font-size: 22px;
            margin-bottom: 5px;
        }
        
        .login-header p {
            color: #7f8c8d;
            font-size: 14px;
            font-weight: 400;
            margin: 0;
        }
        
        .login-header .subtitle {
            color: #667eea;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #2C3E50;
            font-size: 13px;
            margin-bottom: 6px;
            display: block;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #667eea;
        }
        
        .input-group-custom {
            position: relative;
        }
        
        .input-group-custom .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #bdc3c7;
            z-index: 10;
            transition: all 0.3s;
        }
        
        .input-group-custom .form-control {
            padding: 12px 15px 12px 45px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8f9fa;
            height: 50px;
        }
        
        .input-group-custom .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
            background: white;
        }
        
        .input-group-custom .form-control:focus + .input-icon {
            color: #667eea;
        }
        
        .input-group-custom .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #bdc3c7;
            cursor: pointer;
            z-index: 10;
            padding: 5px;
            transition: all 0.3s;
        }
        
        .input-group-custom .toggle-password:hover {
            color: #667eea;
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .form-options .form-check {
            margin: 0;
        }
        
        .form-options .form-check-input {
            border-radius: 4px;
            border: 2px solid #dce1e8;
            width: 18px;
            height: 18px;
            margin-top: 1px;
            cursor: pointer;
        }
        
        .form-options .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .form-options .form-check-label {
            font-size: 14px;
            color: #5a6c7d;
            cursor: pointer;
            padding-left: 5px;
        }
        
        .form-options .forgot-link {
            font-size: 14px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .form-options .forgot-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            width: 100%;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            height: 52px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login i {
            margin-right: 8px;
        }
        
        .btn-login .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .btn-login.loading .btn-text {
            display: none;
        }
        
        .btn-login.loading .spinner {
            display: block;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .login-footer p {
            color: #7f8c8d;
            font-size: 14px;
            margin: 0;
        }
        
        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .login-footer a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .login-footer .copyright {
            font-size: 12px;
            color: #bdc3c7;
            margin-top: 10px;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 12px 18px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .alert i {
            margin-right: 10px;
        }
        
        /* Social login divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: #bdc3c7;
            font-size: 13px;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 2px dashed #e9ecef;
        }
        
        .divider::before {
            margin-right: 15px;
        }
        
        .divider::after {
            margin-left: 15px;
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .login-card {
                padding: 30px 20px;
            }
            
            .login-header h3 {
                font-size: 20px;
            }
            
            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .form-options .forgot-link {
                align-self: flex-end;
            }
        }
        
        /* Toast notification */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="logo-wrapper">
                    <img src="<?php echo $loginLogo; ?>" alt="University Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <i class="fas fa-university" style="display: none;"></i>
                </div>
                <h3>Welcome Back!</h3>
                <p>Sign in to access your account</p>
                <div class="subtitle"><?php echo SITE_NAME; ?></div>
            </div>
            
            <!-- Messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" action="" id="loginForm" novalidate>
                <?php echo Security::csrfField(); ?>
                
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username or Email</label>
                    <div class="input-group-custom">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               placeholder="Enter your username or email" 
                               value="<?php echo htmlspecialchars($username); ?>"
                               required 
                               autofocus
                               autocomplete="username">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="input-group-custom">
                        <i class="fas fa-key input-icon"></i>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password" 
                               required
                               autocomplete="current-password">
                        <button type="button" class="toggle-password" onclick="togglePassword()" tabindex="-1">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="forgot-link">
                        <i class="fas fa-question-circle"></i> Forgot Password?
                    </a>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Sign In</span>
                    <span class="spinner"></span>
                </button>
            </form>
            
            <!-- Divider -->
            <div class="divider">or continue with</div>
            
            <!-- Demo Credentials (for testing) -->
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <button type="button" class="btn btn-outline-primary w-100" onclick="fillDemo('admin', 'Admin@123')">
                        <i class="fas fa-user-shield"></i> Admin
                    </button>
                </div>
                <div class="col-6">
                    <button type="button" class="btn btn-outline-success w-100" onclick="fillDemo('student', 'Student@123')">
                        <i class="fas fa-user-graduate"></i> Student
                    </button>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="login-footer">
                <p>Don't have an account? <a href="register.php">Register Now</a></p>
                <div class="copyright">
                    &copy; <?php echo date('Y'); ?> Bule Hora University. All rights reserved.
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container">
        <div id="toast" class="toast" role="alert" aria-live="polite" aria-atomic="true">
            <div class="toast-header">
                <i class="fas fa-info-circle me-2"></i>
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body" id="toastMessage"></div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }
        
        // Fill demo credentials
        function fillDemo(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // Highlight the fields
            document.getElementById('username').style.borderColor = '#2ecc71';
            document.getElementById('password').style.borderColor = '#2ecc71';
            
            setTimeout(() => {
                document.getElementById('username').style.borderColor = '';
                document.getElementById('password').style.borderColor = '';
            }, 3000);
            
            // Show toast notification
            showToast('Demo credentials filled! Click Sign In to continue.');
        }
        
        // Show toast notification
        function showToast(message) {
            const toastEl = document.getElementById('toast');
            document.getElementById('toastMessage').textContent = message;
            const toast = new bootstrap.Toast(toastEl, {
                animation: true,
                autohide: true,
                delay: 3000
            });
            toast.show();
        }
        
        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.disabled = true;
            
            // Re-enable after 5 seconds if no response
            setTimeout(() => {
                btn.classList.remove('loading');
                btn.disabled = false;
            }, 5000);
        });
        
        // Enter key support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const form = document.getElementById('loginForm');
                if (document.activeElement === document.getElementById('username') ||
                    document.activeElement === document.getElementById('password')) {
                    form.submit();
                }
            }
        });
        
        // Show toast if there's an error or success message
        document.addEventListener('DOMContentLoaded', function() {
            const alertEl = document.querySelector('.alert');
            if (alertEl) {
                showToast(alertEl.textContent.trim());
            }
        });
    </script>
</body>
</html>