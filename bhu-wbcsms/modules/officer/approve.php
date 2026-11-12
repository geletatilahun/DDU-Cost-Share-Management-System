<?php
// ============================================
// OFFICER - QUICK APPROVAL PAGE
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_OFFICER);

$message = '';
$error = '';

// Get pending applications count
$pendingCount = $pdo->query("SELECT COUNT(*) FROM cost_share_applications WHERE status = 'pending'")->fetchColumn();

// Get all pending applications with student info
$pendingApps = $pdo->query("
    SELECT a.*, 
           s.full_name as student_name, 
           s.student_code,
           s.program,
           s.email as student_email,
           d.dept_name,
           d.dept_code
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    JOIN departments d ON s.department_id = d.dept_id
    WHERE a.status = 'pending'
    ORDER BY a.created_at ASC
")->fetchAll();

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        $applicationId = (int)$_POST['application_id'];
        
        // Get application details for notification
        $stmt = $pdo->prepare("
            SELECT a.*, s.full_name, s.email, s.student_code 
            FROM cost_share_applications a
            JOIN students s ON a.student_id = s.student_id
            WHERE a.application_id = ?
        ");
        $stmt->execute([$applicationId]);
        $app = $stmt->fetch();
        
        if (!$app) {
            $error = 'Application not found.';
        } else {
            try {
                if ($action === 'approve') {
                    $comment = Security::sanitizeInput($_POST['comment'] ?? '');
                    
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("
                        UPDATE cost_share_applications 
                        SET status = 'officer_approved', 
                            officer_approved_by = ?,
                            officer_approval_date = NOW(),
                            officer_comment = ?
                        WHERE application_id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $comment, $applicationId]);
                    
                    $pdo->commit();
                    
                    $message = 'Application #' . $applicationId . ' approved successfully!';
                    Security::logEvent($_SESSION['user_id'], 'APPROVE_APPLICATION', 
                                      "Approved application #{$applicationId} for {$app['full_name']}");
                    
                    // Send email notification
                    sendEmail($app['email'], 'Cost-Share Application Approved (Level 1)', "
                        <h3>Application Status Update</h3>
                        <p>Dear {$app['full_name']},</p>
                        <p>Your cost-share application has been <strong>approved</strong> by the Cost-Share Officer.</p>
                        <p><strong>Amount:</strong> " . formatCurrency($app['requested_amount']) . "</p>
                        <p><strong>Status:</strong> Awaiting Department Head approval</p>
                        <p>You will be notified once the final decision is made.</p>
                        " . ($comment ? "<p><strong>Officer's Comment:</strong> {$comment}</p>" : "") . "
                    ");
                    
                } elseif ($action === 'reject') {
                    $reason = Security::sanitizeInput($_POST['reject_reason']);
                    
                    if (empty($reason)) {
                        $error = 'Please provide a reason for rejection.';
                    } else {
                        $pdo->beginTransaction();
                        
                        $stmt = $pdo->prepare("
                            UPDATE cost_share_applications 
                            SET status = 'rejected', 
                                officer_approved_by = ?,
                                officer_approval_date = NOW(),
                                officer_comment = ?
                            WHERE application_id = ?
                        ");
                        $stmt->execute([$_SESSION['user_id'], $reason, $applicationId]);
                        
                        $pdo->commit();
                        
                        $message = 'Application #' . $applicationId . ' rejected.';
                        Security::logEvent($_SESSION['user_id'], 'REJECT_APPLICATION', 
                                          "Rejected application #{$applicationId} for {$app['full_name']}");
                        
                        // Send rejection email
                        sendEmail($app['email'], 'Cost-Share Application Status Update', "
                            <h3>Application Status Update</h3>
                            <p>Dear {$app['full_name']},</p>
                            <p>Your cost-share application has been <strong style='color: red;'>rejected</strong>.</p>
                            <p><strong>Reason:</strong> {$reason}</p>
                            <p>If you have any questions, please contact the Cost-Share Officer.</p>
                        ");
                    }
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['selected_apps'])) {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $selectedApps = array_map('intval', $_POST['selected_apps']);
        $bulkAction = $_POST['bulk_action'];
        
        if (empty($selectedApps)) {
            $error = 'No applications selected.';
        } else {
            try {
                $pdo->beginTransaction();
                $processed = 0;
                
                foreach ($selectedApps as $appId) {
                    if ($bulkAction === 'approve') {
                        $stmt = $pdo->prepare("
                            UPDATE cost_share_applications 
                            SET status = 'officer_approved', 
                                officer_approved_by = ?,
                                officer_approval_date = NOW()
                            WHERE application_id = ? AND status = 'pending'
                        ");
                        $stmt->execute([$_SESSION['user_id'], $appId]);
                        $processed += $stmt->rowCount();
                    } elseif ($bulkAction === 'reject') {
                        $stmt = $pdo->prepare("
                            UPDATE cost_share_applications 
                            SET status = 'rejected', 
                                officer_approved_by = ?,
                                officer_approval_date = NOW()
                            WHERE application_id = ? AND status = 'pending'
                        ");
                        $stmt->execute([$_SESSION['user_id'], $appId]);
                        $processed += $stmt->rowCount();
                    }
                }
                
                $pdo->commit();
                $message = $processed . ' application(s) ' . $bulkAction . 'd successfully!';
                Security::logEvent($_SESSION['user_id'], 'BULK_ACTION', 
                                  "Bulk {$bulkAction}: {$processed} applications");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Get recent approved applications
$recentApproved = $pdo->query("
    SELECT a.*, s.full_name, s.student_code
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    WHERE a.status = 'officer_approved'
    ORDER BY a.officer_approval_date DESC
    LIMIT 10
")->fetchAll();

// Get statistics
$stats = [];
$stats['pending'] = $pendingCount;
$stats['total_today'] = $pdo->query("
    SELECT COUNT(*) FROM cost_share_applications WHERE DATE(created_at) = CURDATE()
")->fetchColumn();
$stats['total_week'] = $pdo->query("
    SELECT COUNT(*) FROM cost_share_applications WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())
")->fetchColumn();
$stats['avg_amount'] = $pdo->query("
    SELECT AVG(requested_amount) FROM cost_share_applications WHERE status = 'pending'
")->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Approval - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stats-card .number {
            font-size: 32px;
            font-weight: 700;
        }
        .stats-card .label {
            color: #7f8c8d;
            font-size: 14px;
        }
        .stats-card .icon {
            font-size: 30px;
            opacity: 0.2;
            float: right;
        }
        .application-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #f39c12;
            transition: all 0.3s;
        }
        .application-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        .application-card .app-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .application-card .app-student {
            font-weight: 700;
            font-size: 16px;
        }
        .application-card .app-details {
            margin-top: 10px;
            font-size: 14px;
            color: #7f8c8d;
        }
        .application-card .app-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state i {
            font-size: 60px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        .empty-state h5 {
            color: #2C3E50;
        }
        .empty-state p {
            color: #7f8c8d;
        }
        .badge-pending {
            background: #f39c12;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/officer_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-check-double"></i> Application Approval</h5>
            <div>
                <span class="badge bg-warning me-2">
                    <i class="fas fa-clock"></i> <?php echo $pendingCount; ?> Pending
                </span>
                <a href="applications.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-list"></i> View All
                </a>
            </div>
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
                    <div class="icon"><i class="fas fa-clock"></i></div>
                    <div class="number text-warning"><?php echo $stats['pending']; ?></div>
                    <div class="label">Pending Approval</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="number text-primary"><?php echo $stats['total_today']; ?></div>
                    <div class="label">Today's Applications</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-calendar-week"></i></div>
                    <div class="number text-info"><?php echo $stats['total_week']; ?></div>
                    <div class="label">This Week</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="number text-success"><?php echo formatCurrency($stats['avg_amount']); ?></div>
                    <div class="label">Average Amount</div>
                </div>
            </div>
        </div>
        
        <!-- Bulk Actions -->
        <?php if (!empty($pendingApps)): ?>
        <div class="mb-3">
            <form method="POST" id="bulkForm">
                <?php echo Security::csrfField(); ?>
                <div class="card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-check-double me-2"></i>
                            <strong>Bulk Actions:</strong>
                            <span class="ms-2 text-muted" id="selectedCount">0 selected</span>
                        </div>
                        <div>
                            <select name="bulk_action" class="form-select d-inline-block" style="width: 150px;">
                                <option value="approve">Approve Selected</option>
                                <option value="reject">Reject Selected</option>
                            </select>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-play"></i> Apply
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Pending Applications -->
        <?php if (empty($pendingApps)): ?>
            <div class="application-card">
                <div class="empty-state">
                    <i class="fas fa-check-circle text-success"></i>
                    <h5>All Caught Up!</h5>
                    <p>There are no pending applications to review at this time.</p>
                    <span class="badge bg-success">✅ Everything is up to date</span>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($pendingApps as $app): ?>
            <div class="application-card">
                <div class="app-header">
                    <div>
                        <div class="app-student">
                            <?php echo htmlspecialchars($app['student_name']); ?>
                            <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($app['student_code']); ?></span>
                            <span class="badge-pending ms-2">Pending</span>
                        </div>
                        <div class="app-details">
                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($app['dept_name']); ?>
                            <span class="ms-3"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($app['program']); ?></span>
                            <span class="ms-3"><i class="fas fa-calendar"></i> <?php echo formatDate($app['application_date']); ?></span>
                        </div>
                    </div>
                    <div>
                        <span class="h5 text-primary"><?php echo formatCurrency($app['requested_amount']); ?></span>
                    </div>
                </div>
                
                <?php if ($app['reason']): ?>
                <div class="mt-2 p-2 bg-light rounded">
                    <small class="text-muted"><i class="fas fa-quote-left"></i> <?php echo nl2br(htmlspecialchars($app['reason'])); ?></small>
                </div>
                <?php endif; ?>
                
                <div class="app-actions">
                    <form method="POST" class="d-inline">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
                        <button type="submit" class="btn btn-success" onclick="return confirmApprove()">
                            <i class="fas fa-check"></i> Approve
                        </button>
                    </form>
                    
                    <button class="btn btn-danger" onclick="showRejectModal(<?php echo $app['application_id']; ?>, '<?php echo htmlspecialchars($app['student_name']); ?>')">
                        <i class="fas fa-times"></i> Reject
                    </button>
                    
                    <a href="applications.php?view=<?php echo $app['application_id']; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-eye"></i> Details
                    </a>
                    
                    <div class="form-check ms-3">
                        <input class="form-check-input" type="checkbox" name="selected_apps[]" 
                               form="bulkForm" value="<?php echo $app['application_id']; ?>"
                               onchange="updateSelectedCount()">
                        <label class="form-check-label small text-muted">Select</label>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Recent Approved -->
        <?php if (!empty($recentApproved)): ?>
        <div class="card mt-4">
            <div class="card-header bg-white">
                <i class="fas fa-history me-2"></i> Recently Approved
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Approved</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentApproved as $app): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($app['full_name']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($app['student_code']); ?></small>
                                </td>
                                <td><?php echo formatCurrency($app['requested_amount']); ?></td>
                                <td>
                                    <small class="text-muted"><?php echo timeAgo($app['officer_approval_date']); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-times-circle text-danger"></i> Reject Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="application_id" id="reject_app_id">
                        
                        <p>You are about to reject <strong id="reject_student_name"></strong>'s application.</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection *</label>
                            <textarea class="form-control" name="reject_reason" rows="4" required
                                      placeholder="Please provide a detailed reason for rejection"></textarea>
                        </div>
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            This action cannot be undone. The student will be notified via email.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmApprove() {
            return confirm('Are you sure you want to approve this application?');
        }
        
        function showRejectModal(appId, studentName) {
            document.getElementById('reject_app_id').value = appId;
            document.getElementById('reject_student_name').textContent = studentName;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('input[name="selected_apps[]"]:checked');
            document.getElementById('selectedCount').textContent = checkboxes.length + ' selected';
        }
    </script>
</body>
</html>