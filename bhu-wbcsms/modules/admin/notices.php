<?php
// ============================================
// ADMIN - NOTICES MANAGEMENT
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_ADMIN);

$message = '';
$error = '';
$editNotice = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // CREATE NOTICE
        if ($action === 'create') {
            $title = Security::sanitizeInput($_POST['title']);
            $content = Security::sanitizeInput($_POST['content']);
            $noticeType = $_POST['notice_type'];
            $targetAudience = $_POST['target_audience'];
            $deptId = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
            $expirationDate = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO notices (title, content, notice_type, target_audience, dept_id, 
                                         expiration_date, is_active, posted_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $content, $noticeType, $targetAudience, $deptId, 
                               $expirationDate, $isActive, $_SESSION['user_id']]);
                
                $message = 'Notice posted successfully!';
                Security::logEvent($_SESSION['user_id'], 'POST_NOTICE', "Posted notice: {$title}");
                
                // Send email notifications to target audience
                if ($isActive) {
                    sendNoticeNotifications($noticeId ?? $pdo->lastInsertId());
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // UPDATE NOTICE
        elseif ($action === 'update') {
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
                $stmt->execute([$title, $content, $noticeType, $targetAudience, 
                               $deptId, $expirationDate, $isActive, $noticeId]);
                
                $message = 'Notice updated successfully!';
                Security::logEvent($_SESSION['user_id'], 'UPDATE_NOTICE', "Updated notice: {$title}");
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // DELETE NOTICE
        elseif ($action === 'delete') {
            $noticeId = (int)$_POST['notice_id'];
            $stmt = $pdo->prepare("DELETE FROM notices WHERE notice_id = ?");
            $stmt->execute([$noticeId]);
            $message = 'Notice deleted successfully!';
            Security::logEvent($_SESSION['user_id'], 'DELETE_NOTICE', "Deleted notice ID: {$noticeId}");
        }
        
        // TOGGLE STATUS
        elseif ($action === 'toggle_status') {
            $noticeId = (int)$_POST['notice_id'];
            $status = (int)$_POST['status'];
            $stmt = $pdo->prepare("UPDATE notices SET is_active = ? WHERE notice_id = ?");
            $stmt->execute([$status, $noticeId]);
            $message = 'Notice status updated!';
        }
    }
}

// Get edit data
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM notices WHERE notice_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editNotice = $stmt->fetch();
}

// Get all notices
$notices = $pdo->query("
    SELECT n.*, u.full_name as posted_by_name
    FROM notices n
    LEFT JOIN users u ON n.posted_by = u.user_id
    ORDER BY n.created_at DESC
")->fetchAll();

// Get departments for dropdown
$departments = $pdo->query("SELECT * FROM departments WHERE status = 'active' ORDER BY dept_name")->fetchAll();

// Get stats
$totalNotices = count($notices);
$activeNotices = array_filter($notices, function($n) { return $n['is_active'] == 1; });
$expiredNotices = array_filter($notices, function($n) { 
    return $n['expiration_date'] && strtotime($n['expiration_date']) < time(); 
});

// Helper function to send notifications
function sendNoticeNotifications($noticeId) {
    global $pdo;
    // Implementation for email/SMS notifications
    // This will be called when a notice is posted
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
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .notice-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .notice-card .notice-title {
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 5px;
        }
        .notice-card .notice-meta {
            font-size: 13px;
            color: #7f8c8d;
        }
        .notice-card .notice-content {
            margin-top: 10px;
            color: #2C3E50;
        }
        .notice-card .notice-badges {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-bullhorn"></i> Notices Management</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNoticeModal">
                <i class="fas fa-plus"></i> Post New Notice
            </button>
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
        
        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-bullhorn"></i></div>
                    <div class="number text-primary"><?php echo $totalNotices; ?></div>
                    <div class="label">Total Notices</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <div class="number text-success"><?php echo count($activeNotices); ?></div>
                    <div class="label">Active Notices</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-clock"></i></div>
                    <div class="number text-warning"><?php echo count($expiredNotices); ?></div>
                    <div class="label">Expired Notices</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-user"></i></div>
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(DISTINCT posted_by) FROM notices");
                    $posters = $stmt->fetchColumn();
                    ?>
                    <div class="number text-info"><?php echo $posters; ?></div>
                    <div class="label">Posters</div>
                </div>
            </div>
        </div>
        
        <!-- Notices List -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover" id="noticeTable">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Audience</th>
                            <th>Posted By</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notices as $notice): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($notice['title']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $notice['notice_type'] == 'general' ? 'primary' : 
                                        ($notice['notice_type'] == 'deadline' ? 'warning' : 
                                        ($notice['notice_type'] == 'payment' ? 'success' : 
                                        ($notice['notice_type'] == 'disbursement' ? 'info' : 'danger'))); 
                                ?>">
                                    <?php echo ucfirst($notice['notice_type']); ?>
                                </span>
                            </td>
                            <td><?php echo ucfirst($notice['target_audience']); ?></td>
                            <td><?php echo htmlspecialchars($notice['posted_by_name'] ?? 'System'); ?></td>
                            <td>
                                <?php if ($notice['expiration_date']): ?>
                                    <?php echo date('M d, Y', strtotime($notice['expiration_date'])); ?>
                                    <?php if (strtotime($notice['expiration_date']) < time()): ?>
                                        <span class="badge bg-danger">Expired</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($notice['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?php echo $notice['notice_id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-outline-<?php echo $notice['is_active'] ? 'warning' : 'success'; ?>" 
                                            onclick="toggleNotice(<?php echo $notice['notice_id']; ?>, <?php echo $notice['is_active'] ? 0 : 1; ?>)">
                                        <i class="fas fa-<?php echo $notice['is_active'] ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deleteNotice(<?php echo $notice['notice_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Notice Modal -->
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
                            <input type="text" class="form-control" name="title" required 
                                   placeholder="Enter notice title">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Content *</label>
                            <textarea class="form-control" name="content" rows="5" required 
                                      placeholder="Enter notice content"></textarea>
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
    <div class="modal fade show" id="editNoticeModal" tabindex="-1" style="display: block;">
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#noticeTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search notices:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ notices"
                }
            });
        });
        
        function toggleNotice(noticeId, status) {
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
        
        function deleteNotice(noticeId) {
            if (confirm('Are you sure you want to delete this notice?')) {
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