<?php
// ============================================
// ADMIN SIDEBAR
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
    color: #667eea;
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
}

.sidebar .nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    transform: translateX(5px);
}

.sidebar .nav-link.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
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
}

.sidebar .nav-link.text-danger:hover {
    background: rgba(231, 76, 60, 0.2);
    color: #e74c3c !important;
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
    color: #667eea;
    margin-right: 10px;
}

.top-nav small {
    color: #7f8c8d;
}

/* Responsive */
@media (max-width: 992px) {
    .sidebar {
        width: 70px;
    }
    .sidebar .brand h4,
    .sidebar .brand small,
    .sidebar .nav-link span,
    .sidebar .nav-link .badge {
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
    }
    .top-nav .d-flex {
        margin-top: 10px;
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
}
</style>

<div class="sidebar">
    <div class="brand">
        <h4><i class="fas fa-university"></i> WBCSMS</h4>
        <small>Admin Panel</small>
    </div>
    <nav class="nav flex-column">
        <!-- Dashboard -->
        <a class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" 
           href="dashboard.php">
            <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        
        <hr class="nav-divider">
        
        <!-- User Management -->
        <a class="nav-link <?php echo $currentPage == 'users.php' ? 'active' : ''; ?>" 
           href="users.php">
            <i class="fas fa-users"></i> <span>Users</span>
            <?php
            // Count total users
            global $pdo;
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
            $userCount = $stmt->fetch()['total'] ?? 0;
            ?>
            <span class="badge"><?php echo $userCount; ?></span>
        </a>
        
        <!-- Departments -->
        <a class="nav-link <?php echo $currentPage == 'departments.php' ? 'active' : ''; ?>" 
           href="departments.php">
            <i class="fas fa-building"></i> <span>Departments</span>
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM departments");
            $deptCount = $stmt->fetch()['total'] ?? 0;
            ?>
            <span class="badge"><?php echo $deptCount; ?></span>
        </a>
        
        <!-- Academic Years -->
        <a class="nav-link <?php echo $currentPage == 'academic_years.php' ? 'active' : ''; ?>" 
           href="academic_years.php">
            <i class="fas fa-calendar-alt"></i> <span>Academic Years</span>
        </a>
        
        <hr class="nav-divider">
        
        <!-- Settings -->
        <a class="nav-link <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>" 
           href="settings.php">
            <i class="fas fa-cog"></i> <span>Settings</span>
        </a>
        
        <!-- Audit Logs -->
        <a class="nav-link <?php echo $currentPage == 'logs.php' ? 'active' : ''; ?>" 
           href="logs.php">
            <i class="fas fa-list"></i> <span>Audit Logs</span>
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM audit_logs WHERE DATE(created_at) = CURDATE()");
            $logCount = $stmt->fetch()['total'] ?? 0;
            if ($logCount > 0): ?>
            <span class="badge bg-danger"><?php echo $logCount; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Backup -->
        <a class="nav-link <?php echo $currentPage == 'backup.php' ? 'active' : ''; ?>" 
           href="backup.php">
            <i class="fas fa-database"></i> <span>Backup</span>
        </a>
        
        <hr class="nav-divider">
        
        <!-- Notices -->
        <a class="nav-link <?php echo $currentPage == 'notices.php' ? 'active' : ''; ?>" 
           href="notices.php">
            <i class="fas fa-bullhorn"></i> <span>Notices</span>
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM notices WHERE is_active = 1");
            $noticeCount = $stmt->fetch()['total'] ?? 0;
            if ($noticeCount > 0): ?>
            <span class="badge bg-warning"><?php echo $noticeCount; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Feedback -->
        <a class="nav-link <?php echo $currentPage == 'feedback.php' ? 'active' : ''; ?>" 
           href="feedback.php">
            <i class="fas fa-comment"></i> <span>Feedback</span>
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM feedback WHERE status = 'pending'");
            $feedbackCount = $stmt->fetch()['total'] ?? 0;
            if ($feedbackCount > 0): ?>
            <span class="badge bg-danger"><?php echo $feedbackCount; ?></span>
            <?php endif; ?>
        </a>
        
        <hr class="nav-divider">
        
        <!-- Logout -->
        <a class="nav-link text-danger" href="../../logout.php">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </nav>
</div>