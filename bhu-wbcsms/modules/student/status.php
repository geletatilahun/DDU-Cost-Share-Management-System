<?php
// ============================================
// STUDENT - APPLICATION STATUS (UPDATED)
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_STUDENT);

$user = Auth::getCurrentUser();
$studentId = $user['student_id'] ?? null;

if (!$studentId) {
    die('Student record not found. Please contact administrator.');
}

// Get all applications
$stmt = $pdo->prepare("
    SELECT a.*, y.year_name
    FROM cost_share_applications a
    JOIN academic_years y ON a.ac_year_id = y.ac_year_id
    WHERE a.student_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$studentId]);
$applications = $stmt->fetchAll();

// Get form requests
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name as hod_name
    FROM cost_share_form_requests r
    LEFT JOIN users u ON r.hod_id = u.user_id
    WHERE r.student_id = ?
    ORDER BY r.request_id DESC
");
$stmt->execute([$studentId]);
$formRequests = $stmt->fetchAll();

// Get forms
$stmt = $pdo->prepare("
    SELECT f.*, u.full_name as hod_name
    FROM cost_share_forms f
    LEFT JOIN users u ON f.hod_approved_by = u.user_id
    WHERE f.student_id = ?
    ORDER BY f.form_id DESC
");
$stmt->execute([$studentId]);
$forms = $stmt->fetchAll();

// Get cost-share record
$stmt = $pdo->prepare("
    SELECT * FROM cost_share_records 
    WHERE student_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$studentId]);
$costShareRecords = $stmt->fetchAll();

// Get payments
$stmt = $pdo->prepare("
    SELECT * FROM payments 
    WHERE student_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$studentId]);
$payments = $stmt->fetchAll();

// Calculate totals
$totalRequested = array_sum(array_column($applications, 'requested_amount'));
$totalApproved = array_sum(array_filter($applications, function($a) { 
    return in_array($a['status'], ['approved', 'hod_approved', 'disbursed']); 
}));
$totalPaid = array_sum(array_column($payments, 'amount'));
$totalDisbursed = array_sum(array_column($applications, 'requested_amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .status-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #9b59b6;
        }
        .status-card .status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .status-card .status-amount {
            font-size: 24px;
            font-weight: 700;
            color: #2C3E50;
        }
        .status-timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 15px;
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
        .status-step {
            position: relative;
            margin-bottom: 20px;
        }
        .status-step::before {
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
        .status-step.completed::before {
            background: #2ecc71;
            border-color: #2ecc71;
        }
        .status-step.active::before {
            background: #f39c12;
            border-color: #f39c12;
        }
        .status-step.rejected::before {
            background: #e74c3c;
            border-color: #e74c3c;
        }
        .status-step .step-date {
            font-size: 12px;
            color: #95a5a6;
        }
        .summary-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .summary-box .label {
            color: #7f8c8d;
            font-size: 12px;
            text-transform: uppercase;
        }
        .summary-box .value {
            font-size: 18px;
            font-weight: 700;
        }
        .status-tabs .nav-link {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }
        .status-tabs .nav-link.active {
            background: #9b59b6;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../../includes/student_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-info-circle"></i> Application Status</h5>
        </div>
        
        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="summary-box">
                    <div class="label">Total Applications</div>
                    <div class="value text-primary"><?php echo count($applications); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-box">
                    <div class="label">Total Requested</div>
                    <div class="value text-warning"><?php echo formatCurrency($totalRequested); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-box">
                    <div class="label">Total Approved</div>
                    <div class="value text-success"><?php echo formatCurrency($totalApproved); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-box">
                    <div class="label">Total Paid</div>
                    <div class="value text-info"><?php echo formatCurrency($totalPaid); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Status Tabs -->
        <ul class="nav nav-tabs status-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#applications">
                    <i class="fas fa-file-alt"></i> Applications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#form_requests">
                    <i class="fas fa-paper-plane"></i> Form Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#forms">
                    <i class="fas fa-file-signature"></i> Forms
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#payments">
                    <i class="fas fa-credit-card"></i> Payments
                </a>
            </li>
        </ul>
        
        <div class="tab-content">
            <!-- Applications Tab -->
            <div class="tab-pane fade show active" id="applications">
                <?php if (empty($applications)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h5>No Applications Found</h5>
                            <p class="text-muted">You haven't submitted any cost-share applications yet.</p>
                            <a href="request_form.php" class="btn btn-primary">Start Application</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                    <div class="status-card">
                        <div class="status-header">
                            <div>
                                <h5>Application #<?php echo $app['application_id']; ?></h5>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($app['year_name']); ?> - Semester <?php echo $app['semester']; ?>
                                    <br>
                                    Submitted: <?php echo formatDateTime($app['created_at']); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <div class="status-amount"><?php echo formatCurrency($app['requested_amount']); ?></div>
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
                            </div>
                        </div>
                        
                        <?php if ($app['reason']): ?>
                        <div class="mt-2 p-2 bg-light rounded">
                            <small class="text-muted"><i class="fas fa-quote-left"></i> <?php echo nl2br(htmlspecialchars($app['reason'])); ?></small>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Status Timeline -->
                        <div class="status-timeline">
                            <!-- Step 1: Submitted -->
                            <div class="status-step completed">
                                <strong>Application Submitted</strong>
                                <div class="step-date"><?php echo formatDateTime($app['application_date']); ?></div>
                            </div>
                            
                            <!-- Step 2: Officer Approval -->
                            <div class="status-step <?php 
                                if ($app['status'] == 'rejected' || $app['status'] == 'pending') echo '';
                                elseif ($app['officer_approved_by']) echo 'completed';
                                else echo 'active';
                            ?>">
                                <strong>Level 1 - Officer Review</strong>
                                <?php if ($app['officer_approved_by']): ?>
                                    <div class="step-date">
                                        <?php echo formatDateTime($app['officer_approval_date']); ?>
                                        <?php if ($app['officer_comment']): ?>
                                            <br><em>"<?php echo htmlspecialchars($app['officer_comment']); ?>"</em>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($app['status'] == 'pending'): ?>
                                    <div class="step-date text-muted">Pending Review</div>
                                <?php elseif ($app['status'] == 'rejected'): ?>
                                    <div class="step-date text-danger">Rejected</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Step 3: HOD Approval -->
                            <?php if ($app['status'] != 'rejected' && $app['status'] != 'pending'): ?>
                            <div class="status-step <?php 
                                if ($app['status'] == 'rejected') echo 'rejected';
                                elseif ($app['hod_approved_by']) echo 'completed';
                                else echo 'active';
                            ?>">
                                <strong>Level 2 - HOD Approval</strong>
                                <?php if ($app['hod_approved_by']): ?>
                                    <div class="step-date">
                                        <?php echo formatDateTime($app['hod_approval_date']); ?>
                                        <?php if ($app['hod_comment']): ?>
                                            <br><em>"<?php echo htmlspecialchars($app['hod_comment']); ?>"</em>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($app['status'] == 'officer_approved'): ?>
                                    <div class="step-date text-muted">Pending HOD Review</div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Step 4: Final Approval & Disbursement -->
                            <?php if ($app['status'] == 'approved' || $app['status'] == 'disbursed'): ?>
                            <div class="status-step completed">
                                <strong>Final Approval</strong>
                                <div class="step-date"><?php echo formatDateTime($app['final_approval_date']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($app['status'] == 'disbursed'): ?>
                            <div class="status-step completed" style="color: #2ecc71;">
                                <strong><i class="fas fa-check-circle"></i> Funds Disbursed</strong>
                                <div class="step-date"><?php echo formatDateTime($app['final_approval_date']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($app['status'] == 'rejected'): ?>
                            <div class="status-step rejected">
                                <strong class="text-danger">Application Rejected</strong>
                                <?php if ($app['officer_comment'] || $app['hod_comment']): ?>
                                    <div class="step-date text-danger">
                                        Reason: <?php echo htmlspecialchars($app['officer_comment'] ?? $app['hod_comment']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Form Requests Tab -->
            <div class="tab-pane fade" id="form_requests">
                <?php if (empty($formRequests)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h5>No Form Requests</h5>
                            <p class="text-muted">You haven't requested any forms yet.</p>
                            <a href="request_form.php" class="btn btn-primary">Request Form</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($formRequests as $request): ?>
                    <div class="status-card" style="border-left-color: #3498db;">
                        <div class="status-header">
                            <div>
                                <h5>Form Request #<?php echo $request['request_id']; ?></h5>
                                <small class="text-muted">
                                    <?php echo formatDateTime($request['request_date']); ?>
                                    <br>
                                    HOD: <?php echo htmlspecialchars($request['hod_name'] ?? 'N/A'); ?>
                                </small>
                            </div>
                            <div>
                                <?php if ($request['status'] == 'pending'): ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php elseif ($request['status'] == 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php elseif ($request['status'] == 'rejected'): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                                <?php if ($request['response_date']): ?>
                                    <br>
                                    <small class="text-muted"><?php echo formatDateTime($request['response_date']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($request['hod_comment']): ?>
                            <div class="mt-2 p-2 bg-light rounded">
                                <small class="text-muted"><i class="fas fa-quote-left"></i> <?php echo htmlspecialchars($request['hod_comment']); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Forms Tab -->
            <div class="tab-pane fade" id="forms">
                <?php if (empty($forms)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h5>No Forms Found</h5>
                            <p class="text-muted">You haven't submitted any forms yet.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($forms as $form): 
                        $data = json_decode($form['form_data'], true);
                    ?>
                    <div class="status-card" style="border-left-color: #e67e22;">
                        <div class="status-header">
                            <div>
                                <h5>Form #<?php echo $form['form_id']; ?></h5>
                                <small class="text-muted">
                                    Semester <?php echo $form['semester']; ?>
                                    <br>
                                    <?php if ($form['student_submitted_date']): ?>
                                        Submitted: <?php echo formatDateTime($form['student_submitted_date']); ?>
                                    <?php else: ?>
                                        Status: Pending
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <?php if ($data && isset($data['requested_amount'])): ?>
                                    <div class="status-amount"><?php echo formatCurrency($data['requested_amount']); ?></div>
                                <?php endif; ?>
                                <?php if ($form['status'] == 'pending'): ?>
                                    <span class="badge bg-warning">Pending HOD Review</span>
                                <?php elseif ($form['status'] == 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php elseif ($form['status'] == 'rejected'): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($data && isset($data['reason']) && $data['reason']): ?>
                            <div class="mt-2 p-2 bg-light rounded">
                                <small class="text-muted"><i class="fas fa-quote-left"></i> <?php echo htmlspecialchars($data['reason']); ?></small>
                            </div>
                        <?php endif; ?>
                        <?php if ($form['hod_comment']): ?>
                            <div class="mt-2 p-2 bg-light rounded">
                                <small class="text-muted"><i class="fas fa-comment"></i> HOD: <?php echo htmlspecialchars($form['hod_comment']); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Payments Tab -->
            <div class="tab-pane fade" id="payments">
                <?php if (empty($payments)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h5>No Payments Found</h5>
                            <p class="text-muted">You haven't made any payments yet.</p>
                            <a href="payment.php" class="btn btn-primary">Make Payment</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                    <div class="status-card" style="border-left-color: #2ecc71;">
                        <div class="status-header">
                            <div>
                                <h5>Payment #<?php echo $payment['payment_id']; ?></h5>
                                <small class="text-muted">
                                    <?php echo formatDate($payment['payment_date']); ?>
                                    <br>
                                    Method: <?php echo ucfirst($payment['payment_method']); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <div class="status-amount"><?php echo formatCurrency($payment['amount']); ?></div>
                                <?php if ($payment['status'] == 'pending'): ?>
                                    <span class="badge bg-warning">Pending Verification</span>
                                <?php elseif ($payment['status'] == 'verified'): ?>
                                    <span class="badge bg-success">Verified</span>
                                <?php elseif ($payment['status'] == 'rejected'): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                                <?php if ($payment['verification_date']): ?>
                                    <br>
                                    <small class="text-muted"><?php echo formatDateTime($payment['verification_date']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($payment['transaction_id']): ?>
                            <div class="mt-2">
                                <small class="text-muted">Transaction ID: <?php echo htmlspecialchars($payment['transaction_id']); ?></small>
                            </div>
                        <?php endif; ?>
                        <?php if ($payment['verification_comment']): ?>
                            <div class="mt-2 p-2 bg-light rounded">
                                <small class="text-muted"><i class="fas fa-comment"></i> <?php echo htmlspecialchars($payment['verification_comment']); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>