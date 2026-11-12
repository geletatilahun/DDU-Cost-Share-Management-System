<?php
// ============================================
// HOD - DASHBOARD (FIXED - NO JSON FUNCTIONS)
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_HOD);

$user = Auth::getCurrentUser();
$deptId = $_SESSION['dept_id'] ?? 0;

if (!$deptId) {
    die('Department not assigned to this user. Please contact administrator.');
}

// Get department info
$stmt = $pdo->prepare("SELECT * FROM departments WHERE dept_id = ?");
$stmt->execute([$deptId]);
$department = $stmt->fetch();

if (!$department) {
    die('Department not found.');
}

// Statistics
$stats = [];

// Total students in department
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM students WHERE department_id = ? AND enrollment_status = 'active'");
$stmt->execute([$deptId]);
$stats['total_students'] = $stmt->fetch()['total'];

// ============ FORM REQUESTS ============
// Pending form requests from students
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM cost_share_form_requests r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.department_id = ? AND r.status = 'pending'
");
$stmt->execute([$deptId]);
$stats['pending_form_requests'] = $stmt->fetch()['total'];

// Total form requests
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM cost_share_form_requests r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.department_id = ?
");
$stmt->execute([$deptId]);
$stats['total_form_requests'] = $stmt->fetch()['total'];

// ============ SUBMITTED FORMS ============
// Pending forms (submitted by students, waiting for HOD approval)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM cost_share_forms f
    JOIN students s ON f.student_id = s.student_id
    WHERE s.department_id = ? AND f.status = 'pending'
");
$stmt->execute([$deptId]);
$stats['pending_forms'] = $stmt->fetch()['total'];

// Total forms
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM cost_share_forms f
    JOIN students s ON f.student_id = s.student_id
    WHERE s.department_id = ?
");
$stmt->execute([$deptId]);
$stats['total_forms'] = $stmt->fetch()['total'];

// Approved forms
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM cost_share_forms f
    JOIN students s ON f.student_id = s.student_id
    WHERE s.department_id = ? AND f.status = 'approved'
");
$stmt->execute([$deptId]);
$stats['approved_forms'] = $stmt->fetch()['total'];

// ============ APPLICATIONS ============
// Pending applications (awaiting HOD approval)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    WHERE s.department_id = ? AND a.status = 'officer_approved'
");
$stmt->execute([$deptId]);
$stats['pending_applications'] = $stmt->fetch()['total'];

// Total applications
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    WHERE s.department_id = ?
");
$stmt->execute([$deptId]);
$stats['total_applications'] = $stmt->fetch()['total'];

// Approved applications
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    WHERE s.department_id = ? AND a.status IN ('hod_approved', 'approved', 'disbursed')
");
$stmt->execute([$deptId]);
$stats['approved_applications'] = $stmt->fetch()['total'];

// Total cost-share amount
$stmt = $pdo->prepare("
    SELECT SUM(a.requested_amount) as total
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    WHERE s.department_id = ? AND a.status IN ('hod_approved', 'approved')
");
$stmt->execute([$deptId]);
$stats['total_cost_share'] = $stmt->fetch()['total'] ?? 0;

// Department budget
$stats['budget'] = $department['budget_allocation'] ?? 0;

// Budget usage percentage
$budgetUsage = $stats['budget'] > 0 ? ($stats['total_cost_share'] / $stats['budget']) * 100 : 0;

// ============ RECENT ACTIVITY (FIXED - NO JSON) ============
// Get recent form requests
$formRequests = $pdo->prepare("
    SELECT 
        'form_request' as type,
        r.request_id as id,
        r.request_date as date,
        r.status,
        s.full_name,
        s.student_code,
        s.program,
        NULL as amount
    FROM cost_share_form_requests r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.department_id = ?
    ORDER BY r.request_date DESC
    LIMIT 5
");
$formRequests->execute([$deptId]);
$formRequestsData = $formRequests->fetchAll();

// Get recent forms
$forms = $pdo->prepare("
    SELECT 
        'form_submitted' as type,
        f.form_id as id,
        f.created_at as date,
        f.status,
        s.full_name,
        s.student_code,
        s.program,
        f.form_data as amount_data
    FROM cost_share_forms f
    JOIN students s ON f.student_id = s.student_id
    WHERE s.department_id = ?
    ORDER BY f.created_at DESC
    LIMIT 5
");
$forms->execute([$deptId]);
$formsData = $forms->fetchAll();

// Process form data to extract amount
foreach ($formsData as &$form) {
    $data = json_decode($form['amount_data'], true);
    $form['amount'] = $data['requested_amount'] ?? null;
    unset($form['amount_data']);
}

// Get recent applications
$applications = $pdo->prepare("
    SELECT 
        'application' as type,
        a.application_id as id,
        a.created_at as date,
        a.status,
        s.full_name,
        s.student_code,
        s.program,
        a.requested_amount as amount
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    WHERE s.department_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
");
$applications->execute([$deptId]);
$applicationsData = $applications->fetchAll();

// Combine and sort all activity
$recentActivity = array_merge($formRequestsData, $formsData, $applicationsData);

// Sort by date
usort($recentActivity, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Limit to 15 items
$recentActivity = array_slice($recentActivity, 0, 15);

// Program distribution
$stmt = $pdo->prepare("
    SELECT s.program, COUNT(*) as count
    FROM students s
    WHERE s.department_id = ? AND s.enrollment_status = 'active'
    GROUP BY s.program
    ORDER BY count DESC
");
$stmt->execute([$deptId]);
$programStats = $stmt->fetchAll();

// Monthly applications
$stmt = $pdo->prepare("
    SELECT 
        MONTHNAME(a.created_at) as month,
        COUNT(*) as count
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    WHERE s.department_id = ? AND a.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY MONTH(a.created_at)
    ORDER BY MONTH(a.created_at)
");
$stmt->execute([$deptId]);
$monthlyData = $stmt->fetchAll();

$chartLabels = [];
$chartData = [];
foreach ($monthlyData as $row) {
    $chartLabels[] = $row['month'];
    $chartData[] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 4px solid #f39c12;
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
        .stat-card .stat-sub {
            font-size: 12px;
            color: #95a5a6;
        }
        .stat-card.stat-primary { border-left-color: #3498db; }
        .stat-card.stat-success { border-left-color: #2ecc71; }
        .stat-card.stat-warning { border-left-color: #f39c12; }
        .stat-card.stat-danger { border-left-color: #e74c3c; }
        .stat-card.stat-info { border-left-color: #1abc9c; }
        .stat-card.stat-purple { border-left-color: #9b59b6; }
        .stat-card.stat-teal { border-left-color: #20c997; }
        
        .activity-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f2f5;
            transition: all 0.3s;
        }
        .activity-item:hover {
            background: #f8f9fa;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
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
            border-color: #f39c12;
            color: #f39c12;
        }
        .quick-action-btn i {
            font-size: 30px;
            display: block;
            margin-bottom: 10px;
        }
        .quick-action-btn .badge {
            margin-top: 5px;
        }
        .budget-bar {
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
        }
        .budget-bar .progress-bar {
            transition: width 1s ease;
        }
        .dept-info {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .dept-info .dept-name {
            font-size: 20px;
            font-weight: 700;
        }
        .dept-info .dept-code {
            color: #7f8c8d;
        }
        .activity-type-badge {
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 12px;
        }
        .activity-type-badge.form_request { background: #cce5ff; color: #004085; }
        .activity-type-badge.form_submitted { background: #fff3cd; color: #856404; }
        .activity-type-badge.application { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <?php include '../../includes/hod_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <div>
                <h5><i class="fas fa-chart-pie"></i> HOD Dashboard</h5>
                <small>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></small>
            </div>
            <div>
                <span class="badge bg-success me-2"><i class="fas fa-circle"></i> System Online</span>
                <span class="badge bg-info"><?php echo date('l, F d, Y h:i A'); ?></span>
            </div>
        </div>
        
        <!-- Department Info -->
        <div class="dept-info mb-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="dept-name">
                        <i class="fas fa-building text-primary"></i> 
                        <?php echo htmlspecialchars($department['dept_name']); ?>
                    </div>
                    <div class="dept-code">
                        Code: <?php echo htmlspecialchars($department['dept_code']); ?>
                        | Faculty: <?php echo htmlspecialchars($department['faculty'] ?? 'N/A'); ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="h5 mb-0 text-primary"><?php echo $stats['total_students']; ?></div>
                            <small class="text-muted">Students</small>
                        </div>
                        <div class="col-4">
                            <div class="h5 mb-0 text-warning"><?php echo $stats['pending_form_requests'] + $stats['pending_forms']; ?></div>
                            <small class="text-muted">Pending</small>
                        </div>
                        <div class="col-4">
                            <div class="h5 mb-0 text-success"><?php echo formatCurrency($stats['total_cost_share']); ?></div>
                            <small class="text-muted">Total Approved</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Row 1 - Form Requests & Forms -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-primary">
                    <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
                    <div class="stat-number"><?php echo $stats['total_form_requests']; ?></div>
                    <div class="stat-label">Total Form Requests</div>
                    <?php if ($stats['pending_form_requests'] > 0): ?>
                        <div class="stat-sub text-danger">
                            <i class="fas fa-clock"></i> <?php echo $stats['pending_form_requests']; ?> pending
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-warning">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-number"><?php echo $stats['pending_form_requests']; ?></div>
                    <div class="stat-label">Pending Form Requests</div>
                    <div class="stat-sub text-warning">Students waiting for approval</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-teal">
                    <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                    <div class="stat-number"><?php echo $stats['total_forms']; ?></div>
                    <div class="stat-label">Total Submitted Forms</div>
                    <?php if ($stats['pending_forms'] > 0): ?>
                        <div class="stat-sub text-danger">
                            <i class="fas fa-clock"></i> <?php echo $stats['pending_forms']; ?> pending review
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-success">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['approved_forms']; ?></div>
                    <div class="stat-label">Approved Forms</div>
                    <div class="stat-sub text-success">Processed successfully</div>
                </div>
            </div>
        </div>
        
        <!-- Stats Row 2 - Applications -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card" style="border-left-color: #6c757d;">
                    <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                    <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-danger">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
                    <div class="stat-label">Pending Applications</div>
                    <div class="stat-sub text-danger">Requires your review</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-success">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['approved_applications']; ?></div>
                    <div class="stat-label">Approved Applications</div>
                    <div class="stat-sub text-success">Finalized</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-info">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-number"><?php echo formatCurrency($stats['total_cost_share']); ?></div>
                    <div class="stat-label">Total Cost-Share</div>
                    <div class="stat-sub text-muted">Department total</div>
                </div>
            </div>
        </div>
        
        <!-- Budget -->
        <div class="row g-4 mb-4">
            <div class="col-md-12">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong>Department Budget Usage</strong>
                            <span class="text-muted ms-2">
                                <?php echo formatCurrency($stats['total_cost_share']); ?> / <?php echo formatCurrency($stats['budget']); ?>
                            </span>
                        </div>
                        <span class="badge bg-<?php echo $budgetUsage > 80 ? 'danger' : ($budgetUsage > 60 ? 'warning' : 'success'); ?>">
                            <?php echo round($budgetUsage, 1); ?>% Used
                        </span>
                    </div>
                    <div class="budget-bar">
                        <div class="progress-bar <?php echo $budgetUsage > 80 ? 'bg-danger' : ($budgetUsage > 60 ? 'bg-warning' : 'bg-success'); ?>" 
                             style="width: <?php echo min($budgetUsage, 100); ?>%">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts & Quick Actions -->
        <div class="row g-4">
            <!-- Chart -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-2"></i> Monthly Applications
                    </div>
                    <div class="card-body">
                        <?php if (empty($chartData) || array_sum($chartData) == 0): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-chart-line fa-3x mb-3"></i>
                                <p>No application data available</p>
                            </div>
                        <?php else: ?>
                            <canvas id="applicationsChart" height="250"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt me-2"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="form_requests.php" class="quick-action-btn">
                                    <i class="fas fa-paper-plane text-primary"></i>
                                    <span class="label">Form Requests</span>
                                    <?php if ($stats['pending_form_requests'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $stats['pending_form_requests']; ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="form_requests.php#forms" class="quick-action-btn">
                                    <i class="fas fa-file-alt text-warning"></i>
                                    <span class="label">Review Forms</span>
                                    <?php if ($stats['pending_forms'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $stats['pending_forms']; ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="applications.php" class="quick-action-btn">
                                    <i class="fas fa-file-invoice text-success"></i>
                                    <span class="label">Applications</span>
                                    <?php if ($stats['pending_applications'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $stats['pending_applications']; ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="students.php" class="quick-action-btn">
                                    <i class="fas fa-user-graduate text-info"></i>
                                    <span class="label">Students</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-clock me-2"></i> Recent Activity
                </div>
                <div>
                    <span class="badge bg-primary me-1">Form Requests</span>
                    <span class="badge bg-warning me-1">Submitted Forms</span>
                    <span class="badge bg-success">Applications</span>
                </div>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($recentActivity)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No recent activity</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentActivity as $activity): ?>
                    <div class="activity-item">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($activity['student_code']); ?></small>
                            </div>
                            <div class="col-md-3">
                                <?php if ($activity['amount']): ?>
                                    <?php echo formatCurrency($activity['amount']); ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($activity['program']); ?></small>
                            </div>
                            <div class="col-md-3">
                                <span class="activity-type-badge <?php echo $activity['type']; ?>">
                                    <?php 
                                    if ($activity['type'] == 'form_request') echo '📨 Form Request';
                                    elseif ($activity['type'] == 'form_submitted') echo '📝 Form Submitted';
                                    else echo '📄 Application';
                                    ?>
                                </span>
                                <br>
                                <?php 
                                $statusClass = '';
                                if ($activity['status'] == 'pending') $statusClass = 'warning';
                                elseif ($activity['status'] == 'approved') $statusClass = 'success';
                                elseif ($activity['status'] == 'rejected') $statusClass = 'danger';
                                ?>
                                <span class="badge bg-<?php echo $statusClass; ?>">
                                    <?php echo ucfirst($activity['status']); ?>
                                </span>
                            </div>
                            <div class="col-md-2 text-end">
                                <small class="text-muted"><?php echo timeAgo($activity['date']); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (!empty($chartData) && array_sum($chartData) > 0): ?>
        const ctx = document.getElementById('applicationsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode($chartData); ?>,
                    backgroundColor: 'rgba(243, 156, 18, 0.6)',
                    borderColor: 'rgba(243, 156, 18, 1)',
                    borderWidth: 2,
                    borderRadius: 10
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>