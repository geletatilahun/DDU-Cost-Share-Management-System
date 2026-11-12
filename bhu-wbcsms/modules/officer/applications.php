<?php
// ============================================
// OFFICER - APPLICATION MANAGEMENT (FIXED)
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_OFFICER);

$message = '';
$error = '';
$viewApplication = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // APPROVE APPLICATION
        if ($action === 'approve') {
            $applicationId = (int)$_POST['application_id'];
            $comment = Security::sanitizeInput($_POST['comment'] ?? '');
            
            try {
                $pdo->beginTransaction();
                
                // Update application status
                $stmt = $pdo->prepare("
                    UPDATE cost_share_applications 
                    SET status = 'officer_approved', 
                        officer_approved_by = ?,
                        officer_approval_date = NOW(),
                        officer_comment = ?
                    WHERE application_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $comment, $applicationId]);
                
                // Get application details for notification
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
                Security::logEvent($_SESSION['user_id'], 'APPROVE_APPLICATION', 
                                  "Approved application #{$applicationId} for {$app['full_name']}");
                
                // Send notification email
                if ($app) {
                    sendEmail($app['email'], 'Cost-Share Application Approved (Level 1)', "
                        <h3>Application Status Update</h3>
                        <p>Dear {$app['full_name']},</p>
                        <p>Your cost-share application has been <strong>approved</strong> by the Cost-Share Officer.</p>
                        <p><strong>Amount:</strong> " . formatCurrency($app['requested_amount']) . "</p>
                        <p><strong>Status:</strong> Awaiting Department Head approval</p>
                        <p>You will be notified once the final decision is made.</p>
                    ");
                }
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // REJECT APPLICATION
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
                            officer_approved_by = ?,
                            officer_approval_date = NOW(),
                            officer_comment = ?
                        WHERE application_id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $reason, $applicationId]);
                    
                    // Get application details for notification
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
                    Security::logEvent($_SESSION['user_id'], 'REJECT_APPLICATION', 
                                      "Rejected application #{$applicationId}");
                    
                    // Send rejection email
                    if ($app) {
                        sendEmail($app['email'], 'Cost-Share Application Status Update', "
                            <h3>Application Status Update</h3>
                            <p>Dear {$app['full_name']},</p>
                            <p>Your cost-share application has been <strong style='color: red;'>rejected</strong>.</p>
                            <p><strong>Reason:</strong> {$reason}</p>
                            <p>If you have any questions, please contact the Cost-Share Officer.</p>
                        ");
                    }
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// View single application - FIXED (removed finance_approved_by)
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
        WHERE a.application_id = ?
    ");
    $stmt->execute([$_GET['view']]);
    $viewApplication = $stmt->fetch();
}

// Get applications with filters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query with proper parameter binding
$query = "
    SELECT a.*, 
           s.full_name as student_name, 
           s.student_code,
           d.dept_name
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    JOIN departments d ON s.department_id = d.dept_id
    WHERE 1=1
";

$params = [];

if ($statusFilter != 'all') {
    $query .= " AND a.status = ?";
    $params[] = $statusFilter;
}

if ($dateFilter) {
    $query .= " AND DATE(a.application_date) = ?";
    $params[] = $dateFilter;
}

$query .= " ORDER BY a.created_at DESC";

// Execute with parameter binding
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get statistics
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM cost_share_applications")->fetchColumn();
$stats['pending'] = $pdo->query("SELECT COUNT(*) FROM cost_share_applications WHERE status = 'pending'")->fetchColumn();
$stats['officer_approved'] = $pdo->query("SELECT COUNT(*) FROM cost_share_applications WHERE status = 'officer_approved'")->fetchColumn();
$stats['approved'] = $pdo->query("SELECT COUNT(*) FROM cost_share_applications WHERE status = 'approved'")->fetchColumn();
$stats['rejected'] = $pdo->query("SELECT COUNT(*) FROM cost_share_applications WHERE status = 'rejected'")->fetchColumn();

// Get status counts for filter
$statuses = $pdo->query("SELECT DISTINCT status FROM cost_share_applications ORDER BY status")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Management - <?php echo SITE_NAME; ?></title>
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
            transition: all 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-card .number {
            font-size: 28px;
            font-weight: 700;
        }
        .stats-card .label {
            color: #7f8c8d;
            font-size: 14px;
        }
        .stats-card .icon {
            float: right;
            font-size: 30px;
            opacity: 0.2;
        }
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
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
        .status-timeline {
            position: relative;
            padding-left: 30px;
        }
        .status-timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .status-item {
            position: relative;
            margin-bottom: 20px;
        }
        .status-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e9ecef;
            border: 2px solid #dee2e6;
        }
        .status-item.active::before {
            background: #2ecc71;
            border-color: #2ecc71;
        }
        .status-item.completed::before {
            background: #3498db;
            border-color: #3498db;
        }
        .status-item.rejected::before {
            background: #e74c3c;
            border-color: #e74c3c;
        }
        .status-item .date {
            font-size: 12px;
            color: #95a5a6;
        }
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 15px 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/officer_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-file-alt"></i> Application Management</h5>
            <div>
                <span class="badge bg-warning me-2">
                    <i class="fas fa-clock"></i> <?php echo $stats['pending']; ?> Pending
                </span>
                <a href="approve.php" class="btn btn-success btn-sm">
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
                    <div class="icon"><i class="fas fa-file-alt"></i></div>
                    <div class="number text-primary"><?php echo $stats['total']; ?></div>
                    <div class="label">Total Applications</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-clock"></i></div>
                    <div class="number text-warning"><?php echo $stats['pending']; ?></div>
                    <div class="label">Pending Review</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <div class="number text-info"><?php echo $stats['officer_approved']; ?></div>
                    <div class="label">Level 1 Approved</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-times-circle"></i></div>
                    <div class="number text-danger"><?php echo $stats['rejected']; ?></div>
                    <div class="label">Rejected</div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all">All Status</option>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?php echo $s['status']; ?>" 
                                <?php echo $statusFilter == $s['status'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $s['status'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo $dateFilter; ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
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
                            <th>Department</th>
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
                            <td><?php echo htmlspecialchars($app['dept_name']); ?></td>
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
                                elseif ($app['status'] == 'disbursed') $statusClass = 'dark';
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
                                    <?php if ($app['status'] == 'pending'): ?>
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
    
    <!-- View Application Modal -->
    <?php if ($viewApplication): ?>
    <div class="modal fade show" id="viewApplicationModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-alt"></i> Application #<?php echo $viewApplication['application_id']; ?></h5>
                    <a href="?" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <!-- Student Information -->
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
                                <div class="label">Department</div>
                                <div class="value"><?php echo htmlspecialchars($viewApplication['dept_name']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="application-detail">
                                <div class="label">Requested Amount</div>
                                <div class="value text-primary">
                                    <strong><?php echo formatCurrency($viewApplication['requested_amount']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="application-detail">
                                <div class="label">Status</div>
                                <div class="value">
                                    <?php 
                                    $statusClass = '';
                                    if ($viewApplication['status'] == 'pending') $statusClass = 'warning';
                                    elseif ($viewApplication['status'] == 'officer_approved') $statusClass = 'info';
                                    elseif ($viewApplication['status'] == 'hod_approved') $statusClass = 'primary';
                                    elseif ($viewApplication['status'] == 'approved') $statusClass = 'success';
                                    elseif ($viewApplication['status'] == 'rejected') $statusClass = 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $viewApplication['status'])); ?>
                                    </span>
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
                    
                    <?php if ($viewApplication['supporting_documents']): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="application-detail">
                                <div class="label">Supporting Document</div>
                                <div class="value">
                                    <a href="../../uploads/documents/<?php echo $viewApplication['supporting_documents']; ?>" target="_blank">
                                        <i class="fas fa-file-pdf"></i> View Document
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Approval Timeline -->
                    <h6 class="mt-4"><i class="fas fa-clock"></i> Approval Timeline</h6>
                    <div class="status-timeline">
                        <div class="status-item <?php echo $viewApplication['status'] != 'rejected' ? 'completed' : ''; ?>">
                            <strong>Application Submitted</strong>
                            <div class="date"><?php echo formatDateTime($viewApplication['created_at'] ?? $viewApplication['application_date']); ?></div>
                        </div>
                        
                        <div class="status-item <?php echo $viewApplication['officer_approved_by'] ? 'completed' : ''; ?>">
                            <strong>Level 1 - Officer Approval</strong>
                            <?php if ($viewApplication['officer_approved_by']): ?>
                                <div class="date">
                                    Approved by: <?php echo htmlspecialchars($viewApplication['officer_name'] ?? 'Officer'); ?>
                                    <br>
                                    <?php echo formatDateTime($viewApplication['officer_approval_date']); ?>
                                    <?php if ($viewApplication['officer_comment']): ?>
                                        <br>
                                        <em>"<?php echo htmlspecialchars($viewApplication['officer_comment']); ?>"</em>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="date text-muted">Pending</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="status-item <?php echo $viewApplication['hod_approved_by'] ? 'completed' : ''; ?>">
                            <strong>Level 2 - HOD Approval</strong>
                            <?php if ($viewApplication['hod_approved_by']): ?>
                                <div class="date">
                                    Approved by: <?php echo htmlspecialchars($viewApplication['hod_name'] ?? 'HOD'); ?>
                                    <br>
                                    <?php echo formatDateTime($viewApplication['hod_approval_date']); ?>
                                    <?php if ($viewApplication['hod_comment']): ?>
                                        <br>
                                        <em>"<?php echo htmlspecialchars($viewApplication['hod_comment']); ?>"</em>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="date text-muted">Pending</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="status-item <?php echo $viewApplication['final_approval_date'] ? 'completed' : ''; ?>">
                            <strong>Final Approval</strong>
                            <?php if ($viewApplication['final_approval_date']): ?>
                                <div class="date">
                                    <?php echo formatDateTime($viewApplication['final_approval_date']); ?>
                                </div>
                            <?php else: ?>
                                <div class="date text-muted">Pending</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <?php if ($viewApplication['status'] == 'pending'): ?>
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> This application is pending your review.
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
                            This will approve the application and forward it to the Department Head for final approval.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve Application</button>
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
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#applicationTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search applications:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ applications"
                }
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