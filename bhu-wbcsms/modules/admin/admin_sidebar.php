<?php
// ============================================
// ADMIN SIDEBAR - Include this in all admin pages
// ============================================
?>
<div class="sidebar">
    <div class="brand">
        <h4><i class="fas fa-university"></i> WBCSMS</h4>
        <small>Admin Panel</small>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
           href="dashboard.php">
            <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        
        <hr class="nav-divider">
        
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" 
           href="users.php">
            <i class="fas fa-users"></i> <span>Users</span>
        </a>
        
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'departments.php' ? 'active' : ''; ?>" 
           href="departments.php">
            <i class="fas fa-building"></i> <span>Departments</span>
        </a>
        
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'academic_years.php' ? 'active' : ''; ?>" 
           href="academic_years.php">
            <i class="fas fa-calendar-alt"></i> <span>Academic Years</span>
        </a>
        
        <hr class="nav-divider">
        
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" 
           href="settings.php">
            <i class="fas fa-cog"></i> <span>Settings</span>
        </a>
        
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>" 
           href="logs.php">
            <i class="fas fa-list"></i> <span>Audit Logs</span>
        </a>
        
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'active' : ''; ?>" 
           href="backup.php">
            <i class="fas fa-database"></i> <span>Backup</span>
        </a>
        
        <hr class="nav-divider">
        
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notices.php' ? 'active' : ''; ?>" 
           href="notices.php">
            <i class="fas fa-bullhorn"></i> <span>Notices</span>
        </a>
        
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'feedback.php' ? 'active' : ''; ?>" 
           href="feedback.php">
            <i class="fas fa-comment"></i> <span>Feedback</span>
        </a>
        
        <hr class="nav-divider">
        
        <a class="nav-link text-danger" href="../../logout.php">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </nav>
</div>

<style>
.sidebar {
    min-height: 100vh;
    background: linear-gradient(180deg, #1a1a2e, #16213e, #0f3460);
    padding: 20px 0;
    position: fixed;
    width: 250px;
    z-index: 1000;
    transition: all 0.3s;
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

.sidebar .brand small {
    color: #bdc3c7;
    font-size: 11px;
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
}

.sidebar .nav-link.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.sidebar .nav-link i {
    width: 25px;
    text-align: center;
    font-size: 16px;
}

.sidebar .nav-link span {
    margin-left: 10px;
}

.main-content {
    margin-left: 250px;
    padding: 30px;
    min-height: 100vh;
}

@media (max-width: 992px) {
    .sidebar {
        width: 70px;
    }
    .sidebar .brand h4,
    .sidebar .brand small,
    .sidebar .nav-link span {
        display: none;
    }
    .sidebar .nav-link {
        justify-content: center;
        padding: 12px;
    }
    .sidebar .nav-link i {
        margin: 0;
        font-size: 20px;
    }
    .main-content {
        margin-left: 70px;
    }
}
</style>