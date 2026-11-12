<?php
// ============================================
// DASHBOARD
// ============================================

require_once 'includes/auth.php';
require_once 'includes/functions.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$roleName = $_SESSION['role_name'] ?? 'User';

// Get dashboard stats based on role
$stats = [];

if (Auth::hasRole(ROLE_ADMIN)) {
    // Admin stats
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $stats['users'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $stats['students'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cost_share_applications WHERE status = 'pending'");
    $stats['pending_applications'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE status = 'verified'");
    $stats['total_payments'] = $stmt->fetch()['total'] ?? 0;
    
} elseif (Auth::hasRole(ROLE_OFFICER)) {
    // Officer stats
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cost_share_applications WHERE status = 'pending'");
    $stats['pending_applications'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cost_share_applications WHERE status = 'officer_approved'");
    $stats['officer_approved'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $stats['students'] = $stmt->fetch()['total'];
    
} elseif (Auth::hasRole(ROLE_STUDENT)) {
    // Student stats
    $studentId = $_SESSION['student_id'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cost_share_applications WHERE student_id = (SELECT student_id FROM users WHERE user_id = ?)");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['applications'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cost_share_applications WHERE student_id = (SELECT student_id FROM users WHERE user_id = ?) AND status = 'approved'");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['approved_applications'] = $stmt->fetch()['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: #f0f2f5;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2C3E50, #34495E);
            padding: 20px 0;
        }
        .sidebar .brand {
            color: white;
            text-align: center;
            padding: 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .sidebar .brand h4 {
            font-weight: 700;
        }
        .sidebar .brand small {
            color: #bdc3c7;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 12px 20px;
            border-radius: 10px;
            margin: 5px 10px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .sidebar .nav-link i {
            width: 25px;
            text-align: center;
        }
        .main-content {
            padding: 30px;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            border-left: 4px solid #667eea;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-card .icon {
            font-size: 30px;
            opacity: 0.3;
            float: right;
        }
        .stats-card .number {
            font-size: 28px;
            font-weight: 700;
            color: #2C3E50;
        }
        .stats-card .label {
            color: #7f8c8d;
            font-size: 14px;
        }
        .top-nav {
            background: white;
            padding: 15px 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .welcome-text h5 {
            margin: 0;
            font-weight: 700;
            color: #2C3E50;
        }
        .welcome-text small {
            color: #7f8c8d;
        }
        .user-dropdown {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 px-0 sidebar">
                <div class="brand">
                    <h4><i class="fas fa-university"></i> WBCSMS</h4>
                    <small>Bule Hora University</small>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-chart-pie"></i> Dashboard
                    </a>
                    
                    <?php if (Auth::hasRole(ROLE_ADMIN)): ?>
                    <a class="nav-link" href="modules/admin/users.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                    <a class="nav-link" href="modules/admin/settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <?php endif; ?>
                    
                    <?php if (Auth::hasRole(ROLE_OFFICER)): ?>
                    <a class="nav-link" href="modules/officer/students.php">
                        <i class="fas fa-user-graduate"></i> Students
                    </a>
                    <a class="nav-link" href="modules/officer/applications.php">
                        <i class="fas fa-file-alt"></i> Applications
                    </a>
                    <a class="nav-link" href="modules/officer/cost_share.php">
                        <i class="fas fa-coins"></i> Cost-Share
                    </a>
                    <a class="nav-link" href="modules/officer/reports.php">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                    <?php endif; ?>
                    
                    <?php if (Auth::hasRole(ROLE_HOD)): ?>
                    <a class="nav-link" href="modules/hod/approvals.php">
                        <i class="fas fa-check-double"></i> Approvals
                    </a>
                    <a class="nav-link" href="modules/hod/department.php">
                        <i class="fas fa-building"></i> Department
                    </a>
                    <?php endif; ?>
                    
                    <?php if (Auth::hasRole(ROLE_FINANCE)): ?>
                    <a class="nav-link" href="modules/finance/payments.php">
                        <i class="fas fa-credit-card"></i> Payments
                    </a>
                    <a class="nav-link" href="modules/finance/disbursements.php">
                        <i class="fas fa-hand-holding-usd"></i> Disbursements
                    </a>
                    <?php endif; ?>
                    
                    <?php if (Auth::hasRole(ROLE_STUDENT)): ?>
                    <a class="nav-link" href="modules/student/apply.php">
                        <i class="fas fa-pen-fancy"></i> Apply
                    </a>
                    <a class="nav-link" href="modules/student/status.php">
                        <i class="fas fa-info-circle"></i> Status
                    </a>
                    <?php endif; ?>
                    
                    <a class="nav-link" href="modules/notices.php">
                        <i class="fas fa-bullhorn"></i> Notices
                    </a>
                    <a class="nav-link" href="modules/feedback.php">
                        <i class="fas fa-comment"></i> Feedback
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <!-- Top Navigation -->
                <div class="top-nav d-flex justify-content-between align-items-center">
                    <div class="welcome-text">
                        <h5>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h5>
                        <small><i class="fas fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?></small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-primary me-3"><?php echo ucfirst($roleName); ?></span>
                        <div class="user-dropdown" onclick="toggleUserMenu()">
                            <img src="assets/images/avatar.png" alt="Avatar" width="40" height="40" 
                                 class="rounded-circle border" onerror="this.src='https://via.placeholder.com/40'">
                            <i class="fas fa-chevron-down ms-1"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <?php if (isset($stats['users'])): ?>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="icon"><i class="fas fa-users"></i></div>
                            <div class="number"><?php echo $stats['users']; ?></div>
                            <div class="label">Total Users</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($stats['students'])): ?>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="icon"><i class="fas fa-user-graduate"></i></div>
                            <div class="number"><?php echo $stats['students']; ?></div>
                            <div class="label">Registered Students</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($stats['pending_applications'])): ?>
                    <div class="col-md-3">
                        <div class="stats-card" style="border-left-color: #f39c12;">
                            <div class="icon"><i class="fas fa-clock"></i></div>
                            <div class="number"><?php echo $stats['pending_applications']; ?></div>
                            <div class="label">Pending Applications</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($stats['total_payments'])): ?>
                    <div class="col-md-3">
                        <div class="stats-card" style="border-left-color: #27ae60;">
                            <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                            <div class="number"><?php echo formatCurrency($stats['total_payments']); ?></div>
                            <div class="label">Total Payments</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($stats['applications'])): ?>
                    <div class="col-md-3">
                        <div class="stats-card" style="border-left-color: #3498db;">
                            <div class="icon"><i class="fas fa-file-alt"></i></div>
                            <div class="number"><?php echo $stats['applications']; ?></div>
                            <div class="label">My Applications</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($stats['approved_applications'])): ?>
                    <div class="col-md-3">
                        <div class="stats-card" style="border-left-color: #2ecc71;">
                            <div class="icon"><i class="fas fa-check-circle"></i></div>
                            <div class="number"><?php echo $stats['approved_applications']; ?></div>
                            <div class="label">Approved Applications</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions / Recent Activity -->
                <div class="row g-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="fas fa-bell me-2"></i> Recent Activity</h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p>No recent activity to display</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="fas fa-calendar-check me-2"></i> Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <?php if (Auth::hasRole(ROLE_STUDENT)): ?>
                                    <a href="modules/student/apply.php" class="btn btn-primary">
                                        <i class="fas fa-pen-fancy"></i> Apply for Cost-Share
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (Auth::hasRole(ROLE_OFFICER)): ?>
                                    <a href="modules/officer/applications.php" class="btn btn-warning">
                                        <i class="fas fa-check-circle"></i> Review Applications
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (Auth::hasRole(ROLE_FINANCE)): ?>
                                    <a href="modules/finance/payments.php" class="btn btn-success">
                                        <i class="fas fa-credit-card"></i> Verify Payments
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="modules/notices.php" class="btn btn-info">
                                        <i class="fas fa-bullhorn"></i> View Notices
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleUserMenu() {
            // Implement user dropdown menu
            alert('User menu will be implemented in the full version');
        }
    </script>
</body>
</html>