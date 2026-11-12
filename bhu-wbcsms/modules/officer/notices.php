<?php
// ============================================
// NOTICES MANAGEMENT (WORKING)
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireLogin();

$message = '';
$error = '';
$editNotice = null;

// Get user role and department
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role_id'];
$userDept = $_SESSION['dept_id'] ?? null;

// Handle actions (only for Admin and Officer)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // CREATE NOTICE
        if ($action === 'create') {
            // Only Admin and Officer can create notices
            if (!Auth::hasRole(ROLE_ADMIN) && !Auth::hasRole(ROLE_OFFICER)) {
                $error = 'You do not have permission to create notices.';
            } else {
                $title = Security::sanitizeInput($_POST['title']);
                $content = Security::sanitizeInput($_POST['content']);
                $noticeType = $_POST['notice_type'];
                $targetAudience = $_POST['target_audience'];
                $deptId = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
                $expirationDate = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($title) || empty($content)) {
                    $error = 'Please fill in all required fields.';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO notices (title, content, notice_type, target_audience, dept_id, 
                                                 expiration_date, is_active, posted_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $title, $content, $noticeType, $targetAudience, 
                            $deptId, $expirationDate, $isActive, $userId
                        ]);
                        
                        $noticeId = $pdo->lastInsertId();
                        $message = 'Notice posted successfully!';
                        Security::logEvent($userId, 'POST_NOTICE', "Posted notice: {$title}");
                        
                        // Send notifications to target audience
                        if ($isActive) {
                            sendNoticeNotifications($noticeId);
                        }
                        
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        }
        
        // UPDATE NOTICE
        elseif ($action === 'update') {
            if (!Auth::hasRole(ROLE_ADMIN) && !Auth::hasRole(ROLE_OFFICER)) {
                $error = 'You do not have permission to update notices.';
            } else {
                $noticeId = (int)$_POST['notice_id'];
                $title = Security::sanitizeInput($_POST['title']);
                $content = Security::sanitizeInput($_POST['content']);
                $noticeType = $_POST['notice_type'];
                $targetAudience = $_POST['target_audience'];
                $deptId = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
                $expirationDate = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                try {
                    $stmt = $pdo->prepare("
                        UPDATE notices 
                        SET title = ?, content = ?, notice_type = ?, target_audience = ?, 
                            dept_id = ?, expiration_date = ?, is_active = ?
                        WHERE notice_id = ?
                    ");
                    $stmt->execute([
                        $title, $content, $noticeType, $targetAudience, 
                        $deptId, $expirationDate, $isActive, $noticeId
                    ]);
                    
                    $message = 'Notice updated successfully!';
                    Security::logEvent($userId, 'UPDATE_NOTICE', "Updated notice: {$title}");
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        
        // DELETE NOTICE
        elseif ($action === 'delete') {
            if (!Auth::hasRole(ROLE_ADMIN) && !Auth::hasRole(ROLE_OFFICER)) {
                $error = 'You do not have permission to delete notices.';
            } else {
                $noticeId = (int)$_POST['notice_id'];
                try {
                    $stmt = $pdo->prepare("DELETE FROM notices WHERE notice_id = ?");
                    $stmt->execute([$noticeId]);
                    $message = 'Notice deleted successfully!';
                    Security::logEvent($userId, 'DELETE_NOTICE', "Deleted notice ID: {$noticeId}");
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        
        // TOGGLE NOTICE STATUS
        elseif ($action === 'toggle_status') {
            if (!Auth::hasRole(ROLE_ADMIN) && !Auth::hasRole(ROLE_OFFICER)) {
                $error = 'You do not have permission to change notice status.';
            } else {
                $noticeId = (int)$_POST['notice_id'];
                $status = (int)$_POST['status'];
                try {
                    $stmt = $pdo->prepare("UPDATE notices SET is_active = ? WHERE notice_id = ?");
                    $stmt->execute([$status, $noticeId]);
                    $message = 'Notice status updated!';
                    Security::logEvent($userId, 'TOGGLE_NOTICE', 
                                      "Notice #{$noticeId} set to " . ($status ? 'active' : 'inactive'));
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get edit data
if (isset($_GET['edit'])) {
    if (!Auth::hasRole(ROLE_ADMIN) && !Auth::hasRole(ROLE_OFFICER)) {
        $error = 'You do not have permission to edit notices.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM notices WHERE notice_id = ?");
        $stmt->execute([$_GET['edit']]);
        $editNotice = $stmt->fetch();
    }
}

// Build notice query based on user role
$query = "
    SELECT n.*, u.full_name as posted_by_name
    FROM notices n
    LEFT JOIN users u ON n.posted_by = u.user_id
    WHERE 1=1
";

// Apply filters based on user role
if (Auth::hasRole(ROLE_STUDENT)) {
    // Students see: all active notices (or department-specific)
    $query .= " AND n.is_active = 1";
    $query .= " AND (n.target_audience = 'all' OR n.target_audience = 'students')";
    if ($userDept) {
        $query .= " AND (n.dept_id IS NULL OR n.dept_id = " . (int)$userDept . ")";
    }
} elseif (Auth::hasRole(ROLE_HOD)) {
    // HOD sees: all notices, with department focus
    if ($userDept) {
        $query .= " AND (n.dept_id IS NULL OR n.dept_id = " . (int)$userDept . ")";
    }
} elseif (Auth::hasRole(ROLE_FINANCE)) {
    // Finance sees: all notices, especially payment/disbursement
    $query .= " AND (n.target_audience = 'all' OR n.target_audience = 'staff')";
}

// Order by latest first
$query .= " ORDER BY n.created_at DESC";

$notices = $pdo->query($query)->fetchAll();

// Get departments for dropdown
$departments = $pdo->query("SELECT * FROM departments WHERE status = 'active' ORDER BY dept_name")->fetchAll();

// Get stats
$totalNotices = count($notices);
$activeNotices = array_filter($notices, function($n) { return $n['is_active'] == 1; });

// Send notification function
function sendNoticeNotifications($noticeId) {
    global $pdo;
    // Get notice details
    $stmt = $pdo->prepare("SELECT * FROM notices WHERE notice_id = ?");
    $stmt->execute([$noticeId]);
    $notice = $stmt->fetch();
    
    if (!$notice) return;
    
    // Get target users based on audience
    $users = [];
    
    if ($notice['target_audience'] == 'all') {
        $stmt = $pdo->query("SELECT email FROM users WHERE is_active = 1");
        $users = $stmt->fetchAll();
    } elseif ($notice['target_audience'] == 'students') {
        $stmt = $pdo->query("
            SELECT u.email 
            FROM users u
            JOIN students s ON u.user_id = s.user_id
            WHERE u.is_active = 1 AND s.enrollment_status = 'active'
        ");
        $users = $stmt->fetchAll();
    } elseif ($notice['target_audience'] == 'staff') {
        $stmt = $pdo->query("
            SELECT email FROM users 
            WHERE is_active = 1 AND role_id IN (1, 2, 3, 4)
        ");
        $users = $stmt->fetchAll();
    } elseif ($notice['target_audience'] == 'department' && $notice['dept_id']) {
        $stmt = $pdo->prepare("
            SELECT u.email 
            FROM users u
            JOIN departments d ON u.dept_id = d.dept_id
            WHERE u.is_active = 1 AND d.dept_id = ?
        ");
        $stmt->execute([$notice['dept_id']]);
        $users = $stmt->fetchAll();
    }
    
    // Send email to each user (batch)
    foreach ($users as $user) {
        sendEmail($user['email'], "New Notice: {$notice['title']}", "
            <h3>New Notice Posted</h3>
            <p><strong>Title:</strong> {$notice['title']}</p>
            <p><strong>Type:</strong> " . ucfirst($notice['notice_type']) . "</p>
            <p><strong>Content:</strong></p>
            <p>" . nl2br($notice['content']) . "</p>
            <p><a href='" . SITE_URL . "modules/notices.php'>View all notices</a></p>
        ");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notices - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .notice-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }
        .notice-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        .notice-card .notice-title {
            font-weight: 700;
            font-size: 18px;
            color: #2C3E50;
        }
        .notice-card .notice-meta {
            font-size: 13px;
            color: #7f8c8d;
            margin: 8px 0;
        }
        .notice-card .notice-content {
            margin: 12px 0;
            color: #34495E;
        }
        .notice-card .notice-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .notice-card .notice-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f2f5;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .stats-card .number {
            font-size: 28px;
            font-weight: 700;
        }
        .stats-card .label {
            color: #7f8c8d;
            font-size: 14px;
        }
        .notice-type-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .notice-type-badge.general { background: #3498db; color: white; }
        .notice-type-badge.deadline { background: #f39c12; color: white; }
        .notice-type-badge.payment { background: #2ecc71; color: white; }
        .notice-type-badge.disbursement { background: #1abc9c; color: white; }
        .notice-type-badge.alert { background: #e74c3c; color: white; }
    </style>
</head>
<body>
    <?php 
    if (Auth::hasRole(ROLE_ADMIN)) {
        include '../includes/admin_sidebar.php';
    } elseif (Auth::hasRole(ROLE_OFFICER)) {
        include '../../includes/officer_sidebar.php';
    } else {
        include '../../includes/student_sidebar.php';
    }
    ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-bullhorn"></i> Notices</h5>
            <?php if (Auth::hasRole(ROLE_ADMIN) || Auth::hasRole(ROLE_OFFICER)): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNoticeModal">
                    <i class="fas fa-plus"></i> Post New Notice
                </button>
            <?php endif; ?>
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
        
        <!-- Stats (Admin/Officer only) -->
        <?php if (Auth::hasRole(ROLE_ADMIN) || Auth::hasRole(ROLE_OFFICER)): ?>
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="number text-primary"><?php echo $totalNotices; ?></div>
                    <div class="label">Total Notices</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="number text-success"><?php echo count($activeNotices); ?></div>
                    <div class="label">Active Notices</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="number text-secondary"><?php echo $totalNotices - count($activeNotices); ?></div>
                    <div class="label">Inactive Notices</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Notices List -->
        <?php if (empty($notices)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-bullhorn fa-4x text-muted mb-3"></i>
                    <h5>No Notices Found</h5>
                    <p class="text-muted">
                        <?php if (Auth::hasRole(ROLE_ADMIN) || Auth::hasRole(ROLE_OFFICER)): ?>
                            Click "Post New Notice" to create your first notice.
                        <?php else: ?>
                            There are no notices available at this time. Please check back later.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($notices as $notice): ?>
            <div class="notice-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="notice-title"><?php echo htmlspecialchars($notice['title']); ?></div>
                        <div class="notice-meta">
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($notice['posted_by_name'] ?? 'System'); ?>
                            <span class="ms-3">
                                <i class="far fa-clock"></i> 
                                <?php echo timeAgo($notice['created_at']); ?>
                            </span>
                            <?php if ($notice['expiration_date']): ?>
                                <span class="ms-3">
                                    <i class="fas fa-hourglass-end"></i> 
                                    Expires: <?php echo formatDate($notice['expiration_date']); ?>
                                    <?php if (strtotime($notice['expiration_date']) < time()): ?>
                                        <span class="badge bg-danger">Expired</span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="notice-badges">
                        <span class="notice-type-badge <?php echo $notice['notice_type']; ?>">
                            <?php echo ucfirst($notice['notice_type']); ?>
                        </span>
                        <?php if ($notice['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                        <span class="badge bg-info"><?php echo ucfirst($notice['target_audience']); ?></span>
                    </div>
                </div>
                
                <div class="notice-content">
                    <?php echo nl2br(htmlspecialchars($notice['content'])); ?>
                </div>
                
                <!-- Admin Actions -->
                <?php if (Auth::hasRole(ROLE_ADMIN) || Auth::hasRole(ROLE_OFFICER)): ?>
                <div class="notice-actions">
                    <div class="d-flex gap-2">
                        <a href="?edit=<?php echo $notice['notice_id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <button class="btn btn-sm btn-outline-<?php echo $notice['is_active'] ? 'warning' : 'success'; ?>" 
                                onclick="toggleNotice(<?php echo $notice['notice_id']; ?>, <?php echo $notice['is_active'] ? 0 : 1; ?>)">
                            <i class="fas fa-<?php echo $notice['is_active'] ? 'ban' : 'check'; ?>"></i>
                            <?php echo $notice['is_active'] ? 'Deactivate' : 'Activate'; ?>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteNotice(<?php echo $notice['notice_id']; ?>)">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Add Notice Modal -->
    <?php if (Auth::hasRole(ROLE_ADMIN) || Auth::hasRole(ROLE_OFFICER)): ?>
    <div class="modal fade" id="addNoticeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Post New Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Content *</label>
                            <textarea class="form-control" name="content" rows="5" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Notice Type</label>
                                    <select class="form-select" name="notice_type">
                                        <option value="general">General</option>
                                        <option value="deadline">Deadline</option>
                                        <option value="payment">Payment</option>
                                        <option value="disbursement">Disbursement</option>
                                        <option value="alert">Alert</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Target Audience</label>
                                    <select class="form-select" name="target_audience">
                                        <option value="all">All Users</option>
                                        <option value="students">Students Only</option>
                                        <option value="staff">Staff Only</option>
                                        <option value="department">Specific Department</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Department (if specific)</label>
                                    <select class="form-select" name="dept_id">
                                        <option value="">All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['dept_id']; ?>">
                                                <?php echo htmlspecialchars($dept['dept_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Expiration Date</label>
                                    <input type="date" class="form-control" name="expiration_date">
                                    <small class="text-muted">Leave empty for no expiration</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" checked>
                                <label class="form-check-label">
                                    Publish immediately
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Post Notice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Notice Modal -->
    <?php if ($editNotice): ?>
    <div class="modal fade show" id="editNoticeModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Notice</h5>
                    <a href="?" class="btn-close"></a>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="notice_id" value="<?php echo $editNotice['notice_id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" 
                                   value="<?php echo htmlspecialchars($editNotice['title']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Content *</label>
                            <textarea class="form-control" name="content" rows="5" required><?php 
                                echo htmlspecialchars($editNotice['content']); 
                            ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Notice Type</label>
                                    <select class="form-select" name="notice_type">
                                        <?php
                                        $types = ['general', 'deadline', 'payment', 'disbursement', 'alert'];
                                        foreach ($types as $type):
                                        ?>
                                            <option value="<?php echo $type; ?>" 
                                                <?php echo $editNotice['notice_type'] == $type ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($type); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Target Audience</label>
                                    <select class="form-select" name="target_audience">
                                        <option value="all" <?php echo $editNotice['target_audience'] == 'all' ? 'selected' : ''; ?>>All Users</option>
                                        <option value="students" <?php echo $editNotice['target_audience'] == 'students' ? 'selected' : ''; ?>>Students Only</option>
                                        <option value="staff" <?php echo $editNotice['target_audience'] == 'staff' ? 'selected' : ''; ?>>Staff Only</option>
                                        <option value="department" <?php echo $editNotice['target_audience'] == 'department' ? 'selected' : ''; ?>>Specific Department</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Department (if specific)</label>
                                    <select class="form-select" name="dept_id">
                                        <option value="">All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['dept_id']; ?>"
                                                <?php echo $dept['dept_id'] == $editNotice['dept_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['dept_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Expiration Date</label>
                                    <input type="date" class="form-control" name="expiration_date" 
                                           value="<?php echo $editNotice['expiration_date']; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       <?php echo $editNotice['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="?" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Notice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleNotice(noticeId, status) {
            if (confirm('Are you sure you want to ' + (status ? 'activate' : 'deactivate') + ' this notice?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="notice_id" value="${noticeId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteNotice(noticeId) {
            if (confirm('Are you sure you want to delete this notice? This cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="notice_id" value="${noticeId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>