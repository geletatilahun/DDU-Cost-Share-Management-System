<?php
// ============================================
// HOD - APPLICATION MANAGEMENT
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_HOD);

$deptId = $_SESSION['dept_id'] ?? 0;

if (!$deptId) {
    die('Department not assigned. Please contact administrator.');
}

$message = '';
$error = '';
$viewApplication = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // APPROVE APPLICATION (HOD Level)
        if ($action === 'approve') {
            $applicationId = (int)$_POST['application_id'];
            $comment = Security::sanitizeInput($_POST['comment'] ?? '');
            
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    UPDATE cost_share_applications 
                    SET status = 'hod_approved', 
                        hod_approved_by = ?,
                        hod_approval_date = NOW(),
                        hod_comment = ?
                    WHERE application_id = ? AND status = 'officer_approved'
                ");
                $stmt->execute([$_SESSION['user_id'], $comment, $applicationId]);
                
                if ($stmt->rowCount() > 0) {
                    // Get application details
                    $stmt = $pdo->prepare("
                        SELECT a.*, s.full_name, s.email, s.student_code 
                        FROM cost_share_applications a
                        JOIN students s ON a.student_id = s.student_id
                        WHERE a.application_id = ?
                    ");
                    $stmt->execute([$applicationId]);
                    $app = $stmt->fetch();
                    
                    $pdo->commit();
                    
                    $message = 'Application approved successfully!';
                    Security::logEvent($_SESSION['user_id'], 'HOD_APPROVE_APPLICATION', 
                                      "HOD approved application #{$applicationId} for {$app['full_name']}");
                    
                    // Send notification
                    if ($app) {
                        sendEmail($app['email'], 'Cost-Share Application Approved (Level 2)', "
                            <h3>Application Status Update</h3>
                            <p>Dear {$app['full_name']},</p>
                            <p>Your cost-share application has been <strong>approved</strong> by the Department Head.</p>
                            <p><strong>Amount:</strong> " . formatCurrency($app['requested_amount']) . "</p>
                            <p><strong>Status:</strong> Awaiting Finance Department processing</p>
                            <p>You will be notified once the funds are disbursed.</p>
                        ");
                    }
                } else {
                    $error = 'Application not found or not in the correct status.';
                }
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // REJECT APPLICATION (HOD Level)
        elseif ($action === 'reject') {
            $applicationId = (int)$_POST['application_id'];
            $reason = Security::sanitizeInput($_POST['reject_reason']);
            
            if (empty($reason)) {
                $error = 'Please provide a reason for rejection.';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("
                        UPDATE cost_share_applications 
                        SET status = 'rejected', 
                            hod_approved_by = ?,
                            hod_approval_date = NOW(),
                            hod_comment = ?
                        WHERE application_id = ? AND status = 'officer_approved'
                    ");
                    $stmt->execute([$_SESSION['user_id'], $reason, $applicationId]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Get application details
                        $stmt = $pdo->prepare("
                            SELECT a.*, s.full_name, s.email 
                            FROM cost_share_applications a
                            JOIN students s ON a.student_id = s.student_id
                            WHERE a.application_id = ?
                        ");
                        $stmt->execute([$applicationId]);
                        $app = $stmt->fetch();
                        
                        $pdo->commit();
                        
                        $message = 'Application rejected successfully!';
                        Security::logEvent($_SESSION['user_id'], 'HOD_REJECT_APPLICATION', 
                                          "HOD rejected application #{$applicationId}");
                        
                        // Send rejection email
                        if ($app) {
                            sendEmail($app['email'], 'Cost-Share Application Status Update', "
                                <h3>Application Status Update</h3>
                                <p>Dear {$app['full_name']},</p>
                                <p>Your cost-share application has been <strong style='color: red;'>rejected</strong> by the Department Head.</p>
                                <p><strong>Reason:</strong> {$reason}</p>
                                <p>If you have any questions, please contact the Department Head.</p>
                            ");
                        }
                    } else {
                        $error = 'Application not found or not in the correct status.';
                    }
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// View single application
if (isset($_GET['view'])) {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               s.full_name as student_name, 
               s.student_code,
               s.email as student_email,
               s.program,
               s.phone as student_phone,
               d.dept_name,
               u.full_name as officer_name,
               h.full_name as hod_name
        FROM cost_share_applications a
        JOIN students s ON a.student_id = s.student_id
        JOIN departments d ON s.department_id = d.dept_id
        LEFT JOIN users u ON a.officer_approved_by = u.user_id
        LEFT JOIN users h ON a.hod_approved_by = h.user_id
        WHERE a.application_id = ? AND s.department_id = ?
    ");
    $stmt->execute([$_GET['view'], $deptId]);
    $viewApplication = $stmt->fetch();
}

// Get applications - only department-specific
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'officer_approved';

$query = "
    SELECT a.*, 
           s.full_name as student_name, 
           s.student_code,
           s.program,
           d.dept_name
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    JOIN departments d ON s.department_id = d.dept_id
    WHERE s.department_id = ?
";

$params = [$deptId];

if ($statusFilter != 'all') {
    $query .= " AND a.status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get statistics
$stats = [];
$stats['total'] = count($applications);
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    WHERE s.department_id = ? AND a.status = 'officer_approved'
");
$stmt->execute([$deptId]);
$stats['pending'] = $stmt->fetch()['total'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    WHERE s.department_id = ? AND a.status IN ('hod_approved', 'approved')
");
$stmt->execute([$deptId]);
$stats['approved'] = $stmt->fetch()['total'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    WHERE s.department_id = ? AND a.status = 'rejected'
");
$stmt->execute([$deptId]);
$stats['rejected'] = $stmt->fetch()['total'];

// Get statuses for filter
$statuses = ['all', 'pending', 'officer_approved', 'hod_approved', 'approved', 'rejected', 'disbursed'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Applications - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
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
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 15px 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .application-detail {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }
        .application-detail .label {
            font-weight: 600;
            color: #7f8c8d;
            font-size: 13px;
            text-transform: uppercase;
        }
        .application-detail .value {
            font-size: 16px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/hod_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-file-alt"></i> Applications</h5>
            <div>
                <span class="badge bg-warning me-2">
                    <i class="fas fa-clock"></i> <?php echo $stats['pending']; ?> Pending
                </span>
                <a href="approve.php" class="btn btn-warning btn-sm">
                    <i class="fas fa-check-double"></i> Quick Approve
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
                    <div class="number text-primary"><?php echo $stats['total']; ?></div>
                    <div class="label">Total Applications</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number text-warning"><?php echo $stats['pending']; ?></div>
                    <div class="label">Pending Review</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number text-success"><?php echo $stats['approved']; ?></div>
                    <div class="label">Approved</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number text-danger"><?php echo $stats['rejected']; ?></div>
                    <div class="label">Rejected</div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status; ?>" 
                                <?php echo $statusFilter == $status ? 'selected' : ''; ?>>
                                <?php echo $status == 'all' ? 'All Status' : ucfirst(str_replace('_', ' ', $status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="applications.php" class="btn btn-secondary w-100 mt-2">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Applications Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover" id="applicationTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>#<?php echo $app['application_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($app['student_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($app['student_code']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($app['program']); ?></td>
                            <td><?php echo formatCurrency($app['requested_amount']); ?></td>
                            <td><?php echo formatDate($app['application_date']); ?></td>
                            <td>
                                <?php 
                                $statusClass = '';
                                if ($app['status'] == 'pending') $statusClass = 'warning';
                                elseif ($app['status'] == 'officer_approved') $statusClass = 'info';
                                elseif ($app['status'] == 'hod_approved') $statusClass = 'primary';
                                elseif ($app['status'] == 'approved') $statusClass = 'success';
                                elseif ($app['status'] == 'rejected') $statusClass = 'danger';
                                ?>
                                <span class="badge bg-<?php echo $statusClass; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?view=<?php echo $app['application_id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($app['status'] == 'officer_approved'): ?>
                                        <button class="btn btn-outline-success" onclick="openApproveModal(<?php echo $app['application_id']; ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="openRejectModal(<?php echo $app['application_id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- View Modal -->
    <?php if ($viewApplication): ?>
    <div class="modal fade show" id="viewModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-alt"></i> Application #<?php echo $viewApplication['application_id']; ?></h5>
                    <a href="?" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="application-detail">
                                <div class="label">Student Name</div>
                                <div class="value"><?php echo htmlspecialchars($viewApplication['student_name']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="application-detail">
                                <div class="label">Student ID</div>
                                <div class="value"><?php echo htmlspecialchars($viewApplication['student_code']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="application-detail">
                                <div class="label">Program</div>
                                <div class="value"><?php echo htmlspecialchars($viewApplication['program']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="application-detail">
                                <div class="label">Amount</div>
                                <div class="value text-primary">
                                    <strong><?php echo formatCurrency($viewApplication['requested_amount']); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="application-detail">
                                <div class="label">Reason / Justification</div>
                                <div class="value"><?php echo nl2br(htmlspecialchars($viewApplication['reason'] ?? 'N/A')); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="application-detail">
                                <div class="label">Officer Comment</div>
                                <div class="value"><?php echo htmlspecialchars($viewApplication['officer_comment'] ?? 'No comment'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="application-detail">
                                <div class="label">Current Status</div>
                                <div class="value">
                                    <span class="badge bg-<?php echo $viewApplication['status'] == 'officer_approved' ? 'info' : 'warning'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $viewApplication['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($viewApplication['status'] == 'officer_approved'): ?>
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> This application is pending your approval.
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success" onclick="openApproveModal(<?php echo $viewApplication['application_id']; ?>)">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-danger" onclick="openRejectModal(<?php echo $viewApplication['application_id']; ?>)">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            <a href="?" class="btn btn-secondary">Close</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle"></i> Approve Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="application_id" id="approve_app_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Comment (Optional)</label>
                            <textarea class="form-control" name="comment" rows="3" 
                                      placeholder="Add any comments about this approval"></textarea>
                        </div>
                        
                        <div class="alert alert-success">
                            <i class="fas fa-info-circle"></i> 
                            This will approve the application and forward it to the Finance Department.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="application_id" id="reject_app_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Reason *</label>
                            <textarea class="form-control" name="reject_reason" rows="4" required></textarea>
                        </div>
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            This action cannot be undone.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#applicationTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25
            });
        });
        
        function openApproveModal(appId) {
            document.getElementById('approve_app_id').value = appId;
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }
        
        function openRejectModal(appId) {
            document.getElementById('reject_app_id').value = appId;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
    </script>
</body>
</html>