<?php
// ============================================
// HOD - SETTINGS
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_HOD);

$message = '';
$error = '';

$userId = $_SESSION['user_id'];

// Get user profile
$stmt = $pdo->prepare("
    SELECT u.*, r.role_name, d.dept_name 
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    WHERE u.user_id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die('User not found.');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // Update Profile
        if ($action === 'update_profile') {
            $fullName = Security::sanitizeInput($_POST['full_name']);
            $phone = Security::sanitizeInput($_POST['phone']);
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } elseif (empty($fullName)) {
                $error = 'Full name is required.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET full_name = ?, phone = ?, email = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$fullName, $phone, $email, $userId]);
                    
                    $_SESSION['full_name'] = $fullName;
                    
                    $message = 'Profile updated successfully!';
                    Security::logEvent($userId, 'UPDATE_PROFILE', 'Updated profile');
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                    
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        
        // Change Password
        elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if (!Security::verifyPassword($currentPassword, $user['password_hash'])) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                $error = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match.';
            } else {
                $validationErrors = Security::validatePassword($newPassword);
                if (!empty($validationErrors)) {
                    $error = implode('<br>', $validationErrors);
                } else {
                    try {
                        $hashedPassword = Security::hashPassword($newPassword);
                        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                        $stmt->execute([$hashedPassword, $userId]);
                        
                        $message = 'Password changed successfully!';
                        Security::logEvent($userId, 'CHANGE_PASSWORD', 'Changed password');
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Get department info
$deptId = $_SESSION['dept_id'] ?? 0;
$department = null;
if ($deptId) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE dept_id = ?");
    $stmt->execute([$deptId]);
    $department = $stmt->fetch();
}

// Get system settings
$systemSettings = $pdo->query("
    SELECT setting_key, setting_value 
    FROM system_settings 
    WHERE setting_group IN ('general', 'financial')
")->fetchAll();

$settings = [];
foreach ($systemSettings as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Settings - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .settings-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .settings-card .card-title {
            font-weight: 700;
            color: #2C3E50;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .settings-card .card-title i {
            color: #f39c12;
            margin-right: 10px;
        }
        .settings-group {
            margin-bottom: 20px;
        }
        .settings-group label {
            font-weight: 600;
            color: #2C3E50;
            font-size: 14px;
        }
        .settings-group .help-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #f39c12;
            padding: 3px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 10px;
            border-left: 4px solid #f39c12;
            margin-bottom: 10px;
        }
        .info-box .label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
        }
        .info-box .value {
            font-weight: 600;
            margin-top: 3px;
        }
        .dept-info {
            background: #fff8e1;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #f39c12;
        }
    </style>
</head>
<body>
    <?php include '../../includes/hod_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-cog"></i> HOD Settings</h5>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Profile Settings -->
            <div class="col-lg-6">
                <div class="settings-card">
                    <div class="card-title">
                        <i class="fas fa-user"></i> Profile Information
                    </div>
                    
                    <form method="POST">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="text-center mb-4">
                            <img src="../../assets/images/avatar.png" alt="Profile" 
                                 class="avatar-preview" onerror="this.src='https://via.placeholder.com/100'">
                            <div class="mt-2">
                                <span class="badge bg-warning"><?php echo ucfirst($user['role_name']); ?></span>
                            </div>
                        </div>
                        
                        <div class="settings-group">
                            <label>Username</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <div class="help-text">Username cannot be changed</div>
                        </div>
                        
                        <div class="settings-group">
                            <label>Full Name *</label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        
                        <div class="settings-group">
                            <label>Email Address *</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="settings-group">
                            <label>Phone Number</label>
                            <input type="text" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                   placeholder="+251 9XX XXX XXX">
                        </div>
                        
                        <div class="settings-group">
                            <label>Department</label>
                            <div class="dept-info">
                                <i class="fas fa-building text-warning"></i>
                                <strong><?php echo htmlspecialchars($department['dept_name'] ?? 'N/A'); ?></strong>
                                <span class="ms-2 text-muted">(<?php echo htmlspecialchars($department['dept_code'] ?? ''); ?>)</span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-warning w-100">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="col-lg-6">
                <div class="settings-card">
                    <div class="card-title">
                        <i class="fas fa-key"></i> Change Password
                    </div>
                    
                    <form method="POST">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="settings-group">
                            <label>Current Password *</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        
                        <div class="settings-group">
                            <label>New Password *</label>
                            <input type="password" class="form-control" name="new_password" required>
                            <div class="help-text">
                                Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters with uppercase, lowercase, number, and special character
                            </div>
                        </div>
                        
                        <div class="settings-group">
                            <label>Confirm New Password *</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-warning w-100">
                            <i class="fas fa-exchange-alt"></i> Change Password
                        </button>
                    </form>
                </div>
                
                <!-- System Information -->
                <div class="settings-card">
                    <div class="card-title">
                        <i class="fas fa-info-circle"></i> System Information
                    </div>
                    
                    <div class="settings-group">
                        <label>Account Status</label>
                        <div class="info-box">
                            <div class="value">
                                <i class="fas fa-<?php echo $user['is_active'] ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i>
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                <span class="ms-3">
                                    <i class="fas fa-<?php echo $user['is_verified'] ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i>
                                    <?php echo $user['is_verified'] ? 'Verified' : 'Not Verified'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="settings-group">
                        <label>Last Login</label>
                        <div class="info-box">
                            <div class="value"><?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?></div>
                        </div>
                    </div>
                    
                    <div class="settings-group">
                        <label>Account Created</label>
                        <div class="info-box">
                            <div class="value"><?php echo formatDateTime($user['created_at']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>