<?php
// ============================================
// ADMIN - SYSTEM SETTINGS
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_ADMIN);

$message = '';
$error = '';

// Get all settings
$settings = $pdo->query("SELECT * FROM system_settings ORDER BY setting_group, setting_key")->fetchAll();
$settingsByGroup = [];
foreach ($settings as $setting) {
    $settingsByGroup[$setting['setting_group']][] = $setting;
}

// Update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        try {
            $pdo->beginTransaction();
            
            foreach ($_POST['settings'] as $key => $value) {
                $value = Security::sanitizeInput($value);
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
            
            $pdo->commit();
            $message = 'Settings updated successfully!';
            
            // Log activity
            Security::logEvent($_SESSION['user_id'], 'UPDATE_SETTINGS', 'System settings updated');
            
            // Refresh settings
            $settings = $pdo->query("SELECT * FROM system_settings ORDER BY setting_group, setting_key")->fetchAll();
            $settingsByGroup = [];
            foreach ($settings as $setting) {
                $settingsByGroup[$setting['setting_group']][] = $setting;
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error updating settings: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <style>
        .settings-group {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .settings-group h6 {
            color: #2C3E50;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .settings-group .form-control,
        .settings-group .form-select {
            max-width: 400px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-cog"></i> System Settings</h5>
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
        
        <form method="POST">
            <?php echo Security::csrfField(); ?>
            
            <?php foreach ($settingsByGroup as $group => $groupSettings): ?>
            <div class="settings-group">
                <h6><i class="fas fa-<?php echo $group == 'general' ? 'globe' : ($group == 'financial' ? 'money-bill' : 'cog'); ?>"></i> 
                    <?php echo ucfirst($group); ?>
                </h6>
                
                <div class="row">
                    <?php foreach ($groupSettings as $setting): ?>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">
                                <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                            </label>
                            <?php if ($setting['setting_key'] == 'maintenance_mode'): ?>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" 
                                           name="settings[<?php echo $setting['setting_key']; ?>]" 
                                           value="true" <?php echo $setting['setting_value'] == 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Enabled</label>
                                </div>
                            <?php elseif (strpos($setting['setting_key'], 'email') !== false): ?>
                                <input type="email" class="form-control" 
                                       name="settings[<?php echo $setting['setting_key']; ?>]" 
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                            <?php elseif (strpos($setting['setting_key'], 'password') !== false): ?>
                                <input type="password" class="form-control" 
                                       name="settings[<?php echo $setting['setting_key']; ?>]" 
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                            <?php elseif (is_numeric($setting['setting_value'])): ?>
                                <input type="number" class="form-control" 
                                       name="settings[<?php echo $setting['setting_key']; ?>]" 
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                            <?php else: ?>
                                <input type="text" class="form-control" 
                                       name="settings[<?php echo $setting['setting_key']; ?>]" 
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                            <?php endif; ?>
                            <?php if ($setting['description']): ?>
                                <small class="text-muted"><?php echo $setting['description']; ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="text-end">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Save All Settings
                </button>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>