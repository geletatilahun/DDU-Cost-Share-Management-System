<?php
// ============================================
// HOD - FORM REQUESTS MANAGEMENT
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_HOD);

$deptId = $_SESSION['dept_id'] ?? 0;
$message = '';
$error = '';

if (!$deptId) {
    die('Department not assigned. Please contact administrator.');
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // Approve Form Request
        if ($action === 'approve_request') {
            $requestId = (int)$_POST['request_id'];
            $comment = Security::sanitizeInput($_POST['comment'] ?? '');
            
            try {
                $pdo->beginTransaction();
                
                // Update request status
                $stmt = $pdo->prepare("
                    UPDATE cost_share_form_requests 
                    SET status = 'approved', hod_comment = ?, response_date = NOW()
                    WHERE request_id = ?
                ");
                $stmt->execute([$comment, $requestId]);
                
                // Get request details
                $stmt = $pdo->prepare("
                    SELECT r.*, s.full_name, s.email, s.student_code
                    FROM cost_share_form_requests r
                    JOIN students s ON r.student_id = s.student_id
                    WHERE r.request_id = ?
                ");
                $stmt->execute([$requestId]);
                $request = $stmt->fetch();
                
                // Create form for student
                $stmt = $pdo->prepare("
                    INSERT INTO cost_share_forms (
                        student_id, academic_year_id, semester, status
                    ) VALUES (
                        ?, 
                        (SELECT ac_year_id FROM academic_years WHERE is_current = 1 LIMIT 1),
                        1,
                        'pending'
                    )
                ");
                $stmt->execute([$request['student_id']]);
                
                $pdo->commit();
                
                $message = 'Form request approved successfully!';
                Security::logEvent($_SESSION['user_id'], 'APPROVE_FORM_REQUEST', 
                                  "Approved request #{$requestId}");
                
                // Send notification to student
                sendEmail($request['email'], 'Cost-Share Form Request Approved', "
                    <h3>Form Request Approved</h3>
                    <p>Dear {$request['full_name']},</p>
                    <p>Your cost-share form request has been <strong>approved</strong>.</p>
                    <p>Please login to the system and fill the form.</p>
                    <p><a href='" . SITE_URL . "modules/student/request_form.php'>Fill Form</a></p>
                    " . ($comment ? "<p><strong>HOD Comment:</strong> {$comment}</p>" : "") . "
                ");
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // Reject Form Request
        elseif ($action === 'reject_request') {
            $requestId = (int)$_POST['request_id'];
            $reason = Security::sanitizeInput($_POST['reason']);
            
            if (empty($reason)) {
                $error = 'Please provide a reason for rejection.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE cost_share_form_requests 
                        SET status = 'rejected', hod_comment = ?, response_date = NOW()
                        WHERE request_id = ?
                    ");
                    $stmt->execute([$reason, $requestId]);
                    
                    // Get request details
                    $stmt = $pdo->prepare("
                        SELECT r.*, s.full_name, s.email
                        FROM cost_share_form_requests r
                        JOIN students s ON r.student_id = s.student_id
                        WHERE r.request_id = ?
                    ");
                    $stmt->execute([$requestId]);
                    $request = $stmt->fetch();
                    
                    $message = 'Form request rejected.';
                    Security::logEvent($_SESSION['user_id'], 'REJECT_FORM_REQUEST', 
                                      "Rejected request #{$requestId}");
                    
                    // Send notification to student
                    sendEmail($request['email'], 'Cost-Share Form Request Rejected', "
                        <h3>Form Request Rejected</h3>
                        <p>Dear {$request['full_name']},</p>
                        <p>Your cost-share form request has been <strong style='color: red;'>rejected</strong>.</p>
                        <p><strong>Reason:</strong> {$reason}</p>
                        <p>If you have any questions, please contact the HOD.</p>
                    ");
                    
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        
        // Approve Submitted Form
        elseif ($action === 'approve_form') {
            $formId = (int)$_POST['form_id'];
            $comment = Security::sanitizeInput($_POST['comment'] ?? '');
            
            try {
                $pdo->beginTransaction();
                
                // Update form status
                $stmt = $pdo->prepare("
                    UPDATE cost_share_forms 
                    SET status = 'approved', 
                        hod_approved_by = ?,
                        hod_approval_date = NOW(),
                        hod_comment = ?
                    WHERE form_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $comment, $formId]);
                
                // Get form details
                $stmt = $pdo->prepare("
                    SELECT f.*, s.full_name, s.email, s.student_id, s.student_code
                    FROM cost_share_forms f
                    JOIN students s ON f.student_id = s.student_id
                    WHERE f.form_id = ?
                ");
                $stmt->execute([$formId]);
                $form = $stmt->fetch();
                
                // Create application record
                $data = json_decode($form['form_data'], true);
                $stmt = $pdo->prepare("
                    INSERT INTO cost_share_applications (
                        student_id, ac_year_id, semester, application_date,
                        requested_amount, reason, status, hod_approved_by, hod_approval_date
                    ) VALUES (?, ?, ?, CURDATE(), ?, ?, 'hod_approved', ?, NOW())
                ");
                $stmt->execute([
                    $form['student_id'],
                    $form['academic_year_id'],
                    $form['semester'],
                    $data['requested_amount'] ?? 0,
                    $data['reason'] ?? '',
                    $_SESSION['user_id']
                ]);
                
                $pdo->commit();
                
                $message = 'Form approved and application created successfully!';
                Security::logEvent($_SESSION['user_id'], 'APPROVE_FORM', "Approved form #{$formId}");
                
                // Send notification to student
                sendEmail($form['email'], 'Cost-Share Form Approved', "
                    <h3>Cost-Share Form Approved</h3>
                    <p>Dear {$form['full_name']},</p>
                    <p>Your cost-share form has been <strong>approved</strong>.</p>
                    <p><strong>Amount:</strong> " . formatCurrency($data['requested_amount'] ?? 0) . "</p>
                    <p>Your application has been forwarded to the Finance Department for processing.</p>
                    " . ($comment ? "<p><strong>HOD Comment:</strong> {$comment}</p>" : "") . "
                ");
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // Reject Submitted Form
        elseif ($action === 'reject_form') {
            $formId = (int)$_POST['form_id'];
            $reason = Security::sanitizeInput($_POST['reason']);
            
            if (empty($reason)) {
                $error = 'Please provide a reason for rejection.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE cost_share_forms 
                        SET status = 'rejected', 
                            hod_approved_by = ?,
                            hod_approval_date = NOW(),
                            hod_comment = ?
                        WHERE form_id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $reason, $formId]);
                    
                    // Get form details
                    $stmt = $pdo->prepare("
                        SELECT f.*, s.full_name, s.email
                        FROM cost_share_forms f
                        JOIN students s ON f.student_id = s.student_id
                        WHERE f.form_id = ?
                    ");
                    $stmt->execute([$formId]);
                    $form = $stmt->fetch();
                    
                    $message = 'Form rejected.';
                    Security::logEvent($_SESSION['user_id'], 'REJECT_FORM', "Rejected form #{$formId}");
                    
                    // Send notification to student
                    sendEmail($form['email'], 'Cost-Share Form Rejected', "
                        <h3>Cost-Share Form Rejected</h3>
                        <p>Dear {$form['full_name']},</p>
                        <p>Your cost-share form has been <strong style='color: red;'>rejected</strong>.</p>
                        <p><strong>Reason:</strong> {$reason}</p>
                        <p>If you have any questions, please contact the HOD.</p>
                    ");
                    
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get form requests
$requests = $pdo->prepare("
    SELECT r.*, s.full_name, s.student_code, s.program
    FROM cost_share_form_requests r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.department_id = ? AND r.status = 'pending'
    ORDER BY r.request_date ASC
");
$requests->execute([$deptId]);
$pendingRequests = $requests->fetchAll();

// Get submitted forms pending approval
$forms = $pdo->prepare("
    SELECT f.*, s.full_name, s.student_code, s.program
    FROM cost_share_forms f
    JOIN students s ON f.student_id = s.student_id
    WHERE s.department_id = ? AND f.status = 'pending'
    ORDER BY f.student_submitted_date ASC
");
$forms->execute([$deptId]);
$pendingForms = $forms->fetchAll();

// Get stats
$requestCount = count($pendingRequests);
$formCount = count($pendingForms);

// Get recent activity
$recentActivity = $pdo->prepare("
    SELECT 
        'request' as type,
        r.request_id as id,
        r.request_date as date,
        r.status,
        s.full_name,
        s.student_code
    FROM cost_share_form_requests r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.department_id = ?
    UNION
    SELECT 
        'form' as type,
        f.form_id as id,
        f.created_at as date,
        f.status,
        s.full_name,
        s.student_code
    FROM cost_share_forms f
    JOIN students s ON f.student_id = s.student_id
    WHERE s.department_id = ?
    ORDER BY date DESC
    LIMIT 10
");
$recentActivity->execute([$deptId, $deptId]);
$recentActivityList = $recentActivity->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Requests - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .request-card, .form-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #f39c12;
        }
        .request-card .student-name {
            font-weight: 700;
            font-size: 18px;
        }
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
    </style>
</head>
<body>
    <?php include '../../includes/hod_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-file-alt"></i> Form Requests</h5>
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
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="number text-warning"><?php echo $requestCount; ?></div>
                    <div class="label">Pending Form Requests</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="number text-primary"><?php echo $formCount; ?></div>
                    <div class="label">Pending Form Approvals</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="number text-success"><?php echo $requestCount + $formCount; ?></div>
                    <div class="label">Total Pending</div>
                </div>
            </div>
        </div>
        
        <!-- Step 1: Form Requests -->
        <h6 class="mt-4"><i class="fas fa-paper-plane text-primary me-2"></i> Step 1: Form Requests from Students</h6>
        <p class="text-muted">Students requesting cost-share forms. Approve to allow them to fill the form.</p>
        
        <?php if (empty($pendingRequests)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No pending form requests.
            </div>
        <?php else: ?>
            <?php foreach ($pendingRequests as $request): ?>
            <div class="request-card">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="student-name"><?php echo htmlspecialchars($request['full_name']); ?></div>
                        <div class="text-muted">
                            <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($request['student_code']); ?>
                            <span class="ms-3"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($request['program']); ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Requested:</small>
                        <br>
                        <?php echo timeAgo($request['request_date']); ?>
                    </div>
                    <div class="col-md-5 text-end">
                        <button class="btn btn-success" onclick="openApproveRequestModal(<?php echo $request['request_id']; ?>)">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn btn-danger" onclick="openRejectRequestModal(<?php echo $request['request_id']; ?>)">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Step 2: Submitted Forms -->
        <h6 class="mt-4"><i class="fas fa-file-alt text-warning me-2"></i> Step 2: Submitted Forms to Review</h6>
        <p class="text-muted">Students have filled the form. Review and approve to process.</p>
        
        <?php if (empty($pendingForms)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No submitted forms pending review.
            </div>
        <?php else: ?>
            <?php foreach ($pendingForms as $form): 
                $data = json_decode($form['form_data'], true);
            ?>
            <div class="form-card" style="border-left-color: #3498db;">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="student-name"><?php echo htmlspecialchars($form['full_name']); ?></div>
                        <div class="text-muted">
                            <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($form['student_code']); ?>
                            <span class="ms-3"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($form['program']); ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div><strong><?php echo formatCurrency($data['requested_amount'] ?? 0); ?></strong></div>
                        <small class="text-muted">Semester <?php echo $form['semester']; ?></small>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted">Submitted:</small>
                        <br>
                        <?php echo timeAgo($form['student_submitted_date']); ?>
                    </div>
                    <div class="col-md-3 text-end">
                        <button class="btn btn-success" onclick="openApproveFormModal(<?php echo $form['form_id']; ?>)">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn btn-danger" onclick="openRejectFormModal(<?php echo $form['form_id']; ?>)">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                </div>
                <?php if (!empty($data['reason'])): ?>
                    <div class="mt-2 p-2 bg-light rounded">
                        <small class="text-muted"><i class="fas fa-quote-left"></i> <?php echo htmlspecialchars($data['reason']); ?></small>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Recent Activity -->
        <?php if (!empty($recentActivityList)): ?>
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h6><i class="fas fa-clock me-2"></i> Recent Activity</h6>
            </div>
            <div class="card-body">
                <?php foreach ($recentActivityList as $activity): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <?php if ($activity['type'] == 'request'): ?>
                            <i class="fas fa-paper-plane text-primary"></i>
                            <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                            requested a form
                        <?php else: ?>
                            <i class="fas fa-file-alt text-warning"></i>
                            <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                            submitted a form
                        <?php endif; ?>
                        <br>
                        <small class="text-muted"><?php echo htmlspecialchars($activity['student_code']); ?></small>
                    </div>
                    <div>
                        <?php if ($activity['status'] == 'pending'): ?>
                            <span class="badge bg-warning">Pending</span>
                        <?php elseif ($activity['status'] == 'approved'): ?>
                            <span class="badge bg-success">Approved</span>
                        <?php elseif ($activity['status'] == 'rejected'): ?>
                            <span class="badge bg-danger">Rejected</span>
                        <?php endif; ?>
                        <br>
                        <small class="text-muted"><?php echo timeAgo($activity['date']); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Approve Request Modal -->
    <div class="modal fade" id="approveRequestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle"></i> Approve Form Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="approve_request">
                        <input type="hidden" name="request_id" id="approve_request_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Comment (Optional)</label>
                            <textarea class="form-control" name="comment" rows="3"></textarea>
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
    
    <!-- Reject Request Modal -->
    <div class="modal fade" id="rejectRequestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject Form Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="reject_request">
                        <input type="hidden" name="request_id" id="reject_request_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection *</label>
                            <textarea class="form-control" name="reason" rows="3" required></textarea>
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
    
    <!-- Approve Form Modal -->
    <div class="modal fade" id="approveFormModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle"></i> Approve Submitted Form</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="approve_form">
                        <input type="hidden" name="form_id" id="approve_form_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Comment (Optional)</label>
                            <textarea class="form-control" name="comment" rows="3"></textarea>
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
    
    <!-- Reject Form Modal -->
    <div class="modal fade" id="rejectFormModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject Form</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="reject_form">
                        <input type="hidden" name="form_id" id="reject_form_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection *</label>
                            <textarea class="form-control" name="reason" rows="3" required></textarea>
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
    <script>
        function openApproveRequestModal(requestId) {
            document.getElementById('approve_request_id').value = requestId;
            new bootstrap.Modal(document.getElementById('approveRequestModal')).show();
        }
        
        function openRejectRequestModal(requestId) {
            document.getElementById('reject_request_id').value = requestId;
            new bootstrap.Modal(document.getElementById('rejectRequestModal')).show();
        }
        
        function openApproveFormModal(formId) {
            document.getElementById('approve_form_id').value = formId;
            new bootstrap.Modal(document.getElementById('approveFormModal')).show();
        }
        
        function openRejectFormModal(formId) {
            document.getElementById('reject_form_id').value = formId;
            new bootstrap.Modal(document.getElementById('rejectFormModal')).show();
        }
    </script>
</body>
</html>