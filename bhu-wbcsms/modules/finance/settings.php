<?php
// ============================================
// FINANCE - SETTINGS
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_FINANCE);

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
        
        // Update Finance Preferences
        elseif ($action === 'update_preferences') {
            $defaultVerification = Security::sanitizeInput($_POST['default_verification']);
            $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
            $itemsPerPage = (int)$_POST['items_per_page'];
            
            try {
                // Delete existing preferences
                $pdo->query("
                    DELETE FROM system_settings 
                    WHERE setting_group = 'finance_preferences' 
                    AND setting_key IN ('default_verification', 'email_notifications', 'items_per_page')
                ");
                
                // Insert new preferences
                $prefs = [
                    'default_verification' => $defaultVerification,
                    'email_notifications' => $emailNotifications,
                    'items_per_page' => $itemsPerPage
                ];
                
                $stmt = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value, setting_group, description)
                    VALUES (?, ?, 'finance_preferences', 'Finance preference')
                ");
                
                foreach ($prefs as $key => $value) {
                    $stmt->execute([$key, $value]);
                }
                
                $message = 'Preferences updated successfully!';
                Security::logEvent($userId, 'UPDATE_PREFERENCES', 'Updated preferences');
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get finance preferences
$preferences = [];
$stmt = $pdo->query("
    SELECT setting_key, setting_value 
    FROM system_settings 
    WHERE setting_group = 'finance_preferences'
");
while ($row = $stmt->fetch()) {
    $preferences[$row['setting_key']] = $row['setting_value'];
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
    <title>Finance Settings - <?php echo SITE_NAME; ?></title>
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
            color: #e74c3c;
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
            border: 3px solid #e74c3c;
            padding: 3px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 10px;
            border-left: 4px solid #e74c3c;
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
        .preference-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .preference-item:last-child {
            border-bottom: none;
        }
        .preference-item .pref-label {
            font-weight: 500;
        }
        .preference-item .pref-desc {
            font-size: 12px;
            color: #7f8c8d;
        }
        .form-switch .form-check-input {
            width: 50px;
            height: 26px;
        }
        .form-switch .form-check-input:checked {
            background-color: #e74c3c;
            border-color: #e74c3c;
        }
    </style>
</head>
<body>
    <?php include '../../includes/finance_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-cog"></i> Finance Settings</h5>
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
                                <span class="badge bg-danger"><?php echo ucfirst($user['role_name']); ?></span>
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
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['dept_name'] ?? 'N/A'); ?>" disabled>
                        </div>
                        
                        <div class="settings-group">
                            <label>Account Status</label>
                            <div class="info-box">
                                <div class="label">Status</div>
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
                        
                        <button type="submit" class="btn btn-danger w-100">
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
                        
                        <button type="submit" class="btn btn-danger w-100">
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
                        <label>System Name</label>
                        <div class="info-box">
                            <div class="value"><?php echo $settings['site_name'] ?? SITE_NAME; ?></div>
                        </div>
                    </div>
                    
                    <div class="settings-group">
                        <label>Maximum Cost-Share</label>
                        <div class="info-box">
                            <div class="value"><?php echo formatCurrency($settings['max_cost_share'] ?? 100000); ?></div>
                        </div>
                    </div>
                    
                    <div class="settings-group">
                        <label>Default Currency</label>
                        <div class="info-box">
                            <div class="value"><?php echo $settings['default_currency'] ?? 'ETB'; ?></div>
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
        
        <!-- Finance Preferences -->
        <div class="settings-card">
            <div class="card-title">
                <i class="fas fa-sliders-h"></i> Finance Preferences
            </div>
            
            <form method="POST">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="update_preferences">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="preference-item">
                            <div>
                                <div class="pref-label">
                                    <i class="fas fa-check-double"></i> Default Verification
                                </div>
                                <div class="pref-desc">Default action for payments</div>
                            </div>
                            <select class="form-select" name="default_verification" style="width: 150px;">
                                <option value="verify" <?php echo ($preferences['default_verification'] ?? 'verify') == 'verify' ? 'selected' : ''; ?>>Verify</option>
                                <option value="pending" <?php echo ($preferences['default_verification'] ?? 'verify') == 'pending' ? 'selected' : ''; ?>>Keep Pending</option>
                            </select>
                        </div>
                        
                        <div class="preference-item">
                            <div>
                                <div class="pref-label">
                                    <i class="fas fa-envelope"></i> Email Notifications
                                </div>
                                <div class="pref-desc">Receive email notifications for new payments</div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="email_notifications" 
                                       <?php echo ($preferences['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="preference-item">
                            <div>
                                <div class="pref-label">
                                    <i class="fas fa-list"></i> Items Per Page
                                </div>
                                <div class="pref-desc">Number of items in tables</div>
                            </div>
                            <select class="form-select" name="items_per_page" style="width: 100px;">
                                <?php 
                                $options = [10, 25, 50, 100];
                                $selected = $preferences['items_per_page'] ?? 25;
                                foreach ($options as $opt): 
                                ?>
                                    <option value="<?php echo $opt; ?>" <?php echo $selected == $opt ? 'selected' : ''; ?>>
                                        <?php echo $opt; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>