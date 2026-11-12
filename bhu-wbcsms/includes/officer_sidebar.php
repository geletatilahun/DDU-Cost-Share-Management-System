<?php
// ============================================
// OFFICER SIDEBAR
// ============================================

// Get current page name for active class
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
/* Sidebar Styles */
.sidebar {
    min-height: 100vh;
    background: linear-gradient(180deg, #1a1a2e, #16213e, #0f3460);
    padding: 20px 0;
    position: fixed;
    width: 250px;
    z-index: 1000;
    transition: all 0.3s;
    overflow-y: auto;
}

.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
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
    margin: 0;
    font-size: 18px;
}

.sidebar .brand h4 i {
    color: #2ecc71;
}

.sidebar .brand small {
    color: #bdc3c7;
    font-size: 11px;
    display: block;
    margin-top: 5px;
}

.sidebar .nav-divider {
    border-color: rgba(255,255,255,0.05);
    margin: 10px 15px;
}

.sidebar .nav-link {
    color: rgba(255,255,255,0.7);
    padding: 12px 20px;
    border-radius: 10px;
    margin: 3px 10px;
    transition: all 0.3s;
    text-decoration: none;
    display: flex;
    align-items: center;
    font-size: 14px;
    position: relative;
}

.sidebar .nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    transform: translateX(5px);
}

.sidebar .nav-link.active {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
    color: white;
    box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
}

.sidebar .nav-link i {
    width: 25px;
    text-align: center;
    font-size: 16px;
}

.sidebar .nav-link span {
    margin-left: 10px;
}

.sidebar .nav-link .badge {
    margin-left: auto;
    background: rgba(255,255,255,0.2);
    color: white;
    font-size: 11px;
    padding: 3px 10px;
}

.sidebar .nav-link .badge.bg-danger {
    background: #e74c3c !important;
    animation: pulse-badge 2s infinite;
}

@keyframes pulse-badge {
    0% { opacity: 1; }
    50% { opacity: 0.6; }
    100% { opacity: 1; }
}

.sidebar .nav-link.text-danger:hover {
    background: rgba(231, 76, 60, 0.2);
    color: #e74c3c !important;
}

.sidebar .nav-label {
    color: rgba(255,255,255,0.3);
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 10px 20px 5px;
    font-weight: 600;
}

/* Main Content */
.main-content {
    margin-left: 250px;
    padding: 30px;
    min-height: 100vh;
    background: #f0f2f5;
}

/* Top Navigation */
.top-nav {
    background: white;
    padding: 15px 25px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.top-nav h5 {
    margin: 0;
    font-weight: 700;
    color: #2C3E50;
}

.top-nav h5 i {
    color: #2ecc71;
    margin-right: 10px;
}

.top-nav small {
    color: #7f8c8d;
    font-size: 13px;
}

/* Stats Card */
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

/* Responsive */
@media (max-width: 992px) {
    .sidebar {
        width: 70px;
    }
    .sidebar .brand h4,
    .sidebar .brand small,
    .sidebar .nav-link span,
    .sidebar .nav-link .badge,
    .sidebar .nav-label {
        display: none;
    }
    .sidebar .nav-link {
        justify-content: center;
        padding: 12px;
        margin: 3px 5px;
    }
    .sidebar .nav-link i {
        margin: 0;
        font-size: 20px;
    }
    .main-content {
        margin-left: 70px;
        padding: 15px;
    }
    .top-nav {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
}

@media (max-width: 576px) {
    .sidebar {
        width: 60px;
    }
    .main-content {
        margin-left: 60px;
        padding: 10px;
    }
    .stat-card .stat-number {
        font-size: 20px;
    }
}

/* Print Styles */
@media print {
    .sidebar {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .top-nav {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<div class="sidebar">
    <div class="brand">
        <h4><i class="fas fa-university"></i> WBCSMS</h4>
        <small>Officer Panel</small>
    </div>
    
    <nav class="nav flex-column">
        <!-- Dashboard -->
        <a class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        
        <hr class="nav-divider">
        
        <!-- Students -->
        <a class="nav-link <?php echo $currentPage == 'students.php' ? 'active' : ''; ?>" href="students.php">
            <i class="fas fa-user-graduate"></i> <span>Students</span>
            <?php
            // Count total active students
            global $pdo;
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM students WHERE enrollment_status = 'active'");
            $studentCount = $stmt->fetch()['total'] ?? 0;
            if ($studentCount > 0): ?>
                <span class="badge"><?php echo $studentCount; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Applications -->
        <a class="nav-link <?php echo ($currentPage == 'applications.php' || $currentPage == 'approve.php') ? 'active' : ''; ?>" href="applications.php">
            <i class="fas fa-file-alt"></i> <span>Applications</span>
            <?php
            // Count pending applications
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM cost_share_applications WHERE status = 'pending'");
            $pendingCount = $stmt->fetch()['total'] ?? 0;
            if ($pendingCount > 0): ?>
                <span class="badge bg-danger"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Approve (Quick Action) -->
        <a class="nav-link <?php echo $currentPage == 'approve.php' ? 'active' : ''; ?>" href="approve.php">
            <i class="fas fa-check-double"></i> <span>Quick Approve</span>
            <?php if ($pendingCount > 0): ?>
                <span class="badge bg-danger"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Cost-Share -->
        <a class="nav-link <?php echo $currentPage == 'cost_share.php' ? 'active' : ''; ?>" href="cost_share.php">
            <i class="fas fa-coins"></i> <span>Cost-Share</span>
        </a>
        
        <hr class="nav-divider">
        
        <!-- Reports -->
        <a class="nav-link <?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
            <i class="fas fa-chart-bar"></i> <span>Reports</span>
        </a>
        
        <!-- Feedback -->
        <a class="nav-link <?php echo $currentPage == 'feedback.php' ? 'active' : ''; ?>" href="feedback.php">
            <i class="fas fa-comment"></i> <span>Feedback</span>
            <?php
            // Count pending feedback
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM feedback WHERE status = 'pending'");
            $feedbackCount = $stmt->fetch()['total'] ?? 0;
            if ($feedbackCount > 0): ?>
                <span class="badge bg-danger"><?php echo $feedbackCount; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Notices -->
        <a class="nav-link <?php echo $currentPage == 'notices.php' ? 'active' : ''; ?>" href="notices.php">
            <i class="fas fa-bullhorn"></i> <span>Notices</span>
            <?php
            // Count active notices
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM notices WHERE is_active = 1");
            $noticeCount = $stmt->fetch()['total'] ?? 0;
            if ($noticeCount > 0): ?>
                <span class="badge"><?php echo $noticeCount; ?></span>
            <?php endif; ?>
        </a>
        
        <hr class="nav-divider">
        
        <!-- Settings -->
        <a class="nav-link <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
            <i class="fas fa-cog"></i> <span>Settings</span>
        </a>
        
        <!-- Logout -->
        <a class="nav-link text-danger" href="../../logout.php">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </nav>
</div>