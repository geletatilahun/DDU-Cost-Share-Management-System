<?php
// ============================================
// OFFICER - DASHBOARD
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_OFFICER);

// Get statistics
$stats = [];

// Total Students
$stmt = $pdo->query("SELECT COUNT(*) as total FROM students WHERE enrollment_status = 'active'");
$stats['total_students'] = $stmt->fetch()['total'];

// Pending Applications
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cost_share_applications WHERE status = 'pending'");
$stats['pending_applications'] = $stmt->fetch()['total'];

// Total Applications
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cost_share_applications");
$stats['total_applications'] = $stmt->fetch()['total'];

// Approved Applications
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cost_share_applications WHERE status = 'officer_approved'");
$stats['approved_applications'] = $stmt->fetch()['total'];

// Total Cost-Share Amount
$stmt = $pdo->query("SELECT SUM(requested_amount) as total FROM cost_share_applications WHERE status IN ('officer_approved', 'approved')");
$stats['total_cost_share'] = $stmt->fetch()['total'] ?? 0;

// Recent Applications
$recentApplications = $pdo->query("
    SELECT a.*, s.full_name as student_name, s.student_code
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    ORDER BY a.created_at DESC
    LIMIT 10
")->fetchAll();

// Today's Applications
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM cost_share_applications 
    WHERE DATE(created_at) = CURDATE()
");
$stats['today_applications'] = $stmt->fetch()['total'];

// This Week's Applications
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM cost_share_applications 
    WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())
");
$stats['weekly_applications'] = $stmt->fetch()['total'];

// Students by Program
$programStats = $pdo->query("
    SELECT program, COUNT(*) as count 
    FROM students 
    WHERE enrollment_status = 'active'
    GROUP BY program 
    ORDER BY count DESC 
    LIMIT 5
")->fetchAll();

// Get status counts for quick view
$statusCounts = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM cost_share_applications 
    GROUP BY status
")->fetchAll();

$statusLabels = [];
$statusData = [];
foreach ($statusCounts as $row) {
    $statusLabels[] = ucfirst(str_replace('_', ' ', $row['status']));
    $statusData[] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 4px solid #2ecc71;
            margin-bottom: 20px;
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
        
        .application-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f2f5;
            transition: all 0.3s;
        }
        .application-item:hover {
            background: #f8f9fa;
        }
        .application-item:last-child {
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
            border-color: #667eea;
            color: #667eea;
        }
        .quick-action-btn i {
            font-size: 30px;
            display: block;
            margin-bottom: 10px;
        }
        .quick-action-btn .label {
            font-weight: 600;
            font-size: 14px;
        }
        .quick-action-btn .badge {
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/officer_sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
            <div>
                <h5><i class="fas fa-chart-pie"></i> Officer Dashboard</h5>
                <small>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></small>
            </div>
            <div>
                <span class="badge bg-success me-2"><i class="fas fa-circle"></i> System Online</span>
                <span class="badge bg-info"><?php echo date('l, F d, Y h:i A'); ?></span>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-primary">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                    <div class="stat-label">Total Students</div>
                    <small class="text-muted">Active students</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-warning">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
                    <div class="stat-label">Pending Applications</div>
                    <small class="text-danger">Requires your attention</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-success">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['approved_applications']; ?></div>
                    <div class="stat-label">Approved</div>
                    <small class="text-muted">Ready for HOD approval</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-info">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-number"><?php echo formatCurrency($stats['total_cost_share']); ?></div>
                    <div class="stat-label">Total Cost-Share</div>
                    <small class="text-muted">Approved amounts</small>
                </div>
            </div>
        </div>
        
        <!-- Second Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-danger">
                    <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-number"><?php echo $stats['today_applications']; ?></div>
                    <div class="stat-label">Today's Applications</div>
                    <small class="text-muted">New submissions</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-purple">
                    <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                    <div class="stat-number"><?php echo $stats['weekly_applications']; ?></div>
                    <div class="stat-label">This Week</div>
                    <small class="text-muted">Total applications</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card" style="border-left-color: #e67e22;">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                    <div class="stat-label">Total Applications</div>
                    <small class="text-muted">All time</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card" style="border-left-color: #e74c3c;">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM feedback WHERE status = 'pending'");
                    $pendingFeedback = $stmt->fetch()['total'];
                    ?>
                    <div class="stat-number"><?php echo $pendingFeedback; ?></div>
                    <div class="stat-label">Pending Feedback</div>
                    <small class="text-danger">Needs response</small>
                </div>
            </div>
        </div>
        
        <!-- Charts and Quick Actions -->
        <div class="row g-4">
            <!-- Status Chart -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-pie me-2"></i> Application Status Distribution
                    </div>
                    <div class="card-body">
                        <?php if (empty($statusData) || array_sum($statusData) == 0): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No applications to display</p>
                            </div>
                        <?php else: ?>
                            <canvas id="statusChart" height="250"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt me-2"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="applications.php" class="quick-action-btn">
                                    <i class="fas fa-file-alt text-primary"></i>
                                    <span class="label">Review Applications</span>
                                    <?php if ($stats['pending_applications'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $stats['pending_applications']; ?> Pending</span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="students.php" class="quick-action-btn">
                                    <i class="fas fa-user-graduate text-success"></i>
                                    <span class="label">Manage Students</span>
                                    <span class="badge bg-info"><?php echo $stats['total_students']; ?></span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="cost_share.php" class="quick-action-btn">
                                    <i class="fas fa-coins text-warning"></i>
                                    <span class="label">Register Cost-Share</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="reports.php" class="quick-action-btn">
                                    <i class="fas fa-chart-bar text-danger"></i>
                                    <span class="label">Generate Reports</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Applications -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-clock me-2"></i> Recent Applications
                </div>
                <a href="applications.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($recentApplications)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No applications found</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentApplications as $app): ?>
                    <div class="application-item">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <strong><?php echo htmlspecialchars($app['student_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($app['student_code']); ?></small>
                            </div>
                            <div class="col-md-3">
                                <span class="badge bg-<?php 
                                    echo $app['status'] == 'pending' ? 'warning' : 
                                        ($app['status'] == 'approved' ? 'success' : 
                                        ($app['status'] == 'rejected' ? 'danger' : 'info')); 
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                </span>
                            </div>
                            <div class="col-md-3">
                                <?php echo formatCurrency($app['requested_amount']); ?>
                            </div>
                            <div class="col-md-2 text-end">
                                <small class="text-muted"><?php echo timeAgo($app['created_at']); ?></small>
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
        <?php if (!empty($statusData) && array_sum($statusData) > 0): ?>
        // Status Chart
        const ctx = document.getElementById('statusChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($statusLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($statusData); ?>,
                    backgroundColor: [
                        '#f39c12',  // pending
                        '#3498db',  // officer_approved
                        '#2ecc71',  // approved
                        '#e74c3c',  // rejected
                        '#9b59b6',  // disbursed
                        '#1abc9c'   // other
                    ],
                    borderWidth: 2,
                    borderColor: 'white'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20
                        }
                    }
                },
                cutout: '60%'
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>