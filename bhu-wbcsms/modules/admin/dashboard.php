<?php
// ============================================
// ADMIN - ENHANCED DASHBOARD
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireRole(ROLE_ADMIN);

// Get statistics
$stats = [];

// Total Users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $stmt->fetch()['total'];

// Active Users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
$stats['active_users'] = $stmt->fetch()['total'];

// Total Students
$stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
$stats['total_students'] = $stmt->fetch()['total'];

// Total Applications
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cost_share_applications");
$stats['total_applications'] = $stmt->fetch()['total'];

// Pending Applications
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cost_share_applications WHERE status = 'pending'");
$stats['pending_applications'] = $stmt->fetch()['total'];

// Total Payments
$stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE status = 'verified'");
$stats['total_payments'] = $stmt->fetch()['total'] ?? 0;

// Pending Payments
$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'");
$stats['pending_payments'] = $stmt->fetch()['total'];

// Total Disbursements
$stmt = $pdo->query("SELECT SUM(amount) as total FROM disbursements WHERE status = 'completed'");
$stats['total_disbursements'] = $stmt->fetch()['total'] ?? 0;

// Recent Activity
$recentActivity = $pdo->query("
    SELECT * FROM audit_logs 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

// System Health
$systemHealth = [
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'mysql_version' => $pdo->query("SELECT VERSION()")->fetchColumn(),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'memory_limit' => ini_get('memory_limit'),
];

// Chart Data - Monthly Applications (with fallback data)
$monthlyData = $pdo->query("
    SELECT 
        MONTHNAME(created_at) as month,
        COUNT(*) as count
    FROM cost_share_applications 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY MONTH(created_at)
    ORDER BY MONTH(created_at)
")->fetchAll();

// If no data, create sample data for demo
if (empty($monthlyData)) {
    $months = ['January', 'February', 'March', 'April', 'May', 'June'];
    $chartLabels = $months;
    $chartData = [0, 0, 0, 0, 0, 0];
} else {
    $chartLabels = [];
    $chartData = [];
    foreach ($monthlyData as $row) {
        $chartLabels[] = $row['month'];
        $chartData[] = $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 4px solid #667eea;
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
        
        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-item .time {
            color: #95a5a6;
            font-size: 12px;
        }
        
        .system-health-item {
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
            <div>
                <h5><i class="fas fa-chart-pie"></i> Admin Dashboard</h5>
                <small>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></small>
            </div>
            <div>
                <span class="badge bg-success me-2"><i class="fas fa-circle"></i> System Online</span>
                <span class="badge bg-info"><?php echo date('l, F d, Y h:i A'); ?></span>
            </div>
        </div>
        
        <!-- Quick Stats Row -->
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-primary">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                    <small class="text-muted"><?php echo $stats['active_users']; ?> active</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-success">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                    <div class="stat-label">Total Students</div>
                    <small class="text-muted">Registered in system</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-warning">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                    <div class="stat-label">Applications</div>
                    <small class="text-danger"><?php echo $stats['pending_applications']; ?> pending</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-danger">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-number"><?php echo formatCurrency($stats['total_payments']); ?></div>
                    <div class="stat-label">Total Payments</div>
                    <small class="text-muted"><?php echo $stats['pending_payments']; ?> pending verification</small>
                </div>
            </div>
        </div>
        
        <!-- Second Row -->
        <div class="row g-4 mt-2">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-info">
                    <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="stat-number"><?php echo formatCurrency($stats['total_disbursements']); ?></div>
                    <div class="stat-label">Total Disbursements</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-purple">
                    <div class="stat-icon"><i class="fas fa-building"></i></div>
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM departments");
                    $depts = $stmt->fetch()['total'];
                    ?>
                    <div class="stat-number"><?php echo $depts; ?></div>
                    <div class="stat-label">Departments</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card" style="border-left-color: #e67e22;">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM academic_years WHERE is_current = 1");
                    $currentYear = $stmt->fetch()['total'] ? 'Active' : 'None';
                    ?>
                    <div class="stat-number"><?php echo $currentYear; ?></div>
                    <div class="stat-label">Academic Year</div>
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
                </div>
            </div>
        </div>
        
        <!-- Charts and Tables -->
        <div class="row g-4 mt-3">
            <!-- Chart -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-2"></i> Monthly Applications
                    </div>
                    <div class="card-body">
                        <canvas id="applicationsChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- System Health -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-heartbeat me-2"></i> System Health
                    </div>
                    <div class="card-body">
                        <div class="system-health-item">
                            <strong>PHP Version:</strong> <?php echo $systemHealth['php_version']; ?>
                        </div>
                        <div class="system-health-item">
                            <strong>MySQL Version:</strong> <?php echo $systemHealth['mysql_version']; ?>
                        </div>
                        <div class="system-health-item">
                            <strong>Server:</strong> <?php echo $systemHealth['server']; ?>
                        </div>
                        <div class="system-health-item">
                            <strong>Max Upload:</strong> <?php echo $systemHealth['upload_max_filesize']; ?>
                        </div>
                        <div class="system-health-item">
                            <strong>Memory Limit:</strong> <?php echo $systemHealth['memory_limit']; ?>
                        </div>
                        <div class="system-health-item">
                            <strong>Database Size:</strong> 
                            <?php
                            $stmt = $pdo->query("
                                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size 
                                FROM information_schema.TABLES 
                                WHERE table_schema = 'wbcsms_db'
                            ");
                            $dbSize = $stmt->fetch()['size'] ?? 0;
                            echo $dbSize . ' MB';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity & Quick Actions -->
        <div class="row g-4 mt-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clock me-2"></i> Recent Activity
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
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity['action_type']); ?></strong>
                                        <span class="text-muted ms-2"><?php echo htmlspecialchars($activity['action_description']); ?></span>
                                    </div>
                                    <span class="badge bg-<?php echo $activity['status'] == 'success' ? 'success' : 'danger'; ?>">
                                        <?php echo $activity['status']; ?>
                                    </span>
                                </div>
                                <div class="time">
                                    <i class="far fa-clock"></i> <?php echo timeAgo($activity['created_at']); ?>
                                    <span class="ms-3"><i class="fas fa-globe"></i> <?php echo $activity['ip_address'] ?? 'N/A'; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt me-2"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="users.php" class="btn btn-outline-primary">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                            <a href="departments.php" class="btn btn-outline-success">
                                <i class="fas fa-building"></i> Manage Departments
                            </a>
                            <a href="settings.php" class="btn btn-outline-info">
                                <i class="fas fa-cog"></i> System Settings
                            </a>
                            <a href="backup.php" class="btn btn-outline-warning">
                                <i class="fas fa-database"></i> Backup Database
                            </a>
                            <a href="logs.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list"></i> View Audit Logs
                            </a>
                            <a href="notices.php" class="btn btn-outline-danger">
                                <i class="fas fa-bullhorn"></i> Post Notice
                            <a href="feedback.php" class="btn btn-outline-danger">
                                <i class="fas fa-bullhorn"></i> Feedback
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Applications Chart
        const ctx = document.getElementById('applicationsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode($chartData); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.6)',
                    borderColor: 'rgba(102, 126, 234, 1)',
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
    </script>
</body>
</html>