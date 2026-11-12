<?php
// ============================================
// STUDENT - DASHBOARD (UPDATED WITH WORKFLOW)
// ============================================

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

Auth::requireRole(ROLE_STUDENT);

$user = Auth::getCurrentUser();

// Debug: Check if user exists
if (!$user) {
    die('User not found. Please login again.');
}

// Get student ID
$studentId = null;

// Check if student_id exists in session
if (isset($_SESSION['student_id']) && $_SESSION['student_id']) {
    $studentId = $_SESSION['student_id'];
} else {
    // Try to find student by user_id
    try {
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $student = $stmt->fetch();
        if ($student) {
            $studentId = $student['student_id'];
            // Update session
            $_SESSION['student_id'] = $studentId;
            // Update users table
            $stmt = $pdo->prepare("UPDATE users SET student_id = ? WHERE user_id = ?");
            $stmt->execute([$studentId, $_SESSION['user_id']]);
        }
    } catch (PDOException $e) {
        die('Database error: ' . $e->getMessage());
    }
}

// If still no student ID, show error
if (!$studentId) {
    die('Student record not found. Please contact administrator.');
}

// Get student info
try {
    $stmt = $pdo->prepare("
        SELECT s.*, d.dept_name 
        FROM students s
        JOIN departments d ON s.department_id = d.dept_id
        WHERE s.student_id = ?
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

if (!$student) {
    die('Student record not found. Please contact administrator.');
}

// Get statistics with error handling
$stats = [
    'total_applications' => 0,
    'pending_applications' => 0,
    'approved_applications' => 0,
    'disbursed_applications' => 0,
    'total_amount' => 0,
    'form_requests' => 0,
    'pending_forms' => 0
];

try {
    // Total applications
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cost_share_applications WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $stats['total_applications'] = $stmt->fetch()['total'] ?? 0;

    // Pending applications
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cost_share_applications WHERE student_id = ? AND status = 'pending'");
    $stmt->execute([$studentId]);
    $stats['pending_applications'] = $stmt->fetch()['total'] ?? 0;

    // Approved applications
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cost_share_applications WHERE student_id = ? AND status IN ('approved', 'hod_approved', 'disbursed')");
    $stmt->execute([$studentId]);
    $stats['approved_applications'] = $stmt->fetch()['total'] ?? 0;

    // Disbursed applications
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cost_share_applications WHERE student_id = ? AND status = 'disbursed'");
    $stmt->execute([$studentId]);
    $stats['disbursed_applications'] = $stmt->fetch()['total'] ?? 0;

    // Total amount
    $stmt = $pdo->prepare("SELECT SUM(requested_amount) as total FROM cost_share_applications WHERE student_id = ? AND status IN ('approved', 'hod_approved', 'disbursed')");
    $stmt->execute([$studentId]);
    $stats['total_amount'] = $stmt->fetch()['total'] ?? 0;

    // Form requests
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cost_share_form_requests WHERE student_id = ? AND status = 'pending'");
    $stmt->execute([$studentId]);
    $stats['form_requests'] = $stmt->fetch()['total'] ?? 0;

    // Pending forms (waiting for student to fill)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cost_share_forms WHERE student_id = ? AND status = 'pending'");
    $stmt->execute([$studentId]);
    $stats['pending_forms'] = $stmt->fetch()['total'] ?? 0;

} catch (PDOException $e) {
    // Log error but continue
    error_log("Stats error: " . $e->getMessage());
}

// Recent applications
$recentApplications = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM cost_share_applications 
        WHERE student_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $recentApplications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Recent applications error: " . $e->getMessage());
}

// Get unread notifications (with error handling)
$unreadNotifications = [];
try {
    // Check if notifications table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetch();
    if ($tableCheck) {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadNotifications = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Notifications error: " . $e->getMessage());
}

// Get active notices
$notices = [];
try {
    $notices = $pdo->query("
        SELECT * FROM notices 
        WHERE is_active = 1 
        AND (target_audience = 'all' OR target_audience = 'students')
        AND (expiration_date IS NULL OR expiration_date >= CURDATE())
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Notices error: " . $e->getMessage());
}

// Get cost-share record
$costShareRecord = null;
try {
    $stmt = $pdo->prepare("
        SELECT * FROM cost_share_records 
        WHERE student_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $costShareRecord = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Cost-share record error: " . $e->getMessage());
}

// Get workflow status
$workflowStatus = [
    'form_requested' => false,
    'form_approved' => false,
    'form_submitted' => false,
    'form_approved_by_hod' => false
];

// Check if form has been requested
$stmt = $pdo->prepare("SELECT * FROM cost_share_form_requests WHERE student_id = ? ORDER BY request_id DESC LIMIT 1");
$stmt->execute([$studentId]);
$latestRequest = $stmt->fetch();

if ($latestRequest) {
    $workflowStatus['form_requested'] = true;
    if ($latestRequest['status'] == 'approved') {
        $workflowStatus['form_approved'] = true;
    }
}

// Check if form has been submitted
$stmt = $pdo->prepare("SELECT * FROM cost_share_forms WHERE student_id = ? ORDER BY form_id DESC LIMIT 1");
$stmt->execute([$studentId]);
$latestForm = $stmt->fetch();

if ($latestForm) {
    $workflowStatus['form_submitted'] = true;
    if ($latestForm['status'] == 'approved') {
        $workflowStatus['form_approved_by_hod'] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 4px solid #9b59b6;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        .stat-card .stat-icon {
            float: right;
            font-size: 35px;
            opacity: 0.3;
        }
        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #2C3E50;
        }
        .stat-card .stat-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        .stat-card.stat-primary { border-left-color: #3498db; }
        .stat-card.stat-success { border-left-color: #2ecc71; }
        .stat-card.stat-warning { border-left-color: #f39c12; }
        .stat-card.stat-danger { border-left-color: #e74c3c; }
        .stat-card.stat-info { border-left-color: #1abc9c; }
        .stat-card.stat-purple { border-left-color: #9b59b6; }
        
        .quick-action-btn {
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s;
            background: white;
            border: 2px solid #e9ecef;
            color: #2C3E50;
            text-decoration: none;
            display: block;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #9b59b6;
            color: #9b59b6;
        }
        .quick-action-btn i {
            font-size: 30px;
            display: block;
            margin-bottom: 10px;
        }
        .quick-action-btn .badge {
            margin-top: 8px;
        }
        .notice-item, .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f2f5;
            transition: all 0.3s;
        }
        .notice-item:hover, .notification-item:hover {
            background: #f8f9fa;
        }
        .notice-item:last-child, .notification-item:last-child {
            border-bottom: none;
        }
        .student-info-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .student-info-card .info-label {
            color: #7f8c8d;
            font-size: 12px;
            text-transform: uppercase;
        }
        .student-info-card .info-value {
            font-weight: 600;
            font-size: 16px;
        }
        .workflow-status {
            background: white;
            border-radius: 15px;
            padding: 15px 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .workflow-step {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-right: 10px;
        }
        .workflow-step.completed {
            background: #d4edda;
            color: #155724;
        }
        .workflow-step.active {
            background: #cce5ff;
            color: #004085;
        }
        .workflow-step.pending {
            background: #fff3cd;
            color: #856404;
        }
        .workflow-step .step-icon {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/student_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <div>
                <h5><i class="fas fa-chart-pie"></i> Student Dashboard</h5>
                <small>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></small>
            </div>
            <div>
                <span class="badge bg-success me-2"><i class="fas fa-circle"></i> System Online</span>
                <span class="badge bg-info"><?php echo date('l, F d, Y h:i A'); ?></span>
            </div>
        </div>
        
        <!-- Student Info -->
        <div class="student-info-card mb-4">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <img src="../../assets/images/avatar.png" alt="Avatar" class="rounded-circle me-3" 
                             style="width: 60px; height: 60px;" onerror="this.src='https://via.placeholder.com/60'">
                        <div>
                            <h5 class="mb-0"><?php echo htmlspecialchars($student['full_name']); ?></h5>
                            <small class="text-muted"><?php echo htmlspecialchars($student['student_code']); ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="info-label">Program</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['program']); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="info-label">Department</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['dept_name']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Workflow Status -->
        <div class="workflow-status">
            <h6><i class="fas fa-route me-2"></i> Application Workflow Status</h6>
            <div class="mt-2">
                <span class="workflow-step <?php echo $workflowStatus['form_requested'] ? 'completed' : 'pending'; ?>">
                    <i class="fas <?php echo $workflowStatus['form_requested'] ? 'fa-check-circle' : 'fa-clock'; ?> step-icon"></i>
                    Request Form
                </span>
                <span class="workflow-step <?php 
                    if ($workflowStatus['form_approved']) echo 'completed'; 
                    elseif ($workflowStatus['form_requested']) echo 'active'; 
                    else echo 'pending'; 
                ?>">
                    <i class="fas <?php 
                        if ($workflowStatus['form_approved']) echo 'fa-check-circle'; 
                        elseif ($workflowStatus['form_requested']) echo 'fa-spinner fa-spin'; 
                        else echo 'fa-clock'; 
                    ?> step-icon"></i>
                    HOD Approval
                </span>
                <span class="workflow-step <?php 
                    if ($workflowStatus['form_submitted']) echo 'completed'; 
                    elseif ($workflowStatus['form_approved']) echo 'active'; 
                    else echo 'pending'; 
                ?>">
                    <i class="fas <?php 
                        if ($workflowStatus['form_submitted']) echo 'fa-check-circle'; 
                        elseif ($workflowStatus['form_approved']) echo 'fa-pen'; 
                        else echo 'fa-clock'; 
                    ?> step-icon"></i>
                    Fill Form
                </span>
                <span class="workflow-step <?php 
                    if ($workflowStatus['form_approved_by_hod']) echo 'completed'; 
                    elseif ($workflowStatus['form_submitted']) echo 'active'; 
                    else echo 'pending'; 
                ?>">
                    <i class="fas <?php 
                        if ($workflowStatus['form_approved_by_hod']) echo 'fa-check-circle'; 
                        elseif ($workflowStatus['form_submitted']) echo 'fa-spinner fa-spin'; 
                        else echo 'fa-clock'; 
                    ?> step-icon"></i>
                    HOD Review
                </span>
                <span class="workflow-step <?php 
                    if ($stats['approved_applications'] > 0) echo 'completed'; 
                    elseif ($workflowStatus['form_approved_by_hod']) echo 'active'; 
                    else echo 'pending'; 
                ?>">
                    <i class="fas <?php 
                        if ($stats['approved_applications'] > 0) echo 'fa-check-circle'; 
                        elseif ($workflowStatus['form_approved_by_hod']) echo 'fa-spinner fa-spin'; 
                        else echo 'fa-clock'; 
                    ?> step-icon"></i>
                    Processing
                </span>
            </div>
            <?php if ($stats['form_requests'] > 0): ?>
                <div class="mt-2">
                    <span class="badge bg-warning">
                        <i class="fas fa-clock"></i> Waiting for HOD to approve your form request
                    </span>
                </div>
            <?php endif; ?>
            <?php if ($stats['pending_forms'] > 0): ?>
                <div class="mt-2">
                    <span class="badge bg-info">
                        <i class="fas fa-pen"></i> Form is ready for you to fill
                    </span>
                    <a href="request_form.php" class="btn btn-sm btn-primary ms-2">Fill Form Now</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-primary">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-warning">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-success">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['approved_applications']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-info">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-number"><?php echo formatCurrency($stats['total_amount']); ?></div>
                    <div class="stat-label">Total Approved Amount</div>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt me-2"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="request_form.php" class="quick-action-btn">
                                    <i class="fas fa-paper-plane text-primary"></i>
                                    <span class="label">Request Form</span>
                                    <?php if ($stats['form_requests'] > 0): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="status.php" class="quick-action-btn">
                                    <i class="fas fa-info-circle text-success"></i>
                                    <span class="label">Check Status</span>
                                    <?php if ($stats['pending_applications'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $stats['pending_applications']; ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="payment.php" class="quick-action-btn">
                                    <i class="fas fa-credit-card text-warning"></i>
                                    <span class="label">Make Payment</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="../feedback.php" class="quick-action-btn">
                                    <i class="fas fa-comment text-danger"></i>
                                    <span class="label">Send Feedback</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notifications -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-bell me-2"></i> Notifications
                            <?php if (count($unreadNotifications) > 0): ?>
                                <span class="badge bg-danger"><?php echo count($unreadNotifications); ?> New</span>
                            <?php endif; ?>
                        </div>
                        <a href="notifications.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($unreadNotifications)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-bell-slash fa-3x mb-3"></i>
                                <p>No new notifications</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($unreadNotifications as $notification): ?>
                            <div class="notification-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($notification['message']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo timeAgo($notification['created_at']); ?></small>
                                    </div>
                                    <span class="badge bg-primary">New</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Applications & Notices -->
        <div class="row g-4 mt-3">
            <!-- Recent Applications -->
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-history me-2"></i> Recent Applications
                        </div>
                        <a href="status.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($recentApplications)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No applications yet</p>
                                <a href="request_form.php" class="btn btn-primary btn-sm">Start Application</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentApplications as $app): ?>
                            <div class="notice-item">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <strong>Application #<?php echo $app['application_id']; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo formatDate($app['application_date']); ?></small>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo formatCurrency($app['requested_amount']); ?>
                                    </div>
                                    <div class="col-md-3 text-end">
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
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Notices -->
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-bullhorn me-2"></i> Recent Notices
                        </div>
                        <a href="../notices.php" class="btn btn-sm btn-info">View All</a>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($notices)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-bullhorn fa-3x mb-3"></i>
                                <p>No notices available</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notices as $notice): ?>
                            <div class="notice-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($notice['title']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo timeAgo($notice['created_at']); ?></small>
                                    </div>
                                    <span class="badge bg-<?php 
                                        echo $notice['notice_type'] == 'general' ? 'primary' : 
                                            ($notice['notice_type'] == 'deadline' ? 'warning' : 
                                            ($notice['notice_type'] == 'payment' ? 'success' : 
                                            ($notice['notice_type'] == 'disbursement' ? 'info' : 'danger'))); 
                                    ?>">
                                        <?php echo ucfirst($notice['notice_type']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>