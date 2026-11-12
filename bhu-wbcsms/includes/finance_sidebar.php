<?php
// ============================================
// FINANCE SIDEBAR
// ============================================

$currentPage = basename($_SERVER['PHP_SELF']);

// Get pending counts
$pendingVerifications = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'")->fetch()['total'] ?? 0;
$pendingDisbursements = $pdo->query("SELECT COUNT(*) as total FROM disbursements WHERE status = 'pending'")->fetch()['total'] ?? 0;
?>
<style>
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
    color: #e74c3c;
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
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
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
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.6; }
    100% { opacity: 1; }
}

.main-content {
    margin-left: 250px;
    padding: 30px;
    min-height: 100vh;
    background: #f0f2f5;
}

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
        padding: 15px;
    }
}
</style>

<div class="sidebar">
    <div class="brand">
        <h4><i class="fas fa-university"></i> WBCSMS</h4>
        <small>Finance Panel</small>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        <hr class="nav-divider">
        <a class="nav-link <?php echo $currentPage == 'payments.php' ? 'active' : ''; ?>" href="payments.php">
            <i class="fas fa-credit-card"></i> <span>Payments</span>
            <?php if ($pendingVerifications > 0): ?>
                <span class="badge bg-danger"><?php echo $pendingVerifications; ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link <?php echo $currentPage == 'disbursements.php' ? 'active' : ''; ?>" href="disbursements.php">
            <i class="fas fa-hand-holding-usd"></i> <span>Disbursements</span>
            <?php if ($pendingDisbursements > 0): ?>
                <span class="badge bg-danger"><?php echo $pendingDisbursements; ?></span>
            <?php endif; ?>
        </a>
        <hr class="nav-divider">
        <a class="nav-link <?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
            <i class="fas fa-chart-bar"></i> <span>Reports</span>
        </a>
        <a class="nav-link <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
            <i class="fas fa-cog"></i> <span>Settings</span>
        </a>
        <hr class="nav-divider">
        <a class="nav-link text-danger" href="../../logout.php">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </nav>
</div>